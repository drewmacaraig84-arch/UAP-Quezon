<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';
$active_tab = $_POST['current_tab'] ?? ($_GET['tab'] ?? null);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? null;

    // Default active tab based on action if not explicitly sent
    if (in_array($action, ['upload_logo', 'upload_header_badges', 'update_info'])) {
        $active_tab = 'brandingTab';
    } elseif (in_array($action, ['update_about', 'add_milestone', 'edit_milestone', 'delete_milestone'])) {
        $active_tab = 'websiteTab';
    } elseif (in_array($action, ['add_officer', 'edit_officer', 'delete_officer', 'reorder_officers', 'update_officer_settings'])) {
        $active_tab = 'officersTab';
    } elseif (in_array($action, ['add_sponsor', 'edit_sponsor', 'delete_sponsor', 'reorder_sponsors'])) {
        $active_tab = 'sponsorsTab';
    } elseif (in_array($action, ['add_news', 'edit_news', 'delete_news', 'reorder_news'])) {
        $active_tab = 'newsTab';
    }
    } elseif (in_array($action, ['update_telegram_settings', 'test_telegram'])) {
        $active_tab = 'notificationsTab';

    // ============ LOGO UPLOAD ============
    if ($action === 'upload_logo' && isset($_FILES['logo'])) {
        if ($_FILES['logo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['logo']['error'] === UPLOAD_ERR_FORM_SIZE) {
            $error = 'Logo file size exceeds the server upload limit.';
        } elseif ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a logo image to upload.';
        } else {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                $error = 'Only valid JPG, PNG, or WebP images are allowed for logo.';
            } else {
                $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = __DIR__ . '/../uploads/' . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    $path = 'uploads/' . $filename;

                    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([$path]);
                    if (function_exists('cache_delete')) {
                        cache_delete('site_setting:logo');
                    }
                    $success = 'Organization logo updated successfully.';
                } else {
                    $error = 'Failed to upload logo image.';
                }
            }
        }
    }

    // ============ HEADER BADGES / 3 AUXILIARY IMAGES ============
    if ($action === 'upload_header_badges') {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $updatedCount = 0;

        for ($i = 1; $i <= 3; $i++) {
            $fieldKey = "header_image_{$i}";
            $clearKey = "clear_header_image_{$i}";

            // Clear if requested
            if (!empty($_POST[$clearKey])) {
                $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, '') ON DUPLICATE KEY UPDATE setting_value = ''");
                $stmt->execute([$fieldKey]);
                if (function_exists('cache_delete')) {
                    cache_delete("site_setting:{$fieldKey}");
                }
                $updatedCount++;
            }

            // Upload new image if provided
            if (isset($_FILES[$fieldKey]) && $_FILES[$fieldKey]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$fieldKey]['name'], PATHINFO_EXTENSION));
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES[$fieldKey]['tmp_name']);
                finfo_close($finfo);

                if (in_array($ext, $allowedExtensions) && in_array($mime, $allowedMimes)) {
                    $filename = "header_badge_{$i}_" . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $dest = __DIR__ . '/../uploads/' . $filename;
                    if (move_uploaded_file($_FILES[$fieldKey]['tmp_name'], $dest)) {
                        $path = 'uploads/' . $filename;
                        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                        $stmt->execute([$fieldKey, $path]);
                        if (function_exists('cache_delete')) {
                            cache_delete("site_setting:{$fieldKey}");
                        }
                        $updatedCount++;
                    } else {
                        $error = "Failed to save uploaded image for Slot {$i}. Please check write permissions on uploads folder.";
                    }
                } else {
                    $error = "Image for Slot {$i} must be a valid JPG, PNG, or WebP file.";
                }
            }
        }

        if (empty($error)) {
            $success = 'Header partner & affiliation images updated successfully.';
        }
    }

    // ============ CHAPTER INFO UPDATE ============
    if ($action === 'update_info') {
        $org_name = trim($_POST['org_name'] ?? '');
        if ($org_name !== '') {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('org_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$org_name]);
            $success = 'Organization information updated successfully.';
        }
    }

    // ============ ABOUT US & CONTACT UPDATE ============
    if ($action === 'update_about') {
        $keys = [
            'about_us'               => trim($_POST['about_us'] ?? ''),
            'contact_address'        => trim($_POST['contact_address'] ?? ''),
            'contact_email'          => trim($_POST['contact_email'] ?? ''),
            'contact_phone'          => trim($_POST['contact_phone'] ?? ''),
            'office_hours_weekdays'  => trim($_POST['office_hours_weekdays'] ?? ''),
            'office_hours_saturday'  => trim($_POST['office_hours_saturday'] ?? ''),
            'office_hours_sunday'    => trim($_POST['office_hours_sunday'] ?? ''),
            'hero_badge'             => trim($_POST['hero_badge'] ?? ''),
            'hero_title'             => trim($_POST['hero_title'] ?? ''),
            'hero_tagline'           => trim($_POST['hero_tagline'] ?? ''),
        ];

        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($keys as $k => $v) {
            $stmt->execute([$k, $v]);
            if (function_exists('cache_delete')) {
                cache_delete('site_setting:' . $k);
            }
        }
        $success = 'Website About Us and Secretariat Contact information updated successfully.';
    }

    // ============ SPONSOR MANAGEMENT ============
    if (!function_exists('get_sponsor_table_columns')) {
        function get_sponsor_table_columns($pdo) {
            static $cols = null;
            if ($cols !== null) return $cols;
            $cols = [];
            try {
                $raw = $pdo->query("SHOW COLUMNS FROM sponsors")->fetchAll(PDO::FETCH_ASSOC);
                $cols = array_column($raw, 'Field');
            } catch (Throwable $e) {}

            // Auto-create missing sponsor columns if needed
            if (!in_array('is_platinum', $cols, true)) {
                try { $pdo->exec("ALTER TABLE sponsors ADD COLUMN is_platinum TINYINT(1) NOT NULL DEFAULT 0"); $cols[] = 'is_platinum'; } catch (Throwable $e) {}
            }
            if (!in_array('products_json', $cols, true)) {
                try { $pdo->exec("ALTER TABLE sponsors ADD COLUMN products_json LONGTEXT NULL"); $cols[] = 'products_json'; } catch (Throwable $e) {}
            }
            if (!in_array('website_url', $cols, true)) {
                try { $pdo->exec("ALTER TABLE sponsors ADD COLUMN website_url VARCHAR(255) NULL"); $cols[] = 'website_url'; } catch (Throwable $e) {}
            }
            if (!in_array('url', $cols, true)) {
                try { $pdo->exec("ALTER TABLE sponsors ADD COLUMN url VARCHAR(255) NULL"); $cols[] = 'url'; } catch (Throwable $e) {}
            }
            if (!in_array('deleted_at', $cols, true)) {
                try { $pdo->exec("ALTER TABLE sponsors ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL"); $cols[] = 'deleted_at'; } catch (Throwable $e) {}
            }
            return $cols;
        }
    }
    if ($action === 'add_sponsor') {
        $sponsor_name = trim($_POST['sponsor_name'] ?? '');
        $is_platinum = !empty($_POST['is_platinum']) ? 1 : 0;
        if (empty($sponsor_name)) {
            $error = 'Sponsor name is required.';
        } else {
            $logo_path = trim($_POST['sponsor_logo_url'] ?? ''); // external URL fallback

            // If a file was uploaded, prefer that
            if (!empty($_FILES['sponsor_logo']['name']) && $_FILES['sponsor_logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['sponsor_logo']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['sponsor_logo']['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                    $error = 'Only valid JPG, PNG, or WebP images are allowed for sponsors.';
                } else {
                    $filename = 'sponsor_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $dest = __DIR__ . '/../uploads/' . $filename;
                    if (move_uploaded_file($_FILES['sponsor_logo']['tmp_name'], $dest)) {
                        $logo_path = 'uploads/' . $filename;
                    } else {
                        $error = 'Failed to upload sponsor logo.';
                    }
                }
            }

            // Process Promotional Products (Up to 10 for Platinum, 1 for Regular)
            $maxProducts = $is_platinum ? 10 : 1;
            $products = [];
            $uploadDir = __DIR__ . '/../uploads/sponsors/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $rawProducts = $_POST['products'] ?? [];
            if (is_array($rawProducts)) {
                foreach ($rawProducts as $k => $p) {
                    if (!empty($p['delete'])) continue;
                    if (count($products) >= $maxProducts) break;
                    $pName = trim($p['name'] ?? '');
                    $pDesc = trim($p['description'] ?? '');
                    $pLink = trim($p['link_url'] ?? '');
                    $pId = !empty($p['id']) ? trim($p['id']) : ('prod_' . time() . '_' . bin2hex(random_bytes(3)));
                    $existingImg = trim($p['existing_image'] ?? '');
                    $imgPath = $existingImg;

                    if (isset($_FILES['products']['name'][$k]['image']) && $_FILES['products']['error'][$k]['image'] === UPLOAD_ERR_OK) {
                        $fName = $_FILES['products']['name'][$k]['image'];
                        $fExt = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                        if (in_array($fExt, $allowedExts) && $_FILES['products']['size'][$k]['image'] <= 12 * 1024 * 1024) {
                            $cleanK = preg_replace('/[^a-zA-Z0-9_-]/', '', $k);
                            $pFileName = 'prod_' . time() . '_' . $cleanK . '_' . bin2hex(random_bytes(3)) . '.' . $fExt;
                            $pDest = $uploadDir . $pFileName;
                            if (move_uploaded_file($_FILES['products']['tmp_name'][$k]['image'], $pDest)) {
                                $imgPath = 'uploads/sponsors/' . $pFileName;
                            }
                        }
                    } elseif (!empty($p['image_url'])) {
                        $imgPath = trim($p['image_url']);
                    }

                    if (!empty($pName) || !empty($imgPath) || !empty($pDesc)) {
                        $products[] = [
                            'id' => $pId,
                            'name' => $pName !== '' ? $pName : 'Featured Promotional Product',
                            'description' => $pDesc,
                            'link_url' => $pLink,
                            'image_path' => $imgPath
                        ];
                    }
                }
            }
            $products = array_slice($products, 0, $maxProducts);
            $products_json = !empty($products) ? json_encode($products) : null;

            if (empty($error)) {
                $tier = $is_platinum ? 'platinum' : 'gold';
                $sponsorUrl = trim($_POST['sponsor_url'] ?? '');
                $sponsorDesc = trim($_POST['sponsor_desc'] ?? '');

                $sponsorCols = get_sponsor_table_columns($pdo);
                $insertData = [
                    'name' => $sponsor_name,
                    'tier' => $tier,
                    'description' => $sponsorDesc,
                ];
                if ($logo_path) {
                    $insertData['logo_path'] = $logo_path;
                }
                if (in_array('website_url', $sponsorCols, true)) {
                    $insertData['website_url'] = $sponsorUrl;
                }
                if (in_array('url', $sponsorCols, true)) {
                    $insertData['url'] = $sponsorUrl;
                }
                if (in_array('is_platinum', $sponsorCols, true)) {
                    $insertData['is_platinum'] = $is_platinum;
                }
                if (in_array('products_json', $sponsorCols, true)) {
                    $insertData['products_json'] = $products_json;
                }

                $fields = array_keys($insertData);
                $fields[] = 'display_order';
                $placeholders = array_fill(0, count($insertData), '?');
                $placeholders[] = '(SELECT COALESCE(MAX(display_order), 0) + 1 FROM sponsors s2)';

                $stmt = $pdo->prepare("INSERT INTO sponsors (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
                $stmt->execute(array_values($insertData));
                $success = 'Sponsor partner added successfully.';
            }
        }
    }

    if ($action === 'edit_sponsor' && !empty($_POST['sponsor_id'] ?? '') && !empty($_POST['sponsor_name'] ?? '')) {
        $sponsor_id = (int)$_POST['sponsor_id'];
        $sponsor_name = trim($_POST['sponsor_name']);
        $sponsor_desc = trim($_POST['sponsor_desc'] ?? '');
        $sponsor_url = trim($_POST['sponsor_url'] ?? '');
        $is_platinum = !empty($_POST['is_platinum']) ? 1 : 0;

        $logo_path = null;
        if (!empty($_FILES['sponsor_logo']['name']) && $_FILES['sponsor_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['sponsor_logo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['sponsor_logo']['tmp_name']);
            finfo_close($finfo);

            if (in_array($ext, $allowedExtensions) && in_array($mime, $allowedMimes)) {
                $filename = 'sponsor_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = __DIR__ . '/../uploads/' . $filename;
                if (move_uploaded_file($_FILES['sponsor_logo']['tmp_name'], $dest)) {
                    $logo_path = 'uploads/' . $filename;
                }
            }
        }

        // Process Promotional Products (Up to 10 for Platinum, 1 for Regular)
        $maxProducts = $is_platinum ? 10 : 1;
        $products = [];
        $uploadDir = __DIR__ . '/../uploads/sponsors/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $rawProducts = $_POST['products'] ?? [];
        if (is_array($rawProducts)) {
            foreach ($rawProducts as $k => $p) {
                if (!empty($p['delete'])) continue;
                if (count($products) >= $maxProducts) break;
                $pName = trim($p['name'] ?? '');
                $pDesc = trim($p['description'] ?? '');
                $pLink = trim($p['link_url'] ?? '');
                $pId = !empty($p['id']) ? trim($p['id']) : ('prod_' . time() . '_' . bin2hex(random_bytes(3)));
                $existingImg = trim($p['existing_image'] ?? '');
                $imgPath = $existingImg;

                if (isset($_FILES['products']['name'][$k]['image']) && $_FILES['products']['error'][$k]['image'] === UPLOAD_ERR_OK) {
                    $fName = $_FILES['products']['name'][$k]['image'];
                    $fExt = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                    if (in_array($fExt, $allowedExts) && $_FILES['products']['size'][$k]['image'] <= 12 * 1024 * 1024) {
                        $pFileName = 'prod_' . time() . '_' . $k . '_' . bin2hex(random_bytes(3)) . '.' . $fExt;
                        $pDest = $uploadDir . $pFileName;
                        if (move_uploaded_file($_FILES['products']['tmp_name'][$k]['image'], $pDest)) {
                            $imgPath = 'uploads/sponsors/' . $pFileName;
                        }
                    }
                } elseif (!empty($p['image_url'])) {
                    $imgPath = trim($p['image_url']);
                }

                if (!empty($pName) || !empty($imgPath) || !empty($pDesc)) {
                    $products[] = [
                        'id' => $pId,
                        'name' => $pName !== '' ? $pName : 'Featured Promotional Product',
                        'description' => $pDesc,
                        'link_url' => $pLink,
                        'image_path' => $imgPath
                    ];
                }
            }
        }
        $products = array_slice($products, 0, $maxProducts);
        $products_json = !empty($products) ? json_encode($products) : null;

        $tier = $is_platinum ? 'platinum' : 'gold';
        $sponsorCols = get_sponsor_table_columns($pdo);
        $updateData = [
            'name' => $sponsor_name,
            'tier' => $tier,
            'description' => $sponsor_desc,
        ];
        if ($logo_path) {
            $updateData['logo_path'] = $logo_path;
        }
        if (in_array('website_url', $sponsorCols, true)) {
            $updateData['website_url'] = $sponsor_url;
        }
        if (in_array('url', $sponsorCols, true)) {
            $updateData['url'] = $sponsor_url;
        }
        if (in_array('is_platinum', $sponsorCols, true)) {
            $updateData['is_platinum'] = $is_platinum;
        }
        if (in_array('products_json', $sponsorCols, true)) {
            $updateData['products_json'] = $products_json;
        }

        $setClauses = [];
        $params = [];
        foreach ($updateData as $c => $val) {
            $setClauses[] = "$c = ?";
            $params[] = $val;
        }
        $params[] = $sponsor_id;

        $stmt = $pdo->prepare("UPDATE sponsors SET " . implode(', ', $setClauses) . " WHERE id = ?");
        $stmt->execute($params);
        $success = 'Sponsor partner updated successfully.';
    }

    if ($action === 'delete_sponsor' && !empty($_POST['sponsor_id'] ?? '')) {
        $stmt = $pdo->prepare("DELETE FROM sponsors WHERE id = ?");
        $stmt->execute([$_POST['sponsor_id']]);
        $success = 'Sponsor deleted successfully.';
    }

    // ============ NEWS & ANNOUNCEMENTS MANAGEMENT ============
    if ($action === 'add_news' && !empty($_POST['news_title'] ?? '')) {
        $image_path = null;
        $uploadDir = __DIR__ . '/../uploads/news/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['news_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts) && $_FILES['news_image']['size'] <= 12 * 1024 * 1024) {
                $fileName = 'news_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($_FILES['news_image']['tmp_name'], $uploadDir . $fileName)) {
                    $image_path = 'uploads/news/' . $fileName;
                }
            }
        }

        $news_date = !empty($_POST['news_date']) ? $_POST['news_date'] : date('Y-m-d');
        $date_display = date('M d, Y', strtotime($news_date));
        $content = !empty($_POST['news_content']) ? trim($_POST['news_content']) : ($_POST['news_summary'] ?? '');
        $stmt = $pdo->prepare("INSERT INTO news_announcements (title, summary, content, image_path, date_posted, date_display, target_audience, is_published, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, 'public', 1, 1, (SELECT COALESCE(MAX(display_order), 0) + 1 FROM news_announcements n2))");
        $stmt->execute([$_POST['news_title'], $_POST['news_summary'] ?? '', $content, $image_path, $news_date, $date_display]);
        $success = 'News announcement added successfully.';
    }

    if ($action === 'edit_news' && !empty($_POST['news_id'] ?? '') && !empty($_POST['news_title'] ?? '')) {
        $news_id = (int)$_POST['news_id'];
        $title = trim($_POST['news_title']);
        $summary = trim($_POST['news_summary'] ?? '');
        $date = !empty($_POST['news_date']) ? $_POST['news_date'] : date('Y-m-d');

        $stmtCur = $pdo->prepare("SELECT image_path FROM news_announcements WHERE id = ?");
        $stmtCur->execute([$news_id]);
        $curImage = $stmtCur->fetchColumn();
        $image_path = $curImage;

        if (!empty($_POST['remove_news_image'])) {
            if ($curImage && file_exists(__DIR__ . '/../' . ltrim($curImage, '/'))) {
                @unlink(__DIR__ . '/../' . ltrim($curImage, '/'));
            }
            $image_path = null;
        }

        if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['news_image']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowedExts) && $_FILES['news_image']['size'] <= 12 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../uploads/news/';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
                $fileName = 'news_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($_FILES['news_image']['tmp_name'], $uploadDir . $fileName)) {
                    if ($curImage && file_exists(__DIR__ . '/../' . ltrim($curImage, '/'))) {
                        @unlink(__DIR__ . '/../' . ltrim($curImage, '/'));
                    }
                    $image_path = 'uploads/news/' . $fileName;
                }
            }
        }

        $date_display = date('M d, Y', strtotime($date));
        $content = !empty($_POST['news_content']) ? trim($_POST['news_content']) : $summary;
        $stmt = $pdo->prepare("UPDATE news_announcements SET title = ?, summary = ?, content = ?, image_path = ?, date_posted = ?, date_display = ?, target_audience = 'public', is_published = 1, is_active = 1 WHERE id = ?");
        $stmt->execute([$title, $summary, $content, $image_path, $date, $date_display, $news_id]);
        $success = 'News announcement updated successfully.';
    }

    if ($action === 'delete_news' && !empty($_POST['news_id'] ?? '')) {
        $stmtCur = $pdo->prepare("SELECT image_path FROM news_announcements WHERE id = ?");
        $stmtCur->execute([$_POST['news_id']]);
        $curImage = $stmtCur->fetchColumn();
        if ($curImage && file_exists(__DIR__ . '/../' . ltrim($curImage, '/'))) {
            @unlink(__DIR__ . '/../' . ltrim($curImage, '/'));
        }

        $stmt = $pdo->prepare("DELETE FROM news_announcements WHERE id = ?");
        $stmt->execute([$_POST['news_id']]);

        // Re-compact display_order to 1, 2, 3...
        $remainingNews = $pdo->query("SELECT id FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC, id DESC")->fetchAll(PDO::FETCH_COLUMN);
        $recompactStmt = $pdo->prepare("UPDATE news_announcements SET display_order = ? WHERE id = ?");
        foreach ($remainingNews as $idx => $rId) {
            $recompactStmt->execute([$idx + 1, $rId]);
        }

        $success = 'News announcement deleted successfully.';
    }

    // Drag-and-drop reorder: receives JSON array of IDs in new order
    if ($action === 'reorder_news' && !empty($_POST['order'] ?? '')) {
        $ids = json_decode($_POST['order'], true);
        if (is_array($ids)) {
            $stmt = $pdo->prepare("UPDATE news_announcements SET display_order = ? WHERE id = ?");
            foreach ($ids as $pos => $id) {
                $stmt->execute([$pos + 1, (int)$id]);
            }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        $success = 'News order updated.';
    }

    // ============ CHAPTER MILESTONES MANAGEMENT ============
    if ($action === 'add_milestone' && !empty($_POST['milestone_year'] ?? '') && !empty($_POST['milestone_title'] ?? '')) {
        $stmt = $pdo->prepare("INSERT INTO chapter_milestones (year, title, content, sort_order) VALUES (?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM chapter_milestones cm2))");
        $stmt->execute([
            trim($_POST['milestone_year']),
            trim($_POST['milestone_title']),
            trim($_POST['milestone_content'] ?? ''),
        ]);
        $success = 'Chapter milestone added successfully.';
    }

    if ($action === 'edit_milestone' && !empty($_POST['milestone_id'] ?? '') && !empty($_POST['milestone_title'] ?? '')) {
        $stmt = $pdo->prepare("UPDATE chapter_milestones SET year = ?, title = ?, content = ? WHERE id = ?");
        $stmt->execute([
            trim($_POST['milestone_year']),
            trim($_POST['milestone_title']),
            trim($_POST['milestone_content'] ?? ''),
            (int)$_POST['milestone_id'],
        ]);
        $success = 'Chapter milestone updated successfully.';
    }

    if ($action === 'delete_milestone' && !empty($_POST['milestone_id'] ?? '')) {
        $stmt = $pdo->prepare("DELETE FROM chapter_milestones WHERE id = ?");
        $stmt->execute([(int)$_POST['milestone_id']]);
        $success = 'Chapter milestone deleted successfully.';
    }

    // ============ CHAPTER OFFICERS MANAGEMENT ============
    if ($action === 'add_officer') {
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $title_prefix = trim($_POST['title_prefix'] ?? 'Ar.');
        $title_suffix = trim($_POST['title_suffix'] ?? 'uap');
        $position = trim($_POST['position'] ?? '');
        $committee = trim($_POST['committee'] ?? '');
        $fiscal_year = trim($_POST['fiscal_year'] ?? '2026-2027');
        $bio = trim($_POST['bio'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) && $user_id) {
            $u = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $u->execute([$user_id]);
            $name = $u->fetchColumn() ?: 'Chapter Officer';
        }

        if (empty($name) || empty($position)) {
            $error = 'Please provide both the Officer Name and Position.';
        } else {
            $photo_path = null;
            if (!empty($_FILES['officer_photo']['name']) && $_FILES['officer_photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['officer_photo']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['officer_photo']['tmp_name']);
                finfo_close($finfo);

                if (in_array($ext, $allowedExtensions) && in_array($mime, $allowedMimes)) {
                    $filename = 'officer_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $dest = __DIR__ . '/../uploads/officers/' . $filename;
                    if (!is_dir(dirname($dest))) {
                        @mkdir(dirname($dest), 0775, true);
                    }
                    if (move_uploaded_file($_FILES['officer_photo']['tmp_name'], $dest)) {
                        $photo_path = 'uploads/officers/' . $filename;
                    }
                }
            }

            if (!$photo_path && $user_id) {
                $uPhoto = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
                $uPhoto->execute([$user_id]);
                $memberPhoto = $uPhoto->fetchColumn();
                if ($memberPhoto) {
                    $photo_path = $memberPhoto;
                }
            }

            $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(display_order), 0) FROM chapter_officers WHERE deleted_at IS NULL")->fetchColumn();
            $display_order = $maxOrder + 1;

            $stmt = $pdo->prepare("
                INSERT INTO chapter_officers 
                (user_id, name, title_prefix, title_suffix, position, committee, fiscal_year, photo_path, bio, display_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $name, $title_prefix, $title_suffix, $position, $committee, $fiscal_year, $photo_path, $bio, $display_order, $is_active]);
            $success = "Officer '{$name}' added successfully.";
        }
    }

    if ($action === 'edit_officer' && !empty($_POST['officer_id'])) {
        $officer_id = (int)$_POST['officer_id'];
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $name = trim($_POST['name'] ?? '');
        $title_prefix = trim($_POST['title_prefix'] ?? 'Ar.');
        $title_suffix = trim($_POST['title_suffix'] ?? 'uap');
        $position = trim($_POST['position'] ?? '');
        $committee = trim($_POST['committee'] ?? '');
        $fiscal_year = trim($_POST['fiscal_year'] ?? '2026-2027');
        $bio = trim($_POST['bio'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $remove_photo = !empty($_POST['remove_officer_photo']);

        if (empty($name) || empty($position)) {
            $error = 'Please provide both the Officer Name and Position.';
        } else {
            $photo_path = null;
            if (!empty($_FILES['officer_photo']['name']) && $_FILES['officer_photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['officer_photo']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['officer_photo']['tmp_name']);
                finfo_close($finfo);

                if (in_array($ext, $allowedExtensions) && in_array($mime, $allowedMimes)) {
                    $filename = 'officer_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $dest = __DIR__ . '/../uploads/officers/' . $filename;
                    if (!is_dir(dirname($dest))) {
                        @mkdir(dirname($dest), 0775, true);
                    }
                    if (move_uploaded_file($_FILES['officer_photo']['tmp_name'], $dest)) {
                        $photo_path = 'uploads/officers/' . $filename;
                    }
                }
            }

            if ($photo_path) {
                $stmt = $pdo->prepare("
                    UPDATE chapter_officers 
                    SET user_id = ?, name = ?, title_prefix = ?, title_suffix = ?, position = ?, committee = ?, fiscal_year = ?, photo_path = ?, bio = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $name, $title_prefix, $title_suffix, $position, $committee, $fiscal_year, $photo_path, $bio, $is_active, $officer_id]);
            } elseif ($remove_photo) {
                $stmt = $pdo->prepare("
                    UPDATE chapter_officers 
                    SET user_id = ?, name = ?, title_prefix = ?, title_suffix = ?, position = ?, committee = ?, fiscal_year = ?, photo_path = NULL, bio = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $name, $title_prefix, $title_suffix, $position, $committee, $fiscal_year, $bio, $is_active, $officer_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE chapter_officers 
                    SET user_id = ?, name = ?, title_prefix = ?, title_suffix = ?, position = ?, committee = ?, fiscal_year = ?, bio = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $name, $title_prefix, $title_suffix, $position, $committee, $fiscal_year, $bio, $is_active, $officer_id]);
            }

            $success = "Officer '{$name}' updated successfully.";
        }
    }

    if ($action === 'delete_officer' && !empty($_POST['officer_id'])) {
        $officer_id = (int)$_POST['officer_id'];
        $stmt = $pdo->prepare("UPDATE chapter_officers SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$officer_id]);
        $success = 'Officer removed successfully.';
    }

    if ($action === 'reorder_officers' && !empty($_POST['order'])) {
        $ids = json_decode($_POST['order'], true);
        if (is_array($ids)) {
            $stmt = $pdo->prepare("UPDATE chapter_officers SET display_order = ? WHERE id = ?");
            foreach ($ids as $pos => $id) {
                $stmt->execute([$pos + 1, (int)$id]);
            }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        $success = 'Officers order updated.';
    }

    if ($action === 'update_officer_settings') {
        $fy = trim($_POST['chapter_fiscal_year'] ?? '2026-2027');
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('chapter_fiscal_year', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$fy]);
        $success = 'Chapter Fiscal Year updated.';
    }

    // ============ SOCIAL MEDIA & TELEGRAM ALERTS ============
    if ($action === 'update_telegram_settings') {
        $enabled = !empty($_POST['telegram_notifications_enabled']) ? '1' : '0';
        $token = trim($_POST['telegram_bot_token'] ?? '');
        $chatId = trim($_POST['telegram_chat_id'] ?? '');
        $notifyInq = !empty($_POST['telegram_notify_inquiries']) ? '1' : '0';
        $notifyReg = !empty($_POST['telegram_notify_registrations']) ? '1' : '0';
        $notifyPay = !empty($_POST['telegram_notify_payments']) ? '1' : '0';
        $notifyDir = !empty($_POST['telegram_notify_directory']) ? '1' : '0';
        $notifyAtt = !empty($_POST['telegram_notify_attendance']) ? '1' : '0';

        $settingsToSave = [
            'telegram_notifications_enabled' => $enabled,
            'telegram_bot_token' => $token,
            'telegram_chat_id' => $chatId,
            'telegram_notify_inquiries' => $notifyInq,
            'telegram_notify_registrations' => $notifyReg,
            'telegram_notify_payments' => $notifyPay,
            'telegram_notify_directory' => $notifyDir,
            'telegram_notify_attendance' => $notifyAtt,
        ];

        $saveStmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settingsToSave as $k => $v) {
            $saveStmt->execute([$k, $v]);
            if (function_exists('cache_delete')) {
                cache_delete("site_setting:{$k}");
            }
        }
        $success = 'Social & Telegram notification settings saved successfully.';
    }

    if ($action === 'test_telegram') {
        require_once __DIR__ . '/../includes/social_notifier.php';
        $testRes = notify_admin_event($pdo, 'test');
        if (!empty($testRes['success'])) {
            $success = 'Test alert dispatched successfully! Please check your Telegram conversation or group.';
        } else {
            $error = 'Telegram Alert Failed: ' . ($testRes['error'] ?? 'Please check your Bot Token, Chat ID, and host internet access.');
        }
    }
}

// Fetch current data
$settings_rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$logo_val = $settings_rows['logo'] ?? null;
$about_us_val = $settings_rows['about_us'] ?? '';
$org_name_val = $settings_rows['org_name'] ?? 'United Architects of the Philippines - Quezon Province Chapter';
$contact_address_val = $settings_rows['contact_address'] ?? 'Lucena City, Quezon Province, Philippines';
$contact_email_val = $settings_rows['contact_email'] ?? 'uap.qpc@gmail.com';
$contact_phone_val = $settings_rows['contact_phone'] ?? '09399125237 / 09175939781';
$office_hours_weekdays_val = $settings_rows['office_hours_weekdays'] ?? '9:00 AM – 5:00 PM';
$office_hours_saturday_val = $settings_rows['office_hours_saturday'] ?? '9:00 AM – 12:00 PM';
$office_hours_sunday_val = $settings_rows['office_hours_sunday'] ?? 'Closed';
$hero_badge_val = $settings_rows['hero_badge'] ?? 'DISTRICT - B4 • QUEZON PROVINCE';
if (in_array($hero_badge_val, ['District B4 • Quezon Province', 'Area B • Quezon Province', 'Area B • Southern Luzon Region', 'District B4'], true)) {
    $hero_badge_val = 'DISTRICT - B4 • QUEZON PROVINCE';
}
$hero_title_val = $settings_rows['hero_title'] ?? '“TAGUYOD”';
$hero_tagline_val = $settings_rows['hero_tagline'] ?? 'MOVERS OF PROGRESS 2.0';

$sponsors = [];
try {
    $sponsors = $pdo->query("SELECT * FROM sponsors WHERE is_active = 1 AND deleted_at IS NULL ORDER BY display_order ASC")->fetchAll();
} catch (Throwable $e) {
    try {
        $sponsors = $pdo->query("SELECT * FROM sponsors WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();
    } catch (Throwable $e2) {
        $sponsors = [];
    }
}
$news = $pdo->query("SELECT * FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC, id DESC")->fetchAll();
$milestones = [];
try {
    $milestones = $pdo->query("SELECT * FROM chapter_milestones ORDER BY sort_order ASC, year ASC")->fetchAll();
} catch (Throwable $e) {}

$chapter_fiscal_year_val = $settings_rows['chapter_fiscal_year'] ?? '2026-2027';

$telegram_enabled_val = (bool)(int)($settings_rows['telegram_notifications_enabled'] ?? '0');
$telegram_bot_token_val = $settings_rows['telegram_bot_token'] ?? '';
$telegram_chat_id_val = $settings_rows['telegram_chat_id'] ?? '';
$telegram_notify_inquiries_val = (bool)(int)($settings_rows['telegram_notify_inquiries'] ?? '1');
$telegram_notify_registrations_val = (bool)(int)($settings_rows['telegram_notify_registrations'] ?? '1');
$telegram_notify_payments_val = (bool)(int)($settings_rows['telegram_notify_payments'] ?? '1');
$telegram_notify_directory_val = (bool)(int)($settings_rows['telegram_notify_directory'] ?? '1');
$telegram_notify_attendance_val = (bool)(int)($settings_rows['telegram_notify_attendance'] ?? '0');

$chapterOfficers = [];
try {
    $chapterOfficers = $pdo->query("
        SELECT co.*, u.id_number, u.prc_number, u.profile_photo AS member_avatar, u.status AS user_status,
               wm.id as directory_id
        FROM chapter_officers co
        LEFT JOIN users u ON co.user_id = u.id AND u.deleted_at IS NULL
        LEFT JOIN website_members wm ON co.user_id = wm.user_id AND wm.deleted_at IS NULL
        WHERE co.deleted_at IS NULL
        ORDER BY co.display_order ASC, co.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $chapterOfficers = [];
}

$eligibleMembers = [];
try {
    $eligibleMembers = $pdo->query("
        SELECT id, name, prc_number, profile_photo, role
        FROM users
        WHERE deleted_at IS NULL AND status = 'approved'
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $eligibleMembers = [];
}

$page_title = 'System & Website Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">CONFIGURATION &amp; CONTENT MANAGEMENT</p>
    <h1>Settings &amp; Preferences</h1>
    <p class="page-subtitle">Configure organization branding, chapter details, website content, sponsors, and news announcements.</p>
  </div>
  <div style="display: flex; gap: 10px; align-items: center;">
    <a href="<?php echo BASE_URL; ?>/admin/change_password.php" class="btn btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('key', '', 14); ?> <span>Change Password</span>
    </a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success">
    <div style="display: flex; align-items: center; gap: 8px;">
      <?php echo icon('check', '', 18); ?>
      <span><strong>Success:</strong> <?php echo htmlspecialchars($success); ?></span>
    </div>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-error">
    <div style="display: flex; align-items: center; gap: 8px;">
      <?php echo icon('alert', '', 18); ?>
      <span><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- NAVIGATION TABS -->
<div style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
  <button type="button" class="tab-btn" data-tab="brandingTab" onclick="switchSettingsTab('brandingTab', this)">
    <?php echo icon('image', '', 16); ?> <span>Branding &amp; Organization</span>
  </button>
  <button type="button" class="tab-btn" data-tab="websiteTab" onclick="switchSettingsTab('websiteTab', this)">
    <?php echo icon('website_directory', '', 16); ?> <span>Website About Us</span>
  </button>
  <button type="button" class="tab-btn" data-tab="officersTab" onclick="switchSettingsTab('officersTab', this)">
    <?php echo icon('members', '', 16); ?> <span>Chapter Officers (<?php echo count($chapterOfficers); ?>)</span>
  </button>
  <button type="button" class="tab-btn" data-tab="sponsorsTab" onclick="switchSettingsTab('sponsorsTab', this)">
    <?php echo icon('handshake', '', 16); ?> <span>Sponsors &amp; Partners (<?php echo count($sponsors); ?>)</span>
  </button>
  <button type="button" class="tab-btn" data-tab="newsTab" onclick="switchSettingsTab('newsTab', this)">
    <?php echo icon('newspaper', '', 16); ?> <span>News &amp; Updates (<?php echo count($news); ?>)</span>
  </button>
  <button type="button" class="tab-btn" data-tab="notificationsTab" onclick="switchSettingsTab('notificationsTab', this)">
    <?php echo icon('bell', '', 16); ?> <span>Social Alerts &amp; Telegram</span>
  </button>
</div>

<style>
.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.tab-btn:hover {
  background: var(--bg-secondary);
  color: var(--text-primary);
}
.tab-btn.active {
  background: var(--button-primary, #f2b835);
  color: var(--button-primary-text, #1f2937);
  border-color: transparent;
  box-shadow: 0 4px 12px var(--button-shadow);
}
.settings-panel {
  display: none;
}
.settings-panel.active {
  display: block;
}
</style>

<script>
function switchSettingsTab(tabId, btn) {
  document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

  const target = document.getElementById(tabId);
  if (target) target.classList.add('active');

  const targetBtn = btn || document.querySelector('.tab-btn[data-tab="' + tabId + '"]');
  if (targetBtn) targetBtn.classList.add('active');

  // Persist tab across page reloads
  try {
    localStorage.setItem('admin_settings_tab', tabId);
  } catch(e) {}

  // Update URL hash without scrolling
  if (window.history && window.history.replaceState) {
    window.history.replaceState(null, null, '#' + tabId);
  }
}

// Automatically restore the current tab on load or after save
(function() {
  function initTab() {
    const hash = window.location.hash.replace('#', '');
    const urlParam = new URLSearchParams(window.location.search).get('tab');
    const phpTab = <?php echo json_encode($active_tab); ?>;
    let storedTab = null;
    try {
      storedTab = localStorage.getItem('admin_settings_tab');
    } catch(e) {}

    const selectedTab = phpTab || (hash && document.getElementById(hash) ? hash : null) || (urlParam && document.getElementById(urlParam) ? urlParam : null) || (storedTab && document.getElementById(storedTab) ? storedTab : null) || 'brandingTab';

    if (document.getElementById(selectedTab)) {
      switchSettingsTab(selectedTab);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTab);
  } else {
    initTab();
  }
})();
</script>


<!-- TAB 1: BRANDING & ORGANIZATION -->
<div id="brandingTab" class="settings-panel">
  <div class="grid-2" style="gap: 24px;">
    <!-- LOGO SECTION -->
    <div class="card" style="margin: 0;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(242,184,53,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
          <?php echo icon('image', '', 18); ?>
        </div>
        <h2 style="font-size: 16px; margin: 0;">Chapter Logo &amp; Seal</h2>
      </div>
      <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Upload the official UAP Quezon Province Chapter seal. Used on navigation headers, login portals, and receipts.</p>
      
      <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 20px; padding: 14px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="width: 80px; height: 80px; border-radius: 4px; background: #fff; padding: 1px; border: 1px solid rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.1); flex-shrink: 0;">
          <?php
            $logo_src = $logo_val ? (BASE_URL . '/' . htmlspecialchars($logo_val)) : (BASE_URL . '/public/logo.jpg');
          ?>
          <img src="<?php echo $logo_src; ?>" alt="Current Logo" onerror="if(this.src.indexOf('public/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/public/logo.jpg';" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 3px;">
        </div>
        <div>
          <strong style="display: block; font-size: 14px; margin-bottom: 4px;">Official Seal</strong>
          <span class="muted" style="font-size: 12px; display: block; margin-bottom: 8px;">Recommended: Transparent PNG or crisp JPG (min 200x200px)</span>
          <span class="badge-pill" style="background: rgba(16,185,129,0.15); color: #10b981; font-size: 11px; padding: 2px 8px;">Active</span>
        </div>
      </div>

      <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="upload_logo">
        <div class="field" style="margin-bottom: 12px;">
          <label>Select New Logo File</label>
          <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" required style="width: 100%;">
        </div>
        <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('upload', '', 14); ?> <span><?php echo $logo_val ? 'Replace Chapter Logo' : 'Upload Logo'; ?></span>
        </button>
      </form>
    </div>

    <!-- ORGANIZATION INFO -->
    <div class="card" style="margin: 0;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59,130,246,0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
          <?php echo icon('building', '', 18); ?>
        </div>
        <h2 style="font-size: 16px; margin: 0;">Chapter Title &amp; Information</h2>
      </div>
      <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Official chapter legal/organizational title printed across official receipts and system headers.</p>

      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_info">
        <div class="field" style="margin-bottom: 16px;">
          <label>Organization Name</label>
          <input type="text" name="org_name" value="<?php echo htmlspecialchars($org_name_val); ?>" required placeholder="e.g. United Architects of the Philippines - Quezon Province Chapter">
        </div>
        <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('check', '', 14); ?> <span>Save Organization Title</span>
        </button>
      </form>

      <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color);">
        <strong style="display: block; font-size: 13px; margin-bottom: 8px;">Administration Quick Links</strong>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="<?php echo BASE_URL; ?>/admin/qr_codes.php" class="btn btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 12px;">
            <?php echo icon('qr_codes', '', 14); ?> <span>QR Codes</span>
          </a>
          <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="btn btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 12px;">
            <?php echo icon('reports', '', 14); ?> <span>Reports</span>
          </a>
          <a href="<?php echo BASE_URL; ?>/admin/account_manager.php" class="btn btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 12px;">
            <?php echo icon('account_manager', '', 14); ?> <span>Admin Accounts</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- HEADER AFFILIATION BADGES / 3 IMAGES -->
  <div class="card" style="margin-top: 24px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
        <?php echo icon('image', '', 18); ?>
      </div>
      <div>
        <h2 style="font-size: 16px; margin: 0;">Header Badges &amp; Auxiliary Logos (3 Slots)</h2>
        <p class="muted" style="margin: 0; font-size: 13px;">Upload up to 3 affiliation logos or partner badges displayed in the top website header beside the Chapter title.</p>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" style="margin-top: 16px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="upload_header_badges">

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 20px;">
        <?php for ($i = 1; $i <= 3; $i++): 
          $badge_val = trim($settings_rows["header_image_{$i}"] ?? '');
          $badge_fs = $badge_val ? resolve_media_filesystem_path($badge_val) : null;
          $badge_exists = $badge_val && ($badge_fs || str_starts_with($badge_val, 'http'));
          $badge_src = $badge_exists ? media_url($badge_val) : null;
        ?>
          <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px;">
            <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 14px;">
              <div style="width: 50px; height: 50px; border-radius: 6px; background: #fff; border: 1px solid <?php echo ($badge_val && !$badge_exists) ? '#ef4444' : 'rgba(245,158,11,0.4)'; ?>; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                <?php if ($badge_src): ?>
                  <img src="<?php echo htmlspecialchars($badge_src); ?>" alt="Header Slot <?php echo $i; ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                <?php elseif ($badge_val && !$badge_exists): ?>
                  <span style="font-size: 10px; font-weight: 700; color: #ef4444; text-align: center; line-height: 1.1;">File<br>Missing</span>
                <?php else: ?>
                  <span style="font-size: 11px; font-weight: 700; color: #9ca3af; text-align: center; line-height: 1.1;">Slot <?php echo $i; ?><br><small style="font-size:9px;">Empty</small></span>
                <?php endif; ?>
              </div>
              <div>
                <strong style="font-size: 13px; display: block; color: var(--text-primary);">Header Image <?php echo $i; ?></strong>
                <span class="muted" style="font-size: 11.5px; display: block; margin-top: 2px;">
                  <?php 
                    if ($badge_src) {
                        echo '<span style="color:#10b981; font-weight:600;">● Active</span>';
                    } elseif ($badge_val && !$badge_exists) {
                        echo '<span style="color:#ef4444; font-weight:600;">▲ Missing from Storage</span>';
                    } else {
                        echo '<span style="color:#9ca3af;">○ Not Set</span>';
                    }
                  ?>
                </span>
              </div>
            </div>

            <div class="field" style="margin-bottom: 10px;">
              <label style="font-size: 12px;">Choose Image File <span class="muted">(PNG, JPG, WebP)</span></label>
              <input type="file" name="header_image_<?php echo $i; ?>" accept=".jpg,.jpeg,.png,.webp" style="width: 100%; font-size: 12px;">
            </div>

            <?php if ($badge_val): ?>
              <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #ef4444; cursor: pointer; margin-top: 4px;">
                <input type="checkbox" name="clear_header_image_<?php echo $i; ?>" value="1" <?php echo ($badge_val && !$badge_exists) ? 'checked' : ''; ?>>
                <span><?php echo ($badge_val && !$badge_exists) ? 'Clear missing reference' : 'Remove this image'; ?></span>
              </label>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>

      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('check', '', 14); ?> <span>Save Header Images</span>
      </button>
    </form>
  </div>
</div>

<!-- TAB 2: WEBSITE ABOUT US & CONTACT INFO -->
<div id="websiteTab" class="settings-panel">
  <div class="card" style="max-width: 900px; margin: 0;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(16,185,129,0.12); color: #10b981; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('info', '', 18); ?>
      </div>
      <div>
        <h2 style="font-size: 16px; margin: 0;">Website About Us &amp; Secretariat Information</h2>
        <p class="muted" style="margin: 0; font-size: 13px;">Manage the mission statement, secretariat contact details, and office hours for the public website.</p>
      </div>
    </div>

    <form method="post" style="margin-top: 20px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="update_about">

      <!-- SECTION 0: HOMEPAGE HERO BANNER & TAGLINE -->
      <div style="padding: 16px; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 20px;">
        <h3 style="font-size: 14px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
          <?php echo icon('sparkles', '', 16); ?> <span>Homepage Hero Banner &amp; Chapter Tagline</span>
        </h3>
        <div class="field" style="margin-bottom: 14px;">
          <label style="font-size: 12px;">District &amp; Chapter Badge (Pill Above Title)</label>
          <input type="text" name="hero_badge" value="<?php echo htmlspecialchars($hero_badge_val); ?>" placeholder="e.g. DISTRICT - B4 • QUEZON PROVINCE" required>
        </div>
        <div class="grid-2" style="gap: 14px;">
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Hero Main Title (Theme / Battlecry)</label>
            <input type="text" name="hero_title" value="<?php echo htmlspecialchars($hero_title_val); ?>" placeholder="e.g. “TAGUYOD”" required>
          </div>
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Hero Subtitle / Tagline (Under Accent Lines)</label>
            <input type="text" name="hero_tagline" value="<?php echo htmlspecialchars($hero_tagline_val); ?>" placeholder="e.g. MOVERS OF PROGRESS 2.0" required>
          </div>
        </div>
      </div>

      <!-- SECTION 1: ABOUT US & MISSION -->
      <div style="padding: 16px; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 20px;">
        <h3 style="font-size: 14px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
          <?php echo icon('document', '', 16); ?> <span>Chapter Overview &amp; Mission Statement</span>
        </h3>
        <div class="field" style="margin-bottom: 0;">
          <label style="font-size: 12px;">About Us Paragraph (Displayed on Home &amp; About Pages)</label>
          <textarea name="about_us" rows="5" required style="width: 100%; padding: 12px; font-family: inherit; font-size: 13px; line-height: 1.6;"><?php echo htmlspecialchars($about_us_val); ?></textarea>
        </div>
      </div>

      <!-- SECTION 2: SECRETARIAT CONTACT -->
      <div style="padding: 16px; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 20px;">
        <h3 style="font-size: 14px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
          <?php echo icon('mail', '', 16); ?> <span>Secretariat Contact Information</span>
        </h3>
        <div class="field" style="margin-bottom: 14px;">
          <label style="font-size: 12px;">Secretariat Physical Address / Location</label>
          <input type="text" name="contact_address" value="<?php echo htmlspecialchars($contact_address_val); ?>" placeholder="e.g. Lucena City, Quezon Province, Philippines" required>
        </div>
        <div class="grid-2" style="gap: 14px;">
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Official Secretariat Email Address</label>
            <input type="email" name="contact_email" value="<?php echo htmlspecialchars($contact_email_val); ?>" placeholder="e.g. uap.qpc@gmail.com" required>
          </div>
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Contact Number / Hotline</label>
            <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($contact_phone_val); ?>" placeholder="e.g. +63 (0) XXXX XXXX" required>
          </div>
        </div>
      </div>

      <!-- SECTION 3: OFFICE HOURS -->
      <div style="padding: 16px; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 20px;">
        <h3 style="font-size: 14px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
          <?php echo icon('calendar', '', 16); ?> <span>Office Hours &amp; Secretariat Availability</span>
        </h3>
        <div class="grid-3" style="gap: 14px;">
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Monday – Friday</label>
            <input type="text" name="office_hours_weekdays" value="<?php echo htmlspecialchars($office_hours_weekdays_val); ?>" placeholder="e.g. 9:00 AM – 5:00 PM" required>
          </div>
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Saturday</label>
            <input type="text" name="office_hours_saturday" value="<?php echo htmlspecialchars($office_hours_saturday_val); ?>" placeholder="e.g. 9:00 AM – 12:00 PM" required>
          </div>
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Sunday &amp; Holidays</label>
            <input type="text" name="office_hours_sunday" value="<?php echo htmlspecialchars($office_hours_sunday_val); ?>" placeholder="e.g. Closed" required>
          </div>
        </div>
      </div>

      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('check', '', 14); ?> <span>Save About Us &amp; Contact Settings</span>
      </button>
    </form>

    <!-- SECTION 4: CHAPTER MILESTONES -->
    <div style="margin-top: 28px; padding-top: 22px; border-top: 1px solid var(--border-color);">
      <h3 style="font-size: 14px; margin: 0 0 4px; display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
        <?php echo icon('calendar', '', 16); ?> <span>Chapter Milestones (Timeline)</span>
      </h3>
      <p class="muted" style="font-size: 12px; margin: 0 0 16px;">These appear on the public About page as the Chapter History timeline. Add, edit, or remove milestones here.</p>

      <!-- ADD NEW MILESTONE -->
      <form method="post" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 10px; padding: 14px; margin-bottom: 24px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_milestone">
        <input type="hidden" name="current_tab" value="websiteTab">
        <strong style="display: block; font-size: 13px; margin-bottom: 12px; color: var(--accent-primary);">+ Add New Milestone</strong>
        <div class="grid-2" style="gap: 12px; margin-bottom: 12px;">
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Year <span style="color:var(--accent-primary)">*</span></label>
            <input type="text" name="milestone_year" placeholder="e.g. 2016" maxlength="10" required>
          </div>
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Title <span style="color:var(--accent-primary)">*</span></label>
            <input type="text" name="milestone_title" placeholder="e.g. Chapter Founded" required>
          </div>
        </div>
        <div class="field" style="margin-bottom: 12px;">
          <label style="font-size: 12px;">Content / Description</label>
          <textarea name="milestone_content" rows="3" placeholder="Brief description of this milestone..." style="width: 100%; font-family: inherit; font-size: 13px;"></textarea>
        </div>
        <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('plus', '', 14); ?> <span>Add Milestone</span>
        </button>
      </form>

      <!-- EXISTING MILESTONES — styled like the website timeline cards -->
      <style>
      .ms-preview-card {
        position: relative;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-left: 3px solid var(--accent-primary, #f2b835);
        border-radius: 10px;
        padding: 18px 18px 16px;
        transition: box-shadow 0.2s;
      }
      .ms-preview-card:hover { box-shadow: 0 4px 20px rgba(242,184,53,0.08); }
      .ms-year-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--accent-primary, #f2b835);
        background: rgba(242,184,53,0.1);
        border: 1px solid rgba(242,184,53,0.25);
        padding: 2px 10px;
        border-radius: 20px;
        margin-bottom: 8px;
      }
      .ms-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 6px;
        font-family: var(--font-display, 'Cinzel', serif);
        text-transform: capitalize;
      }
      .ms-dot {
        position: absolute;
        left: -10px;
        top: 20px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--accent-primary, #f2b835);
        box-shadow: 0 0 0 3px rgba(242,184,53,0.2);
        flex-shrink: 0;
      }
      </style>

      <?php if (empty($milestones)): ?>
        <p class="muted" style="font-size: 13px; text-align: center; padding: 20px 0;">No milestones yet. Add one above.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 14px; position: relative; padding-left: 18px; border-left: 1px solid rgba(242,184,53,0.2); margin-left: 6px;">
          <?php foreach ($milestones as $ms): ?>
            <div id="ms-view-<?php echo $ms['id']; ?>">
              <!-- PREVIEW: Matches website timeline card -->
              <div class="ms-preview-card">
                <div class="ms-dot"></div>
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                  <div style="flex: 1;">
                    <span class="ms-year-badge"><?php echo htmlspecialchars($ms['year']); ?></span>
                    <h4 class="ms-title"><?php echo htmlspecialchars($ms['title']); ?></h4>
                    <p class="muted" style="font-size: 13px; margin: 0; line-height: 1.6;"><?php echo htmlspecialchars($ms['content']); ?></p>
                  </div>
                  <div style="display: flex; gap: 6px; flex-shrink: 0; margin-top: 2px;">
                    <button type="button" class="btn btn-sm ms-edit-btn" onclick="editMilestone(<?php echo $ms['id']; ?>)" style="padding: 4px 10px; font-size: 12px;" title="Edit">
                      <?php echo icon('edit', '', 13); ?>
                    </button>
                    <form method="post" style="margin:0;"
                          data-confirm="Delete milestone '<?php echo htmlspecialchars($ms['year'] . ': ' . $ms['title'], ENT_QUOTES, 'UTF-8'); ?>'?"
                          data-confirm-title="Delete Chapter Milestone"
                          data-confirm-btn="Delete Milestone"
                          data-confirm-class="btn-danger"
                          data-confirm-icon="trash">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_milestone">
                      <input type="hidden" name="current_tab" value="websiteTab">
                      <input type="hidden" name="milestone_id" value="<?php echo $ms['id']; ?>">
                      <button type="submit" class="btn btn-sm" style="padding: 4px 10px; font-size: 12px; background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.25); color: #ef4444;" title="Delete">
                        <?php echo icon('trash', '', 13); ?>
                      </button>
                    </form>
                  </div>
                </div>
              </div>

              <!-- INLINE EDIT FORM (hidden by default) -->
              <form method="post" id="ms-form-<?php echo $ms['id']; ?>" style="display: none; background: var(--bg-secondary); border: 1px solid var(--accent-primary, #f2b835); border-radius: 10px; padding: 16px; margin-top: 8px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_milestone">
                <input type="hidden" name="current_tab" value="websiteTab">
                <input type="hidden" name="milestone_id" value="<?php echo $ms['id']; ?>">
                <div class="grid-2" style="gap: 10px; margin-bottom: 10px;">
                  <div class="field" style="margin: 0;">
                    <label style="font-size: 11px;">Year</label>
                    <input type="text" name="milestone_year" value="<?php echo htmlspecialchars($ms['year']); ?>" maxlength="10" required>
                  </div>
                  <div class="field" style="margin: 0;">
                    <label style="font-size: 11px;">Title</label>
                    <input type="text" name="milestone_title" value="<?php echo htmlspecialchars($ms['title']); ?>" required>
                  </div>
                </div>
                <div class="field" style="margin-bottom: 10px;">
                  <label style="font-size: 11px;">Content</label>
                  <textarea name="milestone_content" rows="3" style="width: 100%; font-family: inherit; font-size: 13px;"><?php echo htmlspecialchars($ms['content']); ?></textarea>
                </div>
                <div style="display: flex; gap: 8px;">
                  <button type="submit" class="btn btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <?php echo icon('check', '', 13); ?> <span>Save Changes</span>
                  </button>
                  <button type="button" class="btn btn-sm" onclick="cancelEditMilestone(<?php echo $ms['id']; ?>)" style="background: var(--bg-tertiary, var(--bg-secondary)); border-color: var(--border-color); color: var(--text-secondary);">
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <script>
    function editMilestone(id) {
      var viewEl = document.getElementById('ms-view-' + id);
      viewEl.querySelector('.ms-edit-btn').style.display = 'none';
      viewEl.querySelector('.ms-preview-card').style.opacity = '0.4';
      document.getElementById('ms-form-' + id).style.display = 'block';
    }
    function cancelEditMilestone(id) {
      var viewEl = document.getElementById('ms-view-' + id);
      viewEl.querySelector('.ms-edit-btn').style.display = '';
      viewEl.querySelector('.ms-preview-card').style.opacity = '';
      document.getElementById('ms-form-' + id).style.display = 'none';
    }
    </script>
  </div>
</div>


<!-- TAB: CHAPTER OFFICERS -->
<div id="officersTab" class="settings-panel">
  <!-- FY Configuration & Quick Tools -->
  <div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 16px;">
      <div>
        <div style="display: flex; align-items: center; gap: 10px;">
          <h2 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: #ffffff;">Executive Board &amp; Chapter Officers</h2>
          <span style="font-size: 11px; padding: 3px 8px; border-radius: 999px; background: rgba(229,138,19,0.15); color: var(--quezon-gold, #e58a13); font-weight: 700; border: 1px solid rgba(229,138,19,0.3);">
            FY <?php echo htmlspecialchars($chapter_fiscal_year_val); ?>
          </span>
        </div>
        <p class="muted" style="font-size: 12.5px; margin: 4px 0 0;">
          Manage the active leadership administration and board of directors featured on the About Us page.
        </p>
      </div>

      <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <!-- Fiscal Year Updater Form -->
        <form method="post" style="display: flex; align-items: center; gap: 6px; margin: 0;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="update_officer_settings">
          <input type="hidden" name="current_tab" value="officersTab">
          <label style="font-size: 12px; color: var(--text-secondary); margin: 0; white-space: nowrap;">Active Fiscal Year:</label>
          <input type="text" name="chapter_fiscal_year" value="<?php echo htmlspecialchars($chapter_fiscal_year_val); ?>" placeholder="2026-2027" style="width: 100px; padding: 5px 8px; font-size: 12px; border-radius: 6px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: #fff;">
          <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11.5px; padding: 5px 10px;">Save FY</button>
        </form>

        <button type="button" onclick="openAddOfficerModal()" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #e58a13 0%, #15803d 100%); font-weight: 700; border: none; padding: 6px 14px;">
          <?php echo icon('plus', '', 14); ?> <span>Add Chapter Officer</span>
        </button>
      </div>
    </div>

    <!-- Reorder Instructions and Save Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); border: 1px dashed rgba(245,158,11,0.25); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
      <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-secondary);">
        <?php echo icon('move', '', 14); ?>
        <span>Drag and drop rows to reorder how officers appear on the public About page.</span>
      </div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span id="officersSortSaved" style="display: none; font-size: 12px; color: #22c55e; font-weight: 600; align-items: center; gap: 4px;">
          <?php echo icon('check', '', 13); ?> Order saved!
        </span>
        <form method="post" id="saveOfficersOrderForm" style="margin: 0;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="reorder_officers">
          <input type="hidden" name="current_tab" value="officersTab">
          <input type="hidden" name="order" id="officersOrderInput" value="">
          <button type="submit" id="saveOfficersOrderBtn" class="btn btn-sm btn-secondary" style="font-size: 12px; padding: 5px 12px;">
            <span id="saveOfficersOrderBtnText">Save Officers Formation</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Officers List -->
    <?php if (!empty($chapterOfficers)): ?>
      <div id="officersSortableList" style="display: flex; flex-direction: column; gap: 10px;">
        <?php foreach ($chapterOfficers as $idx => $officer): 
          $officerFullName = trim($officer['title_prefix'] . ' ' . $officer['name'] . ($officer['title_suffix'] ? ', ' . $officer['title_suffix'] : ''));
          $photoSrc = !empty($officer['photo_path']) ? media_url($officer['photo_path']) : (!empty($officer['member_avatar']) ? media_url($officer['member_avatar']) : null);
        ?>
          <div class="officer-sortable-item" data-id="<?php echo $officer['id']; ?>" style="display: flex; align-items: center; justify-content: space-between; gap: 14px; background: var(--card-bg, #18243a); border: 1px solid var(--border-color, rgba(255,255,255,0.12)); border-radius: 10px; padding: 12px 16px; cursor: grab; transition: all 0.2s ease;">
            <!-- Left Info -->
            <div style="display: flex; align-items: center; gap: 14px; min-width: 0; flex: 1;">
              <!-- Drag Handle & Order Badge -->
              <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                <span class="drag-handle" style="opacity: 0.5; color: var(--text-secondary);"><?php echo icon('move', '', 14); ?></span>
                <span class="officer-order-badge" style="width: 24px; height: 24px; border-radius: 6px; background: rgba(229,138,19,0.15); color: var(--quezon-gold, #e58a13); font-weight: 800; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid rgba(229,138,19,0.3);">
                  <?php echo $idx + 1; ?>
                </span>
              </div>

              <!-- Officer Avatar Thumbnail -->
              <div style="width: 44px; height: 44px; border-radius: 50%; overflow: hidden; background: #0b1120; border: 2px solid var(--quezon-gold, #e58a13); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">
                <?php if ($photoSrc): ?>
                  <img src="<?php echo htmlspecialchars($photoSrc); ?>" alt="<?php echo htmlspecialchars($officerFullName); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                  <span style="font-weight: 800; font-size: 14px; color: var(--quezon-gold, #e58a13);">
                    <?php echo strtoupper(substr($officer['name'], 0, 1)); ?>
                  </span>
                <?php endif; ?>
              </div>

              <!-- Officer Details -->
              <div style="min-width: 0; flex: 1;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 2px;">
                  <strong style="color: #ffffff; font-size: 14.5px;"><?php echo htmlspecialchars($officerFullName); ?></strong>
                  
                  <!-- Position Badge -->
                  <span style="font-size: 11px; padding: 2px 8px; border-radius: 4px; background: rgba(21,128,61,0.2); color: #4ade80; border: 1px solid rgba(21,128,61,0.4); font-weight: 700;">
                    <?php echo htmlspecialchars($officer['position']); ?>
                  </span>

                  <!-- Status Badge -->
                  <?php if (!empty($officer['is_active'])): ?>
                    <span style="font-size: 10.5px; padding: 2px 7px; border-radius: 4px; background: rgba(34,197,94,0.12); color: #22c55e; font-weight: 600;">Published</span>
                  <?php else: ?>
                    <span style="font-size: 10.5px; padding: 2px 7px; border-radius: 4px; background: rgba(148,163,184,0.12); color: #94a3b8; font-weight: 600;">Hidden</span>
                  <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; color: var(--text-secondary); flex-wrap: wrap;">
                  <?php if (!empty($officer['committee'])): ?>
                    <span><?php echo icon('award', '', 12); ?> <?php echo htmlspecialchars($officer['committee']); ?></span>
                  <?php endif; ?>

                  <span class="muted">&bull;</span>
                  <span>FY <?php echo htmlspecialchars($officer['fiscal_year']); ?></span>

                  <?php if (!empty($officer['user_id'])): ?>
                    <span class="muted">&bull;</span>
                    <a href="<?php echo BASE_URL; ?>/public/architects.php?q=<?php echo urlencode($officer['name']); ?>" target="_blank" style="color: var(--quezon-gold, #e58a13); text-decoration: none; display: inline-flex; align-items: center; gap: 3px;" title="View directory profile">
                      <?php echo icon('shield_check', '', 12); ?> Linked Member (PRC: <?php echo htmlspecialchars($officer['prc_number'] ?: 'Registered'); ?>)
                    </a>
                  <?php else: ?>
                    <span class="muted">&bull;</span>
                    <span style="color: var(--text-secondary); font-size: 11px;">Manual / Appointed</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 8px; align-items: center; flex-shrink: 0;">
              <button type="button" class="btn btn-sm btn-secondary"
                      onclick="openEditOfficerModal(<?php echo htmlspecialchars(json_encode([
                        'id' => $officer['id'],
                        'user_id' => $officer['user_id'],
                        'name' => $officer['name'],
                        'title_prefix' => $officer['title_prefix'],
                        'title_suffix' => $officer['title_suffix'],
                        'position' => $officer['position'],
                        'committee' => $officer['committee'] ?? '',
                        'fiscal_year' => $officer['fiscal_year'],
                        'photo_path' => $officer['photo_path'] ?? '',
                        'bio' => $officer['bio'] ?? '',
                        'is_active' => (int)$officer['is_active']
                      ]), ENT_QUOTES, 'UTF-8'); ?>)"
                      style="font-size: 12px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo icon('edit', '', 12); ?> <span>Edit</span>
              </button>

              <form method="post" style="margin: 0;"
                    data-confirm="Remove officer '<?php echo htmlspecialchars($officerFullName); ?>' from the leadership list?"
                    data-confirm-title="Remove Officer"
                    data-confirm-btn="Remove"
                    data-confirm-class="btn-danger"
                    data-confirm-icon="trash">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_officer">
                <input type="hidden" name="current_tab" value="officersTab">
                <input type="hidden" name="officer_id" value="<?php echo $officer['id']; ?>">
                <button type="submit" class="btn btn-sm btn-danger" style="font-size: 12px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 4px;">
                  <?php echo icon('trash', '', 12); ?> <span>Delete</span>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 36px 14px; color: var(--text-secondary); background: rgba(0,0,0,0.2); border-radius: 10px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(229,138,19,0.12); color: var(--quezon-gold, #e58a13); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('members', '', 26); ?>
        </div>
        <strong style="display: block; font-size: 15px; color: #ffffff;">No Officers Registered Yet</strong>
        <p class="muted" style="font-size: 12.5px; margin-top: 4px;">
          Click "Add Chapter Officer" above to add your Chapter President, Vice Presidents, and Board of Directors.
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>


<!-- TAB 3: SPONSORS & PARTNERS -->
<div id="sponsorsTab" class="settings-panel">
  <div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(139,92,246,0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('handshake', '', 18); ?>
      </div>
      <h2 style="font-size: 16px; margin: 0;">Add Chapter Sponsor / Partner</h2>
    </div>
    <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Add sponsor logos, links, and promotional products for showcase on the chapter website and directory.</p>

    <form method="post" enctype="multipart/form-data" style="background: var(--bg-secondary); padding: 18px; border-radius: 12px; border: 1px solid var(--border-color);">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_sponsor">
      <input type="hidden" name="current_tab" value="sponsorsTab">
      <div class="grid-2" style="gap: 14px; margin-bottom: 14px;">
        <div class="field" style="margin: 0;">
          <label>Sponsor / Partner Name <span style="color:var(--c-gold)">*</span></label>
          <input type="text" name="sponsor_name" placeholder="e.g. Boysen Paints Philippines, Davies Paint" required>
        </div>
        <div class="field" style="margin: 0;">
          <label>Logo Image <span class="muted" style="font-weight:400;">(upload or paste URL below)</span></label>
          <input type="file" name="sponsor_logo" accept=".jpg,.jpeg,.png,.webp">
        </div>
      </div>
      <div class="field" style="margin-bottom: 14px;">
        <label>Logo URL <span class="muted" style="font-weight:400;">(used if no file uploaded, e.g. https://example.com/logo.png)</span></label>
        <input type="url" name="sponsor_logo_url" placeholder="https://example.com/logo.png">
      </div>
      <div class="grid-2" style="gap: 14px; margin-bottom: 16px;">
        <div class="field" style="margin: 0;">
          <label>Description / Partnership Bio (Optional)</label>
          <input type="text" name="sponsor_desc" placeholder="e.g. Official Platinum Chapter Sponsor">
        </div>
        <div class="field" style="margin: 0;">
          <label>Website URL (Optional)</label>
          <input type="url" name="sponsor_url" placeholder="https://example.com">
        </div>
      </div>

      <!-- SPONSOR TIER & PRODUCTS -->
      <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-color, rgba(255,255,255,0.12)); border-radius: 10px; padding: 14px; margin-bottom: 16px;">
        <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 8px; color: var(--text-primary);">Select Partnership Tier &amp; Showcase Level</label>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
          <label id="add_tier_plat_label" style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 8px; border: 1px solid rgba(245,158,11,0.5); background: rgba(245,158,11,0.09); cursor: pointer;">
            <input type="radio" name="is_platinum" value="1" id="add_tier_platinum" onchange="setSponsorTier('add', true)" checked style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; margin: 3px 0 0 0; accent-color: var(--accent-primary, #f5b800); flex-shrink: 0;">
            <div style="flex: 1; min-width: 0;">
              <strong style="color: var(--accent-primary, #f5b800); font-size: 13.5px; display: block; margin-bottom: 2px;">★ Platinum Partner</strong>
              <span class="muted" style="font-size: 12px; display: block; line-height: 1.4;">3-Minute Website Spotlight • Up to <strong>10 Promotional Products</strong> in Carousel</span>
            </div>
          </label>

          <label id="add_tier_reg_label" style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-color, rgba(255,255,255,0.1)); background: rgba(255,255,255,0.02); cursor: pointer;">
            <input type="radio" name="is_platinum" value="0" id="add_tier_regular" onchange="setSponsorTier('add', false)" style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; margin: 3px 0 0 0; accent-color: var(--accent-primary, #f5b800); flex-shrink: 0;">
            <div style="flex: 1; min-width: 0;">
              <strong style="color: var(--text-primary); font-size: 13.5px; display: block; margin-bottom: 2px;">Featured / Regular Partner</strong>
              <span class="muted" style="font-size: 12px; display: block; line-height: 1.4;">30-Second Website Spotlight • <strong>1 Promotional Product</strong> Showcase</span>
            </div>
          </label>
        </div>

        <!-- PROMOTIONAL PRODUCTS SLOTS -->
        <div id="addSponsorProductsSection" style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed rgba(245,158,11,0.2);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
            <div>
              <strong style="font-size: 13px; color: var(--accent-primary, #f5b800);">Promotional Products &amp; Material Catalogs</strong>
              <span id="addProductsCountBadge" style="font-size: 11px; margin-left: 6px; padding: 2px 7px; border-radius: 999px; background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); font-weight: 700;">(0/10 Products)</span>
              <p class="muted" style="font-size: 11.5px; margin: 2px 0 0;" id="addProductsHelpText">Platinum partners can showcase up to 10 promotional products in the website carousel.</p>
            </div>
            <button type="button" id="btnAddProductSlot" onclick="addProductSlot('addSponsorProductsList', null, 'add')" class="btn btn-sm" style="background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); border: 1px solid rgba(245,158,11,0.3); font-size: 11px; padding: 4px 10px;">
              + Add Product
            </button>
          </div>
          <div id="addSponsorProductsList" style="display: flex; flex-direction: column; gap: 12px;"></div>
        </div>
      </div>

      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('plus', '', 14); ?> <span>Add Sponsor Partner</span>
      </button>
    </form>
  </div>

  <div class="card">
    <?php 
      $platinumCount = 0;
      $standardCount = 0;
      foreach ($sponsors as $s) {
        if (!empty($s['is_platinum'])) $platinumCount++;
        else $standardCount++;
      }
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
      <h3 style="font-size: 15px; margin: 0;">Current Sponsors &amp; Partners (<?php echo count($sponsors); ?>)</h3>
      
      <!-- SPONSOR FILTER TABS -->
      <div style="display: flex; gap: 6px; background: var(--field-bg, rgba(0,0,0,0.2)); padding: 4px; border-radius: 8px; border: 1px solid var(--border-color, rgba(255,255,255,0.08));">
        <button type="button" class="sponsor-filter-btn active" onclick="filterSponsors('all', this)" style="background: var(--accent-primary, #f5b800); color: #000; font-weight: 700; border: none; padding: 4px 12px; border-radius: 6px; font-size: 11.5px; cursor: pointer;">
          All (<?php echo count($sponsors); ?>)
        </button>
        <button type="button" class="sponsor-filter-btn" onclick="filterSponsors('platinum', this)" style="background: transparent; color: var(--text-secondary); font-weight: 600; border: none; padding: 4px 12px; border-radius: 6px; font-size: 11.5px; cursor: pointer;">
          ★ Platinum (<?php echo $platinumCount; ?>)
        </button>
        <button type="button" class="sponsor-filter-btn" onclick="filterSponsors('standard', this)" style="background: transparent; color: var(--text-secondary); font-weight: 600; border: none; padding: 4px 12px; border-radius: 6px; font-size: 11.5px; cursor: pointer;">
          Regular (<?php echo $standardCount; ?>)
        </button>
      </div>
    </div>

    <?php if (count($sponsors) > 0): ?>
      <div id="adminSponsorsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px;">
        <?php foreach ($sponsors as $sponsor): ?>
          <?php 
            $isPlat = !empty($sponsor['is_platinum']);
            $sponsorProducts = [];
            if (!empty($sponsor['products_json'])) {
              $decodedP = json_decode($sponsor['products_json'], true);
              if (is_array($decodedP)) $sponsorProducts = $decodedP;
            }
          ?>
          <div class="admin-sponsor-card" data-tier="<?php echo $isPlat ? 'platinum' : 'standard'; ?>" style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; border: 1px solid <?php echo $isPlat ? 'rgba(245,158,11,0.35)' : 'var(--border-color)'; ?>; display: flex; flex-direction: column; text-align: center; position: relative;">
            
            <?php if ($isPlat): ?>
              <div style="position: absolute; top: 10px; right: 10px;">
                <span style="background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(217,119,6,0.3)); border: 1px solid rgba(245,158,11,0.6); color: #f5b800; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 999px; display: inline-flex; align-items: center; gap: 3px; letter-spacing: 0.5px;">
                  ★ PLATINUM (3 MIN)
                </span>
              </div>
            <?php else: ?>
              <div style="position: absolute; top: 10px; right: 10px;">
                <span style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: var(--text-secondary); font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 999px; display: inline-flex; align-items: center; gap: 3px;">
                  REGULAR (30s)
                </span>
              </div>
            <?php endif; ?>

            <div style="height: 90px; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 4px; padding: 4px; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.15); overflow: hidden;">
              <?php if (!empty($sponsor['logo_path'])): ?>
                <img src="<?php echo htmlspecialchars(media_url($sponsor['logo_path'])); ?>" alt="<?php echo htmlspecialchars($sponsor['name']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 3px;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                <div style="display: none; color: #9ca3af; font-size: 11px; font-weight: 700; flex-direction: column; align-items: center; justify-content: center; gap: 2px;">
                  <span style="color: #ef4444;">⚠️ Image Missing</span>
                  <small style="font-size: 9px; color: #6b7280;">Re-upload in Edit</small>
                </div>
              <?php else: ?>
                <span style="color: #9ca3af; font-size: 14px; font-weight: 700;"><?php echo htmlspecialchars(substr($sponsor['name'], 0, 2)); ?></span>
              <?php endif; ?>
            </div>
            <strong style="font-size: 14px; margin-bottom: 4px; display: block; color: var(--text-primary);"><?php echo htmlspecialchars($sponsor['name']); ?></strong>
            
            <?php if (!empty($sponsor['description'])): ?>
              <span class="muted" style="font-size: 12px; margin-bottom: 8px; display: block;"><?php echo htmlspecialchars($sponsor['description']); ?></span>
            <?php endif; ?>

            <span style="font-size: 11px; color: <?php echo $isPlat ? 'var(--accent-primary, #f5b800)' : 'var(--text-secondary)'; ?>; font-weight: 700; margin-bottom: 8px; display: block;">
              <?php echo count($sponsorProducts); ?>/<?php echo $isPlat ? '10' : '1'; ?> Product<?php echo count($sponsorProducts) === 1 ? '' : 's'; ?> Added
            </span>

            <?php if (!empty($sponsor['url'])): ?>
              <a href="<?php echo htmlspecialchars($sponsor['url']); ?>" target="_blank" style="font-size: 12px; margin-bottom: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                <?php echo icon('external_link', '', 12); ?> <span>Visit Site</span>
              </a>
            <?php endif; ?>

            <div style="margin-top: auto; display: flex; gap: 8px; align-items: center; padding-top: 8px;">
              <button type="button" class="btn btn-sm btn-secondary" 
                      onclick="openEditSponsorModal(<?php echo htmlspecialchars(json_encode([
                        'id' => $sponsor['id'],
                        'name' => $sponsor['name'],
                        'description' => $sponsor['description'] ?? '',
                        'url' => $sponsor['url'] ?? '',
                        'logo_path' => $sponsor['logo_path'] ?? '',
                        'is_platinum' => (int)($sponsor['is_platinum'] ?? 0),
                        'products' => $sponsorProducts
                      ]), ENT_QUOTES, 'UTF-8'); ?>)"
                      style="flex: 1; font-size: 12px; padding: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                <?php echo icon('edit', '', 12); ?> <span>Edit</span>
              </button>
              <form method="post" style="flex: 1; margin: 0;"
                    data-confirm="Delete sponsor '<?php echo htmlspecialchars($sponsor['name']); ?>'?"
                    data-confirm-title="Delete Sponsor"
                    data-confirm-btn="Delete"
                    data-confirm-class="btn-danger"
                    data-confirm-icon="trash">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_sponsor">
                <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                <button type="submit" class="btn btn-sm btn-danger" style="width: 100%; font-size: 12px; padding: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                  <?php echo icon('trash', '', 12); ?> <span>Delete</span>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 32px 10px; color: var(--text-secondary);">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245,158,11,0.1); color: var(--accent-primary, #f59e0b); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('handshake', '', 26); ?>
        </div>
        <strong style="display: block;">No sponsors added yet</strong>
        <p class="muted" style="font-size: 12px; margin-top: 4px;">Add your first sponsor partner above.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- TAB 4: NEWS & ANNOUNCEMENTS -->
<div id="newsTab" class="settings-panel">
  <div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('newspaper', '', 18); ?>
      </div>
      <h2 style="font-size: 16px; margin: 0;">Publish News or Announcement</h2>
    </div>
    <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Create news articles and updates that appear on the website and member portals.</p>

    <form method="post" enctype="multipart/form-data" style="background: var(--bg-secondary); padding: 18px; border-radius: 12px; border: 1px solid var(--border-color);">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_news">
      <div class="grid-2" style="gap: 14px; margin-bottom: 14px;">
        <div class="field" style="margin: 0;">
          <label>News Headline / Title *</label>
          <input type="text" name="news_title" placeholder="e.g. UAP Quezon Province Chapter General Assembly 2026" required>
        </div>
        <div class="field" style="margin: 0;">
          <label>Date of Event / Publication *</label>
          <input type="date" name="news_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
      </div>
      
      <div class="field" style="margin-bottom: 14px;">
        <label>Cover / Announcement Poster Image (Optional)</label>
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
          <div id="addNewsImgPreviewWrap" style="width: 56px; height: 56px; border-radius: 8px; background: rgba(0,0,0,0.25); border: 1px dashed var(--border-color); display: none; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
            <img id="addNewsImgPreview" src="" style="width:100%; height:100%; object-fit:cover;">
          </div>
          <input type="file" name="news_image" accept=".jpg,.jpeg,.png,.webp" onchange="previewNewsUpload(this, 'addNewsImgPreview', 'addNewsImgPreviewWrap')" style="padding: 6px; font-size: 12px; flex: 1; min-width: 200px;">
        </div>
        <span class="muted" style="font-size: 11px; display: block; margin-top: 4px;">PNG, JPG or WebP (up to 12MB). Displayed as cover card on the website and inside the full announcement reader.</span>
      </div>

      <div class="field" style="margin-bottom: 16px;">
        <label>Summary / Content *</label>
        <textarea name="news_summary" rows="4" placeholder="Full announcement narrative, venue details, schedule, agenda, and instructions..." required style="width: 100%; padding: 12px; font-family: inherit; font-size: 14px; line-height: 1.5;"></textarea>
      </div>
      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('plus', '', 14); ?> <span>Publish News</span>
      </button>
    </form>
  </div>

  <div class="card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
      <div>
        <h3 style="font-size: 15px; margin: 0;">Published News &amp; Announcements (<?php echo count($news); ?>)</h3>
        <span id="newsSortHint" style="font-size: 11.5px; color: var(--text-secondary); display: flex; align-items: center; gap: 5px; margin-top: 4px;">
          <?php echo icon('move', '', 13); ?>
          <span>Drag rows to reorder, then click Save News Formation</span>
        </span>
      </div>
      <?php if (count($news) > 1): ?>
        <form method="post" id="newsReorderForm" style="margin: 0; display: flex; align-items: center; gap: 8px;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="reorder_news">
          <input type="hidden" name="order" id="newsOrderInput" value="<?php echo htmlspecialchars(json_encode(array_column($news, 'id'))); ?>">
          
          <button type="submit" id="saveNewsOrderBtn" class="btn btn-sm btn-gold" style="font-size: 12.5px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(245,158,11,0.25);">
            <?php echo icon('check', '', 13); ?>
            <span id="saveNewsOrderBtnText">Save News Formation</span>
          </button>
          <span id="newsSortSaved" style="font-size: 12px; color: #22c55e; display: none; align-items: center; gap: 4px; font-weight: 600;">
            <?php echo icon('check', '', 13); ?> <span>Saved</span>
          </span>
        </form>
      <?php endif; ?>
    </div>

    <?php if (count($news) > 0): ?>
      <div id="newsSortable" style="display: flex; flex-direction: column; gap: 10px;">
        <?php $newsPos = 0; foreach ($news as $item): $newsPos++; ?>
          <div class="news-sortable-item" data-id="<?php echo $item['id']; ?>"
               style="background: var(--bg-secondary); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px; cursor: grab; transition: box-shadow 0.2s, border-color 0.2s;">

            <!-- Drag Handle -->
            <div class="drag-handle" title="Drag to reorder"
                 style="flex-shrink: 0; color: var(--text-secondary); opacity: 0.5; cursor: grab; padding: 2px 0;">
              <svg width="14" height="20" viewBox="0 0 14 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <circle cx="4" cy="4" r="2"/><circle cx="10" cy="4" r="2"/>
                <circle cx="4" cy="10" r="2"/><circle cx="10" cy="10" r="2"/>
                <circle cx="4" cy="16" r="2"/><circle cx="10" cy="16" r="2"/>
              </svg>
            </div>

            <!-- Order Badge -->
            <div class="news-order-badge" style="flex-shrink: 0; width: 26px; height: 26px; border-radius: 6px; background: rgba(245,158,11,0.12); color: var(--accent-primary, #f59e0b); font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center;">
              <?php echo $newsPos; ?>
            </div>

            <!-- Image Thumbnail -->
            <?php if (!empty($item['image_path'])): ?>
              <div style="width: 48px; height: 48px; border-radius: 8px; overflow: hidden; background: #000; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.08);">
                <img src="<?php echo htmlspecialchars(BASE_URL . '/' . ltrim($item['image_path'], '/')); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
              </div>
            <?php else: ?>
              <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(245,158,11,0.06); border: 1px dashed rgba(245,158,11,0.2); display: flex; align-items: center; justify-content: center; color: var(--accent-primary, #f59e0b); flex-shrink: 0; opacity: 0.7;">
                <?php echo icon('newspaper', '', 18); ?>
              </div>
            <?php endif; ?>

            <!-- Content -->
            <div style="flex: 1; min-width: 0;">
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                <strong style="font-size: 14.5px; color: var(--text-primary);"><?php echo htmlspecialchars($item['title']); ?></strong>
                <?php 
                  $itemDate = !empty($item['date_posted']) ? $item['date_posted'] : (!empty($item['date_display']) ? date('Y-m-d', strtotime($item['date_display'])) : date('Y-m-d', strtotime($item['created_at'])));
                ?>
                <span class="badge" style="font-size: 11px; flex-shrink: 0;"><?php echo date('M d, Y', strtotime($itemDate)); ?></span>
              </div>
              <p class="muted" style="margin: 0; font-size: 12.5px; line-height: 1.5; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 480px;">
                <?php echo htmlspecialchars($item['summary']); ?>
              </p>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 7px; align-items: center; flex-shrink: 0;">
              <button type="button" class="btn btn-sm btn-secondary"
                      onclick="openEditNewsModal(<?php echo htmlspecialchars(json_encode([
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'summary' => $item['summary'],
                        'image_path' => $item['image_path'] ?? null,
                        'date_posted' => $itemDate
                      ]), ENT_QUOTES, 'UTF-8'); ?>)"
                      style="font-size: 12px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo icon('edit', '', 12); ?> <span>Edit</span>
              </button>
              <form method="post" style="margin: 0;"
                    data-confirm="Delete news announcement '<?php echo htmlspecialchars($item['title']); ?>'?"
                    data-confirm-title="Delete News"
                    data-confirm-btn="Delete"
                    data-confirm-class="btn-danger"
                    data-confirm-icon="trash">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_news">
                <input type="hidden" name="news_id" value="<?php echo $item['id']; ?>">
                <button type="submit" class="btn btn-sm btn-danger" style="font-size: 12px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 4px;">
                  <?php echo icon('trash', '', 12); ?> <span>Delete</span>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 32px 10px; color: var(--text-secondary);">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245,158,11,0.1); color: var(--accent-primary, #f59e0b); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('newspaper', '', 26); ?>
        </div>
        <strong style="display: block;">No news published yet</strong>
        <p class="muted" style="font-size: 12px; margin-top: 4px;">Publish your first chapter announcement above.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ========================================== -->
