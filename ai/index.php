<?php
// API endpoint - zwraca listę folderów jako JSON
if (isset($_GET['api']) && $_GET['api'] === 'folders') {
    header('Content-Type: application/json');
    
    $folders = [];
    $currentDir = __DIR__;
    
    // Ignorowane foldery
    $ignored = ['.git', '.vscode', 'node_modules'];
    
    // Funkcja formatująca nazwę folderu
    function formatFolderName($name) {
        // Zamienia myślniki i podkreślenia na spacje
        $formatted = str_replace(['-', '_'], ' ', $name);
        
        // Każde słowo zaczyna się wielką literą
        $formatted = ucwords($formatted);
        
        return $formatted;
    }
    
    // Funkcja przypisująca ikonę i kolor na podstawie nazwy folderu
    function getIconAndColor($name) {
        $nameLower = strtolower($name);
        
        // Mapowanie słów kluczowych do ikon i kolorów
        $iconMap = [
            // AI & Machine Learning & Tech
            'ai' => ['icon' => 'psychology', 'color' => 'purple'],
            'artificial' => ['icon' => 'psychology', 'color' => 'purple'],
            'machine-learning' => ['icon' => 'model_training', 'color' => 'purple'],
            'chatbot' => ['icon' => 'smart_toy', 'color' => 'purple'],
            'bot' => ['icon' => 'smart_toy', 'color' => 'purple'],
            'neural' => ['icon' => 'neurology', 'color' => 'purple'],
            'sztuczna' => ['icon' => 'psychology', 'color' => 'purple'],
            'inteligencja' => ['icon' => 'psychology', 'color' => 'purple'],
            'robot' => ['icon' => 'smart_toy', 'color' => 'purple'],
            'ml' => ['icon' => 'model_training', 'color' => 'purple'],
            'deep-learning' => ['icon' => 'model_training', 'color' => 'purple'],
            'tensorflow' => ['icon' => 'psychology', 'color' => 'purple'],
            
            // Web & Apps & Development
            'web' => ['icon' => 'language', 'color' => 'blue'],
            'app' => ['icon' => 'apps', 'color' => 'indigo'],
            'website' => ['icon' => 'public', 'color' => 'blue'],
            'portal' => ['icon' => 'web', 'color' => 'blue'],
            'strona' => ['icon' => 'public', 'color' => 'blue'],
            'aplikacja' => ['icon' => 'apps', 'color' => 'indigo'],
            'site' => ['icon' => 'public', 'color' => 'blue'],
            'landing' => ['icon' => 'web_asset', 'color' => 'blue'],
            'blog' => ['icon' => 'article', 'color' => 'blue'],
            'cms' => ['icon' => 'article', 'color' => 'blue'],
            'wordpress' => ['icon' => 'article', 'color' => 'blue'],
            'react' => ['icon' => 'code', 'color' => 'cyan'],
            'vue' => ['icon' => 'code', 'color' => 'green'],
            'angular' => ['icon' => 'code', 'color' => 'red'],
            'next' => ['icon' => 'code', 'color' => 'slate'],
            'nuxt' => ['icon' => 'code', 'color' => 'green'],
            'svelte' => ['icon' => 'code', 'color' => 'orange'],
            'html' => ['icon' => 'code', 'color' => 'orange'],
            'css' => ['icon' => 'style', 'color' => 'blue'],
            'javascript' => ['icon' => 'code', 'color' => 'yellow'],
            'js' => ['icon' => 'code', 'color' => 'yellow'],
            'typescript' => ['icon' => 'code', 'color' => 'blue'],
            'ts' => ['icon' => 'code', 'color' => 'blue'],
            'php' => ['icon' => 'code', 'color' => 'purple'],
            'python' => ['icon' => 'code', 'color' => 'yellow'],
            'java' => ['icon' => 'code', 'color' => 'red'],
            'node' => ['icon' => 'terminal', 'color' => 'green'],
            'nodejs' => ['icon' => 'terminal', 'color' => 'green'],
            
            // Games & Entertainment
            'game' => ['icon' => 'sports_esports', 'color' => 'pink'],
            'gra' => ['icon' => 'sports_esports', 'color' => 'pink'],
            'gaming' => ['icon' => 'sports_esports', 'color' => 'pink'],
            'zabawa' => ['icon' => 'celebration', 'color' => 'pink'],
            'puzzle' => ['icon' => 'extension', 'color' => 'purple'],
            'quiz' => ['icon' => 'quiz', 'color' => 'teal'],
            'board' => ['icon' => 'casino', 'color' => 'amber'],
            'card' => ['icon' => 'playing_cards', 'color' => 'red'],
            'arcade' => ['icon' => 'sports_esports', 'color' => 'pink'],
            'multiplayer' => ['icon' => 'groups', 'color' => 'purple'],
            'rpg' => ['icon' => 'shield', 'color' => 'amber'],
            'strategy' => ['icon' => 'psychology', 'color' => 'indigo'],
            'platformer' => ['icon' => 'videogame_asset', 'color' => 'green'],
            
            // Tools & Utilities
            'calculator' => ['icon' => 'calculate', 'color' => 'green'],
            'kalkulator' => ['icon' => 'calculate', 'color' => 'green'],
            'tool' => ['icon' => 'construction', 'color' => 'orange'],
            'narzedzie' => ['icon' => 'construction', 'color' => 'orange'],
            'generator' => ['icon' => 'auto_awesome', 'color' => 'amber'],
            'converter' => ['icon' => 'swap_horiz', 'color' => 'teal'],
            'konwerter' => ['icon' => 'swap_horiz', 'color' => 'teal'],
            'utility' => ['icon' => 'handyman', 'color' => 'orange'],
            'widget' => ['icon' => 'widgets', 'color' => 'purple'],
            'extension' => ['icon' => 'extension', 'color' => 'indigo'],
            'plugin' => ['icon' => 'extension', 'color' => 'indigo'],
            'addon' => ['icon' => 'extension', 'color' => 'indigo'],
            'helper' => ['icon' => 'help', 'color' => 'blue'],
            'assistant' => ['icon' => 'assistant', 'color' => 'purple'],
            'asystent' => ['icon' => 'assistant', 'color' => 'purple'],
            
            // Data & Analytics & Science
            'dashboard' => ['icon' => 'dashboard', 'color' => 'cyan'],
            'analytics' => ['icon' => 'analytics', 'color' => 'cyan'],
            'chart' => ['icon' => 'bar_chart', 'color' => 'cyan'],
            'data' => ['icon' => 'database', 'color' => 'slate'],
            'dane' => ['icon' => 'database', 'color' => 'slate'],
            'analiza' => ['icon' => 'analytics', 'color' => 'cyan'],
            'wykres' => ['icon' => 'bar_chart', 'color' => 'cyan'],
            'graph' => ['icon' => 'show_chart', 'color' => 'cyan'],
            'stats' => ['icon' => 'query_stats', 'color' => 'cyan'],
            'statystyki' => ['icon' => 'query_stats', 'color' => 'cyan'],
            'report' => ['icon' => 'assessment', 'color' => 'blue'],
            'raport' => ['icon' => 'assessment', 'color' => 'blue'],
            'visualization' => ['icon' => 'bubble_chart', 'color' => 'purple'],
            'wizualizacja' => ['icon' => 'bubble_chart', 'color' => 'purple'],
            'science' => ['icon' => 'science', 'color' => 'teal'],
            'research' => ['icon' => 'biotech', 'color' => 'teal'],
            'badania' => ['icon' => 'biotech', 'color' => 'teal'],
            
            // Design & Creative
            'design' => ['icon' => 'palette', 'color' => 'rose'],
            'creative' => ['icon' => 'brush', 'color' => 'rose'],
            'photo' => ['icon' => 'photo_camera', 'color' => 'violet'],
            'projekt' => ['icon' => 'palette', 'color' => 'rose'],
            'grafika' => ['icon' => 'brush', 'color' => 'rose'],
            'zdjecie' => ['icon' => 'photo_camera', 'color' => 'violet'],
            'art' => ['icon' => 'palette', 'color' => 'pink'],
            'sztuka' => ['icon' => 'palette', 'color' => 'pink'],
            'draw' => ['icon' => 'draw', 'color' => 'purple'],
            'paint' => ['icon' => 'format_paint', 'color' => 'rose'],
            'sketch' => ['icon' => 'edit', 'color' => 'slate'],
            'illustration' => ['icon' => 'brush', 'color' => 'rose'],
            'ilustracja' => ['icon' => 'brush', 'color' => 'rose'],
            'gallery' => ['icon' => 'collections', 'color' => 'purple'],
            'galeria' => ['icon' => 'collections', 'color' => 'purple'],
            'portfolio' => ['icon' => 'work', 'color' => 'indigo'],
            'logo' => ['icon' => 'workspace_premium', 'color' => 'amber'],
            
            // E-commerce & Business
            'shop' => ['icon' => 'shopping_cart', 'color' => 'emerald'],
            'store' => ['icon' => 'storefront', 'color' => 'emerald'],
            'sklep' => ['icon' => 'store', 'color' => 'emerald'],
            'ecommerce' => ['icon' => 'shopping_bag', 'color' => 'emerald'],
            'biznes' => ['icon' => 'business', 'color' => 'emerald'],
            'firma' => ['icon' => 'corporate_fare', 'color' => 'emerald'],
            'business' => ['icon' => 'business', 'color' => 'emerald'],
            'commerce' => ['icon' => 'shopping_cart', 'color' => 'green'],
            'market' => ['icon' => 'store', 'color' => 'emerald'],
            'marketplace' => ['icon' => 'storefront', 'color' => 'emerald'],
            'checkout' => ['icon' => 'shopping_cart_checkout', 'color' => 'green'],
            'payment' => ['icon' => 'payment', 'color' => 'green'],
            'platnosc' => ['icon' => 'payment', 'color' => 'green'],
            'invoice' => ['icon' => 'receipt', 'color' => 'blue'],
            'faktura' => ['icon' => 'receipt', 'color' => 'blue'],
            'pos' => ['icon' => 'point_of_sale', 'color' => 'green'],
            'kasa' => ['icon' => 'point_of_sale', 'color' => 'green'],
            'inventory' => ['icon' => 'inventory', 'color' => 'orange'],
            'magazyn' => ['icon' => 'inventory', 'color' => 'orange'],
            
            // Education & Learning
            'learning' => ['icon' => 'school', 'color' => 'blue'],
            'course' => ['icon' => 'menu_book', 'color' => 'blue'],
            'kurs' => ['icon' => 'menu_book', 'color' => 'blue'],
            'nauka' => ['icon' => 'school', 'color' => 'blue'],
            'edukacja' => ['icon' => 'school', 'color' => 'blue'],
            'szkola' => ['icon' => 'school', 'color' => 'blue'],
            'education' => ['icon' => 'school', 'color' => 'blue'],
            'tutorial' => ['icon' => 'school', 'color' => 'blue'],
            'poradnik' => ['icon' => 'help', 'color' => 'blue'],
            'lesson' => ['icon' => 'menu_book', 'color' => 'indigo'],
            'lekcja' => ['icon' => 'menu_book', 'color' => 'indigo'],
            'academy' => ['icon' => 'account_balance', 'color' => 'indigo'],
            'akademia' => ['icon' => 'account_balance', 'color' => 'indigo'],
            'university' => ['icon' => 'school', 'color' => 'blue'],
            'uniwersytet' => ['icon' => 'school', 'color' => 'blue'],
            'training' => ['icon' => 'model_training', 'color' => 'purple'],
            'szkolenie' => ['icon' => 'model_training', 'color' => 'purple'],
            
            // Social & Communication
            'chat' => ['icon' => 'chat', 'color' => 'green'],
            'social' => ['icon' => 'groups', 'color' => 'indigo'],
            'forum' => ['icon' => 'forum', 'color' => 'indigo'],
            'czat' => ['icon' => 'chat', 'color' => 'green'],
            'wiadomosci' => ['icon' => 'mail', 'color' => 'green'],
            'spolecznosc' => ['icon' => 'groups', 'color' => 'indigo'],
            'message' => ['icon' => 'message', 'color' => 'blue'],
            'messenger' => ['icon' => 'chat_bubble', 'color' => 'blue'],
            'comment' => ['icon' => 'comment', 'color' => 'cyan'],
            'komentarz' => ['icon' => 'comment', 'color' => 'cyan'],
            'discussion' => ['icon' => 'forum', 'color' => 'indigo'],
            'dyskusja' => ['icon' => 'forum', 'color' => 'indigo'],
            'community' => ['icon' => 'groups', 'color' => 'purple'],
            'network' => ['icon' => 'share', 'color' => 'blue'],
            'siec' => ['icon' => 'share', 'color' => 'blue'],
            'feed' => ['icon' => 'dynamic_feed', 'color' => 'orange'],
            'notification' => ['icon' => 'notifications', 'color' => 'red'],
            'powiadomienia' => ['icon' => 'notifications', 'color' => 'red'],
            
            // Productivity & Organization
            'todo' => ['icon' => 'checklist', 'color' => 'orange'],
            'task' => ['icon' => 'task_alt', 'color' => 'orange'],
            'note' => ['icon' => 'note', 'color' => 'yellow'],
            'calendar' => ['icon' => 'calendar_month', 'color' => 'red'],
            'zadania' => ['icon' => 'task_alt', 'color' => 'orange'],
            'notatki' => ['icon' => 'note', 'color' => 'yellow'],
            'kalendarz' => ['icon' => 'calendar_month', 'color' => 'red'],
            'terminarz' => ['icon' => 'event', 'color' => 'red'],
            'planner' => ['icon' => 'event_note', 'color' => 'blue'],
            'organizer' => ['icon' => 'calendar_today', 'color' => 'indigo'],
            'agenda' => ['icon' => 'event', 'color' => 'purple'],
            'schedule' => ['icon' => 'schedule', 'color' => 'blue'],
            'harmonogram' => ['icon' => 'schedule', 'color' => 'blue'],
            'timer' => ['icon' => 'timer', 'color' => 'orange'],
            'alarm' => ['icon' => 'alarm', 'color' => 'red'],
            'reminder' => ['icon' => 'alarm', 'color' => 'orange'],
            'przypomnienie' => ['icon' => 'alarm', 'color' => 'orange'],
            'bookmark' => ['icon' => 'bookmark', 'color' => 'yellow'],
            'zakladka' => ['icon' => 'bookmark', 'color' => 'yellow'],
            'list' => ['icon' => 'list', 'color' => 'slate'],
            'lista' => ['icon' => 'list', 'color' => 'slate'],
            
            // Technical & Development
            'api' => ['icon' => 'api', 'color' => 'slate'],
            'database' => ['icon' => 'storage', 'color' => 'slate'],
            'server' => ['icon' => 'dns', 'color' => 'slate'],
            'code' => ['icon' => 'code', 'color' => 'purple'],
            'baza-danych' => ['icon' => 'storage', 'color' => 'slate'],
            'serwer' => ['icon' => 'dns', 'color' => 'slate'],
            'kod' => ['icon' => 'code', 'color' => 'purple'],
            'backend' => ['icon' => 'dns', 'color' => 'slate'],
            'frontend' => ['icon' => 'web', 'color' => 'blue'],
            'fullstack' => ['icon' => 'layers', 'color' => 'indigo'],
            'devops' => ['icon' => 'settings', 'color' => 'slate'],
            'cloud' => ['icon' => 'cloud', 'color' => 'sky'],
            'chmura' => ['icon' => 'cloud', 'color' => 'sky'],
            'docker' => ['icon' => 'sailing', 'color' => 'blue'],
            'kubernetes' => ['icon' => 'hub', 'color' => 'blue'],
            'git' => ['icon' => 'source', 'color' => 'orange'],
            'github' => ['icon' => 'code', 'color' => 'slate'],
            'gitlab' => ['icon' => 'code', 'color' => 'orange'],
            'deploy' => ['icon' => 'rocket_launch', 'color' => 'purple'],
            'hosting' => ['icon' => 'dns', 'color' => 'blue'],
            'domain' => ['icon' => 'language', 'color' => 'blue'],
            'domena' => ['icon' => 'language', 'color' => 'blue'],
            
            // Media & Entertainment
            'video' => ['icon' => 'videocam', 'color' => 'red'],
            'music' => ['icon' => 'music_note', 'color' => 'pink'],
            'audio' => ['icon' => 'audiotrack', 'color' => 'pink'],
            'wideo' => ['icon' => 'videocam', 'color' => 'red'],
            'muzyka' => ['icon' => 'music_note', 'color' => 'pink'],
            'film' => ['icon' => 'movie', 'color' => 'red'],
            'movie' => ['icon' => 'movie', 'color' => 'red'],
            'cinema' => ['icon' => 'theaters', 'color' => 'red'],
            'kino' => ['icon' => 'theaters', 'color' => 'red'],
            'player' => ['icon' => 'play_circle', 'color' => 'red'],
            'odtwarzacz' => ['icon' => 'play_circle', 'color' => 'red'],
            'stream' => ['icon' => 'stream', 'color' => 'purple'],
            'podcast' => ['icon' => 'podcasts', 'color' => 'purple'],
            'radio' => ['icon' => 'radio', 'color' => 'blue'],
            'camera' => ['icon' => 'photo_camera', 'color' => 'violet'],
            'kamera' => ['icon' => 'photo_camera', 'color' => 'violet'],
            'edit' => ['icon' => 'edit', 'color' => 'blue'],
            'edytor' => ['icon' => 'edit', 'color' => 'blue'],
            
            // Weather & Environment
            'weather' => ['icon' => 'cloud', 'color' => 'sky'],
            'pogoda' => ['icon' => 'cloud', 'color' => 'sky'],
            'forecast' => ['icon' => 'thermostat', 'color' => 'blue'],
            'prognoza' => ['icon' => 'thermostat', 'color' => 'blue'],
            'climate' => ['icon' => 'wb_sunny', 'color' => 'yellow'],
            'klimat' => ['icon' => 'wb_sunny', 'color' => 'yellow'],
            'temperature' => ['icon' => 'thermostat', 'color' => 'red'],
            'temperatura' => ['icon' => 'thermostat', 'color' => 'red'],
            
            // Maps & Location
            'map' => ['icon' => 'map', 'color' => 'green'],
            'mapa' => ['icon' => 'map', 'color' => 'green'],
            'location' => ['icon' => 'location_on', 'color' => 'red'],
            'lokalizacja' => ['icon' => 'location_on', 'color' => 'red'],
            'gps' => ['icon' => 'my_location', 'color' => 'blue'],
            'navigation' => ['icon' => 'navigation', 'color' => 'blue'],
            'nawigacja' => ['icon' => 'navigation', 'color' => 'blue'],
            'route' => ['icon' => 'directions', 'color' => 'green'],
            'trasa' => ['icon' => 'directions', 'color' => 'green'],
            
            // Food & Cooking
            'recipe' => ['icon' => 'restaurant', 'color' => 'orange'],
            'przepis' => ['icon' => 'restaurant', 'color' => 'orange'],
            'food' => ['icon' => 'restaurant_menu', 'color' => 'amber'],
            'jedzenie' => ['icon' => 'restaurant_menu', 'color' => 'amber'],
            'cooking' => ['icon' => 'soup_kitchen', 'color' => 'orange'],
            'gotowanie' => ['icon' => 'soup_kitchen', 'color' => 'orange'],
            'kitchen' => ['icon' => 'kitchen', 'color' => 'orange'],
            'kuchnia' => ['icon' => 'kitchen', 'color' => 'orange'],
            'restaurant' => ['icon' => 'restaurant', 'color' => 'red'],
            'restauracja' => ['icon' => 'restaurant', 'color' => 'red'],
            'menu' => ['icon' => 'restaurant_menu', 'color' => 'amber'],
            'delivery' => ['icon' => 'delivery_dining', 'color' => 'orange'],
            'dostawa' => ['icon' => 'delivery_dining', 'color' => 'orange'],
            
            // Health & Fitness
            'health' => ['icon' => 'favorite', 'color' => 'red'],
            'zdrowie' => ['icon' => 'favorite', 'color' => 'red'],
            'fitness' => ['icon' => 'fitness_center', 'color' => 'red'],
            'workout' => ['icon' => 'exercise', 'color' => 'orange'],
            'trening' => ['icon' => 'exercise', 'color' => 'orange'],
            'gym' => ['icon' => 'fitness_center', 'color' => 'red'],
            'silownia' => ['icon' => 'fitness_center', 'color' => 'red'],
            'medical' => ['icon' => 'medical_services', 'color' => 'red'],
            'medyczny' => ['icon' => 'medical_services', 'color' => 'red'],
            'hospital' => ['icon' => 'local_hospital', 'color' => 'red'],
            'szpital' => ['icon' => 'local_hospital', 'color' => 'red'],
            'doctor' => ['icon' => 'medical_services', 'color' => 'blue'],
            'lekarz' => ['icon' => 'medical_services', 'color' => 'blue'],
            'pharmacy' => ['icon' => 'medication', 'color' => 'green'],
            'apteka' => ['icon' => 'medication', 'color' => 'green'],
            
            // Money & Finance
            'money' => ['icon' => 'payments', 'color' => 'green'],
            'finance' => ['icon' => 'account_balance', 'color' => 'green'],
            'pieniadze' => ['icon' => 'payments', 'color' => 'green'],
            'finanse' => ['icon' => 'account_balance', 'color' => 'green'],
            'budzet' => ['icon' => 'savings', 'color' => 'green'],
            'budget' => ['icon' => 'savings', 'color' => 'green'],
            'bank' => ['icon' => 'account_balance', 'color' => 'blue'],
            'wallet' => ['icon' => 'account_balance_wallet', 'color' => 'green'],
            'portfel' => ['icon' => 'account_balance_wallet', 'color' => 'green'],
            'crypto' => ['icon' => 'currency_bitcoin', 'color' => 'orange'],
            'kryptowaluta' => ['icon' => 'currency_bitcoin', 'color' => 'orange'],
            'bitcoin' => ['icon' => 'currency_bitcoin', 'color' => 'orange'],
            'stock' => ['icon' => 'trending_up', 'color' => 'green'],
            'akcje' => ['icon' => 'trending_up', 'color' => 'green'],
            'investment' => ['icon' => 'trending_up', 'color' => 'green'],
            'inwestycja' => ['icon' => 'trending_up', 'color' => 'green'],
            
            // Travel & Transport
            'travel' => ['icon' => 'flight', 'color' => 'blue'],
            'transport' => ['icon' => 'directions_car', 'color' => 'blue'],
            'podroze' => ['icon' => 'flight', 'color' => 'blue'],
            'wycieczka' => ['icon' => 'luggage', 'color' => 'blue'],
            'trip' => ['icon' => 'luggage', 'color' => 'blue'],
            'flight' => ['icon' => 'flight', 'color' => 'blue'],
            'lot' => ['icon' => 'flight', 'color' => 'blue'],
            'hotel' => ['icon' => 'hotel', 'color' => 'purple'],
            'booking' => ['icon' => 'book_online', 'color' => 'blue'],
            'rezerwacja' => ['icon' => 'book_online', 'color' => 'blue'],
            'car' => ['icon' => 'directions_car', 'color' => 'blue'],
            'samochod' => ['icon' => 'directions_car', 'color' => 'blue'],
            'train' => ['icon' => 'train', 'color' => 'blue'],
            'pociag' => ['icon' => 'train', 'color' => 'blue'],
            'bus' => ['icon' => 'directions_bus', 'color' => 'orange'],
            'autobus' => ['icon' => 'directions_bus', 'color' => 'orange'],
            'taxi' => ['icon' => 'local_taxi', 'color' => 'yellow'],
            'bike' => ['icon' => 'directions_bike', 'color' => 'green'],
            'rower' => ['icon' => 'directions_bike', 'color' => 'green'],
            
            // Books & Reading
            'book' => ['icon' => 'book', 'color' => 'amber'],
            'library' => ['icon' => 'local_library', 'color' => 'amber'],
            'ksiazka' => ['icon' => 'book', 'color' => 'amber'],
            'biblioteka' => ['icon' => 'local_library', 'color' => 'amber'],
            'czytanie' => ['icon' => 'menu_book', 'color' => 'amber'],
            'reading' => ['icon' => 'menu_book', 'color' => 'blue'],
            'ebook' => ['icon' => 'import_contacts', 'color' => 'indigo'],
            'magazine' => ['icon' => 'auto_stories', 'color' => 'purple'],
            'czasopismo' => ['icon' => 'auto_stories', 'color' => 'purple'],
            'newspaper' => ['icon' => 'newspaper', 'color' => 'slate'],
            'gazeta' => ['icon' => 'newspaper', 'color' => 'slate'],
            
            // Sports & Activities
            'sport' => ['icon' => 'sports_soccer', 'color' => 'orange'],
            'pilka' => ['icon' => 'sports_soccer', 'color' => 'orange'],
            'football' => ['icon' => 'sports_soccer', 'color' => 'green'],
            'basketball' => ['icon' => 'sports_basketball', 'color' => 'orange'],
            'koszykowka' => ['icon' => 'sports_basketball', 'color' => 'orange'],
            'tennis' => ['icon' => 'sports_tennis', 'color' => 'yellow'],
            'tenis' => ['icon' => 'sports_tennis', 'color' => 'yellow'],
            'volleyball' => ['icon' => 'sports_volleyball', 'color' => 'blue'],
            'siatkowka' => ['icon' => 'sports_volleyball', 'color' => 'blue'],
            'running' => ['icon' => 'directions_run', 'color' => 'blue'],
            'bieganie' => ['icon' => 'directions_run', 'color' => 'blue'],
            'swimming' => ['icon' => 'pool', 'color' => 'cyan'],
            'plywanie' => ['icon' => 'pool', 'color' => 'cyan'],
            
            // Security & Privacy
            'security' => ['icon' => 'security', 'color' => 'red'],
            'bezpieczenstwo' => ['icon' => 'security', 'color' => 'red'],
            'password' => ['icon' => 'password', 'color' => 'amber'],
            'haslo' => ['icon' => 'password', 'color' => 'amber'],
            'login' => ['icon' => 'login', 'color' => 'blue'],
            'logowanie' => ['icon' => 'login', 'color' => 'blue'],
            'auth' => ['icon' => 'verified_user', 'color' => 'green'],
            'authentication' => ['icon' => 'verified_user', 'color' => 'green'],
            'privacy' => ['icon' => 'privacy_tip', 'color' => 'purple'],
            'prywatnosc' => ['icon' => 'privacy_tip', 'color' => 'purple'],
            'encryption' => ['icon' => 'lock', 'color' => 'slate'],
            'szyfrowanie' => ['icon' => 'lock', 'color' => 'slate'],
            
            // Other Common
            'test' => ['icon' => 'science', 'color' => 'amber'],
            'demo' => ['icon' => 'preview', 'color' => 'cyan'],
            'example' => ['icon' => 'lightbulb', 'color' => 'yellow'],
            'sample' => ['icon' => 'category', 'color' => 'gray'],
            'przyklad' => ['icon' => 'lightbulb', 'color' => 'yellow'],
            'szablon' => ['icon' => 'description', 'color' => 'gray'],
            'template' => ['icon' => 'description', 'color' => 'slate'],
            'starter' => ['icon' => 'rocket_launch', 'color' => 'purple'],
            'boilerplate' => ['icon' => 'inventory_2', 'color' => 'slate'],
            'admin' => ['icon' => 'admin_panel_settings', 'color' => 'red'],
            'panel' => ['icon' => 'dashboard', 'color' => 'indigo'],
            'settings' => ['icon' => 'settings', 'color' => 'slate'],
            'ustawienia' => ['icon' => 'settings', 'color' => 'slate'],
            'config' => ['icon' => 'settings', 'color' => 'slate'],
            'konfiguracja' => ['icon' => 'settings', 'color' => 'slate'],
            'profile' => ['icon' => 'account_circle', 'color' => 'blue'],
            'profil' => ['icon' => 'account_circle', 'color' => 'blue'],
            'user' => ['icon' => 'person', 'color' => 'blue'],
            'uzytkownik' => ['icon' => 'person', 'color' => 'blue'],
            'account' => ['icon' => 'manage_accounts', 'color' => 'indigo'],
            'konto' => ['icon' => 'manage_accounts', 'color' => 'indigo'],
            'archive' => ['icon' => 'archive', 'color' => 'amber'],
            'archiwum' => ['icon' => 'archive', 'color' => 'amber'],
            'backup' => ['icon' => 'backup', 'color' => 'blue'],
            'kopia' => ['icon' => 'backup', 'color' => 'blue'],
            'search' => ['icon' => 'search', 'color' => 'blue'],
            'wyszukiwarka' => ['icon' => 'search', 'color' => 'blue'],
            'filter' => ['icon' => 'filter_alt', 'color' => 'purple'],
            'filtr' => ['icon' => 'filter_alt', 'color' => 'purple'],
            'sort' => ['icon' => 'sort', 'color' => 'slate'],
            'sortowanie' => ['icon' => 'sort', 'color' => 'slate'],

            // Logic & Simulation (stems + English equivalents)
            'logik' => ['icon' => 'psychology', 'color' => 'purple'],
            'logika' => ['icon' => 'psychology', 'color' => 'purple'],
            'logiki' => ['icon' => 'psychology', 'color' => 'purple'],
            'logic' => ['icon' => 'psychology', 'color' => 'purple'],
            'logical' => ['icon' => 'psychology', 'color' => 'purple'],

            'symul' => ['icon' => 'science', 'color' => 'teal'],
            'symulator' => ['icon' => 'science', 'color' => 'teal'],
            'symulacja' => ['icon' => 'science', 'color' => 'teal'],
            'symulacje' => ['icon' => 'science', 'color' => 'teal'],
            'simul' => ['icon' => 'science', 'color' => 'teal'],
            'simulat' => ['icon' => 'science', 'color' => 'teal'],
            'simulator' => ['icon' => 'science', 'color' => 'teal'],
            'simulation' => ['icon' => 'science', 'color' => 'teal'],
            'simulations' => ['icon' => 'science', 'color' => 'teal'],
            'simulators' => ['icon' => 'science', 'color' => 'teal'],
        ];
        
        // Sprawdzamy, czy nazwa zawiera któreś ze słów kluczowych
        foreach ($iconMap as $keyword => $config) {
            if (strpos($nameLower, $keyword) !== false) {
                return $config;
            }
        }
        
        // Domyślna ikona i kolor
        return ['icon' => 'folder', 'color' => 'blue'];
    }
    
    // Skanujemy katalog
    if ($handle = opendir($currentDir)) {
        while (false !== ($entry = readdir($handle))) {
            // Sprawdzamy czy to folder (nie plik) i nie jest w liście ignorowanych
            if ($entry != "." && $entry != ".." && is_dir($currentDir . '/' . $entry)) {
                if (!in_array($entry, $ignored)) {
                    $iconConfig = getIconAndColor($entry);
                    $folders[] = [
                        'name' => formatFolderName($entry),
                        'originalName' => $entry,
                        'path' => $entry . '/',
                        'type' => 'folder',
                        'icon' => $iconConfig['icon'],
                        'color' => $iconConfig['color']
                    ];
                }
            }
        }
        closedir($handle);
    }
    
    // Sortujemy alfabetycznie
    usort($folders, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    echo json_encode($folders);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Projects Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#4338ca',
                        secondary: '#64748b',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
        }
        .dark body {
            background-color: #0f172a;
            color: #f1f5f9;
        }
        .card {
            transition: all 0.2s ease-in-out;
        }
        .card:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col transition-colors duration-300">

    <!-- Header -->
    <header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                    <span class="material-symbols-rounded text-2xl">hub</span>
                </div>
                <h1 class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-indigo-500">
                    AI Projects Hub
                </h1>
            </div>
            
            <div class="flex items-center gap-3">
                <button id="refresh-btn" class="p-2 text-slate-500 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors" title="Odśwież listę">
                    <span class="material-symbols-rounded">refresh</span>
                </button>
                <button id="theme-toggle" class="p-2 text-slate-500 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors">
                    <span class="material-symbols-rounded">dark_mode</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        
        <!-- Status Bar -->
        <div id="status-bar" class="mb-8 hidden">
            <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
                <span class="material-symbols-rounded text-lg">info</span>
                <span id="status-text">Skanowanie folderów...</span>
            </div>
        </div>

        <!-- Projects Grid -->
        <div id="projects-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <!-- Cards will be injected here -->
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden flex flex-col items-center justify-center py-20 text-center">
            <div class="w-20 h-20 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                <span class="material-symbols-rounded text-4xl text-slate-400">folder_off</span>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 dark:text-slate-200">Nie znaleziono projektów</h3>
            <p class="text-slate-500 dark:text-slate-400 max-w-md mt-2">
                Upewnij się, że foldery z projektami znajdują się w tym samym katalogu co ten plik.
            </p>
        </div>

    </main>

    <footer class="bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center text-sm text-slate-500">
            &copy; 2025 AI Projects Hub. Local Workspace.
        </div>
    </footer>

    <script>
        const grid = document.getElementById('projects-grid');
        const emptyState = document.getElementById('empty-state');
        const statusBar = document.getElementById('status-bar');
        const statusText = document.getElementById('status-text');

        async function scanDirectory() {
            showStatus('Skanowanie folderów...');
            grid.innerHTML = '';
            
            try {
                const response = await fetch('?api=folders');
                console.log('📡 Response status:', response.status, response.ok);
                
                if (response.ok) {
                    const projects = await response.json();
                    console.log('📦 Znaleziono folderów:', projects.length);
                    console.log('📦 Projekty:', projects);
                    
                    renderProjects(projects);
                    if (projects.length > 0) {
                        hideStatus();
                    } else {
                        showStatus('Nie znaleziono folderów w tym katalogu.', true);
                    }
                } else {
                    throw new Error('Błąd odpowiedzi serwera');
                }
            } catch (err) {
                console.error('❌ Błąd skanowania:', err);
                showStatus('Nie można przeskanować katalogu. Błąd serwera.', true);
            }
        }

        function renderProjects(projects) {
            if (projects.length === 0) {
                grid.classList.add('hidden');
                emptyState.classList.remove('hidden');
                return;
            }

            grid.classList.remove('hidden');
            emptyState.classList.add('hidden');

            projects.forEach(proj => {
                const card = document.createElement('a');
                card.href = proj.path;
                card.className = 'card block bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 hover:shadow-lg hover:border-primary/50 dark:hover:border-primary/50 group';
                
                // Mapowanie kolorów z PHP do klas Tailwind
                const colorMap = {
                    'blue': 'text-blue-500 bg-blue-50 dark:bg-blue-900/20',
                    'indigo': 'text-indigo-500 bg-indigo-50 dark:bg-indigo-900/20',
                    'purple': 'text-purple-500 bg-purple-50 dark:bg-purple-900/20',
                    'pink': 'text-pink-500 bg-pink-50 dark:bg-pink-900/20',
                    'rose': 'text-rose-500 bg-rose-50 dark:bg-rose-900/20',
                    'red': 'text-red-500 bg-red-50 dark:bg-red-900/20',
                    'orange': 'text-orange-500 bg-orange-50 dark:bg-orange-900/20',
                    'amber': 'text-amber-500 bg-amber-50 dark:bg-amber-900/20',
                    'yellow': 'text-yellow-500 bg-yellow-50 dark:bg-yellow-900/20',
                    'lime': 'text-lime-500 bg-lime-50 dark:bg-lime-900/20',
                    'green': 'text-green-500 bg-green-50 dark:bg-green-900/20',
                    'emerald': 'text-emerald-500 bg-emerald-50 dark:bg-emerald-900/20',
                    'teal': 'text-teal-500 bg-teal-50 dark:bg-teal-900/20',
                    'cyan': 'text-cyan-500 bg-cyan-50 dark:bg-cyan-900/20',
                    'sky': 'text-sky-500 bg-sky-50 dark:bg-sky-900/20',
                    'violet': 'text-violet-500 bg-violet-50 dark:bg-violet-900/20',
                    'slate': 'text-slate-500 bg-slate-50 dark:bg-slate-900/20',
                    'gray': 'text-gray-500 bg-gray-50 dark:bg-gray-900/20'
                };
                
                const icon = proj.icon || 'folder';
                const colorClass = colorMap[proj.color] || colorMap['blue'];

                card.innerHTML = `
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-12 h-12 rounded-lg ${colorClass} flex items-center justify-center">
                            <span class="material-symbols-rounded text-2xl">${icon}</span>
                        </div>
                        <span class="material-symbols-rounded text-slate-300 group-hover:text-primary transition-colors">arrow_outward</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1 group-hover:text-primary transition-colors truncate">
                        ${proj.name}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                        ${proj.path}
                    </p>
                `;
                grid.appendChild(card);
            });
        }

        function showStatus(msg, isError = false) {
            statusBar.classList.remove('hidden');
            statusText.innerText = msg;
            const div = statusBar.firstElementChild;
            if (isError) {
                div.className = 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 px-4 py-3 rounded-lg text-sm flex items-center gap-2';
            } else {
                div.className = 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 px-4 py-3 rounded-lg text-sm flex items-center gap-2';
            }
        }

        function hideStatus() {
            statusBar.classList.add('hidden');
        }

        // --- Theme Handling ---
        const themeToggle = document.getElementById('theme-toggle');
        
        function initTheme() {
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
                themeToggle.querySelector('span').innerText = 'light_mode';
            } else {
                document.documentElement.classList.remove('dark');
                themeToggle.querySelector('span').innerText = 'dark_mode';
            }
        }

        themeToggle.addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
                themeToggle.querySelector('span').innerText = 'dark_mode';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
                themeToggle.querySelector('span').innerText = 'light_mode';
            }
        });

        document.getElementById('refresh-btn').addEventListener('click', scanDirectory);

        // Init
        initTheme();
        scanDirectory();

    </script>
</body>
</html>
