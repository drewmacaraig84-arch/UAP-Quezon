<?php
/**
 * Dues & Payment Business Logic Service
 */

/**
 * Determine the status of a member due based on total paid vs amount due
 *
 * @param float $total_paid
 * @param float $amount_due
 * @return string 'paid'|'partial'|'unpaid'
 */
function calculate_due_status($total_paid, $amount_due) {
    $total_paid = round((float)$total_paid, 2);
    $amount_due = round((float)$amount_due, 2);

    if ($total_paid >= $amount_due && $amount_due > 0) {
        return 'paid';
    } elseif ($total_paid > 0) {
        return 'partial';
    }
    return 'unpaid';
}

/**
 * Generate a sequential official receipt number (UAP-YYYY-XXXXX)
 *
 * @param PDO $pdo
 * @param int|null $year
 * @return string
 */
function generate_receipt_number(PDO $pdo, $year = null) {
    $year = $year ?: date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM receipts WHERE receipt_number LIKE ?");
    $stmt->execute(['UAP-' . $year . '-%']);
    $count = (int)$stmt->fetchColumn();
    return sprintf('UAP-%s-%05d', $year, $count + 1);
}

/**
 * Get remaining balance for a specific member due
 *
 * @param PDO $pdo
 * @param int $member_due_id
 * @return array ['total_due' => float, 'total_paid' => float, 'remaining' => float, 'status' => string]
 */
function get_member_due_summary(PDO $pdo, $member_due_id) {
    $stmt = $pdo->prepare("
        SELECT md.id, 
               COALESCE(md.custom_amount, d.amount) as amount_due,
               COALESCE(SUM(CASE WHEN p.status = 'verified' THEN p.amount_paid ELSE 0 END), 0) as verified_paid
        FROM member_dues md
        JOIN dues d ON md.due_id = d.id
        LEFT JOIN payments p ON p.member_due_id = md.id
        WHERE md.id = ?
        GROUP BY md.id, md.custom_amount, d.amount
    ");
    $stmt->execute([$member_due_id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        return null;
    }

    $amount_due = round((float)$row['amount_due'], 2);
    $verified_paid = round((float)$row['verified_paid'], 2);
    $remaining = max(0, round($amount_due - $verified_paid, 2));

    return [
        'amount_due' => $amount_due,
        'verified_paid' => $verified_paid,
        'remaining' => $remaining,
        'status' => calculate_due_status($verified_paid, $amount_due)
    ];
}

/**
 * Soft delete a due package
 *
 * @param PDO $pdo
 * @param int $dueId
 * @param int|null $adminId
 * @return bool
 */
function soft_delete_due_package(PDO $pdo, int $dueId, ?int $adminId = null): bool {
    try {
        $stmt = $pdo->prepare("UPDATE dues SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
        $res = $stmt->execute([$adminId, $dueId]);
        if ($res && function_exists('audit_log')) {
            audit_log('DUE_PACKAGE_SOFT_DELETED', 'DUES', "Soft deleted due package ID {$dueId}. 7-day retention started.", $adminId);
        }
        return $res;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Restore a soft-deleted due package back to active
 *
 * @param PDO $pdo
 * @param int $dueId
 * @return bool
 */
function restore_due_package(PDO $pdo, int $dueId): bool {
    try {
        $stmt = $pdo->prepare("UPDATE dues SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
        $res = $stmt->execute([$dueId]);
        if ($res && function_exists('audit_log')) {
            $adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            audit_log('DUE_PACKAGE_RESTORED', 'DUES', "Restored due package ID {$dueId} to active list.", $adminId);
        }
        return $res;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Permanently purge a due package (cascading receipts, payments, member_dues, and dues)
 *
 * @param PDO $pdo
 * @param int $dueId
 * @return bool
 */
function purge_due_package(PDO $pdo, int $dueId): bool {
    $inTx = $pdo->inTransaction();
    try {
        if (!$inTx) {
            $pdo->beginTransaction();
        }

        $md_ids = $pdo->prepare("SELECT id FROM member_dues WHERE due_id = ?");
        $md_ids->execute([$dueId]);
        $md_rows = $md_ids->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($md_rows)) {
            foreach ($md_rows as $md_id) {
                $pay_ids = $pdo->prepare("SELECT id FROM payments WHERE member_due_id = ?");
                $pay_ids->execute([$md_id]);
                $p_rows = $pay_ids->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($p_rows)) {
                    $ph = implode(',', array_fill(0, count($p_rows), '?'));
                    $pdo->prepare("DELETE FROM receipts WHERE payment_id IN ($ph)")->execute(array_values($p_rows));
                    $pdo->prepare("DELETE FROM payments WHERE id IN ($ph)")->execute(array_values($p_rows));
                }
            }
            $phMd = implode(',', array_fill(0, count($md_rows), '?'));
            $pdo->prepare("DELETE FROM member_dues WHERE id IN ($phMd)")->execute(array_values($md_rows));
        }

        $pdo->prepare("DELETE FROM dues WHERE id = ?")->execute([$dueId]);

        if (!$inTx) {
            $pdo->commit();
        }

        if (function_exists('audit_log')) {
            $adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            audit_log('DUE_PACKAGE_PURGED', 'DUES', "Permanently purged due package ID {$dueId} and cascaded all associated records.", $adminId);
        }

        return true;
    } catch (Throwable $e) {
        if (!$inTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

/**
 * Automatically purge any soft-deleted dues that have exceeded the 7-day retention period.
 *
 * @param PDO $pdo
 * @return int Number of permanently purged dues packages
 */
function purge_expired_soft_deleted_dues(PDO $pdo): int {
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM dues 
            WHERE deleted_at IS NOT NULL 
              AND deleted_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $expiredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $purgedCount = 0;
        foreach ($expiredIds as $dId) {
            if (purge_due_package($pdo, (int)$dId)) {
                $purgedCount++;
            }
        }
        return $purgedCount;
    } catch (Throwable $e) {
        return 0;
    }
}
