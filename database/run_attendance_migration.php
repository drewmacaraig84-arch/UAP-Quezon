<?php
require_once __DIR__ . '/../includes/config.php';

try {
    echo "Running migration 022: Create event_attendance_logs...\n";
    $sql = file_get_contents(__DIR__ . '/migrations/022_create_event_attendance_logs.sql');
    $pdo->exec($sql);
    echo "Migration 022 executed successfully!\n";

    // Verify table creation
    $stmt = $pdo->query("SHOW TABLES LIKE 'event_attendance_logs'");
    $table = $stmt->fetchColumn();
    if ($table) {
        echo "Verification passed: Table 'event_attendance_logs' exists.\n";
        $cols = $pdo->query("SHOW COLUMNS FROM event_attendance_logs")->fetchAll(PDO::FETCH_COLUMN);
        echo "Columns: " . implode(', ', $cols) . "\n";
    } else {
        echo "Warning: Table not found after migration.\n";
    }
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

