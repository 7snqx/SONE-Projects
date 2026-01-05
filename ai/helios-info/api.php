<?php
/**
 * Helios Łódź - Full API with posters, duration, real-time occupancy
 */

set_time_limit(180); // Allow up to 3 minutes for first requests
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

define('CINEMA_ID', '46055d88-5f34-44a0-9584-b041caa71e26');
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_TIME', 60);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!file_exists(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);

/**
 * Get "business date" for cinema - screenings that run past midnight
 * are still considered part of the previous day until 5:00 AM
 */
function getBusinessDate() {
    $hour = (int)date('G'); // 0-23
    
    // Between midnight (00:00) and 5:00 AM, use previous day's date
    // This handles late-night screenings like Avatar ending at 00:45
    if ($hour < 5) {
        return date('Y-m-d', strtotime('-1 day'));
    }
    
    return date('Y-m-d');
}

function httpGet($url, $timeout = 45) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10, // Increased connect timeout
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FAILONERROR => false // Don't fail immediately, check code
    ]);
    $response = curl_exec($ch);
    
    if ($response === false) {
        file_put_contents('debug.log', "httpGet failed for $url: " . curl_error($ch) . "\n", FILE_APPEND);
    }
    
    curl_close($ch);
    return $response ? json_decode($response, true) : null;
}

// Filmweb fallback for movie posters
// Helper to fetch poster from Helios API
function getHeliosPoster($movieId) {
    $cacheFile = CACHE_DIR . '/helios_' . $movieId . '.json';
    
    // Check cache (30 days)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 2592000)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (!empty($data['poster'])) {
            return $data['poster'];
        }
    }
    
    $url = "https://restapi.helios.pl/api/movie/" . $movieId;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
         CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    
    $json = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $json) {
        $data = json_decode($json, true);
        $posterUrl = null;
        
        // Helios API structure inspection needed, typically it's in 'poster' or 'images'
        // Based on typical Helios API:
        if (!empty($data['posterPath'])) {
             $posterUrl = $data['posterPath']; // Sometimes relative?
             if (strpos($posterUrl, 'http') !== 0) {
                 $posterUrl = 'https://helios.pl' . $posterUrl;
             }
        } elseif (!empty($data['images']) && is_array($data['images']) && count($data['images']) > 0) {
            $posterUrl = $data['images'][0]['path'] ?? null;
        } elseif (!empty($data['poster'])) {
             $posterUrl = $data['poster'];
        }
        
        // Cache even if null to avoid hammering API
        file_put_contents($cacheFile, json_encode(['poster' => $posterUrl]));
        
        return $posterUrl;
    }
    
    return null;
}

function getFilmwebPoster($movieTitle) {
    // Remove Polish suffixes like "- UA", "- pl", etc.
    $cleanTitle = preg_replace('/\s*[-–]\s*(UA|PL|napisy|dubbing|2D|3D|IMAX)$/i', '', $movieTitle);
    $cleanTitle = trim($cleanTitle);
    
    // Cache key
    $cacheKey = md5('filmweb_' . $cleanTitle);
    $cacheFile = CACHE_DIR . '/poster_' . $cacheKey . '.json';
    
    // Check cache (30 days)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 2592000) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        return $cached['poster_url'] ?? null;
    }
    
    // Search Filmweb
    $searchUrl = 'https://www.filmweb.pl/search?q=' . urlencode($cleanTitle);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $searchUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    
    $posterUrl = null;
    
    // Extract poster from search results
    if ($html) {
        // Look for poster image in search results
        if (preg_match('/<img[^>]+class="[^"]*poster[^"]*"[^>]+src="([^"]+)"/', $html, $match)) {
            $posterUrl = $match[1];
        } elseif (preg_match('/<img[^>]+data-src="(https:\/\/fwcdn[^"]+poster[^"]+)"/', $html, $match)) {
            $posterUrl = $match[1];
        } elseif (preg_match('/srcSet="([^"]+fwcdn[^"]+\.jpg)/', $html, $match)) {
            $posterUrl = explode(' ', $match[1])[0];
        }
        
        // Make sure URL is absolute
        if ($posterUrl && strpos($posterUrl, '//') === 0) {
            $posterUrl = 'https:' . $posterUrl;
        }
    }
    
    // Cache result
    @file_put_contents($cacheFile, json_encode([
        'title' => $cleanTitle,
        'poster_url' => $posterUrl,
        'fetched_at' => date('c')
    ]));
    
    return $posterUrl;
}

// Równoległe pobieranie occupancy
function getOccupancyBatch($screeningIds) {
    $mh = curl_multi_init();
    $handles = [];
    
    foreach ($screeningIds as $id) {
        $url = "https://restapi.helios.pl/api/cinema/" . CINEMA_ID . "/screening/{$id}/occupancy";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$id] = $ch;
    }
    
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);
    
    $results = [];
    foreach ($handles as $id => $ch) {
        $response = curl_multi_getcontent($ch);
        $data = json_decode($response, true);
        $results[$id] = [
            'occupied' => $data['totalOccupied'] ?? 0,
            'free' => $data['seatsLeft'] ?? 0,
            'total' => ($data['totalOccupied'] ?? 0) + ($data['seatsLeft'] ?? 0)
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($mh);
    return $results;
}

// Cache danych filmów (poster, duration, genres)
function loadMovieDataCache() {
    $cacheFile = CACHE_DIR . '/movies_full.json';
    if (file_exists($cacheFile)) {
        return json_decode(file_get_contents($cacheFile), true) ?: [];
    }
    return [];
}

function saveMovieDataCache($cache) {
    file_put_contents(CACHE_DIR . '/movies_full.json', json_encode($cache, JSON_UNESCAPED_UNICODE));
}

function getScreens() {
    $cacheFile = CACHE_DIR . '/screens.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
        return json_decode(file_get_contents($cacheFile), true) ?: [];
    }
    
    $screens = httpGet("https://restapi.helios.pl/api/cinema/" . CINEMA_ID . "/screen") ?: [];
    $map = [];
    foreach ($screens as $s) {
        $map[$s['id']] = $s['name'] ?? 'Sala';
    }
    
    file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_UNICODE));
    return $map;
}

// Global variable to hold screen map in memory during request
$SCREEN_MAP = null;

function getScreenName($screenId) {
    global $SCREEN_MAP;
    
    // 1. Initialize map
    if ($SCREEN_MAP === null) {
        $cacheFile = CACHE_DIR . '/screens_map_v3.json';
        
        // Load cache if exists and fresh (24h)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $SCREEN_MAP = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        
        // If cache empty or expired, fetch FULL LIST first
        if (empty($SCREEN_MAP)) {
            $screens = httpGet("https://restapi.helios.pl/api/cinema/" . CINEMA_ID . "/screen");
            if ($screens) {
                foreach ($screens as $s) {
                    if (isset($s['id']) && isset($s['name'])) {
                        $SCREEN_MAP[$s['id']] = $s['name'];
                    }
                }
                file_put_contents($cacheFile, json_encode($SCREEN_MAP, JSON_UNESCAPED_UNICODE));
            }
        }
    }
    
    // 2. Return if exists in mass-fetched map
    if (isset($SCREEN_MAP[$screenId])) {
        return $SCREEN_MAP[$screenId];
    }
    
    // 3. Fetch specific ID if missing (lazy load for hidden screens like Sala 7)
    if ($screenId) {
        $data = httpGet("https://restapi.helios.pl/api/cinema/" . CINEMA_ID . "/screen/" . $screenId);
        if ($data && isset($data['name'])) {
            $name = $data['name'];
            $SCREEN_MAP[$screenId] = $name;
            
            // Update cache file
            file_put_contents(CACHE_DIR . '/screens_map_v3.json', json_encode($SCREEN_MAP, JSON_UNESCAPED_UNICODE));
            return $name;
        }
    }
    
    return null;
}

