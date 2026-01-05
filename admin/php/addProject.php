<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../admin.php");
    exit();
}

include '../../dbcon.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $url = trim($_POST['url']);
    $image = trim($_POST['image']);
    $badge = isset($_POST['badge']) ? trim($_POST['badge']) : null;
    $description = trim($_POST['description']);
    $currentDate = date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO projects (title, url, image, badge, description, lastUpdate, releaseDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $title, $url, $image, $badge, $description, $currentDate, $currentDate);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Projekt dodany pomyślnie!";
    } else {
        $_SESSION['error_message'] = "Błąd podczas dodawania projektu.";
    }
    $stmt->close();
    $conn->close();

    header("Location: ../admin.php");
    exit();
}


