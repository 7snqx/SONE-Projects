<?php
/**
 * CRON: Daily Historical Data Collector
 * 
 * Run this script every day at 22:00 via cron to save
 * occupancy data for the prediction learning system.
 * 
 * Usage: php cron_save_history.php
 * Or via HTTP: https://yoursite.com/cron_save_history.php?key=YOUR_SECRET_KEY
 * 
 * Data is saved to: /history/YYYY-MM-DD.json
 */

// Security key - change this to something random!
define('CRON_SECRET_KEY', 'YDh3YpHfIl');

// Allow CLI or authenticated HTTP
if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? '';
    if ($providedKey !== CRON_SECRET_KEY) {
        http_response_code(403);
        die('Forbidden');
    }
}

// Set timezone
date_default_timezone_set('Europe/Warsaw');

// Paths
$historyDir = __DIR__ . '/history';
$cacheDir = __DIR__ . '/cache';

// Allow custom date via parameter (for testing/reprocessing)
$today = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
    die("Invalid date format. Use YYYY-MM-DD (e.g. ?date=2025-12-22)\n");
}

$outputFile = $historyDir . '/' . $today . '.json';

// Create directories if needed
if (!is_dir($historyDir)) {
    mkdir($historyDir, 0755, true);
}

// Check if already saved today
$alreadySaved = file_exists($outputFile);
if ($alreadySaved) {
    echo "Data for {$today} already exists. Loading existing data for insights processing...\n";
    // Load existing data instead of fetching new
    $output = json_decode(file_get_contents($outputFile), true);
    if (!$output) {
        echo "ERROR: Could not parse existing history file.\n";
        exit(1);
    }
}

// Include API implementation (without executing it) - needed either way
define('API_MODE_INTERNAL', true);
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/AdvancedPredictor.php';

