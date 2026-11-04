<?php
/**
 * Social Media & Telegram Notification Dispatcher
 * UAP Quezon Province Chapter Portal
 * 
 * Provides real-time notification dispatch to Telegram bots/groups
 * and supports custom webhooks for social media integrations.
 */

if (!function_exists('get_telegram_config')) {
    function get_telegram_config(PDO $pdo): array {
        return [
            'enabled' => (bool)(int)get_site_setting($pdo, 'telegram_notifications_enabled', '1'),
            'bot_token' => trim(get_site_setting($pdo, 'telegram_bot_token', '9271918713:AAGYI59isn_J53xwq1yNK3g6WHUlVfi4RjA')),
            'chat_id' => trim(get_site_setting($pdo, 'telegram_chat_id', '')),
            'notify_inquiries' => (bool)(int)get_site_setting($pdo, 'telegram_notify_inquiries', '1'),
            'notify_registrations' => (bool)(int)get_site_setting($pdo, 'telegram_notify_registrations', '1'),
            'notify_payments' => (bool)(int)get_site_setting($pdo, 'telegram_notify_payments', '1'),
            'notify_directory' => (bool)(int)get_site_setting($pdo, 'telegram_notify_directory', '1'),
            'notify_attendance' => (bool)(int)get_site_setting($pdo, 'telegram_notify_attendance', '0'),
        ];
    }
}

if (!function_exists('send_telegram_message')) {
    function send_telegram_message(string $botToken, string $chatId, string $htmlMessage): array {
        $botToken = trim($botToken);
        $chatId = trim($chatId);

        if (empty($botToken) || empty($chatId)) {
            return ['success' => false, 'error' => 'Telegram Bot Token or Chat ID is missing.'];
        }

        $url = "https://api.telegram.org/bot" . urlencode($botToken) . "/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $htmlMessage,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        // 1. Try cURL if available
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false) {
                $json = json_decode($response, true);
                if ($httpCode >= 200 && $httpCode < 300 && !empty($json['ok'])) {
                    return ['success' => true, 'data' => $json];
                }
                $errMsg = $json['description'] ?? ('Telegram API returned HTTP ' . $httpCode);
                return ['success' => false, 'error' => $errMsg];
            }
        }

        // 2. Fallback to stream context file_get_contents
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($payload),
                'timeout' => 6
            ]
        ];
        $context  = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $json = json_decode($response, true);
            if (!empty($json['ok'])) {
                return ['success' => true, 'data' => $json];
            }
            return ['success' => false, 'error' => $json['description'] ?? 'Telegram API error'];
        }

        return ['success' => false, 'error' => 'Unable to connect to Telegram API servers. Check host network connectivity.'];
    }
}

