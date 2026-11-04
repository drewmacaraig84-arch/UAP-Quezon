<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_admin();

$fy = trim($_GET['fy'] ?? get_active_fiscal_year());
$eventKey = trim($_GET['event_key'] ?? $_GET['event'] ?? '');
if (empty($eventKey)) {
    $eventKey = get_default_event_key($pdo);
}

$selectedEventTitle = get_event_title_by_key($eventKey, $pdo);

// Find event date if available
$eventDate = null;
if (str_starts_with($eventKey, 'ce_')) {
    try {
        $ceId = (int)substr($eventKey, 3);
        $stmt = $pdo->prepare("SELECT * FROM chapter_events WHERE id = ?");
        $stmt->execute([$ceId]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($ev['event_date'])) {
            $eventDate = $ev['event_date'];
        }
    } catch (Throwable $e) {}
}

// Fetch all attendance logs for this event
$logs = get_event_attendance_logs($pdo, $eventKey, $fy, 2000);
// Chronological sign-in order (#1 to #N)
$logs = array_reverse($logs);
$totalAttendees = count($logs);

$displayDate = !empty($eventDate) ? date('F j, Y', strtotime($eventDate)) : date('F j, Y');
$currentDate = date('F j, Y');

$adminUser = function_exists('current_user') ? current_user($pdo) : null;
$adminName = $adminUser['name'] ?? ($_SESSION['name'] ?? ($_SESSION['user_name'] ?? 'Chapter Administrator'));
$pdfFilename = "Attendance_Roster_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $selectedEventTitle) . "_" . date('Ymd');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($selectedEventTitle); ?> • Attendance Roster PDF</title>
    <link rel="icon" type="image/jpeg" href="<?php echo BASE_URL; ?>/public/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    
    <style>
        *, *:before, *:after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #0b1120;
            color: #1e293b;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5;
            padding: 24px 16px 60px;
        }

        /* SCREEN-ONLY TOOLBAR */
        .pdf-toolbar {
            max-width: 860px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #131d33;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            gap: 12px;
            flex-wrap: wrap;
        }

        .pdf-toolbar-title {
            color: #ffffff;
            font-size: 0.92rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pdf-btn-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pdf-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }

        .pdf-btn-primary {
            background: #f59e0b;
            color: #0b1120;
        }

        .pdf-btn-primary:hover {
            background: #fbbf24;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
        }

        .pdf-btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .pdf-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #f59e0b;
        }

        .pdf-btn-danger {
            background: rgba(239, 68, 68, 0.18);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.35);
        }

        .pdf-btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* PRINTABLE SHEET CONTAINER (Strict Wireframe Format) */
        .pdf-sheet-wrapper {
            max-width: 860px;
            margin: 0 auto;
        }

        .pdf-sheet {
            background: #ffffff;
            color: #0f172a;
            width: 100%;
            min-height: 1080px;
            padding: 48px 48px 54px;
            border-radius: 6px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
            position: relative;
            box-sizing: border-box;
        }

        /* Top Right Date */
        .pdf-top-date-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 24px;
        }

        .pdf-top-date {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: 0.02em;
        }

        /* Center Event Name */
        .pdf-event-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 0 20px;
        }

        .pdf-chapter-eyebrow {
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
            margin-bottom: 6px;
        }

        .pdf-event-title {
            font-family: 'Space Grotesk', 'Plus Jakarta Sans', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin: 0 0 6px 0;
            line-height: 1.25;
        }

        .pdf-event-sub {
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        /* Table Box (Matching Wireframe) */
        .pdf-table-container {
            border: 2px solid #0f172a;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 28px;
        }

        .pdf-attendance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            text-align: left;
        }

        .pdf-attendance-table thead th {
            background: #f1f5f9;
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 12px 14px;
            border-bottom: 2px solid #0f172a;
            border-right: 1px solid #cbd5e1;
        }

        .pdf-attendance-table thead th:last-child {
            border-right: none;
        }

        .pdf-attendance-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }

        .pdf-attendance-table tbody td:last-child {
            border-right: none;
        }

        .pdf-attendance-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .pdf-attendance-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Column Specific Alignments */
        .col-no {
            width: 60px;
            text-align: center !important;
            font-weight: 700;
            color: #64748b;
        }

        .col-name {
            font-weight: 700;
            color: #0f172a;
        }

        .col-date {
            width: 140px;
            text-align: center !important;
            color: #334155;
            font-weight: 500;
        }

        .col-time {
            width: 130px;
            text-align: center !important;
            color: #0f172a;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        /* Document Footer / Signatures */
        .pdf-footer-meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
            font-size: 11.5px;
            color: #64748b;
        }

        .pdf-signature-block {
            text-align: center;
            width: 220px;
        }

        .pdf-signature-line {
            border-bottom: 1.5px solid #0f172a;
            margin-bottom: 6px;
            height: 32px;
        }

        .pdf-signature-name {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
        }

        .pdf-signature-role {
            font-size: 10px;
            color: #64748b;
        }

        /* PRINT MEDIA STYLES (Clean A4 / Letter Output) */
        @media print {
            @page {
                size: portrait;
                margin: 12mm 15mm;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .no-print, .pdf-toolbar {
                display: none !important;
            }

            .pdf-sheet-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
            }

            .pdf-sheet {
                box-shadow: none !important;
                padding: 0 !important;
                min-height: auto !important;
                border-radius: 0 !important;
            }

            .pdf-table-container {
                border-color: #000000 !important;
            }

            .pdf-attendance-table thead th {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border-bottom: 2px solid #000000 !important;
            }

            .pdf-attendance-table tbody tr {
                page-break-inside: avoid;
            }

            .pdf-footer-meta {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN-ONLY CONTROL TOOLBAR -->
    <div class="pdf-toolbar no-print">
        <div class="pdf-toolbar-title">
            <span>📄</span>
            <span>Live Attendance Roster Export (PDF)</span>
        </div>
        <div class="pdf-btn-group">
            <button type="button" onclick="downloadAsPdf()" class="pdf-btn pdf-btn-danger" title="Download direct PDF file">
                <span>⬇️</span> <span>Download PDF</span>
            </button>
            <button type="button" onclick="window.print()" class="pdf-btn pdf-btn-primary" title="Open Print / Save as PDF dialog">
                <span>🖨️</span> <span>Print / Save as PDF</span>
            </button>
            <a href="scan_attendance.php?event_key=<?php echo urlencode($eventKey); ?>" class="pdf-btn pdf-btn-secondary">
                <span>&larr;</span> <span>Back to Scanner</span>
            </a>
        </div>
    </div>

    <!-- MAIN PRINTABLE PDF DOCUMENT SHEET -->
    <div class="pdf-sheet-wrapper">
        <div class="pdf-sheet" id="attendancePdfSheet">
            
            <!-- TOP RIGHT: Date -->
            <div class="pdf-top-date-row">
                <div class="pdf-top-date">
                    Date: <?php echo htmlspecialchars($displayDate); ?>
                </div>
            </div>

            <!-- TOP CENTER: Name of Events -->
            <div class="pdf-event-header">
                <div class="pdf-chapter-eyebrow">
                    United Architects of the Philippines &bull; Quezon Province Chapter (IAPOA Chapter 121)
                </div>
                <h1 class="pdf-event-title">
                    <?php echo htmlspecialchars($selectedEventTitle); ?>
                </h1>
                <div class="pdf-event-sub">
                    Official Attendance Record &bull; Fiscal Year <?php echo htmlspecialchars($fy); ?>
                </div>
            </div>

            <!-- TABLE CONTAINER (no. | name | date | time) -->
            <div class="pdf-table-container">
                <table class="pdf-attendance-table">
                    <thead>
                        <tr>
                            <th class="col-no">no.</th>
                            <th class="col-name">name</th>
                            <th class="col-date">date</th>
                            <th class="col-time">time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 36px 16px; color: #64748b; font-style: italic;">
                                    No attendee check-ins recorded for this event yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $idx = 1; foreach ($logs as $row): 
                                $rowTimestamp = !empty($row['scanned_at']) ? strtotime($row['scanned_at']) : time();
                                $rowDate = date('F j, Y', $rowTimestamp);
                                $rowTime = date('h:i:s A', $rowTimestamp);
                            ?>
                                <tr>
                                    <td class="col-no"><?php echo $idx++; ?></td>
                                    <td class="col-name">
                                        <?php echo htmlspecialchars($row['name']); ?>
                                        <?php if (!empty($row['prc_number']) && $row['prc_number'] !== '—'): ?>
                                            <span style="font-size: 10.5px; color: #64748b; font-weight: normal; margin-left: 6px;">
                                                (PRC: <?php echo htmlspecialchars($row['prc_number']); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-date"><?php echo htmlspecialchars($rowDate); ?></td>
                                    <td class="col-time"><?php echo htmlspecialchars($rowTime); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- FOOTER SUMMARY & SIGNATURE BLOCK -->
            <div class="pdf-footer-meta">
                <div>
                    <strong>Total Verified Attendees:</strong> <?php echo $totalAttendees; ?><br>
                    <span style="font-size: 10.5px;">Exported on: <?php echo date('F j, Y - h:i A'); ?></span>
                </div>

                <div class="pdf-signature-block">
                    <div class="pdf-signature-line"></div>
                    <div class="pdf-signature-name"><?php echo htmlspecialchars($adminName); ?></div>
                    <div class="pdf-signature-role">Verified Attendance Administrator</div>
                </div>
            </div>

        </div>
    </div>

    <!-- html2pdf.js Bundle for direct client-side PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function downloadAsPdf() {
        const element = document.getElementById('attendancePdfSheet');
        const filename = '<?php echo addslashes($pdfFilename); ?>.pdf';

        if (typeof html2pdf !== 'undefined') {
            const opt = {
                margin:       [10, 10, 12, 10],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        } else {
            window.print();
        }
    }

    // Auto-trigger if ?print=1 or ?download=1 passed in URL
    window.addEventListener('DOMContentLoaded', function() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('download') === '1') {
            setTimeout(downloadAsPdf, 600);
        } else if (params.get('print') === '1') {
            setTimeout(function() { window.print(); }, 400);
        }
    });
    </script>
</body>
</html>