// Only fetch new data if not already saved
if (!$alreadySaved) {

// Main execution
echo "=== Helios Daily Data Collector ===\n";
echo "Date: {$today}\n";
echo "Time: " . date('H:i:s') . "\n";
echo "Using logic from api.php (scrapes from restapi.helios.pl)\n\n";

// Fetch schedule using scrape() from api.php
echo "Fetching schedule...\n";
// scrape($date, $force)
$data = scrape($today, true);

if (!$data || empty($data['movies'])) {
    echo "ERROR: Could not fetch schedule (api.php returned empty data)\n";
    exit(1);
}

// Process data to match historical format
$hourlyData = [];
$movies = [];
$totalOccupied = 0;
$totalSeats = 0;
$screeningsCount = 0;

echo "Processing " . count($data['movies']) . " movies...\n";

foreach ($data['movies'] as $movie) {
    $movieTitle = $movie['movieTitle'] ?? 'Unknown';
    
    // Initialize movie tracking if new
    if (!isset($movies[$movieTitle])) {
        $movies[$movieTitle] = [
            'genres' => $movie['genres'] ?? [],
            'screenings' => [],
            'totalOccupied' => 0,
            'totalSeats' => 0
        ];
    }
    
    foreach ($movie['screenings'] ?? [] as $s) {
        $screeningsCount++;
        
        $occupied = $s['stats']['occupied'] ?? 0;
        $total = $s['stats']['total'] ?? 0;
        $time = $s['time'] ?? '00:00';
        
        $totalOccupied += $occupied;
        $totalSeats += $total;
        
        // Extract hour
        $hour = (int)substr($time, 0, 2);
        
        // Aggregate by hour
        if (!isset($hourlyData[$hour])) {
            $hourlyData[$hour] = [
                'occupied' => 0,
                'total' => 0,
                'screenings' => 0
            ];
        }
        $hourlyData[$hour]['occupied'] += $occupied;
        $hourlyData[$hour]['total'] += $total;
        $hourlyData[$hour]['screenings']++;
        
        // Add to movie stats
        $movies[$movieTitle]['screenings'][] = [
            'time' => $time,
            'occupied' => $occupied,
            'total' => $total,
            'room' => $s['hall'] ?? null
        ];
        $movies[$movieTitle]['totalOccupied'] += $occupied;
        $movies[$movieTitle]['totalSeats'] += $total;
    }
}

// Determine day type
$dayOfWeek = (int)date('w', strtotime($today));
// 0=Sun, 1=Mon, ..., 6=Sat
if ($dayOfWeek == 2) { // Tuesday
    $dayType = 'tuesday';
} elseif ($dayOfWeek == 0 || $dayOfWeek == 5 || $dayOfWeek == 6) { // Fri, Sat, Sun
    $dayType = 'weekend';
} else {
    $dayType = 'workday';
}

// Build output
$output = [
    'date' => $today,
    'dayType' => $dayType,
    'dayOfWeek' => $dayOfWeek,
    'savedAt' => date('c'),
    'totals' => [
        'occupied' => $totalOccupied,
        'total' => $totalSeats,
        'percent' => $totalSeats > 0 ? round(($totalOccupied / $totalSeats) * 100, 1) : 0,
        'screenings' => $screeningsCount
    ],
    'hourly' => $hourlyData,
    'movies' => array_values(array_map(function($title, $data) {
        return array_merge(['title' => $title], $data);
    }, array_keys($movies), $movies))
];

// Save to file
$saved = file_put_contents($outputFile, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($saved) {
    echo "\nSUCCESS! Data saved to: {$outputFile}\n";
    echo "Total screenings: {$screeningsCount}\n";
    echo "Total occupancy: {$totalOccupied}/{$totalSeats} (" . $output['totals']['percent'] . "%)\n";
    echo "Day type: {$dayType}\n";
} else {
    echo "ERROR: Could not save file\n";
    exit(1);
}

} // End of if (!$alreadySaved) block

// Initialize AIInsightsLogger early (needed for Factor Learning insights)
require_once __DIR__ . '/AIInsightsLogger.php';
$insightsLogger = new AIInsightsLogger($historyDir);

// =====================================================
// CLOSED DAY DETECTION
// Skip learning on days when cinema is closed (0 screenings)
// e.g., Christmas Eve, New Year's Eve, etc.
// =====================================================
$screeningsCount = $output['totals']['screenings'] ?? 0;
$totalOccupied = $output['totals']['occupied'] ?? 0;
$isCinemaClosedDay = ($screeningsCount === 0 || $totalOccupied === 0);

if ($isCinemaClosedDay) {
    echo "\n⚠️  DETECTED: Cinema closed day (0 screenings or 0 visitors).\n";
    echo "    Skipping all learning operations to prevent algorithm corruption.\n";
    echo "    History file will still be saved for reference.\n\n";
}

// Initialize ExternalDataProvider for weather, sports, and LEARNING
require_once __DIR__ . '/ExternalDataProvider.php';
$externalProvider = new ExternalDataProvider($cacheDir, [
    'enableLearning' => true,
    'enableWeather' => true,
    'enableSports' => true
]);

// Initialize predictor WITH external provider (enables learning!)
$predictor = new AdvancedPredictor($historyDir, [
    'externalProvider' => $externalProvider
]);
echo "\nLearning system enabled with ExternalDataProvider.\n";

// Train the model with today's actual data (SKIP IF CINEMA CLOSED)
if (!$isCinemaClosedDay) {
    $trained = $predictor->train($today, $output);
    if ($trained) {
        echo "SUCCESS: Learning system updated weights based on today's performance.\n";
        echo "  - Weights saved to: cache/learning_weights.json\n";
        
        // Log factor learning adjustment insights
        if (is_array($trained) && isset($trained['learningResult'])) {
             $lr = $trained['learningResult'];
             
             // 1. If adjustments were made, log them (CORRECTION/LEARNING)
             if ($lr['status'] === 'adjusted') {
                 $insight = $insightsLogger->logLearningAdjustments($lr);
                 if ($insight) echo "  - Generated learning insight: {$insight['title']}\n";
             } 
             // 2. If prediction was accurate, log to console but DON'T create a simplified insight
             // Let generateDailyInsights() handle the rich user-facing "Trafna prognoza" insight later
             elseif ($lr['status'] === 'accurate') {
                 echo "  - Prediction was accurate (error: {$lr['error']}). Handing over to detailed daily analysis.\n";
             }
        }
    } else {
        echo "NOTICE: Learning system skipped update (not enough hourly data).\n";
    }
} else {
    echo "SKIPPED: Training disabled (cinema closed day).\n";
}

// =====================================================
// ALSO: Analyze yesterday's data (which is now complete)
// This fills in 'actual' values for predictions made yesterday
// =====================================================
$yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
$yesterdayFile = $historyDir . '/' . $yesterday . '.json';

if (file_exists($yesterdayFile)) {
    echo "\nChecking yesterday's data ({$yesterday}) for Factor Learning...\n";
    $yesterdayData = json_decode(file_get_contents($yesterdayFile), true);
    
    // Check if yesterday was also a closed day
    $yesterdayScreenings = $yesterdayData['totals']['screenings'] ?? 0;
    $yesterdayOccupied = $yesterdayData['totals']['occupied'] ?? 0;
    $wasYesterdayClosed = ($yesterdayScreenings === 0 || $yesterdayOccupied === 0);
    
    if ($wasYesterdayClosed) {
        echo "SKIPPED: Yesterday was a closed day (0 screenings/visitors). Skipping analysis.\n";
    } elseif ($yesterdayData && isset($yesterdayData['totals']['occupied'])) {
        $yesterdayActual = $yesterdayData['totals']['occupied'];
        
        // Get FactorLearningSystem from predictor
        require_once __DIR__ . '/FactorLearningSystem.php';
        $factorLearning = new FactorLearningSystem($cacheDir);
        
        // Prepare hourly actual data for hourly learning
        $hourlyActual = [];
        if (isset($yesterdayData['hourly'])) {
            foreach ($yesterdayData['hourly'] as $hour => $data) {
                $hourlyActual[$hour] = $data['occupied'] ?? 0;
            }
            // Record hourly actual data first
            $factorLearning->recordHourlyActual($yesterday, $hourlyActual);
        }
        
        // Try to record actual and analyze (now includes hourly)
        $result = $factorLearning->learnFromDay($yesterday, $yesterdayActual);
        
        // Handle new result structure (daily + hourly)
        $dailyResult = $result['daily'] ?? $result;
        $hourlyResult = $result['hourly'] ?? null;
        
        if ($dailyResult && isset($dailyResult['status']) && in_array($dailyResult['status'], ['adjusted', 'accurate'])) {
            echo "SUCCESS: Analyzed yesterday ({$yesterday}): {$dailyResult['status']}, error: {$dailyResult['error']}\n";
            
            // Log insight for yesterday's analysis
            if ($dailyResult['status'] === 'adjusted') {
                $insight = $insightsLogger->logLearningAdjustments($dailyResult);
                if ($insight) echo "  - Generated KOREKTA insight for {$yesterday}\n";
            } elseif ($dailyResult['status'] === 'accurate') {
                echo "  - Prediction was accurate, no correction needed\n";
            }
        } else {
            $errorMsg = $dailyResult['error'] ?? ($dailyResult['message'] ?? 'No prediction recorded for this date');
            echo "NOTICE: Yesterday ({$yesterday}) analysis skipped: {$errorMsg}\n";
        }
        
        // Log hourly learning results
        if ($hourlyResult && !isset($hourlyResult['error'])) {
            $avgError = $hourlyResult['avgError'] ?? 'N/A';
            echo "  - Hourly learning: avg error {$avgError}\n";
            
            // Update hourly biases saved
            $biases = $factorLearning->getAllHourlyBiases();
            if (!empty($biases)) {
                echo "  - Updated hourly biases for " . count($biases) . " hours\n";
            }
        }
    }
}

// Generate AI Insights for today (SKIP IF CINEMA CLOSED)
echo "\nGenerating AI insights...\n";
// (AIInsightsLogger already initialized above)

if (!$isCinemaClosedDay) {
    // Generate prediction for today (as if we didn't know the result)
    $predicted = $predictor->predict($today, null);
    
    // Generate insights by comparing prediction vs actual
    $newInsights = $insightsLogger->generateDailyInsights($today, $predicted, $output);
    
    if (!empty($newInsights)) {
        echo "SUCCESS: Generated " . count($newInsights) . " new insights.\n";
        foreach ($newInsights as $insight) {
            echo "  - [{$insight['type']}] {$insight['title']}\n";
        }
    } else {
        echo "NOTICE: No significant insights generated (predictions were accurate or not enough data).\n";
    }
} else {
    // Add a special insight noting the cinema was closed
    $insightsLogger->addInsight(
        'pattern',
        "Kino zamknięte ($today)",
        "Kino było zamknięte lub nie odbyły się żadne seanse. Dzień pominięty w uczeniu algorytmu.",
        ['forDate' => $today, 'icon' => 'event_busy', 'closedDay' => true]
    );
    $insightsLogger->saveInsights();
    echo "SKIPPED: Insights generation disabled (cinema closed day).\n";
    echo "  - Added 'Kino zamknięte' notice.\n";
}

// Check and generate weekly/monthly performance reports
echo "\nChecking for periodic reports...\n";
$reports = $insightsLogger->checkAndGenerateReports();
if (!empty($reports)) {
    echo "SUCCESS: Generated " . count($reports) . " performance report(s).\n";
    foreach ($reports as $report) {
        echo "  - [{$report['type']}] {$report['title']}\n";
    }
} else {
    echo "NOTICE: No performance reports due (weekly: Sundays, monthly: 1st of month).\n";
}

// Cleanup old cache files (> 7 days)
echo "\nCleaning up old cache files...\n";
$files = glob($cacheDir . '/*');
$deletedCount = 0;
$now = time();
$sevenDays = 7 * 24 * 60 * 60;

foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= $sevenDays) {
            unlink($file);
            $deletedCount++;
        }
    }
}

if ($deletedCount > 0) {
    echo "Cleaned up {$deletedCount} old files from cache.\n";
} else {
    echo "Cache is clean (no files older than 7 days).\n";
}

exit(0);
