<?php 
require_once '../../dbcon.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $inputCode = isset($_POST['code']) ? (int)trim($_POST['code']) : 0;
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    
    if(empty($inputCode)) {
        $response = [
            'success' => false,
            'message' => 'Brak wprowadzonego kodu weryfikacyjnego'
        ];
        echo json_encode($response);
        exit();
    }
    if(!isset($_SESSION['verification_code'])) {
        $response = [
            'success' => false,
            'message' => 'Kod weryfikacyjny wygasł. Proszę wygenerować nowy kod.'
        ];
        echo json_encode($response);
        exit();
    }
    
    // Porównaj jako integer
    if($_SESSION['verification_code'] !== $inputCode) {
        $response = [
            'success' => false,
            'message' => 'Nieprawidłowy kod weryfikacyjny. Proszę spróbować ponownie.'
        ];
        echo json_encode($response);
        exit();
    }
    $response = [
        'success' => true,
        'message' => 'Kod weryfikacyjny jest poprawny.'
    ];
    if ($type === 'emailVerify') { 
        if (isset($_SESSION['userid'])) {
            $userId = $_SESSION['userid'];
            $sql = "UPDATE `accounts` SET email_confirmed = 1 WHERE id = ?";
            $prep = $conn->prepare($sql);
            $prep->bind_param("i", $userId);
            $prep->execute();
        }
    }
    echo json_encode($response);
    exit();
}