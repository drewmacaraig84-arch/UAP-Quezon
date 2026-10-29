<?php
// Define flag so config.php doesn't throw uncaught exception before our retry loop
define('CONFIG_IGNORE_DB_FAILURE', true);

$configFile = __DIR__ . '/../includes/config.php';
$configExample = __DIR__ . '/../includes/config.example.php';

if (!file_exists($configFile) && file_exists($configExample)) {
    echo "Notice: includes/config.php not found. Copying from config.example.php...\n";
    copy($configExample, $configFile);
}

if (!file_exists($configFile)) {
    echo "Error: Database configuration file not found at " . $configFile . "\n";
    exit(1);
}

require_once $configFile;

// Retry connecting to database if not ready yet (common during Docker/Railway startup)
$maxRetries = 10;
$retryDelay = 2; // seconds

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
            break; // Connection active and working
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    try {
        $dbPortStr = defined('DB_PORT') && DB_PORT ? ';port=' . DB_PORT : '';
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . $dbPortStr . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        echo "Database connected successfully.\n";
        break;
    } catch (PDOException $e) {
        echo "Database connection attempt {$attempt}/{$maxRetries} failed (" . $e->getMessage() . "). Retrying in {$retryDelay}s...\n";
        if ($attempt === $maxRetries) {
            echo "Error: Could not connect to database after {$maxRetries} attempts.\n";
            exit(1);
        }
        sleep($retryDelay);
    }
}

function run_sql_files(PDO $pdo, string $folder, string $label): void {
    if (!is_dir($folder)) {
        echo "Directory {$folder} does not exist. Skipping {$label}s.\n";
        return;
    }

    $files = glob($folder . '/*.sql');
    if ($files === false || empty($files)) {
        echo "No {$label} files found in {$folder}.\n";
        return;
    }

    sort($files, SORT_NATURAL);

    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            echo "Could not read {$label}: " . basename($file) . "\n";
            exit(1);
        }

        try {
            $pdo->exec($sql);
            echo "Ran {$label}: " . basename($file) . "\n";
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if ($e->getCode() === '42S21' 
                || str_contains($msg, 'Duplicate column') 
                || str_contains($msg, 'already exists') 
                || str_contains($msg, 'Duplicate key name')
                || str_contains($msg, 'Duplicate entry')
                || str_contains($msg, '1061')
                || str_contains($msg, '1060')) {
                echo "Already applied {$label}: " . basename($file) . "\n";
            } else {
                echo "{$label} notice: " . basename($file) . " - " . $e->getMessage() . "\n";
            }
        }
    }
}

run_sql_files($pdo, __DIR__ . '/migrations', 'migration');

