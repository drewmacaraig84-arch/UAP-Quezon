<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $cols = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'gallery_json'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN gallery_json LONGTEXT NULL");
        echo "✓ Added gallery_json column.\n";
    } else {
        echo "- gallery_json column already exists.\n";
    }

    $colsP = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'projects_json'")->fetchAll();
    if (empty($colsP)) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN projects_json LONGTEXT NULL");
        echo "✓ Added projects_json column.\n";
    } else {
        echo "- projects_json column already exists.\n";
    }

    echo "Migration completed.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