function getScreenings($date) {
    $url = "https://restapi.helios.pl/api/cinema/" . CINEMA_ID . "/screening?" 
         . "dateTimeFrom={$date}T00:00:00&dateTimeTo={$date}T23:59:59";
    return httpGet($url) ?: [];
}

function getEvents($date) {
    $url = "https://restapi.helios.pl/api/cinema/" . CINEMA_ID . "/event?" 
         . "dateTimeFrom={$date}T00:00:00&dateTimeTo={$date}T23:59:59";
    return httpGet($url) ?: [];
}


function getMoviesBatch($movieIds) {
    if (empty($movieIds)) return [];
    
    $mh = curl_multi_init();
    if (!$mh) {
        file_put_contents('debug.log', "curl_multi_init failed\n", FILE_APPEND);
        return [];
    }

    $handles = [];
    $results = [];
    
    // Chunking to avoid overly large batches
    $chunks = array_chunk($movieIds, 10);
    
    foreach ($chunks as $chunk) {
        // Prepare handles
        foreach ($chunk as $mid) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://restapi.helios.pl/api/movie/{$mid}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20, // Increased timeout
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FAILONERROR => false
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$mid] = $ch;
        }
        
        // Execute
        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0 && $status == CURLM_OK);
        
        // Collect
        foreach ($chunk as $mid) {
            $ch = $handles[$mid];
            $json = curl_multi_getcontent($ch);
            $info = curl_getinfo($ch);
            
            if ($info['http_code'] !== 200 || empty($json)) {
                file_put_contents('debug.log', "Failed fetching movie $mid: HTTP {$info['http_code']}, Error: " . curl_error($ch) . "\n", FILE_APPEND);
            }
            
            $data = json_decode($json, true);
            if ($data) {
                $results[$mid] = $data;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
    }
    
    curl_multi_close($mh);
    return $results;
}

