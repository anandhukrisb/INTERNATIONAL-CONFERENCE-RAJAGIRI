<?php
/**
 * ------------------------------------------------------------
 * GlobalSearch.php – Multi-Entity Gateway Search Engine
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway
 * Purpose : High-performance parameterized global search across:
 *           - Transactions (Vortex ID, Razorpay IDs, Email, Mobile)
 *           - Merchants / API Clients (Name, Email, API Keys)
 *           - Events (ID, Name, Department)
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class GlobalSearch
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Execute comprehensive global search across all entities
     *
     * @param string $query   Search term / query
     * @param array  $filters Optional filters: [category, status, from_date, to_date, limit]
     * @return array Structured search result bundle
     */
    public function search(string $query, array $filters = []): array
    {
        $startTime = microtime(true);
        $query = trim($query);

        $category = strtolower(trim((string)($filters['category'] ?? 'all')));
        $limit    = max(1, min((int)($filters['limit'] ?? 25), 100));

        $results = [
            'query'             => $query,
            'total_matches'     => 0,
            'execution_time_ms' => 0.0,
            'exact_match'       => null,
            'categories'        => [
                'transactions' => ['count' => 0, 'items' => []],
                'clients'      => ['count' => 0, 'items' => []],
                'events'       => ['count' => 0, 'items' => []],
            ]
        ];

        if (strlen($query) < 1) {
            $results['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            return $results;
        }

        try {
            // 1. Search Transactions (if category is 'all' or 'transactions')
            if ($category === 'all' || $category === 'transactions') {
                $txResults = $this->searchTransactions($query, $filters, $limit);
                $results['categories']['transactions'] = $txResults;
                $results['total_matches'] += $txResults['count'];

                // Check for exact single match on unique identifiers
                if ($results['exact_match'] === null && !empty($txResults['items'])) {
                    foreach ($txResults['items'] as $item) {
                        if (
                            strcasecmp($item['vortex_transaction_id'], $query) === 0 ||
                            strcasecmp((string)$item['razorpay_payment_id'], $query) === 0 ||
                            strcasecmp((string)$item['razorpay_order_id'], $query) === 0
                        ) {
                            $results['exact_match'] = [
                                'type'  => 'transaction',
                                'title' => $item['vortex_transaction_id'],
                                'url'   => 'payments/payment_details.php?vortex_transaction_id=' . urlencode($item['vortex_transaction_id']),
                                'data'  => $item
                            ];
                            break;
                        }
                    }
                }
            }

            // 2. Search Merchants / API Clients (if category is 'all' or 'clients')
            if ($category === 'all' || $category === 'clients') {
                $clientResults = $this->searchClients($query, $limit);
                $results['categories']['clients'] = $clientResults;
                $results['total_matches'] += $clientResults['count'];

                // Check exact match on API key or ID
                if ($results['exact_match'] === null && !empty($clientResults['items'])) {
                    foreach ($clientResults['items'] as $c) {
                        if (
                            strcasecmp((string)$c['api_key'], $query) === 0 ||
                            strcasecmp((string)($c['test_api_key'] ?? ''), $query) === 0 ||
                            (is_numeric($query) && (int)$c['id'] === (int)$query)
                        ) {
                            $results['exact_match'] = [
                                'type'  => 'client',
                                'title' => $c['client_name'],
                                'url'   => 'api_clients/index.php?q=' . urlencode($c['client_name']),
                                'data'  => $c
                            ];
                            break;
                        }
                    }
                }
            }

            // 3. Search Events (if category is 'all' or 'events')
            if ($category === 'all' || $category === 'events') {
                $eventResults = $this->searchEvents($query, $limit);
                $results['categories']['events'] = $eventResults;
                $results['total_matches'] += $eventResults['count'];
            }

        } catch (\Throwable $e) {
            Logger::error("GlobalSearch error: " . $e->getMessage());
        }

        $results['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        return $results;
    }

    /**
     * Search Transactions Table
     */
    private function searchTransactions(string $query, array $filters, int $limit): array
    {
        $params = [];
        $where = [];

        // Exact match or wildcard pattern
        $wildcard = '%' . $query . '%';

        // Search dimensions: Vortex ID, Razorpay IDs, Customer Email, Mobile, DB ID
        $fieldClauses = [
            't.vortex_transaction_id LIKE :w1',
            't.razorpay_payment_id LIKE :w2',
            't.razorpay_order_id LIKE :w3',
            't.customer_email LIKE :w4',
            't.customer_mobile LIKE :w5'
        ];

        $params[':w1'] = $wildcard;
        $params[':w2'] = $wildcard;
        $params[':w3'] = $wildcard;
        $params[':w4'] = $wildcard;
        $params[':w5'] = $wildcard;

        if (is_numeric($query)) {
            $fieldClauses[] = 't.id = :num_id';
            $params[':num_id'] = (int)$query;
        }

        $where[] = '(' . implode(' OR ', $fieldClauses) . ')';

        // Filter by Transaction Status
        if (!empty($filters['status']) && strtoupper($filters['status']) !== 'ALL') {
            $where[] = 't.status = :status';
            $params[':status'] = strtoupper(trim($filters['status']));
        }

        // Filter by Date Range
        if (!empty($filters['from_date'])) {
            $where[] = 't.created_at >= :from_date';
            $params[':from_date'] = $filters['from_date'] . ' 00:00:00';
        }
        if (!empty($filters['to_date'])) {
            $where[] = 't.created_at <= :to_date';
            $params[':to_date'] = $filters['to_date'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        // Count total matching
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM transactions t WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // Fetch matching items
        $sql = "SELECT t.id, t.vortex_transaction_id, t.amount, t.currency, t.status,
                       t.customer_email, t.customer_mobile,
                       t.razorpay_order_id, t.razorpay_payment_id,
                       t.is_test_mode, t.created_at,
                       e.event_name, e.department,
                       c.client_name
                FROM transactions t
                LEFT JOIN events e ON t.event_id = e.event_id
                LEFT JOIN api_clients c ON t.api_client_id = c.id
                WHERE {$whereSql}
                ORDER BY t.id DESC
                LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'count' => $totalCount,
            'items' => $items
        ];
    }

    /**
     * Search API Clients / Merchants Table
     */
    private function searchClients(string $query, int $limit): array
    {
        $params = [];
        $wildcard = '%' . $query . '%';

        $clauses = [
            'client_name LIKE :c1',
            'email LIKE :c2',
            'api_key LIKE :c3',
            'test_api_key LIKE :c4'
        ];
        $params[':c1'] = $wildcard;
        $params[':c2'] = $wildcard;
        $params[':c3'] = $wildcard;
        $params[':c4'] = $wildcard;

        if (is_numeric($query)) {
            $clauses[] = 'id = :cid';
            $params[':cid'] = (int)$query;
        }

        $whereSql = '(' . implode(' OR ', $clauses) . ')';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM api_clients WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        $sql = "SELECT id, client_name, email, phone, api_key, test_api_key, is_active, created_at
                FROM api_clients
                WHERE {$whereSql}
                ORDER BY id DESC
                LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'count' => $totalCount,
            'items' => $items
        ];
    }

    /**
     * Search Events Table
     */
    private function searchEvents(string $query, int $limit): array
    {
        $params = [];
        $wildcard = '%' . $query . '%';

        $clauses = [
            'event_id LIKE :e1',
            'event_name LIKE :e2',
            'department LIKE :e3'
        ];
        $params[':e1'] = $wildcard;
        $params[':e2'] = $wildcard;
        $params[':e3'] = $wildcard;

        if (is_numeric($query)) {
            $clauses[] = 'id = :eid';
            $params[':eid'] = (int)$query;
        }

        $whereSql = '(' . implode(' OR ', $clauses) . ')';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM events WHERE {$whereSql}");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        $sql = "SELECT id, event_id, event_name, department, start_date, end_date, webhook_url, is_active, created_at
                FROM events
                WHERE {$whereSql}
                ORDER BY id DESC
                LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'count' => $totalCount,
            'items' => $items
        ];
    }
}
