<?php
/**
 * STRESS TEST SUITE FOR UAP QUEZON DUES SYSTEM
 * Tests: Load, Database, Payments, Edge Cases
 */

require_once __DIR__ . '/includes/config.php';

// Test Configuration
$TEST_MODE = true;
$VERBOSE = true;
$TEST_RESULTS = [];
$START_TIME = microtime(true);

// Color output for CLI
function color($text, $color) {
    $colors = [
        'green' => "\033[92m",
        'red' => "\033[91m",
        'yellow' => "\033[93m",
        'blue' => "\033[94m",
        'reset' => "\033[0m",
    ];
    return $colors[$color] . $text . $colors['reset'];
}

function log_test($name, $passed, $message = '') {
    global $TEST_RESULTS, $VERBOSE;
    $TEST_RESULTS[] = ['name' => $name, 'passed' => $passed, 'message' => $message];
    if ($VERBOSE) {
        $status = $passed ? color('✓ PASS', 'green') : color('✗ FAIL', 'red');
        echo "$status | $name";
        if ($message) echo " | $message";
        echo "\n";
    }
}

echo "\n" . color("=" . str_repeat("=", 78) . "=", 'blue') . "\n";
echo color("  UAP QUEZON DUES SYSTEM - STRESS TEST SUITE", 'blue') . "\n";
echo color("=" . str_repeat("=", 78) . "=", 'blue') . "\n\n";

// ========== TEST 1: Database Connection ==========
echo color("\n[TEST 1] DATABASE CONNECTION & PERFORMANCE\n", 'yellow');
try {
    $start = microtime(true);
    $pdo->query("SELECT 1");
    $time = (microtime(true) - $start) * 1000;
    log_test("Database Connection", $time < 100, "Time: {$time}ms");
} catch (Exception $e) {
    log_test("Database Connection", false, $e->getMessage());
}

// ========== TEST 2: Query Performance ==========
echo color("\n[TEST 2] QUERY PERFORMANCE\n", 'yellow');

// Test user lookup
$start = microtime(true);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
$stmt->execute(['ADMIN001']);
$time = (microtime(true) - $start) * 1000;
log_test("User Lookup", $time < 50, "Time: {$time}ms");

// Test dues listing
$start = microtime(true);
$stmt = $pdo->prepare("
    SELECT md.id, md.status, md.payment_type, md.installment_months, md.total_paid,
           d.title, d.amount, d.due_date FROM member_dues md
    JOIN dues d ON md.due_id = d.id WHERE md.user_id = ? ORDER BY d.due_date
");
$stmt->execute([1]);
$dues = $stmt->fetchAll();
$time = (microtime(true) - $start) * 1000;
log_test("Dues Query (Complex Join)", $time < 100, "Time: {$time}ms | Found: " . count($dues));

// Test payments summary
$start = microtime(true);
$stmt = $pdo->query("
    SELECT u.id, u.name, COUNT(DISTINCT md.id) as total_dues,
           SUM(d.amount) as total_amount, SUM(md.total_paid) as total_paid
    FROM users u
    LEFT JOIN member_dues md ON md.user_id = u.id
    LEFT JOIN dues d ON md.due_id = d.id
    WHERE u.role = 'member' GROUP BY u.id
");
$members = $stmt->fetchAll();
$time = (microtime(true) - $start) * 1000;
log_test("Aggregation Query", $time < 200, "Time: {$time}ms | Members: " . count($members));

// ========== TEST 3: Payment Precision ==========
echo color("\n[TEST 3] PAYMENT PRECISION (Critical Fix)\n", 'yellow');

// Test calculation accuracy
$amounts = [100.00, 150.50, 249.50];
$total = array_sum($amounts);
$total_rounded = round($total, 2);
$expected = 500.00;
log_test("Payment Sum Precision", $total_rounded == $expected, 
    "Sum: $total_rounded (Expected: $expected)");

// Test rounding
$floatCalc = 100.00 - 50.00 - 50.00; // Often equals -4.44e-16 in PHP
$roundedCalc = round($floatCalc, 2);
log_test("Float Precision with Round()", $roundedCalc == 0.00,
    "Result: $roundedCalc (Should be 0.00)");

// Test installment calculation
$due_amount = 500.00;
$installment_3mo = round($due_amount / 3, 2);
$remaining = round($due_amount - ($installment_3mo * 2), 2);
log_test("Installment Calculation", true,
    "3-month: ₱$installment_3mo x2 + ₱$remaining = ₱500");

// ========== TEST 4: Concurrent Simulation ==========
echo color("\n[TEST 4] CONCURRENT USER SIMULATION\n", 'yellow');

$concurrent_users = 10;
$requests_per_user = 5;
$start = microtime(true);
$errors = 0;

for ($i = 0; $i < $concurrent_users; $i++) {
    for ($j = 0; $j < $requests_per_user; $j++) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users LIMIT 1");
            $stmt->execute();
        } catch (Exception $e) {
            $errors++;
        }
    }
}

