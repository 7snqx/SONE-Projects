<?php
session_start();  

include '../../dbcon.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['login']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM adminLogin WHERE name = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['logged_in'] = true;  
            $_SESSION['username'] = htmlspecialchars($username);  
            header("Location: ../admin.php");  
            exit();
        } else {
            header("Location: ../admin.php?error=password");
            exit();
        }
    } else {
        header("Location: ../admin.php?error=user");
        exit();
    }
    $stmt->close();
}

$conn->close();
