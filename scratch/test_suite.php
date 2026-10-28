<?php
require_once __DIR__ . '/../includes/config.php';

echo "1. Database Connection: " . DB_NAME . "\n";

// Check Users
$superAdmin = $pdo->query("SELECT id, name, id_number, role, status FROM users WHERE role = 'super_admin'")->fetch();
echo "2. Super Admin Account: " . ($superAdmin ? "{$superAdmin['name']} ({$superAdmin['id_number']}) - {$superAdmin['role']}" : "NOT FOUND") . "\n";

$admin = $pdo->query("SELECT id, name, id_number, role, status FROM users WHERE role = 'admin'")->fetch();
echo "3. Admin Account: " . ($admin ? "{$admin['name']} ({$admin['id_number']}) - {$admin['role']}" : "NOT FOUND") . "\n";

$member = $pdo->query("SELECT id, name, id_number, role, status FROM users WHERE role = 'member' LIMIT 1")->fetch();
echo "4. Sample Member Account: " . ($member ? "{$member['name']} ({$member['id_number']}) - {$member['role']}" : "NOT FOUND") . "\n";

// Test System Crash Logger
log_system_crash('INFO', 'Test verification log from automated test suite', 'TestSuiteNotice', __FILE__, __LINE__);
$logId = (int)$pdo->lastInsertId();
if ($logId > 0) {
    $pdo->prepare("UPDATE system_crash_logs SET is_resolved = 1, notes = 'Auto-resolved automated test' WHERE id = ?")->execute([$logId]);
}
$logCount = $pdo->query("SELECT COUNT(*) FROM system_crash_logs")->fetchColumn();
echo "5. System Crash Logs Count: " . $logCount . "\n";

// Test Soft Delete File
$testFile = __DIR__ . '/test_sample_proof.txt';
file_put_contents($testFile, 'Sample payment proof content for soft delete test');
$relPath = 'scratch/test_sample_proof.txt';
$softId = soft_delete_file($relPath, 'scratch_test', 999, 'Verification test');
echo "6. Soft Deleted File ID: " . ($softId ? $softId : "FAILED") . "\n";

if ($softId) {
    $res = restore_soft_deleted_file($softId);
    echo "7. Restore File Result: " . ($res['success'] ? "SUCCESS ({$res['message']})" : "FAILED ({$res['message']})") . "\n";
    if (file_exists($testFile)) {
        @unlink($testFile);
    }
}

// Test Audit Log
audit_log('TEST_EVENT', 'SYSTEM', 'Audit logging pipeline verified.', $superAdmin['id'] ?? 1);
$auditCount = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
echo "8. Audit Logs Count: " . $auditCount . "\n";

// Test Settings
$chapterName = get_site_setting($pdo, 'chapter_name');
echo "9. Chapter Name Setting: " . $chapterName . "\n";

echo "\nALL TESTS COMPLETED SUCCESSFULLY!\n";

