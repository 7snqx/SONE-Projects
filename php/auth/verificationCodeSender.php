<?php
session_start();
require_once '../../dbcon.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../assets/PHPMailer/src/PHPMailer.php';
require '../../assets/PHPMailer/src/SMTP.php';
require '../../assets/PHPMailer/src/Exception.php';

header('Content-Type: application/json; charset=utf-8');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';

    if(empty($email)) {
        $response = [
        'success' => false,
        'message' => 'Brak wprowadzonego adresu email'
        ];
        echo json_encode($response);
        exit();
    }
    
    if($type !== 'passwordReset' && $type !== 'emailVerify') {
        $response = [
            'success' => false,
            'message' => 'Nieprawidłowy typ żądania.'
        ];
        echo json_encode($response);
        exit();
    }

    $sql = "SELECT * FROM `accounts` WHERE email = ?";
    $prep = $conn->prepare($sql);
    $prep->bind_param("s", $email);
    $prep->execute();
    $result = $prep->get_result();

    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Generuj 6-cyfrowy kod
        $code = random_int(100000, 999999);

        switch ($type) {
            case 'passwordReset':
                $subject = 'Prośba o reset hasła';
                $body = 'Twój kod potwierdzający: ' . $code;
                break;
            case 'emailVerify':
                $subject = 'Weryfikacja adresu email';
                $body = 'Twój kod weryfikacyjny: ' . $code;
                break;
            default:
                $subject = 'Kod weryfikacyjny';
                $body = 'Twój kod weryfikacyjny: ' . $code;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            // === SMTP CONFIGURATION ===
            // Copy mail_config.php.example to mail_config.php and fill in your credentials
            require_once __DIR__ . '/mail_config.php';
            
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(MAIL_FROM, 'SONE Projects');
            $mail->addAddress($email);

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();

            $_SESSION['verification_code'] = $code;
            $_SESSION['verification_user_id'] = $user['id'];

            
            $response = [
                'success' => true,
                'message' => 'Kod wysłany na email.'
            ];
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'message' => 'Błąd wysyłania emaila: ' . $mail->ErrorInfo
            ];
        }
    } else {
        $response = [
        'success' => false,
        'message' => 'Email nie istnieje w naszej bazie danych.'
        ];
    }
    echo json_encode($response);
    exit();
}