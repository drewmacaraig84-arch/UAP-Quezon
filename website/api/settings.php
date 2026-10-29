<?php
require_once __DIR__ . '/config.php';
header("Cache-Control: public, max-age=60, stale-while-revalidate=300");

try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Paths are stored relative to project root (e.g. 'public/logo.jpg', 'uploads/xxx.jpg')
    // .htaccess uses RewriteBase / so we just prepend /
    // Vite dev proxy also maps /public/* and /uploads/* correctly
    $toUrl = function($path) {
        if (!$path || trim((string)$path) === '') return null;
        $path = trim((string)$path);
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) return $path;
        return '/' . ltrim($path, '/');
    };

    echo json_encode([
        'logo'                   => $toUrl($rows['logo'] ?? 'public/logo.jpg'),
        'header_image_1'         => $toUrl($rows['header_image_1'] ?? null),
        'header_image_2'         => $toUrl($rows['header_image_2'] ?? null),
        'header_image_3'         => $toUrl($rows['header_image_3'] ?? null),
        'org_name'               => $rows['org_name'] ?? 'UAP Quezon Chapter',
        'about_us'               => $rows['about_us'] ?? '',
        'contact_address'        => $rows['contact_address'] ?? 'Lucena City, Quezon Province, Philippines',
        'contact_email'          => $rows['contact_email'] ?? 'quezon@united-architects.ph',
        'contact_phone'          => $rows['contact_phone'] ?? '+63 (0) XXXX XXXX',
        'office_hours_weekdays'  => $rows['office_hours_weekdays'] ?? '9:00 AM – 5:00 PM',
        'office_hours_saturday'  => $rows['office_hours_saturday'] ?? '9:00 AM – 12:00 PM',
        'office_hours_sunday'    => $rows['office_hours_sunday'] ?? 'Closed',
        'directory_fee'          => $rows['website_directory_fee'] ?? null,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
