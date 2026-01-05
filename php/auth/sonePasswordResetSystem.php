<?php 
require_once '../../dbcon.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newPassword = isset($_POST['newPassword']) ? trim($_POST['newPassword']) : '';
    if(empty($newPassword)) {
        $response = [
            'success' => false,
            'message' => 'Proszę wprowadzić nowe hasło.'
        ];
        echo json_encode($response);
        exit();
    }
    if(!isset($_SESSION['verification_user_id'])) {
        $response = [
            'success' => false,
            'message' => 'Sesja wygasła. Proszę ponownie przejść proces resetu hasła.'
        ];
        echo json_encode($response);
        exit();
    }
    $userId = $_SESSION['verification_user_id'];
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $prep = $conn->prepare("UPDATE `accounts` SET password = ? WHERE id = ?");
    $prep->bind_param("si", $hashedPassword, $userId);
    if ($prep->execute()) {
        $response = [
            'success' => true,
            'message' => 'Hasło zostało pomyślnie zaktualizowane.'
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Wystąpił błąd podczas aktualizacji hasła.'
        ];
    }
    echo json_encode($response);
    exit();
}