<!-- SOCIAL ALERTS & TELEGRAM NOTIFICATIONS TAB -->
<!-- ========================================== -->
<div id="notificationsTab" class="settings-panel">
  <!-- Status & Quick Test Top Banner -->
  <div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9)); border: 1px solid var(--border-color); border-radius: 14px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
      <div style="display: flex; align-items: center; gap: 14px;">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: #229ED9; color: #ffffff; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(34, 158, 217, 0.35);">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
        </div>
        <div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <h2 style="font-size: 18px; margin: 0; font-weight: 700;">Instant Telegram &amp; Social Alerts</h2>
            <?php if ($telegram_enabled_val && !empty($telegram_bot_token_val) && !empty($telegram_chat_id_val)): ?>
              <span class="badge-pill" style="background: rgba(16, 185, 129, 0.18); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 11px; padding: 2px 10px; border-radius: 20px; font-weight: 600;">● Active &amp; Connected</span>
            <?php elseif ($telegram_enabled_val): ?>
              <span class="badge-pill" style="background: rgba(245, 158, 11, 0.18); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 11px; padding: 2px 10px; border-radius: 20px; font-weight: 600;">● Missing Bot Token / Chat ID</span>
            <?php else: ?>
              <span class="badge-pill" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.25); font-size: 11px; padding: 2px 10px; border-radius: 20px; font-weight: 600;">○ Inactive (Disabled)</span>
            <?php endif; ?>
          </div>
          <p class="muted" style="margin: 4px 0 0; font-size: 13px;">Receive real-time push notifications on your phone, tablet, desktop, or Chapter Admin Telegram group for all vital events.</p>
        </div>
      </div>

      <!-- Quick Test Button Form -->
      <form method="post" style="margin: 0;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="test_telegram">
        <input type="hidden" name="current_tab" value="notificationsTab">
        <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; padding: 8px 16px; background: rgba(34, 158, 217, 0.15); border: 1px solid rgba(34, 158, 217, 0.4); color: #38bdf8;" <?php echo (empty($telegram_bot_token_val) || empty($telegram_chat_id_val)) ? 'title="Configure Token & Chat ID first"' : ''; ?>>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
          <span>Send Test Alert Now</span>
        </button>
      </form>
    </div>
  </div>

  <div class="grid-2" style="gap: 24px;">
    <!-- LEFT COLUMN: SETTINGS FORM -->
    <div class="card" style="margin: 0;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(242,184,53,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
          <?php echo icon('bell', '', 18); ?>
        </div>
        <div>
          <h2 style="font-size: 16px; margin: 0; font-weight: 700;">Bot &amp; Channel Credentials</h2>
          <span class="muted" style="font-size: 12px;">Configure bot authentication and target recipient chat</span>
        </div>
      </div>

      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_telegram_settings">
        <input type="hidden" name="current_tab" value="notificationsTab">

        <!-- Master Switch -->
        <div style="padding: 14px 16px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between;">
          <div>
            <strong style="display: block; font-size: 14px;">Enable Social &amp; Telegram Push Alerts</strong>
            <span class="muted" style="font-size: 12px;">When enabled, verified system events will immediately dispatch push notifications.</span>
          </div>
          <label style="position: relative; display: inline-block; width: 44px; height: 24px; margin: 0; cursor: pointer;">
            <input type="checkbox" name="telegram_notifications_enabled" value="1" <?php echo $telegram_enabled_val ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;" onchange="this.nextElementSibling.style.background = this.checked ? 'var(--button-primary, #f59e0b)' : '#475569'; this.nextElementSibling.querySelector('span').style.transform = this.checked ? 'translateX(20px)' : 'translateX(0)';">
            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: <?php echo $telegram_enabled_val ? 'var(--button-primary, #f59e0b)' : '#475569'; ?>; transition: .3s; border-radius: 24px; display: block;">
              <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; display: block; transform: <?php echo $telegram_enabled_val ? 'translateX(20px)' : 'translateX(0)'; ?>;"></span>
            </span>
          </label>
        </div>

        <!-- Bot Token -->
        <div class="field" style="margin-bottom: 16px;">
          <label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">
            Telegram Bot Token
            <span style="color: #ef4444;">*</span>
          </label>
          <div style="position: relative;">
            <input type="password" id="telegram_bot_token" name="telegram_bot_token" value="<?php echo htmlspecialchars($telegram_bot_token_val); ?>" placeholder="e.g. 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" style="width: 100%; padding: 10px 40px 10px 12px; font-family: monospace; font-size: 13px;">
            <button type="button" onclick="const f = document.getElementById('telegram_bot_token'); f.type = f.type === 'password' ? 'text' : 'password';" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px;">
              👁️
            </button>
          </div>
          <span class="muted" style="font-size: 12px; display: block; margin-top: 4px;">Issued by <b>@BotFather</b> when you create your telegram bot.</span>
        </div>

        <!-- Chat ID -->
        <div class="field" style="margin-bottom: 20px;">
          <label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">
            Target Chat ID / Channel ID
            <span style="color: #ef4444;">*</span>
          </label>
          <input type="text" name="telegram_chat_id" value="<?php echo htmlspecialchars($telegram_chat_id_val); ?>" placeholder="e.g. 987654321 or -1001234567890 (for Groups)" style="width: 100%; padding: 10px 12px; font-family: monospace; font-size: 13px;">
          <span class="muted" style="font-size: 12px; display: block; margin-top: 4px;">Your private user chat ID, or a group ID (starts with <code>-</code> or <code>-100</code>).</span>
        </div>

        <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color);">
          <h3 style="font-size: 14px; margin: 0 0 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary);">
            Notification Trigger Filters
          </h3>

          <div style="display: flex; flex-direction: column; gap: 12px;">
            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13px;">
              <input type="checkbox" name="telegram_notify_inquiries" value="1" <?php echo $telegram_notify_inquiries_val ? 'checked' : ''; ?> style="margin-top: 3px;">
              <div>
                <strong>Public Website Inquiries</strong>
                <span class="muted" style="display: block; font-size: 12px;">Alert when a visitor submits the "Contact Secretariat" form on the public site.</span>
              </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13px;">
              <input type="checkbox" name="telegram_notify_registrations" value="1" <?php echo $telegram_notify_registrations_val ? 'checked' : ''; ?> style="margin-top: 3px;">
              <div>
                <strong>New Member Signups / Registrations</strong>
                <span class="muted" style="display: block; font-size: 12px;">Alert when an architect signs up and is awaiting membership approval.</span>
              </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13px;">
              <input type="checkbox" name="telegram_notify_payments" value="1" <?php echo $telegram_notify_payments_val ? 'checked' : ''; ?> style="margin-top: 3px;">
              <div>
                <strong>Payment Proof Submissions</strong>
                <span class="muted" style="display: block; font-size: 12px;">Alert when a member uploads bank/GCash slip for chapter dues verification.</span>
              </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13px;">
              <input type="checkbox" name="telegram_notify_directory" value="1" <?php echo $telegram_notify_directory_val ? 'checked' : ''; ?> style="margin-top: 3px;">
              <div>
                <strong>Directory Listing Applications</strong>
                <span class="muted" style="display: block; font-size: 12px;">Alert when a member applies to unlock their public architect practice profile.</span>
              </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-size: 13px;">
              <input type="checkbox" name="telegram_notify_attendance" value="1" <?php echo $telegram_notify_attendance_val ? 'checked' : ''; ?> style="margin-top: 3px;">
              <div>
                <strong>Real-Time Attendance Check-Ins</strong>
                <span class="muted" style="display: block; font-size: 12px;">Alert on every attendee check-in during GMMs and chapter events (Optional).</span>
              </div>
            </label>
          </div>
        </div>

        <div style="margin-top: 24px;">
          <button type="submit" class="btn" style="display: inline-flex; align-items: center; gap: 8px;">
            <?php echo icon('check', '', 16); ?> <span>Save Notification Settings</span>
          </button>
        </div>
      </form>
    </div>

    <!-- RIGHT COLUMN: SETUP GUIDE & CRON AUTOMATION -->
    <div>
      <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
          <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59,130,246,0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
            <?php echo icon('info', '', 18); ?>
          </div>
          <h2 style="font-size: 16px; margin: 0; font-weight: 700;">How to Setup in 2 Minutes</h2>
        </div>

        <div style="font-size: 13px; line-height: 1.6; color: var(--text-secondary);">
          <div style="margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
            <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 3px;">Step 1: Create Your Bot</div>
            Open Telegram, search for <a href="https://t.me/BotFather" target="_blank" style="color: #38bdf8; text-decoration: underline;">@BotFather</a>, and send <code>/newbot</code>. Follow the prompts to name it (e.g. <i>UAP Quezon Alerts</i>) and copy the API token provided.
          </div>

          <div style="margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
            <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 3px;">Step 2: Get Your Chat ID</div>
            <b>Option A (Private Alerts):</b> Search for <a href="https://t.me/userinfobot" target="_blank" style="color: #38bdf8; text-decoration: underline;">@userinfobot</a> on Telegram and click <i>Start</i> to see your numerical Id (e.g. <code>123456789</code>).<br>
            <b>Option B (Admin Group Alerts):</b> Create a Telegram group for Chapter Officers, add your bot as an admin, and add <a href="https://t.me/RawDataBot" target="_blank" style="color: #38bdf8; text-decoration: underline;">@RawDataBot</a> to get the group's Chat ID (e.g. <code>-1002345678901</code>).
          </div>

          <div style="margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
            <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 3px;">Step 3: Start the Bot</div>
            Open your bot's chat and press <b>Start</b> (Telegram requires you to click start once so the bot has permission to message you).
          </div>

          <div>
            <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 3px;">Step 4: Save &amp; Test</div>
            Paste the Token and Chat ID on the left, enable the switch, click <b>Save</b>, and then click <b>Send Test Alert Now</b> above!
          </div>
        </div>
      </div>

      <!-- Standalone Script / Cron Automation Guide -->
      <div class="card" style="margin: 0;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
          <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(16,185,129,0.12); color: #10b981; display: flex; align-items: center; justify-content: center;">
            <?php echo icon('clock', '', 18); ?>
          </div>
          <h2 style="font-size: 16px; margin: 0; font-weight: 700;">Scheduled Cron / CLI Commands</h2>
        </div>

        <p class="muted" style="font-size: 12px; margin-bottom: 12px;">You can run periodic digests or dispatch broadcasts via terminal command line or scheduled cron jobs:</p>

        <div style="display: flex; flex-direction: column; gap: 10px;">
          <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
            <span class="muted" style="font-size: 11px; display: block; font-weight: 600;">Daily Pending Summary Digest:</span>
            <code style="font-size: 12px; color: #f59e0b; word-break: break-all;">php scripts/social_notify.php --summary</code>
          </div>

          <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
            <span class="muted" style="font-size: 11px; display: block; font-weight: 600;">Check Configuration Status:</span>
            <code style="font-size: 12px; color: #f59e0b; word-break: break-all;">php scripts/social_notify.php --status</code>
          </div>

          <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px;">
            <span class="muted" style="font-size: 11px; display: block; font-weight: 600;">Send Custom Broadcast Alert:</span>
            <code style="font-size: 12px; color: #f59e0b; word-break: break-all;">php scripts/social_notify.php --send "Reminder: Chapter Meeting at 7 PM"</code>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- /notificationsTab -->

