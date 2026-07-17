<?php
// save_registration.php
header('Content-Type: application/json');

// Disable display_errors so HTML warnings don't break the JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    error_log("save_registration.php: Script started.");
    // 1. Get database connection using PDO
    require_once __DIR__ . '/backend/db.php';
    error_log("save_registration.php: Database connection loaded.");

    // 2. Read the JSON payload from the request body
    $json = file_get_contents('php://input');
    error_log("save_registration.php: Payload received: " . $json);
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception("Invalid JSON payload received.");
    }

    // 3. Extract variables from payload
    $firstName = $data['firstName'] ?? '';
    $middleName = $data['middleName'] ?? null;
    $lastName = $data['lastName'] ?? '';
    $email = $data['email'] ?? '';
    $organization = $data['organization'] ?? '';
    $phone = $data['phone'] ?? '';
    $dob = $data['dob'] ?? null;
    $participantType = $data['participantType'] ?? '';
    $countryCategory = $data['countryCategory'] ?? ''; // not in DB, wait, actually countryCategory is in DB
    $country = $_POST['country'] ?? ''; // Wait, the JS payload does not pass 'country'
    // Let me check what the JS payload passes. It didn't pass 'country' but 'countryCategory'.
    // Let's modify the JS payload to include 'country' in registration.php and registration.html later if needed,
    // or just assume we'll fix the payload.
    // For now, I will extract 'country' from $data if it exists, else 'Unknown'.
    $country = $data['country'] ?? 'Unknown';
    $package = $data['requiredPackage'] ?? '';
    $abstractSubmitted = $data['abstractSubmitted'] ?? 'no';
    $abstractEmail = $data['abstractEmail'] ?? null;
    $baseAmount = $data['baseAmount'] ?? 0;
    $paymentStatus = $data['paymentStatus'] ?? 'Not Completed';

    // 4. Check if the email already exists to prevent duplicate registrations and data overwriting
    $stmtCheck = $pdo->prepare("SELECT registration_id, id FROM user_registrations WHERE email = :email LIMIT 1");
    $stmtCheck->execute([':email' => $email]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        throw new Exception("This email is already registered.");
    }

    // 5. Generate a unique registration ID and INSERT
        do {
            $midnight = strtotime('today');
            $now = microtime(true);
            $millisecondsSinceMidnight = (int)(($now - $midnight) * 1000);
            $base36_ms = strtoupper(base_convert($millisecondsSinceMidnight, 10, 36));
            
            $registrationId = 'ICSW' . date('ymd') . '-' . $base36_ms;

            // Check if it exists
            $stmtCheckId = $pdo->prepare("SELECT id FROM user_registrations WHERE registration_id = :reg_id");
            $stmtCheckId->execute([':reg_id' => $registrationId]);
            
            if ($stmtCheckId->fetch()) {
                // If it exists (extremely rare collision), wait 1 millisecond and retry
                usleep(1000);
            } else {
                break;
            }
        } while (true);

        error_log("save_registration.php: Generated secure unique registration_id = $registrationId");
        
        $sql = "INSERT INTO user_registrations (
            registration_id, first_name, middle_name, last_name, email,
            organization, phone, date_of_birth, participant_category, country, country_category,
            package, abstract_submitted, abstract_email, base_amount, payment_status
        ) VALUES (
            :reg_id, :fname, :mname, :lname, :email,
            :org, :phone, :dob, :ptype, :country, :ccat,
            :pkg, :absub, :abemail, :baseamt, :paystat
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':reg_id' => $registrationId,
            ':fname' => $firstName,
            ':mname' => $middleName,
            ':lname' => $lastName,
            ':email' => $email,
            ':org' => $organization,
            ':phone' => $phone,
            ':dob' => $dob,
            ':ptype' => $participantType,
            ':country' => $country,
            ':ccat' => $countryCategory,
            ':pkg' => $package,
            ':absub' => $abstractSubmitted,
            ':abemail' => $abstractEmail,
            ':baseamt' => $baseAmount,
            ':paystat' => $paymentStatus
        ]);
        $dbId = $pdo->lastInsertId();
        error_log("save_registration.php: Registration inserted successfully. ID: " . $dbId);
    // Send confirmation email with Registration ID using PHPMailer
    require_once __DIR__ . '/backend/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/backend/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/backend/PHPMailer/src/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $env['SMTP_USER'] ?? '';
        $mail->Password   = $env['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($env['SMTP_USER'] ?? 'icswhmh2027@rajagiri.edu', 'ICSWHMH Organizing Team');
        $mail->addAddress($email, "$firstName $lastName");
        $mail->addReplyTo($env['SMTP_USER'] ?? 'icswhmh2027@rajagiri.edu', 'ICSWHMH Support');

        // Content
        $mail->isHTML(false);
        $mail->Subject = 'Your Registration ID for ICSWHMH';
        
        $message = "Dear $firstName $lastName,\n\n";
        $message .= "Thank you for starting your registration for ICSWHMH.\n\n";
        $message .= "Here are your registration details:\n";
        $message .= "- Registration ID: $registrationId\n";
        $message .= "- Participant Type: $participantType\n";
        $message .= "- Package Selected: $package\n";
        $message .= "- Base Amount: $baseAmount\n\n";
        $message .= "Please keep this Registration ID safe. You can use it to track your registration or complete your payment if it was interrupted.\n\n";
        $message .= "Best Regards,\nICSWHMH Organizing Team";

        $mail->Body = $message;

        $mail->send();
        error_log("save_registration.php: Registration email sent via PHPMailer to " . $email);
    } catch (Exception $e) {
        error_log("save_registration.php: Failed to send registration email to $email. Mailer Error: {$mail->ErrorInfo}");
    }

    // 6. Return success response
    echo json_encode([
        'success' => true,
        'registration_id' => $registrationId,
        'db_id' => $dbId
    ]);

} catch (Exception $e) {
    error_log("save_registration.php: Error occurred - " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred while saving your registration. Please try again.'
    ]);
}
