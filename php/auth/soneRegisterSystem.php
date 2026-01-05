<?php 
session_start();
// Database connection
require_once '../../dbcon.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../assets/PHPMailer/src/PHPMailer.php';
require '../../assets/PHPMailer/src/SMTP.php';
require '../../assets/PHPMailer/src/Exception.php';


if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    if(empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $_SESSION['errorRegister'] = "Proszę wypełnić wszystkie pola.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['errorRegister'] = "Nieprawidłowy format adresu email.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    $prep = $conn->prepare("SELECT * FROM `accounts` WHERE LOWER(username) = LOWER(?)");
    $prep->bind_param("s", $username);
    $prep->execute();
    $result = $prep->get_result();
    if($result->num_rows > 0) {
        $_SESSION['errorRegister'] = "Nazwa użytkownika jest już zajęta.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    $prep = $conn->prepare("SELECT * FROM `accounts` WHERE LOWER(email) = LOWER(?)");
    $prep->bind_param("s", $email);
    $prep->execute();
    $result = $prep->get_result();
    if($result->num_rows > 0) {
        $_SESSION['errorRegister'] = "Email jest już zajęty.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    if($password !== $confirmPassword) {
        $_SESSION['errorRegister'] = "Hasła nie są identyczne.";
        $_SESSION['forceRegister'] = true;
        header("Location: ../../pages/account.php");
        exit();
    }

    $code = random_int(100000, 999999);

    $mail = new PHPMailer(true);

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
    $mail->Subject = 'Kod potwierdzający';
    $mail->Body = 'Twój kod: ' . $code;

    $mail->send();

    $_SESSION['verification_code'] = $code;

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $prep = $conn->prepare("INSERT INTO `accounts` (username, email, password) VALUES (?, ?, ?)");
    $prep->bind_param("sss", $username, $email, $hashedPassword);
    $prep->execute();
    $_SESSION['forceRegister'] = null;
    header("Location: ../../pages/account.php");
    exit();
}

