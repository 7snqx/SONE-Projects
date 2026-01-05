<?php
/**
 * Factor Learning System
 * 
 * Tracks and learns which specific factors (weather, holidays, seasons, etc.)
 * contribute to prediction errors and automatically adjusts their multipliers.
 * 
 * This system identifies WHICH factor caused the error, not just that an error occurred.
 * 
 * @version 1.0
 */

class FactorLearningSystem {
    private $cacheDir;
    private $dataFile;
    private $data;
    
    // Learning configuration
    private $config = [
        'learningRate' => 0.1,          // How fast to adjust (0.1 = 10% of error)
        'minSamples' => 3,              // Minimum samples before adjusting
        'maxAdjustment' => 0.15,        // Max single adjustment (±15%)
        'decayRate' => 0.95,            // Weight decay for old samples
        'minMultiplier' => 0.5,         // Minimum allowed multiplier
        'maxMultiplier' => 2.0,         // Maximum allowed multiplier
        'significanceThreshold' => 0.10  // Minimum error to consider significant (10%)
    ];
    
    // Default multiplier values (starting points)
    private $defaultMultipliers = [
        'holiday' => [
            'christmas' => 1.30,
            'christmas_eve' => 0.60,
            'new_years_eve' => 0.60,
            'new_year' => 1.10,
            'valentines' => 1.15,
            'childrens_day' => 1.10,
            'post_christmas' => 1.15,
            'default' => 1.0
        ],
        'weather' => [
            'rain_light' => 1.20,
            'rain_moderate' => 1.35,
            'rain_heavy' => 1.45,
            'cold' => 1.10,
            'very_cold' => 1.15,
            'hot_sunny' => 0.85,
            'hot' => 0.75,
            'very_hot' => 0.65,
            'perfect_outdoor' => 0.80,
            'default' => 1.0
        ],
        'season' => [
            'summer' => 0.90,
            'winter' => 1.10,
            'spring' => 1.0,
            'autumn' => 1.0
        ],
        'school' => [
            'holidays' => 1.25,
            'regular' => 1.0
        ],
        'payday' => [
            'after_payday' => 1.05,
            'before_payday' => 0.95,
            'regular' => 1.0
        ],
        'sports' => [
            'national_team' => 0.60,
            'local_match' => 0.80,
            'match_day' => 0.80, // Generic match day alias
            'other_ekstraklasa' => 0.95,
            'default' => 1.0
        ],
        'day_type' => [
            'weekend' => 1.0,
            'workday' => 1.0,
            'tuesday' => 1.0
        ],
        'trading_ban' => [
            'ban' => 1.15,      // More people when shops closed
            'shopping' => 0.90, // Less people when shops open (competition)
            'default' => 1.0
        ],
        'long_weekend' => [
            'yes' => 1.20,
            'no' => 1.0
        ]
    ];
    
    public function __construct($cacheDir) {
        $this->cacheDir = $cacheDir;
        $this->dataFile = $cacheDir . '/factor_learning.json';
        $this->loadData();
    }
    
    /**
     * Load learning data from file
     */
    private function loadData() {
        if (file_exists($this->dataFile)) {
            $this->data = json_decode(file_get_contents($this->dataFile), true) ?: [];
        } else {
            $this->data = [
                'multipliers' => [],
                'history' => [],
                'stats' => [
                    'totalSamples' => 0,
                    'avgError' => 0,
                    'lastUpdate' => null
                ]
            ];
        }
    }
    
