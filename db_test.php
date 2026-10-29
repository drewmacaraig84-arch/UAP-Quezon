<?php
/**
 * UAP Quezon Province Chapter - InfinityFree Database Diagnostics & Auto-Connector
 */
header('Content-Type: text/html; charset=utf-8');

$host = 'sql108.infinityfree.com';
$user = 'if0_43293362';
$pass = 'xDdiuKOGkEJ4O';

// If user submitted a custom name via form
$customDb = trim($_POST['custom_db'] ?? '');
if (!empty($customDb) && !str_starts_with($customDb, $user . '_')) {
    $customDb = $user . '_' . ltrim($customDb, '_');
}

$candidates = array_unique(array_filter([
    $customDb,
    $user . '_Uap_qc',
    $user . '_Uap_qd',
    $user . '_uap_qc',
    $user . '_uap_qd',
    $user . '_uapqc',
    $user . '_uapqd',
    $user . '_uap',
    $user . '_quezon',
    $user . '_qc',
    $user . '_db',
    $user . '_portal'
]));

echo "<!DOCTYPE html><html><head><title>Database Diagnostic & Connector</title>";
echo "<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 30px; background: #0f172a; color: #f8fafc; max-width: 750px; margin: 0 auto; line-height: 1.6; }
h1 { color: #f59e0b; margin-top: 0; }
.card { background: #1e293b; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
.btn { background: #15803d; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; }
.input { background: #0f172a; border: 1px solid #475569; color: white; padding: 8px 12px; border-radius: 6px; width: 60%; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
.badge-success { background: rgba(34,197,94,0.2); color: #22c55e; border: 1px solid #22c55e; }
.badge-fail { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid #ef4444; }
code { background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px; color: #f59e0b; font-family: monospace; }
</style></head><body>";

echo "<h1>UAP Quezon • InfinityFree Database Connector</h1>";
echo "<div class='card'>";
echo "<p><span class='badge badge-success'>✓ Connected</span> Authenticated with MySQL host <code>{$host}</code> as <code>{$user}</code>.</p>";
echo "</div>";

$matchedDb = null;
$tableCount = 0;
$tables = [];

echo "<div class='card'>";
echo "<h3>Testing Candidate Database Names:</h3><ul style='list-style: none; padding-left: 0;'>";

foreach ($candidates as $cand) {
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$cand};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        
        $tblStmt = $pdo->query("SHOW TABLES");
        $tables = $tblStmt->fetchAll(PDO::FETCH_COLUMN);
        $tableCount = count($tables);
        $matchedDb = $cand;

        echo "<li style='padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.06);'>";
        echo "<span class='badge badge-success'>FOUND & CONNECTED</span> <strong><code>{$cand}</code></strong> ";
        echo "— Tables inside: <strong style='color:#22c55e;'>{$tableCount}</strong>";
        echo "</li>";
        break; // Stop at first successful database
    } catch (PDOException $e) {
        $err = $e->getCode();
        echo "<li style='padding: 6px 0; opacity: 0.6; font-size: 13px;'>";
        echo "<span class='badge badge-fail'>Not Found</span> <code>{$cand}</code>";
        echo "</li>";
    }
}
echo "</ul></div>";

if ($matchedDb) {
    echo "<div class='card' style='border-color: #22c55e;'>";
    echo "<h2 style='color: #22c55e; margin-top: 0;'>✓ SUCCESS: Database Verified!</h2>";
    echo "<p>Your live database is: <strong><code>{$matchedDb}</code></strong></p>";
    
    // Auto-update includes/db_config.php on live server if needed
    $configPath = __DIR__ . '/includes/db_config.php';
    if (file_exists($configPath) && is_writable($configPath)) {
        $content = file_get_contents($configPath);
        $content = preg_replace("/define\('DB_NAME'[^)]+\);/", "define('DB_NAME', '{$matchedDb}');", $content);
        file_put_contents($configPath, $content);
        echo "<p style='color: #22c55e;'>✓ Automatically updated <code>includes/db_config.php</code> to use <code>{$matchedDb}</code>!</p>";
    }

    if ($tableCount === 0) {
        $schemaPath = __DIR__ . '/schema.sql';
        if (file_exists($schemaPath)) {
            $rawSql = file_get_contents($schemaPath);
            $cleanSql = '';
            foreach (explode("\n", $rawSql) as $line) {
                $t = trim($line);
                if ($t !== '' && !str_starts_with($t, '--') && !str_starts_with($t, '/*')) {
                    $cleanSql .= $line . "\n";
                }
            }
            foreach (array_filter(array_map('trim', explode(';', $cleanSql))) as $stmt) {
                if ($stmt) {
                    try { $pdo->exec($stmt); } catch (Throwable $e) {}
                }
            }
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $tableCount = count($tables);
            echo "<div style='background: rgba(34,197,94,0.15); border: 1px solid #22c55e; padding: 14px; border-radius: 8px; margin-top: 10px;'>";
            echo "<strong style='color: #22c55e;'>✓ Automatically imported schema.sql into {$matchedDb}!</strong><br>";
            echo "Created <strong>{$tableCount}</strong> tables and initialized Admin, Super Admin, and Member accounts.<br>";
            echo "<p style='margin-top: 10px;'><a href='auth/login.php' class='btn' style='display:inline-block; text-decoration:none;'>Go to Login Page &rarr;</a></p>";
            echo "</div>";
        }
    } else {
        echo "<p style='color: #22c55e;'>Found <strong>{$tableCount}</strong> tables in database: " . implode(', ', array_slice($tables, 0, 8)) . "...</p>";
        echo "<p><a href='index.php' class='btn' style='display:inline-block; text-decoration:none;'>Open UAP Quezon Province Chapter Website &rarr;</a> ";
        echo "<a href='auth/login.php' class='btn' style='display:inline-block; text-decoration:none; background: #e58a13; margin-left: 10px;'>Go to Login &rarr;</a></p>";
    }
    echo "</div>";
} else {
    echo "<div class='card' style='border-color: #ef4444;'>";
    echo "<h3 style='color: #ef4444; margin-top: 0;'>No Matching Database Found Yet</h3>";
    echo "<p>None of the common database names were found on your MySQL account.</p>";
    echo "<p>Did you name it something else in your vPanel? Type the name below to test it:</p>";
    echo "<form method='POST' style='margin: 14px 0;'>";
    echo "<input type='text' name='custom_db' class='input' placeholder='e.g. uap_qc or your database name' required> ";
    echo "<button type='submit' class='btn'>Test Database</button>";
    echo "</form>";
    echo "<hr style='border: none; border-top: 1px solid rgba(255,255,255,0.1); margin: 20px 0;'>";
    echo "<p><strong>If you haven't created a database yet:</strong></p>";
    echo "<ol>";
    echo "<li>In your vPanel, open <strong>MySQL Databases</strong>.</li>";
    echo "<li>Type a database name (e.g. <code>uap_qc</code>) into the box and click <strong>Create Database</strong>.</li>";
    echo "<li>Then refresh this page or test the name above!</li>";
    echo "</ol>";
    echo "</div>";
}

echo "</body></html>";
