<?php
require_once __DIR__ . '/config.php';

try {
    $milestones = $pdo->query(
        "SELECT id, year, title, description, description AS content, display_order 
         FROM chapter_milestones 
         WHERE deleted_at IS NULL 
         ORDER BY display_order ASC, year ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($milestones ?: []);
} catch (Exception $e) {
    echo json_encode([]);
}
