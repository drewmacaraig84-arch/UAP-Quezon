<?php
require_once __DIR__ . '/../includes/config.php';

try {
    echo "Running migration 023: Create chapter_events table...\n";
    $sql = file_get_contents(__DIR__ . '/migrations/023_create_chapter_events_table.sql');
    $pdo->exec($sql);
    echo "Migration 023 executed successfully!\n";

    // Verify table creation
    $stmt = $pdo->query("SHOW TABLES LIKE 'chapter_events'");
    $table = $stmt->fetchColumn();
    if ($table) {
        echo "Verification passed: Table 'chapter_events' exists.\n";
        $cols = $pdo->query("SHOW COLUMNS FROM chapter_events")->fetchAll(PDO::FETCH_COLUMN);
        echo "Columns: " . implode(', ', $cols) . "\n";
    } else {
        echo "Warning: Table 'chapter_events' not found after migration.\n";
    }

    // Seed initial upcoming sample meetings if empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM chapter_events")->fetchColumn();
    if ($count === 0) {
        $insert = $pdo->prepare("
            INSERT INTO chapter_events 
            (title, event_type, fiscal_year, event_date, start_time, end_time, location_type, venue_name, meeting_link, description, cpd_units, counts_for_standing, is_published, status)
            VALUES 
            ('Special GMM: Architectural Heritage Preservation in Quezon', 'gmm', '2026-2027', '2026-11-20', '18:00:00', '21:00:00', 'hybrid', 'Lucena Grand Central Hotel / Convention Hall', 'https://zoom.us/j/uapquezon2026', 'In-depth discussion on local municipal heritage guidelines and RA 9266 compliance across Quezon LGUs.', 1.5, 1, 1, 'upcoming'),
            ('CPD Masterclass: Climate-Resilient Tropical Architecture', 'cpd_seminar', '2026-2027', '2026-12-10', '13:00:00', '17:00:00', 'f2f', 'Quezon Capitol Convention Center, Lucena City', NULL, 'Technical CPD seminar featuring sustainable timber and passive cooling strategies for coastal Quezon structures.', 3.0, 1, 1, 'upcoming')
        ");
        $insert->execute();
        echo "Seeded 2 initial chapter events successfully!\n";
    }
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