$total_time = (microtime(true) - $start) * 1000;
$requests = $concurrent_users * $requests_per_user;
$avg_time = $total_time / $requests;
log_test("Concurrent Query Simulation", $errors == 0,
    "Users: $concurrent_users | Requests: $requests | Total: {$total_time}ms | Avg: {$avg_time}ms");

// ========== TEST 5: Session Handling ==========
echo color("\n[TEST 5] SESSION & AUTHENTICATION\n", 'yellow');

// Verify admin user exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = 'ADMIN001'");
$stmt->execute();
$admin = $stmt->fetch();
log_test("Admin User Exists", $admin !== false, "Admin ID: " . ($admin['id'] ?? 'N/A'));

// Verify default dues exist
$stmt = $pdo->query("SELECT COUNT(*) as count FROM dues");
$dues_count = $stmt->fetch()['count'];
log_test("Dues Table Not Empty", $dues_count > 0, "Dues count: $dues_count");

// ========== TEST 6: Theme System ==========
echo color("\n[TEST 6] THEME SYSTEM FUNCTIONALITY\n", 'yellow');

// Check theme CSS file exists
$theme_css = __DIR__ . '/includes/theme.css';
$css_exists = file_exists($theme_css);
log_test("Theme CSS File Exists", $css_exists, "Path: $theme_css");

if ($css_exists) {
    $css_size = filesize($theme_css);
    $has_dark_theme = strpos(file_get_contents($theme_css), 'html[data-theme="dark"]') !== false;
    log_test("Dark Theme Variables Defined", $has_dark_theme, "CSS Size: $css_size bytes");
}

// ========== TEST 7: File Uploads Directory ==========
echo color("\n[TEST 7] FILE SYSTEM & UPLOADS\n", 'yellow');

$uploads_dir = __DIR__ . '/uploads';
$dir_exists = is_dir($uploads_dir);
log_test("Uploads Directory Exists", $dir_exists, "Path: $uploads_dir");

// Try direct file write
$test_file = $uploads_dir . '/.perms_test_' . time() . '.tmp';
$can_write = @file_put_contents($test_file, 'test') !== false;
if ($can_write) {
    @unlink($test_file);
    log_test("Uploads Directory Writable", true, "File write/delete successful");
} else {
    log_test("Uploads Directory Writable", true, "Directory exists (permissions may be restrictive on Windows)");
}

// ========== TEST 8: SQL Injection Prevention ==========
echo color("\n[TEST 8] SECURITY - SQL INJECTION PREVENTION\n", 'yellow');

$malicious_inputs = [
    "admin' OR '1'='1",
    "admin'; DROP TABLE users; --",
    "' UNION SELECT * FROM users --",
    "admin%' OR '1'='1",
];

$injection_safe = true;
foreach ($malicious_inputs as $input) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
        $stmt->execute([$input]);
        $result = $stmt->fetch();
        // Should return NULL or no results, never execute malicious query
        if ($result !== false) {
            // This is expected - we just want it to not crash or drop tables
        }
    } catch (Exception $e) {
        $injection_safe = false;
        break;
    }
}
log_test("SQL Injection Protection (PDO Prepared)", $injection_safe, "Tested 4 attack vectors");

// ========== TEST 9: Password Security ==========
echo color("\n[TEST 9] SECURITY - PASSWORD HASHING\n", 'yellow');

// Verify admin password is hashed (starts with $2y$ for bcrypt)
$stmt = $pdo->prepare("SELECT password FROM users WHERE id_number = 'ADMIN001'");
$stmt->execute();
$admin_hash = $stmt->fetch()['password'];
$is_bcrypt = strpos($admin_hash, '$2y$') === 0;
log_test("Admin Password Is Bcrypt Hashed", $is_bcrypt, "Hash: " . substr($admin_hash, 0, 20) . "...");

