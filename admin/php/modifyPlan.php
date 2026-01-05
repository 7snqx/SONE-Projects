<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin.php");
    exit();
}

include '../../dbcon.php';
    
if (isset($_GET['id']) && isset($_GET['completed'])) {
    $id = intval($_GET['id']);
    $completed = intval($_GET['completed']);
    
    if ($completed == 1) {
        $currentDate = date('Y-m-d');
        $stmt = $conn->prepare("UPDATE plans SET completed = 1, completion_date = ? WHERE id = ?");
        $stmt->bind_param("si", $currentDate, $id);
    } else {
        $stmt = $conn->prepare("UPDATE plans SET completed = 0, completion_date = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = $completed ? "Plan oznaczony jako ukończony!" : "Plan przywrócony!";
    } else {
        $_SESSION['error_message'] = "Błąd podczas zmiany statusu.";
    }
    $stmt->close();
}

$conn->close();
header("Location: ../admin.php");
exit();
