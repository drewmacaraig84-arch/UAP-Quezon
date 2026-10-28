<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve', 'reject'])) {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'member'");
        $stmt->execute([$new_status, $user_id]);

        if (function_exists('audit_log')) {
            audit_log('MEMBER_REGISTRATION_' . strtoupper($action), 'MEMBERS', "Registration $action for user ID #$user_id", $_SESSION['user_id']);
        }

        if (function_exists('set_flash')) {
            set_flash('success', 'Member registration ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully.');
        }
    }
    header('Location: approvals.php');
    exit;
}

$pending = $pdo->query("SELECT * FROM users WHERE role = 'member' AND status = 'pending' ORDER BY created_at ASC")->fetchAll();

$page_title = 'Pending Member Approvals • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">MEMBERSHIP VETTING</p>
    <h1>Member Registration Approvals</h1>
    <p class="page-subtitle">Review, verify professional PRC credentials, demographics, and approve new chapter member registrations.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('approvals', '', 14); ?> <span><?php echo count($pending); ?> Pending</span>
  </div>
</div>

<div class="card">
  <?php if (empty($pending)): ?>
    <div style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
      <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(16,185,129,0.1); color: #10b981; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
        <?php echo icon('check', '', 28); ?>
      </div>
      <strong style="display: block; font-size: 16px; color: var(--text-primary);">All registrations are reviewed!</strong>
      <p class="muted" style="margin-top: 4px; font-size: 13px;">There are no new pending member registration applications awaiting review.</p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Applicant &amp; PRC Details</th>
            <th>Demographics</th>
            <th>Contact &amp; Address</th>
            <th>Compliance</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $p): 
            $dob = $p['birth_date'] ? date('M d, Y', strtotime($p['birth_date'])) : null;
            $ageDisplay = $p['age'] ? $p['age'] . ' yrs' : ($dob ? (date_diff(date_create($p['birth_date']), date_create('today'))->y . ' yrs') : '—');
          ?>
            <tr>
              <td>
                <strong style="font-size: 14px; color: var(--text-primary); display: block;"><?php echo htmlspecialchars($p['name']); ?></strong>
                <div style="margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                  <span class="badge" style="font-size: 11px; background: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3);">
                    PRC #<?php echo htmlspecialchars($p['prc_number'] ?: $p['id_number']); ?>
                  </span>
                  <span class="muted" style="font-size: 11px;">Registered: <?php echo date('M d, Y', strtotime($p['created_at'])); ?></span>
                </div>
              </td>
              <td>
                <div style="font-size: 12.5px; line-height: 1.5;">
                  <div><span class="muted">Gender:</span> <strong><?php echo htmlspecialchars($p['gender'] ?: '—'); ?></strong></div>
                  <div><span class="muted">Age/DOB:</span> <strong><?php echo $ageDisplay; ?></strong> <?php if ($dob): ?><span class="muted">(<?php echo $dob; ?>)</span><?php endif; ?></div>
                  <div><span class="muted">Civil Status:</span> <strong><?php echo htmlspecialchars($p['civil_status'] ?: '—'); ?></strong></div>
                </div>
              </td>
              <td>
                <div style="font-size: 12.5px; line-height: 1.5;">
                  <div><span class="muted">Mobile:</span> <strong><?php echo htmlspecialchars($p['phone'] ?: '—'); ?></strong></div>
                  <div><span class="muted">Email:</span> <strong><?php echo htmlspecialchars($p['email'] ?: '—'); ?></strong></div>
                  <?php if (!empty($p['address'])): ?>
                    <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px; max-width: 250px;">
                      <?php echo htmlspecialchars($p['address']); ?>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <?php if (!empty($p['terms_accepted']) || !empty($p['privacy_accepted'])): ?>
                  <span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 11); ?> Policies Agreed
                  </span>
                  <?php if (!empty($p['terms_accepted_at'])): ?>
                    <div class="muted" style="font-size: 10.5px; margin-top: 2px;">
                      <?php echo date('M d, Y', strtotime($p['terms_accepted_at'])); ?>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted" style="font-size: 11.5px;">Standard</span>
                <?php endif; ?>
              </td>
              <td style="white-space: nowrap; text-align: right;">
                <form method="post" class="inline" style="display:inline-block;margin-right:4px;"
                      data-confirm="Approve registration for <?php echo htmlspecialchars($p['name']); ?>? Member will be granted portal access."
                      data-confirm-title="Approve Member Registration"
                      data-confirm-btn="Approve"
                      data-confirm-class="btn-success">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                  <input type="hidden" name="action" value="approve">
                  <button class="btn btn-sm btn-success" type="submit" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 12); ?> <span>Approve</span>
                  </button>
                </form>
                <form method="post" class="inline" style="display:inline-block;"
                      data-confirm="Reject registration for <?php echo htmlspecialchars($p['name']); ?>?"
                      data-confirm-title="Reject Member Registration"
                      data-confirm-btn="Reject"
                      data-confirm-class="btn-danger">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="btn btn-sm btn-danger" type="submit" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('x', '', 12); ?> <span>Reject</span>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