function scrape($date, $force = false) {
    $cacheFile = CACHE_DIR . "/data_{$date}.json";
    
    if (!$force && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TIME) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    $screenings = getScreenings($date);
    $events = getEvents($date);
    $screens = getScreens();
    
    // Cache danych filmów
    $movieData = loadMovieDataCache();
    $movieDataUpdated = false;
    
    // Pobierz brakujące dane filmów równolegle
    $movieIdsToFetch = [];
    foreach ($screenings as $s) {
        $movieId = $s['movieId'] ?? '';
        if ($movieId && !isset($movieData[$movieId])) {
            $movieIdsToFetch[] = $movieId; // Push unique later
        }
    }
    $movieIdsToFetch = array_unique($movieIdsToFetch);
    
    if (!empty($movieIdsToFetch)) {
        $fetchedMovies = getMoviesBatch($movieIdsToFetch);
        foreach ($fetchedMovies as $mid => $data) {
             $movieData[$mid] = [
                'title' => $data['title'] ?? 'Film',
                'poster' => $data['posters'][0] ?? null,
                'duration' => $data['duration'] ?? null,
                'genres' => array_map(fn($g) => $g['name'], $data['genres'] ?? []),
                'rating' => $data['ratings'][0]['value'] ?? null,
                // New fields
                'imdbRating' => $data['imdbRating'] ?? null,
                'description' => $data['shortDescription'] ?? null,
                'director' => $data['director'] ?? null,
                'cast' => $data['filmCast'] ?? null,
                'country' => $data['country'] ?? null,
                'year' => $data['yearOfProduction'] ?? null,
                'trailers' => $data['trailers'] ?? [],
                'country' => $data['country'] ?? null,
                'year' => $data['yearOfProduction'] ?? null,
                'trailers' => $data['trailers'] ?? [],
                'premiereDate' => $data['premiereDate'] ?? null,
                'isForChildren' => $data['isForChildren'] ?? false
            ];
            $movieDataUpdated = true;
        }
    }
    
    if ($movieDataUpdated) {
        saveMovieDataCache($movieData);
    }
    
    $results = [];
    $seenIds = [];
    
    // 1. Zwykłe seanse
    foreach ($screenings as $s) {
        $id = $s['id'] ?? null;
        if (!$id || isset($seenIds[$id])) continue;
        $seenIds[$id] = true;
        
        $movieId = $s['movieId'] ?? '';
        $movie = $movieData[$movieId] ?? ['title' => 'Film'];
        
        $screenId = $s['screenId'] ?? '';
        $hallName = getScreenName($screenId) ?: 'N/A';
        
        // Dane bezpośrednio z /screening response
        $occupied = $s['audience'] ?? 0;
        $total = $s['maxOccupancy'] ?? 0;
        $free = max(0, $total - $occupied);
        
        preg_match('/T(\d{2}:\d{2})/', $s['screeningTimeFrom'] ?? '', $m);
        
        // Inteligentne szacowanie czasu reklam na podstawie typu filmu
        // Helios usunął prawdziwe dane z API, więc szacujemy na podstawie:
        // - isForChildren: 15 min (krótsze reklamy dla dzieci)
        // - premiera (≤7 dni): 25 min (więcej zwiastunów)
        // - domyślnie: 20 min (jak kinobezreklam.org)
        $adDuration = 20; // domyślna wartość
        
        $isForChildren = $movie['isForChildren'] ?? false;
        $premiereDate = $movie['premiereDate'] ?? null;
        
        if ($isForChildren) {
            // Filmy dla dzieci mają krótszy blok reklamowy
            $adDuration = 15;
        } elseif ($premiereDate) {
            // Sprawdź czy to świeża premiera (≤7 dni od premiery)
            $premiereDateObj = strtotime($premiereDate);
            $screeningDateObj = strtotime($date);
            $daysSincePremiere = ($screeningDateObj - $premiereDateObj) / 86400;
            
            if ($daysSincePremiere >= 0 && $daysSincePremiere <= 7) {
                // Premiery mają dłuższy blok reklamowy (więcej zwiastunów)
                $adDuration = 25;
            } elseif ($daysSincePremiere > 7 && $daysSincePremiere <= 14) {
                // Tydzień po premierze - wciąż trochę dłuższe
                $adDuration = 22;
            }
        }
        
        $results[] = [
            'movieId' => $movieId,
            'movieTitle' => $movie['title'],
            'poster' => $movie['poster'] ?? null,
            'duration' => $movie['duration'] ?? null,
            'adDuration' => $adDuration, // czas trwania bloku reklamowego w minutach
            'genres' => $movie['genres'] ?? [],
            'rating' => $movie['rating'] ?? null,
            // New fields
            'imdbRating' => $movie['imdbRating'] ?? null,
            'description' => $movie['description'] ?? null,
            'director' => $movie['director'] ?? null,
            'cast' => $movie['cast'] ?? null,
            'country' => $movie['country'] ?? null,
            'year' => $movie['year'] ?? null,
            'premiereDate' => $movie['premiereDate'] ?? null,
            'isForChildren' => $movie['isForChildren'] ?? false,
            // Screening info
            'screeningTime' => $m[1] ?? 'N/A',
            'timestamp' => strtotime($date . ' ' . ($m[1] ?? '00:00')),
            'format' => $s['release'] ?? '2D',
            'hall' => $hallName,
            'screenId' => $screenId,
            'stats' => [
                'total' => $total,
                'free' => $free,
                'occupied' => $occupied,
                'occupancyPercent' => $total > 0 ? round(($occupied / $total) * 100) : 0
            ],
            'originalUrl' => "https://bilety.helios.pl/screen/{$id}?cinemaId=" . CINEMA_ID
        ];
    }
    
    // 2. Wydarzenia specjalne - używają audience/maxOccupancy bezpośrednio
    foreach ($events as $e) {
        $id = $e['screeningId'] ?? null;
        if (!$id || isset($seenIds[$id])) continue;
        $seenIds[$id] = true;
        
        $screenId = $e['screenId'] ?? '';
        $hallName = $screens[$screenId] ?? 'N/A';
        
        // Events mają dane bezpośrednio w odpowiedzi (nie w /occupancy)
        $occupied = $e['audience'] ?? 0;
        $total = $e['maxOccupancy'] ?? 0;
        $free = max(0, $total - $occupied);
        
        preg_match('/T(\d{2}:\d{2})/', $e['timeFrom'] ?? '', $m);
        
        $results[] = [
            'movieId' => null,
            'movieTitle' => $e['name'] ?? 'Wydarzenie specjalne',
            'poster' => $e['posters'][0] ?? null,
            'duration' => $e['duration'] ?? null,
            'adDuration' => 10, // wydarzenia specjalne mają krótszy blok reklamowy
            'genres' => array_map(fn($g) => $g['name'] ?? '', $e['genres'] ?? []),
            'rating' => null,
            'screeningTime' => $m[1] ?? 'N/A',
            'timestamp' => strtotime($date . ' ' . ($m[1] ?? '00:00')),
            'screenId' => $screenId,
            'format' => $e['release'] ?? '2D',
            'hall' => $hallName,
            'isSpecialEvent' => true,
            'stats' => [
                'total' => $total,
                'free' => $free,
                'occupied' => $occupied,
                'occupancyPercent' => $total > 0 ? round(($occupied / $total) * 100) : 0
            ],
            'originalUrl' => "https://bilety.helios.pl/screen/{$id}?cinemaId=" . CINEMA_ID
        ];
    }
    
    // Grupuj po filmach
    $movies = [];
    foreach ($results as $r) {
        $title = $r['movieTitle'];
        if (!isset($movies[$title])) {
            $movies[$title] = [
                'movieTitle' => $title,
                'movieId' => $r['movieId'],
                'poster' => $r['poster'],
                'duration' => $r['duration'],
                'genres' => $r['genres'],
                'rating' => $r['rating'],
                // New fields
                'imdbRating' => $r['imdbRating'] ?? null,
                'description' => $r['description'] ?? null,
                'director' => $r['director'] ?? null,
                'country' => $r['country'] ?? null,
                'year' => $r['year'] ?? null,
                'premiereDate' => $r['premiereDate'] ?? null,
                'isForChildren' => $r['isForChildren'] ?? false,
                'isSpecialEvent' => $r['isSpecialEvent'] ?? false,
                'screenings' => [],
                // Stats for popularity
                '_totalOccupied' => 0,
                '_totalSeats' => 0
            ];
        }
        $movies[$title]['screenings'][] = [
            'time' => $r['screeningTime'],
            'timestamp' => $r['timestamp'],
            'format' => $r['format'],
            'hall' => $r['hall'],
            'screenId' => $r['screenId'],
            'adDuration' => $r['adDuration'] ?? 20, // czas trwania reklam
            'stats' => $r['stats'],
            'url' => $r['originalUrl']
        ];
        // Accumulate for popularity
        $movies[$title]['_totalOccupied'] += $r['stats']['occupied'] ?? 0;
        $movies[$title]['_totalSeats'] += $r['stats']['total'] ?? 0;
    }
    
    // Calculate popularity and clean up temp fields
    foreach ($movies as &$m) {
        usort($m['screenings'], fn($a, $b) => strcmp($a['time'], $b['time']));
        // Calculate popularity percentage
        $m['popularity'] = $m['_totalSeats'] > 0 
            ? round(($m['_totalOccupied'] / $m['_totalSeats']) * 100) 
            : 0;
        unset($m['_totalOccupied'], $m['_totalSeats']);
    }
    
    // Sort by popularity (highest first)
    uasort($movies, fn($a, $b) => $b['popularity'] <=> $a['popularity']);
    
    // HALLS STATUS (Status Sal)
    $hallsStatus = [];
    $now = time();
    foreach ($screens as $sId => $sName) {
        // Find screenings for this screen
        $hallScreenings = array_filter($results, fn($r) => ($r['screenId'] ?? '') == $sId);
        
        // Sort by time
        usort($hallScreenings, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        
        $current = null;
        $next = null;
        
        foreach ($hallScreenings as $s) {
            $startTs = $s['timestamp'];
            $duration = ($s['duration'] ?? 120) * 60; // Duration in seconds
            $endTs = $startTs + $duration;
            
            if ($startTs <= $now && $endTs > $now) {
                // Currently playing
                $current = $s;
            } elseif ($startTs > $now && $next === null) {
                // First upcoming screening
                $next = $s;
                break; // We have both current and next
            }
        }
        
        $hallsStatus[] = [
            'id' => $sId,
            'name' => $sName,
            'currentScreening' => $current ? [
                'title' => $current['movieTitle'],
                'time' => $current['screeningTime'],
                'timestamp' => $current['timestamp'],
                'poster' => $current['poster'],
                'duration' => $current['duration'],
                'adDuration' => $current['adDuration'] ?? 20,
                'occupied' => $current['stats']['occupied'] ?? null,
                'total' => $current['stats']['total'] ?? null
            ] : null,
            'nextScreening' => $next ? [
                'title' => $next['movieTitle'],
                'time' => $next['screeningTime'],
                'timestamp' => $next['timestamp'],
                'poster' => $next['poster'],
                'duration' => $next['duration'],
                'adDuration' => $next['adDuration'] ?? 20,
                'occupied' => $next['stats']['occupied'] ?? null,
                'total' => $next['stats']['total'] ?? null
            ] : null
        ];
    }
    
    // Sort naturally (Sala 1, Sala 2...)
    usort($hallsStatus, fn($a, $b) => strnatcmp($a['name'], $b['name']));

    $data = [
        'cinema' => 'Helios Łódź',
        'date' => $date,
        'scrapedAt' => date('c'),
        'totalMovies' => count($movies),
        'totalScreenings' => count($results),
        'movies' => array_values($movies),
        'halls' => $hallsStatus
    ];
    
    file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $data;
}

// Include Advanced Predictor for intelligent predictions
require_once __DIR__ . '/AdvancedPredictor.php';

function addPredictions($data) {
    // Use AdvancedPredictor with all features enabled
    $predictor = new AdvancedPredictor(__DIR__ . '/history', [
        // Weather API key can be set via environment variable OPENWEATHER_API_KEY
        // 'weatherApiKey' => 'your-api-key-here'
    ]);
    
    $stats = $predictor->getModelStats();
    
    // Check if we have enough data (any data, fallback handles missing day types)
    if (($stats['totalDays'] ?? 0) < 1) {
        return $data; // No prediction available
    }
    
    $dayType = $predictor->getDayType($data['date']);
    
    // Build current totals for Bayesian update
    $currentTotals = [
        'totals' => ['occupied' => 0, 'total' => 0],
        'hourly' => []
    ];
    
    foreach ($data['movies'] as $movie) {
        foreach ($movie['screenings'] as $s) {
            $currentTotals['totals']['occupied'] += $s['stats']['occupied'] ?? 0;
            $currentTotals['totals']['total'] += $s['stats']['total'] ?? 0;
            
            // Aggregate by hour for Bayesian update
            $hour = (int)explode(':', $s['time'])[0];
            if (!isset($currentTotals['hourly'][$hour])) {
                $currentTotals['hourly'][$hour] = ['occupied' => 0, 'total' => 0, 'screenings' => 0];
            }
            $currentTotals['hourly'][$hour]['occupied'] += $s['stats']['occupied'] ?? 0;
            $currentTotals['hourly'][$hour]['total'] += $s['stats']['total'] ?? 0;
            $currentTotals['hourly'][$hour]['screenings']++;
        }
    }
    
    // Only apply Bayesian update for TODAY - not for future dates!
    // For future dates, we don't have current data to base corrections on
    // BUT we still need to pass total seats for accurate percentage calculation
    $isToday = ($data['date'] === date('Y-m-d'));
    
    // Always pass at least the total seats for this day (for accurate percentage)
    $dataToPass = $isToday ? $currentTotals : [
        'totals' => [
            'occupied' => 0,  // No current occupied for future dates
            'total' => $currentTotals['totals']['total'] ?? 0  // Actual seats for this day
        ]
    ];
    
    // Get global prediction with all multipliers applied
    $prediction = $predictor->predict($data['date'], $dataToPass);
    
    // Add per-screening predictions with movie context
    foreach ($data['movies'] as &$movie) {
        // Build movie context for genre/premiere-aware predictions
        $movieContext = [
            'genres' => $movie['genres'] ?? [],
            'imdbRating' => isset($movie['imdbRating']) ? (float)$movie['imdbRating'] : null,
            'premiereDate' => $movie['premiereDate'] ?? null,
            'isForChildren' => $movie['isForChildren'] ?? false,
            'date' => $data['date']
        ];
        
        foreach ($movie['screenings'] as &$screening) {
            $hour = (int)explode(':', $screening['time'])[0];
            $screeningPred = $predictor->predictScreening(
                $hour,
                $dayType,
                $screening['stats']['occupied'] ?? 0,
                $screening['stats']['total'] ?? 0,
                $movieContext  // Pass movie context for smart predictions
            );
            if ($screeningPred) {
                $screening['prediction'] = $screeningPred;
            }
        }
    }
    
    $data['prediction'] = $prediction;
    $data['predictionStats'] = $stats;
    
    return $data;
}

/**
 * Get Coming Soon movies by parsing helios.pl/zapowiedzi HTML
 * Fully dynamic - uses HTML article structure to link titles/dates securely
 */
function getComingSoon() {
    $cacheFile = CACHE_DIR . '/comingsoon.json';
    
    // Cache for 6 hours
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 21600) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    // Fetch the zapowiedzi page (single request)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://helios.pl/lodz/kino-helios/filmy/zapowiedzi',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        return ['cinema' => 'Helios Łódź', 'error' => 'Failed to fetch page', 'comingSoon' => []];
    }
    
    $comingSoon = [];
    $seenTitles = [];
    $todayTimestamp = strtotime(date('Y-m-d'));
    
    // Use regex to find each movie article block
    // Pattern matches <article class="... rB_XOJ ..."> ... </article>
    // The class 'rB_XOJ' seems stable for movie cards based on inspection
    preg_match_all('/<article[^>]*class="[^"]*rB_XOJ[^"]*"[^>]*>(.*?)<\/article>/s', $html, $articleMatches);
    
    foreach ($articleMatches[1] as $block) {
        // 1. Extract Slug and Title from primary link
        // <a href="/lodz/kino-helios/filmy/song-sung-blue-4287" title="Song Sung Blue" ...>
        if (!preg_match('/href="\/[^\/]+\/[^\/]+\/filmy\/([a-z0-9-]+-\d+)"[^>]*title="([^"]+)"/', $block, $linkMatch)) {
            // Fallback: try finding title in H2 if link title missing
            if (!preg_match('/href="\/[^\/]+\/[^\/]+\/filmy\/([a-z0-9-]+-\d+)"/', $block, $slugUnsafe)) continue;
            $fullSlug = $slugUnsafe[1];
            
            if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/', $block, $h2Match)) {
                $title = trim($h2Match[1]);
            } else {
                continue; // Cannot determine title
            }
        } else {
            $fullSlug = $linkMatch[1];
            $title = html_entity_decode(trim($linkMatch[2]));
        }
        
        // Skip duplicates
        if (isset($seenTitles[$title])) continue;
        $seenTitles[$title] = true;
        
        // 2. Extract Date from <time datetime="...">
        // <time datetime="2025-12-26T01:00:00+01:00" ...>
        $premiereDate = null;
        if (preg_match('/<time[^>]*datetime="(\d{4}-\d{2}-\d{2})/', $block, $dateMatch)) {
            $premiereDate = $dateMatch[1];
        }
        
        // Skip past movies
        if ($premiereDate && strtotime($premiereDate) <= $todayTimestamp) {
            continue;
        }
        
        // 3. Extract Poster
        // <img ... src="https://img.helios.pl/..." ...>
        $poster = null;
        if (preg_match('/<picture>.*?<img[^>]*src="([^"]+)"/s', $block, $imgMatch)) {
            $poster = $imgMatch[1];
            // Ensure https
            if (strpos($poster, '//') === 0) $poster = 'https:' . $poster;
            // Fix unicode escapes if any
            $poster = str_replace('\\u002F', '/', $poster);
        }
        
        $comingSoon[] = [
            'title' => $title,
            'slug' => $fullSlug,
            'premiereDate' => $premiereDate, 
            'poster' => $poster,
            'onSale' => false, 
            'genres' => [],
            'duration' => null,
            'description' => null,
            'director' => null,
            'isForChildren' => false
        ];
    }
    
    // Sort by premiere date
    usort($comingSoon, function($a, $b) {
        if (!$a['premiereDate'] && !$b['premiereDate']) return 0;
        if (!$a['premiereDate']) return 1;
        if (!$b['premiereDate']) return -1;
        return strcmp($a['premiereDate'], $b['premiereDate']);
    });
    
    $result = [
        'cinema' => 'Helios Łódź',
        'generatedAt' => date('c'),
        'count' => count($comingSoon),
        'comingSoon' => $comingSoon
    ];
    
    file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $result;
}

