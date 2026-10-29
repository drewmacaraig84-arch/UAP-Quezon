<?php
require_once __DIR__ . '/../includes/auth.php';
require_member();

$userId = current_user_id();
$app = get_directory_application($pdo, $userId);
$isUnlocked = has_unlocked_website_directory($pdo, $userId);

$error = '';
$success = '';

// Handle Application Submission & Re-application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['apply_directory', 'reapply_directory'], true)) {
    require_csrf();
    if (!$app) {
        $stmt = $pdo->prepare("INSERT INTO directory_applications (user_id, status) VALUES (?, 'pending_fee')
                               ON DUPLICATE KEY UPDATE status = 'pending_fee'");
        $stmt->execute([$userId]);
        $success = 'Application submitted successfully! The Chapter Admin will review and assign your advertising fee shortly.';
        $app = get_directory_application($pdo, $userId);
    } elseif ($app['status'] === 'rejected') {
        $allowed = (int)($app['reapply_allowed'] ?? 1);
        $reapplyDate = $app['reapply_after'] ?? null;
        $canReapply = ($allowed === 1) && (empty($reapplyDate) || strtotime($reapplyDate) <= strtotime(date('Y-m-d')));

        if (!$allowed) {
            $error = 'Re-application is currently restricted by Chapter Administration. Please consult the chapter secretariat for assistance.';
        } elseif (!$canReapply) {
            $error = 'You will be eligible to re-apply on or after ' . date('F d, Y', strtotime($reapplyDate)) . '.';
        } else {
            $stmt = $pdo->prepare("UPDATE directory_applications 
                                   SET status = 'pending_fee', 
                                       fee_amount = NULL, 
                                       member_due_id = NULL, 
                                       notes = NULL, 
                                       reapply_allowed = 1, 
                                       reapply_after = NULL, 
                                       dismissed_notification = 0, 
                                       rejected_at = NULL, 
                                       created_at = NOW() 
                                   WHERE user_id = ?");
            $stmt->execute([$userId]);
            $success = 'Your directory application has been re-submitted! The Chapter Admin will review it shortly.';
            $app = get_directory_application($pdo, $userId);
        }
    }
}

// 1. Handle Profile Details Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile_details') {
    require_csrf();
    
    if (!$isUnlocked) {
        $error = 'You must complete and verify your directory advertising payment to unlock this feature.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $idNumber = trim($_POST['id_number'] ?? '');
        if ($idNumber === '') {
            $uPr = $pdo->prepare("SELECT id_number FROM users WHERE id = ?");
            $uPr->execute([$userId]);
            $idNumber = $uPr->fetchColumn() ?: '';
        }
        $role = trim($_POST['role_title'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');

        // Process up to 3 Website / Social Media Links
        $links = [];
        $rawLinks = $_POST['links'] ?? [];
        if (is_array($rawLinks)) {
            foreach ($rawLinks as $lnk) {
                $u = trim($lnk['url'] ?? '');
                $t = trim($lnk['type'] ?? 'auto');
                if (!in_array($t, ['auto', 'facebook', 'instagram', 'linkedin', 'youtube', 'telegram', 'website'], true)) {
                    $t = 'auto';
                }
                if ($u !== '') {
                    if (!preg_match('#^https?://#i', $u)) {
                        $u = 'https://' . ltrim($u, '/');
                    }
                    $links[] = [
                        'url' => $u,
                        'type' => $t
                    ];
                }
            }
        }
        // Fallback to legacy single link inputs if rawLinks was empty
        if (empty($links) && !empty($_POST['link_url'])) {
            $u = trim($_POST['link_url']);
            $t = trim($_POST['link_type'] ?? 'auto');
            if ($u !== '') {
                if (!preg_match('#^https?://#i', $u)) $u = 'https://' . ltrim($u, '/');
                $links[] = ['url' => $u, 'type' => $t];
            }
        }
        $links = array_slice($links, 0, 3);
        $linksJson = !empty($links) ? json_encode($links) : null;
        $primaryLinkUrl = !empty($links[0]['url']) ? $links[0]['url'] : null;
        $primaryLinkType = !empty($links[0]['type']) ? $links[0]['type'] : 'auto';

        $achievements = trim($_POST['achievements'] ?? '');
        $awards = trim($_POST['awards'] ?? '');

        // Handle Company Logo Upload
        $companyLogoPath = $profile['company_logo_path'] ?? null;
        $uploadDir = __DIR__ . '/../uploads/members/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['company_logo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['company_logo']['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error = 'Company logo file size exceeds the server upload limit.';
            } elseif ($_FILES['company_logo']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Failed to upload company logo (error code: ' . $_FILES['company_logo']['error'] . ').';
            } else {
                $cloExt = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
                $cloMime = mime_content_type($_FILES['company_logo']['tmp_name']);
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                if (in_array($cloExt, $allowedExts) && in_array($cloMime, $allowedMimes) && $_FILES['company_logo']['size'] <= 25 * 1024 * 1024) {
                    // Delete old company logo if exists
                    if (!empty($companyLogoPath)) {
                        $oldFile = __DIR__ . '/../' . ltrim($companyLogoPath, '/');
                        if (file_exists($oldFile)) @unlink($oldFile);
                    }
                    $cloUnique = 'company_logo_' . $userId . '_' . time() . '.' . $cloExt;
                    $cloTarget = $uploadDir . $cloUnique;
                    if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $cloTarget)) {
                        @chmod($cloTarget, 0664);
                        $companyLogoPath = 'uploads/members/' . $cloUnique;
                    }
                } else {
                    $error = 'Company logo must be a valid JPG, PNG, or WebP file under 25MB.';
                }
            }
        }

        // Handle company logo removal
        if (!empty($_POST['remove_company_logo'])) {
            if (!empty($companyLogoPath)) {
                $oldFile = __DIR__ . '/../' . ltrim($companyLogoPath, '/');
                if (file_exists($oldFile)) @unlink($oldFile);
            }
            $companyLogoPath = null;
        }

        // Check if user has an existing profile photo in users table
        $uStmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $userPhoto = $uStmt->fetchColumn();
        $firstPhoto = !empty($userPhoto) ? $userPhoto : null;

        ensure_user_profile_photo_column($pdo);
        $stmt = $pdo->prepare("INSERT INTO website_members 
            (user_id, name, id_number, role_title, specialty, location, company_name, company_logo_path, link_url, link_type, links_json, achievements, awards, photo_path, is_published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                id_number = VALUES(id_number),
                role_title = VALUES(role_title),
                specialty = VALUES(specialty),
                location = VALUES(location),
                company_name = VALUES(company_name),
                company_logo_path = VALUES(company_logo_path),
                link_url = VALUES(link_url),
                link_type = VALUES(link_type),
                links_json = VALUES(links_json),
                achievements = VALUES(achievements),
                awards = VALUES(awards),
                photo_path = VALUES(photo_path),
                is_published = 1");
        $stmt->execute([
            $userId,
            $name,
            $idNumber,
            $role,
            $specialty,
            $location,
            $companyName !== '' ? $companyName : null,
            $companyLogoPath,
            $primaryLinkUrl,
            $primaryLinkType,
            $linksJson,
            $achievements,
            $awards,
            $firstPhoto
        ]);

        // Automatically generate public QR code for this website directory member
        if (file_exists(__DIR__ . '/../includes/qr_helper.php')) {
            require_once __DIR__ . '/../includes/qr_helper.php';
            $fStmt = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
            $fStmt->execute([$userId]);
            $wmId = (int)$fStmt->fetchColumn();
            if ($wmId > 0 && function_exists('generate_member_directory_qr')) {
                generate_member_directory_qr($pdo, $wmId, true);
            }
        }

        $success = 'Directory profile details saved successfully!';
    }
}

// 2. Handle Projects Portfolio Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_projects') {
    require_csrf();

    if (!$isUnlocked) {
        $error = 'You must complete and verify your directory advertising payment to unlock this feature.';
    } else {
        $projects = [];
        $rawProjects = $_POST['projects'] ?? [];
        $uploadDir = __DIR__ . '/../uploads/members/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        if (is_array($rawProjects)) {
            foreach ($rawProjects as $pKey => $pData) {
                $pTitle = trim($pData['title'] ?? '');
                $pId = !empty($pData['id']) ? trim($pData['id']) : ('proj_' . time() . '_' . bin2hex(random_bytes(3)));
                $pCat = trim($pData['category'] ?? 'RESIDENTIAL');
                $pLoc = trim($pData['location'] ?? '');
                $pDesc = trim($pData['description'] ?? '');
                $pTeam = trim($pData['project_team'] ?? '');
                $existingCover = trim($pData['existing_cover'] ?? '');
                $existingPhotos = $pData['existing_photos'] ?? [];
                $deletePhotos = $pData['delete_photos'] ?? [];

                $coverPhoto = '';
                $additionalPhotos = [];

                if (!empty($existingCover) && !in_array('cover', $deletePhotos, true)) {
                    $coverPhoto = $existingCover;
                }
                if (is_array($existingPhotos)) {
                    foreach ($existingPhotos as $phIdx => $phPath) {
                        if (!empty($phPath) && !in_array((string)$phIdx, $deletePhotos, true)) {
                            if ($phPath !== $coverPhoto && !in_array($phPath, $additionalPhotos, true)) {
                                $additionalPhotos[] = $phPath;
                            }
                        }
                    }
                }

                // Handle Newly Uploaded Cover Photo
                if (isset($_FILES['projects']['error'][$pKey]['cover']) && $_FILES['projects']['error'][$pKey]['cover'] !== UPLOAD_ERR_NO_FILE) {
                    if ($_FILES['projects']['error'][$pKey]['cover'] === UPLOAD_ERR_INI_SIZE || $_FILES['projects']['error'][$pKey]['cover'] === UPLOAD_ERR_FORM_SIZE) {
                        $error = 'Project cover photo exceeds the server upload limit.';
                    } elseif ($_FILES['projects']['error'][$pKey]['cover'] === UPLOAD_ERR_OK) {
                        $cName = $_FILES['projects']['name'][$pKey]['cover'];
                        $cExt = strtolower(pathinfo($cName, PATHINFO_EXTENSION));
                        if (in_array($cExt, $allowedExts) && $_FILES['projects']['size'][$pKey]['cover'] <= 25 * 1024 * 1024) {
                            $uniqueName = 'proj_cover_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $cExt;
                            $targetPath = $uploadDir . $uniqueName;
                            if (move_uploaded_file($_FILES['projects']['tmp_name'][$pKey]['cover'], $targetPath)) {
                                @chmod($targetPath, 0664);
                                $coverPhoto = 'uploads/members/' . $uniqueName;
                            }
                        }
                    }
                }

                // Handle Newly Uploaded Additional Photos (up to limit of 5 total photos)
                if (isset($_FILES['projects']['name'][$pKey]['photos']) && is_array($_FILES['projects']['name'][$pKey]['photos'])) {
                    foreach ($_FILES['projects']['name'][$pKey]['photos'] as $fIdx => $filename) {
                        if (count($additionalPhotos) + (!empty($coverPhoto) ? 1 : 0) >= 5) break; // Limit to 5 total photos including cover
                        if (isset($_FILES['projects']['error'][$pKey]['photos'][$fIdx]) && $_FILES['projects']['error'][$pKey]['photos'][$fIdx] === UPLOAD_ERR_OK && !empty($filename)) {
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            if (in_array($ext, $allowedExts) && $_FILES['projects']['size'][$pKey]['photos'][$fIdx] <= 25 * 1024 * 1024) {
                                $uniqueName = 'proj_photo_' . $userId . '_' . time() . '_' . $fIdx . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                                $targetPath = $uploadDir . $uniqueName;
                                if (move_uploaded_file($_FILES['projects']['tmp_name'][$pKey]['photos'][$fIdx], $targetPath)) {
                                    @chmod($targetPath, 0664);
                                    $additionalPhotos[] = 'uploads/members/' . $uniqueName;
                                }
                            }
                        }
                    }
                }

                // Combine: Cover photo is ALWAYS #1 in the photos array, followed by additional photos
                $allPhotos = [];
                if (!empty($coverPhoto)) {
                    $allPhotos[] = $coverPhoto;
                }
                foreach ($additionalPhotos as $aph) {
                    if (!in_array($aph, $allPhotos, true)) {
                        $allPhotos[] = $aph;
                    }
                }
                if (empty($coverPhoto) && !empty($allPhotos[0])) {
                    $coverPhoto = $allPhotos[0];
                }
                $allPhotos = array_slice($allPhotos, 0, 5);

                if (!empty($pTitle) || !empty($allPhotos)) {
                    $projects[] = [
                        'id' => $pId,
                        'title' => $pTitle !== '' ? $pTitle : 'Completed Architectural Work',
                        'category' => $pCat !== '' ? strtoupper($pCat) : 'RESIDENTIAL',
                        'location' => $pLoc !== '' ? $pLoc : '',
                        'description' => $pDesc,
                        'project_team' => $pTeam,
                        'cover_photo' => $coverPhoto,
                        'photos' => $allPhotos
                    ];
                }
            }
        }

        $projectsJson = json_encode($projects);

        // Build flat gallery for legacy consumers
        $gallery = [];
        foreach ($projects as $proj) {
            foreach ($proj['photos'] as $ph) {
                $gallery[] = [
                    'path' => $ph,
                    'description' => $proj['title']
                ];
            }
        }
        $galleryJson = json_encode($gallery);

        ensure_user_profile_photo_column($pdo);
        $stmt = $pdo->prepare("UPDATE website_members SET projects_json = ?, gallery_json = ?, is_published = 1 WHERE user_id = ?");
        $stmt->execute([$projectsJson, $galleryJson, $userId]);

        $success = 'Completed works & projects portfolio saved successfully!';
    }
}

