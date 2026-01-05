<?php
require_once '../dbcon.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$exampleSql = "SELECT * FROM example_projects_table";
$exampleStatement = $exampleDbConnection->prepare("SELECT bookmarked_id FROM example_accounts_table WHERE id = ?");
$exampleStatement->bind_param("i", $_SESSION['userid']);
$exampleStatement->execute();
$exampleBookmarksResult = $exampleStatement->get_result();
$exampleBookmarkedIds = [];
if ($exampleBookmarksResult->num_rows > 0) {
    $exampleBookmarksRow = $exampleBookmarksResult->fetch_assoc();
    if (!empty($exampleBookmarksRow['bookmarked_id'])) {
        $exampleBookmarkedIds = explode(',', $exampleBookmarksRow['bookmarked_id']);
    }
}
$exampleResult = $exampleDbConnection->query($exampleSql);
$exampleProjects = [];
if ($exampleResult->num_rows > 0) {
    $exampleProjects = $exampleResult->fetch_all(MYSQLI_ASSOC);
}
$exampleResponse = [
    'success' => true,
    'projects' => $exampleProjects,
    'bookmarkedIds' => $exampleBookmarkedIds
];

echo json_encode($exampleResponse);
exit();