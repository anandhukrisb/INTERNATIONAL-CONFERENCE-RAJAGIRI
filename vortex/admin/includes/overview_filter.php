<?php
/**
 * ------------------------------------------------------------
 * overview_filter.php
 * ------------------------------------------------------------
 * Hot-Reload Filter Bar Component for Overview Transactions.
 * Updates the table in real time as dropdowns and dates change.
 * ------------------------------------------------------------
 */

$filterStartDate   = $filterStartDate ?? '';
$filterEndDate     = $filterEndDate ?? '';
$filterStatus      = $filterStatus ?? '';
$filterEventId     = $filterEventId ?? '';
$filterClientId    = $filterClientId ?? '';
$filterCurrency    = $filterCurrency ?? '';
$filterEvents      = $filterEvents ?? [];
$filterClients     = $filterClients ?? [];
$activeFilterCount = $activeFilterCount ?? 0;
$isFiltered        = $isFiltered ?? false;
?>

<div class="section-card overview-filter-card" id="overviewFilterCard" style="margin-top: 24px; margin-bottom: 20px; border-radius: var(--radius-md, 10px); border: 1px solid var(--border-color); background: var(--card-bg, #ffffff); box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden;">
    <!-- Filter Header Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: var(--bg-hover, #f8fafc); border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary-blue, #1a73e8);">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            <h3 style="font-size: 0.92rem; font-weight: 700; color: var(--text-dark, #1e293b); margin: 0; letter-spacing: -0.2px;">
                Filter Transactions
            </h3>
            <span id="overviewFilterActiveBadge">
                <?php if ($isFiltered): ?>
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 0.72rem; font-weight: 700; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                        <?= (int)$activeFilterCount ?> Active <?= $activeFilterCount === 1 ? 'Filter' : 'Filters' ?>
                    </span>
                <?php endif; ?>
            </span>
            <!-- Live Hot-Reload Spinner -->
            <span id="filterLiveSpinner" style="display: none; align-items: center; gap: 6px; font-size: 0.74rem; font-weight: 600; color: var(--primary-blue, #1a73e8); margin-left: 6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="animation: vortexSpin 0.7s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                <span>Filtering live...</span>
            </span>
        </div>

        <div id="overviewFilterHeaderStats" style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 0.8rem; color: var(--text-gray, #64748b);">
                Matching: <strong id="overviewMatchCount" style="color: var(--text-dark, #1e293b);"><?= number_format($txTotalCount ?? 0) ?></strong> transactions
            </span>
            <a href="dashboard.php" id="overviewClearAllBtn" class="btn btn-secondary btn-sm" style="<?= $isFiltered ? 'display: inline-flex;' : 'display: none;' ?> font-size: 0.76rem; padding: 4px 10px; color: #dc2626; border-color: #fecaca; background: #fff5f5; align-items: center; gap: 4px;" onclick="resetOverviewFilter(event)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                Clear All
            </a>
        </div>
    </div>

    <!-- Filter Form (Live Inputs) -->
    <div style="padding: 16px 20px;">
        <form method="GET" action="dashboard.php" id="overviewFilterForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: end;">
                
                <!-- 1. Start Date -->
                <div>
                    <label for="filterStartDate" style="display: block; font-size: 0.74rem; font-weight: 700; color: var(--text-gray, #64748b); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px;">
                        Start Date
                    </label>
                    <input type="date" 
                           id="filterStartDate" 
                           name="start_date" 
                           class="form-control" 
                           value="<?= htmlspecialchars($filterStartDate) ?>" 
                           style="width: 100%; font-size: 0.84rem; padding: 7px 10px; height: 38px;">
                </div>

                <!-- 2. End Date -->
                <div>
                    <label for="filterEndDate" style="display: block; font-size: 0.74rem; font-weight: 700; color: var(--text-gray, #64748b); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px;">
                        End Date
                    </label>
                    <input type="date" 
                           id="filterEndDate" 
                           name="end_date" 
                           class="form-control" 
                           value="<?= htmlspecialchars($filterEndDate) ?>" 
                           style="width: 100%; font-size: 0.84rem; padding: 7px 10px; height: 38px;">
                </div>

                <!-- 3. Payment Status -->
                <div>
                    <label for="filterStatus" style="display: block; font-size: 0.74rem; font-weight: 700; color: var(--text-gray, #64748b); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px;">
                        Payment Status
                    </label>
                    <select id="filterStatus" name="status" class="form-control" style="width: 100%; font-size: 0.84rem; padding: 7px 10px; height: 38px; cursor: pointer;">
                        <option value="">All Statuses</option>
                        <option value="SUCCESS" <?= $filterStatus === 'SUCCESS' ? 'selected' : '' ?>>Success (Paid)</option>
                        <option value="PENDING" <?= $filterStatus === 'PENDING' ? 'selected' : '' ?>>Pending / Initiated</option>
                        <option value="FAILED" <?= $filterStatus === 'FAILED' ? 'selected' : '' ?>>Failed</option>
                        <option value="REFUNDED" <?= $filterStatus === 'REFUNDED' ? 'selected' : '' ?>>Refunded</option>
                        <option value="DISPUTED" <?= $filterStatus === 'DISPUTED' ? 'selected' : '' ?>>Disputed</option>
                    </select>
                </div>

                <!-- 4. Events -->
                <div>
                    <label for="filterEventId" style="display: block; font-size: 0.74rem; font-weight: 700; color: var(--text-gray, #64748b); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px;">
                        Event
                    </label>
                    <select id="filterEventId" name="event_id" class="form-control" style="width: 100%; font-size: 0.84rem; padding: 7px 10px; height: 38px; cursor: pointer;">
                        <option value="">All Events</option>
                        <?php foreach ($filterEvents as $evt): ?>
                            <option value="<?= htmlspecialchars($evt['event_id']) ?>" <?= $filterEventId === $evt['event_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($evt['event_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 5. API Clients (Merchants) -->
                <div>
                    <label for="filterClientId" style="display: block; font-size: 0.74rem; font-weight: 700; color: var(--text-gray, #64748b); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px;">
                        API Client
                    </label>
                    <select id="filterClientId" name="api_client_id" class="form-control" style="width: 100%; font-size: 0.84rem; padding: 7px 10px; height: 38px; cursor: pointer;">
                        <option value="">All API Clients</option>
                        <?php foreach ($filterClients as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>" <?= $filterClientId === (string)$cl['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 6. Currency (Domestic / International) -->
                <div>
                    <label for="filterCurrency" style="display: block; font-size: 0.74rem; font-weight: 700; color: var(--text-gray, #64748b); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px;">
                        Currency
                    </label>
                    <select id="filterCurrency" name="currency" class="form-control" style="width: 100%; font-size: 0.84rem; padding: 7px 10px; height: 38px; cursor: pointer;">
                        <option value="">All Currencies</option>
                        <option value="INR" <?= $filterCurrency === 'INR' ? 'selected' : '' ?>>₹ INR (Domestic)</option>
                        <option value="USD" <?= $filterCurrency === 'USD' ? 'selected' : '' ?>>$ USD (International)</option>
                    </select>
                </div>

            </div>

            <!-- Active Filters Indicator Pills -->
            <div id="overviewActivePillsWrapper">
                <?php if ($isFiltered): ?>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border-color);">
                        <span style="font-size: 0.74rem; font-weight: 600; color: var(--text-gray, #64748b);">Active Filters:</span>

                        <?php if ($filterStartDate !== ''): ?>
                            <span style="display: inline-flex; align-items: center; gap: 5px; background: var(--bg-hover, #f1f5f9); color: var(--text-dark, #1e293b); padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; border: 1px solid var(--border-color);">
                                From: <strong><?= htmlspecialchars($filterStartDate) ?></strong>
                                <a href="#" onclick="removeSingleOverviewFilter(event, 'start_date')" style="color: #64748b; font-weight: 700; text-decoration: none; margin-left: 2px;" title="Remove Start Date filter">&times;</a>
                            </span>
                        <?php endif; ?>

                        <?php if ($filterEndDate !== ''): ?>
                            <span style="display: inline-flex; align-items: center; gap: 5px; background: var(--bg-hover, #f1f5f9); color: var(--text-dark, #1e293b); padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; border: 1px solid var(--border-color);">
                                To: <strong><?= htmlspecialchars($filterEndDate) ?></strong>
                                <a href="#" onclick="removeSingleOverviewFilter(event, 'end_date')" style="color: #64748b; font-weight: 700; text-decoration: none; margin-left: 2px;" title="Remove End Date filter">&times;</a>
                            </span>
                        <?php endif; ?>

                        <?php if ($filterStatus !== '' && $filterStatus !== 'ALL'): ?>
                            <span style="display: inline-flex; align-items: center; gap: 5px; background: var(--bg-hover, #f1f5f9); color: var(--text-dark, #1e293b); padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; border: 1px solid var(--border-color);">
                                Status: <strong><?= htmlspecialchars($filterStatus) ?></strong>
                                <a href="#" onclick="removeSingleOverviewFilter(event, 'status')" style="color: #64748b; font-weight: 700; text-decoration: none; margin-left: 2px;" title="Remove Status filter">&times;</a>
                            </span>
                        <?php endif; ?>

                        <?php if ($filterEventId !== '' && $filterEventId !== 'ALL'): ?>
                            <?php 
                            $evName = $filterEventId;
                            foreach ($filterEvents as $ev) {
                                if ($ev['event_id'] === $filterEventId) {
                                    $evName = $ev['event_name'];
                                    break;
                                }
                            }
                            ?>
                            <span style="display: inline-flex; align-items: center; gap: 5px; background: var(--bg-hover, #f1f5f9); color: var(--text-dark, #1e293b); padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; border: 1px solid var(--border-color);">
                                Event: <strong><?= htmlspecialchars($evName) ?></strong>
                                <a href="#" onclick="removeSingleOverviewFilter(event, 'event_id')" style="color: #64748b; font-weight: 700; text-decoration: none; margin-left: 2px;" title="Remove Event filter">&times;</a>
                            </span>
                        <?php endif; ?>

                        <?php if ($filterClientId !== '' && $filterClientId !== 'ALL'): ?>
                            <?php 
                            $clName = "Client #{$filterClientId}";
                            foreach ($filterClients as $cl) {
                                if ((int)$cl['id'] === (int)$filterClientId) {
                                    $clName = $cl['client_name'];
                                    break;
                                }
                            }
                            ?>
                            <span style="display: inline-flex; align-items: center; gap: 5px; background: var(--bg-hover, #f1f5f9); color: var(--text-dark, #1e293b); padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; border: 1px solid var(--border-color);">
                                Client: <strong><?= htmlspecialchars($clName) ?></strong>
                                <a href="#" onclick="removeSingleOverviewFilter(event, 'api_client_id')" style="color: #64748b; font-weight: 700; text-decoration: none; margin-left: 2px;" title="Remove Client filter">&times;</a>
                            </span>
                        <?php endif; ?>

                        <?php if ($filterCurrency !== '' && $filterCurrency !== 'ALL'): ?>
                            <span style="display: inline-flex; align-items: center; gap: 5px; background: #e0f2fe; color: #0369a1; padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; border: 1px solid #bae6fd;">
                                Currency: <strong><?= htmlspecialchars($filterCurrency) === 'USD' ? '$ USD (International)' : '₹ INR (Domestic)' ?></strong>
                                <a href="#" onclick="removeSingleOverviewFilter(event, 'currency')" style="color: #0369a1; font-weight: 700; text-decoration: none; margin-left: 2px;" title="Remove Currency filter">&times;</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes vortexSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.transactions-loading {
    opacity: 0.45 !important;
    pointer-events: none !important;
    transition: opacity 0.15s ease-in-out !important;
}
</style>

<script>
(function() {
    let debounceTimer = null;
    let currentController = null;

    function getFilterParams() {
        const form = document.getElementById('overviewFilterForm');
        if (!form) return new URLSearchParams();
        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, val] of formData.entries()) {
            const trimmed = String(val).trim();
            if (trimmed !== '' && trimmed !== 'ALL') {
                params.set(key, trimmed);
            }
        }
        return params;
    }

    function applyOverviewFilterLive(page = 1) {
        const params = getFilterParams();
        if (page > 1) {
            params.set('page', page);
        }

        const queryString = params.toString();
        const targetUrl = 'dashboard.php' + (queryString ? '?' + queryString : '');

        // Visual loading indicator
        const spinner = document.getElementById('filterLiveSpinner');
        const txContainer = document.getElementById('transactions-component');
        if (spinner) spinner.style.display = 'inline-flex';
        if (txContainer) txContainer.classList.add('transactions-loading');

        // Cancel previous request if still in flight
        if (currentController) {
            currentController.abort();
        }
        currentController = new AbortController();

        fetch(targetUrl, {
            signal: currentController.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.text();
        })
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // 1. Hot Swap Transactions Table Component
            const newTxComp = doc.getElementById('transactions-component');
            const curTxComp = document.getElementById('transactions-component');
            if (newTxComp && curTxComp) {
                curTxComp.replaceWith(newTxComp);
                bindPaginationEvents();
            }

            // 2. Hot Swap Header Matching Stats & Clear All Button
            const newStats = doc.getElementById('overviewFilterHeaderStats');
            const curStats = document.getElementById('overviewFilterHeaderStats');
            if (newStats && curStats) {
                curStats.innerHTML = newStats.innerHTML;
            }

            // 3. Hot Swap Active Filter Count Badge
            const newBadge = doc.getElementById('overviewFilterActiveBadge');
            const curBadge = document.getElementById('overviewFilterActiveBadge');
            if (newBadge && curBadge) {
                curBadge.innerHTML = newBadge.innerHTML;
            }

            // 4. Hot Swap Active Filter Pills
            const newPills = doc.getElementById('overviewActivePillsWrapper');
            const curPills = document.getElementById('overviewActivePillsWrapper');
            if (newPills && curPills) {
                curPills.innerHTML = newPills.innerHTML;
            }

            // 5. Update browser address bar seamlessly without reload
            window.history.replaceState(null, '', targetUrl);
        })
        .catch(err => {
            if (err.name === 'AbortError') return;
            console.error('Filter hot reload failed:', err);
            // Fallback: regular submit
            const form = document.getElementById('overviewFilterForm');
            if (form) form.submit();
        })
        .finally(() => {
            if (spinner) spinner.style.display = 'none';
            const freshTxComp = document.getElementById('transactions-component');
            if (freshTxComp) freshTxComp.classList.remove('transactions-loading');
        });
    }

    // Initialize Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Dropdown hot reload on change
        ['filterStatus', 'filterEventId', 'filterClientId', 'filterCurrency'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function() {
                    applyOverviewFilterLive(1);
                });
            }
        });

        // Date inputs hot reload
        ['filterStartDate', 'filterEndDate'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function() {
                    applyOverviewFilterLive(1);
                });
                el.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        applyOverviewFilterLive(1);
                    }, 350);
                });
            }
        });

        // Intercept form submit (e.g. user pressing Enter in date input)
        const form = document.getElementById('overviewFilterForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                applyOverviewFilterLive(1);
            });
        }

        bindPaginationEvents();
    });

    // Intercept pagination clicks so pagination is also hot-reloaded
    function bindPaginationEvents() {
        const txComp = document.getElementById('transactions-component');
        if (!txComp) return;

        txComp.querySelectorAll('.pagination-controls a.pagination-btn, .pagination-controls a.pagination-nav-btn').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                try {
                    const u = new URL(this.href, window.location.href);
                    const p = parseInt(u.searchParams.get('page') || u.searchParams.get('p') || '1', 10);
                    applyOverviewFilterLive(p);
                } catch(ex) {
                    window.location.href = this.href;
                }
            });
        });
    }

    // Expose Global Helper Functions for Reset & Pill removals
    window.resetOverviewFilter = function(e) {
        if (e) e.preventDefault();
        const form = document.getElementById('overviewFilterForm');
        if (form) {
            ['filterStartDate', 'filterEndDate'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            ['filterStatus', 'filterEventId', 'filterClientId', 'filterCurrency'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        }
        applyOverviewFilterLive(1);
    };

    window.removeSingleOverviewFilter = function(e, paramName) {
        if (e) e.preventDefault();
        const fieldMap = {
            'start_date': 'filterStartDate',
            'end_date': 'filterEndDate',
            'status': 'filterStatus',
            'event_id': 'filterEventId',
            'api_client_id': 'filterClientId',
            'currency': 'filterCurrency'
        };
        const inputId = fieldMap[paramName];
        if (inputId) {
            const el = document.getElementById(inputId);
            if (el) el.value = '';
        }
        applyOverviewFilterLive(1);
    };
})();
</script>
