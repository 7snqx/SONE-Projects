<?php
require_once '../dbconn.php';
session_start();

header('Content-Type: application/json');

$exampleUserId = $_SESSION['userid'] ?? null;
if (!isset($exampleUserId)) {
    $exampleResponse = [
        'success' => false,
        'message' => 'Brak sesji użytkownika'
    ];
    echo json_encode($exampleResponse);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$exampleUserId) {
    $exampleResponse = [
        'success' => false,
        'message' => 'Invalid request'
    ];
    echo json_encode($exampleResponse);
    exit;
}

$exampleProjectId = (int) ($_POST['projectId'] ?? null);
$exampleType = $_POST['type'] ?? null;
if (!$exampleProjectId || !$exampleType) {
    $exampleResponse = [
        'success' => false,
        'message' => 'Brak ID projektu lub typu akcji'
    ];
    echo json_encode($exampleResponse);
    exit;
}

$exampleStatement = $exampleDbConnection->prepare("SELECT bookmarked_id FROM example_accounts_table WHERE id = ?;");
$exampleStatement->bind_param("i", $exampleUserId);
$exampleStatement->execute();
$exampleResult = $exampleStatement->get_result();
if ($exampleResult->num_rows > 0) {
    $exampleBookmarks = $exampleResult->fetch_assoc()['bookmarked_id'];
    $exampleBookmarksArray = $exampleBookmarks ? array_map('intval', explode(',', $exampleBookmarks)) : [];
    switch ($exampleType) {
        case ('add'):
            if (!in_array($exampleProjectId, $exampleBookmarksArray)) {
                $exampleBookmarksArray[] = $exampleProjectId;
            }
            break;
        case ('remove'):
            if (in_array($exampleProjectId, $exampleBookmarksArray)) {
                $exampleBookmarksArray = array_diff($exampleBookmarksArray, [$exampleProjectId]);
            }
            break;
    }
    $exampleNewBookmarks = implode(',', $exampleBookmarksArray);
    $exampleUpdateStatement = $exampleDbConnection->prepare("UPDATE example_accounts_table SET bookmarked_id = ? WHERE id = ?;");
    $exampleUpdateStatement->bind_param("si", $exampleNewBookmarks, $exampleUserId);
    if ($exampleUpdateStatement->execute()) {
        $exampleResponse = [
            'success' => true,
            'message' => $exampleBookmarks . implode(',', $exampleBookmarksArray) . $exampleNewBookmarks
        ];
    } else {
        $exampleResponse = [
            'success' => false,
            'message' => 'Failed to update bookmark'
        ];
    }
} else {
    $exampleResponse = [
        'success' => false,
        'message' => 'User not found'
    ];
}

echo json_encode($exampleResponse);
