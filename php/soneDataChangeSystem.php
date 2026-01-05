<?php 
session_start();
require_once '../dbcon.php';

header('Content-Type: application/json; charset=utf-8');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if(!isset($_SESSION['userid'])) {
          $response = [
            'success' => false,
            'message' => 'Sesja wygasła. Proszę się zalogować ponownie.'
        ];
        echo json_encode($response);
        exit;
    }
    $userId = $_SESSION['userid'];
    $type = $_POST['type'];
    $newData = trim($_POST['newData']);

    switch($type) {
        case 'username':
            $checkPrep = $conn->prepare("SELECT id FROM `accounts` WHERE username = ? AND id != ?");
            $checkPrep->bind_param("si", $newData, $userId);
            $checkPrep->execute();
            $checkPrep->store_result();
            
            if($checkPrep->num_rows > 0) {
                $checkPrep->close();
                $response = [
                    'success' => false,
                    'message' => 'Ta nazwa użytkownika jest już zajęta.'
                ];
                echo json_encode($response);
                exit;
            }
            $checkPrep->close();
            
            $prep = $conn->prepare("UPDATE `accounts` SET username = ? WHERE id = ?");
            $prep->bind_param("si", $newData, $userId);
            $prep->execute();
            $prep->close();
            $response = [
                'success' => true,
                'message' => 'Nazwa użytkownika została zaktualizowana.'
            ];
            echo json_encode($response);
            break;

        case 'email':
            if(!filter_var($newData, FILTER_VALIDATE_EMAIL)) {
                $response = [
                    'success' => false,
                    'message' => 'Nieprawidłowy format adresu email.'
                ];
                echo json_encode($response);
                exit;
            }

            $checkPrep = $conn->prepare("SELECT id FROM `accounts` WHERE email = ?");
            $checkPrep->bind_param("s", $newData);
            $checkPrep->execute();
            $checkPrep->store_result();
            
            if($checkPrep->num_rows > 0) {
                $checkPrep->close();
                $response = [
                    'success' => false,
                    'message' => 'Ten adres email jest już zajęty.'
                ];
                echo json_encode($response);
                exit;
            }
            $checkPrep->close();
            
            $prep = $conn->prepare("UPDATE `accounts` SET email = ?, email_confirmed = 0 WHERE id = ?");
            $prep->bind_param("si", $newData, $userId);
            $prep->execute();
            $prep->close();
            $response = [
                'success' => true,
                'message' => 'Adres email został zaktualizowany. Proszę zrestartować stronę'
            ];
            echo json_encode($response);
            break;
        case 'password':
            $passwordData = json_decode($newData, true);
            
            if(!$passwordData || !isset($passwordData['newPassword']) || !isset($passwordData['currentPassword'])) {
                $response = [
                    'success' => false,
                    'message' => 'Nieprawidłowe dane hasła.'
                ];
                echo json_encode($response);
                exit;
            }
            
            $newPassword = $passwordData['newPassword'];
            $confirmPassword = $passwordData['passwordConfirm'];
            $currentPassword = $passwordData['currentPassword'];


            if($newPassword != $confirmPassword) {
                $response = [
                    'success' => false,
                    'message' => 'Hasła nie są zgodne.'
                ];
                echo json_encode($response);
                exit;
            }
            
            $prep = $conn->prepare("SELECT password FROM `accounts` WHERE id = ?");
            $prep->bind_param("i", $userId);
            $prep->execute();
            $result = $prep->get_result();
            $row = $result->fetch_assoc();
            $prep->close();
            
            if(!password_verify($currentPassword, $row['password'])) {
                $response = [
                    'success' => false,
                    'message' => 'Obecne hasło jest nieprawidłowe.'
                ];
                echo json_encode($response);
                exit;
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $prep = $conn->prepare("UPDATE `accounts` SET password = ? WHERE id = ?");
            $prep->bind_param("si", $hashedPassword, $userId);
            $prep->execute();
            $prep->close();
            
            $response = [
                'success' => true,
                'message' => 'Hasło zostało zaktualizowane.'
            ];
            echo json_encode($response);
            break;
        default:
            $response = [
                'success' => false,
                'message' => 'Nieprawidłowy typ danych do zmiany.'
            ];
            echo json_encode($response);
            break;
    }
    $conn->close();
} else {
    $response = [
        'success' => false,
        'message' => 'Nieprawidłowa metoda żądania.'
    ];
    echo json_encode($response);
}