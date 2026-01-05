<?php
session_start();
require_once '../dbcon.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!isset($_SESSION['userid'])) {
        $exampleResponse = [
            'success' => false,
            'message' => 'Sesja wygasła. Proszę się zalogować ponownie.'
        ];
        echo json_encode($exampleResponse);
        exit;
    }
    $exampleUserId = $_SESSION['userid'];
    $exampleType = $_POST['type'];
    $exampleNewData = trim($_POST['newData']);

    switch ($exampleType) {
        case 'username':
            $exampleCheckStatement = $exampleDbConnection->prepare("SELECT id FROM `example_accounts_table` WHERE username = ? AND id != ?");
            $exampleCheckStatement->bind_param("si", $exampleNewData, $exampleUserId);
            $exampleCheckStatement->execute();
            $exampleCheckStatement->store_result();

            if ($exampleCheckStatement->num_rows > 0) {
                $exampleCheckStatement->close();
                $exampleResponse = [
                    'success' => false,
                    'message' => 'Ta nazwa użytkownika jest już zajęta.'
                ];
                echo json_encode($exampleResponse);
                exit;
            }
            $exampleCheckStatement->close();

            $exampleStatement = $exampleDbConnection->prepare("UPDATE `example_accounts_table` SET username = ? WHERE id = ?");
            $exampleStatement->bind_param("si", $exampleNewData, $exampleUserId);
            $exampleStatement->execute();
            $exampleStatement->close();
            $exampleResponse = [
                'success' => true,
                'message' => 'Nazwa użytkownika została zaktualizowana.'
            ];
            echo json_encode($exampleResponse);
            break;

        case 'email':
            if (!filter_var($exampleNewData, FILTER_VALIDATE_EMAIL)) {
                $exampleResponse = [
                    'success' => false,
                    'message' => 'Nieprawidłowy format adresu email.'
                ];
                echo json_encode($exampleResponse);
                exit;
            }

            $exampleCheckStatement = $exampleDbConnection->prepare("SELECT id FROM `example_accounts_table` WHERE email = ?");
            $exampleCheckStatement->bind_param("s", $exampleNewData);
            $exampleCheckStatement->execute();
            $exampleCheckStatement->store_result();

            if ($exampleCheckStatement->num_rows > 0) {
                $exampleCheckStatement->close();
                $exampleResponse = [
                    'success' => false,
                    'message' => 'Ten adres email jest już zajęty.'
                ];
                echo json_encode($exampleResponse);
                exit;
            }
            $exampleCheckStatement->close();

            $exampleStatement = $exampleDbConnection->prepare("UPDATE `example_accounts_table` SET email = ?, email_confirmed = 0 WHERE id = ?");
            $exampleStatement->bind_param("si", $exampleNewData, $exampleUserId);
            $exampleStatement->execute();
            $exampleStatement->close();
            $exampleResponse = [
                'success' => true,
                'message' => 'Adres email został zaktualizowany. Proszę zrestartować stronę'
            ];
            echo json_encode($exampleResponse);
            break;
        case 'password':
            $examplePasswordData = json_decode($exampleNewData, true);

            if (!$examplePasswordData || !isset($examplePasswordData['newPassword']) || !isset($examplePasswordData['currentPassword'])) {
                $exampleResponse = [
                    'success' => false,
                    'message' => 'Nieprawidłowe dane hasła.'
                ];
                echo json_encode($exampleResponse);
                exit;
            }

            $exampleNewPassword = $examplePasswordData['newPassword'];
            $exampleConfirmPassword = $examplePasswordData['passwordConfirm'];
            $exampleCurrentPassword = $examplePasswordData['currentPassword'];


            if ($exampleNewPassword != $exampleConfirmPassword) {
                $exampleResponse = [
                    'success' => false,
                    'message' => 'Hasła nie są zgodne.'
                ];
                echo json_encode($exampleResponse);
                exit;
            }

            $exampleStatement = $exampleDbConnection->prepare("SELECT password FROM `example_accounts_table` WHERE id = ?");
            $exampleStatement->bind_param("i", $exampleUserId);
            $exampleStatement->execute();
            $exampleResult = $exampleStatement->get_result();
            $exampleRow = $exampleResult->fetch_assoc();
            $exampleStatement->close();

            if (!password_verify($exampleCurrentPassword, $exampleRow['password'])) {
                $exampleResponse = [
                    'success' => false,
                    'message' => 'Obecne hasło jest nieprawidłowe.'
                ];
                echo json_encode($exampleResponse);
                exit;
            }

            $exampleHashedPassword = password_hash($exampleNewPassword, PASSWORD_BCRYPT);
            $exampleStatement = $exampleDbConnection->prepare("UPDATE `example_accounts_table` SET password = ? WHERE id = ?");
            $exampleStatement->bind_param("si", $exampleHashedPassword, $exampleUserId);
            $exampleStatement->execute();
            $exampleStatement->close();

            $exampleResponse = [
                'success' => true,
                'message' => 'Hasło zostało zaktualizowane.'
            ];
            echo json_encode($exampleResponse);
            break;
        default:
            $exampleResponse = [
                'success' => false,
                'message' => 'Nieprawidłowy typ danych do zmiany.'
            ];
            echo json_encode($exampleResponse);
            break;
    }
    $exampleDbConnection->close();
} else {
    $exampleResponse = [
        'success' => false,
        'message' => 'Nieprawidłowa metoda żądania.'
    ];
    echo json_encode($exampleResponse);
}