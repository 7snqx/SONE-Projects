<?php
/**
 * Reprocess historical data for Factor Learning
 * 
 * This script goes through all history files and uses AdvancedPredictor 
 * to generate real predictions, then calls learnFromDay() to update factor weights.
 * 
 * Usage: php reprocess_learning.php
 * Or via HTTP: https://yoursite.com/reprocess_learning.php?key=YOUR_KEY
 */

// Security
define('CRON_SECRET_KEY', 'YDh3YpHfIl');
if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Forbidden');
    }
    // Output as plain text for browser
    header('Content-Type: text/plain; charset=utf-8');
}

date_default_timezone_set('Europe/Warsaw');

$historyDir = __DIR__ . '/history';
$cacheDir = __DIR__ . '/cache';

require_once __DIR__ . '/AdvancedPredictor.php';
require_once __DIR__ . '/ExternalDataProvider.php';
require_once __DIR__ . '/FactorLearningSystem.php';

// Check if reset is requested - MUST happen BEFORE creating predictor
$reset = isset($_GET['reset']) || (php_sapi_name() === 'cli' && in_array('--reset', $argv ?? []));

if ($reset) {
    echo "=== RESET MODE: Clearing factor_learning.json ===\n\n";
    $factorFile = $cacheDir . '/factor_learning.json';
    if (file_exists($factorFile)) {
        unlink($factorFile);
        echo "Deleted: {$factorFile}\n\n";
    }
}

// Initialize predictor AFTER reset (so its internal FLS has fresh data)
$externalProvider = new ExternalDataProvider($cacheDir, [
    'enableLearning' => true,
    'enableWeather' => true,
    'enableSports' => true
]);

$predictor = new AdvancedPredictor($historyDir, [
    'externalProvider' => $externalProvider
]);

// Get all history files sorted by date
$files = glob($historyDir . '/????-??-??.json');
sort($files);

echo "=== Reprocessing Historical Data for Factor Learning ===\n";
echo "Use ?reset=1 to clear existing data first.\n\n";
echo "Found " . count($files) . " history files.\n\n";

$processed = 0;
$adjusted = 0;
$errors = 0;

foreach ($files as $file) {
    $date = basename($file, '.json');
    $data = json_decode(file_get_contents($file), true);
    
    if (!$data || !isset($data['totals']['occupied'])) {
        echo "SKIP: {$date} - invalid data\n";
        continue;
    }
    
    $actualOccupancy = $data['totals']['occupied'];
    $screenings = $data['totals']['screenings'] ?? 0;
    
    // Skip closed days
    if ($actualOccupancy == 0 || $screenings == 0) {
        echo "SKIP: {$date} - cinema closed (0 visitors)\n";
        continue;
    }
    
    // Generate a REAL prediction using AdvancedPredictor
    // This also records the prediction to factor_learning.json via predictor's internal FLS
    $prediction = $predictor->predict($date, null);
    
    if (!$prediction || isset($prediction['error'])) {
        echo "SKIP: {$date} - could not generate prediction\n";
        continue;
    }
    
    $predictedOccupancy = $prediction['totals']['adjustedOccupied'] ?? $prediction['totals']['predictedOccupied'] ?? 0;
    
    // IMPORTANT: Reload FLS to see the prediction that was just recorded
    // (predictor has its own FLS instance that saved the prediction)
    $fls = new FactorLearningSystem($cacheDir);
    
    // Now call learnFromDay which will analyze the stored prediction
    $result = $fls->learnFromDay($date, $actualOccupancy);
    
    if ($result) {
        $status = $result['status'] ?? 'unknown';
        $resultError = $result['error'] ?? '?';
        
        if ($status === 'adjusted') {
            echo "ADJUSTED: {$date} - pred:{$predictedOccupancy} vs actual:{$actualOccupancy} ({$resultError})\n";
            $adjusted++;
        } elseif ($status === 'accurate') {
            echo "ACCURATE: {$date} - pred:{$predictedOccupancy} vs actual:{$actualOccupancy}\n";
        } else {
            echo "INFO: {$date} - {$status}\n";
        }
        $processed++;
    } else {
        echo "ERROR: {$date} - learning failed\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Processed: {$processed}\n";
echo "Adjusted: {$adjusted}\n";
echo "Errors: {$errors}\n";

// Reload FLS to get final accurate stats from file
$fls = new FactorLearningSystem($cacheDir);
$stats = $fls->getStats();
echo "\nFinal Learning Stats:\n";
echo "  Total Samples: " . ($stats['totalSamples'] ?? 0) . "\n";
echo "  Avg Error: " . ($stats['avgError'] ?? '0%') . "\n";

$learned = $fls->getLearnedMultipliers();
$learnedCount = 0;
foreach ($learned as $type => $keys) {
    foreach ($keys as $key => $data) {
        if ($data['samples'] > 0) {
            $learnedCount++;
        }
    }
}
echo "  Factors Learned: {$learnedCount}\n";

echo "\nDone!\n";
