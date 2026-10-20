<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }

try {
    $stmt = $pdo->prepare("SELECT wm.*, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path, u.id as user_id 
                          FROM website_members wm 
                          LEFT JOIN users u ON wm.user_id = u.id 
                          WHERE (wm.id = ? OR wm.user_id = ?) AND wm.is_published = 1");
    $stmt->execute([$id, $id]);
    $member = $stmt->fetch();
    if (!$member) { 
        http_response_code(404); 
        echo json_encode(['error' => 'The requested architect profile does not exist or has been unpublished.', 'code' => 'NOT_FOUND']); 
        exit; 
    }

    if (!empty($member['user_id'])) {
        $isAdminOrOwner = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || 
                          (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$member['user_id']);

        if (function_exists('has_unlocked_website_directory') && !has_unlocked_website_directory($pdo, (int)$member['user_id'])) {
            if (!$isAdminOrOwner) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'This directory profile is currently pending approval or directory fee payment.',
                    'code' => 'PENDING_DIRECTORY',
                    'name' => $member['name'] ?? null
                ]);
                exit;
            } else {
                $member['is_admin_preview'] = true;
                $member['preview_notice'] = 'Directory profile is currently pending approval or fee payment.';
            }
        }
        if (function_exists('is_good_member') && !is_good_member($pdo, (int)$member['user_id'])) {
            if (!$isAdminOrOwner) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'Member profile is currently on administrative hold or pending certification.',
                    'code' => 'ADMIN_HOLD',
                    'name' => $member['name'] ?? null
                ]);
                exit;
            } else {
                $member['is_admin_preview'] = true;
                $member['preview_notice'] = 'Member profile is currently on administrative hold or has overdue chapter dues.';
            }
        }
    }

    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    // Build photo URL (strictly member portrait, never project cover photos)
    $photo = $member['photo_path'];
    if ($photo && str_contains($photo, 'proj_')) {
        $photo = null;
    }
    $member['photo_url'] = $toUrl($photo);
    $member['company_logo_url'] = $toUrl($member['company_logo_path'] ?? null);

    // Decode multiple social links (up to 3 links)
    $links = [];
    if (!empty($member['links_json'])) {
        $decodedLinks = json_decode($member['links_json'], true);
        if (is_array($decodedLinks)) {
            foreach ($decodedLinks as $lnk) {
                if (!empty($lnk['url'])) {
                    $u = trim($lnk['url']);
                    if (!preg_match('#^https?://#i', $u)) $u = 'https://' . ltrim($u, '/');
                    $t = function_exists('detect_social_link_type') ? detect_social_link_type($u, $lnk['type'] ?? 'auto') : ($lnk['type'] ?? 'website');
                    $links[] = [
                        'url' => $u,
                        'type' => $t
                    ];
                }
            }
        }
    }
    if (empty($links) && !empty($member['link_url'])) {
        $u = trim($member['link_url']);
        if (!preg_match('#^https?://#i', $u)) $u = 'https://' . ltrim($u, '/');
        $t = function_exists('detect_social_link_type') ? detect_social_link_type($u, $member['link_type'] ?? 'auto') : 'website';
        $links[] = [
            'url' => $u,
            'type' => $t
        ];
    }
    $member['links'] = $links;
    if (!empty($links[0])) {
        $member['link_url'] = $links[0]['url'];
        $member['link_type'] = $links[0]['type'];
    }

    // Decode gallery JSON (legacy support)
    $gallery = [];
    if (!empty($member['gallery_json'])) {
        $decoded = json_decode($member['gallery_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as &$g) {
                if (!empty($g['path'])) {
                    $g['url'] = $toUrl($g['path']);
                }
            }
            $gallery = $decoded;
        }
    }
    $member['gallery'] = $gallery;

    // Decode projects JSON (Completed Works)
    $projects = [];
    if (!empty($member['projects_json'])) {
        $decodedProjects = json_decode($member['projects_json'], true);
        if (is_array($decodedProjects)) {
            foreach ($decodedProjects as &$p) {
                $coverUrl = !empty($p['cover_photo']) ? $toUrl($p['cover_photo']) : null;
                $p['cover_url'] = $coverUrl;

                $photoUrls = [];
                // 1. Always include Cover Photo first in slideshow array
                if (!empty($coverUrl)) {
                    $photoUrls[] = $coverUrl;
                }
                // 2. Include all additional gallery photos
                if (!empty($p['photos']) && is_array($p['photos'])) {
                    foreach ($p['photos'] as $ph) {
                        if (!empty($ph)) {
                            $u = $toUrl($ph);
                            if (!in_array($u, $photoUrls, true)) {
                                $photoUrls[] = $u;
                            }
                        }
                    }
                }
                if (empty($photoUrls) && !empty($coverUrl)) {
                    $photoUrls[] = $coverUrl;
                }
                $p['photos'] = $photoUrls;
                if (empty($p['cover_url']) && !empty($photoUrls[0])) {
                    $p['cover_url'] = $photoUrls[0];
                }
            }
            $projects = $decodedProjects;
        }
    }
    $member['projects'] = $projects;

    // QR image
    $member['qr_url'] = $toUrl($member['qr_image_path']);

    // Clean up raw paths
    unset($member['photo_path'], $member['gallery_json'], $member['projects_json'], $member['qr_image_path']);

    echo json_encode($member);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
