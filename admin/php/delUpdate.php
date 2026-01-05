<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin.php");
    exit();
}

include '../../dbcon.php';
    
if (isset($_POST['delete'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM updates WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success_message_update'] = "Aktualizacja została usunięta";
    } else {
        $_SESSION['error_message_update'] = "Błąd podczas usuwania.";
    }
    $stmt->close();
}

$conn->close();
header("Location: ../admin.php");
exit();