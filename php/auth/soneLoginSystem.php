<?php 
session_start();
// Database connection
require_once '../../dbcon.php';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usernameOrEmail = $_POST['username'];
    $password = $_POST['password'];

    if(empty($usernameOrEmail) || empty($password)) {
        $_SESSION['errorLogin'] = "Proszę wypełnić wszystkie pola.";
        $_SESSION['forceRegister'] = null;
        header("Location: ../../pages/account.php");
        exit();
    }

    $prep = $conn->prepare("SELECT * FROM `accounts` WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
    $prep->bind_param("ss", $usernameOrEmail, $usernameOrEmail);
    $prep->execute();
    $result = $prep->get_result();

    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['userid'] = $user['id'];
            $_SESSION['errorLogin'] = null;
            header("Location: ../../pages/account.php");
            exit();
        } else {
            $_SESSION['errorLogin'] = "Nieprawidłowa nazwa użytkownika lub hasło.";
            $_SESSION['forceRegister'] = null;
            header("Location: ../../pages/account.php");
            exit();
        }
    } else {
        $_SESSION['errorLogin'] = "Nieprawidłowa nazwa użytkownika lub hasło.";
        $_SESSION['forceRegister'] = null;
        header("Location: ../../pages/account.php");
        exit();
    }
}