/**
 * Get Movie Marathons from helios.pl/maratony-filmowe
 * Returns actual marathon events with dates (not categories)
 */
function getMarathons() {
    $cacheFile = CACHE_DIR . '/marathons.json';
    
    // Cache for 6 hours
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 21600) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    // Fetch marathons page
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://helios.pl/lodz/kino-helios/maratony-filmowe',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        return ['cinema' => 'Helios Łódź', 'error' => 'Failed to fetch page', 'marathons' => []];
    }
    
    $marathons = [];
    $today = date('Y-m-d');
    $todayTimestamp = strtotime($today);
    
    // Known marathon events with correct data
    $marathonEvents = [
        'zestaw-1-filmowy-sylwester-25' => [
            'title' => 'Zestaw 1 Filmowy Sylwester 25',
            'date' => '2025-12-31',
            'time' => '21:30',
            'description' => 'Zapraszamy do Sylwestrowej zabawy w towarzystwie gwiazd kina!',
            'poster' => 'https://img.helios.pl/pliki/wydarzenie/zestaw-1-filmowy-sylwester-25/zestaw-1-filmowy-sylwester-25-plakat-762.jpg'
        ],
        'zestaw-2-filmowy-sylwester-25' => [
            'title' => 'Zestaw 2 Filmowy Sylwester 25',
            'date' => '2025-12-31',
            'time' => '21:30',
            'description' => 'Zapraszamy do Sylwestrowej zabawy w towarzystwie gwiazd kina!',
            'poster' => 'https://img.helios.pl/pliki/wydarzenie/zestaw-2-filmowy-sylwester-25/zestaw-2-filmowy-sylwester-25-plakat-125.jpg'
        ],
    ];
    
    // Extract marathon event slugs from page
    preg_match_all('/\/lodz\/kino-helios\/maratony-filmowe\/([a-z0-9-]+-\d+)/', $html, $slugMatches);
    $eventSlugs = array_unique($slugMatches[1] ?? []);
    
    // Also look for event titles in the HTML
    preg_match_all('/"(Zestaw \d+ Filmowy[^"]+)"/', $html, $titleMatches);
    
    // Build marathon list from known events
    foreach ($marathonEvents as $slugBase => $data) {
        // Check if this event's slug exists on page
        $found = false;
        foreach ($eventSlugs as $slug) {
            if (strpos($slug, str_replace('-25', '', $slugBase)) !== false) {
                $found = true;
                break;
            }
        }
        
        // Only add if event is in the future
        if (strtotime($data['date']) >= $todayTimestamp) {
            $marathons[] = [
                'title' => $data['title'],
                'slug' => $slugBase,
                'poster' => $data['poster'],
                'eventDate' => $data['date'],
                'time' => $data['time'],
                'description' => $data['description'],
                'type' => 'marathon'
            ];
        }
    }
    
    // Sort by date (nearest first)
    usort($marathons, function($a, $b) {
        return strcmp($a['eventDate'], $b['eventDate']);
    });
    
    $result = [
        'cinema' => 'Helios Łódź',
        'generatedAt' => date('c'),
        'count' => count($marathons),
        'marathons' => $marathons
    ];
    
    file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $result;
}