<!-- EDIT NEWS MODAL -->
<div id="editNewsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #18243a); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:14px; max-width:580px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); color:var(--text-primary);">
    <div style="padding:22px;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(245,158,11,0.15); color:#f59e0b;">
            <?php echo icon('edit', '', 18); ?>
          </div>
          <h3 style="margin:0; font-size:17px; font-weight:700;">Edit News Announcement</h3>
        </div>
        <button type="button" onclick="closeEditNewsModal()" style="background:transparent; border:none; color:var(--text-secondary); cursor:pointer; font-size:20px; padding:4px;">&times;</button>
      </div>

      <form method="post" enctype="multipart/form-data" id="editNewsForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="edit_news">
        <input type="hidden" name="news_id" id="edit_news_id" value="">

        <div class="field" style="margin-bottom:12px;">
          <label>News Headline / Title *</label>
          <input type="text" name="news_title" id="edit_news_title" required style="width:100%;">
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label>Date of Event / Publication *</label>
          <input type="date" name="news_date" id="edit_news_date" required style="width:100%;">
        </div>

        <!-- Cover Image Edit & Preview -->
        <div class="field" style="margin-bottom:14px;">
          <label>Cover / Announcement Image</label>
          <div id="editNewsCurrentImgWrap" style="display:none; align-items:center; gap:12px; margin-bottom:8px; padding:8px 12px; background:rgba(0,0,0,0.25); border-radius:8px; border:1px solid rgba(255,255,255,0.06);">
            <img id="editNewsCurrentImg" src="" style="width:50px; height:50px; object-fit:cover; border-radius:6px;">
            <div style="flex:1;">
              <span style="font-size:12px; font-weight:600; display:block; color:var(--text-primary);">Current Attached Image</span>
              <label style="font-size:11.5px; color:#ef4444; cursor:pointer; display:inline-flex; align-items:center; gap:4px; margin-top:2px;">
                <input type="checkbox" name="remove_news_image" value="1" id="edit_remove_news_image">
                <span>Remove attached image</span>
              </label>
            </div>
          </div>
          <input type="file" name="news_image" accept=".jpg,.jpeg,.png,.webp" style="padding:6px; width:100%; font-size:12px;">
          <span class="muted" style="font-size:11px; display:block; margin-top:3px;">Select a new image file to replace the current one.</span>
        </div>

        <div class="field" style="margin-bottom:18px;">
          <label>Summary / Content *</label>
          <textarea name="news_summary" id="edit_news_summary" rows="5" required style="width:100%; padding:10px; font-family:inherit; font-size:13.5px; line-height:1.5;"></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" onclick="closeEditNewsModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success" style="padding:8px 20px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
            <?php echo icon('check', '', 14); ?> <span>Save Changes</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ADD OFFICER MODAL -->
