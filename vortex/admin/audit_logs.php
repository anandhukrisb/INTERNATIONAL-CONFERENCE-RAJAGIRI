<?php
/**
 * ------------------------------------------------------------
 * audit_logs.php – Enterprise Audit Trail & Governance Log
 * ------------------------------------------------------------
 * Project : PayVortex Unified Payment Gateway
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../classes/AuditLogger.php';

// RBAC Check
RBAC::require('dashboard.view');

// Read filters
$entityTypeFilter = trim($_GET['entity_type'] ?? '');
$entityIdFilter   = trim($_GET['entity_id'] ?? '');
$actionFilter     = trim($_GET['action'] ?? '');
$actorTypeFilter  = trim($_GET['actor_type'] ?? '');
$search           = trim($_GET['search'] ?? '');
$fromDate         = trim($_GET['from_date'] ?? '');
$toDate           = trim($_GET['to_date'] ?? '');
$limit            = max(10, min((int)($_GET['limit'] ?? 25), 200));
$page             = max(1, (int)($_GET['page'] ?? 1));
$offset           = ($page - 1) * $limit;

$filters = [
    'entity_type' => $entityTypeFilter,
    'entity_id'   => $entityIdFilter,
    'action'      => $actionFilter,
    'actor_type'  => $actorTypeFilter,
    'search'      => $search,
    'from_date'   => $fromDate,
    'to_date'     => $toDate,
];

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportLogs = AuditLogger::getRecentLogs($filters, 10000, 0);
    $filename = "rpay_audit_trail_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    fputcsv($out, ['Audit Log ID', 'Timestamp', 'Action', 'Target Entity Type', 'Target Entity ID', 'Actor Type', 'Actor ID', 'Actor Username', 'Actor Role', 'IP Address', 'User Agent', 'Description', 'Old Values', 'New Values']);

    foreach ($exportLogs as $l) {
        fputcsv($out, [
            $l['id'],
            $l['created_at'],
            $l['action'],
            $l['entity_type'],
            $l['entity_id'] ?? '',
            $l['actor_type'],
            $l['actor_id'] ?? '',
            $l['actor_username'] ?? '',
            $l['actor_role'] ?? '',
            $l['ip_address'] ?? '',
            $l['user_agent'] ?? '',
            $l['description'] ?? '',
            $l['old_values'] ?? '',
            $l['new_values'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// Fetch total count and paginated rows
$totalLogs  = AuditLogger::countLogs($filters);
$auditLogs  = AuditLogger::getRecentLogs($filters, $limit, $offset);
$totalPages = ceil($totalLogs / $limit);

// Quick summary metrics
$clientLogsCount = AuditLogger::countLogs(['entity_type' => 'api_client']);
$adminLogsCount  = AuditLogger::countLogs(['entity_type' => 'admin_user']);
$refundLogsCount = AuditLogger::countLogs(['entity_type' => 'refund']);

$exportQuery = http_build_query(array_merge($_GET, ['export' => 'csv']));
?>

<div class="content-wrapper" style="padding: 24px 32px;">
    <!-- Page Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0;">Audit Trail &amp; Governance</h1>
                <span style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                    Multi-Tenant &bull; Enterprise
                </span>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 4px 0 0;">
                Tamper-evident record of administrative operations, merchant mutations, role modifications, and status changes.
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="?<?= $exportQuery ?>" class="btn btn-secondary btn-sm" title="Export audit records to CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export Filtered Audit CSV
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards Strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Audit Records</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-top: 4px;"><?= number_format($totalLogs) ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Matching current filters</div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.74rem; font-weight: 700; color: #2563eb; text-transform: uppercase;">Client Mutations</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-top: 4px;"><?= number_format($clientLogsCount) ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Keys, webhooks, activations</div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.74rem; font-weight: 700; color: #d97706; text-transform: uppercase;">Admin Team Governance</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-top: 4px;"><?= number_format($adminLogsCount) ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Role changes, user creations</div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 0.74rem; font-weight: 700; color: #7c3aed; text-transform: uppercase;">Refund Dispatches</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-top: 4px;"><?= number_format($refundLogsCount) ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Authorized refunds</div>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; padding: 18px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <form method="GET" action="audit_logs.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) 120px; gap: 14px; align-items: end;">
            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px;">Search Keyword</label>
                <input type="text" name="search" class="form-control" placeholder="Actor, IP, text..." value="<?= htmlspecialchars($search) ?>" style="font-size: 0.82rem; padding: 7px 10px;">
            </div>

            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px;">Entity Type</label>
                <select name="entity_type" class="form-control" style="font-size: 0.82rem; padding: 7px 10px;">
                    <option value="" <?= $entityTypeFilter === '' ? 'selected' : '' ?>>All Entities</option>
                    <option value="api_client" <?= $entityTypeFilter === 'api_client' ? 'selected' : '' ?>>API Clients (Merchants)</option>
                    <option value="admin_user" <?= $entityTypeFilter === 'admin_user' ? 'selected' : '' ?>>Admin Accounts</option>
                    <option value="event" <?= $entityTypeFilter === 'event' ? 'selected' : '' ?>>Events</option>
                    <option value="refund" <?= $entityTypeFilter === 'refund' ? 'selected' : '' ?>>Refunds</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px;">Target Entity ID</label>
                <input type="text" name="entity_id" class="form-control" placeholder="e.g. 1, 2..." value="<?= htmlspecialchars($entityIdFilter) ?>" style="font-size: 0.82rem; padding: 7px 10px;">
            </div>

            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px;">Actor Type</label>
                <select name="actor_type" class="form-control" style="font-size: 0.82rem; padding: 7px 10px;">
                    <option value="" <?= $actorTypeFilter === '' ? 'selected' : '' ?>>All Actors</option>
                    <option value="admin" <?= $actorTypeFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="client" <?= $actorTypeFilter === 'client' ? 'selected' : '' ?>>Client / Merchant</option>
                    <option value="system" <?= $actorTypeFilter === 'system' ? 'selected' : '' ?>>System Automation</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px;">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>" style="font-size: 0.82rem; padding: 7px 10px;">
            </div>

            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px;">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>" style="font-size: 0.82rem; padding: 7px 10px;">
            </div>

            <div style="display: flex; gap: 6px;">
                <button type="submit" class="btn btn-primary btn-sm" style="flex: 1; padding: 8px;">Filter</button>
                <a href="audit_logs.php" class="btn btn-secondary btn-sm" style="padding: 8px 10px;" title="Reset filters">Reset</a>
            </div>
        </form>
    </div>

    <!-- Audit Records Table Card -->
    <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden;">
        <div style="padding: 14px 20px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Showing <strong><?= $totalLogs > 0 ? ($offset + 1) : 0 ?></strong> to <strong><?= min($offset + $limit, $totalLogs) ?></strong> of <strong><?= number_format($totalLogs) ?></strong> records
            </div>
            <div>
                <span style="font-size: 0.8rem; color: var(--text-muted); margin-right: 6px;">Rows:</span>
                <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 25, 'page' => 1])) ?>" style="font-size: 0.8rem; margin: 0 4px; <?= $limit == 25 ? 'font-weight:700; color:var(--primary);' : 'color:var(--text-muted);' ?>">25</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 50, 'page' => 1])) ?>" style="font-size: 0.8rem; margin: 0 4px; <?= $limit == 50 ? 'font-weight:700; color:var(--primary);' : 'color:var(--text-muted);' ?>">50</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['limit' => 100, 'page' => 1])) ?>" style="font-size: 0.8rem; margin: 0 4px; <?= $limit == 100 ? 'font-weight:700; color:var(--primary);' : 'color:var(--text-muted);' ?>">100</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="admin-table" style="width: 100%; border-collapse: collapse; font-size: 0.86rem;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left; border-bottom: 1px solid var(--border-light);">
                        <th style="padding: 12px 16px;">Timestamp</th>
                        <th style="padding: 12px 16px;">Action</th>
                        <th style="padding: 12px 16px;">Target Entity</th>
                        <th style="padding: 12px 16px;">Actor Attribution</th>
                        <th style="padding: 12px 16px;">Description</th>
                        <th style="padding: 12px 16px;">Network Context</th>
                        <th style="padding: 12px 16px; text-align: right;">State Diff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px 16px; color: var(--text-muted);">
                                <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 6px; color: var(--text-main);">No audit events match your search</div>
                                <p style="font-size: 0.84rem; max-width: 400px; margin: 0 auto 12px;">Admin actions and client governance events will automatically appear here as changes are performed.</p>
                                <a href="audit_logs.php" class="btn btn-secondary btn-sm">Clear Filters</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <?php
                            $hasDiffs = !empty($log['old_values']) || !empty($log['new_values']);
                            $oldJson = $log['old_values'] ? json_decode($log['old_values'], true) : null;
                            $newJson = $log['new_values'] ? json_decode($log['new_values'], true) : null;
                            ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 16px; white-space: nowrap; font-size: 0.8rem; color: var(--text-muted);">
                                    <div><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></div>
                                    <div style="font-size: 0.72rem; color: #94a3b8;"><?= htmlspecialchars(substr($log['created_at'], 11, 8)) ?></div>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <?= AuditLogger::getActionBadge($log['action']) ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <span style="font-weight: 600; text-transform: uppercase; font-size: 0.75rem; color: #475569;">
                                        <?= htmlspecialchars(str_replace('_', ' ', $log['entity_type'])) ?>
                                    </span>
                                    <?php if (!empty($log['entity_id'])): ?>
                                        <div class="font-mono" style="font-size: 0.8rem; color: var(--primary); font-weight: 700;">
                                            #<?= htmlspecialchars($log['entity_id']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-weight: 600; font-size: 0.84rem;"><?= htmlspecialchars($log['actor_username'] ?: 'System') ?></span>
                                        <span class="badge" style="font-size: 0.68rem; padding: 2px 6px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                                            <?= htmlspecialchars(strtoupper($log['actor_role'] ?: $log['actor_type'])) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($log['actor_id'])): ?>
                                        <div style="font-size: 0.72rem; color: var(--text-muted); font-family: monospace;">ID: #<?= (int)$log['actor_id'] ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 16px; font-size: 0.84rem; color: var(--text-main); max-width: 320px;">
                                    <?= htmlspecialchars($log['description'] ?: '–') ?>
                                </td>
                                <td style="padding: 12px 16px; font-size: 0.76rem; color: var(--text-muted); white-space: nowrap;">
                                    <div class="font-mono" style="color: #334155; font-weight: 600;"><?= htmlspecialchars($log['ip_address'] ?: '127.0.0.1') ?></div>
                                    <div style="font-size: 0.7rem; color: #94a3b8; max-width: 140px; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                        <?= htmlspecialchars(substr($log['user_agent'] ?? 'System', 0, 30)) ?>...
                                    </div>
                                </td>
                                <td style="padding: 12px 16px; text-align: right;">
                                    <?php if ($hasDiffs): ?>
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                onclick="openAuditDiffModal(<?= (int)$log['id'] ?>)"
                                                style="padding: 4px 8px; font-size: 0.74rem;">
                                            Diff &rarr;
                                        </button>
                                        <!-- Hidden Diff Payload for Modal -->
                                        <script id="audit-diff-<?= (int)$log['id'] ?>" type="application/json">
                                            <?= json_encode([
                                                'id'          => $log['id'],
                                                'action'      => $log['action'],
                                                'description' => $log['description'],
                                                'old_values'  => $oldJson,
                                                'new_values'  => $newJson,
                                                'metadata'    => $log['metadata'] ? json_decode($log['metadata'], true) : null,
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
                                        </script>
                                    <?php else: ?>
                                        <span style="font-size: 0.75rem; color: #cbd5e1;">–</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 14px 20px; border-top: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="font-size: 0.84rem; color: var(--text-muted);">
                    Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Audit Diff Viewer -->
<div id="auditDiffModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 680px; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 id="modalDiffTitle" style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin: 0;">State Mutation Diff</h3>
                <div id="modalDiffSubtitle" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">Audit Log Record</div>
            </div>
            <button type="button" onclick="closeAuditDiffModal()" style="background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer;">&times;</button>
        </div>

        <div style="padding: 24px; overflow-y: auto; flex: 1;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <div style="font-size: 0.74rem; font-weight: 700; color: #dc2626; text-transform: uppercase; margin-bottom: 6px;">Prior State (Old Values)</div>
                    <pre id="modalOldValues" class="font-mono" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 6px; font-size: 0.78rem; max-height: 240px; overflow: auto; margin: 0;"></pre>
                </div>
                <div>
                    <div style="font-size: 0.74rem; font-weight: 700; color: #16a34a; text-transform: uppercase; margin-bottom: 6px;">Updated State (New Values)</div>
                    <pre id="modalNewValues" class="font-mono" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px; border-radius: 6px; font-size: 0.78rem; max-height: 240px; overflow: auto; margin: 0;"></pre>
                </div>
            </div>

            <div id="modalMetadataWrap" style="display: none; border-top: 1px solid var(--border-light); padding-top: 14px;">
                <div style="font-size: 0.74rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px;">Contextual Metadata</div>
                <pre id="modalMetadata" class="font-mono" style="background: #f8fafc; border: 1px solid var(--border-light); color: #334155; padding: 10px; border-radius: 6px; font-size: 0.78rem; margin: 0;"></pre>
            </div>
        </div>

        <div style="padding: 14px 24px; border-top: 1px solid var(--border-light); background: #f8fafc; text-align: right;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeAuditDiffModal()">Close</button>
        </div>
    </div>
</div>

<script>
function openAuditDiffModal(logId) {
    const raw = document.getElementById('audit-diff-' + logId);
    if (!raw) return;

    try {
        const data = JSON.parse(raw.textContent);
        document.getElementById('modalDiffTitle').innerText = 'State Mutation Diff: ' + data.action;
        document.getElementById('modalDiffSubtitle').innerText = data.description || ('Record #' + data.id);

        document.getElementById('modalOldValues').innerText = data.old_values ? JSON.stringify(data.old_values, null, 2) : '(None / Unchanged)';
        document.getElementById('modalNewValues').innerText = data.new_values ? JSON.stringify(data.new_values, null, 2) : '(None / Unchanged)';

        const metaWrap = document.getElementById('modalMetadataWrap');
        if (data.metadata && Object.keys(data.metadata).length > 0) {
            metaWrap.style.display = 'block';
            document.getElementById('modalMetadata').innerText = JSON.stringify(data.metadata, null, 2);
        } else {
            metaWrap.style.display = 'none';
        }

        const modal = document.getElementById('auditDiffModal');
        modal.style.display = 'flex';
    } catch (e) {
        console.error('Failed to parse audit diff payload:', e);
    }
}

function closeAuditDiffModal() {
    document.getElementById('auditDiffModal').style.display = 'none';
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
