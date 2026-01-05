<?php
/**
 * Debug: Test if predictions use learned multipliers
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

$historyDir = __DIR__ . '/history';
$cacheDir = __DIR__ . '/cache';

require_once __DIR__ . '/AdvancedPredictor.php';

$predictor = new AdvancedPredictor($historyDir);

$today = $_GET['date'] ?? date('Y-m-d');

echo "=== CHECKING LEARNED MULTIPLIERS IN PREDICTION ===\n\n";
echo "Date: {$today}\n\n";

// Access private factorLearning via reflection
$reflection = new ReflectionClass($predictor);
$prop = $reflection->getProperty('factorLearning');
$prop->setAccessible(true);
$factorLearning = $prop->getValue($predictor);

echo "=== FROM FactorLearningSystem ===\n";
echo "Season (winter): " . $factorLearning->getMultiplier('season', 'winter') . "\n";
echo "Weather (default): " . $factorLearning->getMultiplier('weather', 'default') . "\n";
echo "Combined (total): " . $factorLearning->getMultiplier('combined_mult', 'total') . "\n";
echo "Holiday (christmas_eve): " . $factorLearning->getMultiplier('holiday', 'christmas_eve') . "\n";

echo "\n=== FROM AdvancedPredictor methods ===\n";
echo "getSeasonMultiplier({$today}): " . $predictor->getSeasonMultiplier($today) . "\n";
// getWeatherMultiplier is private, but we can check through prediction

echo "\n=== PREDICTION FOR TODAY ===\n";
$prediction = $predictor->predict($today, null);

if ($prediction && isset($prediction['factors'])) {
    echo "Factors in prediction:\n";
    foreach ($prediction['factors'] as $name => $factor) {
        $value = is_array($factor) ? ($factor['value'] ?? 'N/A') : $factor;
        echo "  - {$name}: {$value}\n";
    }
}

if ($prediction && isset($prediction['hourly']) && !empty($prediction['hourly'])) {
    $firstHour = reset($prediction['hourly']);
    if (isset($firstHour['learningFactors'])) {
        echo "\nLearning factors from prediction:\n";
        foreach ($firstHour['learningFactors'] as $name => $data) {
            $value = $data['value'] ?? 'N/A';
            $desc = $data['description'] ?? '';
            echo "  - {$name}: {$value} ({$desc})\n";
        }
    }
}

echo "\n=== EXPECTED VS ACTUAL ===\n";
echo "If season shows 1.1 instead of ~1.007, server has old code or wrong file path.\n";
