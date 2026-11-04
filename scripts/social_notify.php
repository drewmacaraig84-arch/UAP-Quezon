<?php
/**
 * Standalone Social Media & Telegram Notification Script / Cron Worker
 * UAP Quezon Province Chapter Portal
 * 
 * Usage from CLI:
 *   php scripts/social_notify.php --test
 *   php scripts/social_notify.php --status
 *   php scripts/social_notify.php --summary
 *   php scripts/social_notify.php --send "Your custom broadcast text"
 * 
 * Usage from Web / Scheduled Job:
 *   GET /scripts/social_notify.php?action=summary&key=SECRET
 */

if (php_sapi_name() !== 'cli') {
    // If accessed via web, verify admin session or secret cron key
    session_start();
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/social_notifier.php';

    $isAdmin = !empty($_SESSION['admin_logged_in']) || (($_SESSION['user_role'] ?? '') === 'admin');
    $secretKey = get_site_setting($pdo, 'cron_secret_key', 'uap_quezon_cron_2026');
    $providedKey = $_GET['key'] ?? '';

    if (!$isAdmin && $providedKey !== $secretKey) {
        http_response_code(403);
        die("Access Denied: Administrative or secret cron token required.");
    }
} else {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/social_notifier.php';
}

$cfg = get_telegram_config($pdo);

// Parse CLI or GET arguments
$action = 'summary';
$customText = '';

if (php_sapi_name() === 'cli') {
    $args = $argv;
    array_shift($args); // remove script name
    while (!empty($args)) {
        $arg = array_shift($args);
        if ($arg === '--test') {
            $action = 'test';
        } elseif ($arg === '--status') {
            $action = 'status';
        } elseif ($arg === '--summary') {
            $action = 'summary';
        } elseif ($arg === '--send' && !empty($args)) {
            $action = 'send';
            $customText = array_shift($args);
        }
    }
} else {
    $action = $_GET['action'] ?? 'summary';
    $customText = $_GET['text'] ?? '';
}

// Execution Handlers
switch ($action) {
    case 'status':
        $res = [
            'enabled' => $cfg['enabled'],
            'has_token' => !empty($cfg['bot_token']),
            'chat_id' => $cfg['chat_id'] ?: 'Not configured',
            'inquiries_enabled' => $cfg['notify_inquiries'],
            'registrations_enabled' => $cfg['notify_registrations'],
            'payments_enabled' => $cfg['notify_payments'],
            'directory_enabled' => $cfg['notify_directory']
        ];
        if (php_sapi_name() === 'cli') {
            echo "=== UAP QUEZON TELEGRAM NOTIFICATION STATUS ===\n";
            echo "Enabled: " . ($res['enabled'] ? 'YES' : 'NO') . "\n";
            echo "Bot Token: " . ($res['has_token'] ? 'Configured (hidden)' : 'MISSING') . "\n";
            echo "Chat ID: " . $res['chat_id'] . "\n";
            echo "Triggers: Inquiries=" . ($res['inquiries_enabled'] ? 'ON' : 'OFF') .
                 ", Registrations=" . ($res['registrations_enabled'] ? 'ON' : 'OFF') .
                 ", Payments=" . ($res['payments_enabled'] ? 'ON' : 'OFF') .
                 ", Directory=" . ($res['directory_enabled'] ? 'ON' : 'OFF') . "\n";
        } else {
            header('Content-Type: application/json');
            echo json_encode($res, JSON_PRETTY_PRINT);
        }
        break;

    case 'test':
        $result = notify_admin_event($pdo, 'test');
        if (php_sapi_name() === 'cli') {
            echo "Test Notification Result: " . ($result['success'] ? "SUCCESS (Check Telegram)" : "FAILED - " . ($result['error'] ?? 'Unknown')) . "\n";
        } else {
            header('Content-Type: application/json');
            echo json_encode($result);
        }
        break;

    case 'send':
        if (empty($customText)) {
            die("Error: No message text provided. Usage: php scripts/social_notify.php --send \"Your message\"\n");
        }
        $result = send_telegram_message($cfg['bot_token'], $cfg['chat_id'], htmlspecialchars($customText));
        if (php_sapi_name() === 'cli') {
            echo "Send Result: " . ($result['success'] ? "SUCCESS" : "FAILED - " . ($result['error'] ?? '')) . "\n";
        } else {
            header('Content-Type: application/json');
            echo json_encode($result);
        }
        break;

    case 'summary':
    default:
        // Automatically purge soft-deleted dues that exceeded the 7-day retention period
        if (file_exists(__DIR__ . '/../includes/dues_service.php')) {
            require_once __DIR__ . '/../includes/dues_service.php';
            if (function_exists('purge_expired_soft_deleted_dues')) {
                purge_expired_soft_deleted_dues($pdo);
            }
        }

        // Gather counts of pending items
        $approvals = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'pending' AND deleted_at IS NULL")->fetchColumn();
        $payments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending' AND deleted_at IS NULL")->fetchColumn();
        $inquiries = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'unread' AND deleted_at IS NULL")->fetchColumn();
        $directory = (int)$pdo->query("SELECT COUNT(*) FROM directory_applications WHERE status = 'pending_fee' AND deleted_at IS NULL")->fetchColumn();

        $totalPending = $approvals + $payments + $inquiries + $directory;

        $result = notify_admin_event($pdo, 'summary', [
            'approvals' => $approvals,
            'payments' => $payments,
            'inquiries' => $inquiries,
            'directory' => $directory
        ]);

        if (php_sapi_name() === 'cli') {
            echo "Summary dispatched. Total pending items: {$totalPending}.\n";
            echo "Telegram Dispatch Result: " . (!empty($result['success']) ? 'SUCCESS' : (!empty($result['skipped']) ? 'SKIPPED (' . $result['reason'] . ')' : 'FAILED - ' . ($result['error'] ?? ''))) . "\n";
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'total_pending' => $totalPending,
                'breakdown' => [
                    'approvals' => $approvals,
                    'payments' => $payments,
                    'inquiries' => $inquiries,
                    'directory' => $directory
                ],
                'telegram' => $result
            ], JSON_PRETTY_PRINT);
        }
        break;
}

