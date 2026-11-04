<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: text/plain');

try {
    echo "Running migration 024: Create chapter_officers table...\n";
    $sql = file_get_contents(__DIR__ . '/migrations/024_create_chapter_officers_table.sql');
    $pdo->exec($sql);
    echo "Migration 024 table created/verified successfully!\n";

    // Verify table creation
    $stmt = $pdo->query("SHOW TABLES LIKE 'chapter_officers'");
    $table = $stmt->fetchColumn();
    if ($table) {
        echo "Verification passed: Table 'chapter_officers' exists.\n";
        $cols = $pdo->query("SHOW COLUMNS FROM chapter_officers")->fetchAll(PDO::FETCH_COLUMN);
        echo "Columns: " . implode(', ', $cols) . "\n";
    }

    // Default site setting: chapter_fiscal_year
    $fyExists = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'chapter_fiscal_year'")->fetchColumn();
    if ($fyExists === false) {
        $pdo->exec("INSERT INTO site_settings (setting_key, setting_value) VALUES ('chapter_fiscal_year', '2026-2027')");
        echo "Added site_setting: chapter_fiscal_year = 2026-2027\n";
    }

    // Seed initial officers if empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM chapter_officers WHERE deleted_at IS NULL")->fetchColumn();
    if ($count === 0) {
        $initialOfficers = [
            [
                'user_id' => 3,
                'name' => 'Juan Dela Cruz',
                'title_prefix' => 'Ar.',
                'title_suffix' => 'uap',
                'position' => 'Chapter President',
                'committee' => 'Executive Leadership',
                'fiscal_year' => '2026-2027',
                'photo_path' => 'uploads/avatars/avatar_member_3_1793678845_e22043.jpg',
                'display_order' => 1
            ],
            [
                'user_id' => 4,
                'name' => 'Maria Santos',
                'title_prefix' => 'Ar.',
                'title_suffix' => 'uap',
                'position' => 'Vice President on Operations',
                'committee' => 'Chapter Operations & Heritage',
                'fiscal_year' => '2026-2027',
                'photo_path' => null,
                'display_order' => 2
            ],
            [
                'user_id' => null,
                'name' => 'Roberto Ramos',
                'title_prefix' => 'Ar.',
                'title_suffix' => 'uap',
                'position' => 'Vice President on Programs & Development',
                'committee' => 'CPD Seminars & Guhit Arkitekto',
                'fiscal_year' => '2026-2027',
                'photo_path' => null,
                'display_order' => 3
            ],
            [
                'user_id' => null,
                'name' => 'Clarissa Mendoza',
                'title_prefix' => 'Ar.',
                'title_suffix' => 'uap',
                'position' => 'Chapter Secretary',
                'committee' => 'Secretariat & Records',
                'fiscal_year' => '2026-2027',
                'photo_path' => null,
                'display_order' => 4
            ],
            [
                'user_id' => null,
                'name' => 'Eduardo Alcala',
                'title_prefix' => 'Ar.',
                'title_suffix' => 'uap',
                'position' => 'Chapter Treasurer',
                'committee' => 'Finance & Ways and Means',
                'fiscal_year' => '2026-2027',
                'photo_path' => null,
                'display_order' => 5
            ],
            [
                'user_id' => null,
                'name' => 'Patricia Villanueva',
                'title_prefix' => 'Ar.',
                'title_suffix' => 'uap',
                'position' => 'Chapter Auditor',
                'committee' => 'Audit & Internal Governance',
                'fiscal_year' => '2026-2027',
                'photo_path' => null,
                'display_order' => 6
            ],
            [
                'user_id' => null,
                'name' => 'Benigno Tan',
                'title_prefix' => 'Ar.',
                'title_suffix' => 'FUAP',
                'position' => 'Immediate Past President',
                'committee' => 'Council of Past Presidents',
                'fiscal_year' => '2026-2027',
                'photo_path' => null,
                'display_order' => 7
            ]
        ];

        $ins = $pdo->prepare("
            INSERT INTO chapter_officers 
            (user_id, name, title_prefix, title_suffix, position, committee, fiscal_year, photo_path, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        foreach ($initialOfficers as $officer) {
            $ins->execute([
                $officer['user_id'],
                $officer['name'],
                $officer['title_prefix'],
                $officer['title_suffix'],
                $officer['position'],
                $officer['committee'],
                $officer['fiscal_year'],
                $officer['photo_path'],
                $officer['display_order']
            ]);
        }
        echo "Seeded " . count($initialOfficers) . " initial chapter officers successfully!\n";
    } else {
        echo "Table already has $count officer records.\n";
    }

    echo "\n=== MIGRATION COMPLETE ===\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

