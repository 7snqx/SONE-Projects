<?php
/**
 * Debug: View Factor Learning Data
 * Shows current state of factor_learning.json
 */

define('CRON_SECRET_KEY', 'YDh3YpHfIl');

if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Forbidden');
    }
}

header('Content-Type: application/json');

$cacheDir = __DIR__ . '/cache';
$dataFile = $cacheDir . '/factor_learning.json';

echo "=== FACTOR LEARNING DEBUG ===\n\n";
echo "File path: {$dataFile}\n";
echo "File exists: " . (file_exists($dataFile) ? 'YES' : 'NO') . "\n";

if (file_exists($dataFile)) {
    echo "File size: " . filesize($dataFile) . " bytes\n";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($dataFile)) . "\n\n";
    
    $data = json_decode(file_get_contents($dataFile), true);
    
    if ($data) {
        echo "=== LEARNED MULTIPLIERS ===\n";
        if (isset($data['multipliers']) && !empty($data['multipliers'])) {
            foreach ($data['multipliers'] as $type => $keys) {
                echo "\n{$type}:\n";
                foreach ($keys as $key => $info) {
                    $value = $info['value'] ?? 'N/A';
                    $samples = $info['samples'] ?? 0;
                    $original = $info['originalValue'] ?? 'N/A';
                    echo "  - {$key}: {$value} (original: {$original}, samples: {$samples})\n";
                }
            }
        } else {
            echo "  (no learned multipliers yet)\n";
        }
        
        echo "\n=== STATS ===\n";
        echo "Total samples: " . ($data['stats']['totalSamples'] ?? 0) . "\n";
        echo "Avg error: " . round(($data['stats']['avgError'] ?? 0) * 100, 2) . "%\n";
        
        echo "\n=== RECENT HISTORY (DETAILED) ===\n";
        if (isset($data['history'])) {
            $recentHistory = array_slice($data['history'], -10, 10, true);
            foreach ($recentHistory as $date => $entry) {
                $status = $entry['result']['status'] ?? 'pending';
                $error = $entry['result']['error'] ?? 'N/A';
                $predicted = $entry['predicted'] ?? 'N/A';
                $actual = $entry['actual'] ?? 'NULL';
                $analyzed = $entry['analyzed'] ? 'YES' : 'NO';
                echo "  - {$date}: {$status}\n";
                echo "      Predicted: {$predicted}, Actual: {$actual}\n";
                echo "      Analyzed: {$analyzed}, Error: {$error}\n";
                if (isset($entry['factors'])) {
                    echo "      Factors: ";
                    $factorList = [];
                    foreach ($entry['factors'] as $type => $f) {
                        $val = $f['value'] ?? 'N/A';
                        $factorList[] = "{$type}={$val}";
                    }
                    echo implode(', ', $factorList) . "\n";
                }
                echo "\n";
            }
        }
    } else {
        echo "\nERROR: Could not parse JSON!\n";
        echo "Raw content:\n";
        echo file_get_contents($dataFile);
    }
} else {
    echo "\nNo factor_learning.json file exists yet.\n";
    echo "It will be created when the learning system runs for the first time.\n";
}
