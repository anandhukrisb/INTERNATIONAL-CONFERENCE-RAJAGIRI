<?php
/**
 * ------------------------------------------------------------
 * export_report.php – Self-Service Transaction CSV Report Generator
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Guard: require logged-in client session
if (empty($_SESSION['vortex_client_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ApiClient.php';

$clientId = (int)$_SESSION['vortex_client_id'];
$clientModel = new ApiClient();
$currentClient = $clientModel->getClientById($clientId);

if (!$currentClient || (int)$currentClient['is_active'] !== 1) {
    header('Location: login.php?error=inactive');
    exit;
}

// Sanitize query filters
$statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));
$modeFilter   = isset($_GET['mode']) && $_GET['mode'] !== '' ? (int)$_GET['mode'] : '';
$search       = trim($_GET['search'] ?? '');
$fromDate     = trim($_GET['from_date'] ?? '');
$toDate       = trim($_GET['to_date'] ?? '');

$filterParams = [
    'status'       => $statusFilter,
    'search'       => $search,
    'from_date'    => $fromDate,
    'to_date'      => $toDate,
    'limit'        => 5000,
    'offset'       => 0,
];

if ($modeFilter !== '') {
    $filterParams['is_test_mode'] = $modeFilter;
}

// Fetch transactions with tenant isolation
$data = $clientModel->getClientTransactions($clientId, $filterParams);
$transactions = $data['rows'] ?? [];

// Filename
$safeClientName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $currentClient['client_name'] ?? 'merchant');
$filename = "rpay_report_{$safeClientName}_client{$clientId}_" . date('Ymd_His') . ".csv";

// Headers for direct file download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Output UTF-8 Byte Order Mark (BOM) for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header row
fputcsv($output, [
    'Rpay Transaction ID',
    'Razorpay Order ID',
    'Razorpay Payment ID',
    'Customer Name',
    'Customer Email',
    'Customer Mobile',
    'Event / Purpose',
    'Amount (INR)',
    'Currency',
    'Environment Mode',
    'Status',
    'Error Message',
    'Created At',
    'Last Updated At'
]);

// Write data rows
foreach ($transactions as $tx) {
    $isTest = (int)($tx['is_test_mode'] ?? 0) === 1;
    fputcsv($output, [
        $tx['vortex_transaction_id'] ?? '',
        $tx['razorpay_order_id'] ?? '',
        $tx['razorpay_payment_id'] ?? '',
        $tx['customer_name'] ?? '',
        $tx['customer_email'] ?? '',
        $tx['customer_mobile'] ?? '',
        $tx['event_name'] ?? ('Event #' . ($tx['event_id'] ?? '')),
        number_format((float)($tx['amount'] ?? 0), 2, '.', ''),
        $tx['currency'] ?? 'INR',
        $isTest ? 'TEST' : 'LIVE',
        strtoupper($tx['status'] ?? 'PENDING'),
        $tx['error_message'] ?? '',
        $tx['created_at'] ?? '',
        $tx['updated_at'] ?? ''
    ]);
}

fclose($output);
exit;