if (!function_exists('notify_admin_event')) {
    function notify_admin_event(PDO $pdo, string $eventType, array $payload = []): array {
        try {
            $cfg = get_telegram_config($pdo);
            if (!$cfg['enabled'] || empty($cfg['bot_token']) || empty($cfg['chat_id'])) {
                return ['success' => false, 'skipped' => true, 'reason' => 'Telegram alerts disabled or not configured'];
            }

            $portalUrl = defined('BASE_URL') ? BASE_URL : 'http://uap-quezonchapter.page.gd';
            $msg = '';

            switch ($eventType) {
                case 'inquiry':
                    if (!$cfg['notify_inquiries']) return ['skipped' => true];
                    $name = htmlspecialchars($payload['name'] ?? 'Website Visitor');
                    $email = htmlspecialchars($payload['email'] ?? 'No email');
                    $phone = htmlspecialchars($payload['phone'] ?? '—');
                    $subject = htmlspecialchars($payload['subject'] ?? 'New Message');
                    $preview = htmlspecialchars(mb_strimwidth($payload['message'] ?? '', 0, 180, '...'));

                    $msg  = "📩 <b>NEW WEBSITE INQUIRY</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👤 <b>From:</b> {$name}\n";
                    $msg .= "📧 <b>Email:</b> {$email}\n";
                    $msg .= "📞 <b>Phone:</b> {$phone}\n";
                    $msg .= "📌 <b>Subject:</b> {$subject}\n";
                    $msg .= "💬 <b>Message:</b>\n<i>\"{$preview}\"</i>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👉 <a href=\"{$portalUrl}/admin/inquiries.php\">Open Inquiry in Admin Portal</a>";
                    break;

                case 'registration':
                    if (!$cfg['notify_registrations']) return ['skipped' => true];
                    $name = htmlspecialchars($payload['name'] ?? 'New Architect');
                    $prc = htmlspecialchars($payload['id_number'] ?? ($payload['prc_number'] ?? '—'));
                    $email = htmlspecialchars($payload['email'] ?? '—');
                    $phone = htmlspecialchars($payload['phone'] ?? '—');

                    $msg  = "👤 <b>NEW MEMBER REGISTRATION</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "🏛️ <b>Architect:</b> {$name}\n";
                    $msg .= "🪪 <b>PRC / Chapter ID:</b> <code>{$prc}</code>\n";
                    $msg .= "📧 <b>Email:</b> {$email}\n";
                    $msg .= "📞 <b>Phone:</b> {$phone}\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "⏳ Status: <b>Pending Admin Approval</b>\n";
                    $msg .= "👉 <a href=\"{$portalUrl}/admin/approvals.php\">Review &amp; Approve Member</a>";
                    break;

                case 'payment':
                    if (!$cfg['notify_payments']) return ['skipped' => true];
                    $name = htmlspecialchars($payload['name'] ?? 'Member');
                    $dueTitle = htmlspecialchars($payload['due_title'] ?? 'Chapter Due');
                    $amount = number_format((float)($payload['amount_paid'] ?? 0), 2);
                    $method = strtoupper(htmlspecialchars($payload['method'] ?? 'ONLINE'));
                    $ref = htmlspecialchars($payload['reference_number'] ?? '—');

                    $msg  = "💳 <b>NEW PAYMENT PROOF SUBMITTED</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👤 <b>Member:</b> {$name}\n";
                    $msg .= "📋 <b>Due / Fee:</b> {$dueTitle}\n";
                    $msg .= "💰 <b>Amount Paid:</b> ₱{$amount}\n";
                    $msg .= "🏦 <b>Payment Method:</b> {$method}\n";
                    $msg .= "🔖 <b>Reference No:</b> <code>{$ref}</code>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "⏳ Status: <b>Pending Verification</b>\n";
                    $msg .= "👉 <a href=\"{$portalUrl}/admin/payments.php\">Verify Payment in Portal</a>";
                    break;

                case 'directory_app':
                    if (!$cfg['notify_directory']) return ['skipped' => true];
                    $name = htmlspecialchars($payload['name'] ?? 'Architect');
                    $fee = number_format((float)($payload['fee_amount'] ?? 0), 2);

                    $msg  = "🏛️ <b>DIRECTORY LISTING APPLICATION</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👤 <b>Architect:</b> {$name}\n";
                    $msg .= "💵 <b>Advertising Fee:</b> ₱{$fee}\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👉 <a href=\"{$portalUrl}/admin/website_directory.php\">Review Application in Portal</a>";
                    break;

                case 'attendance':
                    if (!$cfg['notify_attendance']) return ['skipped' => true];
                    $name = htmlspecialchars($payload['name'] ?? 'Architect');
                    $eventTitle = htmlspecialchars($payload['event_title'] ?? 'Chapter Meeting');
                    $time = htmlspecialchars($payload['time'] ?? date('h:i A'));
                    $total = (int)($payload['total_count'] ?? 0);

                    $msg  = "🎟️ <b>ATTENDANCE CHECK-IN VERIFIED</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👤 <b>Architect:</b> {$name}\n";
                    $msg .= "📅 <b>Event:</b> {$eventTitle}\n";
                    $msg .= "⏰ <b>Time:</b> {$time}\n";
                    if ($total > 0) {
                        $msg .= "📊 <b>Total Attendees:</b> {$total}\n";
                    }
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👉 <a href=\"{$portalUrl}/admin/scan_attendance.php\">Open Live Attendees Roster</a>";
                    break;

                case 'test':
                    $msg  = "🔔 <b>UAP QUEZON &bull; NOTIFICATION TEST</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "✅ Telegram alerts are successfully configured!\n";
                    $msg .= "📅 <b>Timestamp:</b> " . date('F j, Y - h:i:s A') . "\n";
                    $msg .= "🌐 <b>Host:</b> {$portalUrl}\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "You will now receive instant push alerts whenever a member registers, pays dues, sends a contact inquiry, or submits an application.";
                    break;

                case 'summary':
                    $approvals = (int)($payload['approvals'] ?? 0);
                    $payments = (int)($payload['payments'] ?? 0);
                    $inquiries = (int)($payload['inquiries'] ?? 0);
                    $directory = (int)($payload['directory'] ?? 0);
                    $total = $approvals + $payments + $inquiries + $directory;

                    $msg  = "📊 <b>UAP QUEZON &bull; PENDING TASKS SUMMARY</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "📅 <b>Date:</b> " . date('F j, Y - h:i A') . "\n";
                    $msg .= "🔔 <b>Total Action Items:</b> {$total}\n\n";
                    $msg .= "• Member Approvals: <b>{$approvals}</b>\n";
                    $msg .= "• Payment Verifications: <b>{$payments}</b>\n";
                    $msg .= "• Unread Inquiries: <b>{$inquiries}</b>\n";
                    $msg .= "• Directory Applications: <b>{$directory}</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👉 <a href=\"{$portalUrl}/admin/dashboard.php\">Open Admin Dashboard</a>";
                    break;

                default:
                    $title = htmlspecialchars($payload['title'] ?? 'Chapter Portal Alert');
                    $body = htmlspecialchars($payload['message'] ?? 'A new notification was generated.');
                    $msg  = "🔔 <b>{$title}</b>\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "{$body}\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "👉 <a href=\"{$portalUrl}/admin/dashboard.php\">Open Admin Portal</a>";
                    break;
            }

            return send_telegram_message($cfg['bot_token'], $cfg['chat_id'], $msg);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

