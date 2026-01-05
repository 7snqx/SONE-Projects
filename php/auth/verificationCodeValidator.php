<?php
require_once '../../dbcon.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exampleInputCode = isset($_POST['code']) ? (int) trim($_POST['code']) : 0;
    $exampleType = isset($_POST['type']) ? trim($_POST['type']) : '';

    if (empty($exampleInputCode)) {
        $exampleResponse = [
            'success' => false,
            'message' => 'Brak wprowadzonego kodu weryfikacyjnego'
        ];
        echo json_encode($exampleResponse);
        exit();
    }
    if (!isset($_SESSION['verification_code'])) {
        $exampleResponse = [
            'success' => false,
            'message' => 'Kod weryfikacyjny wygasł. Proszę wygenerować nowy kod.'
        ];
        echo json_encode($exampleResponse);
        exit();
    }

    // Porównaj jako integer
    if ($_SESSION['verification_code'] !== $exampleInputCode) {
        $exampleResponse = [
            'success' => false,
            'message' => 'Nieprawidłowy kod weryfikacyjny. Proszę spróbować ponownie.'
        ];
        echo json_encode($exampleResponse);
        exit();
    }
    $exampleResponse = [
        'success' => true,
        'message' => 'Kod weryfikacyjny jest poprawny.'
    ];
    if ($exampleType === 'emailVerify') {
        if (isset($_SESSION['userid'])) {
            $exampleUserId = $_SESSION['userid'];
            $exampleSql = "UPDATE `example_accounts_table` SET email_confirmed = 1 WHERE id = ?";
            $exampleStatement = $conn->prepare($exampleSql);
            $exampleStatement->bind_param("i", $exampleUserId);
            $exampleStatement->execute();
        }
    }
    echo json_encode($exampleResponse);
    exit();
}