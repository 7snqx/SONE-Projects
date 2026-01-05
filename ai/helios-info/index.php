<?php 
// MANUAL BUILD VERSION - Update this when making code changes!
// TODO: Change this value when you modify the code
$buildVersion = 'v1.0.2'; 
?>
<!DOCTYPE html>
<html lang="pl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#E30613" />
    <title>Obłożenie sal | Helios Łódź</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g' x1='0%25' y1='0%25' x2='100%25' y2='100%25'><stop offset='0%25' stop-color='%23FF2020'/><stop offset='100%25' stop-color='%23B00'/></linearGradient></defs><rect fill='url(%23g)' width='100' height='100' rx='20'/><g transform='translate(10,10) scale(3.33)'><path fill='white' d='M22 10V6c0-1.11-.9-2-2-2H4c-1.1 0-1.99.89-1.99 2v4c1.1 0 1.99.9 1.99 2s-.89 2-2 2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-4c-1.1 0-2-.9-2-2s.9-2 2-2zm-9 7.5h-2v-2h2v2zm0-4.5h-2v-2h2v2zm0-4.5h-2v-2h2v2z'/></g></svg>" />
    <link rel="manifest" href="./manifest.json" />
    <link
      rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    />
    <!-- Using time() to prevent caching completely on every reload -->
    <link rel="stylesheet" href="styles.css?ver=<?php echo time(); ?>" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      // Register Service Worker for offline support
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          // Use relative path with scope
          navigator.serviceWorker.register('./sw.js', { scope: './' })
            .then(reg => {
              console.log('[SW] Registered:', reg.scope);
              // Check for updates
              reg.update();
            })
            .catch(err => console.log('[SW] Registration failed:', err));
        });
      }
      
      // Offline indicator
      window.addEventListener('online', () => {
        document.body.classList.remove('offline');
        const badge = document.getElementById('offlineBadge');
        if (badge) badge.style.display = 'none';
      });
      
      window.addEventListener('offline', () => {
        document.body.classList.add('offline');
        const badge = document.getElementById('offlineBadge');
        if (badge) badge.style.display = 'flex';
      });

      // PWA Install Prompt
      let deferredPrompt;
      
      window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // Show subtle install button in footer
        const btn = document.getElementById('installBtn');
        if (btn) btn.style.display = 'inline-flex';
      });
      
      window.addEventListener('appinstalled', () => {
        console.log('[PWA] App installed');
        const btn = document.getElementById('installBtn');
        if (btn) btn.style.display = 'none';
        deferredPrompt = null;
      });
      
      function installApp() {
        if (deferredPrompt) {
          deferredPrompt.prompt();
          deferredPrompt.userChoice.then((result) => {
            console.log('[PWA] User choice:', result.outcome);
            deferredPrompt = null;
          });
        } else {
          // Show install modal
          const modal = document.getElementById('installModal');
          if (modal) modal.style.display = 'flex';
          
          // Detect platform and show appropriate instructions
          const iosInstr = document.getElementById('iosInstructions');
          const androidInstr = document.getElementById('androidInstructions');
          const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
          
          if (iosInstr && androidInstr) {
            iosInstr.style.display = isIOS ? 'block' : 'none';
            androidInstr.style.display = isIOS ? 'none' : 'block';
          }
        }
      }
      
      function closeInstallModal() {
        const modal = document.getElementById('installModal');
        if (modal) modal.style.display = 'none';
      }
    </script>
  </head>
  <body>
    <!-- Offline Badge -->
    <div id="offlineBadge" class="offline-badge" style="display: none;">
      <span class="material-symbols-rounded">cloud_off</span>
      <span>Tryb offline - dane z cache</span>
    </div>

    <!-- Install Modal -->
    <div id="installModal" class="install-modal" style="display: none;" onclick="closeInstallModal()">
      <div class="install-modal-content" onclick="event.stopPropagation()">
        <div class="install-modal-header">
          <span class="material-symbols-rounded">download</span>
          <h3>Dodaj do ekranu</h3>
          <button onclick="closeInstallModal()" class="close-btn">
            <span class="material-symbols-rounded">close</span>
          </button>
        </div>
        <div id="iosInstructions" class="install-instructions">
          <p><strong>Na iOS (Safari):</strong></p>
          <ol>
            <li>Kliknij <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">ios_share</span> <strong>Udostępnij</strong></li>
            <li>Przewiń i wybierz <strong>"Dodaj do ekranu początkowego"</strong></li>
          </ol>
        </div>
        <div id="androidInstructions" class="install-instructions">
          <p><strong>Na Androidzie:</strong></p>
          <ol>
            <li>Otwórz menu przeglądarki <strong>⋮</strong></li>
            <li>Wybierz <strong>"Dodaj do ekranu głównego"</strong><br>lub <strong>"Zainstaluj aplikację"</strong></li>
          </ol>
        </div>
        <button onclick="closeInstallModal()" class="install-modal-btn">Rozumiem</button>
      </div>
    </div>

    <!-- Full-page loading overlay -->
    <div id="fullPageLoader" class="full-page-loader">
      <div class="loader-content">
        <div class="loader-spinner"></div>
        <p>Ładowanie danych...</p>
      </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="history-modal" style="display: none;">
      <div class="history-modal-content">
        <div class="history-header">
          <h3>Archiwum</h3>
          <button onclick="window.heliosApp.toggleHistoryModal()" class="close-btn">
            <span class="material-symbols-rounded">close</span>
          </button>
        </div>
        <div id="historyList" class="history-list">
          <div class="spinner"></div>
        </div>
      </div>
    </div>

    <!-- Calendar Modal -->
    <div id="calendarModal" class="calendar-modal" style="display: none;">
      <div class="calendar-modal-content">
        <div class="calendar-header">
          <button class="calendar-nav-btn" id="prevMonth" title="Poprzedni miesiąc">
            <span class="material-symbols-rounded">chevron_left</span>
          </button>
          <button class="calendar-title-btn" id="calendarTitleBtn" title="Wybierz miesiąc">
            <span id="calendarTitle">Grudzień 2025</span>
            <span class="material-symbols-rounded">expand_more</span>
          </button>
          <button class="calendar-nav-btn" id="nextMonth" title="Następny miesiąc">
            <span class="material-symbols-rounded">chevron_right</span>
          </button>
          <button onclick="window.heliosApp.toggleCalendarModal()" class="close-btn" title="Zamknij">
            <span class="material-symbols-rounded">close</span>
          </button>
        </div>
        <div class="month-picker-dropdown" id="monthPickerDropdown" style="display: none;">
          <div class="year-nav">
            <button class="year-nav-btn" id="prevYear"><span class="material-symbols-rounded">chevron_left</span></button>
            <span id="pickerYear">2025</span>
            <button class="year-nav-btn" id="nextYear"><span class="material-symbols-rounded">chevron_right</span></button>
          </div>
          <div class="month-grid" id="monthGrid"></div>
        </div>
        <div class="calendar-weekdays">
          <span>Pon</span><span>Wt</span><span>Śr</span><span>Czw</span><span>Pt</span><span>Sob</span><span>Ndz</span>
        </div>
        <div id="calendarGrid" class="calendar-grid">
          <!-- JS rendered -->
        </div>
        <div class="calendar-legend">
          <span><span class="legend-dot low"></span> &lt;20%</span>
          <span><span class="legend-dot medium"></span> 20-50%</span>
          <span><span class="legend-dot high"></span> &gt;50%</span>
        </div>
        <div class="calendar-day-preview" id="calendarDayPreview" style="display: none;">
          <div class="preview-header">
            <span class="material-symbols-rounded">event</span>
            <span id="previewDate">-</span>
          </div>
          <div class="preview-stats">
            <div class="preview-stat">
              <span class="preview-value" id="previewOcc">-</span>
              <span class="preview-label">Obłożenie</span>
            </div>
            <div class="preview-stat">
              <span class="preview-value" id="previewScreenings">-</span>
              <span class="preview-label">Seansów</span>
            </div>
            <div class="preview-stat">
              <span class="preview-value" id="previewSeats">-</span>
              <span class="preview-label">Miejsc</span>
            </div>
          </div>
          <button class="load-history-btn" id="loadHistoryBtn">
            <span class="material-symbols-rounded">history</span>
            Wczytaj historię
          </button>
        </div>
      </div>
    </div>

    <div class="app">
      <!-- Red Header Bar -->
      <header class="header">
        <div class="container">
          <div class="header-content">
            <a href="/" class="logo">
              <span class="logo-icon material-symbols-rounded">movie</span>
              <div class="logo-text">
                <span class="logo-brand">HELIOS</span>
                <span class="logo-city">Łódź • Obłożenie</span>
              </div>
            </a>
            <div class="header-actions">
              <button
                class="refresh-btn"
                onclick="window.heliosApp.toggleTheme()"
                title="Zmień motyw"
              >
                <span class="material-symbols-rounded">dark_mode</span>
              </button>
              <button
                class="refresh-btn"
                onclick="window.heliosApp.loadData(true)"
                title="Odśwież dane"
              >
                <span class="material-symbols-rounded">refresh</span>
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Day Picker - like Helios -->
      <div class="day-picker-wrapper">
        <div class="container day-picker-container">
          <div class="day-picker" id="dayPicker">
            <!-- Dynamically generated -->
          </div>
          <button
            class="coming-soon-tab"
            id="comingSoonTab"
            onclick="window.heliosApp.showComingSoon()"
          >
            <span class="material-symbols-rounded">upcoming</span>
            <span>Zapowiedzi</span>
          </button>

          <div class="more-menu-wrapper">
            <button
              class="more-menu-btn"
              onclick="window.heliosApp.toggleMoreDropdown()"
              title="Więcej"
            >
              <span class="material-symbols-rounded">more_horiz</span>
            </button>
            <div class="more-dropdown" id="moreDropdown" style="display: none;">
              <button onclick="window.heliosApp.showMovieLibrary(); window.heliosApp.toggleMoreDropdown();">
                <span class="material-symbols-rounded">video_library</span>
                <span>Biblioteka filmów</span>
              </button>
              <button onclick="window.heliosApp.toggleCalendarModal(); window.heliosApp.toggleMoreDropdown();">
                <span class="material-symbols-rounded">calendar_month</span>
                <span>Kalendarz</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <main class="main">
        <div class="container">
          <!-- Archive Banner (injected by JS) -->
          <div id="archiveBannerContainer"></div>
          
          <!-- Prediction Banner (shown when data available) -->
          <div
            class="prediction-banner"
            id="predictionBanner"
            style="display: none"
          >
            <span class="material-symbols-rounded">trending_up</span>
            <div class="prediction-text">
              <span class="prediction-title" id="predictionTitle"
                >Predykcja obłożenia</span
              >
              <span class="prediction-value" id="predictionValue"></span>
            </div>
            <button
              class="ai-insights-btn"
              onclick="window.heliosApp.toggleAIInsights()"
              title="Myśli AI"
            >
              <span class="material-symbols-rounded">psychology</span>
              <span>Myśli AI</span>
            </button>
          </div>

          <!-- Stats Bar -->
          <div class="stats-bar">
            <div class="stat-item">
              <span class="material-symbols-rounded">theaters</span>
              <div class="stat-content">
                <span class="stat-value" id="totalMovies">0</span>
                <span class="stat-label">Filmów</span>
              </div>
            </div>
            <div class="stat-item">
              <span class="material-symbols-rounded">schedule</span>
              <div class="stat-content">
                <span class="stat-value" id="totalScreenings">0</span>
                <span class="stat-label">Seansów</span>
              </div>
            </div>
            <div class="stat-item">
              <span class="material-symbols-rounded">event_seat</span>
              <div class="stat-content">
                <span class="stat-value" id="seatsRatio">0/0</span>
                <span class="stat-label">Sprzedanych / Wszystkich</span>
              </div>
            </div>
            <div class="stat-item highlight">
              <span class="material-symbols-rounded">analytics</span>
              <div class="stat-content">
                <span class="stat-value" id="avgOccupancy">0%</span>
                <span class="stat-label">Śr. obłożenie</span>
              </div>
            </div>
            <div class="stat-item muted">
              <span class="material-symbols-rounded">update</span>
              <div class="stat-content">
                <span class="stat-value" id="lastUpdate">-</span>
                <span class="stat-label">Aktualizacja</span>
              </div>
            </div>
          </div>

          <!-- Hall Status -->
          <section class="section" id="hallSection">
            <h2 class="section-title">
              <span class="material-symbols-rounded">theater_comedy</span>
              Status Sal
            </h2>
            <div id="hallStatus" class="hall-grid">
              <!-- JS rendered content -->
            </div>
          </section>

          <!-- Histogram -->
          <section class="section histogram-section">
            <h2 class="section-title">
              <span class="material-symbols-rounded">bar_chart</span>
              Obłożenie w ciągu dnia
            </h2>
            <div class="histogram-container">
              <div id="histogram" class="histogram"></div>
              <div class="histogram-labels" id="histogramLabels"></div>
            </div>
          </section>

          <!-- Repertoire -->
          <section class="section repertoire-section" id="repertoireSection">
            <h2 class="section-title">
              <span class="material-symbols-rounded">movie</span>
              Repertuar
              <button
                class="timeline-toggle-btn"
                onclick="window.heliosApp.toggleTimeline()"
                title="Oś czasu seansów"
              >
                <span class="material-symbols-rounded">schedule</span>
                <span>Oś czasu</span>
              </button>
            </h2>
            <div id="moviesContainer" class="movies-container">
              <div class="loading">
                <div class="spinner"></div>
                <p>Ładowanie repertuaru...</p>
              </div>
            </div>
          </section>

          <!-- Coming Soon Section (hidden by default) -->
          <section
            class="section coming-soon-section"
            id="comingSoonSection"
            style="display: none"
          >
            <h2 class="section-title">
              <span class="material-symbols-rounded">upcoming</span>
              Zapowiedzi
              <button
                class="back-to-schedule"
                onclick="window.heliosApp.showSchedule()"
              >
                <span class="material-symbols-rounded">arrow_back</span>
                Wróć do repertuaru
              </button>
            </h2>
            <div id="comingSoonContainer" class="coming-soon-container">
              <div class="loading">
                <div class="spinner"></div>
                <p>Ładowanie zapowiedzi...</p>
              </div>
            </div>
          </section>

          <!-- AI Insights Section (hidden by default) -->
          <section
            class="section ai-insights-section"
            id="aiInsightsSection"
            style="display: none"
          >
            <h2 class="section-title">
              <span class="material-symbols-rounded">psychology</span>
              Myśli AI
              <button
                class="back-to-schedule"
                onclick="window.heliosApp.toggleAIInsights()"
              >
                <span class="material-symbols-rounded">close</span>
                Zamknij
              </button>
            </h2>
            <div id="aiInsightsContainer" class="ai-insights-container">
              <p class="ai-insights-intro">Co algorytm nauczył się z analizy danych...</p>
            </div>
          </section>

          <!-- Timeline Section (hidden by default) -->
          <section
            class="section timeline-section"
            id="timelineSection"
            style="display: none"
          >
            <h2 class="section-title">
              <span class="material-symbols-rounded">schedule</span>
              Oś czasu
              <div class="timeline-controls">
                <div class="sort-switch">
                  <button class="sort-btn active" onclick="window.heliosApp.setTimelineSort('start')" data-sort="start">Start</button>
                  <button class="sort-btn" onclick="window.heliosApp.setTimelineSort('end')" data-sort="end">Koniec</button>
                </div>
                <button
                  class="back-to-schedule"
                  onclick="window.heliosApp.toggleTimeline()"
                >
                  <span class="material-symbols-rounded">close</span>
                  Zamknij
                </button>
              </div>
            </h2>
            <div id="timelineContainer" class="timeline-container">
              <!-- JS rendered -->
            </div>
          </section>
        </div>
      </main>

      <!-- Footer -->
      <footer class="footer">
        <div class="container">
          <div class="footer-content">
            <span class="material-symbols-rounded">info</span>
            <span>Dane pobrane z helios.pl • Narzędzie nieoficjalne • Build: <?php echo $buildVersion; ?></span>
            <!-- Install PWA button (always visible) -->
            <button id="installBtn" class="footer-install-btn" onclick="installApp()">
              <span class="material-symbols-rounded">download</span>
              <span>Dodaj do ekranu</span>
            </button>
          </div>
        </div>
      </footer>
    </div>

    <!-- Scroll to Top Button (mobile) -->
    <button id="scrollToTopBtn" class="scroll-to-top-btn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
      <span class="material-symbols-rounded">arrow_upward</span>
    </button>

    <!-- Random version for JS to force reload -->
    <script src="app.js?ver=<?php echo time(); ?>"></script>
  </body>
</html>
