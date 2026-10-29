<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$payments = $pdo->query("
    SELECT u.name as member_name, u.id_number, 
           COALESCE(md.custom_title, d.title) as due_title,
           md.payment_type, p.installment_number, p.amount_paid,
           p.method, p.reference_number, p.status, r.receipt_number,
           p.submitted_at, p.verified_at
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    LEFT JOIN receipts r ON r.payment_id = p.id
    ORDER BY p.submitted_at DESC
")->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="uapqc_dues_report_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Member Name', 'PRC ID No.', 'Due', 'Payment Type', 'Installment #', 'Amount Paid', 'Method', 'Reference #', 'Status', 'Receipt #', 'Submitted', 'Verified']);

foreach ($payments as $p) {
    fputcsv($out, [
        $p['member_name'],
        $p['id_number'],
        $p['due_title'],
        ucfirst($p['payment_type'] ?? 'full'),
        $p['installment_number'] ?? '—',
        $p['amount_paid'],
        strtoupper($p['method']),
        $p['reference_number'],
        ucfirst($p['status']),
        $p['receipt_number'] ?? '',
        $p['submitted_at'],
        $p['verified_at'] ?? ''
    ]);
}
fclose($out);
exit;
