<?php
/**
 * ------------------------------------------------------------
 * search.php – Global Omnisearch Results Dashboard
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Multi-entity global search across transactions,
 *           merchants, events, and identifiers.
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/GlobalSearch.php';
require_once __DIR__ . '/../classes/RBAC.php';
require_once __DIR__ . '/../classes/Logger.php';

// Auth Guard
if (empty($_SESSION['vortex_admin_id'])) {
    header('Location: login.php');
    exit;
}

$query    = trim((string)($_GET['q'] ?? $_GET['query'] ?? $_GET['search'] ?? ''));
$category = strtolower(trim((string)($_GET['category'] ?? 'all')));
$status   = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
$fromDate = trim((string)($_GET['from_date'] ?? ''));
$toDate   = trim((string)($_GET['to_date'] ?? ''));

$searchEngine = new GlobalSearch();
$searchResult = null;

if ($query !== '') {
    $searchResult = $searchEngine->search($query, [
        'category'  => $category,
        'status'    => $status,
        'from_date' => $fromDate,
        'to_date'   => $toDate,
        'limit'     => 50
    ]);
}

$pageTitle = ($query !== '') ? "Search: " . htmlspecialchars($query) . " - Vortex Admin" : "Global Search - Vortex Admin";
$activeNav = "search";
require_once __DIR__ . '/includes/header.php';

/**
 * Helper to highlight matched query in string
 */
function highlightMatch(?string $text, string $query): string
{
    if ($text === null || $text === '') return '–';
    if ($query === '') return htmlspecialchars($text);

    $escapedQuery = preg_quote($query, '/');
    return preg_replace_callback(
        "/({$escapedQuery})/i",
        fn($m) => '<mark style="background: #fef08a; color: #854d0e; padding: 1px 3px; border-radius: 3px; font-weight: 600;">' . htmlspecialchars($m[0]) . '</mark>',
        htmlspecialchars($text)
    );
}
?>

