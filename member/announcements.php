<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulletin_helper.php';
require_member();

$currentUid = current_user_id();
$filterCategory = trim($_GET['category'] ?? 'All');
$search = trim($_GET['search'] ?? '');

$categories = get_bulletin_categories();
$bulletins = get_member_bulletins($pdo, 50, $filterCategory, $search);
$pinnedNotice = get_pinned_bulletin($pdo);

$page_title = 'Chapter Bulletins & Memorandums • UAP Quezon';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">OFFICIAL CHAPTER CIRCULARS</p>
    <h1>Chapter Bulletins &amp; Memorandums</h1>
    <p class="page-subtitle">Official board resolutions, committee advisories, and administrative issuances for chapter architects.</p>
  </div>
</div>

<!-- PINNED NOTICE ALERT (IF ACTIVE) -->
<?php if ($pinnedNotice && empty($search) && ($filterCategory === 'All' || $filterCategory === $pinnedNotice['category'])): ?>
  <div class="card" style="margin-bottom: 24px; padding: 20px 24px; border: 1px solid rgba(245,158,11,0.4); background: linear-gradient(135deg, rgba(245,158,11,0.08) 0%, rgba(21,128,61,0.05) 100%); border-left: 5px solid #f59e0b;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
      <div style="flex: 1; min-width: 260px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
          <span class="badge" style="background: rgba(245,158,11,0.2); color: #f59e0b; border: 1px solid rgba(245,158,11,0.35); font-size: 10px; font-weight: 800; padding: 2px 8px;">PRIORITY NOTICE</span>
          <span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-secondary); font-size: 11px;"><?php echo htmlspecialchars($pinnedNotice['category']); ?></span>
          <span class="muted" style="font-size: 11.5px;"><?php echo htmlspecialchars($pinnedNotice['date_display'] ?: date('M d, Y', strtotime($pinnedNotice['date_posted']))); ?></span>
        </div>
        <h2 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0 0 8px 0;">
          <?php echo htmlspecialchars($pinnedNotice['title']); ?>
        </h2>
        <?php if (!empty($pinnedNotice['summary'])): ?>
          <p style="margin: 0; font-size: 13.5px; color: var(--text-secondary); line-height: 1.5;">
            <?php echo htmlspecialchars($pinnedNotice['summary']); ?>
          </p>
        <?php endif; ?>
      </div>
      <div style="display: flex; gap: 8px; align-items: center;">
        <button type="button" class="btn btn-primary" onclick='openReaderModal(<?php echo json_encode($pinnedNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="font-size: 12px; padding: 8px 16px; font-weight: 700;">
          Read Full Memorandum
        </button>
        <?php if (!empty($pinnedNotice['pdf_attachment'])): ?>
          <a href="<?php echo BASE_URL . '/' . htmlspecialchars($pinnedNotice['pdf_attachment']); ?>" target="_blank" class="btn btn-secondary" style="font-size: 12px; padding: 8px 14px;">
            Download PDF
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- SEARCH & CATEGORY FILTER TABS -->
<div class="card" style="margin-bottom: 24px; padding: 18px 20px;">
  <!-- Search input -->
  <form method="get" style="display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap;">
    <input type="hidden" name="category" value="<?php echo htmlspecialchars($filterCategory); ?>">
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search announcements by keywords, topic, or circular title..." style="flex: 1; min-width: 220px; font-size: 13px;">
    <button type="submit" class="btn btn-primary" style="font-size: 12px; padding: 7px 16px;">Search</button>
    <?php if ($search !== ''): ?>
      <a href="announcements.php?category=<?php echo urlencode($filterCategory); ?>" class="btn btn-outline" style="font-size: 12px; padding: 7px 12px;">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Clean Category Tabs (No emojis) -->
  <div style="display: flex; gap: 8px; flex-wrap: wrap; border-top: 1px solid var(--border-color); padding-top: 14px;">
    <a href="announcements.php?category=All<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
       class="badge-pill" 
       style="text-decoration: none; padding: 6px 14px; font-size: 12px; font-weight: 700; cursor: pointer; <?php echo $filterCategory === 'All' ? 'background: var(--accent-primary); color: #000;' : 'background: rgba(255,255,255,0.06); color: var(--text-secondary);'; ?>">
      All Categories
    </a>
    <?php foreach ($categories as $cat): ?>
      <a href="announcements.php?category=<?php echo urlencode($cat); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
         class="badge-pill" 
         style="text-decoration: none; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; <?php echo $filterCategory === $cat ? 'background: var(--accent-primary); color: #000;' : 'background: rgba(255,255,255,0.06); color: var(--text-secondary);'; ?>">
        <?php echo htmlspecialchars($cat); ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- BULLETINS FEED GRID -->
