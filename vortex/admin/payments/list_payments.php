<?php
/**
 * ------------------------------------------------------------
 * list_payments.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : List all transactions with optional filters.
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../../classes/Database.php';

$pageTitle = "Transactions List";
$activeNav = "payments";

$statusFilter = $_GET['status'] ?? '';
$eventIdFilter = $_GET['event_id'] ?? '';

try {
    $db = Database::getInstance()->getConnection();
    
    $query = "SELECT t.*, e.event_name, c.client_name 
              FROM transactions t 
              LEFT JOIN events e ON t.event_id = e.event_id 
              LEFT JOIN api_clients c ON t.api_client_id = c.id
              WHERE 1=1";
    $params = [];
    
    if ($statusFilter !== '') {
        $query .= " AND t.status = :status";
        $params[':status'] = $statusFilter;
    }
    
    if ($eventIdFilter !== '') {
        $query .= " AND t.event_id = :event_id";
        $params[':event_id'] = $eventIdFilter;
    }
    
    $query .= " ORDER BY t.id DESC LIMIT 500"; // limit to 500 for safety
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $transactions = [];
    $error = "Failed to fetch transactions: " . $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="section-card">
    <div class="section-header">
        <h2>💳 Transactions 
            <?php if ($statusFilter) echo "- " . htmlspecialchars($statusFilter); ?>
            <?php if ($eventIdFilter) echo "- Event: " . htmlspecialchars($eventIdFilter); ?>
        </h2>
        <a href="../dashboard.php" class="btn btn-secondary btn-sm">← Back to Dashboard</a>
    </div>
    <div class="section-body" style="padding: 0;">
        <?php if (isset($error)): ?>
            <div style="padding: 24px; color: var(--danger-text);"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Vortex Tx ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>RZP Payment ID</th>
                            <th>Event</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="8" style="text-align:center; padding: 24px;">No transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td>
                                        <a href="payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="font-mono" style="color: var(--primary); font-weight: 700; text-decoration: underline;">
                                            <?= htmlspecialchars($tx['vortex_transaction_id']) ?>
                                        </a>
                                    </td>
                                    <td style="font-weight: 700; color: var(--text-main);">₹<?= number_format($tx['amount'], 2) ?></td>
                                    <td>
                                        <?php
                                        $bClass = 'badge-pending';
                                        if ($tx['status'] === 'SUCCESS') $bClass = 'badge-success';
                                        if ($tx['status'] === 'FAILED') $bClass = 'badge-danger';
                                        if ($tx['status'] === 'REFUNDED') $bClass = 'badge-refunded';
                                        ?>
                                        <span class="badge <?= $bClass ?>"><?= htmlspecialchars($tx['status']) ?></span>
                                    </td>
                                    <td><span class="font-mono"><?= htmlspecialchars($tx['razorpay_payment_id'] ?? 'N/A') ?></span></td>
                                    <td><?= htmlspecialchars($tx['event_name'] ?? $tx['event_id']) ?></td>
                                    <td><?= htmlspecialchars($tx['client_name'] ?? 'Default Client') ?></td>
                                    <td style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($tx['created_at']) ?></td>
                                    <td>
                                        <a href="payment_details.php?vortex_transaction_id=<?= urlencode($tx['vortex_transaction_id']) ?>" class="btn btn-secondary btn-sm">
                                            🔍 Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
