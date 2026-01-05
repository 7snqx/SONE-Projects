<?php
require_once '../dbcon.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT * FROM projects";
$bookmarksPrep = $conn->prepare("SELECT bookmarked_id FROM accounts WHERE id = ?");
$bookmarksPrep->bind_param("i", $_SESSION['userid']);
$bookmarksPrep->execute();
$bookmarksResult = $bookmarksPrep->get_result();
$bookmarkedIds = [];
if ($bookmarksResult->num_rows > 0) {
    $bookmarksRow = $bookmarksResult->fetch_assoc();
    if (!empty($bookmarksRow['bookmarked_id'])) {
        $bookmarkedIds = explode(',', $bookmarksRow['bookmarked_id']);
    }
}
$result = $conn->query($sql);
$projects = [];
if ($result->num_rows > 0) {
    $projects = $result->fetch_all(MYSQLI_ASSOC);
}
$response = [
    'success' => true,
    'projects' => $projects,
    'bookmarkedIds' => $bookmarkedIds
];

echo json_encode($response);
exit();