<?php 
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin.php");
    exit();
}

include '../../dbcon.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $url = trim($_POST['url']);
    $image = trim($_POST['image']);
    $badge = isset($_POST['badge']) ? trim($_POST['badge']) : null;
    $description = trim($_POST['description']);
    $currentDate = date('Y-m-d');

    $stmt = $conn->prepare("UPDATE projects SET title = ?, url = ?, image = ?, badge = ?, description = ?, lastUpdate = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $title, $url, $image, $badge, $description, $currentDate, $id);
            
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Projekt zaktualizowany!";
    } else {
        $_SESSION['error_message'] = "Błąd podczas modyfikacji projektu.";
    }
    $stmt->close();
    $conn->close();

    header("Location: ../admin.php");
    exit();
}
