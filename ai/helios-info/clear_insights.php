<?php
/**
 * Clear Insights for a Specific Date
 * 
 * Usage: clear_insights.php?key=YDh3YpHfIl&date=2025-12-22
 * 
 * This will remove all insights that have forDate matching the specified date.
 */

// Security key - same as CRON
define('CRON_SECRET_KEY', 'YDh3YpHfIl');

// Allow CLI or authenticated HTTP
if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Forbidden');
    }
}

// Get date parameter
$date = $_GET['date'] ?? null;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    die("Usage: clear_insights.php?key=YOUR_KEY&date=YYYY-MM-DD\n\nExample: clear_insights.php?key=YDh3YpHfIl&date=2025-12-22\n");
}

// Load insights
$historyDir = __DIR__ . '/history';
$insightsFile = $historyDir . '/insights.json';

if (!file_exists($insightsFile)) {
    die("No insights.json file found.\n");
}

$insights = json_decode(file_get_contents($insightsFile), true);
if (!is_array($insights)) {
    die("Error: Could not parse insights.json\n");
}

$originalCount = count($insights);

// Filter out insights for the specified date
// ONLY checks forDate (date insight is ABOUT), NOT date (when generated)
$filteredInsights = array_filter($insights, function($insight) use ($date) {
    // Check ONLY forDate in details - this is the date the insight is ABOUT
    if (isset($insight['details']['forDate']) && $insight['details']['forDate'] === $date) {
        return false; // Remove this insight
    }
    return true; // Keep this insight
});

// Re-index array
$filteredInsights = array_values($filteredInsights);

$removedCount = $originalCount - count($filteredInsights);

if ($removedCount === 0) {
    echo "No insights found for date: {$date}\n";
    echo "Total insights in file: {$originalCount}\n";
    exit(0);
}

// Save filtered insights
$saved = file_put_contents($insightsFile, json_encode($filteredInsights, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($saved) {
    echo "SUCCESS! Removed {$removedCount} insight(s) for date: {$date}\n";
    echo "Remaining insights: " . count($filteredInsights) . "\n";
} else {
    echo "ERROR: Could not save insights.json\n";
}