// Helper to rewrite poster URL to local proxy
function proxyUrl($url) {
    if (!$url) return null;
    $myself = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    return $myself . '?action=image&url=' . urlencode($url);
}

// Main execution block - only run if accessed directly (not included)
if (!defined('API_MODE_INTERNAL')) {

// Handle different actions
$action = $_GET['action'] ?? 'schedule';

if ($action === 'history_list') {
    $files = glob(__DIR__ . '/history/*.json');
    $dates = [];
    foreach ($files as $file) {
        $dates[] = basename($file, '.json');
    }
    rsort($dates); // Newest first
    echo json_encode(['dates' => $dates]);
    exit;
}

if ($action === 'history_data') {
    $date = $_GET['date'] ?? '';
    // Basic validation YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid date format']));
    }
    
    $file = __DIR__ . "/history/{$date}.json";
    if (file_exists($file)) {
        header('Content-Type: application/json');
        echo file_get_contents($file);
    } else {
        http_response_code(404);
        die(json_encode(['error' => 'History not found']));
    }
    exit;
}

// =====================================================
// MOVIE LIBRARY API - Full library of all movies with stats
// =====================================================
if ($action === 'movie_library') {
    $historyDir = __DIR__ . '/history';
    $files = glob($historyDir . '/*.json');
    
    $movies = [];
    $cacheFile = CACHE_DIR . '/movie_library.json';
    
    // Cache for 30 minutes
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 1800) {
        echo file_get_contents($cacheFile);
        exit;
    }
    
    foreach ($files as $file) {
        $filename = basename($file, '.json');
        if ($filename === 'insights') continue; // Skip insights file
        
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['movies'])) continue;
        
        $date = $data['date'] ?? $filename;
        
        foreach ($data['movies'] as $movie) {
            $title = $movie['title'] ?? 'Unknown';
            $titleKey = mb_strtolower(trim($title));
            
            // Initialize movie entry if new
            if (!isset($movies[$titleKey])) {
                $movies[$titleKey] = [
                    'title' => $title,
                    'poster' => $movie['poster'] ?? null,
                    'genres' => $movie['genres'] ?? [],
                    'director' => $movie['director'] ?? null,
                    'country' => $movie['country'] ?? null,
                    'year' => $movie['year'] ?? null,
                    'imdbRating' => $movie['imdbRating'] ?? null,
                    'description' => $movie['description'] ?? null,
                    'premiereDate' => $movie['premiereDate'] ?? null,
                    'isForChildren' => $movie['isForChildren'] ?? false,
                    'duration' => $movie['duration'] ?? null,
                    // Stats
                    'totalViewers' => 0,
                    'totalSeats' => 0,
                    'totalScreenings' => 0,
                    'daysInCinema' => 0,
                    'firstDay' => $date,
                    'lastDay' => $date,
                    'dailyData' => []
                ];
            }
            
            // Aggregate stats
            $occupied = $movie['totalOccupied'] ?? 0;
            $seats = $movie['totalSeats'] ?? 0;
            $screenings = count($movie['screenings'] ?? []);
            
            $movies[$titleKey]['totalViewers'] += $occupied;
            $movies[$titleKey]['totalSeats'] += $seats;
            $movies[$titleKey]['totalScreenings'] += $screenings;
            $movies[$titleKey]['daysInCinema']++;
            
            // Track date range
            if ($date < $movies[$titleKey]['firstDay']) {
                $movies[$titleKey]['firstDay'] = $date;
            }
            if ($date > $movies[$titleKey]['lastDay']) {
                $movies[$titleKey]['lastDay'] = $date;
            }
            
            // Store daily data for trend
            $movies[$titleKey]['dailyData'][] = [
                'date' => $date,
                'occupied' => $occupied,
                'seats' => $seats,
                'screenings' => $screenings,
                'percent' => $seats > 0 ? round(($occupied / $seats) * 100) : 0
            ];
            
            // Update poster/metadata if missing
            if (!$movies[$titleKey]['poster'] && !empty($movie['poster'])) {
                $movies[$titleKey]['poster'] = $movie['poster'];
            }
            if (!$movies[$titleKey]['imdbRating'] && !empty($movie['imdbRating'])) {
                $movies[$titleKey]['imdbRating'] = $movie['imdbRating'];
            }
        }
    }
    
    // Calculate derived stats and clean up
    $result = [];
    foreach ($movies as $key => $m) {
        // Average occupancy
        $m['avgOccupancy'] = $m['totalSeats'] > 0 
            ? round(($m['totalViewers'] / $m['totalSeats']) * 100, 1) 
            : 0;
        
        // Calculate trend (compare first half to second half)
        $dailyData = $m['dailyData'];
        usort($dailyData, fn($a, $b) => strcmp($a['date'], $b['date']));
        
        if (count($dailyData) >= 2) {
            $midpoint = (int)floor(count($dailyData) / 2);
            $firstHalf = array_slice($dailyData, 0, $midpoint);
            $secondHalf = array_slice($dailyData, $midpoint);
            
            $firstAvg = count($firstHalf) > 0 
                ? array_sum(array_column($firstHalf, 'occupied')) / count($firstHalf) 
                : 0;
            $secondAvg = count($secondHalf) > 0 
                ? array_sum(array_column($secondHalf, 'occupied')) / count($secondHalf) 
                : 0;
            
            if ($firstAvg > 0) {
                $trendPct = round((($secondAvg - $firstAvg) / $firstAvg) * 100);
                $m['trend'] = $trendPct;
                $m['trendDirection'] = $trendPct > 10 ? 'up' : ($trendPct < -10 ? 'down' : 'stable');
            } else {
                $m['trend'] = 0;
                $m['trendDirection'] = 'stable';
            }
        } else {
            $m['trend'] = 0;
            $m['trendDirection'] = 'new';
        }
        
        // Poster Fallback Strategy:
        // 1. Check if we have it locally (already likely empty if we are here)
        // 2. Fetch from Helios API (most reliable, matches cinema)
        // 3. Fetch from Filmweb (fallback)
        if (empty($m['poster'])) {
            // Try Helios API first if we have an ID
            if (!empty($m['id'])) {
                $m['poster'] = getHeliosPoster($m['id']);
            }
            
            // If still empty, try Filmweb
            if (empty($m['poster'])) {
                $m['poster'] = getFilmwebPoster($m['title']);
            }
        }
        
        // Remove dailyData from list view (too large)
        unset($m['dailyData']);
        
        $result[] = $m;
    }
    
    // Sort by total viewers (most popular first)
    usort($result, fn($a, $b) => $b['totalViewers'] <=> $a['totalViewers']);
    
    $response = [
        'success' => true,
        'count' => count($result),
        'movies' => $result,
        'generatedAt' => date('c')
    ];
    
    // Cache result
    @file_put_contents($cacheFile, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// MOVIE DETAIL API - Full data for a specific movie
// =====================================================
if ($action === 'movie_detail') {
    $title = $_GET['title'] ?? '';
    if (!$title) {
        echo json_encode(['success' => false, 'error' => 'Title required']);
        exit;
    }
    
    $historyDir = __DIR__ . '/history';
    $files = glob($historyDir . '/*.json');
    
    $titleLower = mb_strtolower(trim($title));
    $movieData = null;
    $dailyHistory = [];
    $hourlyDistribution = [];
    $weekdayStats = [
        'monday' => ['viewers' => 0, 'screenings' => 0, 'days' => 0],
        'tuesday' => ['viewers' => 0, 'screenings' => 0, 'days' => 0],
        'wednesday' => ['viewers' => 0, 'screenings' => 0, 'days' => 0],
        'thursday' => ['viewers' => 0, 'screenings' => 0, 'days' => 0],
        'friday' => ['viewers' => 0, 'screenings' => 0, 'days' => 0],
        'saturday' => ['viewers' => 0, 'screenings' => 0, 'days' => 0],
        'sunday' => ['viewers' => 0, 'screenings' => 0, 'days' => 0]
    ];
    $genreMovies = [];
    
    foreach ($files as $file) {
        $filename = basename($file, '.json');
        if ($filename === 'insights') continue;
        
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['movies'])) continue;
        
        $date = $data['date'] ?? $filename;
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        
        foreach ($data['movies'] as $movie) {
            $movieTitle = $movie['title'] ?? '';
            $movieTitleLower = mb_strtolower(trim($movieTitle));
            
            // Match movie
            if ($movieTitleLower === $titleLower || 
                strpos($movieTitleLower, $titleLower) !== false ||
                strpos($titleLower, $movieTitleLower) !== false) {
                
                // Store metadata (first match)
                if (!$movieData) {
                    $movieData = [
                        'title' => $movie['title'],
                        'poster' => $movie['poster'] ?? null,
                        'genres' => $movie['genres'] ?? [],
                        'director' => $movie['director'] ?? null,
                        'country' => $movie['country'] ?? null,
                        'year' => $movie['year'] ?? null,
                        'imdbRating' => $movie['imdbRating'] ?? null,
                        'description' => $movie['description'] ?? null,
                        'premiereDate' => $movie['premiereDate'] ?? null,
                        'isForChildren' => $movie['isForChildren'] ?? false,
                        'duration' => $movie['duration'] ?? null
                    ];
                } else {
                    // Update missing metadata
                    if (!$movieData['poster'] && !empty($movie['poster'])) {
                        $movieData['poster'] = $movie['poster'];
                    }
                    if (!$movieData['imdbRating'] && !empty($movie['imdbRating'])) {
                        $movieData['imdbRating'] = $movie['imdbRating'];
                    }
                }
                
                // Daily stats
                $occupied = $movie['totalOccupied'] ?? 0;
                $seats = $movie['totalSeats'] ?? 0;
                $screenings = $movie['screenings'] ?? [];
                
                $dailyHistory[] = [
                    'date' => $date,
                    'dayOfWeek' => $dayOfWeek,
                    'occupied' => $occupied,
                    'seats' => $seats,
                    'percent' => $seats > 0 ? round(($occupied / $seats) * 100) : 0,
                    'screenings' => count($screenings)
                ];
                
                // Weekday stats
                $weekdayStats[$dayOfWeek]['viewers'] += $occupied;
                $weekdayStats[$dayOfWeek]['screenings'] += count($screenings);
                $weekdayStats[$dayOfWeek]['days']++;
                
                // Hourly distribution
                foreach ($screenings as $s) {
                    $time = $s['time'] ?? '';
                    $hour = (int)explode(':', $time)[0];
                    if ($hour >= 10 && $hour <= 23) {
                        if (!isset($hourlyDistribution[$hour])) {
                            $hourlyDistribution[$hour] = ['viewers' => 0, 'screenings' => 0];
                        }
                        $hourlyDistribution[$hour]['viewers'] += $s['stats']['occupied'] ?? 0;
                        $hourlyDistribution[$hour]['screenings']++;
                    }
                }
            }
            
            // Collect genre comparison data (if genres match)
            if ($movieData && !empty($movieData['genres'])) {
                $movieGenres = $movie['genres'] ?? [];
                $hasMatchingGenre = count(array_intersect($movieData['genres'], $movieGenres)) > 0;
                
                if ($hasMatchingGenre && $movieTitleLower !== $titleLower) {
                    $genreKey = mb_strtolower($movieTitle);
                    if (!isset($genreMovies[$genreKey])) {
                        $genreMovies[$genreKey] = [
                            'title' => $movieTitle,
                            'totalViewers' => 0,
                            'totalSeats' => 0,
                            'days' => 0,
                            'genres' => $movieGenres
                        ];
                    }
                    $genreMovies[$genreKey]['totalViewers'] += $movie['totalOccupied'] ?? 0;
                    $genreMovies[$genreKey]['totalSeats'] += $movie['totalSeats'] ?? 0;
                    $genreMovies[$genreKey]['days']++;
                }
            }
        }
    }
    
    if (!$movieData) {
        echo json_encode(['success' => false, 'error' => 'Movie not found']);
        exit;
    }
    
    // Sort daily history by date
    usort($dailyHistory, fn($a, $b) => strcmp($a['date'], $b['date']));
    
    // Calculate totals
    $totalViewers = array_sum(array_column($dailyHistory, 'occupied'));
    $totalSeats = array_sum(array_column($dailyHistory, 'seats'));
    $totalScreenings = array_sum(array_column($dailyHistory, 'screenings'));
    $avgOccupancy = $totalSeats > 0 ? round(($totalViewers / $totalSeats) * 100, 1) : 0;
    
    // Sort hourly distribution
    ksort($hourlyDistribution);
    
    // Calculate peak hour
    $peakHour = null;
    $peakViewers = 0;
    foreach ($hourlyDistribution as $hour => $data) {
        $avgPerScreening = $data['screenings'] > 0 ? $data['viewers'] / $data['screenings'] : 0;
        if ($avgPerScreening > $peakViewers) {
            $peakViewers = $avgPerScreening;
            $peakHour = $hour;
        }
    }
    
    // Calculate weekday averages
    $weekdayAverages = [];
    foreach ($weekdayStats as $day => $stats) {
        $weekdayAverages[$day] = $stats['days'] > 0 
            ? round($stats['viewers'] / $stats['days']) 
            : 0;
    }
    
    // Similar movies in genre (top 5)
    $similarMovies = [];
    foreach ($genreMovies as $gm) {
        $gm['avgOccupancy'] = $gm['totalSeats'] > 0 
            ? round(($gm['totalViewers'] / $gm['totalSeats']) * 100) 
            : 0;
        $similarMovies[] = $gm;
    }
    usort($similarMovies, fn($a, $b) => $b['totalViewers'] <=> $a['totalViewers']);
    $similarMovies = array_slice($similarMovies, 0, 5);
    
    echo json_encode([
        'success' => true,
        'movie' => $movieData,
        'stats' => [
            'totalViewers' => $totalViewers,
            'totalSeats' => $totalSeats,
            'totalScreenings' => $totalScreenings,
            'avgOccupancy' => $avgOccupancy,
            'daysInCinema' => count($dailyHistory),
            'firstDay' => $dailyHistory[0]['date'] ?? null,
            'lastDay' => end($dailyHistory)['date'] ?? null,
            'peakHour' => $peakHour ? sprintf('%02d:00', $peakHour) : null,
            'peakHourAvgViewers' => round($peakViewers)
        ],
        'dailyHistory' => $dailyHistory,
        'hourlyDistribution' => $hourlyDistribution,
        'weekdayAverages' => $weekdayAverages,
        'similarMovies' => $similarMovies,
        'generatedAt' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// AI Insights endpoint
if ($action === 'insights') {
    require_once __DIR__ . '/AIInsightsLogger.php';
    
    $logger = new AIInsightsLogger(__DIR__ . '/history');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    
    // If generate flag is set, try to generate new insights
    if (isset($_GET['generate'])) {
        $date = $_GET['date'] ?? date('Y-m-d', strtotime('-1 day'));
        
        // Load historical data for this date
        $histFile = __DIR__ . "/history/{$date}.json";
        if (file_exists($histFile)) {
            $actualData = json_decode(file_get_contents($histFile), true);
            
            // Generate prediction for that date (simulating blind prediction)
            $predictor = new AdvancedPredictor(__DIR__ . '/history');
            $predicted = $predictor->predict($date, null);
            
            // Generate insights
            $logger->generateDailyInsights($date, $predicted, $actualData);
        }
    }
    
    // If generate_report flag is set, generate performance report
    if (isset($_GET['generate_report'])) {
        $reportType = $_GET['generate_report']; // 'weekly' or 'monthly'
        $report = $logger->generatePerformanceReport($reportType);
        if ($report) {
            echo json_encode([
                'success' => true,
                'message' => "Wygenerowano raport: {$report['title']}",
                'report' => $report
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Nie udało się wygenerować raportu (za mało danych lub już istnieje)'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    $insights = $logger->getInsights($limit);
    $stats = $logger->getLearningStats();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'insights' => $insights,
        'generatedAt' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// All Factors endpoint - returns complete list of factors with weights for AI Thoughts panel
if ($action === 'all_factors') {
    require_once __DIR__ . '/FactorLearningSystem.php';
    
    $fls = new FactorLearningSystem(CACHE_DIR);
    $learnedMultipliers = $fls->getLearnedMultipliers();
    $stats = $fls->getStats();
    
    // Factor metadata for better UI display
    $factorMeta = [
        'holiday' => [
            'icon' => 'celebration',
            'name' => 'Święta',
            'description' => 'Wpływ dni świątecznych na frekwencję',
            'keys' => [
                'christmas' => 'Boże Narodzenie',
                'christmas_eve' => 'Wigilia',
                'new_years_eve' => 'Sylwester',
                'new_year' => 'Nowy Rok',
                'valentines' => 'Walentynki',
                'childrens_day' => 'Dzień Dziecka',
                'post_christmas' => 'Tydzień po świętach'
            ]
        ],
        'weather' => [
            'icon' => 'cloud',
            'name' => 'Pogoda',
            'description' => 'Wpływ warunków atmosferycznych',
            'keys' => [
                'rain_light' => 'Lekki deszcz',
                'rain_moderate' => 'Umiarkowany deszcz',
                'rain_heavy' => 'Intensywny deszcz',
                'cold' => 'Zimno (<5°C)',
                'very_cold' => 'Bardzo zimno (<0°C)',
                'hot_sunny' => 'Ciepło i słonecznie',
                'hot' => 'Gorąco (>25°C)',
                'very_hot' => 'Upał (>30°C)',
                'perfect_outdoor' => 'Idealna pogoda na zewnątrz'
            ]
        ],
        'season' => [
            'icon' => 'spa',
            'name' => 'Pora roku',
            'description' => 'Sezonowe wahania frekwencji',
            'keys' => [
                'summer' => 'Lato',
                'winter' => 'Zima',
                'spring' => 'Wiosna',
                'autumn' => 'Jesień'
            ]
        ],
        'school' => [
            'icon' => 'school',
            'name' => 'Szkoła',
            'description' => 'Wpływ kalendarza szkolnego',
            'keys' => [
                'holidays' => 'Ferie/wakacje',
                'regular' => 'Rok szkolny'
            ]
        ],
        'payday' => [
            'icon' => 'payments',
            'name' => 'Dzień wypłaty',
            'description' => 'Cykl wypłat i wydatków',
            'keys' => [
                'after_payday' => 'Po wypłacie (1-5 dnia)',
                'before_payday' => 'Przed wypłatą (25-31 dnia)',
                'regular' => 'Środek miesiąca'
            ]
        ],
        'sports' => [
            'icon' => 'sports_soccer',
            'name' => 'Sport',
            'description' => 'Wpływ wydarzeń sportowych',
            'keys' => [
                'national_team' => 'Mecz reprezentacji Polski',
                'local_match' => 'Mecz lokalny (np. ŁKS, Widzew)',
                'other_ekstraklasa' => 'Inne mecze Ekstraklasy'
            ]
        ],
        'day_type' => [
            'icon' => 'event',
            'name' => 'Typ dnia',
            'description' => 'Podstawowy cykl tygodniowy',
            'keys' => [
                'weekend' => 'Weekend (Pt-Nd)',
                'workday' => 'Dzień roboczy',
                'tuesday' => 'Wtorek (promocje)'
            ]
        ]
    ];
    
    // Build structured response
    $factors = [];
    foreach ($learnedMultipliers as $type => $keys) {
        $meta = $factorMeta[$type] ?? [
            'icon' => 'help',
            'name' => ucfirst($type),
            'description' => '',
            'keys' => []
        ];
        
        $factorData = [
            'type' => $type,
            'icon' => $meta['icon'],
            'name' => $meta['name'],
            'description' => $meta['description'],
            'values' => []
        ];
        
        foreach ($keys as $key => $data) {
            $factorData['values'][] = [
                'key' => $key,
                'label' => $meta['keys'][$key] ?? ucfirst(str_replace('_', ' ', $key)),
                'default' => $data['default'],
                'current' => $data['current'],
                'samples' => $data['samples'],
                'deviation' => $data['deviation'],
                'isLearned' => $data['samples'] > 0,
                'impact' => $data['current'] > 1 
                    ? '+' . round(($data['current'] - 1) * 100) . '%'
                    : round(($data['current'] - 1) * 100) . '%'
            ];
        }
        
        $factors[] = $factorData;
    }
    
    echo json_encode([
        'success' => true,
        'factors' => $factors,
        'stats' => $stats,
        'generatedAt' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Calendar Data endpoint - returns occupancy summary for each day in a month
if ($action === 'calendar_data') {
    $month = $_GET['month'] ?? date('Y-m'); // Format: YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }
    
    $historyDir = __DIR__ . '/history';
    $days = [];
    
    // Get all days in the month
    $startDate = new DateTime($month . '-01');
    $endDate = clone $startDate;
    $endDate->modify('last day of this month');
    
    $current = clone $startDate;
    while ($current <= $endDate) {
        $dateStr = $current->format('Y-m-d');
        $file = $historyDir . '/' . $dateStr . '.json';
        
        $dayData = [
            'date' => $dateStr,
            'dayOfWeek' => (int)$current->format('w'),
            'hasData' => false,
            'occupancy' => 0,
            'screenings' => 0
        ];
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['totals'])) {
                $dayData['hasData'] = true;
                $dayData['occupancy'] = $data['totals']['percent'] ?? 0;
                $dayData['screenings'] = $data['totals']['screenings'] ?? 0;
                $dayData['occupied'] = $data['totals']['occupied'] ?? 0;
                $dayData['total'] = $data['totals']['total'] ?? 0;
            }
        }
        
        $days[] = $dayData;
        $current->modify('+1 day');
    }
    
    echo json_encode([
        'success' => true,
        'month' => $month,
        'days' => $days
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Heatmap Data endpoint - returns 7x24 hour/day visitor counts matrix
if ($action === 'heatmap_data') {
    $historyDir = __DIR__ . '/history';
    $files = glob($historyDir . '/*.json');
    
    // Initialize 7x24 matrix (days 0-6 x hours 0-23)
    $matrix = [];
    $counts = [];
    for ($day = 0; $day < 7; $day++) {
        $matrix[$day] = array_fill(0, 24, 0);
        $counts[$day] = array_fill(0, 24, 0);
    }
    
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['hourly']) || !isset($data['dayOfWeek'])) continue;
        
        $dayOfWeek = (int)$data['dayOfWeek'];
        
        foreach ($data['hourly'] as $hour => $hourData) {
            $hour = (int)$hour;
            if ($hour < 0 || $hour > 23) continue;
            
            // Use visitor count instead of percentage
            $visitors = isset($hourData['occupied']) ? (int)$hourData['occupied'] : 0;
            
            $matrix[$dayOfWeek][$hour] += $visitors;
            $counts[$dayOfWeek][$hour]++;
        }
    }
    
    // Calculate averages and find max for scaling
    $heatmap = [];
    $maxVisitors = 0;
    $dayNames = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
    
    for ($day = 0; $day < 7; $day++) {
        $heatmap[$day] = [
            'dayName' => $dayNames[$day],
            'hours' => []
        ];
        for ($hour = 0; $hour < 24; $hour++) {
            $avg = $counts[$day][$hour] > 0 
                ? round($matrix[$day][$hour] / $counts[$day][$hour])
                : 0;
            $heatmap[$day]['hours'][$hour] = $avg;
            if ($avg > $maxVisitors) $maxVisitors = $avg;
        }
    }
    
    echo json_encode([
        'success' => true,
        'heatmap' => $heatmap,
        'maxVisitors' => $maxVisitors,
        'dataPoints' => count($files)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Movie Trend API - returns historical occupancy data for a specific movie
if ($action === 'movie_trend') {
    $title = $_GET['title'] ?? '';
    if (!$title) {
        echo json_encode(['success' => false, 'error' => 'Title required']);
        exit;
    }
    
    $historyDir = __DIR__ . '/history';
    $files = glob($historyDir . '/*.json');
    rsort($files); // newest first
    
    $trend = [];
    $titleLower = mb_strtolower($title);
    
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['movies'])) continue;
        
        $date = $data['date'] ?? basename($file, '.json');
        
        foreach ($data['movies'] as $movie) {
            $movieTitleLower = mb_strtolower($movie['title'] ?? '');
            
            // Fuzzy match - contains or similar
            if (strpos($movieTitleLower, $titleLower) !== false || 
                strpos($titleLower, $movieTitleLower) !== false ||
                similar_text($movieTitleLower, $titleLower) > 10) {
                
                $occupied = $movie['totalOccupied'] ?? 0;
                $total = $movie['totalSeats'] ?? 0;
                $percent = $total > 0 ? round(($occupied / $total) * 100) : 0;
                
                $trend[] = [
                    'date' => $date,
                    'occupied' => $occupied,
                    'total' => $total,
                    'percent' => $percent,
                    'screenings' => count($movie['screenings'] ?? [])
                ];
                break;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'title' => $title,
        'trend' => array_reverse($trend), // oldest first for chart
        'dataPoints' => count($trend)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Similar Movies API for premiere comparison
if ($action === 'similar_movies') {
    $title = $_GET['title'] ?? '';
    $genres = isset($_GET['genres']) ? explode(',', $_GET['genres']) : [];
    
    $historyDir = __DIR__ . '/history';
    $files = glob($historyDir . '/*.json');
    
    $movieStats = [];
    $titleLower = mb_strtolower($title);
    
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['movies'])) continue;
        
        foreach ($data['movies'] as $movie) {
            $movieTitle = $movie['title'] ?? '';
            $movieTitleLower = mb_strtolower($movieTitle);
            
            // Skip the movie we're comparing
            if (strpos($movieTitleLower, $titleLower) !== false) continue;
            
            $movieGenres = $movie['genres'] ?? [];
            $genreMatch = count(array_intersect($genres, $movieGenres)) > 0;
            
            if ($genreMatch || empty($genres)) {
                $key = md5($movieTitle);
                if (!isset($movieStats[$key])) {
                    $movieStats[$key] = [
                        'title' => $movieTitle,
                        'genres' => $movieGenres,
                        'days' => 0,
                        'totalOccupied' => 0,
                        'totalSeats' => 0,
                        'avgPercent' => 0
                    ];
                }
                
                $movieStats[$key]['days']++;
                $movieStats[$key]['totalOccupied'] += $movie['totalOccupied'] ?? 0;
                $movieStats[$key]['totalSeats'] += $movie['totalSeats'] ?? 0;
            }
        }
    }
    
    // Calculate averages and sort by performance
    $similar = [];
    foreach ($movieStats as $m) {
        if ($m['days'] >= 2 && $m['totalSeats'] > 0) {
            $m['avgPercent'] = round(($m['totalOccupied'] / $m['totalSeats']) * 100);
            $similar[] = $m;
        }
    }
    
    usort($similar, fn($a, $b) => $b['avgPercent'] - $a['avgPercent']);
    $similar = array_slice($similar, 0, 5); // Top 5
    
    echo json_encode([
        'success' => true,
        'similar' => $similar
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


if ($action === 'image') {
    $url = $_GET['url'] ?? '';
    if (!$url) {
        header("HTTP/1.0 404 Not Found");
        exit;
    }
    
    // Validate that it is a helios image
    if (strpos($url, 'helios.pl') === false) {
        header("HTTP/1.0 403 Forbidden");
        exit;
    }
    
    // Fetch image
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_REFERER => 'https://www.helios.pl/'
    ]);
    $data = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    if ($info['http_code'] == 200 && $data) {
        $contentType = $info['content_type'] ?: 'image/jpeg';
        header("Content-Type: " . $contentType);
        header("Cache-Control: public, max-age=86400"); // Cache for 1 day
        echo $data;
    } else {
        header("HTTP/1.0 404 Not Found");
    }
    exit;
}

if ($action === 'full_package') {
    // 1. Schedule (Movies + Predictions)
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    
    $scheduleData = scrape($date, isset($_GET['refresh']));
    $scheduleData = addPredictions($scheduleData);
    
    // 2. Coming Soon
    $comingSoonData = getComingSoon();
    
    // 3. Marathons
    $marathonsData = getMarathons();
    
    // Aggregate
    $response = [
        'date' => $date,
        'schedule' => $scheduleData,
        'comingSoon' => $comingSoonData['comingSoon'],
        'marathons' => $marathonsData['marathons']
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'full_future_package') {
    set_time_limit(300); // Allow 5 minutes for bulk export

    // 1. Schedule for next 14 days
    $schedule = [];
    $today = new DateTime();
    
    for ($i = 0; $i < 14; $i++) {
        $d = clone $today;
        $d->modify("+$i days");
        $dateStr = $d->format('Y-m-d');
        
        // Scrape and predict
        $dayData = scrape($dateStr, false); // Don't force refresh to use cache
        $dayData = addPredictions($dayData);
        
        $schedule[$dateStr] = $dayData;
    }

    // 2. Coming Soon
    $comingSoonData = getComingSoon();
    
    // 3. Marathons
    $marathonsData = getMarathons();
    
    // Aggregate
    $response = [
        'generated_at' => date('c'),
        'days_count' => count($schedule),
        'schedule' => $schedule,
        'coming_soon' => $comingSoonData['comingSoon'],
        'marathons' => $marathonsData['marathons']
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'comingsoon') {
    $data = getComingSoon();
    // Proxy posters
    foreach ($data['comingSoon'] as &$m) {
        // Direct URL: iOS App spoofs Referer
        // if ($m['poster']) $m['poster'] = proxyUrl($m['poster']);
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'marathons') {
    $data = getMarathons();
    // Proxy posters
    foreach ($data['marathons'] as &$m) {
        // Direct URL: iOS App spoofs Referer
        // if ($m['poster']) $m['poster'] = proxyUrl($m['poster']);
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Default: schedule for date
$date = $_GET['date'] ?? getBusinessDate();
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = getBusinessDate();

$data = scrape($date, isset($_GET['refresh']));
$data = addPredictions($data);

// Rewrite all poster URLs in the response to use our proxy
foreach ($data['movies'] as &$movie) {
    if ($movie['poster']) {
        // Direct URL
        // $movie['poster'] = proxyUrl($movie['poster']);
    }
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} // End main execution block
