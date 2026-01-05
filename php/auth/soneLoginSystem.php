<?php
session_start();
// Database connection
require_once '../../dbcon.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exampleUsernameOrEmail = $_POST['username'];
    $examplePassword = $_POST['password'];

    if (empty($exampleUsernameOrEmail) || empty($examplePassword)) {
        $_SESSION['errorLogin'] = "Proszę wypełnić wszystkie pola.";
        $_SESSION['forceRegister'] = null;
        header("Location: ../../pages/account.php");
        exit();
    }

    $exampleStatement = $exampleDbConnection->prepare("SELECT * FROM `example_accounts_table` WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
    $exampleStatement->bind_param("ss", $exampleUsernameOrEmail, $exampleUsernameOrEmail);
    $exampleStatement->execute();
    $exampleResult = $exampleStatement->get_result();

    if ($exampleResult->num_rows > 0) {
        $exampleUser = $exampleResult->fetch_assoc();
        if (password_verify($examplePassword, $exampleUser['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['userid'] = $exampleUser['id'];
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