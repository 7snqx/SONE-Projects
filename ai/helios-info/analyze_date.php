<?php
/**
 * Analyze a specific date for Factor Learning
 * Does NOT save any history data - just runs learnFromDay() for the date
 * 
 * Usage: analyze_date.php?key=YDh3YpHfIl&date=2025-12-22
 */

define('CRON_SECRET_KEY', 'YDh3YpHfIl');

if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Forbidden');
    }
}

date_default_timezone_set('Europe/Warsaw');

$date = $_GET['date'] ?? null;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    die("Usage: analyze_date.php?key=YOUR_KEY&date=YYYY-MM-DD\n\nExample: analyze_date.php?key=YDh3YpHfIl&date=2025-12-22\n");
}

$historyDir = __DIR__ . '/history';
$cacheDir = __DIR__ . '/cache';

echo "=== ANALYZING DATE: {$date} ===\n\n";

// Check if history file exists for this date
$historyFile = $historyDir . '/' . $date . '.json';
if (!file_exists($historyFile)) {
    die("ERROR: No history file found for {$date}\n");
}

$historyData = json_decode(file_get_contents($historyFile), true);
if (!$historyData || !isset($historyData['totals']['occupied'])) {
    die("ERROR: Invalid history data for {$date}\n");
}

$actualOccupied = $historyData['totals']['occupied'];
echo "Found history: {$actualOccupied} viewers\n\n";

// Load FactorLearningSystem
require_once __DIR__ . '/FactorLearningSystem.php';
$factorLearning = new FactorLearningSystem($cacheDir);

// Run learning
echo "Running Factor Learning for {$date}...\n";
$result = $factorLearning->learnFromDay($date, $actualOccupied);

if ($result) {
    echo "Result:\n";
    echo "  Status: " . ($result['status'] ?? 'unknown') . "\n";
    echo "  Error: " . ($result['error'] ?? 'N/A') . "\n";
    
    if (isset($result['adjustments']) && !empty($result['adjustments'])) {
        echo "\nAdjustments made:\n";
        foreach ($result['adjustments'] as $adj) {
            $type = $adj['factorType'] ?? 'unknown';
            $key = $adj['factorKey'] ?? 'unknown';
            $from = $adj['currentValue'] ?? 'N/A';
            $to = $adj['newValue'] ?? 'N/A';
            echo "  - {$type}.{$key}: {$from} -> {$to}\n";
        }
    }
    
    // Generate insight if adjustments were made
    if ($result['status'] === 'adjusted') {
        echo "\nGenerating KOREKTA insight...\n";
        require_once __DIR__ . '/AIInsightsLogger.php';
        $insightsLogger = new AIInsightsLogger($historyDir);
        $insight = $insightsLogger->logLearningAdjustments($result);
        if ($insight) {
            echo "SUCCESS: Generated insight: {$insight['title']}\n";
        } else {
            echo "NOTICE: Insight already exists for this date (deduped)\n";
        }
    } elseif ($result['status'] === 'accurate') {
        echo "\nPrediction was accurate - no correction needed.\n";
    }
} else {
    echo "ERROR: learnFromDay returned null\n";
}

// Also generate VERIFICATION insights (for charts)
echo "\nGenerating VERIFICATION insights for charts...\n";
require_once __DIR__ . '/AIInsightsLogger.php';
require_once __DIR__ . '/AdvancedPredictor.php';
require_once __DIR__ . '/ExternalDataProvider.php';

$externalProvider = new ExternalDataProvider($cacheDir, [
    'enableWeather' => true,
    'enableSports' => true
]);
$predictor = new AdvancedPredictor($historyDir, [
    'externalProvider' => $externalProvider
]);

// Generate prediction for the date (as if we didn't know the result)
$predicted = $predictor->predict($date, null);

if ($predicted && !isset($predicted['error'])) {
    $insightsLogger = new AIInsightsLogger($historyDir);
    $newInsights = $insightsLogger->generateDailyInsights($date, $predicted, $historyData);
    
    if (!empty($newInsights)) {
        echo "SUCCESS: Generated " . count($newInsights) . " daily insights:\n";
        foreach ($newInsights as $insight) {
            echo "  - [{$insight['type']}] {$insight['title']}\n";
        }
    } else {
        echo "NOTICE: No new insights generated (already exist or prediction was accurate)\n";
    }
} else {
    echo "NOTICE: Could not generate prediction for verification insights\n";
}

echo "\n=== DONE ===\n";
