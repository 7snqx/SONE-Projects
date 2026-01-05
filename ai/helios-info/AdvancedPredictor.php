<?php
/**
 * Helios Advanced Prediction Engine v2.0
 * 
 * Multi-factor ensemble model combining:
 * - Historical patterns (day type, hour)
 * - Genre-specific behavior
 * - Premiere decay curves
 * - Polish holiday calendar
 * - Weather integration
 * - Real-time Bayesian updates
 * 
 * @author AI Assistant
 * @version 2.0
 */

class AdvancedPredictor {
    private $historyDir;
    private $cacheDir;
    private $externalProvider; // NEW: Defined property
    private $factorLearning; // NEW: Factor Learning System
    
    // Multi-dimensional models
    private $dayModels = ['weekend' => [], 'workday' => [], 'tuesday' => []];
    private $genreModels = [];
    private $movieModels = []; // Track specific movie performance
    private $hourlyPatterns = [];
    private $genrePatterns = []; // Expert knowledge base
    private $schoolHolidays2025 = [];
    private $specialPeriods = [];
    private $tradingSundays2025 = []; // NEW
    private $stats = ['daysLoaded' => 0];
    
    // Configuration
    private $config = [
        'halfLife' => 14,           // Days for exponential decay
        'minDataPoints' => 3,       // Minimum for reliable prediction
        'ensembleWeights' => [
            'historical' => 0.35,
            'genre' => 0.25,
            'premiere' => 0.20,
            'external' => 0.20
        ],
        'weatherEnabled' => true    // Open-Meteo is free, always enabled!
    ];

    // ... (rest of properties) ...

    public function __construct($historyDir, $config = []) {
        $this->historyDir = $historyDir;
        $this->cacheDir = dirname($historyDir) . '/cache';
        $this->config = array_merge($this->config, $config);
        
        // Initialize external data provider
        require_once __DIR__ . '/ExternalDataProvider.php';
        $this->externalProvider = new ExternalDataProvider($this->cacheDir, [
            'enableSports' => true,
            'enableConcerts' => true,
            'enableLearning' => true
        ]);
        
        // NEW: Initialize Factor Learning System
        require_once __DIR__ . '/FactorLearningSystem.php';
        $this->factorLearning = new FactorLearningSystem($this->cacheDir);
        
        $this->initGenrePatterns();
        $this->initStaticData();
        $this->loadHistoricalData();
    }
    
    private function initGenrePatterns() {
        $this->genrePatterns = [
            'default' => [
                'dayMultiplier' => ['workday' => 0.5, 'weekend' => 1.0, 'tuesday' => 1.3],
                'hourMultiplier' => []
            ],
            'animowany' => [
                 'dayMultiplier' => ['workday' => 0.3, 'weekend' => 1.4, 'tuesday' => 0.6],
            ],
            'horror' => [
                 'dayMultiplier' => ['workday' => 0.4, 'friday' => 1.2, 'weekend' => 0.9],
            ]
        ];
    }
    
    private function initStaticData() {
        $this->schoolHolidays2025 = [
            ['start' => '2025-01-20', 'end' => '2025-03-02', 'name' => 'Ferie zimowe'],
            ['start' => '2025-06-27', 'end' => '2025-08-31', 'name' => 'Wakacje']
        ];
        $this->specialPeriods = [
            ['start' => '2025-02-14', 'end' => '2025-02-14', 'factor' => 1.5], // Walentynki
        ];
        
        $this->tradingSundays2025 = [
            '2025-01-26', '2025-04-13', '2025-04-27', '2025-06-29', 
            '2025-08-31', '2025-12-14', '2025-12-21'
        ];
    }
    
    /**
     * Load and index all historical data
     */
    private function loadHistoricalData() {
        $files = glob($this->historyDir . '/*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data || !isset($data['dayType'])) continue;
            
            $dayType = $data['dayType'];
            $date = $data['date'];
            $daysAgo = (strtotime(date('Y-m-d')) - strtotime($date)) / 86400;
            
            // Skip data older than 90 days (noise)
            if ($daysAgo > 90) continue;
            
            // Exponential decay weight
            $weight = exp(-$daysAgo / $this->config['halfLife']);
            
            $entry = [
                'date' => $date,
                'daysAgo' => $daysAgo,
                'weight' => $weight,
                'hourly' => $data['hourly'] ?? [],
                'totals' => $data['totals'] ?? [],
                'movies' => $data['movies'] ?? []
            ];

            // Index by day type
            $this->dayModels[$dayType][] = $entry;
            
            // Index by genre (if movie data available)
            if (isset($data['movies'])) {
                foreach ($data['movies'] as $movie) {
                    
                    // 1. Genre Indexing
                    $genres = $movie['genres'] ?? [];
                    foreach ($genres as $genre) {
                        $genreKey = $this->normalizeGenre($genre);
                        if (!isset($this->genreModels[$genreKey])) {
                            $this->genreModels[$genreKey] = [];
                        }
                        $this->genreModels[$genreKey][] = [
                            'date' => $date,
                            'dayType' => $dayType,
                            'weight' => $weight,
                            'screenings' => $movie['screenings'] ?? []
                        ];
                    }
                    
                    // 2. Specific Movie Indexing
                    if (!empty($movie['title'])) {
                        $titleKey = $this->normalizeTitle($movie['title']);
                        if (!isset($this->movieModels[$titleKey])) {
                            $this->movieModels[$titleKey] = [];
                        }
                        $this->movieModels[$titleKey][] = [
                            'date' => $date,
                            'dayType' => $dayType, // Keep track of context
                            'weight' => $weight,
                            'totalOccupied' => $movie['totalOccupied'] ?? 0,
                            'totalSeats' => $movie['totalSeats'] ?? 0,
                            'screenings' => $movie['screenings'] ?? []
                        ];
                    }
                }
            }
            
            $this->stats['daysLoaded']++;
        }
        
        $this->stats['lastUpdate'] = date('c');
    }

    private function normalizeTitle($title) {
        return mb_strtolower(trim($title));
    }

    private function normalizeGenre($genre) {
        $genre = mb_strtolower(trim($genre));
        $mappings = [
            'komedia' => 'komedia',
            'comedy' => 'komedia',
            'horror' => 'horror',
            'animowany' => 'animowany',
            'animacja' => 'animowany',
            'animation' => 'animowany',
            'romans' => 'romans',
            'romantyczny' => 'romans',
            'romance' => 'romans',
            'akcja' => 'akcja',
            'action' => 'akcja',
            'dramat' => 'dramat',
            'drama' => 'dramat',
            'sci-fi' => 'scifi',
            'science fiction' => 'scifi',
            'thriller' => 'thriller',
            'familijny' => 'animowany',
            'przygodowy' => 'przygoda',
            'adventure' => 'przygoda',
        ];
        return $mappings[$genre] ?? $genre;
    }
    
    /**
     * Get day type classification
     */
    public function getDayType($date) {
        $dayOfWeek = (int)date('w', strtotime($date));
        if ($dayOfWeek == 2) return 'tuesday';  // Tani wtorek!
        if ($dayOfWeek == 0 || $dayOfWeek == 5 || $dayOfWeek == 6) return 'weekend';
        return 'workday';
    }
    
    /**
     * Check if date is a Polish holiday
     */
    public function getHolidayInfo($date) {
        $md = date('m-d', strtotime($date));
        return $this->polishHolidays[$md] ?? null;
    }
    
