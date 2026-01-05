<?php
/**
 * External Data Provider for Helios Prediction Engine
 * 
 * Integrates multiple free external APIs:
 * - Open-Meteo (hourly weather)
 * - API-Sports (football matches)
 * - Ticketmaster (local concerts/events)
 * - Adaptive Learning System
 * 
 * @version 1.0
 */

class ExternalDataProvider {
    private $cacheDir;
    private $config;
    
    // Łódź coordinates
    private $lat = 51.7592;
    private $lon = 19.4560;
    
    // Cache durations (seconds)
    private $cacheDurations = [
        'weather' => 3600,      // 1 hour
        'sports' => 86400,      // 24 hours
        'concerts' => 86400,    // 24 hours
        'learning' => 0         // No cache (always fresh)
    ];
    
    public function __construct($cacheDir, $config = []) {
        $this->cacheDir = $cacheDir;
        $this->config = array_merge([
            'ticketmasterApiKey' => null,  // Optional
            'enableSports' => true,
            'enableConcerts' => true,
            'enableLearning' => true
        ], $config);
    }
    
    // =========================================
    // HOURLY WEATHER (Open-Meteo - FREE)
    // =========================================
    
    /**
     * Get hourly weather forecast with cinema impact multipliers
     */
    public function getHourlyWeather($date) {
        $cacheFile = $this->cacheDir . "/weather_hourly_{$date}.json";
        
        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheDurations['weather']) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        $url = "https://api.open-meteo.com/v1/forecast?" . http_build_query([
            'latitude' => $this->lat,
            'longitude' => $this->lon,
            'hourly' => 'temperature_2m,precipitation,weathercode,cloudcover',
            'timezone' => 'Europe/Warsaw',
            'start_date' => $date,
            'end_date' => $date
        ]);
        
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $response = @file_get_contents($url, false, $context);
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!isset($data['hourly'])) return null;
        
        $hourly = $data['hourly'];
        $result = ['hours' => [], 'summary' => []];
        
        // Parse each hour
        for ($i = 0; $i < 24; $i++) {
            $temp = $hourly['temperature_2m'][$i] ?? 15;
            $precip = $hourly['precipitation'][$i] ?? 0;
            $code = $hourly['weathercode'][$i] ?? 0;
            $clouds = $hourly['cloudcover'][$i] ?? 50;
            
            // Calculate cinema multiplier for this hour
            // Based on research: weather impact is MUCH stronger than previously assumed
            $mult = 1.0;
            
            // Rain = more cinema (research: +20% to +54%)
            if ($precip > 0.5) $mult += 0.20;  // Light rain +20%
            if ($precip > 2.0) $mult += 0.15;  // Moderate rain +35%
            if ($precip > 5.0) $mult += 0.10;  // Heavy rain +45%
            
            // Very cold = cinema attractive (+10-15%)
            if ($temp < 5) $mult += 0.10;
            if ($temp < 0) $mult += 0.05;
            
            // Hot = outdoor activities (research: -20% to -30%)
            if ($temp > 25 && $clouds < 40) $mult -= 0.15;  // Warm & sunny -15%
            if ($temp > 28) $mult -= 0.10;                   // Hot -25%
            if ($temp > 32) $mult -= 0.10;                   // Very hot -35%
            
            // Perfect outdoor weather = STRONG competition (research: -25%)
            // "Perfect weather syndrome" - clear skies, warm, no rain
            if ($clouds < 25 && $temp > 18 && $temp < 28 && $precip == 0) {
                $mult -= 0.20;  // Perfect day = -20% (was -10%)
            }
            
            // Determine weather condition string for learning system
            $condition = 'default';
            if ($precip > 5.0) $condition = 'rain_heavy';
            elseif ($precip > 2.0) $condition = 'rain_moderate';
            elseif ($precip > 0.5) $condition = 'rain_light';
            elseif ($temp < 0) $condition = 'very_cold';
            elseif ($temp < 5) $condition = 'cold';
            elseif ($temp > 32) $condition = 'very_hot';
            elseif ($temp > 28) $condition = 'hot';
            elseif ($temp > 25 && $clouds < 40) $condition = 'hot_sunny';
            elseif ($clouds < 25 && $temp > 18 && $temp < 28 && $precip == 0) $condition = 'perfect_outdoor';

            $result['hours'][$i] = [
                'temp' => $temp,
                'precipitation' => $precip,
                'weathercode' => $code,
                'cloudcover' => $clouds,
                'condition' => $condition, // NEW for learning system
                'multiplier' => round(max(0.5, min(1.5, $mult)), 2)  // Expanded bounds
            ];
        }
        
        // Calculate daily summary
        $temps = array_column($result['hours'], 'temp');
        $mults = array_column($result['hours'], 'multiplier');
        $result['summary'] = [
            'avgTemp' => round(array_sum($temps) / count($temps), 1),
            'avgMultiplier' => round(array_sum($mults) / count($mults), 2),
            'hasRain' => max(array_column($result['hours'], 'precipitation')) > 0.5
        ];
        
        @file_put_contents($cacheFile, json_encode($result));
        return $result;
    }
    
    /**
     * Get weather multiplier for specific hour
     */
    public function getWeatherMultiplierForHour($date, $hour) {
        $weather = $this->getHourlyWeather($date);
        if (!$weather || !isset($weather['hours'][$hour])) {
            return 1.0;
        }
        return $weather['hours'][$hour]['multiplier'];
    }
    
    // =========================================
    // SPORTS EVENTS (TheSportsDB - FREE)
    // =========================================
    
    /**
     * Get major sports events that might affect cinema attendance
     * Uses TheSportsDB free API (no key required)
     */
    public function getSportsEvents($date) {
        if (!$this->config['enableSports']) return [];
        
        $cacheFile = $this->cacheDir . "/sports_{$date}.json";
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheDurations['sports']) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        $events = [];
        
        // Polish Ekstraklasa
        $url = "https://www.thesportsdb.com/api/v1/json/3/eventsday.php?d={$date}&l=4328";
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['events']) && is_array($data['events'])) {
                foreach ($data['events'] as $event) {
                    $homeTeam = $event['strHomeTeam'] ?? '';
                    $awayTeam = $event['strAwayTeam'] ?? '';
                    
                    // Check if it's a local team (Łódź teams: ŁKS, Widzew)
                    $isLocal = stripos($homeTeam, 'Łódź') !== false ||
                               stripos($homeTeam, 'ŁKS') !== false ||
                               stripos($homeTeam, 'Widzew') !== false;
                    
                    // Check if it's Polish national team (research: 13.4M viewers!)
                    $isNationalTeam = stripos($homeTeam, 'Poland') !== false ||
                                      stripos($awayTeam, 'Poland') !== false ||
                                      stripos($homeTeam, 'Polska') !== false ||
                                      stripos($awayTeam, 'Polska') !== false;
                    
                    // Impact based on research:
                    // - National team: -40% (13.4M viewers = 1/3 of population!)
                    // - Local match: -20%
                    // - Other Ekstraklasa: -5%
                    $impact = -0.05;
                    if ($isNationalTeam) $impact = -0.40;
                    elseif ($isLocal) $impact = -0.20;
                    
                    $events[] = [
                        'type' => 'football',
                        'name' => $homeTeam . ' vs ' . $awayTeam,
                        'time' => $event['strTime'] ?? null,
                        'league' => $isNationalTeam ? 'Reprezentacja' : 'Ekstraklasa',
                        'isLocal' => $isLocal,
                        'isNationalTeam' => $isNationalTeam,
                        'impact' => $impact
                    ];
                }
            }
        }
        
        @file_put_contents($cacheFile, json_encode($events));
        return $events;
    }
    
    /**
     * Get sports impact multiplier for a specific hour
     */
    public function getSportsMultiplier($date, $hour) {
        $events = $this->getSportsEvents($date);
        if (empty($events)) return 1.0;
        
        $mult = 1.0;
        foreach ($events as $event) {
            if ($event['time']) {
                $eventHour = (int)substr($event['time'], 0, 2);
                // Impact during match duration (roughly 2 hours)
                if ($hour >= $eventHour && $hour <= $eventHour + 2) {
                    $mult += $event['impact'];
                }
            }
        }
        
        return max(0.6, $mult);
    }
    
    // =========================================
    // LOCAL CONCERTS/EVENTS
    // =========================================
    
    /**
     * Get local concerts and major events in Łódź
     * Uses public event listings (no API key needed)
     */
    public function getLocalEvents($date) {
        if (!$this->config['enableConcerts']) return [];
        
        $cacheFile = $this->cacheDir . "/events_{$date}.json";
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheDurations['concerts']) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        // Manual list of major Łódź venues that compete with cinema
        $majorVenues = [
            'Atlas Arena' => ['capacity' => 13000, 'impact' => -0.15],
            'Wytwórnia' => ['capacity' => 1500, 'impact' => -0.05],
            'Łódź Fabryczna' => ['capacity' => 2000, 'impact' => -0.05],
            'Manufaktura' => ['capacity' => 5000, 'impact' => -0.08],
        ];
        
        // For now, return empty - can be populated manually or via scraping
        // In production, you could scrape bilety.pl or ebilet.pl
        $events = [];
        
        @file_put_contents($cacheFile, json_encode($events));
        return $events;
    }
    
    // =========================================
    // ADAPTIVE LEARNING SYSTEM
    // =========================================
    
    /**
     * Self-calibrating prediction weights based on historical accuracy
     */
    public function getAdaptiveWeights($dayType) {
        if (!$this->config['enableLearning']) {
            return [
                'historical' => 0.35,
                'genre' => 0.25,
                'premiere' => 0.20,
                'external' => 0.20
            ];
        }
        
        $learningFile = $this->cacheDir . "/learning_weights.json";
        
        if (file_exists($learningFile)) {
            $data = json_decode(file_get_contents($learningFile), true);
            if (isset($data[$dayType])) {
                return $data[$dayType]['weights'];
            }
        }
        
        // Default weights
        return [
            'historical' => 0.35,
            'genre' => 0.25,
            'premiere' => 0.20,
            'external' => 0.20
        ];
    }
    
    /**
     * Update weights based on prediction accuracy
     * Called after actual data is known
     */
    public function updateLearning($dayType, $predictions, $actuals) {
        if (!$this->config['enableLearning']) return;
        
        $learningFile = $this->cacheDir . "/learning_weights.json";
        
        $data = [];
        if (file_exists($learningFile)) {
            $data = json_decode(file_get_contents($learningFile), true) ?: [];
        }
        
        if (!isset($data[$dayType])) {
            $data[$dayType] = [
                'weights' => [
                    'historical' => 0.35,
                    'genre' => 0.25,
                    'premiere' => 0.20,
                    'external' => 0.20
                ],
                'accuracy' => [],
                'samples' => 0
            ];
        }
        
        // Calculate prediction error
        $totalPredicted = array_sum($predictions);
        $totalActual = array_sum($actuals);
        
        if ($totalPredicted > 0) {
            $error = abs($totalActual - $totalPredicted) / $totalPredicted;
            $accuracy = max(0, 1 - $error);
            
            // Store accuracy history (keep last 30)
            $data[$dayType]['accuracy'][] = $accuracy;
            if (count($data[$dayType]['accuracy']) > 30) {
                array_shift($data[$dayType]['accuracy']);
            }
            
            $data[$dayType]['samples']++;
            
            // Adjust weights based on accuracy trend
            $avgAccuracy = array_sum($data[$dayType]['accuracy']) / count($data[$dayType]['accuracy']);
            
            // If accuracy is low, increase historical weight (more conservative)
            if ($avgAccuracy < 0.7 && $data[$dayType]['samples'] > 5) {
                $data[$dayType]['weights']['historical'] = min(0.5, $data[$dayType]['weights']['historical'] + 0.02);
                $data[$dayType]['weights']['external'] = max(0.1, $data[$dayType]['weights']['external'] - 0.02);
            }
            // If accuracy is high, can trust more experimental factors
            elseif ($avgAccuracy > 0.85 && $data[$dayType]['samples'] > 10) {
                $data[$dayType]['weights']['historical'] = max(0.25, $data[$dayType]['weights']['historical'] - 0.01);
                $data[$dayType]['weights']['genre'] = min(0.35, $data[$dayType]['weights']['genre'] + 0.01);
            }
            
            // Normalize weights to sum to 1
            $sum = array_sum($data[$dayType]['weights']);
            foreach ($data[$dayType]['weights'] as $key => $val) {
                $data[$dayType]['weights'][$key] = round($val / $sum, 3);
            }
        }
        
        @file_put_contents($learningFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get learning statistics
     */
    public function getLearningStats() {
        $learningFile = $this->cacheDir . "/learning_weights.json";
        
        if (!file_exists($learningFile)) {
            return ['status' => 'not_started', 'message' => 'Brak danych do nauki'];
        }
        
        $data = json_decode(file_get_contents($learningFile), true);
        
        $stats = [];
        foreach ($data as $dayType => $info) {
            $avgAccuracy = count($info['accuracy']) > 0 
                ? round(array_sum($info['accuracy']) / count($info['accuracy']) * 100, 1)
                : 0;
            
            $stats[$dayType] = [
                'samples' => $info['samples'],
                'avgAccuracy' => $avgAccuracy . '%',
                'weights' => $info['weights']
            ];
        }
        
        return [
            'status' => 'active',
            'dayTypes' => $stats
        ];
    }
    
    // =========================================
    // COMBINED EXTERNAL FACTORS
    // =========================================
    
    /**
     * Get all external factors combined for a specific date/hour
     */
    public function getAllFactors($date, $hour = null) {
        $factors = [
            'weather' => null,
            'sports' => [],
            'events' => [],
            'combinedMultiplier' => 1.0
        ];
        
        // Weather (hourly or daily)
        if ($hour !== null) {
            $factors['weather'] = [
                'hourly' => $this->getWeatherMultiplierForHour($date, $hour)
            ];
            $factors['combinedMultiplier'] *= $factors['weather']['hourly'];
        } else {
            $weather = $this->getHourlyWeather($date);
            if ($weather) {
                $factors['weather'] = $weather['summary'];
                $factors['combinedMultiplier'] *= $weather['summary']['avgMultiplier'];
            }
        }
        
        // Sports
        $factors['sports'] = $this->getSportsEvents($date);
        if ($hour !== null) {
            $factors['combinedMultiplier'] *= $this->getSportsMultiplier($date, $hour);
        }
        
        // Local events
        $factors['events'] = $this->getLocalEvents($date);
        
        $factors['combinedMultiplier'] = round($factors['combinedMultiplier'], 2);
        
        return $factors;
    }
}
