<?php
session_start();
// Database connection
require_once '../../dbcon.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../assets/PHPMailer/src/PHPMailer.php';
require '../../assets/PHPMailer/src/SMTP.php';
require '../../assets/PHPMailer/src/Exception.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exampleUsername = $_POST['username'];
    $exampleEmail = $_POST['email'];
    $examplePassword = $_POST['password'];
    $exampleConfirmPassword = $_POST['confirmPassword'];
    if (empty($exampleUsername) || empty($exampleEmail) || empty($examplePassword) || empty($exampleConfirmPassword)) {
        $_SESSION['errorRegister'] = "Proszę wypełnić wszystkie pola.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    if (!filter_var($exampleEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['errorRegister'] = "Nieprawidłowy format adresu email.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    $exampleStatement = $exampleDbConnection->prepare("SELECT * FROM `example_accounts_table` WHERE LOWER(username) = LOWER(?)");
    $exampleStatement->bind_param("s", $exampleUsername);
    $exampleStatement->execute();
    $exampleResult = $exampleStatement->get_result();
    if ($exampleResult->num_rows > 0) {
        $_SESSION['errorRegister'] = "Nazwa użytkownika jest już zajęta.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    $exampleStatement = $exampleDbConnection->prepare("SELECT * FROM `example_accounts_table` WHERE LOWER(email) = LOWER(?)");
    $exampleStatement->bind_param("s", $exampleEmail);
    $exampleStatement->execute();
    $exampleResult = $exampleStatement->get_result();
    if ($exampleResult->num_rows > 0) {
        $_SESSION['errorRegister'] = "Email jest już zajęty.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    if ($examplePassword !== $exampleConfirmPassword) {
        $_SESSION['errorRegister'] = "Hasła nie są identyczne.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    $exampleCode = random_int(100000, 999999);

    $exampleMailer = new PHPMailer(true);

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
    $exampleMailer->Subject = 'Kod potwierdzający';
    $exampleMailer->Body = 'Twój kod: ' . $exampleCode;

    $exampleMailer->send();

    $_SESSION['verification_code'] = $exampleCode;

    $exampleHashedPassword = password_hash($examplePassword, PASSWORD_BCRYPT);
    $exampleStatement = $exampleDbConnection->prepare("INSERT INTO `example_accounts_table` (username, email, password) VALUES (?, ?, ?)");
    $exampleStatement->bind_param("sss", $exampleUsername, $exampleEmail, $exampleHashedPassword);
    $exampleStatement->execute();
    $_SESSION['forceRegister'] = null;
    header("Location: ../../pages/account.php");
    exit();
}

