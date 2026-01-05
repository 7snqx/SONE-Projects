<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin.php");
    exit();
}

include '../../dbcon.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $icon = !empty($_POST['icon']) ? trim($_POST['icon']) : 'pending_actions';

    $stmt = $conn->prepare("INSERT INTO plans (title, description, icon, completed) VALUES (?, ?, ?, 0)");
    $stmt->bind_param("sss", $title, $description, $icon);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Plan dodany pomyślnie!";
    } else {
        $_SESSION['error_message'] = "Błąd podczas dodawania planu.";
    }
    $stmt->close();
    $conn->close();

    header("Location: ../admin.php");
    exit();
}