<style>
.search-page-header {
    margin-bottom: 24px;
}
.search-bar-card {
    background: #ffffff;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.03);
    margin-bottom: 24px;
}
.search-input-row {
    display: flex;
    gap: 12px;
    align-items: center;
}
.search-hero-input {
    flex: 1;
    font-size: 1.05rem;
    padding: 12px 18px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    transition: all 0.2s ease;
}
.search-hero-input:focus {
    border-color: var(--primary-blue);
    outline: none;
    box-shadow: 0 0 0 4px rgba(37,99,235,0.12);
}
.tab-pills-bar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}
.tab-pill {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    color: #64748b;
    background: #f1f5f9;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.tab-pill:hover {
    background: #e2e8f0;
    color: var(--text-dark);
}
.tab-pill.active {
    background: var(--primary-blue);
    color: #ffffff;
}
.tab-pill-count {
    background: rgba(255,255,255,0.25);
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 0.74rem;
}
.tab-pill:not(.active) .tab-pill-count {
    background: #e2e8f0;
    color: #475569;
}
.spotlight-card {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border-radius: 12px;
    padding: 20px 24px;
    color: #f8fafc;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.15);
}
.entity-badge {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 4px;
}
.entity-badge-tx { background: #dbeafe; color: #1e40af; }
.entity-badge-client { background: #fef3c7; color: #92400e; }
.entity-badge-event { background: #dcfce7; color: #166534; }
</style>

<div class="search-page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 1.4rem; font-weight: 700; color: var(--text-dark); margin: 0; display: flex; align-items: center; gap: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--primary-blue);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                Gateway Global Search
            </h1>
            <div style="font-size: 0.85rem; color: var(--text-gray); margin-top: 3px;">
                Find any transaction, customer, merchant integration, or event across the entire payment gateway.
            </div>
        </div>
        <div style="font-size: 0.8rem; color: #64748b;">
            Shortcut: Press <kbd style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 2px 6px; border-radius: 4px; font-family: monospace;">/</kbd> or <kbd style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 2px 6px; border-radius: 4px; font-family: monospace;">Ctrl+K</kbd> to search anywhere
        </div>
    </div>
</div>

<!-- Search Input & Filter Card -->
<div class="search-bar-card">
    <form action="search.php" method="GET" id="globalSearchForm">
        <div class="search-input-row">
            <input type="text" name="q" id="searchHeroInput" class="search-hero-input"
                   placeholder="Search by Vortex ID (VTX_...), Razorpay ID (pay_...), Customer Email, Mobile, or Merchant..."
                   value="<?= htmlspecialchars($query) ?>" autofocus required>
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: 600; font-size: 0.95rem;">
                🔍 Search
            </button>
            <?php if ($query !== ''): ?>
                <a href="search.php" class="btn btn-secondary" style="padding: 12px 18px;" title="Clear Search">✕</a>
            <?php endif; ?>
        </div>

        <!-- Filter Controls -->
        <div style="display: flex; gap: 12px; margin-top: 14px; flex-wrap: wrap; align-items: center;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Status:</span>
                <select name="status" class="form-control" style="font-size: 0.82rem; padding: 4px 8px; width: 130px;" onchange="this.form.submit()">
                    <option value="ALL" <?= $status === 'ALL' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="SUCCESS" <?= $status === 'SUCCESS' ? 'selected' : '' ?>>Success Only</option>
                    <option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                    <option value="FAILED" <?= $status === 'FAILED' ? 'selected' : '' ?>>Failed</option>
                    <option value="REFUNDED" <?= $status === 'REFUNDED' ? 'selected' : '' ?>>Refunded</option>
                    <option value="DISPUTED" <?= $status === 'DISPUTED' ? 'selected' : '' ?>>Disputed</option>
                </select>
            </div>

            <div style="display: flex; align-items: center; gap: 6px;">
                <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Date:</span>
                <input type="date" name="from_date" class="form-control" style="font-size: 0.82rem; padding: 4px 8px; width: 135px;" value="<?= htmlspecialchars($fromDate) ?>" placeholder="From">
                <span style="color: #94a3b8;">–</span>
                <input type="date" name="to_date" class="form-control" style="font-size: 0.82rem; padding: 4px 8px; width: 135px;" value="<?= htmlspecialchars($toDate) ?>" placeholder="To">
                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 10px;">Apply</button>
            </div>
        </div>

        <?php if ($searchResult): ?>
            <!-- Category Tab Pills -->
            <?php
            $txCount     = $searchResult['categories']['transactions']['count'];
            $clientCount = $searchResult['categories']['clients']['count'];
            $eventCount  = $searchResult['categories']['events']['count'];
            $totalCount  = $searchResult['total_matches'];

            $buildTabUrl = function($cat) use ($query, $status, $fromDate, $toDate) {
                return 'search.php?' . http_build_query([
                    'q'         => $query,
                    'category'  => $cat,
                    'status'    => $status,
                    'from_date' => $fromDate,
                    'to_date'   => $toDate
                ]);
            };
            ?>
            <div class="tab-pills-bar">
                <a href="<?= htmlspecialchars($buildTabUrl('all')) ?>" class="tab-pill <?= $category === 'all' ? 'active' : '' ?>">
                    <span>🌐 All Results</span>
                    <span class="tab-pill-count"><?= $totalCount ?></span>
                </a>
                <a href="<?= htmlspecialchars($buildTabUrl('transactions')) ?>" class="tab-pill <?= $category === 'transactions' ? 'active' : '' ?>">
                    <span>💳 Transactions</span>
                    <span class="tab-pill-count"><?= $txCount ?></span>
                </a>
                <a href="<?= htmlspecialchars($buildTabUrl('clients')) ?>" class="tab-pill <?= $category === 'clients' ? 'active' : '' ?>">
                    <span>🔌 Merchants</span>
                    <span class="tab-pill-count"><?= $clientCount ?></span>
                </a>
                <a href="<?= htmlspecialchars($buildTabUrl('events')) ?>" class="tab-pill <?= $category === 'events' ? 'active' : '' ?>">
                    <span>🎟️ Events</span>
                    <span class="tab-pill-count"><?= $eventCount ?></span>
                </a>
                <div style="margin-left: auto; font-size: 0.78rem; color: #94a3b8; display: flex; align-items: center;">
                    ⚡ Search executed in <?= $searchResult['execution_time_ms'] ?> ms
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($searchResult): ?>
    <!-- 1. EXACT MATCH SPOTLIGHT (If single exact match found) -->
    <?php if (!empty($searchResult['exact_match'])): ?>
        <?php $em = $searchResult['exact_match']; ?>
        <div class="spotlight-card">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="background: rgba(37,99,235,0.2); border: 1px solid #3b82f6; width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                    🎯
                </div>
                <div>
                    <div style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 700;">
                        Exact Match Found (<?= ucfirst($em['type']) ?>)
                    </div>
                    <div style="font-size: 1.15rem; font-weight: 700; color: #f8fafc; font-family: monospace;">
                        <?= htmlspecialchars($em['title']) ?>
                    </div>
                    <?php if ($em['type'] === 'transaction'): ?>
                        <div style="font-size: 0.82rem; color: #cbd5e1; margin-top: 2px;">
                            Customer: <?= htmlspecialchars($em['data']['customer_email']) ?> &bull;
                            Amount: <?= htmlspecialchars(($em['data']['currency'] ?? 'INR') . ' ' . number_format((float)$em['data']['amount'], 2)) ?> &bull;
                            Status: <strong style="color: #86efac;"><?= htmlspecialchars($em['data']['status']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= htmlspecialchars($em['url']) ?>" class="btn btn-primary" style="padding: 9px 18px; font-weight: 600;">
                Inspect Details &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- 2. TRANSACTIONS RESULTS -->
    <?php if ($category === 'all' || $category === 'transactions'): ?>
        <?php $txItems = $searchResult['categories']['transactions']['items']; ?>
        <div class="section-card" style="margin-bottom: 24px;">
            <div class="section-header" style="background: #fafbfc; border-bottom: 1px solid var(--border-color); padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="entity-badge entity-badge-tx">Transactions</span>
                    <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                        Matching Payments &amp; Orders (<?= count($txItems) ?>)
                    </h3>
                </div>
                <?php if (count($txItems) > 0): ?>
                    <a href="../payments/list_payments.php" style="font-size: 0.8rem; color: var(--primary-blue); font-weight: 600; text-decoration: none;">
                        View All in Ledger &rarr;
                    </a>
                <?php endif; ?>
            </div>
            <div class="section-body" style="padding: 0;">
                <?php if (empty($txItems)): ?>
                    <div style="text-align: center; padding: 28px; color: var(--text-gray); font-size: 0.88rem;">
                        No transactions matched your search criteria.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Vortex Tx ID</th>
                                    <th>Customer</th>
                                    <th>Razorpay Payment / Order</th>
                                    <th>Event &amp; Merchant</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($txItems as $tx): ?>
                                    <tr>
                                        <td>
                                            <div class="copy-wrapper" title="<?= htmlspecialchars($tx['vortex_transaction_id']) ?>">
                                                <span class="font-mono" style="font-weight: 600; font-size: 0.85rem; max-width: 140px; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle;">
                                                    <?= highlightMatch($tx['vortex_transaction_id'], $query) ?>
                                                </span>
                                                <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($tx['vortex_transaction_id'], ENT_QUOTES) ?>', this)">📋</button>
                                            </div>
                                            <?php if ((int)$tx['is_test_mode'] === 1): ?>
                                                <span class="badge badge-warning" style="font-size: 0.65rem; padding: 1px 4px; margin-top: 2px;">TEST</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-dark); font-size: 0.88rem;">
                                                <?= highlightMatch($tx['customer_email'], $query) ?>
                                            </div>
                                            <div style="font-size: 0.76rem; color: var(--text-gray); margin-top: 2px;">
                                                📞 <?= highlightMatch($tx['customer_mobile'], $query) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($tx['razorpay_payment_id'])): ?>
                                                <div class="font-mono" style="font-size: 0.78rem; color: #475569;">
                                                    Pay: <?= highlightMatch($tx['razorpay_payment_id'], $query) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($tx['razorpay_order_id'])): ?>
                                                <div class="font-mono" style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">
                                                    Ord: <?= highlightMatch($tx['razorpay_order_id'], $query) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-dark); font-size: 0.85rem;">
                                                <?= highlightMatch($tx['event_name'] ?? 'General Event', $query) ?>
                                            </div>
                                            <div style="font-size: 0.76rem; color: var(--text-gray); margin-top: 2px;">
                                                Merchant: <?= highlightMatch($tx['client_name'] ?? 'Default', $query) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; font-size: 0.92rem; color: var(--text-dark);">
                                                <?= htmlspecialchars($tx['currency'] ?? 'INR') ?> <?= number_format((float)$tx['amount'], 2) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = match ($tx['status']) {
                                                'SUCCESS'  => 'badge-success',
                                                'PENDING'  => 'badge-warning',
                                                'FAILED'   => 'badge-danger',
                                                'REFUNDED' => 'badge-info',
                                                default    => 'badge-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($tx['status']) ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.78rem; color: var(--text-gray); white-space: nowrap;">
                                            <?= date('M d, Y H:i', strtotime($tx['created_at'])) ?>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <a href="payments/payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm">
                                                Ledger ↗
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. MERCHANTS / API CLIENTS RESULTS -->
    <?php if ($category === 'all' || $category === 'clients'): ?>
        <?php $clientItems = $searchResult['categories']['clients']['items']; ?>
        <div class="section-card" style="margin-bottom: 24px;">
            <div class="section-header" style="background: #fafbfc; border-bottom: 1px solid var(--border-color); padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="entity-badge entity-badge-client">Merchants</span>
                    <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                        Connected API Clients (<?= count($clientItems) ?>)
                    </h3>
                </div>
                <?php if (count($clientItems) > 0): ?>
                    <a href="api_clients/index.php" style="font-size: 0.8rem; color: var(--primary-blue); font-weight: 600; text-decoration: none;">
                        Manage All Clients &rarr;
                    </a>
                <?php endif; ?>
            </div>
            <div class="section-body" style="padding: 0;">
                <?php if (empty($clientItems)): ?>
                    <div style="text-align: center; padding: 28px; color: var(--text-gray); font-size: 0.88rem;">
                        No API clients matched your query.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client / Merchant Name</th>
                                    <th>Contact Email</th>
                                    <th>Live API Key</th>
                                    <th>Webhook URL</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientItems as $c): ?>
                                    <tr>
                                        <td>#<?= (int)$c['id'] ?></td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--text-dark); font-size: 0.92rem;">
                                                <?= highlightMatch($c['client_name'], $query) ?>
                                            </div>
                                            <?php if (!empty($c['phone'])): ?>
                                                <div style="font-size: 0.76rem; color: var(--text-gray); margin-top: 2px;">
                                                    📞 <?= highlightMatch($c['phone'], $query) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= highlightMatch($c['email'] ?? '–', $query) ?>
                                        </td>
                                        <td>
                                            <div class="copy-wrapper" title="<?= htmlspecialchars($c['api_key']) ?>">
                                                <span class="font-mono" style="font-size: 0.8rem; max-width: 120px; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle;">
                                                    <?= highlightMatch($c['api_key'], $query) ?>
                                                </span>
                                                <button type="button" class="btn-copy btn-copy-sm" onclick="copyToClipboard('<?= htmlspecialchars($c['api_key'], ENT_QUOTES) ?>', this)">📋</button>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($c['webhook_url'])): ?>
                                                <span class="font-mono" style="font-size: 0.78rem; color: #16a34a; max-width: 180px; overflow: hidden; text-overflow: ellipsis; display: inline-block;">
                                                    <?= highlightMatch($c['webhook_url'], $query) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 0.8rem; color: #94a3b8; font-style: italic;">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= (int)$c['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                                <?= (int)$c['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="api_clients/index.php?q=<?= urlencode($c['client_name']) ?>" class="btn btn-secondary btn-sm">
                                                Manage ↗
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. EVENTS RESULTS -->
    <?php if ($category === 'all' || $category === 'events'): ?>
        <?php $eventItems = $searchResult['categories']['events']['items']; ?>
        <div class="section-card" style="margin-bottom: 24px;">
            <div class="section-header" style="background: #fafbfc; border-bottom: 1px solid var(--border-color); padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="entity-badge entity-badge-event">Events</span>
                    <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin: 0;">
                        University Events (<?= count($eventItems) ?>)
                    </h3>
                </div>
                <?php if (count($eventItems) > 0): ?>
                    <a href="events/index.php" style="font-size: 0.8rem; color: var(--primary-blue); font-weight: 600; text-decoration: none;">
                        Manage All Events &rarr;
                    </a>
                <?php endif; ?>
            </div>
            <div class="section-body" style="padding: 0;">
                <?php if (empty($eventItems)): ?>
                    <div style="text-align: center; padding: 28px; color: var(--text-gray); font-size: 0.88rem;">
                        No university events matched your query.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Event ID</th>
                                    <th>Event Name</th>
                                    <th>Department</th>
                                    <th>Dates</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eventItems as $e): ?>
                                    <tr>
                                        <td>
                                            <span class="font-mono" style="font-weight: 600; font-size: 0.85rem;">
                                                <?= highlightMatch($e['event_id'], $query) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--text-dark); font-size: 0.92rem;">
                                                <?= highlightMatch($e['event_name'], $query) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-info" style="font-size: 0.78rem;">
                                                <?= highlightMatch($e['department'], $query) ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.8rem; color: #64748b; white-space: nowrap;">
                                            <?= date('M d, Y', strtotime($e['start_date'])) ?> – <?= date('M d, Y', strtotime($e['end_date'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= (int)$e['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                                <?= (int)$e['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="events/index.php?q=<?= urlencode($e['event_name']) ?>" class="btn btn-secondary btn-sm">
                                                View Event ↗
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Initial Search Empty State -->
    <div style="background: #ffffff; border: 1px dashed var(--border-color); border-radius: 12px; padding: 48px 24px; text-align: center; max-width: 680px; margin: 30px auto;">
        <div style="font-size: 2.8rem; margin-bottom: 12px;">🔍</div>
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin: 0 0 8px 0;">
            Unified Cross-Entity Gateway Search
        </h3>
        <p style="color: var(--text-gray); font-size: 0.9rem; line-height: 1.5; margin: 0 0 24px 0;">
            Enter a search term above to locate transactions, client API credentials, customer records, and university events.
        </p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; text-align: left; max-width: 520px; margin: 0 auto; font-size: 0.84rem;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 14px; border-radius: 8px;">
                <strong style="color: var(--text-dark); display: block; margin-bottom: 2px;">💳 Transactions:</strong>
                <span style="color: #64748b;">Search by <code>VTX_...</code>, <code>pay_...</code>, <code>order_...</code>, or customer email.</span>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 14px; border-radius: 8px;">
                <strong style="color: var(--text-dark); display: block; margin-bottom: 2px;">🔌 Merchants:</strong>
                <span style="color: #64748b;">Search by client name, contact email, or live/test API keys.</span>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 14px; border-radius: 8px;">
                <strong style="color: var(--text-dark); display: block; margin-bottom: 2px;">🎟️ Events:</strong>
                <span style="color: #64748b;">Search by event name, event code, or academic department.</span>
            </div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 14px; border-radius: 8px;">
                <strong style="color: var(--text-dark); display: block; margin-bottom: 2px;">⚡ Keyboard Shortcut:</strong>
                <span style="color: #64748b;">Press <kbd style="background: #e2e8f0; padding: 1px 4px; border-radius: 3px;">Ctrl+K</kbd> from any admin page.</span>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
