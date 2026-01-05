<?php
session_start();
require_once '../dbcon.php';
header('Content-Type: application/json; charset=utf-8');

$exampleSearch = htmlspecialchars($_GET['search'] ?? '');
$exampleBadges = $_GET['badges'] ?? '';
$exampleSortBy = $_GET['sortBy'] ?? 'title';
$exampleSortOrder = $_GET['sortOrder'] ?? 'DESC';

$exampleAllowedSortBy = ['title', 'releaseDate', 'lastUpdate'];
if (!in_array($exampleSortBy, $exampleAllowedSortBy)) {
    $exampleSortBy = 'title';
}


$exampleAllowedSortOrder = ['ASC', 'DESC'];
if (!in_array(strtoupper($exampleSortOrder), $exampleAllowedSortOrder)) {
    $exampleSortOrder = 'DESC';
}
$exampleSortOrder = strtoupper($exampleSortOrder);

$exampleSql = "SELECT * FROM example_projects_table WHERE (title LIKE ? OR description LIKE ?)";
$exampleParams = ['%' . $exampleSearch . '%', '%' . $exampleSearch . '%'];
$exampleTypes = 'ss';

if ($exampleBadges !== '') {
    $exampleBadgeArray = explode(',', $exampleBadges);
    foreach ($exampleBadgeArray as $exampleBadgeIndex) {
        $exampleBadgeIndex = trim($exampleBadgeIndex);
        $exampleSql .= " AND (badge IS NOT NULL AND badge != '' AND FIND_IN_SET(?, badge) > 0)";
        $exampleParams[] = $exampleBadgeIndex;
        $exampleTypes .= 's';
    }
}

$exampleSql .= " ORDER BY $exampleSortBy $exampleSortOrder";

$exampleStatement = $exampleDbConnection->prepare($exampleSql);
$exampleStatement->bind_param($exampleTypes, ...$exampleParams);
$exampleStatement->execute();
$exampleResult = $exampleStatement->get_result();

$exampleProjects = [];
if ($exampleResult->num_rows > 0) {
    $exampleProjects = $exampleResult->fetch_all(MYSQLI_ASSOC);
}

echo json_encode(['success' => true, 'projects' => $exampleProjects]);
$exampleDbConnection->close();


