<?php 
session_start();
require_once '../dbcon.php';
header('Content-Type: application/json; charset=utf-8');

$search = htmlspecialchars($_GET['search'] ?? '');
$badges = $_GET['badges'] ?? '';
$sortBy = $_GET['sortBy'] ?? 'title';
$sortOrder = $_GET['sortOrder'] ?? 'DESC';

$allowedSortBy = ['title', 'releaseDate', 'lastUpdate'];
if (!in_array($sortBy, $allowedSortBy)) {
    $sortBy = 'title';
}


$allowedSortOrder = ['ASC', 'DESC'];
if (!in_array(strtoupper($sortOrder), $allowedSortOrder)) {
    $sortOrder = 'DESC';
}
$sortOrder = strtoupper($sortOrder);

$sql = "SELECT * FROM projects WHERE (title LIKE ? OR description LIKE ?)";
$params = ['%' . $search . '%', '%' . $search . '%'];
$types = 'ss';

if ($badges !== '') {
    $badgeArray = explode(',', $badges);
    foreach ($badgeArray as $badgeIndex) {
        $badgeIndex = trim($badgeIndex);
        $sql .= " AND (badge IS NOT NULL AND badge != '' AND FIND_IN_SET(?, badge) > 0)";
        $params[] = $badgeIndex;
        $types .= 's';
    }
}

$sql .= " ORDER BY $sortBy $sortOrder";

$prep = $conn->prepare($sql);
$prep->bind_param($types, ...$params);
$prep->execute();
$result = $prep->get_result();

$projects = [];
if ($result->num_rows > 0) {
    $projects = $result->fetch_all(MYSQLI_ASSOC);
}

echo json_encode(['success' => true, 'projects' => $projects]);
$conn->close();
    

