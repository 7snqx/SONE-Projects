<?php
session_start();
require_once '../../dbcon.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../assets/PHPMailer/src/PHPMailer.php';
require '../../assets/PHPMailer/src/SMTP.php';
require '../../assets/PHPMailer/src/Exception.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exampleEmail = isset($_POST['email']) ? trim($_POST['email']) : '';
    $exampleType = isset($_POST['type']) ? trim($_POST['type']) : '';

    if (empty($exampleEmail)) {
        $exampleResponse = [
            'success' => false,
            'message' => 'Brak wprowadzonego adresu email'
        ];
        echo json_encode($exampleResponse);
        exit();
    }

    if ($exampleType !== 'passwordReset' && $exampleType !== 'emailVerify') {
        $exampleResponse = [
            'success' => false,
            'message' => 'Nieprawidłowy typ żądania.'
        ];
        echo json_encode($exampleResponse);
        exit();
    }

    $exampleSql = "SELECT * FROM `example_accounts_table` WHERE email = ?";
    $exampleStatement = $exampleDbConnection->prepare($exampleSql);
    $exampleStatement->bind_param("s", $exampleEmail);
    $exampleStatement->execute();
    $exampleResult = $exampleStatement->get_result();

    if ($exampleResult->num_rows > 0) {
        $exampleUserData = $exampleResult->fetch_assoc();

        // Generuj 6-cyfrowy kod
        $exampleVerificationCode = random_int(100000, 999999);

        switch ($exampleType) {
            case 'passwordReset':
                $exampleSubject = 'Prośba o reset hasła';
                $exampleBody = 'Twój kod potwierdzający: ' . $exampleVerificationCode;
                break;
            case 'emailVerify':
                $exampleSubject = 'Weryfikacja adresu email';
                $exampleBody = 'Twój kod weryfikacyjny: ' . $exampleVerificationCode;
                break;
            default:
                $exampleSubject = 'Kod weryfikacyjny';
                $exampleBody = 'Twój kod weryfikacyjny: ' . $exampleVerificationCode;
        }

        $exampleMailer = new PHPMailer(true);

        try {
            $exampleMailer->CharSet = 'UTF-8';
            $exampleMailer->Encoding = 'base64';

            // === SMTP CONFIGURATION ===
            // Copy mail_config.php.example to mail_config.php and fill in your credentials
            require_once __DIR__ . '/mail_config.php';

            $exampleMailer->isSMTP();
            $exampleMailer->Host = SMTP_HOST;
            $exampleMailer->SMTPAuth = true;
            $exampleMailer->Username = SMTP_USERNAME;
            $exampleMailer->Password = SMTP_PASSWORD;
            $exampleMailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $exampleMailer->Port = SMTP_PORT;

            $exampleMailer->setFrom(MAIL_FROM, 'SONE Projects');
            $exampleMailer->addAddress($exampleEmail);

            $exampleMailer->isHTML(false);
            $exampleMailer->Subject = $exampleSubject;
            $exampleMailer->Body = $exampleBody;

            $exampleMailer->send();

            $_SESSION['verification_code'] = $exampleVerificationCode;
            $_SESSION['verification_user_id'] = $exampleUserData['id'];


            $exampleResponse = [
                'success' => true,
                'message' => 'Kod wysłany na email.'
            ];
        } catch (Exception $e) {
            $exampleResponse = [
                'success' => false,
                'message' => 'Błąd wysyłania emaila: ' . $exampleMailer->ErrorInfo
            ];
        }
    } else {
        $exampleResponse = [
            'success' => false,
            'message' => 'Email nie istnieje w naszej bazie danych.'
        ];
    }
    echo json_encode($exampleResponse);
    exit();
}