// Fetch Profile
$record = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ? LIMIT 1");
$record->execute([$userId]);
$profile = $record->fetch();

// Fetch current user details
$uStmt = $pdo->prepare("SELECT name, id_number, profile_photo FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$uRow = $uStmt->fetch();
$userProfilePhoto = $uRow['profile_photo'] ?? null;

// Fetch current user details as initial fallback
if (!$profile) {
    $profile = [
        'name' => $uRow['name'] ?? '',
        'id_number' => $uRow['id_number'] ?? '',
        'role_title' => 'Architect',
        'specialty' => '',
        'location' => 'Quezon',
        'company_name' => '',
        'company_logo_path' => null,
        'link_url' => '',
        'link_type' => 'auto',
        'links_json' => '',
        'achievements' => '',
        'awards' => '',
        'photo_path' => $userProfilePhoto,
        'gallery_json' => '',
        'projects_json' => ''
    ];
}

// Decode social links (up to 3 links)
$socialLinks = [];
if (!empty($profile['links_json'])) {
    $decodedLinks = json_decode($profile['links_json'], true);
    if (is_array($decodedLinks)) $socialLinks = $decodedLinks;
}
if (empty($socialLinks) && !empty($profile['link_url'])) {
    $socialLinks[] = [
        'url' => $profile['link_url'],
        'type' => $profile['link_type'] ?? 'auto'
    ];
}
while (count($socialLinks) < 3) {
    $socialLinks[] = ['url' => '', 'type' => 'auto'];
}

// Decode Completed Works projects
$projects = [];
if (!empty($profile['projects_json'])) {
    $decodedProjects = json_decode($profile['projects_json'], true);
    if (is_array($decodedProjects)) $projects = $decodedProjects;
}

$page_title = 'Website Directory Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 960px; margin: 0 auto;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 16px;">
    <div>
      <h1 style="margin-bottom:4px;">Website Directory Feature</h1>
      <p class="muted">Showcase your architectural practice, credentials, and projects on the official chapter website.</p>
    </div>
    <?php if ($isUnlocked): ?>
      <span class="badge badge-paid" style="font-size:13px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('sparkles', '', 14); ?> <span>Feature Unlocked</span>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom: 18px;"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom: 18px;"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if (!$isUnlocked): ?>
    <!-- Application & Unlock Flow -->
    <?php if (!$app): ?>
      <div style="background: rgba(245,158,11,0.05); border: 1px dashed rgba(245,158,11,0.3); border-radius: 12px; padding: 24px; text-align: center;">
        <div style="font-size: 32px; color: var(--accent-primary, #f5b800); margin-bottom: 8px;">★</div>
        <h3 style="margin-bottom: 8px;">Unlock Your Official Website Directory Listing</h3>
        <p class="muted" style="max-width: 540px; margin: 0 auto 20px; font-size: 14px;">
          Promote your architectural practice, services, portfolio, and contact links directly on the public UAP Quezon Website.
        </p>
        <form method="POST" style="display: inline-block;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="apply_directory">
          <button class="btn btn-primary" type="submit" style="padding: 10px 24px; font-weight:700;">Apply for Directory Feature</button>
        </form>
      </div>

    <?php elseif ($app['status'] === 'pending_fee'): ?>
      <div class="alert alert-info" style="display: flex; gap: 14px; align-items: center;">
        <div>
          <strong>Application Received &amp; Under Review</strong>
          <p style="margin: 4px 0 0; font-size: 13.5px;">Your application has been received. The Chapter Administrator will assign your advertising fee shortly. Please check back soon.</p>
        </div>
      </div>

    <?php elseif ($app['status'] === 'fee_set'): ?>
      <div style="background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.3); border-radius: 12px; padding: 22px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
          <div>
            <span class="badge badge-unpaid" style="font-size: 12px;">Payment Required</span>
            <h3 style="margin: 6px 0 0;">Website Directory Advertising Fee: ₱<?php echo number_format($app['fee_amount'], 2); ?></h3>
          </div>
          <?php if (!empty($app['due_id'])): ?>
            <a href="pay.php?id=<?php echo (int)$app['due_id']; ?>" class="btn btn-success" style="font-weight: 700; padding: 10px 20px;">
              Pay Now &rarr;
            </a>
          <?php else: ?>
            <a href="pay.php" class="btn btn-success" style="font-weight: 700; padding: 10px 20px;">
              Go to Payments &rarr;
            </a>
          <?php endif; ?>
        </div>
        <p class="muted" style="margin:0; font-size:13px;">
          Once your payment receipt is verified by the Chapter Treasurer, your website directory profile manager will be instantly activated.
        </p>
      </div>

    <?php elseif ($app['status'] === 'rejected'): ?>
      <?php
        $reapplyAllowed = (int)($app['reapply_allowed'] ?? 1);
        $reapplyDate = $app['reapply_after'] ?? null;
        $canReapply = ($reapplyAllowed === 1) && (empty($reapplyDate) || strtotime($reapplyDate) <= strtotime(date('Y-m-d')));
        $adminNotes = trim($app['notes'] ?? '');
      ?>
      <div style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25); border-radius: 12px; padding: 24px; margin-bottom: 24px;">
        <div style="display:flex; align-items:flex-start; gap:16px;">
          <div style="width:44px; height:44px; border-radius:12px; background:rgba(239,68,68,0.12); color:#ef4444; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
            <?php echo icon('x', '', 22); ?>
          </div>
          <div style="flex:1;">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
              <h3 style="margin:0; font-size:16px; color:var(--text-primary);">Website Directory Application Declined</h3>
              <?php if (!empty($app['rejected_at'])): ?>
                <span class="muted" style="font-size:12px;">Declined on <?php echo date('M d, Y', strtotime($app['rejected_at'])); ?></span>
              <?php endif; ?>
            </div>

            <?php if (!empty($adminNotes)): ?>
              <div style="margin: 12px 0; padding: 12px 14px; background: rgba(0,0,0,0.15); border-radius: 8px; border-left: 3px solid #ef4444;">
                <div style="font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#ef4444; margin-bottom:4px;">Remarks from Chapter Admin:</div>
                <div style="font-size:13.5px; color:var(--text-primary); line-height:1.5;"><?php echo nl2br(htmlspecialchars($adminNotes)); ?></div>
              </div>
            <?php else: ?>
              <p style="margin: 8px 0 12px; font-size: 13.5px; color: var(--text-secondary);">
                Your previous directory submission was reviewed and declined by the chapter administrator.
              </p>
            <?php endif; ?>

            <!-- Re-application status box -->
            <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid rgba(239,68,68,0.15); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
              <?php if (!$reapplyAllowed): ?>
                <div style="display:flex; align-items:center; gap:8px; color:var(--text-secondary); font-size:13px;">
                  <?php echo icon('alert', '', 16); ?>
                  <span>Re-application is currently restricted for this submission. Please reach out to chapter officers for inquiries.</span>
                </div>
              <?php elseif (!empty($reapplyDate) && strtotime($reapplyDate) > strtotime(date('Y-m-d'))): ?>
                <div style="display:flex; align-items:center; gap:8px; color:var(--text-secondary); font-size:13px;">
                  <?php echo icon('clock', '', 16); ?>
                  <span>You can re-apply on or after <strong><?php echo date('F d, Y', strtotime($reapplyDate)); ?></strong>.</span>
                </div>
              <?php else: ?>
                <div>
                  <strong style="display:block; font-size:13px; color:var(--text-primary);">Eligible to Re-apply</strong>
                  <span class="muted" style="font-size:12px;">You can submit a new directory application for administrator review anytime.</span>
                </div>
                <form method="POST" style="margin:0;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="reapply_directory">
                  <button class="btn btn-primary" type="submit" style="display:inline-flex; align-items:center; gap:6px; padding:8px 18px; font-weight:700;">
                    <?php echo icon('sparkles', '', 14); ?> <span>Submit New Application</span>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  <?php else: ?>

    <!-- 1. DIRECTORY PROFILE DETAILS FORM -->
    <form method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_profile_details">

      <!-- PROFILE PHOTO LINKED TO MY PROFILE -->
      <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 12px; padding: 18px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 16px;">
          <div style="width: 64px; height: 64px; border-radius: 50%; overflow: hidden; background: var(--card-bg, #1a2234); border: 2px solid var(--border-color-gold, #f5b800); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php if ($userProfilePhoto): ?>
              <img src="<?php echo htmlspecialchars(media_url($userProfilePhoto)); ?>" alt="Profile Avatar" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
              <span style="font-size: 20px; font-weight: 700; color: var(--accent-primary, #f5b800);">
                <?php echo strtoupper(substr($profile['name'] ?? 'AR', 0, 2)); ?>
              </span>
            <?php endif; ?>
          </div>
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <strong style="font-size: 15px; color: var(--text-primary);">Website Directory Profile Avatar</strong>
              <?php if ($userProfilePhoto): ?>
                <span class="badge-pill" style="background: rgba(16,185,129,0.15); color: #10b981; font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                  <?php echo icon('check', '', 12); ?> <span>Linked to My Profile</span>
                </span>
              <?php endif; ?>
            </div>
            <p class="muted" style="font-size: 12.5px; margin: 0; line-height: 1.4;">
              <?php if ($userProfilePhoto): ?>
                Your profile picture from <strong>My Profile</strong> is automatically displayed on the chapter website directory.
              <?php else: ?>
                No profile picture set yet in My Profile. You can upload an official portrait in My Profile anytime.
              <?php endif; ?>
            </p>
          </div>
        </div>
        <a href="profile.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 8px 14px;">
          <?php echo icon('camera', '', 13); ?> <span>Update in My Profile &rarr;</span>
        </a>
      </div>

      <!-- 1. FULL NAME -->
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
      </div>

      <!-- 2. COMPANY & ADDRESS + COMPANY LOGO -->
      <div style="background: var(--bg-secondary, rgba(0,0,0,0.12)); border: 1px solid var(--border-color, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px; margin-bottom: 20px;">
        <strong style="font-size: 14px; color: var(--text-primary); display: block; margin-bottom: 14px;"><?php echo icon('briefcase', '', 14); ?> Company / Firm Details</strong>

        <div class="grid-2" style="margin-bottom: 14px;">
          <div class="field" style="margin: 0;">
            <label>Company / Architectural Firm Name</label>
            <input type="text" name="company_name" value="<?php echo htmlspecialchars($profile['company_name'] ?? ''); ?>" placeholder="e.g. Ting &amp; Associates Architects, AESTRUKTURA Design Studio">
          </div>
          <div class="field" style="margin: 0;">
            <label>Company / Office Address</label>
            <input type="text" name="location" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" placeholder="e.g. Lucena City, Quezon" required>
          </div>
        </div>

        <!-- COMPANY LOGO UPLOAD -->
        <div style="display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding-top: 12px; border-top: 1px solid var(--border-color, rgba(255,255,255,0.06));">
          <!-- Current Logo Preview -->
          <div id="companyLogoPreviewWrap" style="flex-shrink: 0;">
            <?php
              $existingLogo = $profile['company_logo_path'] ?? null;
              $logoUrl = $existingLogo ? '/' . ltrim($existingLogo, '/') : null;
            ?>
            <div id="companyLogoPreview" style="width: 80px; height: 80px; border-radius: 10px; background: <?php echo $logoUrl ? '#fff' : 'rgba(245,158,11,0.07)'; ?>; display: flex; align-items: center; justify-content: center; overflow: hidden; transition: background 0.2s;">
              <?php if ($logoUrl): ?>
                <img id="companyLogoImg" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Company Logo" style="width: 100%; height: 100%; object-fit: contain;">
              <?php else: ?>
                <div id="companyLogoPlaceholder" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: var(--accent-primary, #f5b800); opacity: 0.6;">
                  <?php echo icon('image', '', 28); ?>
                  <span style="font-size: 9px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;">No Logo</span>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div style="flex: 1; min-width: 200px;">
            <div style="margin-bottom: 6px;">
              <strong style="font-size: 13.5px; color: var(--text-primary); display: block;">Company / Firm Logo</strong>
              <span class="muted" style="font-size: 11.5px;">PNG, JPG or WebP, max 10MB. Displayed on your public architect profile.</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <label for="companyLogoInput" class="btn btn-sm btn-secondary" style="cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-size: 12px; padding: 6px 12px; margin: 0;">
                <?php echo icon('upload', '', 13); ?> <span>Upload Logo</span>
              </label>
              <input type="file" name="company_logo" id="companyLogoInput" accept="image/png,image/jpeg,image/webp" style="display: none;" onchange="previewCompanyLogo(this)">
              <?php if (!empty($existingLogo)): ?>
                <label style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px;">
                  <input type="checkbox" name="remove_company_logo" value="1" onchange="if(this.checked){ document.getElementById('companyLogoPreview').style.background='rgba(245,158,11,0.07)'; document.getElementById('companyLogoImg') && (document.getElementById('companyLogoImg').style.display='none'); }">
                  <span class="muted">Remove current logo</span>
                </label>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. ROLE / TITLE & SPECIALIZATION -->
      <div class="grid-2">
        <div class="field">
          <label>Role / Title</label>
          <input type="text" name="role_title" value="<?php echo htmlspecialchars($profile['role_title'] ?? ''); ?>" placeholder="e.g. Principal Architect, Project Architect, General Manager" required>
        </div>
        <div class="field">
          <label>Architectural Specialty / Specialization</label>
          <input type="text" name="specialty" value="<?php echo htmlspecialchars($profile['specialty'] ?? ''); ?>" placeholder="e.g. Sustainable & Residential Design, Commercial" required>
        </div>
      </div>

      <!-- 4. UP TO 3 WEBSITE / SOCIAL MEDIA LINKS -->
      <div style="background: var(--bg-secondary, rgba(0,0,0,0.12)); border: 1px solid var(--border-color, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
          <div>
            <strong style="font-size: 14px; color: var(--text-primary); display: block;">Website &amp; Social Media Showcase Links</strong>
            <span class="muted" style="font-size: 12px;">Add up to 3 links (Official Portfolio Website, Facebook, Instagram, LinkedIn, YouTube, Telegram)</span>
          </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
          <?php for ($lIdx = 0; $lIdx < 3; $lIdx++): 
            $sLink = $socialLinks[$lIdx] ?? ['url' => '', 'type' => 'auto'];
            $curUrl = $sLink['url'] ?? '';
            $curType = $sLink['type'] ?? 'auto';
          ?>
            <div class="grid-2" style="gap: 12px; margin-bottom: 0; align-items: end; background: rgba(0,0,0,0.15); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color, rgba(255,255,255,0.05));">
              <div class="field" style="margin-bottom: 0;">
                <label style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">
                  Link #<?php echo $lIdx + 1; ?> <?php echo $lIdx === 0 ? '<span style="color:var(--accent-primary, #f5b800); font-weight:600;">(Primary Showcase)</span>' : '<span style="color:var(--text-muted); font-weight:normal;">(Optional)</span>'; ?>
                </label>
                <input type="text" name="links[<?php echo $lIdx; ?>][url]" value="<?php echo htmlspecialchars($curUrl); ?>" placeholder="<?php echo $lIdx === 0 ? 'https://myfirm.com or https://facebook.com/...' : 'https://instagram.com/... or https://linkedin.com/...'; ?>">
              </div>
              <div class="field" style="margin-bottom: 0;">
                <label style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">Platform / Icon Style</label>
                <select name="links[<?php echo $lIdx; ?>][type]">
                  <option value="auto" <?php echo $curType === 'auto' ? 'selected' : ''; ?>>Auto-Detect from URL</option>
                  <option value="website" <?php echo $curType === 'website' ? 'selected' : ''; ?>>Official Website / Portfolio</option>
                  <option value="facebook" <?php echo $curType === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                  <option value="instagram" <?php echo $curType === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                  <option value="linkedin" <?php echo $curType === 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                  <option value="youtube" <?php echo $curType === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                  <option value="telegram" <?php echo $curType === 'telegram' ? 'selected' : ''; ?>>Telegram</option>
                </select>
              </div>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- 5. CAREER ACHIEVEMENTS & PRACTICE (UNDER SOCIAL LINKS) -->
      <div class="field" style="margin-top: 20px;">
        <label style="font-weight: 700; font-size: 13.5px; color: var(--text-primary);">Career Achievements &amp; Practice</label>
        <textarea name="achievements" rows="4" placeholder="Describe your architectural experience, milestones, design philosophy, or notable achievements..." style="font-size: 13.5px; line-height: 1.65;"><?php echo htmlspecialchars($profile['achievements'] ?? ''); ?></textarea>
      </div>

      <!-- 6. HONORS, DISTINCTIONS & AWARDS (UNDER ACHIEVEMENTS) -->
      <div class="field" style="margin-bottom: 20px;">
        <label style="font-weight: 700; font-size: 13.5px; color: var(--text-primary);">Honors, Distinctions &amp; Awards</label>
        <textarea name="awards" rows="4" placeholder="List your professional design awards, chapter recognitions, or academic distinctions..." style="font-size: 13.5px; line-height: 1.65;"><?php echo htmlspecialchars($profile['awards'] ?? ''); ?></textarea>
      </div>

      <!-- SAVE PROFILE DETAILS BUTTON (Placed under Honors, Distinctions & Awards) -->
      <div style="margin-top: 20px; margin-bottom: 30px;">
        <button class="btn btn-success" type="submit" style="padding: 12px 28px; font-weight:700; font-size:15px; display:inline-flex; align-items:center; gap:8px; cursor: pointer;">
          <?php echo icon('check', '', 16); ?> <span>Save Profile Details</span>
        </button>
      </div>
    </form>

    <!-- 2. COMPLETED WORKS & PROJECTS PORTFOLIO (WITH ITS OWN SAVE BUTTON) -->
    <form method="POST" enctype="multipart/form-data" style="margin-top: 10px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_projects">

      <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 14px; padding: 22px; margin: 10px 0 24px; width: 100%; box-sizing: border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 16px;">
          <div>
            <h3 style="margin:0; font-size:18px; color:var(--accent-primary, #f5b800); display:inline-flex; align-items:center; gap:8px;">
              <?php echo icon('camera', '', 20); ?> <span>Completed Works &amp; Projects Portfolio</span>
            </h3>
            <p class="muted" style="font-size:12.5px; margin:4px 0 0;">Add completed architectural works with cover photo, team credits, and up to 5 photos per project.</p>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" id="btnToggleAllProjects" onclick="toggleAllProjects()" class="btn btn-sm btn-secondary" style="font-size:11.5px; padding: 5px 14px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
              <span id="toggleAllIcon">▲</span> <span id="toggleAllText">Collapse All</span>
            </button>
          </div>
        </div>

        <?php 
          $renderProjects = !empty($projects) ? $projects : [];
        ?>

        <div id="noProjectsNotice" style="<?php echo empty($renderProjects) ? 'display:block;' : 'display:none;'; ?> padding: 28px 20px; text-align: center; background: var(--field-bg, rgba(0,0,0,0.18)); border: 1px dashed var(--border-color, rgba(255,255,255,0.15)); border-radius: 12px; margin-bottom: 16px;">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(245,158,11,0.12); color: var(--accent-primary, #f5b800); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <?php echo icon('camera', '', 20); ?>
          </div>
          <strong style="display:block; font-size:14px; color:var(--text-primary); margin-bottom:4px;">No completed works added yet</strong>
          <p class="muted" style="font-size:12.5px; margin:0 auto; max-width:480px;">Click the <strong>"+ Add Completed Work / Project"</strong> button below to manually showcase your architectural projects with custom cover photos, narratives, and collaborators.</p>
        </div>

        <div id="projectsContainer" style="display: flex; flex-direction: column; gap: 14px; width: 100%;">
          <?php foreach ($renderProjects as $pIdx => $proj): ?>
            <?php 
              $coverPath = $proj['cover_photo'] ?? ($proj['photos'][0] ?? '');
              $otherPhotos = array_values(array_filter($proj['photos'] ?? [], function($ph) use ($coverPath) {
                  return $ph !== $coverPath;
              }));
              $totalPhotosCount = (!empty($coverPath) ? 1 : 0) + count($otherPhotos);
            ?>
            <div class="project-card-item" id="proj_card_<?php echo $pIdx; ?>" style="background: var(--field-bg, rgba(0,0,0,0.25)); border: 1px solid var(--border-color, rgba(255,255,255,0.12)); border-radius: 12px; overflow: hidden; width: 100%; box-sizing: border-box; transition: border-color 0.2s;">
              <input type="hidden" name="projects[<?php echo $pIdx; ?>][id]" value="<?php echo htmlspecialchars($proj['id'] ?? ('proj_' . $pIdx)); ?>">
              <input type="hidden" name="projects[<?php echo $pIdx; ?>][existing_cover]" value="<?php echo htmlspecialchars($coverPath); ?>">

              <!-- Accordion Header Bar (Clickable) -->
              <div class="project-card-header" onclick="toggleProjectAccordion(this)" style="display:flex; justify-content:space-between; align-items:center; padding: 12px 16px; background: rgba(255,255,255,0.03); cursor: pointer; user-select: none; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));">
                <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                  <span class="accordion-chevron" style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); font-size: 10px; font-weight: 900; transition: transform 0.25s ease;">▼</span>
                  
                  <?php if ($coverPath): ?>
                    <div style="width: 36px; height: 36px; border-radius: 6px; overflow: hidden; background: #000; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.1);">
                      <img src="<?php echo htmlspecialchars(media_url($coverPath)); ?>" alt="Thumb" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                  <?php endif; ?>

                  <div style="min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                      <span style="font-weight: 800; font-size: 13.5px; color: var(--accent-primary, #f5b800);">Completed Work #<?php echo $pIdx + 1; ?>:</span>
                      <strong class="proj-title-preview" style="font-size: 13.5px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px;"><?php echo htmlspecialchars(!empty($proj['title']) ? $proj['title'] : 'New Architectural Work'); ?></strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px; font-size: 11px; color: var(--text-secondary);">
                      <span class="proj-cat-preview"><?php echo htmlspecialchars(!empty($proj['category']) ? $proj['category'] : 'RESIDENTIAL'); ?></span>
                      <span>•</span>
                      <span><?php echo $totalPhotosCount; ?> / 5 Photos</span>
                    </div>
                  </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;" onclick="event.stopPropagation()">
                  <button type="button" onclick="removeProjectCard(this)" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                    <?php echo icon('trash', '', 12); ?> <span>Delete Project</span>
                  </button>
                </div>
              </div>

              <!-- Collapsible Body Content -->
              <div class="project-card-body" style="padding: 18px; display: block;">
                <div class="grid-3" style="gap: 12px; margin-bottom: 12px;">
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:12px;">Project Title / Name *</label>
                    <input type="text" name="projects[<?php echo $pIdx; ?>][title]" value="<?php echo htmlspecialchars($proj['title'] ?? ''); ?>" placeholder="e.g. Casa San Gregorio, DLSU-D Building" required oninput="updateProjTitlePreview(this)">
                  </div>
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:12px;">Project Category</label>
                    <input type="text" name="projects[<?php echo $pIdx; ?>][category]" value="<?php echo htmlspecialchars($proj['category'] ?? 'RESIDENTIAL'); ?>" placeholder="e.g. RESIDENTIAL, COMMERCIAL, INSTITUTIONAL" oninput="updateProjCatPreview(this)">
                  </div>
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:12px;">Location</label>
                    <input type="text" name="projects[<?php echo $pIdx; ?>][location]" value="<?php echo htmlspecialchars($proj['location'] ?? ''); ?>" placeholder="e.g. Makati, Manila">
                  </div>
                </div>

                <div class="grid-2" style="gap: 16px; margin-bottom: 16px;">
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Architectural Concept &amp; Narrative</label>
                    <textarea name="projects[<?php echo $pIdx; ?>][description]" rows="7" placeholder="Describe the design philosophy, space planning, materials, volumetric form, and concept..." style="font-size:13.5px; line-height:1.65; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"><?php echo htmlspecialchars($proj['description'] ?? ''); ?></textarea>
                  </div>
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Team / Collaborators</label>
                    <textarea name="projects[<?php echo $pIdx; ?>][project_team]" rows="7" placeholder="e.g. Ar. Anthony Nazareno&#10;Ar. Vladimir Banks&#10;IDr. Marielle Saguibo&#10;Engr. Juan Dela Cruz" style="font-size:13.5px; line-height:1.6; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"><?php echo htmlspecialchars($proj['project_team'] ?? ''); ?></textarea>
                  </div>
                </div>

                <!-- Photos Section (Cover + Additional, Limit to 5 total) -->
                <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 14px; border: 1px solid var(--border-color, rgba(255,255,255,0.06));">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong style="font-size: 13px; color: var(--text-primary);">Project Photos (Max 5 photos total including Cover)</strong>
                    <span style="font-size: 11.5px; color: var(--accent-primary, #f5b800); font-weight: 700;"><?php echo $totalPhotosCount; ?> / 5 Photos</span>
                  </div>

                  <!-- Cover Photo Block -->
                  <div style="margin-bottom: 12px; padding: 10px; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.25); border-radius: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                      <span style="font-size: 12px; font-weight: 700; color: var(--accent-primary, #f5b800);">★ Cover Photo (Front Thumbnail on Directory Grid)</span>
                      <?php if ($coverPath): ?>
                        <label style="font-size: 11.5px; color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                          <input type="checkbox" name="projects[<?php echo $pIdx; ?>][delete_photos][]" value="cover"> Remove Cover
                        </label>
                      <?php endif; ?>
                    </div>

                    <?php if ($coverPath): ?>
                      <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <div style="width: 90px; height: 60px; border-radius: 6px; overflow: hidden; background: #000; flex-shrink: 0;">
                          <img src="<?php echo htmlspecialchars(media_url($coverPath)); ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <span class="muted" style="font-size: 11.5px;">Current cover photo</span>
                      </div>
                    <?php endif; ?>

                    <input type="file" name="projects[<?php echo $pIdx; ?>][cover]" accept=".jpg,.jpeg,.png,.webp" style="font-size: 12px;">
                  </div>

                  <!-- Existing Additional Photos -->
                  <?php if (!empty($otherPhotos)): ?>
                    <label style="font-size: 12px; color: var(--text-secondary); display: block; margin-bottom: 6px;">Additional Gallery Photos:</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-bottom: 12px;">
                      <?php foreach ($otherPhotos as $oIdx => $oPath): ?>
                        <div style="background: rgba(0,0,0,0.3); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 6px; padding: 6px; display: flex; flex-direction: column; gap: 4px;">
                          <input type="hidden" name="projects[<?php echo $pIdx; ?>][existing_photos][<?php echo $oIdx; ?>]" value="<?php echo htmlspecialchars($oPath); ?>">
                          <div style="width: 100%; height: 75px; border-radius: 4px; overflow: hidden; background: #000;">
                            <img src="<?php echo htmlspecialchars(media_url($oPath)); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                          </div>
                          <label style="font-size: 11px; color: #ef4444; display: flex; align-items: center; gap: 4px; cursor: pointer;">
                            <input type="checkbox" name="projects[<?php echo $pIdx; ?>][delete_photos][]" value="<?php echo $oIdx; ?>"> Delete
                          </label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <!-- Add More Photos File Input -->
                  <?php if ($totalPhotosCount < 5): ?>
                    <div class="field" style="margin-bottom:0;">
                      <label style="font-size: 12px;">Upload Additional Photos (Up to <?php echo 5 - $totalPhotosCount; ?> more files):</label>
                      <input type="file" name="projects[<?php echo $pIdx; ?>][photos][]" accept=".jpg,.jpeg,.png,.webp" multiple style="font-size: 12px;">
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="margin-top: 18px;">
          <button type="button" id="btnAddProject" class="btn btn-sm" style="background: rgba(245,158,11,0.08); border: 1px dashed var(--accent-primary, #f5b800); color: var(--accent-primary, #f5b800); font-weight: 700; padding: 10px 18px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
            <?php echo icon('plus', '', 14); ?> <span>Add Another Completed Work Project</span>
          </button>
        </div>

        <!-- SAVE COMPLETED WORKS & PROJECTS BUTTON -->
        <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color, rgba(255,255,255,0.1));">
          <button class="btn btn-success" type="submit" style="padding: 12px 28px; font-weight:700; font-size:15px; display:inline-flex; align-items:center; gap:8px; cursor: pointer;">
            <?php echo icon('check', '', 16); ?> <span>Save Completed Works &amp; Projects</span>
          </button>
        </div>
      </div>
    </form>

    <script>
    (function() {
      var projectCount = <?php echo count($renderProjects); ?>;

      var allExpanded = true;

      window.toggleProjectAccordion = function(header) {
        var card = header.closest('.project-card-item');
        if (!card) return;
        var body = card.querySelector('.project-card-body');
        var chevron = card.querySelector('.accordion-chevron');
        if (!body) return;

        if (body.style.display === 'none') {
          body.style.display = 'block';
          if (chevron) chevron.style.transform = 'rotate(0deg)';
        } else {
          body.style.display = 'none';
          if (chevron) chevron.style.transform = 'rotate(-90deg)';
        }

        // Sync main toggle button text based on whether any bodies are visible
        var bodies = document.querySelectorAll('.project-card-item .project-card-body');
        var hasVisible = false;
        bodies.forEach(function(b) {
          if (b.style.display !== 'none') hasVisible = true;
        });
        allExpanded = hasVisible;
        var textSpan = document.getElementById('toggleAllText');
        var iconSpan = document.getElementById('toggleAllIcon');
        if (textSpan) textSpan.textContent = allExpanded ? 'Collapse All' : 'Expand All';
        if (iconSpan) iconSpan.textContent = allExpanded ? '▲' : '▼';
      };

      window.toggleAllProjects = function() {
        allExpanded = !allExpanded;
        var bodies = document.querySelectorAll('.project-card-item .project-card-body');
        var chevrons = document.querySelectorAll('.project-card-item .accordion-chevron');
        var textSpan = document.getElementById('toggleAllText');
        var iconSpan = document.getElementById('toggleAllIcon');

        bodies.forEach(function(b) {
          b.style.display = allExpanded ? 'block' : 'none';
        });
        chevrons.forEach(function(c) {
          c.style.transform = allExpanded ? 'rotate(0deg)' : 'rotate(-90deg)';
        });

        if (textSpan) {
          textSpan.textContent = allExpanded ? 'Collapse All' : 'Expand All';
        }
        if (iconSpan) {
          iconSpan.textContent = allExpanded ? '▲' : '▼';
        }
      };

      window.updateProjTitlePreview = function(input) {
        var card = input.closest('.project-card-item');
        if (!card) return;
        var preview = card.querySelector('.proj-title-preview');
        if (preview) {
          preview.textContent = input.value.trim() || 'New Architectural Work';
        }
      };

      window.updateProjCatPreview = function(input) {
        var card = input.closest('.project-card-item');
        if (!card) return;
        var preview = card.querySelector('.proj-cat-preview');
        if (preview) {
          preview.textContent = input.value.trim().toUpperCase() || 'RESIDENTIAL';
        }
      };

      window.removeProjectCard = function(btn) {
        var card = btn.closest('.project-card-item');
        if (card) {
          card.remove();
        }
        var container = document.getElementById('projectsContainer');
        var notice = document.getElementById('noProjectsNotice');
        if (container && notice) {
          if (container.querySelectorAll('.project-card-item').length === 0) {
            notice.style.display = 'block';
          }
        }
      };

      var btnAdd = document.getElementById('btnAddProject');
      if (btnAdd) {
        btnAdd.addEventListener('click', function(e) {
          e.preventDefault();
          var notice = document.getElementById('noProjectsNotice');
          if (notice) notice.style.display = 'none';

          projectCount++;
          var timestamp = Date.now();
          var pIdx = 'new_' + timestamp;
          var container = document.getElementById('projectsContainer');
          if (!container) return;

          var div = document.createElement('div');
          div.className = 'project-card-item';
          div.id = 'proj_card_' + pIdx;
          div.style.cssText = 'background: var(--field-bg, rgba(0,0,0,0.25)); border: 1px dashed var(--border-color-gold, rgba(245,158,11,0.35)); border-radius: 12px; margin-bottom: 14px; overflow: hidden; width: 100%; box-sizing: border-box;';
          
          div.innerHTML = [
            '<input type="hidden" name="projects[' + pIdx + '][id]" value="proj_' + timestamp + '">',
            '<div class="project-card-header" onclick="toggleProjectAccordion(this)" style="display:flex; justify-content:space-between; align-items:center; padding: 12px 16px; background: rgba(255,255,255,0.03); cursor: pointer; user-select: none; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));">',
            '  <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">',
            '    <span class="accordion-chevron" style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); font-size: 10px; font-weight: 900; transition: transform 0.25s ease;">▼</span>',
            '    <div style="min-width: 0;">',
            '      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">',
            '        <span style="font-weight: 800; font-size: 13.5px; color: var(--accent-primary, #f5b800);">New Completed Work #' + projectCount + ':</span>',
            '        <strong class="proj-title-preview" style="font-size: 13.5px; color: var(--text-primary);">(Unsaved New Project)</strong>',
            '      </div>',
            '      <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px; font-size: 11px; color: var(--text-secondary);">',
            '        <span class="proj-cat-preview">RESIDENTIAL</span>',
            '        <span>•</span>',
            '        <span>0 / 5 Photos</span>',
            '      </div>',
            '    </div>',
            '  </div>',
            '  <div style="display: flex; align-items: center; gap: 8px;" onclick="event.stopPropagation()">',
            '    <button type="button" onclick="removeProjectCard(this)" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">',
            '      <span>Delete Project</span>',
            '    </button>',
            '  </div>',
            '</div>',
            '<div class="project-card-body" style="padding: 18px; display: block;">',
            '  <div class="grid-3" style="gap: 12px; margin-bottom: 12px;">',
            '    <div class="field" style="margin-bottom:0;">',
            '      <label style="font-size:12px;">Project Title / Name *</label>',
            '      <input type="text" name="projects[' + pIdx + '][title]" placeholder="e.g. Casa San Gregorio, DLSU-D Building" required oninput="updateProjTitlePreview(this)">',
            '    </div>',
            '    <div class="field" style="margin-bottom:0;">',
            '      <label style="font-size:12px;">Project Category</label>',
            '      <input type="text" name="projects[' + pIdx + '][category]" placeholder="e.g. RESIDENTIAL, COMMERCIAL, INSTITUTIONAL" value="RESIDENTIAL" oninput="updateProjCatPreview(this)">',
            '    </div>',
            '    <div class="field" style="margin-bottom:0;">',
            '      <label style="font-size:12px;">Location</label>',
            '      <input type="text" name="projects[' + pIdx + '][location]" placeholder="e.g. Makati, Manila">',
            '    </div>',
            '  </div>',
            '  <div class="grid-2" style="gap: 16px; margin-bottom: 16px;">',
            '    <div class="field" style="margin-bottom:0;">',
            '      <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Architectural Concept &amp; Narrative</label>',
            '      <textarea name="projects[' + pIdx + '][description]" rows="7" placeholder="Describe the design philosophy, space planning, materials, volumetric form, and concept..." style="font-size:13.5px; line-height:1.65; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"></textarea>',
            '    </div>',
            '    <div class="field" style="margin-bottom:0;">',
            '      <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Team / Collaborators</label>',
            '      <textarea name="projects[' + pIdx + '][project_team]" rows="7" placeholder="e.g. Ar. Anthony Nazareno&#10;Ar. Vladimir Banks&#10;IDr. Marielle Saguibo&#10;Engr. Juan Dela Cruz" style="font-size:13.5px; line-height:1.6; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"></textarea>',
            '    </div>',
            '  </div>',
            '  <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 14px; border: 1px solid var(--border-color, rgba(255,255,255,0.06));">',
            '    <div style="margin-bottom: 12px; padding: 10px; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.25); border-radius: 8px;">',
            '      <span style="font-size: 12px; font-weight: 700; color: var(--accent-primary, #f5b800); display: block; margin-bottom: 6px;">★ Cover Photo (Front Thumbnail on Directory Grid) *</span>',
            '      <input type="file" name="projects[' + pIdx + '][cover]" accept=".jpg,.jpeg,.png,.webp" required style="font-size: 12px;">',
            '    </div>',
            '    <div class="field" style="margin-bottom:0;">',
            '      <label style="font-size: 12px;">Upload Additional Photos (Up to 4 more files, max 5 total photos):</label>',
            '      <input type="file" name="projects[' + pIdx + '][photos][]" accept=".jpg,.jpeg,.png,.webp" multiple style="font-size: 12px;">',
            '    </div>',
            '  </div>',
            '</div>'
          ].join('');

          container.appendChild(div);
        });
      }
    })();
    </script>
  <?php endif; ?>

</div>

<script>
function previewCompanyLogo(input) {
  if (!input.files || !input.files[0]) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    var preview = document.getElementById('companyLogoPreview');
    var placeholder = document.getElementById('companyLogoPlaceholder');
    var imgEl = document.getElementById('companyLogoImg');
    if (!preview) return;
    preview.style.background = '#fff';
    if (placeholder) placeholder.style.display = 'none';
    if (!imgEl) {
      imgEl = document.createElement('img');
      imgEl.id = 'companyLogoImg';
      imgEl.style.cssText = 'width:100%;height:100%;object-fit:contain;';
      preview.appendChild(imgEl);
    }
    imgEl.src = e.target.result;
    imgEl.style.display = '';
  };
  reader.readAsDataURL(input.files[0]);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