<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
      <h2 style="font-size: 16px; margin: 0; color: var(--text-primary);">
        <?php echo $filterCategory === 'All' ? 'Recent Chapter Issuances' : htmlspecialchars($filterCategory); ?>
      </h2>
      <span class="muted" style="font-size: 12px;">Showing <?php echo count($bulletins); ?> active bulletins</span>
    </div>
  </div>

  <?php if (empty($bulletins)): ?>
    <div style="text-align: center; padding: 48px; color: var(--text-secondary);">
      <p style="margin: 0; font-size: 14px;">No bulletins found for the selected category or search filter.</p>
      <a href="announcements.php" class="btn btn-sm btn-secondary" style="margin-top: 12px;">View All Bulletins</a>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
      <?php foreach ($bulletins as $b): ?>
        <div class="card" style="padding: 20px; border: 1px solid var(--border-color); background: rgba(255,255,255,0.02); display: flex; flex-direction: column; justify-content: space-between; position: relative;">
          <div>
            <!-- Header Badges -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 10px;">
              <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-primary); font-size: 10.5px; padding: 3px 8px; border: 1px solid var(--border-color);">
                <?php echo htmlspecialchars($b['category']); ?>
              </span>
              <?php if ($b['is_pinned']): ?>
                <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; font-size: 10px; padding: 2px 6px; font-weight: 700;">PINNED</span>
              <?php endif; ?>
            </div>

            <!-- Title -->
            <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 8px 0; line-height: 1.4;">
              <?php echo htmlspecialchars($b['title']); ?>
            </h3>

            <!-- Date -->
            <div class="muted" style="font-size: 11.5px; margin-bottom: 12px;">
              Date Posted: <?php echo htmlspecialchars($b['date_display'] ?: date('M d, Y', strtotime($b['date_posted']))); ?>
            </div>

            <!-- Summary -->
            <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; margin: 0 0 14px 0;">
              <?php echo htmlspecialchars($b['summary'] ?: substr(strip_tags($b['content'] ?? ''), 0, 140) . '...'); ?>
            </p>
          </div>

          <!-- Attachments & Read Action -->
          <div style="border-top: 1px solid var(--border-color); padding-top: 14px; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div style="display: flex; gap: 6px;">
              <?php if (!empty($b['pdf_attachment'])): ?>
                <span class="badge" style="background: rgba(239,68,68,0.12); color: #f87171; font-size: 10px; padding: 2px 6px; border: 1px solid rgba(239,68,68,0.25);">PDF Included</span>
              <?php endif; ?>
              <?php if (!empty($b['image_path'])): ?>
                <span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-secondary); font-size: 10px; padding: 2px 6px;">Poster</span>
              <?php endif; ?>
            </div>

            <button type="button" class="btn btn-sm btn-primary" onclick='openReaderModal(<?php echo json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="font-size: 11.5px; padding: 5px 12px; font-weight: 700;">
              Read Details &rarr;
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- BULLETIN READER MODAL -->
<!-- ============================================================== -->
<div id="readerModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
  <div class="card" style="max-width: 720px; width: 100%; max-height: 90vh; overflow-y: auto; background: var(--card-bg); border: 1px solid var(--border-color); padding: 28px; box-shadow: 0 12px 48px rgba(0,0,0,0.6);">
    <!-- Modal Header -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
      <div>
        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
          <span class="badge" id="modalCategoryBadge" style="background: rgba(255,255,255,0.06); color: var(--text-primary); font-size: 11px; border: 1px solid var(--border-color); padding: 3px 8px;"></span>
          <span class="muted" id="modalDatePosted" style="font-size: 12px;"></span>
        </div>
        <h2 id="modalTitle" style="font-size: 20px; font-weight: 800; margin: 0; color: var(--text-primary); line-height: 1.3;"></h2>
      </div>
      <button type="button" class="btn btn-sm btn-secondary" onclick="closeReaderModal()" style="font-size: 14px; padding: 2px 8px;">&times;</button>
    </div>

    <!-- Modal Content -->
    <div id="modalBody">
      <!-- Image Poster (If any) -->
      <div id="modalPosterContainer" style="display: none; margin-bottom: 20px; text-align: center;">
        <img id="modalPosterImg" src="" alt="Announcement Poster" style="max-width: 100%; max-height: 400px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain;">
      </div>

      <!-- Full Text Content -->
      <div id="modalContentText" style="font-size: 14px; line-height: 1.7; color: var(--text-primary); white-space: pre-line; margin-bottom: 24px;"></div>

      <!-- PDF Download Button (If any) -->
      <div id="modalPdfContainer" style="display: none; padding: 16px; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid var(--border-color); align-items: center; justify-content: space-between; gap: 12px;">
        <div>
          <strong style="color: var(--text-primary); font-size: 13.5px; display: block;">Official Document Attached</strong>
          <span class="muted" style="font-size: 12px;">Download and review the certified chapter issuance in PDF format.</span>
        </div>
        <a id="modalPdfLink" href="" target="_blank" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px; font-weight: 700; white-space: nowrap;">
          Download PDF Circular
        </a>
      </div>
    </div>

    <!-- Modal Footer -->
    <div style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 24px; text-align: right;">
      <button type="button" class="btn btn-secondary" onclick="closeReaderModal()">Close</button>
    </div>
  </div>
</div>

<script>
function openReaderModal(item) {
    document.getElementById('modalTitle').textContent = item.title || '';
    document.getElementById('modalCategoryBadge').textContent = item.category || 'General';
    document.getElementById('modalDatePosted').textContent = 'Posted: ' + (item.date_display || item.date_posted || '');

    // Full text
    const content = item.content || item.summary || 'No further details provided.';
    document.getElementById('modalContentText').textContent = content;

    // Image Poster
    const posterContainer = document.getElementById('modalPosterContainer');
    const posterImg = document.getElementById('modalPosterImg');
    if (item.image_path) {
        posterImg.src = '<?php echo BASE_URL; ?>/' + item.image_path.replace(/^\/+/, '');
        posterContainer.style.display = 'block';
    } else {
        posterContainer.style.display = 'none';
    }

    // PDF Circular
    const pdfContainer = document.getElementById('modalPdfContainer');
    const pdfLink = document.getElementById('modalPdfLink');
    if (item.pdf_attachment) {
        pdfLink.href = '<?php echo BASE_URL; ?>/' + item.pdf_attachment.replace(/^\/+/, '');
        pdfContainer.style.display = 'flex';
    } else {
        pdfContainer.style.display = 'none';
    }

    document.getElementById('readerModal').style.display = 'flex';
}

function closeReaderModal() {
    document.getElementById('readerModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('readerModal');
    if (e.target === modal) {
        closeReaderModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
