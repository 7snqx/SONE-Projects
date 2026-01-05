<?php
/**
 * Debug script - minimal test
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Info</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

echo "<h2>Testing AdvancedPredictor directly...</h2>";

try {
    echo "<p>Including AdvancedPredictor.php...</p>";
    require_once __DIR__ . '/AdvancedPredictor.php';
    echo "<p style='color:green'>✓ AdvancedPredictor include OK</p>";
    
    echo "<p>Creating AdvancedPredictor object...</p>";
    $predictor = new AdvancedPredictor(__DIR__ . '/history');
    echo "<p style='color:green'>✓ AdvancedPredictor created OK</p>";
    
} catch (Throwable $e) {
    echo "<p style='color:red'>✗ ERROR: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Done</h2>";