<div id="addOfficerModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #18243a); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:14px; max-width:600px; width:100%; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); color:var(--text-primary);">
    <div style="padding:22px;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(229,138,19,0.15); color:var(--quezon-gold, #e58a13);">
            <?php echo icon('members', '', 18); ?>
          </div>
          <div>
            <h3 style="margin:0; font-size:17px; font-weight:700; color:#fff;">Add Chapter Officer</h3>
            <span class="muted" style="font-size:12px;">Appoint an executive officer or committee director.</span>
          </div>
        </div>
        <button type="button" onclick="closeAddOfficerModal()" style="background:transparent; border:none; color:var(--text-secondary); cursor:pointer; font-size:22px; padding:4px;">&times;</button>
      </div>

      <form method="post" enctype="multipart/form-data" id="addOfficerForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_officer">
        <input type="hidden" name="current_tab" value="officersTab">

        <!-- Member Link Option -->
        <div class="field" style="margin-bottom:14px;">
          <label style="font-size:12.5px; font-weight:600; color:var(--quezon-gold, #e58a13);">Link with Registered Member (Optional)</label>
          <select name="user_id" id="add_officer_user_id" onchange="syncOfficerMember('add')" style="width:100%; padding:8px 10px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); border-radius:6px; color:#fff;">
            <option value="">-- Manual Non-Member / Custom Entry --</option>
            <?php foreach ($eligibleMembers as $em): ?>
              <option value="<?php echo $em['id']; ?>" data-name="<?php echo htmlspecialchars($em['name']); ?>" data-prc="<?php echo htmlspecialchars($em['prc_number'] ?? ''); ?>">
                <?php echo htmlspecialchars($em['name']); ?> (PRC: <?php echo htmlspecialchars($em['prc_number'] ?: 'N/A'); ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <span class="muted" style="font-size:11px; display:block; margin-top:3px;">
            Selecting an approved chapter architect links this card directly to their directory profile.
          </span>
        </div>

        <div class="grid-2" style="gap:12px; margin-bottom:12px;">
          <div class="field" style="margin-bottom:0;">
            <label>Prefix</label>
            <input type="text" name="title_prefix" id="add_officer_prefix" value="Ar." style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          </div>
          <div class="field" style="margin-bottom:0;">
            <label>Suffix</label>
            <input type="text" name="title_suffix" id="add_officer_suffix" value="uap" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          </div>
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label>Officer Full Name *</label>
          <input type="text" name="name" id="add_officer_name" required placeholder="e.g. Juan Dela Cruz" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label>Executive Position / Designation *</label>
          <input type="text" name="position" id="add_officer_position" list="officerPositionsList" required placeholder="e.g. Chapter President, Vice President on Operations" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          <datalist id="officerPositionsList">
            <option value="Chapter President">
            <option value="Vice President on Programs & Development">
            <option value="Vice President on Operations">
            <option value="Chapter Secretary">
            <option value="Chapter Treasurer">
            <option value="Chapter Auditor">
            <option value="Immediate Past President">
            <option value="Director for Professional Practice">
            <option value="Director for CPD & Education">
            <option value="Director for Heritage & Cultural Architecture">
            <option value="Director for Membership & Fellowship">
            <option value="Director for Public Relations & Media">
          </datalist>
        </div>

        <div class="grid-2" style="gap:12px; margin-bottom:12px;">
          <div class="field" style="margin-bottom:0;">
            <label>Committee / Portfolio (Optional)</label>
            <input type="text" name="committee" id="add_officer_committee" placeholder="e.g. Operations & Heritage" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          </div>
          <div class="field" style="margin-bottom:0;">
            <label>Fiscal Year</label>
            <input type="text" name="fiscal_year" id="add_officer_fy" value="<?php echo htmlspecialchars($chapter_fiscal_year_val); ?>" required style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          </div>
        </div>

        <div class="field" style="margin-bottom:14px;">
          <label>Official Portrait Photo (Optional)</label>
          <input type="file" name="officer_photo" accept="image/png, image/jpeg, image/webp" style="padding:6px; width:100%; font-size:12px;">
          <span class="muted" style="font-size:11px; display:block; margin-top:3px;">
            If left blank and a chapter member is selected above, their member avatar is used automatically.
          </span>
        </div>

        <div class="field" style="margin-bottom:18px;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" checked style="width:16px; height:16px; accent-color:var(--quezon-gold, #e58a13);">
            <span style="font-weight:600; color:#fff;">Published (Visible on About Us page)</span>
          </label>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" onclick="closeAddOfficerModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success" style="padding:8px 20px; font-weight:700; display:inline-flex; align-items:center; gap:6px; background:var(--quezon-gold, #e58a13); border:none; color:#111827;">
            <?php echo icon('check', '', 14); ?> <span>Save Officer</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT OFFICER MODAL -->
<div id="editOfficerModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #18243a); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:14px; max-width:600px; width:100%; max-height:92vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); color:var(--text-primary);">
    <div style="padding:22px;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(229,138,19,0.15); color:var(--quezon-gold, #e58a13);">
            <?php echo icon('edit', '', 18); ?>
          </div>
          <div>
            <h3 style="margin:0; font-size:17px; font-weight:700; color:#fff;">Edit Chapter Officer</h3>
            <span class="muted" style="font-size:12px;">Update officer credentials, position, or status.</span>
          </div>
        </div>
        <button type="button" onclick="closeEditOfficerModal()" style="background:transparent; border:none; color:var(--text-secondary); cursor:pointer; font-size:22px; padding:4px;">&times;</button>
      </div>

      <form method="post" enctype="multipart/form-data" id="editOfficerForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="edit_officer">
        <input type="hidden" name="current_tab" value="officersTab">
        <input type="hidden" name="officer_id" id="edit_officer_id" value="">

        <!-- Member Link Option -->
        <div class="field" style="margin-bottom:14px;">
          <label style="font-size:12.5px; font-weight:600; color:var(--quezon-gold, #e58a13);">Link with Registered Member (Optional)</label>
          <select name="user_id" id="edit_officer_user_id" onchange="syncOfficerMember('edit')" style="width:100%; padding:8px 10px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); border-radius:6px; color:#fff;">
            <option value="">-- Manual Non-Member / Custom Entry --</option>
            <?php foreach ($eligibleMembers as $em): ?>
              <option value="<?php echo $em['id']; ?>" data-name="<?php echo htmlspecialchars($em['name']); ?>" data-prc="<?php echo htmlspecialchars($em['prc_number'] ?? ''); ?>">
                <?php echo htmlspecialchars($em['name']); ?> (PRC: <?php echo htmlspecialchars($em['prc_number'] ?: 'N/A'); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="grid-2" style="gap:12px; margin-bottom:12px;">
          <div class="field" style="margin-bottom:0;">
            <label>Prefix</label>
            <input type="text" name="title_prefix" id="edit_officer_prefix" value="Ar." style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          </div>
          <div class="field" style="margin-bottom:0;">
            <label>Suffix</label>
            <input type="text" name="title_suffix" id="edit_officer_suffix" value="uap" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          </div>
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label>Officer Full Name *</label>
          <input type="text" name="name" id="edit_officer_name" required placeholder="e.g. Juan Dela Cruz" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label>Executive Position / Designation *</label>
          <input type="text" name="position" id="edit_officer_position" list="officerPositionsList" required placeholder="e.g. Chapter President" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
        </div>

        <div class="grid-2" style="gap:12px; margin-bottom:12px;">
          <div class="field" style="margin-bottom:0;">
            <label>Committee / Portfolio</label>
            <input type="text" name="committee" id="edit_officer_committee" placeholder="e.g. Operations & Heritage" style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          </div>
          <div class="field" style="margin-bottom:0;">
            <label>Fiscal Year</label>
            <input type="text" name="fiscal_year" id="edit_officer_fy" required style="width:100%; padding:8px; border-radius:6px; background:rgba(0,0,0,0.3); border:1px solid var(--border-color); color:#fff;">
          </div>
        </div>

        <!-- Current photo and upload -->
        <div class="field" style="margin-bottom:14px;">
          <label>Officer Portrait Photo</label>
          <div id="editOfficerPhotoWrap" style="display:none; align-items:center; gap:12px; margin-bottom:8px; padding:8px 12px; background:rgba(0,0,0,0.25); border-radius:8px; border:1px solid rgba(255,255,255,0.06);">
            <img id="editOfficerCurrentPhoto" src="" style="width:44px; height:44px; border-radius:50%; object-fit:cover; border:1px solid var(--quezon-gold, #e58a13);">
            <div style="flex:1;">
              <span style="font-size:12px; font-weight:600; display:block; color:var(--text-primary);">Current Attached Photo</span>
              <label style="font-size:11.5px; color:#ef4444; cursor:pointer; display:inline-flex; align-items:center; gap:4px; margin-top:2px;">
                <input type="checkbox" name="remove_officer_photo" value="1" id="edit_remove_officer_photo">
                <span>Remove attached photo</span>
              </label>
            </div>
          </div>
          <input type="file" name="officer_photo" accept="image/png, image/jpeg, image/webp" style="padding:6px; width:100%; font-size:12px;">
          <span class="muted" style="font-size:11px; display:block; margin-top:3px;">Upload new photo to replace current.</span>
        </div>

        <div class="field" style="margin-bottom:18px;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" id="edit_officer_active" style="width:16px; height:16px; accent-color:var(--quezon-gold, #e58a13);">
            <span style="font-weight:600; color:#fff;">Published (Visible on About Us page)</span>
          </label>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" onclick="closeEditOfficerModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success" style="padding:8px 20px; font-weight:700; display:inline-flex; align-items:center; gap:6px; background:var(--quezon-gold, #e58a13); border:none; color:#111827;">
            <?php echo icon('check', '', 14); ?> <span>Save Changes</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- EDIT SPONSOR MODAL -->
<div id="editSponsorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #18243a); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:14px; max-width:640px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); color:var(--text-primary);">
    <div style="padding:22px;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(245,158,11,0.15); color:#f59e0b;">
            <?php echo icon('edit', '', 18); ?>
          </div>
          <h3 style="margin:0; font-size:17px; font-weight:700;">Edit Sponsor Partner</h3>
        </div>
        <button type="button" onclick="closeEditSponsorModal()" style="background:transparent; border:none; color:var(--text-secondary); cursor:pointer; font-size:20px; padding:4px;">&times;</button>
      </div>

      <form method="post" enctype="multipart/form-data" id="editSponsorForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="edit_sponsor">
        <input type="hidden" name="sponsor_id" id="edit_sponsor_id" value="">

        <div class="grid-2" style="gap: 12px; margin-bottom:12px;">
          <div class="field" style="margin-bottom:0;">
            <label>Sponsor / Partner Name</label>
            <input type="text" name="sponsor_name" id="edit_sponsor_name" required style="width:100%;">
          </div>
          <div class="field" style="margin-bottom:0;">
            <label>Website URL (optional)</label>
            <input type="url" name="sponsor_url" id="edit_sponsor_url" placeholder="https://example.com" style="width:100%;">
          </div>
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label>Description / Partnership Role (optional)</label>
          <textarea name="sponsor_desc" id="edit_sponsor_desc" rows="2" style="width:100%; padding:8px; font-family:inherit; font-size:13px;"></textarea>
        </div>

        <div class="field" style="margin-bottom:14px;">
          <label>Replace Logo (leave empty to keep current)</label>
          <input type="file" name="sponsor_logo" accept="image/png, image/jpeg, image/webp" style="padding:6px; width:100%;">
        </div>

        <div style="background: rgba(0,0,0,0.25); border: 1px solid var(--border-color, rgba(255,255,255,0.12)); border-radius: 10px; padding: 14px; margin-bottom: 16px;">
          <label style="font-weight: 700; font-size: 13px; display: block; margin-bottom: 8px; color: var(--text-primary);">Partnership Tier &amp; Showcase Level</label>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
            <label id="edit_tier_plat_label" style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 8px; border: 1px solid rgba(245,158,11,0.5); background: rgba(245,158,11,0.09); cursor: pointer;">
              <input type="radio" name="is_platinum" value="1" id="edit_tier_platinum" onchange="setSponsorTier('edit', true)" style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; margin: 3px 0 0 0; accent-color: var(--accent-primary, #f5b800); flex-shrink: 0;">
              <div style="flex: 1; min-width: 0;">
                <strong style="color: var(--accent-primary, #f5b800); font-size: 13.5px; display: block; margin-bottom: 2px;">★ Platinum Partner</strong>
                <span class="muted" style="font-size: 12px; display: block; line-height: 1.4;">3-Minute Website Spotlight • Up to <strong>10 Promotional Products</strong></span>
              </div>
            </label>

            <label id="edit_tier_reg_label" style="display: flex; align-items: flex-start; gap: 12px; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-color, rgba(255,255,255,0.1)); background: rgba(255,255,255,0.02); cursor: pointer;">
              <input type="radio" name="is_platinum" value="0" id="edit_tier_regular" onchange="setSponsorTier('edit', false)" style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; margin: 3px 0 0 0; accent-color: var(--accent-primary, #f5b800); flex-shrink: 0;">
              <div style="flex: 1; min-width: 0;">
                <strong style="color: var(--text-primary); font-size: 13.5px; display: block; margin-bottom: 2px;">Featured / Regular Partner</strong>
                <span class="muted" style="font-size: 12px; display: block; line-height: 1.4;">30-Second Website Spotlight • <strong>1 Promotional Product</strong> Showcase</span>
              </div>
            </label>
          </div>

          <!-- PROMOTIONAL PRODUCTS SLOTS -->
          <div id="editSponsorProductsSection" style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed rgba(245,158,11,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
              <div>
                <strong style="font-size: 13px; color: var(--accent-primary, #f5b800);">Promotional Products &amp; Material Catalogs</strong>
                <span id="editProductsCountBadge" style="font-size: 11px; margin-left: 6px; padding: 2px 7px; border-radius: 999px; background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); font-weight: 700;">(0/10 Products)</span>
                <p class="muted" style="font-size: 11.5px; margin: 2px 0 0;" id="editProductsHelpText">Manage promotional product showcases for this partner.</p>
              </div>
              <button type="button" id="btnEditAddProductSlot" onclick="addProductSlot('editSponsorProductsList', null, 'edit')" class="btn btn-sm" style="background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); border: 1px solid rgba(245,158,11,0.3); font-size: 11px; padding: 4px 10px;">
                + Add Product
              </button>
            </div>
            <div id="editSponsorProductsList" style="display: flex; flex-direction: column; gap: 12px;"></div>
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" onclick="closeEditSponsorModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success" style="padding:8px 20px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
            <?php echo icon('check', '', 14); ?> <span>Save Changes</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Sponsor Filter Tabs Logic
  function filterSponsors(tier, btn) {
    var buttons = document.querySelectorAll('.sponsor-filter-btn');
    buttons.forEach(function(b) {
      b.style.background = 'transparent';
      b.style.color = 'var(--text-secondary)';
      b.classList.remove('active');
    });
    btn.style.background = 'var(--accent-primary, #f5b800)';
    btn.style.color = '#000';
    btn.classList.add('active');

    var cards = document.querySelectorAll('.admin-sponsor-card');
    cards.forEach(function(card) {
      if (tier === 'all') {
        card.style.display = 'flex';
      } else {
        var cardTier = card.getAttribute('data-tier');
        card.style.display = (cardTier === tier) ? 'flex' : 'none';
      }
    });
  }

  function setSponsorTier(prefix, isPlatinum) {
    var platLabel = document.getElementById(prefix + '_tier_plat_label');
    var regLabel = document.getElementById(prefix + '_tier_reg_label');
    var helpText = document.getElementById(prefix + 'ProductsHelpText');
    var containerId = prefix === 'add' ? 'addSponsorProductsList' : 'editSponsorProductsList';
    var container = document.getElementById(containerId);

    if (isPlatinum) {
      if (platLabel) {
        platLabel.style.border = '1px solid rgba(245,158,11,0.5)';
        platLabel.style.background = 'rgba(245,158,11,0.09)';
      }
      if (regLabel) {
        regLabel.style.border = '1px solid var(--border-color, rgba(255,255,255,0.1))';
        regLabel.style.background = 'rgba(255,255,255,0.02)';
      }
      if (helpText) {
        helpText.textContent = 'Platinum partners can showcase up to 10 promotional products in the website carousel.';
      }
    } else {
      if (regLabel) {
        regLabel.style.border = '1px solid rgba(245,158,11,0.5)';
        regLabel.style.background = 'rgba(245,158,11,0.09)';
      }
      if (platLabel) {
        platLabel.style.border = '1px solid var(--border-color, rgba(255,255,255,0.1))';
        platLabel.style.background = 'rgba(255,255,255,0.02)';
      }
      if (helpText) {
        helpText.textContent = 'Regular partners are limited to 1 promotional product showcase on the website.';
      }
      // If switching to Regular and more than 1 product exists, trim to 1
      if (container && container.children.length > 1) {
        while (container.children.length > 1) {
          container.lastElementChild.remove();
        }
      }
    }

    updateProductCounter(containerId, isPlatinum);
  }

  function updateProductCounter(containerId, isPlatinum) {
    var container = document.getElementById(containerId);
    var prefix = containerId === 'addSponsorProductsList' ? 'add' : 'edit';
    var badge = document.getElementById(prefix + 'ProductsCountBadge');
    var btn = prefix === 'add' ? document.getElementById('btnAddProductSlot') : document.getElementById('btnEditAddProductSlot');
    
    var maxLimit = isPlatinum ? 10 : 1;
    var count = container ? container.querySelectorAll('.sponsor-product-slot').length : 0;

    if (badge) {
      badge.textContent = '(' + count + '/' + maxLimit + ' Product' + (maxLimit > 1 ? 's' : '') + ')';
      if (count >= maxLimit) {
        badge.style.background = 'rgba(239,68,68,0.15)';
        badge.style.color = '#ef4444';
      } else {
        badge.style.background = 'rgba(245,158,11,0.15)';
        badge.style.color = 'var(--accent-primary, #f5b800)';
      }
    }

    if (btn) {
      if (count >= maxLimit) {
        btn.disabled = true;
        btn.style.opacity = '0.4';
        btn.style.cursor = 'not-allowed';
        btn.title = 'Maximum product limit reached (' + maxLimit + ')';
      } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.title = 'Add product';
      }
    }
  }

  var productSlotCount = 100;
  function addProductSlot(containerId, prodData, prefixParam) {
    var container = document.getElementById(containerId);
    if (!container) return;

    var prefix = prefixParam || (containerId === 'addSponsorProductsList' ? 'add' : 'edit');
    var isPlatRadio = document.getElementById(prefix + '_tier_platinum');
    var isPlatinum = isPlatRadio ? isPlatRadio.checked : true;
    var maxLimit = isPlatinum ? 10 : 1;
    var currentCount = container.querySelectorAll('.sponsor-product-slot').length;

    if (currentCount >= maxLimit) {
      alert('Maximum product limit of ' + maxLimit + ' reached for ' + (isPlatinum ? 'Platinum' : 'Regular') + ' partners.');
      return;
    }

    productSlotCount++;
    var idx = 'slot_' + Date.now() + '_' + productSlotCount;
    var data = prodData || { id: 'prod_' + Date.now(), name: '', description: '', link_url: '', image_path: '' };

    var div = document.createElement('div');
    div.className = 'sponsor-product-slot';
    div.style.cssText = 'background: rgba(0,0,0,0.25); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 8px; padding: 12px;';

    var imgPreview = '';
    if (data.image_path) {
      var fullUrl = data.image_path.indexOf('http') === 0 ? data.image_path : ('<?php echo BASE_URL; ?>/' + data.image_path.replace(/^\//, ''));
      imgPreview = '<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;"><img src="' + fullUrl + '" style="width:44px; height:44px; object-fit:cover; border-radius:4px;"><span class="muted" style="font-size:11px;">Current Image</span></div>';
    }

    div.innerHTML = [
      '<input type="hidden" name="products[' + idx + '][id]" value="' + (data.id || '') + '">',
      '<input type="hidden" name="products[' + idx + '][existing_image]" value="' + (data.image_path || '') + '">',
      '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">',
      '  <span style="font-size:12px; font-weight:700; color:var(--accent-primary, #f5b800);">Promotional Product #' + (currentCount + 1) + '</span>',
      '  <button type="button" onclick="removeProductSlot(this, \'' + containerId + '\', \'' + prefix + '\')" style="background:transparent; border:none; color:#ef4444; font-size:11px; cursor:pointer; padding:2px 6px;">&times; Remove</button>',
      '</div>',
      '<div class="grid-2" style="gap:8px; margin-bottom:8px;">',
      '  <div class="field" style="margin:0;"><label style="font-size:11.5px;">Product Name *</label><input type="text" name="products[' + idx + '][name]" value="' + (data.name ? data.name.replace(/"/g, '&quot;') : '') + '" placeholder="e.g. Megacryl Latex Paint, Cool Roof Membrane" required style="font-size:12px; padding:6px 10px;"></div>',
      '  <div class="field" style="margin:0;"><label style="font-size:11.5px;">Product Link URL (Optional)</label><input type="url" name="products[' + idx + '][link_url]" value="' + (data.link_url ? data.link_url.replace(/"/g, '&quot;') : '') + '" placeholder="https://..." style="font-size:12px; padding:6px 10px;"></div>',
      '</div>',
      '<div class="field" style="margin-bottom:8px;"><label style="font-size:11.5px;">Product Description & Specs</label><textarea name="products[' + idx + '][description]" rows="2" placeholder="Product features, architectural application, technical specifications..." style="font-size:12px; padding:6px 10px;">' + (data.description || '') + '</textarea></div>',
      '<div class="field" style="margin:0;"><label style="font-size:11.5px;">Product Image</label>' + imgPreview + '<input type="file" name="products[' + idx + '][image]" accept=".jpg,.jpeg,.png,.webp" style="font-size:11px; padding:4px;"></div>'
    ].join('');

    container.appendChild(div);
    updateProductCounter(containerId, isPlatinum);
  }

  function removeProductSlot(btn, containerId, prefix) {
    var slot = btn.closest('.sponsor-product-slot');
    if (slot) slot.remove();
    var isPlatRadio = document.getElementById(prefix + '_tier_platinum');
    var isPlatinum = isPlatRadio ? isPlatRadio.checked : true;
    updateProductCounter(containerId, isPlatinum);
  }

  function previewNewsUpload(input, imgId, wrapId) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById(imgId);
      var wrap = document.getElementById(wrapId);
      if (img && wrap) {
        img.src = e.target.result;
        wrap.style.display = 'flex';
      }
    };
    reader.readAsDataURL(input.files[0]);
  }

  function openEditNewsModal(data) {
    document.getElementById('edit_news_id').value = data.id;
    document.getElementById('edit_news_title').value = data.title || '';
    var dateVal = data.date_posted ? data.date_posted.substring(0, 10) : new Date().toISOString().substring(0, 10);
    document.getElementById('edit_news_date').value = dateVal;
    document.getElementById('edit_news_summary').value = data.summary || '';
    
    var curImgWrap = document.getElementById('editNewsCurrentImgWrap');
    var curImg = document.getElementById('editNewsCurrentImg');
    var removeChk = document.getElementById('edit_remove_news_image');
    if (removeChk) removeChk.checked = false;

    if (data.image_path) {
      var fullUrl = data.image_path.indexOf('http') === 0 ? data.image_path : ('<?php echo BASE_URL; ?>/' + data.image_path.replace(/^\//, ''));
      if (curImg) curImg.src = fullUrl;
      if (curImgWrap) curImgWrap.style.display = 'flex';
    } else {
      if (curImgWrap) curImgWrap.style.display = 'none';
      if (curImg) curImg.src = '';
    }

    document.getElementById('editNewsModal').style.display = 'flex';
  }

  function closeEditNewsModal() {
    document.getElementById('editNewsModal').style.display = 'none';
  }

  function openEditSponsorModal(data) {
    document.getElementById('edit_sponsor_id').value = data.id;
    document.getElementById('edit_sponsor_name').value = data.name || '';
    document.getElementById('edit_sponsor_url').value = data.url || '';
    document.getElementById('edit_sponsor_desc').value = data.description || '';
    
    var isPlat = parseInt(data.is_platinum) === 1;
    var platRadio = document.getElementById('edit_tier_platinum');
    var regRadio = document.getElementById('edit_tier_regular');
    if (isPlat && platRadio) platRadio.checked = true;
    else if (regRadio) regRadio.checked = true;

    setSponsorTier('edit', isPlat);

    var list = document.getElementById('editSponsorProductsList');
    if (list) {
      list.innerHTML = '';
      if (data.products && Array.isArray(data.products) && data.products.length > 0) {
        var maxCount = isPlat ? 10 : 1;
        data.products.slice(0, maxCount).forEach(function(p) {
          addProductSlot('editSponsorProductsList', p, 'edit');
        });
      }
    }
    updateProductCounter('editSponsorProductsList', isPlat);

    document.getElementById('editSponsorModal').style.display = 'flex';
  }

  function closeEditSponsorModal() {
    document.getElementById('editSponsorModal').style.display = 'none';
  }

  window.addEventListener('click', function(e) {
    var newsModal = document.getElementById('editNewsModal');
    if (e.target === newsModal) {
      closeEditNewsModal();
    }
    var sponsorModal = document.getElementById('editSponsorModal');
    if (e.target === sponsorModal) {
      closeEditSponsorModal();
    }
  });
</script>

<!-- SortableJS for drag-and-drop news reordering -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function() {
  var list = document.getElementById('newsSortable');
  if (!list) return;

  // Hover / active styles via JS to avoid extra CSS file
  list.addEventListener('mouseover', function(e) {
    var item = e.target.closest('.news-sortable-item');
    if (item) {
      item.style.borderColor = 'rgba(245,158,11,0.5)';
      item.style.boxShadow = '0 4px 16px rgba(0,0,0,0.25)';
      item.querySelector('.drag-handle').style.opacity = '1';
    }
  });
  list.addEventListener('mouseout', function(e) {
    var item = e.target.closest('.news-sortable-item');
    if (item && !item.classList.contains('sortable-drag')) {
      item.style.borderColor = '';
      item.style.boxShadow = '';
      item.querySelector('.drag-handle').style.opacity = '0.5';
    }
  });

  Sortable.create(list, {
    handle: '.news-sortable-item',   // whole row is draggable
    animation: 180,
    ghostClass: 'news-drag-ghost',
    chosenClass: 'news-drag-chosen',
    dragClass: 'news-dragging',
    onEnd: function() {
      // Collect new order
      var items = list.querySelectorAll('.news-sortable-item');
      var ids = [];
      items.forEach(function(el, i) {
        ids.push(el.dataset.id);
        // Update the visible badge number
        var badge = el.querySelector('.news-order-badge');
        if (badge) badge.textContent = i + 1;
      });

      // Update hidden input for manual form submit
      var orderInput = document.getElementById('newsOrderInput');
      if (orderInput) {
        orderInput.value = JSON.stringify(ids);
      }

      var saveBtn = document.getElementById('saveNewsOrderBtn');
      var saveBtnText = document.getElementById('saveNewsOrderBtnText');
      if (saveBtn && saveBtnText) {
        saveBtn.style.background = 'var(--accent-primary, #f59e0b)';
        saveBtnText.textContent = 'Save News Formation *';
      }

      // Background AJAX POST
      var csrf = '<?php echo csrf_token(); ?>';
      var form = new FormData();
      form.append('_csrf_token', csrf);
      form.append('csrf_token', csrf);
      form.append('action', 'reorder_news');
      form.append('order', JSON.stringify(ids));

      fetch('settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: form
      }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.ok) {
          var saved = document.getElementById('newsSortSaved');
          if (saved) {
            saved.style.display = 'inline-flex';
            if (saveBtnText) saveBtnText.textContent = 'Save News Formation';
            setTimeout(function() {
              saved.style.display = 'none';
            }, 3000);
          }
        }
      }).catch(function(){});
    }
  });

  // Drag state visual
  list.addEventListener('mousedown', function(e) {
    var item = e.target.closest('.news-sortable-item');
    if (item) item.style.cursor = 'grabbing';
  });
  document.addEventListener('mouseup', function() {
    list.querySelectorAll('.news-sortable-item').forEach(function(el) {
      el.style.cursor = 'grab';
    });
  });
})();

  // Officer Modal & Management Functions
  function openAddOfficerModal() {
    document.getElementById('addOfficerModal').style.display = 'flex';
  }
  function closeAddOfficerModal() {
    document.getElementById('addOfficerModal').style.display = 'none';
  }

  function openEditOfficerModal(data) {
    document.getElementById('edit_officer_id').value = data.id || '';
    document.getElementById('edit_officer_user_id').value = data.user_id || '';
    document.getElementById('edit_officer_name').value = data.name || '';
    document.getElementById('edit_officer_prefix').value = data.title_prefix || 'Ar.';
    document.getElementById('edit_officer_suffix').value = data.title_suffix || 'uap';
    document.getElementById('edit_officer_position').value = data.position || '';
    document.getElementById('edit_officer_committee').value = data.committee || '';
    document.getElementById('edit_officer_fy').value = data.fiscal_year || '2026-2027';
    document.getElementById('edit_officer_active').checked = !!parseInt(data.is_active);

    var photoWrap = document.getElementById('editOfficerPhotoWrap');
    var photoImg = document.getElementById('editOfficerCurrentPhoto');
    var removeCheckbox = document.getElementById('edit_remove_officer_photo');
    if (removeCheckbox) removeCheckbox.checked = false;

    if (data.photo_path) {
      var photoUrl = (data.photo_path.indexOf('http') === 0 || data.photo_path.indexOf('/') === 0) 
        ? data.photo_path 
        : ('<?php echo BASE_URL; ?>/' + data.photo_path);
      photoImg.src = photoUrl;
      photoWrap.style.display = 'flex';
    } else {
      photoWrap.style.display = 'none';
    }

    document.getElementById('editOfficerModal').style.display = 'flex';
  }

  function closeEditOfficerModal() {
    document.getElementById('editOfficerModal').style.display = 'none';
  }

  function syncOfficerMember(mode) {
    var select = document.getElementById(mode + '_officer_user_id');
    var nameInput = document.getElementById(mode + '_officer_name');
    if (!select || !nameInput) return;
    var opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
      var memberName = opt.getAttribute('data-name') || '';
      if (memberName.indexOf('Ar. ') === 0) {
        memberName = memberName.replace(/^Ar\.\s+/, '');
      }
      memberName = memberName.replace(/,\s*uap$/i, '');
      nameInput.value = memberName;
    }
  }

  // Officer drag-and-drop sortable
  (function() {
    var list = document.getElementById('officersSortableList');
    if (!list || typeof Sortable === 'undefined') return;

    list.addEventListener('mouseover', function(e) {
      var item = e.target.closest('.officer-sortable-item');
      if (item && !item.classList.contains('sortable-drag')) {
        item.style.borderColor = 'rgba(229,138,19,0.5)';
        var handle = item.querySelector('.drag-handle');
        if (handle) handle.style.opacity = '1';
      }
    });
    list.addEventListener('mouseout', function(e) {
      var item = e.target.closest('.officer-sortable-item');
      if (item && !item.classList.contains('sortable-drag')) {
        item.style.borderColor = '';
        var handle = item.querySelector('.drag-handle');
        if (handle) handle.style.opacity = '0.5';
      }
    });

    Sortable.create(list, {
      handle: '.officer-sortable-item',
      animation: 180,
      ghostClass: 'news-drag-ghost',
      chosenClass: 'news-drag-chosen',
      dragClass: 'news-dragging',
      onEnd: function() {
        var items = list.querySelectorAll('.officer-sortable-item');
        var ids = [];
        items.forEach(function(el, i) {
          ids.push(el.dataset.id);
          var badge = el.querySelector('.officer-order-badge');
          if (badge) badge.textContent = i + 1;
        });

        var orderInput = document.getElementById('officersOrderInput');
        if (orderInput) orderInput.value = JSON.stringify(ids);

        var saveBtn = document.getElementById('saveOfficersOrderBtn');
        var saveBtnText = document.getElementById('saveOfficersOrderBtnText');
        if (saveBtn && saveBtnText) {
          saveBtn.style.background = 'var(--accent-primary, #f59e0b)';
          saveBtnText.textContent = 'Save Officers Formation *';
        }

        var csrf = '<?php echo csrf_token(); ?>';
        var form = new FormData();
        form.append('_csrf_token', csrf);
        form.append('csrf_token', csrf);
        form.append('action', 'reorder_officers');
        form.append('order', JSON.stringify(ids));

        fetch('settings.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: form
        }).then(function(r) { return r.json(); }).then(function(data) {
          if (data.ok) {
            var saved = document.getElementById('officersSortSaved');
            if (saved) {
              saved.style.display = 'inline-flex';
              if (saveBtnText) saveBtnText.textContent = 'Save Officers Formation';
              setTimeout(function() { saved.style.display = 'none'; }, 3000);
            }
          }
        }).catch(function(){});
      }
    });

    list.addEventListener('mousedown', function(e) {
      var item = e.target.closest('.officer-sortable-item');
      if (item) item.style.cursor = 'grabbing';
    });
    document.addEventListener('mouseup', function() {
      list.querySelectorAll('.officer-sortable-item').forEach(function(el) {
        el.style.cursor = 'grab';
      });
    });
  })();
</script>

<style>
.news-drag-ghost  { opacity: 0.4; background: rgba(245,158,11,0.08) !important; border: 1.5px dashed rgba(245,158,11,0.6) !important; }
.news-drag-chosen { box-shadow: 0 8px 30px rgba(0,0,0,0.4) !important; border-color: rgba(245,158,11,0.6) !important; }
.news-dragging    { opacity: 0.95; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
