<?php
/**
 * Debug insights - show structure of verification insights
 */

define('CRON_SECRET_KEY', 'YDh3YpHfIl');

if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Forbidden');
    }
}

$insightsFile = __DIR__ . '/history/insights.json';

echo "=== INSIGHTS DEBUG ===\n\n";

if (!file_exists($insightsFile)) {
    die("No insights.json found\n");
}

$insights = json_decode(file_get_contents($insightsFile), true) ?? [];
echo "Total insights: " . count($insights) . "\n\n";

// Group by type
$byType = [];
foreach ($insights as $insight) {
    $type = $insight['type'] ?? 'unknown';
    if (!isset($byType[$type])) {
        $byType[$type] = [];
    }
    $byType[$type][] = $insight;
}

echo "=== BY TYPE ===\n";
foreach ($byType as $type => $list) {
    echo "  {$type}: " . count($list) . "\n";
}

echo "\n=== VERIFICATION INSIGHTS (for charts) ===\n";
$verifications = $byType['verification'] ?? [];

if (empty($verifications)) {
    echo "NO VERIFICATION INSIGHTS FOUND!\n";
    echo "Charts require insights with type='verification' and details.predicted/actual/accuracy\n";
} else {
    foreach ($verifications as $v) {
        $forDate = $v['details']['forDate'] ?? 'N/A';
        $predicted = $v['details']['predicted'] ?? 'MISSING';
        $actual = $v['details']['actual'] ?? 'MISSING';
        $accuracy = $v['details']['accuracy'] ?? 'MISSING';
        echo "  - {$forDate}: pred={$predicted}, act={$actual}, acc={$accuracy}\n";
    }
}

echo "\n=== RECENT 10 INSIGHTS ===\n";
$recent = array_slice($insights, -10);
foreach ($recent as $i) {
    $type = $i['type'] ?? 'unknown';
    $title = $i['title'] ?? 'No title';
    $date = $i['date'] ?? 'No date';
    echo "  [{$type}] {$title} ({$date})\n";
}
