<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

require_csrf();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$action = $_POST['action'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    if (function_exists('set_flash')) {
        set_flash('error', 'Payment record not found.');
    }
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'approve') {
        // Mark payment as verified
        $stmt = $pdo->prepare("UPDATE payments SET status = 'verified', verified_at = NOW(), verified_by = ?, remarks = ? WHERE id = ?");
        $stmt->execute([current_user_id(), $remarks ?: 'Approved by admin', $payment_id]);

        // Recalculate total verified payments for this member_due
        $total_stmt = $pdo->prepare("SELECT SUM(amount_paid) as total FROM payments WHERE member_due_id = ? AND status = 'verified'");
        $total_stmt->execute([$payment['member_due_id']]);
        $total_paid = (float)($total_stmt->fetch()['total'] ?? 0);

        // Get the full due amount (support custom amount if present)
        $due_stmt = $pdo->prepare("SELECT COALESCE(md.custom_amount, d.amount) as amount FROM member_dues md JOIN dues d ON md.due_id = d.id WHERE md.id = ?");
        $due_stmt->execute([$payment['member_due_id']]);
        $due_amount = (float)($due_stmt->fetch()['amount'] ?? 0);

        $new_status = calculate_due_status($total_paid, $due_amount);
        $total_paid_precise = round($total_paid, 2);

        $stmt = $pdo->prepare("UPDATE member_dues SET status = ?, total_paid = ? WHERE id = ?");
        $stmt->execute([$new_status, $total_paid_precise, $payment['member_due_id']]);

        // Issue official receipt
        $existingReceipt = $pdo->prepare("SELECT id FROM receipts WHERE payment_id = ?");
        $existingReceipt->execute([$payment_id]);
        if (!$existingReceipt->fetch()) {
            $receipt_number = generate_receipt_number($pdo);
            $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)");
            $stmt->execute([$payment_id, $receipt_number]);
        }

        // If this member due is for a directory application and is now paid, unlock the directory application
        if ($new_status === 'paid') {
            $appStmt = $pdo->prepare("UPDATE directory_applications SET status = 'paid' WHERE member_due_id = ?");
            $appStmt->execute([$payment['member_due_id']]);

            // Also ensure website_members record exists and is published using profile photo
            $daStmt = $pdo->prepare("SELECT user_id FROM directory_applications WHERE member_due_id = ?");
            $daStmt->execute([$payment['member_due_id']]);
            $daUser = $daStmt->fetchColumn();
            if ($daUser) {
                $wmCheck = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
                $wmCheck->execute([$daUser]);
                if (!$wmCheck->fetch()) {
                    $uData = $pdo->prepare("SELECT name, id_number, profile_photo FROM users WHERE id = ?");
                    $uData->execute([$daUser]);
                    $u = $uData->fetch();
                    if ($u) {
                        $pdo->prepare("INSERT INTO website_members (user_id, name, id_number, role_title, specialty, location, photo_path, is_published) 
                                       VALUES (?, ?, ?, 'Architect', 'General Practice', 'Quezon', ?, 1)")
                            ->execute([$daUser, $u['name'], $u['id_number'], $u['profile_photo'] ?: null]);
                    }
                } else {
                    $pdo->prepare("UPDATE website_members SET is_published = 1 WHERE user_id = ?")->execute([$daUser]);
                }

                // Auto-generate QR code for newly unlocked directory member
                if (file_exists(__DIR__ . '/../includes/qr_helper.php')) {
                    require_once __DIR__ . '/../includes/qr_helper.php';
                    $fStmt = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
                    $fStmt->execute([$daUser]);
                    $wmId = (int)$fStmt->fetchColumn();
                    if ($wmId > 0 && function_exists('generate_member_directory_qr')) {
                        generate_member_directory_qr($pdo, $wmId, true);
                    }
                }
            }
        }


        $pdo->commit();
        if (function_exists('set_flash')) {
            set_flash('success', "Payment #{$payment_id} approved successfully and official receipt generated.");
        }

    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE payments SET status = 'rejected', verified_at = NOW(), verified_by = ?, remarks = ? WHERE id = ?");
        $stmt->execute([current_user_id(), $remarks ?: 'Payment rejected by admin', $payment_id]);

        // Revert member_due status based on remaining verified payments
        $total_stmt = $pdo->prepare("SELECT SUM(amount_paid) as total FROM payments WHERE member_due_id = ? AND status = 'verified'");
        $total_stmt->execute([$payment['member_due_id']]);
        $total_paid = (float)($total_stmt->fetch()['total'] ?? 0);

        $due_stmt = $pdo->prepare("SELECT COALESCE(md.custom_amount, d.amount) as amount FROM member_dues md JOIN dues d ON md.due_id = d.id WHERE md.id = ?");
        $due_stmt->execute([$payment['member_due_id']]);
        $due_amount = (float)($due_stmt->fetch()['amount'] ?? 0);

        $new_status = calculate_due_status($total_paid, $due_amount);

        $stmt = $pdo->prepare("UPDATE member_dues SET status = ?, total_paid = ? WHERE id = ?");
        $stmt->execute([$new_status, round($total_paid, 2), $payment['member_due_id']]);

        $pdo->commit();
        if (function_exists('set_flash')) {
            set_flash('warning', "Payment #{$payment_id} has been rejected.");
        }
    } else {
        $pdo->rollBack();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (function_exists('set_flash')) {
        set_flash('error', 'An error occurred while updating payment verification status.');
    }
}

header('Location: dashboard.php');
exit;
