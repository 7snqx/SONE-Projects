<?php
/**
 * Narzędzie do testowania uczenia się z danych historycznych
 * Umożliwia ręczne uruchomienie procesu learningu dla wybranych dni z przeszłości.
 */

require_once __DIR__ . '/AdvancedPredictor.php';
require_once __DIR__ . '/ExternalDataProvider.php'; // FIX: Added missing require
require_once __DIR__ . '/AIInsightsLogger.php';
require_once __DIR__ . '/FactorLearningSystem.php';

// Enable error reporting IMMEDIATELY
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$historyDir = __DIR__ . '/history';
$cacheDir = __DIR__ . '/cache';

// Configuration
$config = [
    'historyDir' => $historyDir,
    'cacheDir' => $cacheDir
];

// 1. Handle Action: Train/Test specific date
$date = $_GET['date'] ?? null;
$action = $_GET['action'] ?? null;

if ($date && $action === 'train') {
    echo "<h1>Wyniki uczenia dla: {$date}</h1>";
    echo "<p><a href='test_learning.php'>&laquo; Wróć do listy</a></p>";
    
    $file = $historyDir . '/' . $date . '.json';
    if (!file_exists($file)) {
        die("Błąd: Brak pliku historii dla tej daty.");
    }
    
    $actualData = json_decode(file_get_contents($file), true);
    
    // Enable error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    echo "<h3>1. Analiza danych z pliku</h3>";
    echo "Liczba seansów: " . ($actualData['totals']['screenings'] ?? 0) . "<br>";
    echo "Frekwencja: " . ($actualData['totals']['occupied'] ?? 0) . " / " . ($actualData['totals']['total'] ?? 0) . "<br>";
    
    echo "<h3>2. Uruchamianie treningu (AdvancedPredictor::train)</h3>";
    
    try {
        // Init systems inside try block to catch constructor errors
        $externalProvider = new ExternalDataProvider($cacheDir, ['enableLearning' => true]);
        $predictor = new AdvancedPredictor($historyDir, ['externalProvider' => $externalProvider]);
        $insightsLogger = new AIInsightsLogger($historyDir);
        
        // START LEARNING
        $result = $predictor->train($date, $actualData);
    } catch (Throwable $e) {
        echo "<div style='background: #fff5f5; padding: 15px; border: 1px solid #e53e3e; border-radius: 5px; color: red;'>";
        echo "<strong>WYSTĄPIŁ BŁĄD KRYTYCZNY:</strong><br>";
        echo $e->getMessage() . "<br>";
        echo "<small>File: " . $e->getFile() . " line " . $e->getLine() . "</small>";
        echo "</div>";
        exit;
    }
    
    if ($result) {
        echo "<div style='background: #e6fffa; padding: 15px; border: 1px solid #38b2ac; border-radius: 5px;'>";
        echo "<strong>SUKCES! System przetworzył dane.</strong>";
        
        if (is_array($result) && isset($result['learningResult'])) {
            $lr = $result['learningResult'];
            echo "<h4>Rezultat Uczenia Atrybucji (Factor Learning):</h4>";
            
            if (isset($lr['adjustments']) && !empty($lr['adjustments'])) {
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr style='background: #f0f0f0;'><th>Czynnik</th><th>Klucz</th><th>Stara Wartość</th><th>Korekta</th><th>Nowa Wartość</th></tr>";
                foreach ($lr['adjustments'] as $adj) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($adj['factorType']) . "</td>";
                    echo "<td>" . htmlspecialchars($adj['factorKey']) . "</td>";
                    echo "<td>" . $adj['currentValue'] . "</td>";
                    echo "<td style='color: " . ($adj['adjustment'] > 0 ? 'green' : 'red') . "'>" . ($adj['adjustment'] > 0 ? '+' : '') . round($adj['adjustment'], 4) . "</td>";
                    echo "<td><strong>" . $adj['newValue'] . "</strong></td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Log to insights
                $insight = $insightsLogger->logLearningAdjustments($lr);
                if ($insight) {
                    echo "<p><em>Dodano wpis do AI Insights.</em></p>";
                }
            } else {
                echo "System uznał, że predykcja była wystarczająco dokładna (brak korekty wag).";
                if (isset($lr['error'])) echo "<br>Błąd predykcji: " . $lr['error'];
            }
        }
        echo "</div>";
    } else {
        echo "<div style='background: #fff5f5; padding: 15px; border: 1px solid #e53e3e; border-radius: 5px;'>";
        echo "Brak aktualizacji. Możliwe przyczyny:<br>";
        echo "- Brak danych godzinowych w pliku historii<br>";
        echo "- Błąd w dopasowaniu predykcji do rzeczywistości";
        echo "</div>";
    }
    
    // Show current multipliers
    echo "<h3>Aktualne wyuczone mnożniki (FactorLearningSystem)</h3>";
    $fls = new FactorLearningSystem($cacheDir);
    $multipliers = $fls->getLearnedMultipliers();
    
    echo "<details><summary style='cursor: pointer; color: #3182ce;'>Pokaż pełne dane JSON (dla programisty)</summary>";
    echo "<pre style='background: #f7f7f7; padding: 10px; font-size: 0.8em; overflow: auto; max-height: 400px;'>" . print_r($multipliers, true) . "</pre>";
    echo "</details>";
    
    exit;
}


// 2. List Files
$files = glob($historyDir . '/*.json');
rsort($files); // Newest first

echo "<!DOCTYPE html><html><head><title>Testowanie Historii</title>";
echo "<style>body{font-family: sans-serif; max-width: 800px; margin: 20px auto; line-height: 1.6;} 
      .item{padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;}
      .btn{background: #3182ce; color: white; text-decoration: none; padding: 5px 15px; border-radius: 4px;}
      .btn:hover{background: #2b6cb0;}
      </style></head><body>";

echo "<h1>Dostępna Historia i Testy</h1>";
echo "<p>Wybierz dzień, aby symulować proces uczenia się na jego podstawie.</p>";

foreach ($files as $file) {
    $basename = basename($file, '.json');
    $size = round(filesize($file) / 1024, 2) . ' KB';
    $data = json_decode(file_get_contents($file), true);
    $occupancy = $data['totals']['percent'] ?? '?';
    
    echo "<div class='item'>";
    echo "<div>";
    echo "<strong>{$basename}</strong> <span style='color: #666; font-size: 0.9em;'>({$size})</span><br>";
    echo "Frekwencja: {$occupancy}% | Seanse: " . ($data['totals']['screenings'] ?? '?');
    echo "</div>";
    echo "<a href='?date={$basename}&action=train' class='btn'>Ucz system z tego dnia &raquo;</a>";
    echo "</div>";
}

echo "</body></html>";
