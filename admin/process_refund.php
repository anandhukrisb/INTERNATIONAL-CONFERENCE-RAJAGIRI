<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$reg_id = $data['registration_id'] ?? '';

if (empty($reg_id)) {
    echo json_encode(['success' => false, 'error' => 'Registration ID is required']);
    exit;
}

try {
    require_once __DIR__ . '/../backend/db.php';
    require_once __DIR__ . '/../razorpay/config.php';
    
    // Ensure the registration is 'Completed'
    $stmt = $pdo->prepare("SELECT payment_status FROM user_registrations WHERE registration_id = :reg_id");
    $stmt->execute([':reg_id' => $reg_id]);
    $reg = $stmt->fetch();
    
    if (!$reg || $reg['payment_status'] !== 'Completed') {
        echo json_encode(['success' => false, 'error' => 'Registration is not in Completed state.']);
        exit;
    }
    
    // Fetch the successful payment_id
    $query = "
        SELECT pa.razorpay_payment_id 
        FROM payment_orders po
        JOIN payment_attempts pa ON po.razorpay_order_id = pa.razorpay_order_id
        WHERE po.registration_id = :reg_id 
        AND po.status = 'paid'
        AND pa.razorpay_payment_id IS NOT NULL
        ORDER BY po.id DESC LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':reg_id' => $reg_id]);
    $payment = $stmt->fetch();
    
    if (!$payment || empty($payment['razorpay_payment_id'])) {
        echo json_encode(['success' => false, 'error' => 'No successful Razorpay payment found for this registration.']);
        exit;
    }
    
    $payment_id = $payment['razorpay_payment_id'];
    
    // Process Refund via Razorpay API
    $api = new \Razorpay\Api\Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $api->payment->fetch($payment_id)->refund();
    
    // Update the status to 'Refund Approved'
    $stmt = $pdo->prepare("UPDATE user_registrations SET payment_status = 'Refund Approved' WHERE registration_id = :reg_id AND payment_status = 'Completed'");
    $stmt->execute([':reg_id' => $reg_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not update local status.']);
    }
} catch (\Razorpay\Api\Errors\Error $e) {
    echo json_encode(['success' => false, 'error' => 'Razorpay API Error: ' . $e->getMessage()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
