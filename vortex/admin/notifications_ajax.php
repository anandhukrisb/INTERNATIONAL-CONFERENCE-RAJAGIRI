<?php
/**
 * ------------------------------------------------------------
 * notifications_ajax.php
 * ------------------------------------------------------------
 * Project : Vortex Unified Payment Gateway - Admin Panel
 * Purpose : Asynchronous endpoint for topbar notification bell,
 *           unread badge polling, and read/unread status updates.
 * ------------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Must be authenticated admin
if (empty($_SESSION['vortex_admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized. Admin authentication required.'
    ]);
    exit;
}

require_once __DIR__ . '/../classes/NotificationCenter.php';

$adminId = (int)$_SESSION['vortex_admin_id'];
$action  = trim($_GET['action'] ?? $_POST['action'] ?? 'get_summary');

try {
    switch ($action) {
        case 'get_summary':
            // Optional sync of live gateway transactions
            if (!empty($_GET['sync'])) {
                NotificationCenter::syncGatewayAlerts();
            }

            $unreadCount   = NotificationCenter::getUnreadCount($adminId);
            $notifications = NotificationCenter::getNotifications($adminId, 8, 0);

            $formatted = [];
            foreach ($notifications as $n) {
                $icon = 'bell';
                $color = 'blue';

                switch ($n['type']) {
                    case NotificationCenter::TYPE_FAILED_WEBHOOK:
                        $icon = 'webhook';
                        $color = 'red';
                        break;
                    case NotificationCenter::TYPE_NEW_DISPUTE:
                        $icon = 'shield-alert';
                        $color = 'amber';
                        break;
                    case NotificationCenter::TYPE_HIGH_VALUE_TXN:
                        $icon = 'trending-up';
                        $color = 'green';
                        break;
                    case NotificationCenter::TYPE_SYSTEM_ALERT:
                    default:
                        $icon = 'info';
                        $color = 'blue';
                        break;
                }

                $formatted[] = [
                    'id'             => (int)$n['id'],
                    'type'           => $n['type'],
                    'title'          => $n['title'],
                    'message'        => $n['message'],
                    'severity'       => $n['severity'],
                    'is_read'        => (int)$n['is_read'],
                    'link'           => $n['link'],
                    'reference_id'   => $n['reference_id'],
                    'reference_type' => $n['reference_type'],
                    'created_at'     => $n['created_at'],
                    'time_ago'       => NotificationCenter::timeAgo($n['created_at']),
                    'icon'           => $icon,
                    'color'          => $color
                ];
            }

            echo json_encode([
                'success'      => true,
                'unread_count' => $unreadCount,
                'items'        => $formatted
            ]);
            break;

        case 'poll':
            $unreadCount = NotificationCenter::getUnreadCount($adminId);
            echo json_encode([
                'success'      => true,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'mark_read':
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid notification ID.']);
                exit;
            }

            NotificationCenter::markAsRead($id, $adminId);
            $unreadCount = NotificationCenter::getUnreadCount($adminId);

            echo json_encode([
                'success'      => true,
                'id'           => $id,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'mark_all_read':
            NotificationCenter::markAllAsRead($adminId);
            echo json_encode([
                'success'      => true,
                'unread_count' => 0
            ]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id > 0) {
                NotificationCenter::delete($id);
            }
            $unreadCount = NotificationCenter::getUnreadCount($adminId);
            echo json_encode([
                'success'      => true,
                'unread_count' => $unreadCount
            ]);
            break;

        case 'simulate':
            $simType = trim($_POST['type'] ?? 'high_value_transaction');
            $createdId = 0;

            if ($simType === 'failed_webhook') {
                $createdId = NotificationCenter::create(
                    NotificationCenter::TYPE_FAILED_WEBHOOK,
                    "Webhook Dispatch Failed: Rajagiri Portal",
                    "Outbound Layer 2 POST to 'https://events.rajagiri.edu/api/webhook' timed out after 3000ms. Response code: 504 Gateway Timeout.",
                    NotificationCenter::SEVERITY_CRITICAL,
                    "api_clients/index.php",
                    "wh_sim_" . time(),
                    'webhook'
                );
            } elseif ($simType === 'new_dispute') {
                $createdId = NotificationCenter::create(
                    NotificationCenter::TYPE_NEW_DISPUTE,
                    "Payment Dispute Lodged: ₹15,000.00",
                    "Customer cardholder filed chargeback claim for txn VTX_DISP_" . substr(md5((string)time()), 0, 6) . ". Reason: Fraudulent activity reported by issuing bank.",
                    NotificationCenter::SEVERITY_CRITICAL,
                    "payments/list_payments.php",
                    "disp_sim_" . time(),
                    'transaction'
                );
            } else {
                $createdId = NotificationCenter::create(
                    NotificationCenter::TYPE_HIGH_VALUE_TXN,
                    "High-Value Transaction: ₹45,000.00",
                    "High-value payment captured from anandhu.k@rajagiri.edu for International Conference registration. Requires AML compliance check.",
                    NotificationCenter::SEVERITY_WARNING,
                    "payments/list_payments.php",
                    "high_sim_" . time(),
                    'transaction'
                );
            }

            echo json_encode([
                'success'      => true,
                'id'           => $createdId,
                'unread_count' => NotificationCenter::getUnreadCount($adminId)
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action.']);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal error: ' . $e->getMessage()
    ]);
}