    /**
     * Check if date is during school holidays
     */
    public function isSchoolHoliday($date) {
        $ts = strtotime($date);
        foreach ($this->schoolHolidays2025 as $period) {
            if ($ts >= strtotime($period['start']) && $ts <= strtotime($period['end'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if date is a Sunday with Trading Ban
     * (Most Sundays in Poland have trading ban)
     */
    public function isTradingBan($date) {
        // First check if it's Sunday
        if (date('w', strtotime($date)) != 0) {
            return false;
        }
        
        // Format date
        $ymd = date('Y-m-d', strtotime($date));
        
        // Check if it's in the allowed list (Trading Sunday)
        if (in_array($ymd, $this->tradingSundays2025)) {
            return false; // It IS a trading sunday, so NO ban.
        }
        
        // If not in allowed list, it IS a ban.
        return true;
    }
    
    /**
     * Get holiday multiplier for a date
     * Uses FactorLearningSystem for adaptive multipliers
     */
    public function getHolidayMultiplier($date) {
        return $this->getHolidayMultiplierWithKey($date)['value'];
    }

    // Capture key for learning
    private function getHolidayMultiplierWithKey($date) {
        $ts = strtotime($date);
        $md = date('m-d', $ts);
        
        // LEARNED: Use FactorLearningSystem
        if ($md == '12-24') return ['key' => 'christmas_eve', 'value' => $this->factorLearning->getMultiplier('holiday', 'christmas_eve')];
        if ($md == '12-25') return ['key' => 'christmas', 'value' => $this->factorLearning->getMultiplier('holiday', 'christmas')];
        if ($md == '12-26') return ['key' => 'post_christmas', 'value' => $this->factorLearning->getMultiplier('holiday', 'post_christmas')];
        if ($md == '12-31') return ['key' => 'new_years_eve', 'value' => $this->factorLearning->getMultiplier('holiday', 'new_years_eve')];
        if ($md == '01-01') return ['key' => 'new_year', 'value' => $this->factorLearning->getMultiplier('holiday', 'new_year')];
        if ($md == '02-14') return ['key' => 'valentines', 'value' => $this->factorLearning->getMultiplier('holiday', 'valentines')];
        if ($md == '06-01') return ['key' => 'childrens_day', 'value' => $this->factorLearning->getMultiplier('holiday', 'childrens_day')];
        
        // Post-Christmas period (27-30 Dec)
        if (date('m', $ts) == 12 && date('d', $ts) >= 27 && date('d', $ts) <= 30) {
             return ['key' => 'post_christmas', 'value' => $this->factorLearning->getMultiplier('holiday', 'post_christmas')];
        }
        
        // Mikołajki
        if ($md == '12-06') return ['key' => 'mikolajki', 'value' => 1.25];
        
        return ['key' => 'default', 'value' => 1.0];
    }
    
    /**
     * Get any special period for a date (exam sessions, pre-holiday, etc.)
     */
    public function getSpecialPeriod($date) {
        $ts = strtotime($date);
        foreach ($this->specialPeriods as $period) {
            if ($ts >= strtotime($period['start']) && $ts <= strtotime($period['end'])) {
                return [
                    'name' => $period['name'] ?? 'Okres specjalny',
                    'multiplier' => $period['factor'] ?? 1.0,
                    'type' => $period['type'] ?? 'positive'
                ];
            }
        }
        return null;
    }
    
    /**
     * Get season multiplier
     * LEARNED: Use FactorLearningSystem
     */
    public function getSeasonMultiplier($date) {

        return $this->getSeasonMultiplierWithKey($date)['value'];
    }

    private function getSeasonMultiplierWithKey($date) {
        $month = (int)date('m', strtotime($date));
        
        if ($month >= 6 && $month <= 8) return ['key' => 'summer', 'value' => $this->factorLearning->getMultiplier('season', 'summer')];
        if ($month == 12 || $month <= 2) return ['key' => 'winter', 'value' => $this->factorLearning->getMultiplier('season', 'winter')];
        if ($month >= 3 && $month <= 5) return ['key' => 'spring', 'value' => $this->factorLearning->getMultiplier('season', 'spring')];
        
        return ['key' => 'autumn', 'value' => $this->factorLearning->getMultiplier('season', 'autumn')];
    }
    
    /**
     * Get payday multiplier
     * People have more money around payday (10th and 25th of month in Poland)
     */
    public function getPaydayMultiplier($date) {
        $day = (int)date('d', strtotime($date));
        
        // Payday window (10-15 and 25-30)
        if (($day >= 10 && $day <= 15) || ($day >= 25 && $day <= 30)) {
            return $this->factorLearning->getMultiplier('payday', 'after_payday');
        }
        
        // End of month before payday (1-9) - less money
        if ($day >= 1 && $day <= 9) {
            return $this->factorLearning->getMultiplier('payday', 'before_payday');
        }
        
        return $this->factorLearning->getMultiplier('payday', 'regular');
    }
    
    /**
     * Get genre-based weather sensitivity modifier
     * Research: "Horrory wykazują zadziwiającą odporność na pogodę"
     * "Filmy familijne są pierwszymi ofiarami dobrej pogody"
     * 
     * Returns multiplier to apply to weather effect
     */
    public function getGenreWeatherSensitivity($genre) {
        $genre = strtolower($genre ?? '');
        
        // Horror - very resistant to weather (research: Martwe Zło did well in sunny April)
        // Young adults treat it as social event in evening hours
        if (strpos($genre, 'horror') !== false) {
            return 0.50; // Only 50% of weather effect applies
        }
        
        // Family/Animation - very sensitive (first victims of nice weather)
        if (strpos($genre, 'familijny') !== false || 
            strpos($genre, 'animacja') !== false ||
            strpos($genre, 'animowany') !== false ||
            strpos($genre, 'dla dzieci') !== false) {
            return 1.50; // 150% of weather effect (more sensitive)
        }
        
        // Long films/dramas - sensitive (3-hour investment hard in sunny day)
        if (strpos($genre, 'dramat') !== false) {
            return 1.25; // 125% of weather effect
        }
        
        // Action/Adventure - moderate sensitivity
        if (strpos($genre, 'akcja') !== false || strpos($genre, 'przygodowy') !== false) {
            return 1.0; // Standard sensitivity
        }
        
        // Comedy - popular date night choice, less weather dependent
        if (strpos($genre, 'komedia') !== false) {
            return 0.80; // 80% of weather effect
        }
        
        // Default
        return 1.0;
    }
    
    /**
     * Get runtime-based weather sensitivity modifier
     * Research: "Filmy o długim metrażu (np. 3 godziny) są pierwszymi ofiarami dobrej pogody,
     * gdyż koszt alternatywny spędzenia 3 godzin w ciemnej sali w słoneczny dzień jest zbyt wysoki"
     * 
     * @param int $durationMinutes Movie duration in minutes
     * @return float Multiplier for weather sensitivity (1.0 = standard)
     */
    public function getRuntimeWeatherSensitivity($durationMinutes) {
        if (!$durationMinutes || $durationMinutes <= 0) return 1.0;
        
        // Short films (under 90 min) - low opportunity cost
        if ($durationMinutes < 90) {
            return 0.80; // 80% of weather effect
        }
        
        // Standard length (90-120 min) - normal sensitivity
        if ($durationMinutes <= 120) {
            return 1.0;
        }
        
        // Long films (120-150 min) - higher opportunity cost
        if ($durationMinutes <= 150) {
            return 1.20; // 120% of weather effect
        }
        
        // Very long films (150+ min, e.g. Oppenheimer 3h) - highest opportunity cost
        // "koszt alternatywny spędzenia 3 godzin w ciemnej sali w słoneczny dzień jest zbyt wysoki"
        return 1.40; // 140% of weather effect
    }
    
    /**
     * Get combined weather sensitivity for a specific movie
     * Combines genre sensitivity, runtime sensitivity, and isForChildren flag
     * 
     * @param array $movie Movie data with genre, duration, isForChildren
     * @return float Combined sensitivity multiplier
     */
    public function getCombinedWeatherSensitivity($movie) {
        if (!$movie) return 1.0;
        
        $genre = $movie['genre'] ?? $movie['genres'][0] ?? '';
        $duration = $movie['duration'] ?? 0;
        $isForChildren = $movie['isForChildren'] ?? false;
        
        // Base sensitivity from genre
        $genreSensitivity = $this->getGenreWeatherSensitivity($genre);
        
        // Modify by runtime
        $runtimeSensitivity = $this->getRuntimeWeatherSensitivity($duration);
        
        // Children's films are extra sensitive to nice weather (parents take kids outside)
        $childrenMultiplier = $isForChildren ? 1.30 : 1.0;
        
        // Combine all factors (multiplicative)
        $combined = $genreSensitivity * $runtimeSensitivity * $childrenMultiplier;
        
        // Cap at reasonable bounds
        return max(0.40, min(2.0, $combined));
    }
    
    /**
     * Get format-based audience modifier
     * IMAX/Dolby shows attract dedicated audience less affected by weather
     * 
     * @param string $format Screening format (2D, 3D, IMAX, Dolby)
     * @return float Weather sensitivity modifier
     */
    public function getFormatWeatherSensitivity($format) {
        $format = strtoupper($format ?? '');
        
        // Premium formats = dedicated audience, less weather-sensitive
        if (strpos($format, 'IMAX') !== false) return 0.70;
        if (strpos($format, 'DOLBY') !== false) return 0.75;
        if (strpos($format, '4DX') !== false) return 0.65; // Experience hunters
        
        // 3D slightly less sensitive (special experience)
        if (strpos($format, '3D') !== false) return 0.90;
        
        // Standard 2D
        return 1.0;
    }
    
    /**
     * Check if date is a long weekend (Friday before or Monday after a holiday)
     */
    public function isLongWeekend($date) {
        $dayOfWeek = (int)date('w', strtotime($date));
        
        // Check if Friday before a Monday holiday
        if ($dayOfWeek == 5) { // Friday
            $monday = date('Y-m-d', strtotime($date . ' +3 days'));
            if ($this->getHolidayInfo($monday)) {
                return true;
            }
        }
        
        // Check if Monday after a Friday holiday  
        if ($dayOfWeek == 1) { // Monday
            $friday = date('Y-m-d', strtotime($date . ' -3 days'));
            if ($this->getHolidayInfo($friday)) {
                return true;
            }
        }
        
        // Check if Thursday before Friday holiday (4-day weekend)
        if ($dayOfWeek == 4) { // Thursday
            $friday = date('Y-m-d', strtotime($date . ' +1 day'));
            if ($this->getHolidayInfo($friday)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get long weekend multiplier
     */
    public function getLongWeekendMultiplier($date) {
        return $this->isLongWeekend($date) ? 1.15 : 1.0; // +15% for long weekends
    }
    
    /**
     * Detect "First Warm Spring Weekend" phenomenon
     * Research: First sunny warm weekend after winter causes -25% to -70% drop
     * "After months of light and warmth deficit, the need for sun exposure becomes a behavioral priority"
     */
    public function isFirstWarmSpringWeekend($date) {
        $month = (int)date('m', strtotime($date));
        $dayOfWeek = (int)date('w', strtotime($date));
        
        // Only March-April, weekends (Sat=6, Sun=0)
        if ($month < 3 || $month > 4) return false;
        if ($dayOfWeek != 0 && $dayOfWeek != 6) return false;
        
        // Check if we have weather data
        if (!$this->externalProvider) return false;
        
        $weather = $this->externalProvider->getHourlyWeather($date);
        if (!$weather) return false;
        
        // Check conditions: warm (>18°C) and sunny (clouds < 30%)
        $avgTemp = $weather['summary']['avgTemp'] ?? 10;
        $isWarm = $avgTemp > 18;
        
        // Check if previous week was cold
        $prevWeek = date('Y-m-d', strtotime($date . ' -7 days'));
        $prevWeather = $this->externalProvider->getHourlyWeather($prevWeek);
        $prevTemp = $prevWeather['summary']['avgTemp'] ?? 15;
        $wasCold = $prevTemp < 12;
        
        // First warm weekend = current warm + previous cold
        return $isWarm && $wasCold;
    }
    
    /**
     * Get first warm spring weekend multiplier
     * Research shows -25% to -70% impact, we use -40% as conservative middle
     */
    public function getFirstWarmWeekendMultiplier($date) {
        return $this->isFirstWarmSpringWeekend($date) ? 0.60 : 1.0; // -40% for first warm weekend
    }
    
    /**
     * Get premiere decay multiplier
     * Research: Decay varies by movie type:
     * - Fandom/horror: -70% (front-loading)
     * - Quality/drama: -10-25% (long legs)
     * - Standard: -50%
     */
    public function getPremiereMultiplier($premiereDate, $screeningDate, $movie = null) {
        if (!$premiereDate) return 1.0;
        
        $premierTs = strtotime($premiereDate);
        $screenTs = strtotime($screeningDate);
        
        if ($premierTs > $screenTs) {
            // Pre-premiere (preview) - high anticipation
            return 1.5;
        }
        
        $daysSincePremiere = ($screenTs - $premierTs) / 86400;
        
        // Opening weekend bonus (reduced from 1.8 to be more conservative)
        if ($daysSincePremiere <= 3) {
            return 1.4;
        }
        
        // First week (reduced from 1.4)
        if ($daysSincePremiere <= 7) {
            return 1.2;
        }
        
        // Get dynamic decay rate based on movie type
        $decayRate = $this->getDecayRateForMovie($movie);
        
        // Exponential decay with variable half-life
        $halfLife = 14; // 2 weeks base
        $decay = pow($decayRate, $daysSincePremiere / $halfLife);
        
        // Floor at 15% of opening performance
        return max(0.15, $decay);
    }
    
    /**
     * Get decay rate for specific movie type
     * Research: FNAF 2 = -70%, Oppenheimer = -11%, Standard = -50%
     * Enhanced with: imdbRating, country (Polish bonus), famous directors
     */
    private function getDecayRateForMovie($movie) {
        if (!$movie) return 0.50; // Standard -50%
        
        $genre = strtolower($movie['genre'] ?? $movie['genres'][0] ?? '');
        $title = strtolower($movie['title'] ?? '');
        $imdbRating = floatval($movie['imdbRating'] ?? 0);
        $country = strtolower($movie['country'] ?? '');
        $director = strtolower($movie['director'] ?? '');
        
        // ============================================
        // QUALITY INDICATORS (Slow decay = long legs)
        // ============================================
        
        // High IMDB rating = quality film = slower decay
        // Research: Oppenheimer (8.3 IMDB) had -11% decay
        if ($imdbRating >= 8.0) {
            return 0.85; // -15% per 2 weeks (exceptional quality)
        }
        if ($imdbRating >= 7.5) {
            return 0.75; // -25% per 2 weeks (high quality)
        }
        
        // Famous directors known for "event" films (slow decay)
        $famousDirectors = [
            'nolan', 'spielberg', 'tarantino', 'scorsese', 'villeneuve', 
            'fincher', 'kubrick', 'coppola', 'anderson', 'cameron',
            // Polish directors
            'wajda', 'polański', 'holland', 'smarzowski', 'komasa', 'szumowska'
        ];
        foreach ($famousDirectors as $famDir) {
            if (strpos($director, $famDir) !== false) {
                return 0.70; // -30% per 2 weeks (director event)
            }
        }
        
        // Polish production bonus (local audience support)
        // Research: Chłopi was Polish cultural event
        if (strpos($country, 'polska') !== false || strpos($country, 'poland') !== false) {
            // Polish comedy tradition (Kogel-Mogel, Teściowie)
            if (strpos($genre, 'komedia') !== false) {
                return 0.60; // -40% per 2 weeks (still better than fandom)
            }
            // Polish drama/historical
            return 0.70; // -30% per 2 weeks
        }
        
        // Ukrainian production bonus (solidarity with Ukraine, large Ukrainian diaspora in Poland)
        // Since 2022 there's strong cultural connection and support
        if (strpos($country, 'ukrain') !== false || strpos($country, 'UA') !== false) {
            return 0.65; // -35% per 2 weeks (solidarity effect)
        }
        
        // Other Central European productions (Czech, Slovak, Baltic) - regional interest
        $regionalCountries = ['czech', 'słowac', 'litw', 'łotw', 'eston', 'węgier'];
        foreach ($regionalCountries as $reg) {
            if (strpos($country, $reg) !== false) {
                return 0.55; // -45% per 2 weeks (some regional interest)
            }
        }
        
        // ============================================
        // FRONT-LOADING (Fast decay = fandom movies)
        // ============================================
        
        // Fandom movies (fast decay = 0.30 means -70%)
        $fandomKeywords = ['horror', 'sci-fi', 'fantasy', 'marvel', 'dc', 'star wars', 'anime', 'superhero'];
        foreach ($fandomKeywords as $keyword) {
            if (strpos($genre, $keyword) !== false || strpos($title, $keyword) !== false) {
                return 0.30; // -70% per 2 weeks
            }
        }
        
        // ============================================
        // SPECIAL CASES
        // ============================================
        
        // Polish adaptations of school curriculum ("Chłopi" effect)
        // Extended list of Polish mandatory reading + historical films
        $schoolCurriculumTitles = [
            'chłopi', 'lalka', 'pan tadeusz', 'potop', 'ogniem i mieczem', 
            'quo vadis', 'krzyżacy', 'wesele', 'dziady', 'faraon',
            'nad niemnem', 'przedwiośnie', 'ferdydurke', 'granica'
        ];
        foreach ($schoolCurriculumTitles as $book) {
            if (strpos($title, $book) !== false) {
                return 0.85; // -15% per 2 weeks (school trips!)
            }
        }
        
        // Long legs quality movies (slow decay = 0.75 means -25%)
        $qualityKeywords = ['dramat', 'biograficzny', 'historyczny', 'oscarowy', 'nagrodzony', 'kryminał'];
        foreach ($qualityKeywords as $keyword) {
            if (strpos($genre, $keyword) !== false) {
                return 0.70; // -30% per 2 weeks
            }
        }
        
        // Animation - depends on quality but generally longer legs
        if (strpos($genre, 'animacja') !== false || strpos($genre, 'animowany') !== false) {
            return 0.65; // -35% per 2 weeks
        }
        
        // Standard decay
        return 0.50; // -50% per 2 weeks
    }
    
    /**
     * Get weather multiplier (if API enabled)
     */
    /**
     * Get weather multiplier based on forecast and hour
     * LEARNED: Uses FactorLearningSystem
     */
    public function getWeatherMultiplier($date, $hour = 15) {
        if (!$this->config['weatherEnabled']) return 1.0;
        
        // Get hourly weather from external provider
        $weather = $this->externalProvider->getHourlyWeather($date);
        
        // Check if we have weather data for this hour
        if (isset($weather['hours']) && isset($weather['hours'][$hour])) {
            $condition = $weather['hours'][$hour]['condition'] ?? 'default';
            // Use learned multiplier for this condition
            return $this->getLearnedWeatherMultiplier($condition);
        }
        
        // Fallback to average/default if no specific hour data
        return 1.0;
    }
    
    // NEW: Helper to get learned multiplier from weather code/condition
    public function getLearnedWeatherMultiplier($condition) {
        // Map condition to learning key - using the unified condition string from ExternalDataProvider
        return $this->factorLearning->getMultiplier('weather', $condition);
    }
    
    /**
     * Fetch weather from Open-Meteo API (FREE, no API key needed!)
     * https://open-meteo.com/
     */
    private function fetchWeather($date) {
        // Łódź coordinates
        $lat = 51.7592;
        $lon = 19.4560;
        
        // Open-Meteo is completely free, no API key required
        $url = "https://api.open-meteo.com/v1/forecast?" . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,weathercode',
            'timezone' => 'Europe/Warsaw',
            'start_date' => $date,
            'end_date' => $date
        ]);
        
        $context = stream_context_create([
            'http' => ['timeout' => 5]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if (!$response) return null;
        
        $data = json_decode($response, true);
        if (!isset($data['daily'])) return null;
        
        $daily = $data['daily'];
        $tempMax = $daily['temperature_2m_max'][0] ?? 15;
        $tempMin = $daily['temperature_2m_min'][0] ?? 10;
        $avgTemp = ($tempMax + $tempMin) / 2;
        $precipitation = $daily['precipitation_sum'][0] ?? 0;
        $weatherCode = $daily['weathercode'][0] ?? 0;
        
        // Weather code interpretation
        // 0: Clear, 1-3: Partly cloudy, 45-48: Fog
        // 51-67: Drizzle/Rain, 71-77: Snow, 80-82: Showers, 95-99: Thunderstorm
        $isRainy = $weatherCode >= 51 || $precipitation > 1;
        $isCloudy = $weatherCode >= 3;
        
        return [
            'temp' => $avgTemp,
            'tempMax' => $tempMax,
            'tempMin' => $tempMin,
            'clouds' => $isCloudy ? 70 : 20,
            'rain' => $isRainy ? 1.0 : 0,
            'precipitation' => $precipitation,
            'weatherCode' => $weatherCode,
            'description' => $this->getWeatherDescription($weatherCode)
        ];
    }
    
    /**
     * Convert weather code to description
     */
    private function getWeatherDescription($code) {
        $descriptions = [
            0 => 'Bezchmurnie',
            1 => 'Przeważnie bezchmurnie',
            2 => 'Częściowe zachmurzenie',
            3 => 'Pochmurno',
            45 => 'Mgła',
            48 => 'Szron',
            51 => 'Lekka mżawka',
            53 => 'Mżawka',
            55 => 'Intensywna mżawka',
            61 => 'Lekki deszcz',
            63 => 'Deszcz',
            65 => 'Intensywny deszcz',
            71 => 'Lekki śnieg',
            73 => 'Śnieg',
            75 => 'Intensywny śnieg',
            80 => 'Przelotne opady',
            81 => 'Umiarkowane przelotne opady',
            82 => 'Silne przelotne opady',
            95 => 'Burza',
            96 => 'Burza z gradem',
            99 => 'Silna burza z gradem'
        ];
        return $descriptions[$code] ?? 'Brak danych';
    }
    
    /**
     * Get genre-specific multiplier
     */
    public function getGenreMultiplier($genres, $hour, $dayType, $date) {
        if (empty($genres)) {
            $pattern = $this->genrePatterns['default'];
        } else {
            // Use first matching genre pattern
            $genreKey = null;
            foreach ($genres as $g) {
                $normalized = $this->normalizeGenre($g);
                if (isset($this->genrePatterns[$normalized])) {
                    $genreKey = $normalized;
                    break;
                }
            }
            $pattern = $this->genrePatterns[$genreKey] ?? $this->genrePatterns['default'];
        }
        
        $multiplier = 1.0;
        
        // Hour-based pattern
        $hourMult = $pattern['hourMultiplier'][$hour] ?? 1.0;
        $multiplier *= $hourMult;
        
        // Day-based pattern
        $dayMult = $pattern['dayMultiplier'][$dayType] ?? 1.0;
        $multiplier *= $dayMult;
        
        // Special day bonuses
        $md = date('m-d', strtotime($date));
        
        if (isset($pattern['halloweenBonus']) && $md === '10-31') {
            $multiplier *= $pattern['halloweenBonus'];
        }
        
        if (isset($pattern['valentinesBonus']) && $md === '02-14') {
            $multiplier *= $pattern['valentinesBonus'];
        }
        
        if (isset($pattern['childrensDay']) && $md === '06-01') {
            $multiplier *= $pattern['childrensDay'];
        }
        
        return $multiplier;
    }
    
    /**
     * MAIN PREDICTION: Ensemble model combining all factors
     */
    public function predict($targetDate, $currentData = null, $movieContext = null) {
        $dayType = $this->getDayType($targetDate);
        $modelData = $this->dayModels[$dayType] ?? [];
        
        // SMART FALLBACK: prefer similar day types to avoid mixing weekends with weekdays
        if (count($modelData) < 1) {
            // Define fallback order: similar types first
            $fallbackOrder = [
                'tuesday' => ['workday', 'mixed'],  // Tuesday → try workday first
                'workday' => ['tuesday', 'mixed'],  // Workday → try tuesday first
                'weekend' => ['mixed'],              // Weekend → use all data
            ];
            
            $fallbacks = $fallbackOrder[$dayType] ?? ['mixed'];
            
            foreach ($fallbacks as $fallbackType) {
                if ($fallbackType === 'mixed') {
                    // Build mixed from all available data
                    $allData = [];
                    foreach ($this->dayModels as $type => $days) {
                        foreach ($days as $day) {
                            $allData[] = $day;
                        }
                    }
                    if (count($allData) > 0) {
                        $modelData = $allData;
                        $dayType = 'mixed';
                        break;
                    }
                } else {
                    // Try specific fallback type
                    if (!empty($this->dayModels[$fallbackType])) {
                        $modelData = $this->dayModels[$fallbackType];
                        $dayType = $fallbackType;
                        break;
                    }
                }
            }
        }
        
        if (count($modelData) < 1) {
            return [
                'error' => 'Brak danych historycznych',
                'daysNeeded' => 1
            ];
        }
        
        // === LAYER 1: Historical baseline (per hour) ===
        $hourlyPrediction = $this->calculateHistoricalBaseline($modelData);
        
        // === LAYER 2: Get adaptive weights from learning system ===
        $weights = $this->externalProvider 
            ? $this->externalProvider->getAdaptiveWeights($dayType)
            : $this->config['ensembleWeights'];
        
        // === LAYER 3: Apply multipliers (now hourly!) ===
        $holidayMult = $this->getHolidayMultiplier($targetDate);
        $schoolMult = $this->isSchoolHoliday($targetDate) ? 1.2 : 1.0;
        
        // Get external factors (sports events etc.)
        $externalFactors = $this->externalProvider 
            ? $this->externalProvider->getAllFactors($targetDate)
            : null;
        
        // Apply to hourly predictions with HOURLY weather and sports
        foreach ($hourlyPrediction as $hour => &$hp) {
            // Get hourly weather multiplier from API
            $weatherApiMult = $this->externalProvider 
                ? $this->externalProvider->getWeatherMultiplierForHour($targetDate, $hour)
                : $this->getWeatherMultiplier($targetDate);
            
            // Apply learned weather correction (Factor Learning calibration)
            $weatherLearnedCorrection = $this->factorLearning->getMultiplier('weather', 'default');
            $weatherMult = $weatherApiMult * $weatherLearnedCorrection;
            
            // Get sports multiplier for this hour
            $sportsMult = $this->externalProvider 
                ? $this->externalProvider->getSportsMultiplier($targetDate, $hour)
                : 1.0;
            
            // NEW: Get additional multipliers
            $seasonMult = $this->getSeasonMultiplier($targetDate);
            $paydayMult = $this->getPaydayMultiplier($targetDate);
            $longWeekendMult = $this->getLongWeekendMultiplier($targetDate);
            $firstWarmMult = $this->getFirstWarmWeekendMultiplier($targetDate);
            
            // NEW: Trading Ban Multiplier (Sunday Shopping Ban)
            // Cinemas in malls usually get MORE traffic when shops are closed (Sunday ban)
            $tradingBanMult = 1.0;
            $isTradingBan = false;
            
            if ($this->getDayType($targetDate) === 'weekend' && date('w', strtotime($targetDate)) == 0) {
                // It's Sunday. Check if it's a trading ban.
                if ($this->isTradingBan($targetDate)) {
                    $isTradingBan = true;
                    $tradingBanMult = 1.15; // +15% traffic on non-trading Sundays
                } else {
                    $tradingBanMult = 0.90; // -10% traffic on trading Sundays (shopping competition)
                }
            }
            
            // Combine all multipliers
            $hourlyMult = $holidayMult * $weatherMult * $schoolMult * $sportsMult 
                        * $seasonMult * $paydayMult * $longWeekendMult * $firstWarmMult * $tradingBanMult;
            
            // Apply hourly bias from learning system
            // This corrects for systematic over/under-prediction at specific hours
            $hourlyBias = 1.0;
            if (method_exists($this->factorLearning, 'getHourlyBias')) {
                $hourlyBias = $this->factorLearning->getHourlyBias($hour);
            }
            $hourlyMult *= $hourlyBias;
            
            // CAP the combined multiplier to prevent excessive stacking
            // Max +40% boost, min -40% reduction
            $hourlyMult = max(0.6, min(1.4, $hourlyMult));
            
            $hp['adjustedOccupied'] = round($hp['predictedOccupied'] * $hourlyMult);
            $hp['hourlyBias'] = $hourlyBias;
            
            // Also adjust the range (min/max) by the same multiplier
            // This ensures "remaining visitors" estimate is accurate
            $hp['range']['min'] = max(0, round(($hp['range']['min'] ?? 0) * $hourlyMult));
            $hp['range']['max'] = round(($hp['range']['max'] ?? 0) * $hourlyMult);
            $hp['multipliers'] = [
                'holiday' => $holidayMult,
                'weather' => $weatherMult,
                'school' => $schoolMult,
                'sports' => $sportsMult,
                'season' => $seasonMult,
                'payday' => $paydayMult,
                'longWeekend' => $longWeekendMult,
                'firstWarmWeekend' => $firstWarmMult,
                'tradingBan' => $tradingBanMult,
                'combined' => round($hourlyMult, 2)
            ];
            
            // NEW: Human-readable description of applied factors
            $hp['appliedFactors'] = $this->getAppliedFactorsDescription(
                $hp['multipliers'], 
                $targetDate,
                $hour
            );
            
            // NEW: Structure for Factor Learning System - PASS ALL FACTORS!
            // Crucial fix: previously we missed school, sports, etc. so AI couldn't learn them.
            $hp['learningFactors'] = [
                'holiday' => ['key' => $this->getHolidayKey($targetDate), 'value' => $holidayMult],
                'weather' => ['key' => 'default', 'value' => $weatherMult], 
                'season' => ['key' => $this->getSeasonKey($targetDate), 'value' => $seasonMult],
                'payday' => ['key' => $this->getPaydayKey($targetDate), 'value' => $paydayMult],
                
                // Now passing the missing ones:
                'school' => ['key' => $this->isSchoolHoliday($targetDate) ? 'holidays' : 'regular', 'value' => $schoolMult],
                'sports' => ['key' => !empty($sportsEvents) ? 'match_day' : 'default', 'value' => $sportsMult],
                'long_weekend' => ['key' => $this->isLongWeekend($targetDate) ? 'yes' : 'no', 'value' => $longWeekendMult],
                'trading_ban' => ['key' => $isTradingBan ? 'ban' : ($tradingBanMult != 1.0 ? 'shopping' : 'default'), 'value' => $tradingBanMult],
                
                'combined_mult' => ['key' => 'total', 'value' => $hourlyMult]
            ];
        }
        
        // === LAYER 3: Real-time Bayesian update ===
        if ($currentData) {
            $this->applyBayesianUpdate($hourlyPrediction, $currentData, $targetDate);
        }
        
        // === Calculate totals ===
        $totalPredicted = 0;
        $totalAdjusted = 0;
        $totalTotal = 0;
        $minRange = 0;
        $maxRange = 0;
        
        foreach ($hourlyPrediction as $h => $hp) {
            $totalPredicted += $hp['predictedOccupied'];
            $totalAdjusted += $hp['adjustedOccupied'] ?? $hp['predictedOccupied'];
            $totalTotal += $hp['predictedTotal'] ?? 0;
            $minRange += $hp['range']['min'] ?? 0;
            // Removed duplicate minRange addition
            $maxRange += $hp['range']['max'] ?? 0;
            
            // Capture factors for learning (take from peak hour ~20:00 or just use the last one)
            if ($h == 20 || !isset($learningFactors)) {
                $learningFactors = $hp['learningFactors'] ?? [];
            }
        }
        
        // NOTE: sparseDataBoost removed to ensure consistency between hourly and daily totals
        // The system will learn appropriate multipliers from actual data via Factor Learning
        
        // NEW: Record prediction for future learning
        // We use the factors from peak time (20:00) as representative for the day
        if (isset($learningFactors)) {
            // Need base value (what it would be without multipliers)
            // Approx: adjusted / combinedMultiplier
            $combinedMult = $learningFactors['combined_mult']['value'] ?? 1.0;
            $baseValue = $totalAdjusted / max(0.1, $combinedMult);
            
            $this->factorLearning->recordPrediction(
                $targetDate, 
                $learningFactors, 
                $totalAdjusted, 
                $baseValue
            );
            
            // Record hourly predictions for per-hour learning
            if (method_exists($this->factorLearning, 'recordHourlyPrediction')) {
                $this->factorLearning->recordHourlyPrediction($targetDate, $hourlyPrediction);
            }
        }
        
        // Use CURRENT day's actual total seats if available AND reasonable (for accurate percentage)
        // If the passed total is too small (< 1000), it's likely incomplete data, use historical
        $actualTotalSeats = $totalTotal;
        if ($currentData && isset($currentData['totals']['total']) && $currentData['totals']['total'] > 1000) {
            $actualTotalSeats = $currentData['totals']['total'];
        }
        
        // Additional prediction (how many more people expected)
        $additionalPrediction = null;
        if ($currentData) {
            $currentOccupied = $currentData['totals']['occupied'] ?? 0;
            
            // Calculate remaining visitors as: predicted total - current sold
            $remaining = max(0, $totalAdjusted - $currentOccupied);
            
            // Calculate uncertainty as ±15% of remaining (reasonable confidence interval)
            $uncertainty = round($remaining * 0.15);
            
            $additionalPrediction = [
                'predictedAdditional' => $remaining,
                'range' => [
                    'min' => max(0, $remaining - $uncertainty),
                    'max' => $remaining + $uncertainty
                ]
            ];
        }
        
        // === Build response ===
        // Calculate average hourly multiplier for factors display
        $avgCombinedMult = count($hourlyPrediction) > 0
            ? array_sum(array_column(array_column($hourlyPrediction, 'multipliers'), 'combined')) / count($hourlyPrediction)
            : 1.0;
        
        // Get sports events for this date
        $sportsEvents = $this->externalProvider 
            ? $this->externalProvider->getSportsEvents($targetDate)
            : [];
        
        // Get learning stats
        $learningStats = $this->externalProvider 
            ? $this->externalProvider->getLearningStats()
            : null;
        
        // Get actual weather summary for frontend display
        $weatherSummary = null;
        if ($this->externalProvider) {
            $weatherData = $this->externalProvider->getHourlyWeather($targetDate);
            if ($weatherData && isset($weatherData['summary'])) {
                $weatherSummary = $weatherData['summary'];
            }
        }
        
        return [
            'targetDate' => $targetDate,
            'dayType' => $dayType,
            'basedOnDays' => count($modelData),
            'confidence' => $this->calculateConfidence($modelData, $avgCombinedMult),
            'adaptiveWeights' => $weights,
            'factors' => [
                'holiday' => $this->getHolidayInfo($targetDate),
                'holidayMultiplier' => $holidayMult,
                'schoolHoliday' => $this->isSchoolHoliday($targetDate),
                'schoolMultiplier' => $schoolMult,
                'specialPeriod' => $this->getSpecialPeriod($targetDate),
                'sports' => $sportsEvents,
                'hasSportsImpact' => !empty($sportsEvents),
                'combinedMultiplier' => round($avgCombinedMult, 2)
            ],
            'hourly' => $hourlyPrediction,
            'totals' => [
                'predictedOccupied' => $totalPredicted,
                'adjustedOccupied' => $totalAdjusted,
                'predictedTotal' => $actualTotalSeats, // Use actual seats when available
                'historicalTotal' => $totalTotal, // Keep historical for reference
                'predictedPercent' => $actualTotalSeats > 0 ? round(($totalAdjusted / $actualTotalSeats) * 100) : 0,
                'range' => [
                    'min' => $minRange,
                    'max' => $maxRange
                ]
            ],
            'additional' => $additionalPrediction,
            'learning' => $learningStats,
            'weather' => $weatherSummary, // Actual weather data (avgTemp, hasRain) for frontend
            'modelVersion' => '2.1-ensemble-hourly'
        ];
    }
    
    /**
     * Calculate historical baseline from weighted average
     * Uses smart outlier detection with proportion-based replacement
     */
    private function calculateHistoricalBaseline($modelData) {
        $hourlyPrediction = [];
        
        // STEP 1: First pass - calculate DAILY TOTALS for each historical day
        // Calculate both raw total and "clean" total (excluding extreme values)
        $dailyTotals = [];
        $cleanDailyTotals = []; // Total excluding outlier hours (>300)
        $hourlyProportions = [];
        
        foreach ($modelData as $dayIdx => $day) {
            $dayTotal = 0;
            $cleanTotal = 0;
            foreach ($day['hourly'] as $h => $hourData) {
                if ($h >= 10 && $h <= 23) {
                    $val = $hourData['occupied'];
                    $dayTotal += $val;
                    // Clean total excludes extreme values
                    if ($val <= 300) {
                        $cleanTotal += $val;
                    }
                }
            }
            $dailyTotals[$dayIdx] = $dayTotal;
            // Use clean total if it has meaningful data, else use raw
            $cleanDailyTotals[$dayIdx] = $cleanTotal > 50 ? $cleanTotal : $dayTotal;
            
            // Calculate proportion using clean total - SKIP outlier hours entirely
            foreach ($day['hourly'] as $h => $hourData) {
                if ($h >= 10 && $h <= 23 && $cleanDailyTotals[$dayIdx] > 0) {
                    $val = $hourData['occupied'];
                    // Only include non-outlier hours in proportion calculation
                    if ($val <= 200) {
                        if (!isset($hourlyProportions[$h])) {
                            $hourlyProportions[$h] = [];
                        }
                        $hourlyProportions[$h][] = $val / $cleanDailyTotals[$dayIdx];
                    }
                }
            }
        }
        
        // Calculate typical proportion for each hour (median of proportions)
        $typicalProportions = [];
        foreach ($hourlyProportions as $h => $proportions) {
            sort($proportions);
            $typicalProportions[$h] = count($proportions) > 0 
                ? $proportions[(int)floor(count($proportions) / 2)]
                : 0.08; // Default ~8% per hour if no data
        }
        
        // STEP 2: Detect outliers using Z-score approach
        // An hour is an outlier if it deviates significantly from typical proportion
        $allOccupancyValues = [];
        foreach ($modelData as $day) {
            foreach ($day['hourly'] as $h => $hourData) {
                if ($h >= 10 && $h <= 23) {
                    $allOccupancyValues[] = $hourData['occupied'];
                }
            }
        }
        sort($allOccupancyValues);
        $q75Index = (int)floor(count($allOccupancyValues) * 0.75);
        $normalMax = count($allOccupancyValues) > 0 ? $allOccupancyValues[$q75Index] : 100;
        
        // Outlier threshold: values that are unusually high compared to 75th percentile
        // and represent disproportionate share of daily traffic
        $outlierThreshold = max(250, $normalMax * 3);
        
        // STEP 3: Process each hour
        for ($h = 10; $h <= 23; $h++) {
            $sumWeightedOccupied = 0;
            $sumWeightedTotal = 0;
            $sumWeights = 0;
            $values = [];
            $dayData = [];
            
            foreach ($modelData as $dayIdx => $day) {
                if (!isset($day['hourly'][$h])) continue;
                $hourData = $day['hourly'][$h];
                $dayTotal = $dailyTotals[$dayIdx];
                $cleanTotal = $cleanDailyTotals[$dayIdx];
                
                // Calculate actual proportion this hour represents
                $actualProportion = $dayTotal > 0 ? ($hourData['occupied'] / $dayTotal) : 0;
                $actualValue = $hourData['occupied'];
                
                // Check if this hour's value is an outlier using multiple criteria:
                // 1. Extreme absolute value: >400 is always suspicious for single hour
                // 2. Extreme proportion: one hour represents >35% of daily traffic (abnormal)
                $isExtremelyHigh = $actualValue > 400;
                $isDisproportionate = $actualProportion > 0.35 && $actualValue > 200;
                $isOutlier = $isExtremelyHigh || $isDisproportionate;
                
                // If outlier, use typical proportion of CLEAN daily total
                // This gives a reasonable estimate based on what other hours look like
                $typicalProp = $typicalProportions[$h] ?? 0.08;
                $reasonableValue = round($cleanTotal * $typicalProp);
                $valueToUse = $isOutlier ? $reasonableValue : $actualValue;
                
                $dayData[] = [
                    'occupied' => $valueToUse,
                    'originalOccupied' => $actualValue,
                    'wasOutlier' => $isOutlier,
                    'total' => $hourData['total'],
                    'weight' => $day['weight']
                ];
                $values[] = $valueToUse;
            }
            
            // Apply IQR filtering for dense data (4+ points)
            $validIndices = count($values) >= 4 
                ? $this->filterOutliers($values)
                : array_keys($values);
            
            // Calculate weighted average
            $outliersReplaced = 0;
            foreach ($validIndices as $idx) {
                $d = $dayData[$idx];
                $sumWeightedOccupied += $d['occupied'] * $d['weight'];
                $sumWeightedTotal += $d['total'] * $d['weight'];
                $sumWeights += $d['weight'];
                if ($d['wasOutlier']) $outliersReplaced++;
            }
            
            $filteredValues = array_map(fn($idx) => $values[$idx], $validIndices);
            
            if ($sumWeights > 0) {
                $predictedOccupied = round($sumWeightedOccupied / $sumWeights);
                $predictedTotal = round($sumWeightedTotal / $sumWeights);
                
                // Calculate standard deviation
                $mean = count($filteredValues) > 0 ? array_sum($filteredValues) / count($filteredValues) : 0;
                $variance = count($filteredValues) > 0 
                    ? array_sum(array_map(fn($v) => pow($v - $mean, 2), $filteredValues)) / count($filteredValues)
                    : 0;
                $stddev = sqrt($variance);
                
                if ($stddev < 2 || count($filteredValues) < 3) {
                    $stddev = max(3, $predictedOccupied * 0.15);
                }
                
                $hourlyPrediction[$h] = [
                    'predictedOccupied' => $predictedOccupied,
                    'predictedTotal' => $predictedTotal,
                    'predictedPercent' => $predictedTotal > 0 ? round(($predictedOccupied / $predictedTotal) * 100) : 0,
                    'dataPoints' => count($filteredValues),
                    'outliersReplaced' => $outliersReplaced,
                    'typicalProportion' => round(($typicalProportions[$h] ?? 0) * 100, 1),
                    'stddev' => round($stddev, 1),
                    'range' => [
                        'min' => max(0, round($predictedOccupied - $stddev)),
                        'max' => round($predictedOccupied + $stddev)
                    ]
                ];
            }
        }
        
        return $hourlyPrediction;
    }
    
    /**
     * Filter outliers using IQR (Interquartile Range) method
     * Returns indices of values that are NOT outliers
     * 
     * @param array $values Array of numeric values
     * @return array Indices of non-outlier values
     */
    private function filterOutliers($values) {
        if (count($values) < 4) {
            // Not enough data for IQR, return all indices
            return array_keys($values);
        }
        
        // Sort values to calculate percentiles
        $sorted = $values;
        sort($sorted);
        $n = count($sorted);
        
        // Calculate Q1 (25th percentile) and Q3 (75th percentile)
        $q1Index = (int)floor($n * 0.25);
        $q3Index = (int)floor($n * 0.75);
        
        $q1 = $sorted[$q1Index];
        $q3 = $sorted[$q3Index];
        $iqr = $q3 - $q1;
        
        // Define outlier bounds (1.5 * IQR is standard)
        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);
        
        // Return indices of values within bounds
        $validIndices = [];
        foreach ($values as $idx => $val) {
            if ($val >= $lowerBound && $val <= $upperBound) {
                $validIndices[] = $idx;
            }
        }
        
        // If all values are outliers (shouldn't happen), return all
        if (empty($validIndices)) {
            return array_keys($values);
        }
        
        return $validIndices;
    }
    
    /**
     * Apply Bayesian update based on current observations
     * If reality diverges from prediction, adjust future hours
     */
    private function applyBayesianUpdate(&$hourlyPrediction, $currentData, $targetDate) {
        $currentHour = (int)date('H');
        
        // Only apply if we have current hour data
        if (!isset($currentData['hourly'][$currentHour])) return;
        if (!isset($hourlyPrediction[$currentHour])) return;
        
        $currentActual = $currentData['hourly'][$currentHour]['occupied'] ?? 0;
        
        // Use ADJUSTED value (with multipliers) for comparison, not raw predicted
        $currentAdjusted = $hourlyPrediction[$currentHour]['adjustedOccupied'] 
            ?? $hourlyPrediction[$currentHour]['predictedOccupied'] 
            ?? 0;
        
        if ($currentAdjusted == 0) return;
        
        // Calculate divergence ratio using adjusted values
        $divergence = $currentActual / $currentAdjusted;
        
        // Only apply Bayesian for REASONABLE divergences (0.5x to 2.0x)
        // Beyond this range, the historical data is likely inadequate
        // and we shouldn't trust it as a basis for correction
        if ($divergence < 0.5 || $divergence > 2.0) {
            // Skip Bayesian - divergence too extreme, historical data unreliable
            return;
        }
        
        // If significant divergence within reasonable range, adjust future predictions
        if (abs($divergence - 1.0) > 0.15) {
            // Dampen the adjustment for future hours (don't overreact)
            $adjustmentFactor = 1 + ($divergence - 1) * 0.5;
            
            foreach ($hourlyPrediction as $hour => &$hp) {
                if ($hour > $currentHour) {
                    $hp['bayesianAdjustment'] = round($adjustmentFactor, 2);
                    $hp['adjustedOccupied'] = round(
                        ($hp['adjustedOccupied'] ?? $hp['predictedOccupied']) * $adjustmentFactor
                    );
                }
            }
        }
    }
    
    /**
     * Calculate confidence score (0-1)
     */
    private function calculateConfidence($modelData, $externalMult) {
        $dataConfidence = min(1, count($modelData) / 10);  // Max at 10 days
        
        // Reduce confidence if using extreme multipliers
        $multiplierUncertainty = abs($externalMult - 1.0) * 0.3;
        
        return max(0.1, min(1.0, $dataConfidence - $multiplierUncertainty));
    }
    
    /**
     * TRAIN: Feed actual results back into the system to improve future predictions
     */
    public function train($date, $actualData) {
        if (!$this->externalProvider) return;
        
        $dayType = $this->getDayType($date);
        
        // 1. Generate prediction for this day (simulating as if we didn't know the result)
        // We pass null as currentData to force a blind prediction
        $prediction = $this->predict($date, null);
        
        if (isset($prediction['hourly']) && isset($actualData['hourly'])) {
            $hourlyPred = [];
            $hourlyActual = [];
            
            // Align hourly data
            foreach ($prediction['hourly'] as $h => $p) {
                if (isset($actualData['hourly'][$h])) {
                    $hourlyPred[] = $p['adjustedOccupied'] ?? $p['predictedOccupied'];
                    $hourlyActual[] = $actualData['hourly'][$h]['occupied'];
                }
            }
            
            // 2. Update learning weights (Category weights)
            if (!empty($hourlyPred)) {
                $this->externalProvider->updateLearning($dayType, $hourlyPred, $hourlyActual);
                
                // 3. NEW: Update Factor Multipliers (Per-Factor Learning)
                // This adjusts specific factors like weather or holiday multipliers
                $actualTotal = $actualData['totals']['occupied'] ?? 0;
                $learningResult = null;
                if ($actualTotal > 0) {
                    $learningResult = $this->factorLearning->learnFromDay($date, $actualTotal);
                }
                
                return [
                    'updated' => true,
                    'learningResult' => $learningResult
                ];
            }
        }
        
        return false;
    }

    /**
     * Predict for a specific screening (with movie context)
     */
    public function predictScreening($hour, $dayType, $currentOccupied, $totalSeats, $movieContext = null) {
        $modelData = $this->dayModels[$dayType];
        if (count($modelData) < 1) return null;
        
        // Base prediction
        $values = [];
        $sumWeighted = 0;
        $sumWeights = 0;
        
        foreach ($modelData as $day) {
            if (!isset($day['hourly'][$hour])) continue;
            $hourData = $day['hourly'][$hour];
            $weight = $day['weight'];
            
            if (($hourData['screenings'] ?? 0) > 0) {
                $avgPerScreening = $hourData['occupied'] / $hourData['screenings'];
                $sumWeighted += $avgPerScreening * $weight;
                $sumWeights += $weight;
                $values[] = $avgPerScreening;
            }
        }
        
        if ($sumWeights == 0) return null;
        
        $basePredicted = round($sumWeighted / $sumWeights);
        
        // Apply movie-specific multipliers
        $multiplier = 1.0;
        
        // Apply movie-specific multipliers
        
        /* 
           CALCULATE MULTIPLIERS 
           We handle $movieContext being null safely with ?? operator
        */
        
        // 1. Hourly Pattern Multiplier (Base)
        $hourlyMult = 0;
        if ($sumWeights > 0) {
            foreach ($modelData as $day) {
                // If this day has data for this hour, include its multiplier
                 if (isset($day['hourly'][$hour])) {
                    $hourlyMult += ($day['hourly'][$hour]['multiplier'] ?? 1.0) * $day['weight'];
                 }
            }
            $hourlyMult /= $sumWeights;
        } else {
            $hourlyMult = 1.0;
        }
        
        // 2. Genre Multiplier
        $genres = $movieContext['genres'] ?? [];
        $date = $movieContext['date'] ?? date('Y-m-d');
        $genreMult = $this->getGenreMultiplier($genres, $hour, $dayType, $date);
        
        // 3. Premiere/Newness Multiplier
        $premiereDate = $movieContext['premiereDate'] ?? null;
        $premiereMult = 1.0;
        if ($premiereDate) {
            $premiereMult = $this->getPremiereMultiplier($premiereDate, $date);
        }
        
        // 4. Children's Movie Multiplier
        $childrenMult = 1.0;
        if (!empty($movieContext['isForChildren'])) {
            $childrenMult = $this->getChildrenMultiplier($hour, $dayType);
        }

        // 5. Specific Movie History Multiplier (The "Learning" Part)
        $movieMult = 1.0;
        if (!empty($movieContext['title'])) {
            $titleKey = $this->normalizeTitle($movieContext['title']);
            if (isset($this->movieModels[$titleKey])) {
                // Determine how this movie performs relative to average
                $history = $this->movieModels[$titleKey];
                $totalOcc = 0;
                $totalCap = 0;
                $weightSum = 0;
                
                foreach ($history as $h) {
                    $totalOcc += $h['totalOccupied'] * $h['weight'];
                    $totalCap += $h['totalSeats'] * $h['weight'];
                    $weightSum += $h['weight'];
                }
                
                if ($totalCap > 0) {
                    $avgOccupancy = $totalOcc / $totalCap; // e.g. 0.25 (25%)
                    
                    // Base occupancy assumption is around 15%
                    // If movie extracts 30%, multiplier should be ~2.0
                    $baseRate = 0.15;
                    $movieMult = $avgOccupancy / $baseRate;
                    
                    // SATURATION LOGIC: If occupancy > 80%, boost significantly
                    // because "sold out" means potential was even higher
                    if ($avgOccupancy > 0.80) {
                        $saturationBonus = 1.0 + ($avgOccupancy - 0.80) * 2.5; // Up to 1.5x boost
                        $movieMult *= $saturationBonus;
                    }
                    
                    // Clamp to reasonable limits (0.5x to 4.0x) to avoid overfitting
                    $movieMult = max(0.5, min(4.0, $movieMult));
                    
                    // Blend with 1.0 based on confidence (amount of history)
                    $confidence = min(1.0, count($history) / 3); // Full confidence after 3 stats
                    $movieMult = 1.0 + ($movieMult - 1.0) * $confidence;
                }
            }
        }
        
        // Combine base multipliers
        $multiplier = $hourlyMult * $genreMult * $premiereMult * $childrenMult * $movieMult;
        
        $predictedOccupied = round($basePredicted * $multiplier);
        
        // Calculate range
        $mean = count($values) > 0 ? array_sum($values) / count($values) : 0;
        $variance = count($values) > 0 
            ? array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / count($values) 
            : 0;
        $stddev = sqrt($variance);
        
        if ($stddev < 2 || count($values) < 3) {
            $stddev = max(3, $predictedOccupied * 0.15);
        }
        
        $predictedMin = max(0, round($predictedOccupied - $stddev));
        $predictedMax = round($predictedOccupied + $stddev);
        
        $additionalMin = max(0, $predictedMin - $currentOccupied);
        $additionalMax = max(0, $predictedMax - $currentOccupied);
        
        return [
            'occupied' => $currentOccupied,
            'total' => $totalSeats,
            'predicted' => $predictedOccupied,
            'multiplier' => round($multiplier, 2),
            // Pass factor details for UI
            'factors' => [
                'hourly' => round($hourlyMult, 2),
                'genre' => round($genreMult, 2),
                'premiere' => round($premiereMult, 2),
                'children' => round($childrenMult, 2)
            ],
            'additional' => [
                'min' => $additionalMin,
                'max' => $additionalMax
            ],
            'finalOccupied' => [
                'min' => $currentOccupied + $additionalMin,
                'max' => min($totalSeats, $currentOccupied + $additionalMax)
            ],
            'finalPercent' => $totalSeats > 0 ? [
                'min' => round(($currentOccupied + $additionalMin) / $totalSeats * 100),
                'max' => round(min($totalSeats, $currentOccupied + $additionalMax) / $totalSeats * 100)
            ] : null
        ];
    }
    
    /**
     * Get children's movie time-based multiplier
     */
    public function getChildrenMultiplier($hour, $dayType) {
        // Kids movies peak earlier (10:00 - 15:00)
        // They drop off sharply after 19:00
        
        if ($dayType === 'weekend') {
            if ($hour >= 10 && $hour <= 14) return 1.30; // Prime time for families
            if ($hour >= 15 && $hour <= 17) return 1.10;
            if ($hour >= 19) return 0.60; // Kids sleep
        } else {
            // Workdays - schools out
            if ($hour >= 16 && $hour <= 18) return 1.15; // After school
            if ($hour < 15) return 0.8; // School time
            if ($hour >= 20) return 0.5; // Late
        }
        
        return 1.0;
    }
    
    /**
     * Get model statistics
     */
    public function getModelStats() {
        return [
            'weekend' => count($this->dayModels['weekend']),
            'workday' => count($this->dayModels['workday']),
            'tuesday' => count($this->dayModels['tuesday']),
            'totalDays' => $this->stats['daysLoaded'],
            'genresTracked' => count($this->genreModels),
            'lastUpdate' => $this->stats['lastUpdate'],
            'weatherEnabled' => $this->config['weatherEnabled'],
            'version' => '2.0-ensemble'
        ];
    }
    
    /**
     * Get days loaded
     */
    public function getDaysLoaded() {
        return $this->stats['daysLoaded'];
    }
    
    /**
     * Generate human-readable descriptions of applied factors
     * Only includes factors that have non-neutral (non-1.0) values
     * 
     * @param array $multipliers The multipliers array from prediction
     * @param string $date The date being predicted
     * @param int $hour The hour being predicted
     * @return array List of factor descriptions
     */
    public function getAppliedFactorsDescription($multipliers, $date, $hour) {
        $factors = [];
        
        // Helper to format percentage
        $formatPct = function($mult) {
            if ($mult > 1.0) return '+' . round(($mult - 1) * 100) . '%';
            if ($mult < 1.0) return round(($mult - 1) * 100) . '%';
            return null;
        };
        
        // Holiday factor
        if (isset($multipliers['holiday']) && $multipliers['holiday'] != 1.0) {
            $pct = $formatPct($multipliers['holiday']);
            $holiday = $this->getHolidayInfo($date);
            $name = $holiday ? $holiday[0] : 'Dzień specjalny';
            $md = date('m-d', strtotime($date));
            
            if ($md === '12-06') $name = 'Mikołajki';
            if ($md === '12-24') $name = 'Wigilia';
            if ($md === '12-25') $name = 'Boże Narodzenie';
            if ($md === '02-14') $name = 'Walentynki';
            
            $factors[] = [
                'icon' => 'celebration',
                'name' => 'Święto/Dzień specjalny',
                'value' => $name,
                'impact' => $pct,
                'type' => $multipliers['holiday'] > 1 ? 'positive' : 'negative'
            ];
        }
        
        // Weather factor
        if (isset($multipliers['weather']) && $multipliers['weather'] != 1.0) {
            $pct = $formatPct($multipliers['weather']);
            $weatherDesc = $multipliers['weather'] > 1.0 ? 'Deszczowo/Zimno' : 'Słonecznie/Ciepło';
            $factors[] = [
                'icon' => $multipliers['weather'] > 1.0 ? 'rainy' : 'sunny',
                'name' => 'Pogoda',
                'value' => $weatherDesc,
                'impact' => $pct,
                'type' => $multipliers['weather'] > 1 ? 'positive' : 'negative'
            ];
        }
        
        // School holiday
        if (isset($multipliers['school']) && $multipliers['school'] != 1.0) {
            $pct = $formatPct($multipliers['school']);
            $factors[] = [
                'icon' => 'school',
                'name' => 'Ferie/Wakacje',
                'value' => 'Okres wolny od szkoły',
                'impact' => $pct,
                'type' => 'positive'
            ];
        }
        
        // Sports events
        if (isset($multipliers['sports']) && $multipliers['sports'] != 1.0) {
            $pct = $formatPct($multipliers['sports']);
            $factors[] = [
                'icon' => 'sports_soccer',
                'name' => 'Wydarzenie sportowe',
                'value' => 'Mecz piłkarski',
                'impact' => $pct,
                'type' => 'negative'
            ];
        }
        
        // Season
        if (isset($multipliers['season']) && $multipliers['season'] != 1.0) {
            $pct = $formatPct($multipliers['season']);
            $seasonName = $multipliers['season'] > 1.0 ? 'Zima' : 'Lato';
            $factors[] = [
                'icon' => $multipliers['season'] > 1.0 ? 'ac_unit' : 'wb_sunny',
                'name' => 'Sezon',
                'value' => $seasonName,
                'impact' => $pct,
                'type' => $multipliers['season'] > 1 ? 'positive' : 'negative'
            ];
        }
        
        // Payday
        if (isset($multipliers['payday']) && $multipliers['payday'] != 1.0) {
            $pct = $formatPct($multipliers['payday']);
            $paydayDesc = $multipliers['payday'] > 1.0 ? 'Po wypłacie (10-15 lub 25-30)' : 'Przed wypłatą (1-9)';
            $factors[] = [
                'icon' => 'payments',
                'name' => 'Cykl płacowy',
                'value' => $paydayDesc,
                'impact' => $pct,
                'type' => $multipliers['payday'] > 1 ? 'positive' : 'negative'
            ];
        }
        
        // Long weekend
        if (isset($multipliers['longWeekend']) && $multipliers['longWeekend'] != 1.0) {
            $pct = $formatPct($multipliers['longWeekend']);
            $factors[] = [
                'icon' => 'beach_access',
                'name' => 'Długi weekend',
                'value' => 'Piątek przed świętem',
                'impact' => $pct,
                'type' => 'positive'
            ];
        }
        
        // First warm weekend
        if (isset($multipliers['firstWarmWeekend']) && $multipliers['firstWarmWeekend'] != 1.0) {
            $pct = $formatPct($multipliers['firstWarmWeekend']);
            $factors[] = [
                'icon' => 'nature_people',
                'name' => 'Pierwszy ciepły weekend',
                'value' => 'Po zimie ludzie wychodzą na zewnątrz',
                'impact' => $pct,
                'type' => 'negative'
            ];
        }
        
        return $factors;
    }
    
    /**
     * Get movie-specific factors description
     * For transparency about movie-level modifiers
     * 
     * @param array $movie Movie data from API
     * @return array List of movie-specific factors
     */
    public function getMovieFactorsDescription($movie) {
        if (!$movie) return [];
        
        $factors = [];
        $genre = $movie['genre'] ?? $movie['genres'][0] ?? '';
        $title = $movie['title'] ?? '';
        $country = $movie['country'] ?? '';
        $director = $movie['director'] ?? '';
        $imdbRating = floatval($movie['imdbRating'] ?? 0);
        $duration = intval($movie['duration'] ?? 0);
        $isForChildren = $movie['isForChildren'] ?? false;
        $format = $movie['format'] ?? '2D';
        
        // Country factor
        $countryLower = strtolower($country);
        if (strpos($countryLower, 'polska') !== false || strpos($countryLower, 'poland') !== false) {
            $factors[] = [
                'icon' => 'flag',
                'name' => 'Produkcja polska',
                'value' => 'Polska',
                'impact' => 'Wolniejszy decay -30%/2tyg',
                'type' => 'positive'
            ];
        } elseif (strpos($countryLower, 'ukrain') !== false || strpos($country, 'UA') !== false) {
            $factors[] = [
                'icon' => 'flag',
                'name' => 'Produkcja ukraińska',
                'value' => 'Ukraina',
                'impact' => 'Solidarność z Ukrainą +10%',
                'type' => 'positive'
            ];
        }
        
        // IMDB rating
        if ($imdbRating >= 8.0) {
            $factors[] = [
                'icon' => 'star',
                'name' => 'Wysoka ocena IMDB',
                'value' => $imdbRating . '/10',
                'impact' => 'Wolniejszy decay -15%/2tyg',
                'type' => 'positive'
            ];
        } elseif ($imdbRating >= 7.5) {
            $factors[] = [
                'icon' => 'star_half',
                'name' => 'Dobra ocena IMDB',
                'value' => $imdbRating . '/10',
                'impact' => 'Wolniejszy decay -25%/2tyg',
                'type' => 'positive'
            ];
        }
        
        // Famous director
        $famousDirectors = ['nolan', 'spielberg', 'tarantino', 'scorsese', 'villeneuve', 
                           'wajda', 'polański', 'holland', 'smarzowski', 'komasa'];
        $directorLower = strtolower($director);
        foreach ($famousDirectors as $famDir) {
            if (strpos($directorLower, $famDir) !== false) {
                $factors[] = [
                    'icon' => 'movie',
                    'name' => 'Znany reżyser',
                    'value' => $director,
                    'impact' => 'Wolniejszy decay -30%/2tyg',
                    'type' => 'positive'
                ];
                break;
            }
        }
        
        // Duration
        if ($duration > 150) {
            $factors[] = [
                'icon' => 'schedule',
                'name' => 'Długi film',
                'value' => $duration . ' min',
                'impact' => 'Wyższa wrażliwość na pogodę +40%',
                'type' => 'warning'
            ];
        }
        
        // Format
        $formatUpper = strtoupper($format);
        if (strpos($formatUpper, 'IMAX') !== false) {
            $factors[] = [
                'icon' => 'theaters',
                'name' => 'Format IMAX',
                'value' => $format,
                'impact' => 'Niższa wrażliwość na pogodę -30%',
                'type' => 'positive'
            ];
        } elseif (strpos($formatUpper, 'DOLBY') !== false || strpos($formatUpper, '4DX') !== false) {
            $factors[] = [
                'icon' => 'theaters',
                'name' => 'Format premium',
                'value' => $format,
                'impact' => 'Niższa wrażliwość na pogodę -25%',
                'type' => 'positive'
            ];
        }
        
        // isForChildren
        if ($isForChildren) {
            $factors[] = [
                'icon' => 'child_care',
                'name' => 'Film dla dzieci',
                'value' => 'Tak',
                'impact' => 'Wyższa wrażliwość na pogodę +30%',
                'type' => 'warning'
            ];
        }
        
        // Genre (fandom vs quality)
        $genreLower = strtolower($genre);
        if (strpos($genreLower, 'horror') !== false) {
            $factors[] = [
                'icon' => 'dark_mode',
                'name' => 'Gatunek: Horror',
                'value' => $genre,
                'impact' => 'Szybki decay -70%/2tyg, Odporność na pogodę',
                'type' => 'neutral'
            ];
        } elseif (strpos($genreLower, 'dramat') !== false) {
            $factors[] = [
                'icon' => 'theater_comedy',
                'name' => 'Gatunek: Dramat',
                'value' => $genre,
                'impact' => 'Wolniejszy decay -30%/2tyg',
                'type' => 'positive'
            ];
        }
        
        return $factors;
    }
    // Helper to get key for learning - used in predict()
    private function getHolidayKey($date) {
        $md = date('m-d', strtotime($date));
        if ($md == '12-24') return 'christmas_eve';
        if ($md == '12-25') return 'christmas';
        if ($md == '12-26') return 'post_christmas';
        if ($md == '12-31') return 'new_years_eve';
        if ($md == '01-01') return 'new_year';
        if ($md == '02-14') return 'valentines';
        if ($md == '06-01') return 'childrens_day';
        
        // Post-Christmas
        if (date('m', strtotime($date)) == 12 && date('d', strtotime($date)) >= 27 && date('d', strtotime($date)) <= 30) {
            return 'post_christmas';
        }
        
        return 'default';
    }

    private function getSeasonKey($date) {
        $m = (int)date('m', strtotime($date));
        if ($m >= 6 && $m <= 8) return 'summer';
        if ($m == 12 || $m <= 2) return 'winter';
        if ($m >= 3 && $m <= 5) return 'spring';
        return 'autumn';
    }

    private function getPaydayKey($date) {
        $d = (int)date('d', strtotime($date));
        if (($d >= 10 && $d <= 15) || ($d >= 25 && $d <= 30)) return 'after_payday';
        if ($d >= 1 && $d <= 9) return 'before_payday';
        return 'regular';
    }
}