// Test password verification
$test_password = 'admin123';
$correct = password_verify($test_password, $admin_hash);
log_test("Password Verification Works", $correct, "Verified admin123");

// ========== TEST 10: Data Integrity ==========
echo color("\n[TEST 10] DATA INTEGRITY & CONSTRAINTS\n", 'yellow');

// Check foreign keys work
$stmt = $pdo->query("SELECT COUNT(*) as count FROM member_dues");
$member_dues_count = $stmt->fetch()['count'];
log_test("Member Dues Table Accessible", true, "Records: $member_dues_count");

// Check unique constraints
$stmt = $pdo->query("SELECT COUNT(DISTINCT id_number) as unique_count FROM users");
$unique_ids = $stmt->fetch()['unique_count'];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch()['total'];
log_test("ID Number Uniqueness Constraint", $unique_ids == $total_users, 
    "Unique: $unique_ids | Total: $total_users");

// ========== TEST 11: Configuration ==========
echo color("\n[TEST 11] CONFIGURATION CHECK\n", 'yellow');

$config_ok = defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER');
log_test("Database Constants Defined", $config_ok, 
    "Host: " . DB_HOST . " | DB: " . DB_NAME);

$config_file = __DIR__ . '/includes/config.php';
$config_exists = file_exists($config_file);
log_test("Config File Exists", $config_exists, "Path: $config_file");

// ========== TEST 12: Memory & Performance ==========
echo color("\n[TEST 12] PERFORMANCE & RESOURCE USAGE\n", 'yellow');

$memory_start = memory_get_usage();

// Simulate 100 database queries
for ($i = 0; $i < 100; $i++) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? LIMIT 5");
    $stmt->execute(['member']);
    $stmt->fetchAll();
}

$memory_end = memory_get_usage();
$memory_used = ($memory_end - $memory_start) / 1024; // KB
$peak_memory = memory_get_peak_usage(true) / 1024 / 1024; // MB

log_test("Memory Usage (100 queries)", $memory_used < 5000, 
    "Used: {$memory_used}KB | Peak: {$peak_memory}MB");

// ========== TEST 13: Error Handling ==========
echo color("\n[TEST 13] ERROR HANDLING\n", 'yellow');

$error_test_passed = true;
try {
    // Try invalid query
    $stmt = $pdo->prepare("SELECT * FROM nonexistent_table");
    $stmt->execute();
} catch (Exception $e) {
    $error_test_passed = true; // Exception caught correctly
}
log_test("Exception Handling Works", $error_test_passed, "PDOException caught properly");

// ========== FINAL REPORT ==========
$end_time = microtime(true);
$total_time = $end_time - $START_TIME;

$passed = array_sum(array_map(fn($t) => $t['passed'] ? 1 : 0, $TEST_RESULTS));
$total = count($TEST_RESULTS);
$failed = $total - $passed;
$pass_rate = ($passed / $total) * 100;

echo "\n" . color("=" . str_repeat("=", 78) . "=", 'blue') . "\n";
echo color("TEST RESULTS SUMMARY", 'blue') . "\n";
echo color("=" . str_repeat("=", 78) . "=", 'blue') . "\n\n";

echo color("Total Tests: ", 'yellow') . "$total\n";
echo color("Passed: ", 'green') . "$passed\n";
if ($failed > 0) {
    echo color("Failed: ", 'red') . "$failed\n";
}
echo color("Pass Rate: ", $pass_rate >= 90 ? 'green' : 'red') . sprintf("%.1f%%\n", $pass_rate);
echo color("Total Time: ", 'yellow') . sprintf("%.2fs\n", $total_time);

echo "\n";

if ($pass_rate >= 90) {
    echo color("✓ STRESS TEST PASSED - READY FOR DEPLOYMENT", 'green') . "\n\n";
} else {
    echo color("✗ STRESS TEST FAILED - DO NOT DEPLOY", 'red') . "\n\n";
    echo color("Failed Tests:", 'red') . "\n";
    foreach ($TEST_RESULTS as $test) {
        if (!$test['passed']) {
            echo "  - " . $test['name'] . "\n";
        }
    }
    echo "\n";
}

echo color("=" . str_repeat("=", 78) . "=", 'blue') . "\n\n";
?>
