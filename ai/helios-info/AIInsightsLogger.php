<?php
/**
 * AI Insights Logger v2.0
 * 
 * Tracks AI prediction accuracy and generates human-readable insights
 * about what the algorithm has learned from comparing predictions to actuals.
 */

class AIInsightsLogger {
    private $insightsFile;
    private $historyDir;
    private $insights = [];
    
    // Insight types
    const TYPE_CORRECTION = 'correction';
    const TYPE_LEARNING = 'learning';
    const TYPE_VERIFICATION = 'verification';
    const TYPE_PATTERN = 'pattern';
    const TYPE_WEEKLY_REPORT = 'weekly_report';
    const TYPE_MONTHLY_REPORT = 'monthly_report';
    
    public function __construct($historyDir) {
        $this->historyDir = rtrim($historyDir, '/');
        $this->insightsFile = $this->historyDir . '/insights.json';
        $this->loadInsights();
    }
    
    private function loadInsights() {
        if (file_exists($this->insightsFile)) {
            $data = file_get_contents($this->insightsFile);
            $this->insights = json_decode($data, true) ?: [];
        }
    }
    
    private function saveInsights() {
        // Limit removed per user request - keep all history
        // $this->insights = array_slice($this->insights, -5000); 
        file_put_contents($this->insightsFile, json_encode($this->insights, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Check if DAILY insights (correction/verification) already exist for a given date
     * NOTE: Excludes LEARNING type insights, as those are managed separately
     */
    private function hasInsightsForDate($date) {
        foreach ($this->insights as $insight) {
            // Only check for CORRECTION or VERIFICATION types (daily analysis results)
            if (isset($insight['details']['forDate']) && 
                $insight['details']['forDate'] === $date &&
                in_array($insight['type'], [self::TYPE_CORRECTION, self::TYPE_VERIFICATION])) {
                
                // IGNORE "Auto-korekta" (internal weight adjustment) - we still want the daily analysis
                if (str_contains($insight['title'] ?? '', 'Auto-korekta')) {
                    continue;
                }
                
                // IGNORE "Weryfikacja zakończona pomyślnie" (simple technical verification)
                // We want the rich "Trafna prognoza" / "Analiza dnia" instead
                if (str_contains($insight['title'] ?? '', 'Weryfikacja zakończona pomyślnie')) {
                    continue;
                }
                
                return true;
            }
        }
        return false;
    }
    
    public function addInsight($type, $title, $message, $details = []) {
        $insight = [
            'id' => uniqid('insight_'),
            'date' => date('Y-m-d'),
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'details' => $details
        ];
        
        $this->insights[] = $insight;
        $this->saveInsights();
        
        return $insight;
    }
    
    public function getInsights($limit = 20) {
        $sorted = $this->insights;
        usort($sorted, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        return array_slice($sorted, 0, $limit);
    }
    
    /**
     * Generate insights by comparing predicted vs actual data
     */
    public function generateDailyInsights($date, $predicted, $actual) {
        if (!$predicted || !$actual) return [];
        
        // Track whether accuracy section already done (allows other sections to run independently)
        $skipAccuracyCheck = $this->hasInsightsForDate($date);
        
        $generatedInsights = [];
        
        // Extract prediction total - use adjustedOccupied (with multipliers) to match banner display
        $predTotal = 0;
        if (isset($predicted['totals']['adjustedOccupied'])) {
            // Adjusted value (with modifiers like weather, season, etc.) - matches banner
            $predTotal = $predicted['totals']['adjustedOccupied'];
        } elseif (isset($predicted['totals']['predictedOccupied'])) {
            // Fallback to raw prediction if adjusted not available
            $predTotal = $predicted['totals']['predictedOccupied'];
        } elseif (isset($predicted['hourly'])) {
            foreach ($predicted['hourly'] as $hourData) {
                // Prefer adjustedOccupied over predictedOccupied
                $predTotal += $hourData['adjustedOccupied'] ?? $hourData['predictedOccupied'] ?? 0;
            }
        }
        
        $actualTotal = $actual['totals']['occupied'] ?? 0;
        $totalSeats = $actual['totals']['total'] ?? 1;
        $screeningsCount = $actual['totals']['screenings'] ?? 0;
        $dayType = $actual['dayType'] ?? 'unknown';
        $avgOccupancy = round(($actualTotal / $totalSeats) * 100, 1);
        
        // =====================
        // 1. OVERALL ACCURACY (skip if already analyzed)
        // =====================
        if (!$skipAccuracyCheck && $actualTotal > 0) {
            if ($predTotal > 0) {
                $accuracy = 1 - abs($predTotal - $actualTotal) / max($predTotal, $actualTotal);
                $percentDiff = round((($actualTotal - $predTotal) / $predTotal) * 100);
                
                $factorsInfo = $this->getFactorsDescription($date, $dayType, $avgOccupancy);
                $factorsArray = $this->getFactorsArray($date, $dayType, $avgOccupancy);
                
                // Extract applied factors from prediction data for transparency
                $appliedFactors = $this->extractAppliedFactors($predicted);
                $multipliersSummary = $this->getMultipliersSummary($predicted);
                
                // Get learning weights info for adjustment description
                $learningWeightsInfo = $this->getLearningWeightsAdjustment($dayType, $percentDiff);
                
                if ($accuracy >= 0.90) {
                    $generatedInsights[] = $this->addInsight(
                        self::TYPE_VERIFICATION,
                        "Trafna prognoza ($date)",
                        "Dokładność " . round($accuracy * 100) . "%. " .
                        "Prognoza: $predTotal, rzeczywistość: $actualTotal widzów.",
                        [
                            'forDate' => $date, 
                            'predicted' => $predTotal, 
                            'actual' => $actualTotal, 
                            'accuracy' => $accuracy, 
                            'dayType' => $dayType, 
                            'icon' => 'check_circle',
                            'appliedFactors' => $appliedFactors,
                            'multipliers' => $multipliersSummary,
                            'factorsArray' => $factorsArray
                        ]
                    );
                } elseif ($percentDiff > 10) {
                    $adjustmentDesc = $this->describeAdjustment('up', $dayType, $percentDiff, $appliedFactors);
                    $generatedInsights[] = $this->addInsight(
                        self::TYPE_CORRECTION,
                        "Niedoszacowanie +$percentDiff% ($date)",
                        "Więcej widzów niż przewidziałem! Prognoza: $predTotal → Rzeczywistość: $actualTotal.",
                        [
                            'forDate' => $date, 
                            'predicted' => $predTotal, 
                            'actual' => $actualTotal, 
                            'diff' => $percentDiff, 
                            'dayType' => $dayType, 
                            'icon' => 'trending_up',
                            'factors' => $factorsInfo,
                            'factorsArray' => $factorsArray,
                            'appliedFactors' => $appliedFactors,
                            'multipliers' => $multipliersSummary,
                            'adjustments' => $learningWeightsInfo,
                            'correction' => $adjustmentDesc
                        ]
                    );
                } elseif ($percentDiff < -10) {
                    $adjustmentDesc = $this->describeAdjustment('down', $dayType, abs($percentDiff), $appliedFactors);
                    $generatedInsights[] = $this->addInsight(
                        self::TYPE_CORRECTION,
                        "Przeszacowanie $percentDiff% ($date)",
                        "Mniej widzów niż przewidywałem. Prognoza: $predTotal → Rzeczywistość: $actualTotal.",
                        [
                            'forDate' => $date, 
                            'predicted' => $predTotal, 
                            'actual' => $actualTotal, 
                            'diff' => $percentDiff, 
                            'dayType' => $dayType, 
                            'icon' => 'trending_down',
                            'factors' => $factorsInfo,
                            'factorsArray' => $factorsArray,
                            'appliedFactors' => $appliedFactors,
                            'multipliers' => $multipliersSummary,
                            'adjustments' => $learningWeightsInfo,
                            'correction' => $adjustmentDesc
                        ]
                    );
                } else {
                    // Difference within ±10% is considered accurate verification
                    $generatedInsights[] = $this->addInsight(
                        self::TYPE_VERIFICATION,
                        "Analiza dnia $date",
                        "Różnica " . ($percentDiff > 0 ? '+' : '') . "$percentDiff% (w normie). " .
                        "Prognoza: $predTotal, rzeczywistość: $actualTotal. Czynniki: " . implode(", ", array_map(fn($f) => $f['name'], $factorsArray)) . ".",
                        [
                            'forDate' => $date, 
                            'predicted' => $predTotal, 
                            'actual' => $actualTotal, 
                            'diff' => $percentDiff,
                            'accuracy' => 1 - abs($percentDiff) / 100,
                            'icon' => 'check_circle',
                            'factorsArray' => $factorsArray,
                            'appliedFactors' => $appliedFactors,
                            'multipliers' => $multipliersSummary
                        ]
                    );
                }
            } else {
                $generatedInsights[] = $this->addInsight(
                    self::TYPE_LEARNING,
                    "Nowe dane ($date)",
                    "Zebrałem: $actualTotal widzów na $screeningsCount seansach (średnie obłożenie: $avgOccupancy%). " .
                    "Typ dnia: $dayType. Wykorzystam do budowy baseline dla tego typu dnia.",
                    ['forDate' => $date, 'actual' => $actualTotal, 'screenings' => $screeningsCount, 'dayType' => $dayType, 'icon' => 'school']
                );
            }
        }
        
        // =====================
        // 2. TOP MOVIES ANALYSIS (skip if already done for this date)
        // =====================
        $skipTopMovies = false;
        foreach ($this->insights as $insight) {
            if (str_contains($insight['title'] ?? '', 'Top filmy dnia') && 
                isset($insight['details']['forDate']) && $insight['details']['forDate'] === $date) {
                $skipTopMovies = true;
                break;
            }
        }
        if (!$skipTopMovies && isset($actual['movies']) && is_array($actual['movies'])) {
            $this->analyzeTopMovies($date, $actual['movies'], $generatedInsights);
        }
        
        // =====================
        // 3. DAY PATTERNS (skip if PATTERN already exists for this date)
        // =====================
        $skipPatterns = false;
        foreach ($this->insights as $insight) {
            if ($insight['type'] === self::TYPE_PATTERN && 
                isset($insight['details']['forDate']) && $insight['details']['forDate'] === $date) {
                $skipPatterns = true;
                break;
            }
        }
        if (!$skipPatterns) {
            $this->detectDayPatterns($date, $actual, $generatedInsights);
        }
        
        return $generatedInsights;
    }
    
    /**
     * Generate description of factors affecting this day
     * Returns BOTH a string summary AND structured array for UI
     */
    private function getFactorsDescription($date, $dayType, $avgOccupancy) {
        $factorsArray = $this->getFactorsArray($date, $dayType, $avgOccupancy);
        
        // Build simple string for backward compatibility
        $strings = array_map(function($f) {
            return $f['label'];
        }, $factorsArray);
        
        return "Czynniki: " . implode(", ", $strings) . ".";
    }
    
    /**
     * Get structured factors array for detailed UI display
     */
    public function getFactorsArray($date, $dayType, $avgOccupancy) {
        $factors = [];
        
        $dayOfWeek = date('l', strtotime($date));
        $polishDays = [
            'Monday' => 'poniedziałek', 'Tuesday' => 'wtorek', 'Wednesday' => 'środa',
            'Thursday' => 'czwartek', 'Friday' => 'piątek', 'Saturday' => 'sobota', 'Sunday' => 'niedziela'
        ];
        $dayPl = $polishDays[$dayOfWeek] ?? $dayOfWeek;
        
        $factors[] = [
            'icon' => 'calendar_today',
            'name' => 'Dzień tygodnia',
            'value' => $dayPl,
            'impact' => null,
            'type' => 'neutral',
            'label' => "Dzień: $dayPl ($dayType)"
        ];
        
        if (in_array($dayOfWeek, ['Friday', 'Saturday', 'Sunday'])) {
            $factors[] = [
                'icon' => 'weekend',
                'name' => 'Weekend',
                'value' => 'Piątek-Niedziela',
                'impact' => '+20-40%',
                'type' => 'positive',
                'label' => 'Weekend (+20-40%)'
            ];
        }
        
        if ($dayType === 'tuesday') {
            $factors[] = [
                'icon' => 'local_offer',
                'name' => 'Tani Wtorek',
                'value' => 'Promocja',
                'impact' => '+30%',
                'type' => 'positive',
                'label' => 'Tani Wtorek (+30%)'
            ];
        }
        
        // Season
        $month = (int)date('m', strtotime($date));
        $day = (int)date('d', strtotime($date));
        
        if ($month >= 6 && $month <= 8) {
            $factors[] = [
                'icon' => 'wb_sunny',
                'name' => 'Lato',
                'value' => 'Aktywności na zewnątrz',
                'impact' => '-20%',
                'type' => 'negative',
                'label' => 'Lato (-20% - aktywności na zewnątrz)'
            ];
        } elseif ($month == 12 || $month <= 2) {
            $factors[] = [
                'icon' => 'ac_unit',
                'name' => 'Zima',
                'value' => 'Zimno na zewnątrz',
                'impact' => '+10%',
                'type' => 'positive',
                'label' => 'Zima (+10%)'
            ];
        }
        
        // Payday
        if (($day >= 10 && $day <= 15) || ($day >= 25 && $day <= 30)) {
            $factors[] = [
                'icon' => 'payments',
                'name' => 'Po wypłacie',
                'value' => 'Dni 10-15 lub 25-30',
                'impact' => '+5%',
                'type' => 'positive',
                'label' => 'Okres wypłat (+5%)'
            ];
        } elseif ($day >= 1 && $day <= 9) {
            $factors[] = [
                'icon' => 'money_off',
                'name' => 'Przed wypłatą',
                'value' => 'Dni 1-9',
                'impact' => '-5%',
                'type' => 'negative',
                'label' => 'Przed wypłatą (-5%)'
            ];
        }
        
        // Holiday period
        if ($month == 12 && $day >= 20) {
            $factors[] = [
                'icon' => 'celebration',
                'name' => 'Okres świąteczny',
                'value' => '20-31 grudnia',
                'impact' => '+30%',
                'type' => 'positive',
                'label' => 'Okres świąteczny (+30%)'
            ];
        }
        
        // Mikołajki
        if ($month == 12 && $day == 6) {
            $factors[] = [
                'icon' => 'redeem',
                'name' => 'Mikołajki',
                'value' => '6 grudnia',
                'impact' => '+25-35%',
                'type' => 'positive',
                'label' => 'Mikołajki (+25-35% - filmy familijne!)'
            ];
        }
        
        // Christmas Eve
        if ($month == 12 && $day == 24) {
            $factors[] = [
                'icon' => 'home',
                'name' => 'Wigilia',
                'value' => '24 grudnia',
                'impact' => '-40%',
                'type' => 'negative',
                'label' => 'Wigilia (-40% - ludzie w domach)'
            ];
        }
        
        // Walentynki
        if ($month == 2 && $day == 14) {
            $factors[] = [
                'icon' => 'favorite',
                'name' => 'Walentynki',
                'value' => '14 lutego',
                'impact' => '+30%',
                'type' => 'positive',
                'label' => 'Walentynki (+30% - randki kinowe)'
            ];
        }
        
        // First warm spring weekend warning
        $dayOfWeekNum = (int)date('w', strtotime($date));
        if ($month >= 3 && $month <= 4 && ($dayOfWeekNum == 0 || $dayOfWeekNum == 6)) {
            $factors[] = [
                'icon' => 'warning',
                'name' => 'Pierwszy ciepły weekend',
                'value' => 'Marzec-Kwiecień',
                'impact' => '-40%',
                'type' => 'warning',
                'label' => 'Możliwy efekt pierwszego ciepłego weekendu wiosny (-40%)'
            ];
        }
        
        $factors[] = [
            'icon' => 'percent',
            'name' => 'Średnie obłożenie',
            'value' => "$avgOccupancy%",
            'impact' => null,
            'type' => 'neutral',
            'label' => "Śr. obłożenie: $avgOccupancy%"
        ];
        
        return $factors;
    }
    
    /**
     * Analyze top performing movies
     */
    private function analyzeTopMovies($date, $movies, &$insights) {
        if (empty($movies)) return;
        
        // Sort by totalOccupied
        usort($movies, function($a, $b) {
            return ($b['totalOccupied'] ?? 0) - ($a['totalOccupied'] ?? 0);
        });
        
        // Top 3 movies
        $topMovies = array_slice($movies, 0, 3);
        $topList = [];
        
        foreach ($topMovies as $i => $movie) {
            $title = $movie['title'] ?? 'Unknown';
            $occupied = $movie['totalOccupied'] ?? 0;
            $seats = $movie['totalSeats'] ?? 1;
            $pct = round(($occupied / $seats) * 100);
            $genres = implode(', ', $movie['genres'] ?? []);
            
            $topList[] = ($i + 1) . ". \"{$title}\" - $occupied widzów ($pct% obłożenia" . ($genres ? ", $genres" : "") . ")";
        }
        
        if (!empty($topList)) {
            $insights[] = $this->addInsight(
                self::TYPE_LEARNING,
                "Top filmy dnia ($date)",
                "Najpopularniejsze seanse:\n" . implode("\n", $topList),
                ['forDate' => $date, 'topMovies' => array_map(fn($m) => $m['title'] ?? '', $topMovies), 'icon' => 'movie']
            );
        }
        
        // Find surprise hits (high occupancy despite low total seats)
        foreach ($movies as $movie) {
            $title = $movie['title'] ?? '';
            $occupied = $movie['totalOccupied'] ?? 0;
            $seats = $movie['totalSeats'] ?? 1;
            $pct = ($occupied / $seats) * 100;
            
            // If occupancy > 60% and at least 50 viewers
            if ($pct > 60 && $occupied > 50) {
                $genres = implode(', ', $movie['genres'] ?? []);
                $insights[] = $this->addInsight(
                    self::TYPE_PATTERN,
                    "Hit: " . mb_substr($title, 0, 25) . " ($date)",
                    "Film \"$title\" miał $occupied widzów (" . round($pct) . "% obłożenia). " .
                    ($genres ? "Gatunek: $genres. " : "") .
                    "Dodaję do analizy popularnych tytułów.",
                    ['forDate' => $date, 'movie' => $title, 'occupancy' => round($pct), 'genres' => $genres, 'icon' => 'local_fire_department']
                );
                break; // Only one hit per day
            }
        }
    }
    
    /**
     * Detect patterns for days of week
     */
    private function detectDayPatterns($date, $actual, &$insights) {
        $dayOfWeek = date('l', strtotime($date));
        $polishDays = [
            'Monday' => 'poniedziałek', 'Tuesday' => 'wtorek', 'Wednesday' => 'środę',
            'Thursday' => 'czwartek', 'Friday' => 'piątek', 'Saturday' => 'sobotę', 'Sunday' => 'niedzielę'
        ];
        $dayPl = $polishDays[$dayOfWeek] ?? $dayOfWeek;
        
        $actualTotal = $actual['totals']['occupied'] ?? 0;
        $totalSeats = $actual['totals']['total'] ?? 1;
        $avgOccupancy = round(($actualTotal / $totalSeats) * 100, 1);
        
        $isWeekend = in_array($dayOfWeek, ['Friday', 'Saturday', 'Sunday']);
        $expectedRange = $isWeekend ? [20, 40] : [8, 20];
        
        if ($avgOccupancy > $expectedRange[1] + 10) {
            $insights[] = $this->addInsight(
                self::TYPE_PATTERN,
                "Wysoka frekwencja na $dayPl ($date)",
                "Obłożenie $avgOccupancy% przekracza typowe " . $expectedRange[1] . "% dla tego dnia. " .
                "Sprawdzam czy to stały trend czy jednorazowy skok.",
                ['forDate' => $date, 'day' => $dayOfWeek, 'occupancy' => $avgOccupancy, 'icon' => 'trending_up']
            );
        } elseif ($avgOccupancy < $expectedRange[0] && $avgOccupancy > 0) {
            $insights[] = $this->addInsight(
                self::TYPE_PATTERN,
                "Niska frekwencja na $dayPl ($date)",
                "Obłożenie $avgOccupancy% jest poniżej typowych " . $expectedRange[0] . "%. " .
                "Możliwy wpływ: pogoda, konkurencyjne wydarzenia, okres przedświąteczny.",
                ['forDate' => $date, 'day' => $dayOfWeek, 'occupancy' => $avgOccupancy, 'icon' => 'trending_down']
            );
        }
    }
    
    public function getLearningStats() {
        $stats = [
            'totalInsights' => count($this->insights),
            'corrections' => 0,
            'verifications' => 0,
            'learnings' => 0,
            'patterns' => 0,
            'avgAccuracy' => null,
            'recentTrend' => null
        ];
        
        $accuracies = [];
        
        foreach ($this->insights as $insight) {
            switch ($insight['type'] ?? '') {
                case self::TYPE_CORRECTION:
                    $stats['corrections']++;
                    break;
                case self::TYPE_VERIFICATION:
                    $stats['verifications']++;
                    if (isset($insight['details']['accuracy'])) {
                        $accuracies[] = $insight['details']['accuracy'];
                    }
                    break;
                case self::TYPE_LEARNING:
                    $stats['learnings']++;
                    break;
                case self::TYPE_PATTERN:
                    $stats['patterns']++;
                    break;
            }
        }
        
        if (!empty($accuracies)) {
            $stats['avgAccuracy'] = round(array_sum($accuracies) / count($accuracies) * 100);
        }
        
        if ($stats['verifications'] > $stats['corrections']) {
            $stats['recentTrend'] = 'improving';
        } elseif ($stats['corrections'] > $stats['verifications'] * 2) {
            $stats['recentTrend'] = 'learning';
        } else {
            $stats['recentTrend'] = 'stable';
        }
        
        return $stats;
    }
    
    /**
     * Clear all insights (for reset)
     */
    public function clearInsights() {
        $this->insights = [];
        $this->saveInsights();
    }
    
    /**
     * Extract applied factors from prediction data
     * Shows which factors were actually active (non-1.0 multipliers)
     */
    private function extractAppliedFactors($predicted) {
        $factors = [];
        
        if (!isset($predicted['hourly']) || empty($predicted['hourly'])) {
            return $factors;
        }
        
        // Get factors from first hour as representative
        $firstHour = reset($predicted['hourly']);
        
        if (isset($firstHour['appliedFactors'])) {
            return $firstHour['appliedFactors'];
        }
        
        // Fallback: extract from multipliers if appliedFactors not available
        if (isset($firstHour['multipliers'])) {
            $mults = $firstHour['multipliers'];
            
            if (isset($mults['holiday']) && $mults['holiday'] != 1.0) {
                $factors[] = ['name' => 'Święto', 'value' => $mults['holiday']];
            }
            if (isset($mults['weather']) && $mults['weather'] != 1.0) {
                $factors[] = ['name' => 'Pogoda', 'value' => $mults['weather']];
            }
            if (isset($mults['season']) && $mults['season'] != 1.0) {
                $factors[] = ['name' => 'Sezon', 'value' => $mults['season']];
            }
            if (isset($mults['payday']) && $mults['payday'] != 1.0) {
                $factors[] = ['name' => 'Dzień płacy', 'value' => $mults['payday']];
            }
            if (isset($mults['longWeekend']) && $mults['longWeekend'] != 1.0) {
                $factors[] = ['name' => 'Długi weekend', 'value' => $mults['longWeekend']];
            }
            if (isset($mults['sports']) && $mults['sports'] != 1.0) {
                $factors[] = ['name' => 'Sport', 'value' => $mults['sports']];
            }
        }
        
        return $factors;
    }
    
    /**
     * Get multipliers summary as human-readable text
     */
    private function getMultipliersSummary($predicted) {
        if (!isset($predicted['hourly']) || empty($predicted['hourly'])) {
            return null;
        }
        
        $firstHour = reset($predicted['hourly']);
        if (!isset($firstHour['multipliers'])) {
            return null;
        }
        
        $mults = $firstHour['multipliers'];
        $summary = [];
        
        $factorNames = [
            'holiday' => 'święto',
            'weather' => 'pogoda', 
            'season' => 'sezon',
            'payday' => 'wypłata',
            'longWeekend' => 'długi weekend',
            'sports' => 'sport',
            'school' => 'ferie',
            'firstWarmWeekend' => 'pierwszy ciepły weekend'
        ];
        
        foreach ($factorNames as $key => $name) {
            if (isset($mults[$key]) && $mults[$key] != 1.0) {
                $pct = round(($mults[$key] - 1) * 100);
                $sign = $pct > 0 ? '+' : '';
                $summary[] = "$name: {$sign}{$pct}%";
            }
        }
        
        return empty($summary) ? 'brak aktywnych modyfikatorów' : implode(', ', $summary);
    }
    
    /**
     * Get learning weights adjustment info
     * Reads from learning_weights.json to show current state
     */
    private function getLearningWeightsAdjustment($dayType, $percentDiff) {
        $learningFile = $this->historyDir . '/../cache/learning_weights.json';
        
        if (!file_exists($learningFile)) {
            return [
                'status' => 'initializing',
                'message' => 'System uczący się dopiero zbiera dane'
            ];
        }
        
        $data = json_decode(file_get_contents($learningFile), true);
        
        if (!isset($data[$dayType])) {
            return [
                'status' => 'new_daytype',
                'message' => "Pierwszy raz analizuję typ dnia: $dayType"
            ];
        }
        
        $weights = $data[$dayType]['weights'];
        $samples = $data[$dayType]['samples'] ?? 0;
        $accuracy = $data[$dayType]['accuracy'] ?? [];
        $avgAccuracy = !empty($accuracy) ? round(array_sum($accuracy) / count($accuracy) * 100) : null;
        
        return [
            'status' => 'active',
            'weights' => $weights,
            'samples' => $samples,
            'avgAccuracy' => $avgAccuracy,
            'message' => "Wagi dla $dayType: historyczne=" . round($weights['historical']*100) . "%, " .
                        "gatunek=" . round($weights['genre']*100) . "%, " .
                        "premiera=" . round($weights['premiere']*100) . "%, " .
                        "zewnętrzne=" . round($weights['external']*100) . "% (próbki: $samples)"
        ];
    }
    
    /**
     * Describe the adjustment being made
     * Human-readable explanation of weight corrections
     */
    private function describeAdjustment($direction, $dayType, $percentDiff, $appliedFactors) {
        $adjustments = [];
        
        // Core adjustment based on direction
        if ($direction === 'up') {
            $adjustments[] = "Zwiększam wagę danych historycznych o 2% dla '$dayType'";
            $adjustments[] = "Zmniejszam wagę czynników zewnętrznych o 2%";
        } else {
            $adjustments[] = "Zmniejszam wagę danych historycznych o 2% dla '$dayType'";
            $adjustments[] = "Zwiększam wagę czynników zewnętrznych o 2%";
        }
        
        // Identify which factors might have caused the error
        if (!empty($appliedFactors)) {
            $factorNames = [];
            foreach ($appliedFactors as $factor) {
                if (is_array($factor) && isset($factor['name'])) {
                    $factorNames[] = $factor['name'];
                } elseif (is_string($factor)) {
                    $factorNames[] = $factor;
                }
            }
            
            if (!empty($factorNames)) {
                $adjustments[] = "Aktywne modyfikatory: " . implode(', ', $factorNames);
                
                if ($direction === 'up' && $percentDiff > 25) {
                    $adjustments[] = "[UWAGA] Możliwe że modyfikatory są zbyt konserwatywne";
                } elseif ($direction === 'down' && $percentDiff > 25) {
                    $adjustments[] = "[UWAGA] Możliwe że modyfikatory są zbyt optymistyczne";
                }
            }
        }
        
        return implode(". ", $adjustments) . ".";
    }
    
    /**
     * Generate weekly or monthly performance report
     * Call this from cron (e.g., every Sunday for weekly, 1st of month for monthly)
     * @param string $period 'weekly' or 'monthly'
     * @return array|null Generated insight or null if not enough data
     */
    public function generatePerformanceReport($period = 'weekly') {
        $now = new DateTime();
        $type = $period === 'monthly' ? self::TYPE_MONTHLY_REPORT : self::TYPE_WEEKLY_REPORT;
        
        // Check if we already generated a report for this period
        $periodKey = $period === 'monthly' 
            ? $now->format('Y-m') 
            : $now->format('Y-W');
        
        foreach ($this->insights as $insight) {
            if (($insight['type'] ?? '') === $type && 
                isset($insight['details']['periodKey']) && 
                $insight['details']['periodKey'] === $periodKey) {
                return null; // Already generated
            }
        }
        
        // Calculate date range
        if ($period === 'monthly') {
            $startDate = (clone $now)->modify('first day of last month')->format('Y-m-d');
            $endDate = (clone $now)->modify('last day of last month')->format('Y-m-d');
            $periodName = (clone $now)->modify('first day of last month')->format('F Y');
        } else {
            $startDate = (clone $now)->modify('-7 days')->format('Y-m-d');
            $endDate = (clone $now)->modify('-1 day')->format('Y-m-d');
            // Format as readable date range: "15.12 - 21.12.2025"
            $startFormatted = (clone $now)->modify('-7 days')->format('d.m');
            $endFormatted = (clone $now)->modify('-1 day')->format('d.m.Y');
            $periodName = "$startFormatted - $endFormatted";
        }
        
        // Collect insights from this period
        $periodInsights = array_filter($this->insights, function($i) use ($startDate, $endDate) {
            $insightDate = $i['details']['forDate'] ?? ($i['date'] ?? null);
            return $insightDate && $insightDate >= $startDate && $insightDate <= $endDate;
        });
        
        if (count($periodInsights) < 3) {
            return null; // Not enough data
        }
        
        // Calculate statistics
        $accuracies = [];
        $corrections = 0;
        $verifications = 0;
        $learnings = 0;
        $dayAccuracies = [];
        
        foreach ($periodInsights as $insight) {
            $insightType = $insight['type'] ?? '';
            
            if ($insightType === self::TYPE_VERIFICATION) {
                $verifications++;
                if (isset($insight['details']['accuracy'])) {
                    $acc = $insight['details']['accuracy'];
                    $accuracies[] = $acc;
                    
                    // Track by day of week
                    $forDate = $insight['details']['forDate'] ?? null;
                    if ($forDate) {
                        $dayOfWeek = date('l', strtotime($forDate));
                        if (!isset($dayAccuracies[$dayOfWeek])) {
                            $dayAccuracies[$dayOfWeek] = [];
                        }
                        $dayAccuracies[$dayOfWeek][] = $acc;
                    }
                }
            } elseif ($insightType === self::TYPE_CORRECTION) {
                $corrections++;
            } elseif ($insightType === self::TYPE_LEARNING) {
                $learnings++;
            }
        }
        
        if (empty($accuracies)) {
            return null; // No accuracy data
        }
        
        // Calculate averages
        $avgAccuracy = round(array_sum($accuracies) / count($accuracies) * 100, 1);
        $minAccuracy = round(min($accuracies) * 100, 1);
        $maxAccuracy = round(max($accuracies) * 100, 1);
        
        // Find best and worst days
        $bestDay = null;
        $worstDay = null;
        $bestAvg = 0;
        $worstAvg = 100;
        
        $polishDays = [
            'Monday' => 'poniedziałki', 'Tuesday' => 'wtorki', 'Wednesday' => 'środy',
            'Thursday' => 'czwartki', 'Friday' => 'piątki', 'Saturday' => 'soboty', 'Sunday' => 'niedziele'
        ];
        
        foreach ($dayAccuracies as $day => $accs) {
            $dayAvg = array_sum($accs) / count($accs) * 100;
            if ($dayAvg > $bestAvg) {
                $bestAvg = $dayAvg;
                $bestDay = $polishDays[$day] ?? $day;
            }
            if ($dayAvg < $worstAvg) {
                $worstAvg = $dayAvg;
                $worstDay = $polishDays[$day] ?? $day;
            }
        }
        
        // Get learning weights info
        $learningFile = $this->historyDir . '/../cache/learning_weights.json';
        $weightsInfo = '';
        if (file_exists($learningFile)) {
            $weightsData = json_decode(file_get_contents($learningFile), true);
            $totalSamples = 0;
            foreach ($weightsData as $dt => $info) {
                $totalSamples += $info['samples'] ?? 0;
            }
            $weightsInfo = "Zebrano $totalSamples próbek do nauki.";
        }
        
        // Build message
        $messages = [];
        $messages[] = "Średnia celność: {$avgAccuracy}%";
        $messages[] = "Zakres: {$minAccuracy}% - {$maxAccuracy}%";
        
        if ($bestDay && $worstDay && $bestDay !== $worstDay) {
            $messages[] = "Najlepiej przewidziane: {$bestDay} (" . round($bestAvg) . "%)";
            $messages[] = "Najtrudniejsze: {$worstDay} (" . round($worstAvg) . "%)";
        }
        
        $messages[] = "Weryfikacji: $verifications, Korekt: $corrections, Nauka: $learnings";
        
        if ($weightsInfo) {
            $messages[] = $weightsInfo;
        }
        
        // Trend analysis
        $trend = 'stabilny';
        if ($avgAccuracy >= 85) {
            $trend = 'świetny';
            $messages[] = "Algorytm działa bardzo dobrze!";
        } elseif ($avgAccuracy >= 75) {
            $trend = 'dobry';
            $messages[] = "Dobre wyniki, kontynuuję optymalizację.";
        } elseif ($avgAccuracy >= 65) {
            $trend = 'umiarkowany';
            $messages[] = "Wyniki mogą być lepsze, analizuję źródła błędów.";
        } else {
            $trend = 'wymaga uwagi';
            $messages[] = "Wyniki poniżej oczekiwań, skupiam się na nauce.";
        }
        
        // Create insight
        $title = $period === 'monthly' 
            ? "Raport miesięczny: $periodName"
            : "Raport tygodniowy: $periodName";
        
        $insight = $this->addInsight(
            $type,
            $title,
            implode(" ", $messages),
            [
                'periodKey' => $periodKey,
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'avgAccuracy' => $avgAccuracy,
                'minAccuracy' => $minAccuracy,
                'maxAccuracy' => $maxAccuracy,
                'verifications' => $verifications,
                'corrections' => $corrections,
                'learnings' => $learnings,
                'bestDay' => $bestDay,
                'worstDay' => $worstDay,
                'trend' => $trend,
                'icon' => $period === 'monthly' ? 'calendar_month' : 'date_range'
            ]
        );
        
        return $insight;
    }
    
    /**
     * Check and generate reports if needed
     * Call this daily from cron
     */
    public function checkAndGenerateReports() {
        $generated = [];
        $dayOfWeek = date('N'); // 1=Monday, 7=Sunday
        $dayOfMonth = date('j'); // 1-31
        
        // Generate weekly report on Sundays
        if ($dayOfWeek == 7) {
            $weekly = $this->generatePerformanceReport('weekly');
            if ($weekly) {
                $generated[] = $weekly;
            }
        }
        
        // Generate monthly report on 1st of month
        if ($dayOfMonth == 1) {
            $monthly = $this->generatePerformanceReport('monthly');
            if ($monthly) {
                $generated[] = $monthly;
            }
        }
        
        return $generated;
    }

    /**
     * Log multiplier adjustments from Per-Factor Learning System
     * NEW: Provides transparency into what the system is learning
     */
    public function logLearningAdjustments($learningResult) {
        if (!isset($learningResult['adjustments']) || empty($learningResult['adjustments'])) {
            return false;
        }

        $date = date('Y-m-d');
        
        // DEDUPE: Check if factor correction insight for this date already exists
        foreach ($this->insights as $insight) {
            if ($insight['type'] === self::TYPE_CORRECTION && 
                isset($insight['details']['forDate']) && 
                $insight['details']['forDate'] === $date &&
                str_contains($insight['title'] ?? '', 'Auto-korekta')) {
                return false; // Already logged for today
            }
        }
        
        $factors = [];
        $factorNames = [
            'combined_mult' => 'Mnożnik całkowity',
            'weather' => 'Pogoda',
            'season' => 'Sezon',
            'holiday' => 'Święto',
            'payday' => 'Wypłata',
            'sports' => 'Sport',
            'longWeekend' => 'Długi weekend',
            'firstWarmWeekend' => 'Pierwszy ciepły weekend'
        ];
        
        $factorIcons = [
            'combined_mult' => 'calculate',
            'weather' => 'partly_cloudy_day',
            'season' => 'seasons',
            'holiday' => 'celebration',
            'payday' => 'payments',
            'sports' => 'sports_soccer',
            'longWeekend' => 'weekend',
            'firstWarmWeekend' => 'wb_sunny'
        ];
        
        $keyTranslations = [
            'winter' => 'zima',
            'spring' => 'wiosna',
            'summer' => 'lato',
            'autumn' => 'jesień',
            'christmas' => 'Boże Narodzenie',
            'christmas_eve' => 'Wigilia',
            'new_years_eve' => 'Sylwester',
            'new_year' => 'Nowy Rok',
            'valentines' => 'Walentynki',
            'childrens_day' => 'Dzień Dziecka',
            'default' => ''
        ];

        $factorsArray = []; // Structured data for badges
        foreach ($learningResult['adjustments'] as $adj) {
            // Format name nicely
            $typeRaw = $adj['factorType'];
            $typeStart = $factorNames[$typeRaw] ?? ucfirst($typeRaw);
            
            // Fix icon: 'seasons' might not exist in all fonts, use 'calendar_month' or 'ac_unit' for winter
            $icon = $factorIcons[$typeRaw] ?? 'tune';
            if ($typeRaw === 'season') $icon = 'calendar_month'; 
            
            $keyRaw = $adj['factorKey'];
            $keyTrans = $keyTranslations[$keyRaw] ?? $keyRaw;
            
            $keyName = ($keyRaw !== 'default' && $keyRaw !== 'total') ? ' (' . $keyTrans . ')' : '';
            
            $name = "{$typeStart}{$keyName}";
            $change = $adj['newValue'] - $adj['currentValue'];
            $arrow = $change > 0 ? '↗' : '↘';
            $impactStr = "{$adj['currentValue']} {$arrow} {$adj['newValue']}";
            
            $factors[] = "{$name}: {$impactStr}";
            
            $factorsArray[] = [
                'name' => $name,
                'icon' => $icon,
                'impact' => $impactStr,
                'type' => $change > 0 ? 'positive' : 'negative'
            ];
        }
        
        $factorsList = implode(', ', array_slice($factors, 0, 3));
        if (count($factors) > 3) $factorsList .= " i " . (count($factors)-3) . " więcej";

        return $this->addInsight(
            self::TYPE_CORRECTION,  // Changed from TYPE_LEARNING - we're correcting factors
            "Auto-korekta wag " . ($learningResult['direction'] == 'overpredicted' ? '(Zbyt wysoka)' : '(Zbyt niska)'),
            "System wykrył odchylenie {$learningResult['error']} i dostosował wagi dla czynników:",
            [
                'error' => $learningResult['error'],
                'direction' => $learningResult['direction'],
                'adjustments' => $learningResult['adjustments'],
                'factorsArray' => $factorsArray, // Smart badges
                'forDate' => $date
            ]
        );
    }
}
