<?php
require_once '../../dbcon.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exampleNewPassword = isset($_POST['newPassword']) ? trim($_POST['newPassword']) : '';
    if (empty($exampleNewPassword)) {
        $exampleResponse = [
            'success' => false,
            'message' => 'Proszę wprowadzić nowe hasło.'
        ];
        echo json_encode($exampleResponse);
        exit();
    }
    if (!isset($_SESSION['verification_user_id'])) {
        $exampleResponse = [
            'success' => false,
            'message' => 'Sesja wygasła. Proszę ponownie przejść proces resetu hasła.'
        ];
        echo json_encode($exampleResponse);
        exit();
    }
    $exampleUserId = $_SESSION['verification_user_id'];
    $exampleHashedPassword = password_hash($exampleNewPassword, PASSWORD_BCRYPT);
    $exampleStatement = $conn->prepare("UPDATE `example_accounts_table` SET password = ? WHERE id = ?");
    $exampleStatement->bind_param("si", $exampleHashedPassword, $exampleUserId);
    if ($exampleStatement->execute()) {
        $exampleResponse = [
            'success' => true,
            'message' => 'Hasło zostało pomyślnie zaktualizowane.'
        ];
    } else {
        $exampleResponse = [
            'success' => false,
            'message' => 'Wystąpił błąd podczas aktualizacji hasła.'
        ];
    }
    echo json_encode($exampleResponse);
    exit();
}