    /**
     * Save learning data to file
     */
    private function saveData() {
        $this->data['stats']['lastUpdate'] = date('c');
        @file_put_contents(
            $this->dataFile, 
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
    
    /**
     * Get the current (learned) multiplier for a factor
     * Returns learned value if available, otherwise default
     */
    public function getMultiplier($factorType, $factorKey) {
        // Check if we have a learned value
        if (isset($this->data['multipliers'][$factorType][$factorKey]['value'])) {
            return $this->data['multipliers'][$factorType][$factorKey]['value'];
        }
        
        // Return default
        return $this->defaultMultipliers[$factorType][$factorKey] 
            ?? $this->defaultMultipliers[$factorType]['default'] 
            ?? 1.0;
    }
    
    /**
     * Record a prediction with its active factors
     * Called when making a prediction
     * 
     * IMPORTANT: Reloads data from file before saving to prevent
     * stale in-memory data from overwriting changes made by other instances.
     */
    public function recordPrediction($date, $factors, $predicted, $baseValue) {
        // CRITICAL: Reload fresh data from file to prevent overwriting
        // changes made by other FLS instances (e.g., analyzed entries)
        $this->loadData();
        
        // Don't overwrite if already exists and has actual data or was analyzed
        if (isset($this->data['history'][$date])) {
            $existing = $this->data['history'][$date];
            if ($existing['actual'] !== null || $existing['analyzed']) {
                return; // Already processed, don't overwrite
            }
        }
        
        $this->data['history'][$date] = [
            'factors' => $factors,
            'predicted' => $predicted,
            'baseValue' => $baseValue,
            'actual' => null,
            'analyzed' => false,
            'recordedAt' => date('c')
        ];
        $this->saveData();
    }
    
    /**
     * Record actual result for a date
     * Called by cron when actual data is known
     */
    public function recordActual($date, $actual) {
        if (!isset($this->data['history'][$date])) {
            // No prediction recorded for this date
            return false;
        }
        
        $this->data['history'][$date]['actual'] = $actual;
        $this->saveData();
        
        return true;
    }
    
    /**
     * Analyze factor attribution for a date
     * Identifies which factor contributed most to the prediction error
     * 
     * @return array Analysis results with factor attributions
     */
    public function analyzeFactorAttribution($date) {
        if (!isset($this->data['history'][$date])) {
            return ['error' => 'No data for this date'];
        }
        
        $entry = $this->data['history'][$date];
        
        if ($entry['actual'] === null) {
            return ['error' => 'Actual data not yet recorded'];
        }
        
        if ($entry['analyzed']) {
            return ['error' => 'Already analyzed'];
        }
        
        $predicted = $entry['predicted'];
        $actual = $entry['actual'];
        $factors = $entry['factors'];
        $baseValue = $entry['baseValue'];
        
        // Calculate prediction error
        $error = ($predicted - $actual) / max($actual, 1);
        $absoluteError = abs($error);
        
        // Check if error is significant enough to learn from (> 10%, not >= 10%)
        if ($absoluteError <= $this->config['significanceThreshold']) {
            $this->data['history'][$date]['analyzed'] = true;
            $this->data['history'][$date]['result'] = [
                'status' => 'accurate',
                'error' => round($error * 100, 2) . '%',
                'message' => 'Prediction was accurate, no adjustment needed'
            ];
            $this->saveData();
            return $this->data['history'][$date]['result'];
        }
        
        // Calculate total adjustment from factors
        $totalMultiplier = 1.0;
        $factorContributions = [];
        
        foreach ($factors as $factorType => $factorData) {
            if (!isset($factorData['key']) || !isset($factorData['value'])) {
                continue;
            }
            
            $key = $factorData['key'];
            $value = $factorData['value'];
            
            $totalMultiplier *= $value;
            
            // Calculate this factor's "impact" on the total adjustment
            // Impact = how much this factor moved the prediction away from base
            $factorImpact = $value - 1.0;
            
            $factorContributions[$factorType] = [
                'key' => $key,
                'value' => $value,
                'impact' => $factorImpact,
                'responsibility' => 0 // Will be calculated
            ];
        }
        
        // Calculate total absolute impact (for proportional distribution)
        $totalAbsoluteImpact = 0.0;
        foreach ($factorContributions as $type => &$contrib) {
            $totalAbsoluteImpact += abs($contrib['impact']);
        }
        unset($contrib);
        
        // Attribute responsibility PROPORTIONALLY to absolute impact
        // ALL factors move in the SAME direction - up if underpredicted, down if overpredicted
        if ($totalAbsoluteImpact > 0.01) {
            foreach ($factorContributions as $type => &$contrib) {
                // Responsibility = what fraction of the TOTAL impact this factor has
                // This is ALWAYS positive - direction is determined by error sign later
                $contrib['responsibility'] = abs($contrib['impact']) / $totalAbsoluteImpact;
                $contrib['absoluteResponsibility'] = $contrib['responsibility'];
            }
            unset($contrib);
            
            // Sort by responsibility (highest first)
            uasort($factorContributions, function($a, $b) {
                return $b['absoluteResponsibility'] <=> $a['absoluteResponsibility'];
            });
        }
        
        // Determine which factor(s) to adjust
        $adjustments = [];
        $remainingResponsibility = 1.0;  // Use responsibility fraction instead of error
        
        foreach ($factorContributions as $type => $contrib) {
            if ($remainingResponsibility <= 0 || $contrib['absoluteResponsibility'] < 0.05) {
                break;
            }
            
            // Dynamic Learning Rate based on Error Magnitude & Sample Confidence
            $dynamicRate = $this->config['learningRate'];
            $dynamicMaxAdj = $this->config['maxAdjustment'];
            
            // 1. Error-Weighted Correction (Non-linear learning)
            // If error is large (>20%), we want to correct much faster to adapt to the trend.
            if ($absoluteError > 0.20) {
                 $boost = 1.0 + ($absoluteError - 0.20) * 3.0; 
                 $dynamicRate *= min($boost, 4.0);
                 $dynamicMaxAdj = 0.35; 
            }

            // 2. Sample-based Dampening (Noise Filter)
            $currentSamples = 0;
            if (isset($this->data['multipliers'][$type][$contrib['key']]['samples'])) {
                $currentSamples = $this->data['multipliers'][$type][$contrib['key']]['samples'];
            }
            
            $confidenceFactor = min(1.0, ($currentSamples + 1) / max(1, $this->config['minSamples']));
            $dynamicRate *= $confidenceFactor;
            
            // Calculate adjustment amount based on this factor's responsibility share
            // The total error is distributed across factors based on their responsibility
            $adjustmentAmount = min(
                $dynamicMaxAdj,
                $absoluteError * $contrib['absoluteResponsibility'] * $dynamicRate
            );
            
            // CRITICAL FIX: Direction is determined ONLY by whether we over or underpredicted
            // If error > 0 (overpredicted = predicted too high) -> ALL multipliers must DECREASE
            // If error < 0 (underpredicted = predicted too low) -> ALL multipliers must INCREASE
            $direction = $error > 0 ? -1 : 1;
            
            $finalAdjustment = $adjustmentAmount * $direction;
            
            $adjustments[] = [
                'factorType' => $type,
                'factorKey' => $contrib['key'],
                'currentValue' => $contrib['value'],
                'adjustment' => $finalAdjustment,
                'newValue' => $this->applyAdjustment($type, $contrib['key'], $finalAdjustment),
                'responsibility' => round($contrib['responsibility'] * 100, 2) . '%'
            ];
            
            $remainingResponsibility -= $contrib['absoluteResponsibility'];
        }
        
        // Store analysis result
        $result = [
            'status' => 'adjusted',
            'error' => round($error * 100, 2) . '%',
            'direction' => $error > 0 ? 'overpredicted' : 'underpredicted',
            'predicted' => $predicted,
            'actual' => $actual,
            'adjustments' => $adjustments,
            'factorContributions' => $factorContributions
        ];
        
        $this->data['history'][$date]['analyzed'] = true;
        $this->data['history'][$date]['result'] = $result;
        $this->data['stats']['totalSamples']++;
        
        // Update running average error
        $this->data['stats']['avgError'] = 
            ($this->data['stats']['avgError'] * ($this->data['stats']['totalSamples'] - 1) + $absoluteError) 
            / $this->data['stats']['totalSamples'];
        
        $this->saveData();
        
        return $result;
    }
    
    /**
     * Apply adjustment to a factor multiplier and save
     */
    private function applyAdjustment($factorType, $factorKey, $adjustment) {
        // Initialize if not exists
        if (!isset($this->data['multipliers'][$factorType])) {
            $this->data['multipliers'][$factorType] = [];
        }
        if (!isset($this->data['multipliers'][$factorType][$factorKey])) {
            $this->data['multipliers'][$factorType][$factorKey] = [
                'value' => $this->getMultiplier($factorType, $factorKey),
                'samples' => 0,
                'adjustments' => [],
                'originalValue' => $this->defaultMultipliers[$factorType][$factorKey] ?? 1.0
            ];
        }
        
        $current = $this->data['multipliers'][$factorType][$factorKey]['value'];
        $new = $current + $adjustment;
        
        // Clamp to allowed range
        $new = max($this->config['minMultiplier'], min($this->config['maxMultiplier'], $new));
        
        // Store adjustment history
        $this->data['multipliers'][$factorType][$factorKey]['adjustments'][] = [
            'date' => date('Y-m-d'),
            'from' => round($current, 3),
            'to' => round($new, 3),
            'adjustment' => round($adjustment, 4)
        ];
        
        // Keep only last 30 adjustments
        if (count($this->data['multipliers'][$factorType][$factorKey]['adjustments']) > 30) {
            array_shift($this->data['multipliers'][$factorType][$factorKey]['adjustments']);
        }
        
        $this->data['multipliers'][$factorType][$factorKey]['value'] = round($new, 3);
        $this->data['multipliers'][$factorType][$factorKey]['samples']++;
        
        return round($new, 3);
    }
    
    /**
     * Learn from a day's data
     * Main entry point called by cron
     * 
     * If no prediction was recorded for this date, we generate a retroactive prediction
     * using current factor values to enable learning.
     */
    public function learnFromDay($date, $actualOccupancy) {
        // Check if we have a prediction for this date
        if (!isset($this->data['history'][$date])) {
            // No prediction recorded - generate retroactive prediction
            $retroactiveFactors = $this->generateRetroactiveFactors($date);
            
            if ($retroactiveFactors) {
                // Calculate what we would have predicted
                $combinedMult = 1.0;
                foreach ($retroactiveFactors as $type => $factor) {
                    if (isset($factor['value']) && is_numeric($factor['value'])) {
                        $combinedMult *= $factor['value'];
                    }
                }
                
                // Estimate base value from actual (reverse calculate)
                // This isn't perfect but allows learning to start
                $baseValue = $actualOccupancy / max(0.5, $combinedMult);
                $predictedValue = round($baseValue * $combinedMult);
                
                // Record the retroactive prediction
                $this->data['history'][$date] = [
                    'factors' => $retroactiveFactors,
                    'predicted' => $predictedValue,
                    'baseValue' => $baseValue,
                    'actual' => null,
                    'analyzed' => false,
                    'recordedAt' => date('c'),
                    'retroactive' => true  // Mark as retroactive
                ];
                $this->saveData();
            } else {
                return ['status' => 'error', 'message' => 'Could not generate retroactive prediction'];
            }
        }
        
        // Record actual value
        $this->recordActual($date, $actualOccupancy);
        
        // Analyze and adjust (daily)
        $dailyResult = $this->analyzeFactorAttribution($date);
        
        // Also analyze hourly if we have hourly data
        $hourlyResult = null;
        if (isset($this->data['hourlyHistory'][$date])) {
            $hourlyResult = $this->analyzeHourlyAccuracy($date);
        }
        
        return [
            'daily' => $dailyResult,
            'hourly' => $hourlyResult
        ];
    }
    
    /**
     * Learn from hourly data for a specific date
     * Called with actual hourly occupancy data
     * 
     * @param string $date Date to learn from
     * @param array $hourlyActual Array of hour => occupied values
     * @return array Learning result
     */
    public function learnHourlyFromDay($date, $hourlyActual) {
        // Record actual hourly values
        $this->recordHourlyActual($date, $hourlyActual);
        
        // Analyze and update hourly biases
        return $this->analyzeHourlyAccuracy($date);
    }
    
    /**
     * Generate retroactive factors for a date based on calendar data
     */
    private function generateRetroactiveFactors($date) {
        $factors = [];
        
        // Holiday factor
        $holidayKey = $this->detectHoliday($date);
        $factors['holiday'] = [
            'key' => $holidayKey,
            'value' => $this->getMultiplier('holiday', $holidayKey)
        ];
        
        // Weather - use default since we don't know historical weather
        $factors['weather'] = [
            'key' => 'default',
            'value' => $this->getMultiplier('weather', 'default')
        ];
        
        // Season
        $seasonKey = $this->detectSeason($date);
        $factors['season'] = [
            'key' => $seasonKey,
            'value' => $this->getMultiplier('season', $seasonKey)
        ];
        
        // Payday
        $paydayKey = $this->detectPayday($date);
        $factors['payday'] = [
            'key' => $paydayKey,
            'value' => $this->getMultiplier('payday', $paydayKey)
        ];
        
        // Calculate combined multiplier
        $combinedMult = 1.0;
        foreach ($factors as $f) {
            $combinedMult *= $f['value'];
        }
        $factors['combined_mult'] = [
            'key' => 'total',
            'value' => $combinedMult
        ];
        
        return $factors;
    }
    
    /**
     * Detect holiday for a date
     */
    private function detectHoliday($date) {
        $md = date('m-d', strtotime($date));
        $year = date('Y', strtotime($date));
        
        // Fixed date holidays
        $holidays = [
            '12-24' => 'christmas_eve',
            '12-25' => 'christmas',
            '12-26' => 'christmas',
            '12-31' => 'new_years_eve',
            '01-01' => 'new_year',
            '02-14' => 'valentines',
            '06-01' => 'childrens_day',
        ];
        
        if (isset($holidays[$md])) {
            return $holidays[$md];
        }
        
        // Post-Christmas period (Dec 27-30)
        if ($md >= '12-27' && $md <= '12-30') {
            return 'post_christmas';
        }
        
        return 'default';
    }
    
    /**
     * Detect season for a date
     */
    private function detectSeason($date) {
        $month = (int)date('n', strtotime($date));
        
        if ($month >= 12 || $month <= 2) return 'winter';
        if ($month >= 3 && $month <= 5) return 'spring';
        if ($month >= 6 && $month <= 8) return 'summer';
        return 'autumn';
    }
    
    /**
     * Detect payday period for a date
     */
    private function detectPayday($date) {
        $day = (int)date('j', strtotime($date));
        
        if ($day >= 10 && $day <= 15) return 'after_payday';
        if ($day >= 25 || $day <= 5) return 'after_payday';
        
        return 'regular';
    }
    
    /**
     * Get all learned multipliers with statistics
     */
    public function getLearnedMultipliers() {
        $result = [];
        
        foreach ($this->defaultMultipliers as $type => $keys) {
            foreach ($keys as $key => $defaultValue) {
                // Skip 'default' keys UNLESS they have been learned
                $learned = $this->data['multipliers'][$type][$key] ?? null;
                
                if ($key === 'default' && !$learned) {
                    continue; // Skip unlearned defaults
                }
                
                $result[$type][$key] = [
                    'default' => $defaultValue,
                    'current' => $learned['value'] ?? $defaultValue,
                    'samples' => $learned['samples'] ?? 0,
                    'deviation' => $learned ? round(($learned['value'] - $defaultValue) / $defaultValue * 100, 1) . '%' : '0%'
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get learning statistics
     */
    public function getStats() {
        // Count actual analyzed entries (more reliable than counter)
        $analyzedCount = 0;
        $totalError = 0;
        foreach ($this->data['history'] ?? [] as $entry) {
            if ($entry['analyzed'] ?? false) {
                $analyzedCount++;
                // Extract error percentage from result
                if (isset($entry['result']['error'])) {
                    $errorStr = str_replace('%', '', $entry['result']['error']);
                    $totalError += abs(floatval($errorStr));
                }
            }
        }
        
        $avgError = $analyzedCount > 0 ? $totalError / $analyzedCount : 0;
        
        return [
            'totalSamples' => $analyzedCount,
            'avgError' => round($avgError, 2) . '%',
            'lastUpdate' => $this->data['stats']['lastUpdate'] ?? 'Never',
            'learnedFactors' => count($this->data['multipliers'] ?? []),
            'historyEntries' => count($this->data['history'] ?? [])
        ];
    }
    
    /**
     * Reset a specific factor to its default value
     */
    public function resetFactor($factorType, $factorKey) {
        if (isset($this->data['multipliers'][$factorType][$factorKey])) {
            unset($this->data['multipliers'][$factorType][$factorKey]);
            $this->saveData();
            return true;
        }
        return false;
    }
    
    /**
     * Reset all learned data
     */
    public function resetAll() {
        $this->data = [
            'multipliers' => [],
            'history' => [],
            'stats' => [
                'totalSamples' => 0,
                'avgError' => 0,
                'lastUpdate' => null
            ]
        ];
        $this->saveData();
    }
    
    /**
     * Clean up old history entries (> 90 days)
     */
    public function cleanupHistory($maxDays = 90) {
        $cutoff = date('Y-m-d', strtotime("-{$maxDays} days"));
        $removed = 0;
        
        foreach ($this->data['history'] as $date => $entry) {
            if ($date < $cutoff) {
                unset($this->data['history'][$date]);
                $removed++;
            }
        }
        
        if ($removed > 0) {
            $this->saveData();
        }
        
        return $removed;
    }
    
    /**
     * Record hourly predictions for a date
     * Stores predicted vs actual for each hour to enable hourly learning
     */
    public function recordHourlyPrediction($date, $hourlyPredicted) {
        $this->loadData();
        
        if (!isset($this->data['hourlyHistory'])) {
            $this->data['hourlyHistory'] = [];
        }
        
        // Initialize or update hourly data for this date
        if (!isset($this->data['hourlyHistory'][$date])) {
            $this->data['hourlyHistory'][$date] = [
                'hours' => [],
                'analyzed' => false,
                'recordedAt' => date('c')
            ];
        }
        
        // Store predictions for each hour
        foreach ($hourlyPredicted as $hour => $data) {
            $this->data['hourlyHistory'][$date]['hours'][$hour] = [
                'predicted' => $data['adjustedOccupied'] ?? $data['predictedOccupied'] ?? 0,
                'actual' => null,
                'factors' => $data['learningFactors'] ?? []
            ];
        }
        
        $this->saveData();
    }
    
    /**
     * Record actual hourly data for a date
     */
    public function recordHourlyActual($date, $hourlyActual) {
        $this->loadData();
        
        if (!isset($this->data['hourlyHistory'][$date])) {
            return false;
        }
        
        foreach ($hourlyActual as $hour => $occupied) {
            if (isset($this->data['hourlyHistory'][$date]['hours'][$hour])) {
                $this->data['hourlyHistory'][$date]['hours'][$hour]['actual'] = $occupied;
            }
        }
        
        $this->saveData();
        return true;
    }
    
    /**
     * Analyze hourly prediction accuracy and calculate correction factors
     * Returns insights about which hours are consistently over/under-predicted
     * 
     * @return array Analysis with per-hour errors and suggested corrections
     */
    public function analyzeHourlyAccuracy($date) {
        $this->loadData();
        
        if (!isset($this->data['hourlyHistory'][$date])) {
            return ['error' => 'No hourly data for this date'];
        }
        
        $entry = $this->data['hourlyHistory'][$date];
        
        if ($entry['analyzed']) {
            return ['error' => 'Already analyzed'];
        }
        
        $hourlyErrors = [];
        $totalPredicted = 0;
        $totalActual = 0;
        
        foreach ($entry['hours'] as $hour => $data) {
            $predicted = $data['predicted'];
            $actual = $data['actual'];
            
            if ($actual === null || $actual == 0) {
                continue;
            }
            
            $totalPredicted += $predicted;
            $totalActual += $actual;
            
            $error = ($predicted - $actual) / max($actual, 1);
            $hourlyErrors[$hour] = [
                'predicted' => $predicted,
                'actual' => $actual,
                'error' => round($error * 100, 1),
                'direction' => $error > 0 ? 'overpredicted' : 'underpredicted'
            ];
        }
        
        // Calculate overall hourly pattern bias
        $avgError = 0;
        if (count($hourlyErrors) > 0) {
            $avgError = array_sum(array_column($hourlyErrors, 'error')) / count($hourlyErrors);
        }
        
        // Update hourly bias multipliers
        $this->updateHourlyBias($hourlyErrors);
        
        // Mark as analyzed
        $this->data['hourlyHistory'][$date]['analyzed'] = true;
        $this->data['hourlyHistory'][$date]['result'] = [
            'avgError' => round($avgError, 1) . '%',
            'hourlyErrors' => $hourlyErrors,
            'totalPredicted' => $totalPredicted,
            'totalActual' => $totalActual
        ];
        
        $this->saveData();
        
        return $this->data['hourlyHistory'][$date]['result'];
    }
    
    /**
     * Update hourly bias multipliers based on observed errors
     * This creates per-hour correction factors for future predictions
     */
    private function updateHourlyBias($hourlyErrors) {
        if (!isset($this->data['hourlyBias'])) {
            $this->data['hourlyBias'] = [];
        }
        
        $learningRate = 0.15; // How much to adjust per observation
        
        foreach ($hourlyErrors as $hour => $data) {
            $error = $data['error'] / 100; // Convert to decimal
            
            if (abs($error) < 0.10) {
                continue; // Skip if error is within 10%
            }
            
            // Initialize hour if not exists
            if (!isset($this->data['hourlyBias'][$hour])) {
                $this->data['hourlyBias'][$hour] = [
                    'multiplier' => 1.0,
                    'samples' => 0
                ];
            }
            
            // Calculate adjustment
            // If overpredicted (error > 0), reduce multiplier
            // If underpredicted (error < 0), increase multiplier
            $adjustment = -$error * $learningRate;
            
            // Apply with dampening based on samples
            $samples = $this->data['hourlyBias'][$hour]['samples'];
            $dampening = 1.0 / (1 + $samples * 0.1); // Reduce adjustment as samples grow
            
            $currentMult = $this->data['hourlyBias'][$hour]['multiplier'];
            $newMult = $currentMult + ($adjustment * $dampening);
            
            // Clamp to reasonable range (0.7 to 1.3)
            $newMult = max(0.7, min(1.3, $newMult));
            
            $this->data['hourlyBias'][$hour]['multiplier'] = round($newMult, 3);
            $this->data['hourlyBias'][$hour]['samples']++;
        }
    }
    
    /**
     * Get hourly bias multiplier for a specific hour
     */
    public function getHourlyBias($hour) {
        if (!isset($this->data['hourlyBias'][$hour])) {
            return 1.0;
        }
        return $this->data['hourlyBias'][$hour]['multiplier'];
    }
    
    /**
     * Get all hourly biases for display
     */
    public function getAllHourlyBiases() {
        return $this->data['hourlyBias'] ?? [];
    }
}
