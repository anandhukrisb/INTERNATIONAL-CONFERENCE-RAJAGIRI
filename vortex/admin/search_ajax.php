<?php
/**
 * ------------------------------------------------------------
 * search_ajax.php – Instant Search Autocomplete Endpoint
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Authenticated JSON endpoint for live Omnisearch dropdown.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/GlobalSearch.php';
require_once __DIR__ . '/../classes/RBAC.php';

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Admin session required.'
    ]);
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));

if (strlen($query) < 2) {
    echo json_encode([
        'success'           => true,
        'query'             => $query,
        'total_matches'     => 0,
        'execution_time_ms' => 0,
        'exact_match'       => null,
        'transactions'      => [],
        'clients'           => [],
        'events'            => []
    ]);
    exit;
}

try {
    $searchEngine = new GlobalSearch();
    $result = $searchEngine->search($query, [
        'category' => 'all',
        'limit'    => 5
    ]);

    // Format results for quick presentation
    $formattedTx = [];
    foreach ($result['categories']['transactions']['items'] as $tx) {
        $formattedTx[] = [
            'id'              => (int)$tx['id'],
            'vortex_id'       => $tx['vortex_transaction_id'],
            'amount_fmt'      => ($tx['currency'] ?? 'INR') . ' ' . number_format((float)$tx['amount'], 2),
            'status'          => $tx['status'],
            'customer_email'  => $tx['customer_email'],
            'customer_mobile' => $tx['customer_mobile'],
            'event_name'      => $tx['event_name'] ?? 'General',
            'client_name'     => $tx['client_name'] ?? 'Default Client',
            'created_at_fmt'  => date('M d, H:i', strtotime($tx['created_at'])),
            'url'             => 'payments/payment_details.php?vortex_transaction_id=' . urlencode($tx['vortex_transaction_id'])
        ];
    }

    $formattedClients = [];
    foreach ($result['categories']['clients']['items'] as $c) {
        $formattedClients[] = [
            'id'          => (int)$c['id'],
            'client_name' => $c['client_name'],
            'email'       => $c['email'] ?? '–',
            'is_active'   => (int)$c['is_active'],
            'url'         => 'api_clients/index.php?q=' . urlencode($c['client_name'])
        ];
    }

    $formattedEvents = [];
    foreach ($result['categories']['events']['items'] as $e) {
        $formattedEvents[] = [
            'id'         => (int)$e['id'],
            'event_id'   => $e['event_id'],
            'event_name' => $e['event_name'],
            'department' => $e['department'],
            'is_active'  => (int)$e['is_active'],
            'url'        => 'events/index.php?q=' . urlencode($e['event_name'])
        ];
    }

    echo json_encode([
        'success'           => true,
        'query'             => $query,
        'total_matches'     => $result['total_matches'],
        'execution_time_ms' => $result['execution_time_ms'],
        'exact_match'       => $result['exact_match'],
        'transactions'      => $formattedTx,
        'clients'           => $formattedClients,
        'events'            => $formattedEvents
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed: ' . $e->getMessage()
    ]);
}
