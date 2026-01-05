<?php 
require_once '../dbcon.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['userid'] ?? null;
if(!isset($userId)) {
    $response = [
        'success' => false,
        'message' => 'Brak sesji użytkownika'
    ];
    echo json_encode($response);
    exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !$userId) {
    $response = [
        'success' => false,
        'message' => 'Invalid request'
    ];
    echo json_encode($response);
    exit;
}

$projectId = (int)($_POST['projectId'] ?? null);
$type = $_POST['type'] ?? null;
if(!$projectId || !$type) {
    $response = [
        'success' => false,
        'message' => 'Brak ID projektu lub typu akcji'
    ];
    echo json_encode($response);
    exit;
}

$prepareBookmarkSql = $conn->prepare("SELECT bookmarked_id FROM accounts WHERE id = ?;");
$prepareBookmarkSql->bind_param("i", $userId);
$prepareBookmarkSql->execute();
$bookmarkResult = $prepareBookmarkSql->get_result();
if ($bookmarkResult->num_rows > 0) {
    $bookmarks = $bookmarkResult->fetch_assoc()['bookmarked_id'];
    $bookmarksArray = $bookmarks ? array_map('intval', explode(',', $bookmarks)) : [];
    switch($type) {
        case('add'):
            if(!in_array($projectId, $bookmarksArray)) {
                $bookmarksArray[] = $projectId;
            }
            break;
        case('remove'):
            if(in_array($projectId, $bookmarksArray)) {
                $bookmarksArray = array_diff($bookmarksArray, [$projectId]);
            }
            break;
    }
    $newBookmarks = implode(',', $bookmarksArray);
    $updateBookmarkSql = $conn->prepare("UPDATE accounts SET bookmarked_id = ? WHERE id = ?;");
    $updateBookmarkSql->bind_param("si", $newBookmarks, $userId);
    if($updateBookmarkSql->execute()) {
        $response = [
            'success' => true,
            'message' => $bookmarks . implode(',', $bookmarksArray) . $newBookmarks
        ];
    } else {
        $response = [           
            'success' => false,
            'message' => 'Failed to update bookmark'
        ];
    }
} else {
    $response = [
        'success' => false,
        'message' => 'User not found'
    ];
}

echo json_encode($response);