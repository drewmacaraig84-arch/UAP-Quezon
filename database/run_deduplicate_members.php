<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== UAP QUEZON: DEDUPLICATING WEBSITE_MEMBERS ===\n";

// 1. Find all user_ids that have more than 1 website_members row
$dupesStmt = $pdo->query("
    SELECT user_id, COUNT(*) as cnt 
    FROM website_members 
    WHERE user_id IS NOT NULL 
    GROUP BY user_id 
    HAVING cnt > 1
");
$dupeUsers = $dupesStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($dupeUsers)) {
    echo "No duplicate website_members found by user_id.\n";
} else {
    echo "Found " . count($dupeUsers) . " user(s) with duplicate website_members rows.\n";
    foreach ($dupeUsers as $dupe) {
        $uid = (int)$dupe['user_id'];
        $rowsStmt = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ? ORDER BY id ASC");
        $rowsStmt->execute([$uid]);
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

        echo "User ID $uid has " . count($rows) . " rows:\n";
        foreach ($rows as $r) {
            echo "  - Row ID {$r['id']}: '{$r['name']}', specialty='{$r['specialty']}', updated_at='{$r['updated_at']}'\n";
        }

        // Determine which row to keep:
        $keepRow = null;
        foreach ($rows as $r) {
            if ($keepRow === null) {
                $keepRow = $r;
            } else {
                // Prefer row with custom specialty over default 'General Practice'
                if ($keepRow['specialty'] === 'General Practice' && !empty($r['specialty']) && $r['specialty'] !== 'General Practice') {
                    $keepRow = $r;
                } elseif (!empty($r['projects_json']) && empty($keepRow['projects_json'])) {
                    $keepRow = $r;
                } elseif ($r['id'] > $keepRow['id'] && $r['specialty'] !== 'General Practice') {
                    $keepRow = $r;
                }
            }
        }

        $keepId = (int)$keepRow['id'];
        echo "  => KEEPING Row ID $keepId ('{$keepRow['specialty']}')\n";

        // Delete all other rows for this user_id
        $delStmt = $pdo->prepare("DELETE FROM website_members WHERE user_id = ? AND id != ?");
        $delStmt->execute([$uid, $keepId]);
        echo "  => Deleted duplicate rows for User ID $uid.\n\n";
    }
}

// 2. Also check if there are duplicate rows by name and id_number
$nameDupesStmt = $pdo->query("
    SELECT name, id_number, COUNT(*) as cnt 
    FROM website_members 
    GROUP BY name, id_number 
    HAVING cnt > 1
");
$nameDupes = $nameDupesStmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($nameDupes)) {
    echo "Found " . count($nameDupes) . " duplicates by name/id_number:\n";
    foreach ($nameDupes as $nd) {
        $rowsStmt = $pdo->prepare("SELECT * FROM website_members WHERE name = ? AND id_number = ? ORDER BY id DESC");
        $rowsStmt->execute([$nd['name'], $nd['id_number']]);
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        $keepId = (int)$rows[0]['id'];
        echo "  => Keeping Row ID $keepId for '{$nd['name']}'\n";
        $delStmt = $pdo->prepare("DELETE FROM website_members WHERE name = ? AND id_number = ? AND id != ?");
        $delStmt->execute([$nd['name'], $nd['id_number'], $keepId]);
    }
}

// 3. Add UNIQUE KEY constraint on user_id if not present
try {
    $idxCheck = $pdo->query("SHOW INDEX FROM website_members WHERE Key_name = 'uq_website_member_user'")->fetch();
    if (!$idxCheck) {
        $pdo->exec("ALTER TABLE website_members ADD UNIQUE KEY uq_website_member_user (user_id)");
        echo "✓ Added UNIQUE KEY uq_website_member_user (user_id) to website_members table.\n";
    } else {
        echo "✓ UNIQUE KEY uq_website_member_user already exists.\n";
    }
} catch (Throwable $e) {
    echo "Note on index: " . $e->getMessage() . "\n";
}

echo "=== DEDUPLICATION COMPLETE ===\n";