// Explicit check for users.profile_photo column across all MySQL versions
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER status");
        echo "Added column 'profile_photo' to 'users' table.\n";
    }
} catch (Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

// Explicit check for good_standing columns across all MySQL versions
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'good_standing_override'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_override ENUM('auto', 'revoked', 'granted') NOT NULL DEFAULT 'auto' AFTER status");
        echo "Added column 'good_standing_override' to 'users' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'good_standing_reason'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_reason VARCHAR(255) NULL AFTER good_standing_override");
        echo "Added column 'good_standing_reason' to 'users' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'good_standing_updated_at'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_updated_at TIMESTAMP NULL AFTER good_standing_reason");
        echo "Added column 'good_standing_updated_at' to 'users' table.\n";
    }
} catch (Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

// Explicit check for website_members company_name and link columns
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'company_name'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN company_name VARCHAR(255) NULL AFTER location");
        echo "Added column 'company_name' to 'website_members' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'link_url'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN link_url VARCHAR(500) NULL AFTER company_name");
        echo "Added column 'link_url' to 'website_members' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'company_logo_path'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN company_logo_path VARCHAR(255) NULL AFTER company_name");
        echo "Added column 'company_logo_path' to 'website_members' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'link_type'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN link_type VARCHAR(50) NULL DEFAULT 'auto' AFTER link_url");
        echo "Added column 'link_type' to 'website_members' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'projects_json'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN projects_json LONGTEXT NULL AFTER gallery_json");
        echo "Added column 'projects_json' to 'website_members' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'qr_code_path'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN qr_code_path VARCHAR(255) NULL AFTER projects_json");
        echo "Added column 'qr_code_path' to 'website_members' table.\n";
    }
} catch (Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

// Explicit check for sponsors is_platinum and products_json columns
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'is_platinum'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE sponsors ADD COLUMN is_platinum TINYINT(1) NOT NULL DEFAULT 0 AFTER url");
        echo "Added column 'is_platinum' to 'sponsors' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'products_json'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE sponsors ADD COLUMN products_json LONGTEXT NULL AFTER is_platinum");
        echo "Added column 'products_json' to 'sponsors' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM news_announcements LIKE 'image_path'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE news_announcements ADD COLUMN image_path VARCHAR(255) NULL AFTER summary");
        echo "Added column 'image_path' to 'news_announcements' table.\n";
    }

    // Explicit check for directory_applications rejection & reapplication columns
    $colCheck = $pdo->query("SHOW COLUMNS FROM directory_applications LIKE 'rejected_at'")->fetch();
    if (!$colCheck) {
        try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN reapply_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER notes"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN reapply_after DATE NULL AFTER reapply_allowed"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN dismissed_notification TINYINT(1) NOT NULL DEFAULT 0 AFTER reapply_after"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN rejected_at TIMESTAMP NULL AFTER dismissed_notification"); } catch (Throwable $e) {}
        echo "Added rejection columns to 'directory_applications' table.\n";
    }
} catch (Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

// Explicit check for contact_inquiries nullable email column
try {
    $pdo->exec("ALTER TABLE contact_inquiries MODIFY COLUMN email VARCHAR(150) NULL");
} catch (Throwable $e) {}

// Generate QR codes for all existing website_members if missing
try {
    require_once __DIR__ . '/../includes/qr_helper.php';
    $qrCount = batch_generate_member_qr_codes($pdo);
    if ($qrCount > 0) {
        echo "Generated directory QR codes for {$qrCount} member(s).\n";
    }
} catch (Throwable $e) {
    echo "Member QR generation notice: " . $e->getMessage() . "\n";
}

// Initialize, deduplicate, and enforce unique constraint on chapter_milestones
try {
    // 1. Delete all existing duplicates
    $pdo->exec("
        DELETE m1 FROM chapter_milestones m1
        INNER JOIN chapter_milestones m2 
        WHERE m1.id > m2.id 
          AND m1.year = m2.year 
          AND m1.title = m2.title
    ");

    // 2. Add unique index if not already present
    try {
        $pdo->exec("ALTER TABLE chapter_milestones ADD UNIQUE KEY uq_milestone_year_title (year, title)");
    } catch (Throwable $e) {}

    // 3. Seed only if table is completely empty
    $msCount = (int)$pdo->query("SELECT COUNT(*) FROM chapter_milestones")->fetchColumn();
    if ($msCount === 0) {
        $pdo->exec("
            INSERT INTO chapter_milestones (id, year, title, content, sort_order) VALUES
            (1, '2016', 'Chapter Founded',           'UAP Quezon Chapter established as IAPOA Chapter 121, bringing together registered architects across the Quezon provinces.', 1),
            (2, '2018', 'Growing Membership',        'Membership expanded significantly with architects from Lucena City, Tayabas, Sariaya, and the four districts of Quezon Province joining the chapter.', 2),
            (3, '2020', 'Digital Transformation',    'Adopted digital systems for member management, dues processing, and chapter communications.', 3),
            (4, '2023', 'New Leadership',            'A new Board of Directors was elected, bringing fresh perspectives and initiatives for chapter growth.', 4),
            (5, '2024', 'Online Architect Directory','Launched the public Architect Directory to connect clients with verified UAP Quezon architects.', 5)
            ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)
        ");
        echo "Seeded default chapter milestones.\n";
    }
} catch (Throwable $e) {
    echo "Milestones setup notice: " . $e->getMessage() . "\n";
}

// Normalize news display_order to sequential 1, 2, 3... starting at 1
try {
    $allNews = $pdo->query("SELECT id FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC, ABS(DATEDIFF(date_posted, CURDATE())) ASC, id DESC")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($allNews)) {
        $updNews = $pdo->prepare("UPDATE news_announcements SET display_order = ? WHERE id = ?");
        foreach ($allNews as $rank => $nId) {
            $updNews->execute([$rank + 1, $nId]);
        }
    }
} catch (Throwable $e) {
    echo "News display order setup notice: " . $e->getMessage() . "\n";
}

run_sql_files($pdo, __DIR__ . '/seeds', 'seed');

// ==============================================================================
// SELF-HEALING ASSET & UPLOADS SYNCHRONIZER (Hosted & Local Storage)
// ==============================================================================
try {
    $projectRoot = dirname(__DIR__);
    $uploadDirs = [
        $projectRoot . '/uploads',
        $projectRoot . '/uploads/qr_codes',
        $projectRoot . '/uploads/avatars',
        $projectRoot . '/uploads/members',
        $projectRoot . '/uploads/sponsors',
        $projectRoot . '/uploads/news',
        $projectRoot . '/uploads/proofs',
        $projectRoot . '/uploads/receipts',
        $projectRoot . '/uploads/home_images',
        $projectRoot . '/uploads/header_badges',
        $projectRoot . '/receipts',
        $projectRoot . '/public'
    ];

    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    // 1. Synchronize seed assets into uploads if mounted on a clean Railway / Docker volume
    $seedUploads = $projectRoot . '/seed_assets/uploads';
    if (is_dir($seedUploads)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($seedUploads, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $destPath = $projectRoot . '/uploads/' . $it->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($destPath)) @mkdir($destPath, 0775, true);
            } else {
                if (!file_exists($destPath)) {
                    @copy($item->getPathname(), $destPath);
                }
            }
        }
    }

    // 2. Cross-sync QR code files between uploads/ and uploads/qr_codes/
    $qrFiles = [
        'qr_bank_1783479751.jpeg',
        'qr_gcash_1783479743.jpeg',
        'qr_maya_1783479730.jpeg'
    ];

    foreach ($qrFiles as $qr) {
        $rootFile = $projectRoot . '/uploads/' . $qr;
        $subFile = $projectRoot . '/uploads/qr_codes/' . $qr;

        if (file_exists($subFile) && !file_exists($rootFile)) {
            @copy($subFile, $rootFile);
        } elseif (file_exists($rootFile) && !file_exists($subFile)) {
            @copy($rootFile, $subFile);
        }
    }

    // 3. Cross-sync Logo files between uploads/ and public/
    $logoFiles = ['logo.jpg', 'uap_logo.jpg'];
    foreach ($logoFiles as $logo) {
        $pubLogo = $projectRoot . '/public/' . $logo;
        $uplLogo = $projectRoot . '/uploads/' . $logo;

        if (file_exists($pubLogo) && !file_exists($uplLogo)) {
            @copy($pubLogo, $uplLogo);
        } elseif (file_exists($uplLogo) && !file_exists($pubLogo)) {
            @copy($uplLogo, $pubLogo);
        }
    }

    // 4. Verify & Heal QR Code database records
    $defaultQRs = [
        'gcash' => 'uploads/qr_gcash_1783479743.jpeg',
        'maya'  => 'uploads/qr_maya_1783479730.jpeg',
        'bank'  => 'uploads/qr_bank_1783479751.jpeg'
    ];

    foreach ($defaultQRs as $method => $defaultRelPath) {
        $stmt = $pdo->prepare("SELECT id, image_path FROM qr_codes WHERE method = ?");
        $stmt->execute([$method]);
        $row = $stmt->fetch();

        $validPath = null;
        if ($row && !empty($row['image_path'])) {
            $checkPath = $projectRoot . '/' . ltrim(str_replace('\\', '/', $row['image_path']), '/');
            if (file_exists($checkPath)) {
                $validPath = $row['image_path'];
            } else {
                // Check if file exists in alternative folder
                $base = basename($row['image_path']);
                if (file_exists($projectRoot . '/uploads/qr_codes/' . $base)) {
                    $validPath = 'uploads/qr_codes/' . $base;
                } elseif (file_exists($projectRoot . '/uploads/' . $base)) {
                    $validPath = 'uploads/' . $base;
                }
            }
        }

        if (!$validPath) {
            $validPath = $defaultRelPath;
        }

        $stmtUpsert = $pdo->prepare("
            INSERT INTO qr_codes (method, image_path, updated_at) VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)
        ");
        $stmtUpsert->execute([$method, $validPath]);
    }

    echo "Asset directories & default media verified.\n";
    
    // 5. Force fresh generation of all member directory QR codes with live domain
    if (file_exists(__DIR__ . '/../includes/qr_helper.php')) {
        require_once __DIR__ . '/../includes/qr_helper.php';
        if (function_exists('batch_generate_member_qr_codes')) {
            $qrCount = batch_generate_member_qr_codes($pdo, true);
            echo "Generated/refreshed {$qrCount} member directory QR codes with full domain URL.\n";
        }
    }

    // 6. Clean up missing header badge references in site_settings
    try {
        $stmtBadges = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'header_badges'");
        $rawBadges = $stmtBadges->fetchColumn();
        if ($rawBadges) {
            $badges = json_decode($rawBadges, true);
            if (is_array($badges)) {
                $changed = false;
                foreach ($badges as $slot => $bPath) {
                    if (!empty($bPath)) {
                        $resolved = function_exists('resolve_media_filesystem_path') ? resolve_media_filesystem_path($bPath) : null;
                        if (!$resolved || !file_exists($resolved)) {
                            $badges[$slot] = '';
                            $changed = true;
                        }
                    }
                }
                if ($changed) {
                    $upd = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'header_badges'");
                    $upd->execute([json_encode($badges)]);
                    echo "Cleaned up missing header badge references in site_settings.\n";
                }
            }
        }
    } catch (Throwable $e) {}
} catch (Throwable $e) {
    echo "Asset verification notice: " . $e->getMessage() . "\n";
}

echo "Project database setup complete.\n";
