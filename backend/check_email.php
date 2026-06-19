<?php
// backend/check_email.php
header('Content-Type: application/json');

// Disable display_errors so HTML warnings don't break the JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/db.php';

    $email = $_GET['email'] ?? '';

    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Email is required.']);
        exit;
    }

    $stmtReg = $pdo->prepare("SELECT COUNT(*) FROM user_registrations WHERE email = :email");
    $stmtReg->execute([':email' => $email]);
    $isRegistered = $stmtReg->fetchColumn() > 0;

    $stmtAbs = $pdo->prepare("SELECT COUNT(*) FROM abstract_details WHERE email = :email");
    $stmtAbs->execute([':email' => $email]);
    $hasAbstract = $stmtAbs->fetchColumn() > 0;

    echo json_encode([
        'success' => true,
        'is_registered' => $isRegistered,
        'has_abstract' => $hasAbstract
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error.'
    ]);
}
?>
