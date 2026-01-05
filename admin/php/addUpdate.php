<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin.php");
    exit();
}

include '../../dbcon.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'];
    $changes = trim($_POST['changes']);
    
    $stmt = $conn->prepare("INSERT INTO updates (date, changes) VALUES (?, ?)");
    $stmt->bind_param("ss", $date, $changes);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Aktualizacja dodana!";
    } else {
        $_SESSION['error_message'] = "Błąd podczas dodawania.";
    }
    $stmt->close();
    $conn->close();

    header("Location: ../admin.php");
    exit();
}


