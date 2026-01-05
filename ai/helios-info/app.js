/**
 * Helios Occupancy - Helios Style with Day Picker
 */

// Czas trwania bloku reklamowego - Helios USUNĄŁ te dane z API
// Inteligentne szacowanie na podstawie typu filmu (z api.php):
// - Filmy dla dzieci: 15 min
// - Premiera (≤7 dni): 25 min
// - 8-14 dni po premierze: 22 min
// - Domyślnie: 20 min (jak kinobezreklam.org)
const DEFAULT_AD_DURATION_MINUTES = 20;

class HeliosApp {
  constructor() {
    this.data = null;
    this.currentDate = this.getBusinessDate();
    this.isArchiveMode = false;
    this.toastContainer = null;
    // Alert threshold (default 70%, saved in localStorage)
    this.alertThreshold =
      parseInt(localStorage.getItem("alertThreshold")) || 70;

    // Timeline sort preference
    this.timelineSort = localStorage.getItem("helios-timeline-sort") || "start";

    this.init();
  }

  // Toast notification system
  showToast(message, type = "info", duration = 3000) {
    if (!this.toastContainer) {
      this.toastContainer = document.createElement("div");
      this.toastContainer.className = "toast-container";
      document.body.appendChild(this.toastContainer);
    }

    const icons = {
      success: "check_circle",
      error: "error",
      warning: "warning",
      info: "info",
    };

    const toast = document.createElement("div");
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <span class="material-symbols-rounded">${icons[type] || "info"}</span>
      <span>${message}</span>
    `;

    this.toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.classList.add("hiding");
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  // Helper to replace unicode arrows with Material Icons
  replaceArrowsWithIcons(text) {
    if (!text) return "";
    return text
      .replace(
        /↗/g,
        '<span class="material-symbols-rounded inline-icon" style="color: #4caf50;">trending_up</span>'
      )
      .replace(
        /↘/g,
        '<span class="material-symbols-rounded inline-icon" style="color: #f44336;">trending_down</span>'
      );
  }

  /**
   * Get "business date" for cinema - screenings that run past midnight
   * are still considered part of the previous day until 5:00 AM
   */
  getBusinessDate() {
    const now = new Date();
    const hour = now.getHours();

    // Between midnight (00:00) and 5:00 AM, use previous day's date
    // This handles late-night screenings like Avatar ending at 00:45
    if (hour < 5) {
      now.setDate(now.getDate() - 1);
    }

    // Format as YYYY-MM-DD in LOCAL timezone (not UTC!)
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  async init() {
    this.setupDayPicker();
    this.setupCollapsibles();
    this.setupScrollEffects();
    this.setupThemeToggle();
    this.loadData();

    // Auto-refresh hall status every 30 seconds for live countdown
    setInterval(() => {
      if (this.data) {
        this.renderHallStatus();
      }
    }, 30000);

    // Check for onboarding
    this.checkOnboarding();

    // Global click handler to close tooltips on mobile
    document.addEventListener("click", (e) => {
      // Don't close if clicking on chart canvas
      if (e.target.tagName === "CANVAS") return;

      // Hide all chart tooltips
      document.querySelectorAll(".custom-chart-tooltip").forEach((tooltip) => {
        tooltip.style.opacity = "0";
        tooltip.style.pointerEvents = "none";
      });
    });
  }

  // Header glass effect on scroll + floating badge
  setupScrollEffects() {
    const header = document.querySelector(".header");
    const dayPickerWrapper = document.querySelector(".day-picker-wrapper");

    // Create floating day badge
    this.floatingBadge = document.createElement("div");
    this.floatingBadge.className = "floating-day-badge";
    document.body.appendChild(this.floatingBadge);

    window.addEventListener(
      "scroll",
      () => {
        // Header scroll effect
        if (header) {
          if (window.scrollY > 10) {
            header.classList.add("scrolled");
          } else {
            header.classList.remove("scrolled");
          }
        }

        // Floating day badge visibility
        if (dayPickerWrapper) {
          const dayPickerBottom =
            dayPickerWrapper.getBoundingClientRect().bottom;
          if (dayPickerBottom < 0) {
            this.floatingBadge.classList.add("visible");
          } else {
            this.floatingBadge.classList.remove("visible");
          }
        }

        // Scroll-to-top button visibility (CSS handles mobile-only display)
        const scrollTopBtn = document.getElementById("scrollToTopBtn");
        if (scrollTopBtn) {
          if (window.scrollY > 300) {
            scrollTopBtn.classList.add("visible");
          } else {
            scrollTopBtn.classList.remove("visible");
          }
        }
      },
      { passive: true }
    );
  }

  // Update floating badge text
  updateFloatingBadge() {
    if (this.floatingBadge) {
      const date = new Date(this.currentDate);
      const dayName = date.toLocaleDateString("pl-PL", { weekday: "short" });
      const dayNum = date.getDate();
      const month = date.toLocaleDateString("pl-PL", { month: "short" });
      this.floatingBadge.textContent = `${dayName.toUpperCase()} ${dayNum} ${month}`;
    }
  }

  // Dark mode toggle with localStorage
  setupThemeToggle() {
    const saved = localStorage.getItem("helios-theme");
    if (saved === "dark") {
      document.documentElement.setAttribute("data-theme", "dark");
    }
  }

  toggleTheme() {
    const current = document.documentElement.getAttribute("data-theme");
    const newTheme = current === "dark" ? "light" : "dark";

    if (newTheme === "dark") {
      document.documentElement.setAttribute("data-theme", "dark");
      localStorage.setItem("helios-theme", "dark");
    } else {
      document.documentElement.removeAttribute("data-theme");
      localStorage.setItem("helios-theme", "light");
    }
  }

  // Animated counter utility
  animateCountUp(element, target, duration = 1000) {
    if (!element) return;

    const start = 0;
    const startTime = performance.now();

    const update = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // ease out cubic
      const current = Math.round(start + (target - start) * eased);
      element.textContent = current.toLocaleString("pl-PL");

      if (progress < 1) {
        requestAnimationFrame(update);
      }
    };

    requestAnimationFrame(update);
  }

  setupCollapsibles() {
    // Hall Section
    const hallSection = document.getElementById("hallSection");
    if (hallSection) {
      const title = hallSection.querySelector(".section-title");
      if (title) {
        // Auto-collapse on mobile (less than 768px)
        const isMobile = window.innerWidth < 768;
        if (isMobile) {
          hallSection.classList.add("collapsed");
        }

        // Add toggle icon
        const icon = document.createElement("span");
        icon.className = "material-symbols-rounded section-toggle-icon";
        // If collapsed (mobile), show expand_more (arrow down), else expand_less (arrow up)
        // Actually CSS handles rotation of expand_less to down if collapsed.
        // But let's stick to consistent icon: expand_less (Arrow Up) for Expanded state.
        // CSS rotation: .collapsed .icon { transform: rotate(-180deg) } -> Arrow Up rotated 180 = Arrow Down.
        // So we always start with expand_less (Arrow Up).
        icon.textContent = "expand_less";
        title.appendChild(icon);

        // Toggle logic
        title.onclick = () => {
          hallSection.classList.toggle("collapsed");
        };
      }
    }
  }

  setupDayPicker() {
    const container = document.getElementById("dayPicker");
    // Use business date as base (cinema day ends at 5:00 AM)
    const businessDateStr = this.getBusinessDate();
    // Parse date string manually to avoid timezone issues
    const [year, month, day] = businessDateStr.split("-").map(Number);
    const today = new Date(year, month - 1, day, 12, 0, 0); // Local time, noon
    const days = [];

    // Generate 14 days starting from business date
    for (let i = 0; i < 14; i++) {
      const d = new Date(today);
      d.setDate(today.getDate() + i);
      days.push(d);
    }

    const dayNames = ["Nd", "Pn", "Wt", "Śr", "Cz", "Pt", "So"];
    const monthNames = [
      "sty",
      "lut",
      "mar",
      "kwi",
      "maj",
      "cze",
      "lip",
      "sie",
      "wrz",
      "paź",
      "lis",
      "gru",
    ];

    container.innerHTML = days
      .map((d, i) => {
        const dateStr = d.toISOString().split("T")[0];
        const isToday = i === 0;
        const isTomorrow = i === 1;
        const isActive = dateStr === this.currentDate;

        let label = "";
        if (isToday) label = "Dziś";
        else if (isTomorrow) label = "Jutro";
        else label = dayNames[d.getDay()];

        return `
                <button class="day-btn ${
                  isActive ? "active" : ""
                }" data-date="${dateStr}">
                    <span class="day-label">${label}</span>
                    <span class="day-date">${d.getDate()} ${
          monthNames[d.getMonth()]
        }</span>
                </button>
            `;
      })
      .join("");

    container.addEventListener("click", (e) => {
      const btn = e.target.closest(".day-btn");
      if (btn && btn.dataset.date !== this.currentDate) {
        this.currentDate = btn.dataset.date;
        container
          .querySelectorAll(".day-btn")
          .forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        // Load data for the new date
        this.loadData();
      }
    });
  }

  async loadData(force = false) {
    // Determine archive mode only if force is false (user navigation)
    // If force is true (refresh), we keep current mode or re-eval
    const businessDate = this.getBusinessDate();
    this.isArchiveMode = this.currentDate < businessDate;

    const loader = document.getElementById("fullPageLoader");
    const container = document.getElementById("moviesContainer");

    // Show skeleton loaders instead of full-page spinner (better UX)
    this.showSkeletonLoaders();

    try {
      const url = `api.php?date=${this.currentDate}&_t=${Date.now()}${
        force ? "&refresh=1" : ""
      }`;
      const response = await fetch(url);
      if (!response.ok) throw new Error("Failed");
      this.data = await response.json();
      this.render();
      this.updateFloatingBadge();

      // Show success toast only on manual refresh
      if (force) {
        this.showToast("Dane zaktualizowane", "success");
      }
    } catch (error) {
      console.error("Error:", error);
      this.showToast("Błąd ładowania danych", "error");
      container.innerHTML = `
        <div class="error-state">
          <span class="material-symbols-rounded">cloud_off</span>
          <h3>Nie udało się załadować danych</h3>
          <p>Sprawdź połączenie z internetem i spróbuj ponownie</p>
          <button class="retry-btn" onclick="window.heliosApp.loadData(true)">
            <span class="material-symbols-rounded">refresh</span>
            Spróbuj ponownie
          </button>
        </div>
      `;
    } finally {
      // Hide full-page loader if shown
      if (loader) loader.classList.add("hidden");
    }
  }

  // Show premium skeleton placeholders while data loads
  showSkeletonLoaders() {
    const container = document.getElementById("moviesContainer");
    if (container) {
      // Generate 4 premium skeleton movie cards
      const skeletonCards = Array(4)
        .fill(0)
        .map(
          () => `
        <div class="movie-card skeleton-card">
          <div class="movie-poster skeleton skeleton-image"></div>
          <div class="movie-info">
            <div class="skeleton" style="width: 85%; height: 22px; margin-bottom: 12px;"></div>
            <div class="skeleton" style="width: 65%; height: 15px; margin-bottom: 8px;"></div>
            <div class="skeleton" style="width: 45%; height: 15px; margin-bottom: 16px;"></div>
            <div class="skeleton-screenings">
              <div class="skeleton" style="width: 56px; height: 32px;"></div>
              <div class="skeleton" style="width: 56px; height: 32px;"></div>
              <div class="skeleton" style="width: 56px; height: 32px;"></div>
            </div>
          </div>
        </div>
      `
        )
        .join("");
      container.innerHTML = skeletonCards;
    }

    // Skeleton for hall status - premium glass cards
    const hallStatus = document.getElementById("hallStatus");
    if (hallStatus) {
      hallStatus.innerHTML = Array(4)
        .fill(0)
        .map(
          () => `
        <div class="hall-card skeleton-hall">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div class="skeleton" style="width: 44px; height: 44px; border-radius: 12px;"></div>
            <div style="flex: 1;">
              <div class="skeleton" style="width: 70%; height: 16px; margin-bottom: 8px;"></div>
              <div class="skeleton" style="width: 50%; height: 12px;"></div>
            </div>
          </div>
        </div>
      `
        )
        .join("");
    }

    // Skeleton for prediction banner - modify existing elements, don't replace
    const predictionBanner = document.getElementById("predictionBanner");
    const predictionTitle = document.getElementById("predictionTitle");
    const predictionValue = document.getElementById("predictionValue");
    if (predictionBanner && predictionTitle && predictionValue) {
      predictionTitle.innerHTML =
        '<div class="skeleton" style="width: 160px; height: 14px; border-radius: 4px;"></div>';
      predictionValue.innerHTML =
        '<div class="skeleton" style="width: 200px; height: 18px; border-radius: 6px;"></div>';
      predictionBanner.style.display = "flex";
    }

    // Skeleton for histogram - animated bars
    const histogram = document.getElementById("histogramContainer");
    if (histogram) {
      histogram.innerHTML = `
        <div style="display: flex; align-items: flex-end; gap: 6px; height: 140px; padding: 16px 12px;">
          ${Array(13)
            .fill(0)
            .map(
              (_, i) => `
            <div class="skeleton skeleton-histogram-bar" style="flex: 1; height: ${
              25 + Math.sin(i * 0.5) * 20 + Math.random() * 35
            }%;"></div>
          `
            )
            .join("")}
        </div>
        <div style="display: flex; gap: 4px; padding: 0 12px;">
          ${Array(13)
            .fill(0)
            .map(
              () =>
                `<div class="skeleton" style="flex: 1; height: 12px; border-radius: 4px;"></div>`
            )
            .join("")}
        </div>
      `;
    }

    // Skeleton for stats bar values - subtle pills
    document.querySelectorAll(".stat-value").forEach((el) => {
      el.innerHTML =
        '<div class="skeleton" style="width: 48px; height: 24px; border-radius: 6px;"></div>';
    });
  }

  render() {
    this.renderArchiveBanner();
    this.renderStats();
    this.renderHallStatus();
    this.renderPrediction();
    this.renderHistogram();
    this.renderMovies();
    this.updateLastUpdate();
  }

  renderArchiveBanner() {
    const container = document.getElementById("archiveBannerContainer");
    if (!container) return;

    if (this.isArchiveMode && this.currentDate) {
      container.innerHTML = `
        <div class="archive-banner" style="margin-bottom: 20px;">
            <div class="archive-info">
                <span class="material-symbols-rounded">history</span>
                <span>Archiwum: <strong>${this.currentDate}</strong></span>
            </div>
            <button onclick="window.location.reload()" class="exit-archive-btn">
                <span class="material-symbols-rounded">close</span> Wróć do dziś
            </button>
        </div>
      `;
    } else {
      container.innerHTML = "";
    }
  }

  renderHallStatus() {
    const container = document.getElementById("hallStatus");
    if (!container) return;

    // Auto-collapse in archive mode
    const hallSection = document.getElementById("hallSection");
    if (hallSection && this.isArchiveMode) {
      hallSection.classList.add("collapsed");
    }

    // 1. Group by screenId
    const halls = {};
    const now = new Date();
    // Use fixed date for testing if needed, or real now
    // For this app, we assume "today" is the loaded date, so we use real time hour:minute
    // but combined with the date from data?
    // Ideally we compare timestamps. Data has timestamps now.

    const nowTs = Math.floor(Date.now() / 1000);

    if (!this.data.movies) return;

    this.data.movies.forEach((movie) => {
      if (!movie.screenings) return;
      movie.screenings.forEach((s) => {
        const sid = s.screenId || "unknown";
        let key = sid;
        // Group by hall name to deduplicate
        if (s.hall && s.hall !== "N/A" && !s.hall.startsWith("Sala ?")) {
          key = s.hall;
        }

        if (!halls[key]) {
          const safeSid = sid === "unknown" ? "?" : sid;
          halls[key] = {
            id: sid,
            name:
              s.hall === "N/A"
                ? `Sala ${
                    safeSid.length > 4
                      ? safeSid.substring(0, 4) + ".."
                      : safeSid
                  }`
                : s.hall,
            screenings: [],
          };
        }
        halls[key].screenings.push({
          ...s,
          movieTitle: movie.movieTitle,
          poster: movie.poster,
          duration: movie.duration,
          adDuration: s.adDuration || DEFAULT_AD_DURATION_MINUTES,
          startTs: s.timestamp,
          endTs:
            s.timestamp +
            ((movie.duration || 120) +
              (s.adDuration || DEFAULT_AD_DURATION_MINUTES)) *
              60,
        });
      });
    });

    // 2. Sort halls
    const sortedHalls = Object.values(halls).sort((a, b) =>
      a.name.localeCompare(b.name, undefined, { numeric: true })
    );

    // 3. Render
    let html = "";

    if (sortedHalls.length === 0) {
      container.innerHTML = '<div class="no-data">Brak danych o salach</div>';
      return;
    }

    sortedHalls.forEach((hall) => {
      // Find current or next screening
      // Sort screenings by time
      hall.screenings.sort((a, b) => a.startTs - b.startTs);

      let currentS = null;
      let nextS = null;

      for (const s of hall.screenings) {
        if (nowTs >= s.startTs && nowTs < s.endTs) {
          currentS = s;
          break;
        }
        if (nowTs < s.startTs) {
          if (!nextS) nextS = s;
          break; // Found first upcoming
        }
      }

      // Card content
      let content = "";
      let statusClass = "idle";

      if (currentS) {
        statusClass = "playing";
        const progress = Math.min(
          100,
          Math.max(
            0,
            ((nowTs - currentS.startTs) / (currentS.endTs - currentS.startTs)) *
              100
          )
        );
        const timeLeft = Math.ceil((currentS.endTs - nowTs) / 60);

        // Format end time
        const endDate = new Date(currentS.endTs * 1000);
        const endTime = `${String(endDate.getHours()).padStart(
          2,
          "0"
        )}:${String(endDate.getMinutes()).padStart(2, "0")}`;

        content = `
                <div class="hall-movie">
                    <div class="hall-movie-info">
                        <span class="hall-status-label">Trwa seans</span>
                        <h4 class="hall-movie-title" title="${currentS.movieTitle}">${currentS.movieTitle}</h4>
                        <div class="hall-meta">
                            <span>${currentS.time}→${endTime} (-${timeLeft} min)</span>
                            <span>•</span>
                            <span>${currentS.stats.occupied}/${currentS.stats.total} (${currentS.stats.occupancyPercent}%)</span>
                        </div>
                    </div>
                </div>
                <div class="progress-container">
                    <div class="progress-bar" style="width: ${progress}%"></div>
                </div>
            `;
      } else if (nextS) {
        const timeToStart = Math.ceil((nextS.startTs - nowTs) / 60);
        const label =
          timeToStart > 60
            ? `Za ${Math.round(timeToStart / 60)}h`
            : `Za ${timeToStart} min`;

        // Format end time for next screening
        const nextEndDate = new Date(nextS.endTs * 1000);
        const nextEndTime = `${String(nextEndDate.getHours()).padStart(
          2,
          "0"
        )}:${String(nextEndDate.getMinutes()).padStart(2, "0")}`;

        content = `
                <div class="hall-movie next">
                    <div class="hall-movie-info">
                        <span class="hall-status-label future">Następnie: ${nextS.time}→${nextEndTime} (${label})</span>
                        <h4 class="hall-movie-title muted" title="${nextS.movieTitle}">${nextS.movieTitle}</h4>
                    </div>
                </div>
             `;
      } else {
        content = `<div class="hall-empty"><span class="material-symbols-rounded">nightlight</span> Koniec seansów</div>`;
      }

      html += `
            <div class="hall-card ${statusClass}">
                <div class="hall-header">
                    <span class="hall-name">${hall.name}</span>
                    ${
                      currentS
                        ? `<span class="hall-badge occupied">${currentS.stats.occupied} os.</span>`
                        : ""
                    }
                </div>
                ${content}
            </div>
        `;
    });

    container.innerHTML = html;
  }

  renderPrediction() {
    const banner = document.getElementById("predictionBanner");
    const title = document.getElementById("predictionTitle");
    const value = document.getElementById("predictionValue");
    const pred = this.data.prediction;

    // Safety check - if elements don't exist (e.g., skeleton is showing), bail out
    if (!banner || !title || !value) return;

    // Hide prediction in archive mode - we show actual historical data instead
    if (this.isArchiveMode) {
      banner.style.display = "none";
      return;
    }

    // Only require valid prediction with totals - additional can be null for future dates
    if (!pred || !pred.totals || pred.basedOnDays < 1) {
      banner.style.display = "none";
      return;
    }

    const add = pred.additional || { range: { min: 0, max: 0 } };
    const dayTypeLabels = {
      weekend: "weekendy",
      workday: "dni robocze",
      tuesday: "wtorki",
    };

    banner.style.display = "flex";

    // Build factors data
    const factors = pred.factors || {};
    const hourly = pred.hourly || {};

    // Day of week (always show)
    const dayNames = {
      0: "niedziela",
      1: "poniedziałek",
      2: "wtorek",
      3: "środa",
      4: "czwartek",
      5: "piątek",
      6: "sobota",
    };
    const currentDay = new Date(this.data.date).getDay();
    const isWeekend = currentDay === 0 || currentDay === 6;

    // Calculate individual multiplier impacts
    const factorCards = [];

    // Day type factor
    factorCards.push({
      icon: isWeekend ? "weekend" : "work",
      name: `Dzień tygodnia: ${dayNames[currentDay]}`,
      description: isWeekend
        ? "Weekendy to tradycyjnie najlepsze dni dla kin. Ludzie mają wolne od pracy i szkoły, planują wspólne wyjścia rodzinne. Frekwencja jest zazwyczaj 30-50% wyższa niż w dni robocze, ze szczytem w sobotnie popołudnie."
        : pred.dayType === "tuesday"
        ? "Wtorki to specjalny dzień promocyjny w wielu kinach (bilety w niższych cenach). Przyciąga to widzów szukających oszczędności, ale ogólna frekwencja jest niższa niż w weekendy."
        : "Dzień roboczy – frekwencja zazwyczaj niższa niż w weekend. Wieczorne seanse (po 18:00) są popularniejsze, gdy ludzie wracają z pracy i szukają rozrywki.",
      impact: isWeekend
        ? "+35%"
        : pred.dayType === "tuesday"
        ? "+15%"
        : "bazowa",
      color: isWeekend ? "positive" : "neutral",
    });

    // Base historical data
    factorCards.push({
      icon: "history",
      name: "Dane historyczne",
      description: `Algorytm analizuje ${
        pred.basedOnDays
      } poprzednich dni tego samego typu (${
        dayTypeLabels[pred.dayType]
      }). Im więcej danych historycznych, tym dokładniejsza prognoza. System uczy się wzorców sezonowych, trendów i anomalii z przeszłości.`,
      impact: "bazowa",
      color: "neutral",
    });

    // Season factor
    const month = new Date(this.data.date).getMonth();
    const seasonInfo = this.getSeasonInfo(month);
    factorCards.push({
      icon: seasonInfo.icon,
      name: `Sezon: ${seasonInfo.name}`,
      description: seasonInfo.description,
      impact: seasonInfo.impact,
      color: seasonInfo.color,
    });

    // Weather (get average from hourly + actual weather data)
    const weatherMults = Object.values(hourly).map(
      (h) => h.multipliers?.weather || 1
    );
    const avgWeather =
      weatherMults.length > 0
        ? weatherMults.reduce((a, b) => a + b, 0) / weatherMults.length
        : 1;

    // Get ACTUAL weather conditions from prediction data (not just multiplier)
    const weatherData = this.data?.prediction?.weather || {};
    const actualTemp = weatherData.avgTemp ?? 15;
    const hasRain = weatherData.hasRain ?? false;

    // Determine weather type based on ACTUAL conditions, not multiplier sign
    // Use realistic impact estimates based on weather research, not Factor Learning multiplier
    let weatherIcon, weatherDesc, weatherColor, weatherImpact;

    if (hasRain) {
      // Rainy weather = good for cinema (+10-20%)
      weatherIcon = "rainy";
      weatherDesc =
        "Deszcz zachęca do wizyty w kinie. Ludzie szukają rozrywki w ciepłym, suchym wnętrzu. Każdy dodatkowy 1mm opadu zwiększa frekwencję o około 2-3%.";
      weatherColor = "positive";
      weatherImpact = "+10-20%";
    } else if (actualTemp < 5) {
      // Cold weather = good for cinema (+5-15%)
      weatherIcon = "ac_unit";
      weatherDesc = `Zimno (${Math.round(
        actualTemp
      )}°C) sprzyja wizytom w kinie. Ludzie szukają ciepłych miejsc na spędzenie czasu. Efekt jest szczególnie silny w zimowe wieczory.`;
      weatherColor = "positive";
      weatherImpact = "+5-15%";
    } else if (actualTemp > 22 && !hasRain) {
      // Warm, dry weather = bad for cinema (-15-25%)
      weatherIcon = "sunny";
      weatherDesc =
        "Słoneczna, ciepła pogoda to konkurencja dla kina. Ludzie wybierają parki, ogródki restauracyjne i aktywności na świeżym powietrzu. Efekt jest szczególnie silny w weekendy wiosenne i letnie.";
      weatherColor = "negative";
      weatherImpact = "-15-25%";
    } else {
      // Neutral weather
      weatherIcon = "cloud";
      weatherDesc =
        "Pogoda jest umiarkowana – ani bardzo słoneczna, ani deszczowa. Nie ma znaczącego wpływu na decyzje widzów o wizycie w kinie.";
      weatherColor = "neutral";
      weatherImpact = "neutralna";
    }

    factorCards.push({
      icon: weatherIcon,
      name: "Wpływ pogody",
      description: weatherDesc,
      impact: weatherImpact,
      color: weatherColor,
    });

    // Payday factor (end of month / beginning)
    const dayOfMonth = new Date(this.data.date).getDate();
    if (dayOfMonth >= 25 || dayOfMonth <= 5) {
      factorCards.push({
        icon: "payments",
        name: "Okres wypłat",
        description:
          "Koniec miesiąca i początek nowego to okres, gdy większość pracowników otrzymuje wypłaty. Ludzie mają więcej gotówki na wydatki uznaniowe – bilety do kina, popcorn, napoje. Ten efekt jest szczególnie widoczny w dni robocze.",
        impact: "+8%",
        color: "positive",
      });
    }

    // Holiday
    if (factors.holiday) {
      factorCards.push({
        icon: "celebration",
        name: factors.holiday[0] || "Dzień wolny",
        description:
          "Święta państwowe i dni wolne od pracy zwiększają frekwencję. Rodziny szukają wspólnych aktywności, a kino to popularna opcja. Efekt jest najsilniejszy w święta zimowe (Boże Narodzenie, Sylwester) oraz Wielkanoc.",
        impact: `+${Math.round((factors.holidayMultiplier - 1) * 100)}%`,
        color: "positive",
      });
    }

    // School holiday
    if (factors.schoolHoliday) {
      factorCards.push({
        icon: "school",
        name: "Ferie szkolne",
        description:
          "W czasie ferii dzieci mają wolne, a rodziny szukają rozrywki. Seanse poranne i popołudniowe (10:00-15:00) są znacznie popularniejsze. Filmy animowane i familijne notują wzrosty nawet o 50%.",
        impact: "+20%",
        color: "positive",
      });
    }

    // Special Period (exam sessions, pre-holiday, summer blockbuster, long weekends)
    if (factors.specialPeriod) {
      const sp = factors.specialPeriod;
      const spPct = Math.round((sp.multiplier - 1) * 100);
      let spIcon = "event";
      if (sp.name.toLowerCase().includes("sesja")) spIcon = "menu_book";
      else if (
        sp.name.toLowerCase().includes("wakacje") ||
        sp.name.toLowerCase().includes("letni")
      )
        spIcon = "beach_access";
      else if (
        sp.name.toLowerCase().includes("weekend") ||
        sp.name.toLowerCase().includes("majówka")
      )
        spIcon = "weekend";
      else if (sp.name.toLowerCase().includes("przed")) spIcon = "redeem";

      factorCards.push({
        icon: spIcon,
        name: sp.name,
        description:
          sp.type === "positive"
            ? "Więcej widzów w tym okresie"
            : "Mniej widzów (studenci zajęci)",
        impact: spPct > 0 ? `+${spPct}%` : `${spPct}%`,
        color: sp.type,
      });
    }

    // Sports
    if (
      factors.hasSportsImpact &&
      factors.sports &&
      factors.sports.length > 0
    ) {
      const localMatch = factors.sports.find((s) => s.isLocal);
      if (localMatch) {
        factorCards.push({
          icon: "sports_soccer",
          name: "Mecz lokalny",
          description: `${localMatch.name} – mecze lokalnych drużyn (Widzew, ŁKS) przyciągają uwagę mieszkańców Łodzi. Część potencjalnych widzów kina wybierze oglądanie meczu w domu lub pubie. Efekt jest najsilniejszy w godzinach transmisji.`,
          impact: "-20%",
          color: "negative",
        });
      }
    }

    // Ukrainian films (detect from movies)
    const movies = this.data.movies || [];
    const uaFilms = movies.filter((m) => {
      const title = (m.movieTitle || "").toLowerCase();
      const country = (m.country || "").toLowerCase();
      return (
        title.includes("(ua)") ||
        title.includes("/ ua") ||
        country.includes("ukrain")
      );
    });
    if (uaFilms.length > 0) {
      factorCards.push({
        icon: "flag",
        name: "Repertuar ukraiński",
        description: `W repertuarze znajduje się ${uaFilms.length} film(ów) z ukraińskim dubbingiem lub napisami. Po 2022 roku społeczność ukraińska w Polsce znacząco wzrosła i stanowi istotny segment widzów kinowych.`,
        impact: "+10%",
        color: "positive",
      });
    }

    // Children's Movies
    const childrenFilms = movies.filter((m) => m.isForChildren);
    if (childrenFilms.length > 0) {
      factorCards.push({
        icon: "child_care",
        name: "Repertuar rodzinny",
        description: `W repertuarze jest ${childrenFilms.length} filmów animowanych/rodzinnych. Te tytuły przyciągają rodziny z dziećmi, szczególnie w weekendy i w godzinach 10:00-15:00. To stabilna grupa odbiorców planująca wizyty z wyprzedzeniem.`,
        impact: isWeekend ? "+15%" : "Info",
        color: isWeekend ? "positive" : "neutral",
      });
    }

    // Premieres (Hype)
    const today = new Date(this.data.date);
    const premieres = movies.filter((m) => {
      if (!m.premiereDate) return false;
      const pDate = new Date(m.premiereDate);
      const diffTime = Math.abs(today - pDate);
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      return diffDays <= 14 && pDate <= today; // Last 2 weeks
    });

    if (premieres.length > 0) {
      factorCards.push({
        icon: "local_fire_department",
        name: "Gorące premiery",
        description: `${premieres.length} film(ów) jest w pierwszych 2 tygodniach po premierze. Efekt "buzz" i chęć uniknięcia spoilerów motywują widzów do szybkiego obejrzenia. Duże blockbustery (Marvel, Disney) mogą zwiększyć frekwencję nawet o 40%.`,
        impact: "+20-40%",
        color: "positive",
      });
    }

    // Store factorCards for use in AI Insights panel
    this.data.prediction.factorCards = factorCards;

    // Build expandable HTML
    const factorBadgesHtml = factorCards
      .slice(1)
      .map(
        (f) =>
          `<span class="factor-badge ${f.color}"><span class="material-symbols-rounded">${f.icon}</span> ${f.impact}</span>`
      )
      .join("");

    // Confidence
    const confidence = pred.confidence || 0.5;
    const confLabel =
      confidence >= 0.8 ? "Wysoka" : confidence >= 0.5 ? "Średnia" : "Niska";
    const confClass =
      confidence >= 0.8 ? "high" : confidence >= 0.5 ? "medium" : "low";

    const version = pred.modelVersion || "v1";

    // Title (clickable to expand)
    title.innerHTML = `
      <div class="prediction-header" onclick="window.heliosApp.togglePredictionDetails()">
        <span class="material-symbols-rounded">psychology</span> 
        AI Predictor ${version} | Pewność: <span class="confidence ${confClass}">${confLabel}</span>
        ${factorBadgesHtml}
        <span class="material-symbols-rounded expand-icon">expand_more</span>
      </div>
    `;

    // Value
    const multiplier = factors.combinedMultiplier || 1.0;
    // Use adjustedOccupied (with multipliers) to match predictedPercent
    const totalPredicted =
      pred.totals.adjustedOccupied || pred.totals.predictedOccupied || 0;
    if (add.range.min === 0 && add.range.max === 0) {
      value.innerHTML = `Przewidywane obłożenie: <span class="range">~${
        pred.totals.predictedPercent
      }%</span> <span class="viewer-count">(~${totalPredicted.toLocaleString(
        "pl-PL"
      )} widzów)</span>`;
    } else {
      let multiplierInfo = "";
      if (multiplier !== 1.0) {
        multiplierInfo = ` <span class="multiplier">(×${multiplier.toFixed(
          2
        )})</span>`;
      }
      value.innerHTML = `Przewidujemy jeszcze <span class="range">${
        add.range.min
      }-${add.range.max}</span> osób → <span class="range">${
        pred.totals.predictedPercent
      }%</span> obłożenia <span class="viewer-count">(~${totalPredicted.toLocaleString(
        "pl-PL"
      )} widzów)</span>${multiplierInfo}`;
    }

    // Add expandable details section
    let detailsEl = document.getElementById("predictionDetails");
    if (!detailsEl) {
      detailsEl = document.createElement("div");
      detailsEl.id = "predictionDetails";
      detailsEl.className = "prediction-details";
      banner.parentNode.insertBefore(detailsEl, banner.nextSibling);
    }

    const factorCardsHtml = factorCards
      .map(
        (f) => `
      <div class="factor-card ${f.color}">
        <div class="factor-header">
          <span class="material-symbols-rounded">${f.icon}</span>
          <span class="factor-impact">${f.impact}</span>
        </div>
        <div class="factor-info">
          <span class="factor-name">${f.name}</span>
          <span class="factor-desc">${f.description}</span>
        </div>
      </div>
    `
      )
      .join("");

    detailsEl.innerHTML = `
      <div class="factors-grid">
        ${factorCardsHtml}
      </div>
      <div class="prediction-summary">
        <div class="summary-label">
          <span class="material-symbols-rounded">functions</span>
          <div class="summary-text">
            <span class="summary-title">Podsumowanie</span>
            <span class="summary-subtitle">Łączny Wpływ Czynników</span>
          </div>
        </div>
        <div>
          <span class="total-multiplier ${
            multiplier >= 1 ? "positive" : "negative"
          }">×${multiplier.toFixed(2)}</span>
          <div class="multiplier-label">Mnożnik całkowity</div>
        </div>
      </div>
    `;
  }

  togglePredictionDetails() {
    const details = document.getElementById("predictionDetails");
    const icon = document.querySelector(".expand-details-btn .expand-icon");
    if (details) {
      details.classList.toggle("expanded");
      if (icon) {
        icon.textContent = details.classList.contains("expanded")
          ? "expand_less"
          : "expand_more";
      }
    }
  }

  // Helper method for season information
  getSeasonInfo(month) {
    const seasons = {
      winter: {
        name: "Zimowy (święta)",
        icon: "ac_unit",
        description:
          "Grudzień to szczyt sezonu kinowego – święta, ferie zimowe, duże premiery. Ludzie szukają wspólnych aktywności w ciepłych wnętrzach. Frekwencja jest o 25-40% wyższa niż w typowych miesiącach.",
        impact: "+30%",
        color: "positive",
      },
      january: {
        name: "Styczeń (po świętach)",
        icon: "calendar_month",
        description:
          "Początek roku to spadek po świątecznym szczycie. Ludzie oszczędzają po wydatkach grudniowych. Jednak ferie zimowe w drugiej połowie stycznia zwiększają frekwencję rodzin z dziećmi.",
        impact: "+5%",
        color: "neutral",
      },
      earlySpring: {
        name: "Wczesnowiosenny",
        icon: "nature",
        description:
          "Luty i marzec to okres przejściowy – mniej premier blockbusterowych, ale stabilna frekwencja. Pogoda wciąż zachęca do wizyt w kinie. To dobry czas na filmy niezależne i europejskie.",
        impact: "bazowa",
        color: "neutral",
      },
      spring: {
        name: "Wiosenny",
        icon: "local_florist",
        description:
          "Kwiecień i maj to okres zmiennej frekwencji. Wielkanoc i majówka mogą zwiększać odwiedziny, ale ładna pogoda konkuruje z kinem. Pierwszy ciepły weekend może drastycznie obniżyć frekwencję.",
        impact: "-10%",
        color: "negative",
      },
      summer: {
        name: "Letni (blockbustery)",
        icon: "beach_access",
        description:
          "Lato to drugi szczyt sezonu – wakacje, duże blockbustery (Marvel, Pixar, kino akcji). Dzieci mają wolne, rodziny szukają rozrywki w klimatyzowanych salach. Frekwencja jest o 20-35% wyższa.",
        impact: "+25%",
        color: "positive",
      },
      autumn: {
        name: "Jesienny",
        icon: "eco",
        description:
          "Wrzesień i październik to okres powrotu do szkoły i pracy. Frekwencja jest niższa niż latem, ale stabilna. To czas filmów kandydujących do Oscarów i premier dramatów. Halloween zwiększa zainteresowanie horrorami.",
        impact: "-5%",
        color: "neutral",
      },
      november: {
        name: "Przedświąteczny",
        icon: "redeem",
        description:
          "Listopad to okres przygotowań do sezonu świątecznego. Premierują filmy familijne targetujące święta. Frekwencja zaczyna rosnąć, szczególnie w drugiej połowie miesiąca.",
        impact: "+10%",
        color: "positive",
      },
    };

    if (month === 11) return seasons.winter; // grudzień
    if (month === 0) return seasons.january; // styczeń
    if (month === 1 || month === 2) return seasons.earlySpring; // luty, marzec
    if (month === 3 || month === 4) return seasons.spring; // kwiecień, maj
    if (month >= 5 && month <= 7) return seasons.summer; // czerwiec, lipiec, sierpień
    if (month === 8 || month === 9) return seasons.autumn; // wrzesień, październik
    if (month === 10) return seasons.november; // listopad

    return seasons.earlySpring; // fallback
  }

  renderStats() {
    const movies = this.data.movies || [];
    let totalOccupied = 0,
      totalSeats = 0,
      totalScreenings = 0;

    movies.forEach((movie) => {
      movie.screenings.forEach((s) => {
        totalOccupied += s.stats?.occupied || 0;
        totalSeats += s.stats?.total || 0;
        totalScreenings++;
      });
    });

    // Calculate actual occupancy percentage (totalOccupied / totalSeats)
    // This is consistent with how prediction calculates percentage
    const avgOccupancy =
      totalSeats > 0 ? Math.round((totalOccupied / totalSeats) * 100) : 0;

    document.getElementById("totalMovies").textContent = movies.length;
    document.getElementById("totalScreenings").textContent = totalScreenings;
    document.getElementById(
      "seatsRatio"
    ).textContent = `${totalOccupied.toLocaleString(
      "pl-PL"
    )} / ${totalSeats.toLocaleString("pl-PL")}`;
    document.getElementById("avgOccupancy").textContent = `${avgOccupancy}%`;
  }

  renderHistogram() {
    const movies = this.data.movies || [];
    const histogram = document.getElementById("histogram");
    const labels = document.getElementById("histogramLabels");

    // Collect all screenings
    const allScreenings = [];
    movies.forEach((movie) => {
      movie.screenings.forEach((s) => {
        const timeParts = (s.time || "").split(":");
        const hour = parseInt(timeParts[0], 10);
        const minute = parseInt(timeParts[1], 10);
        if (!isNaN(hour) && !isNaN(minute)) {
          allScreenings.push({
            hour,
            minute,
            occupied: s.stats?.occupied || 0,
            total: s.stats?.total || 0,
          });
        }
      });
    });

    // Build hourly + minute data
    const hourlyData = {};
    for (let h = 10; h <= 23; h++) {
      hourlyData[h] = { occupied: 0, total: 0, screenings: 0, minuteData: {} };
      for (let m = 0; m <= 60; m += 10) {
        hourlyData[h].minuteData[m] = { occupied: 0, total: 0, screenings: 0 };
      }
    }

    allScreenings.forEach((s) => {
      if (s.hour >= 10 && s.hour <= 23) {
        hourlyData[s.hour].occupied += s.occupied;
        hourlyData[s.hour].total += s.total;
        hourlyData[s.hour].screenings++;
        const bucket = Math.floor(s.minute / 10) * 10;
        hourlyData[s.hour].minuteData[bucket].occupied += s.occupied;
        hourlyData[s.hour].minuteData[bucket].total += s.total;
        hourlyData[s.hour].minuteData[bucket].screenings++;
      }
    });

    // Calculate max real occupancy
    let maxRealOcc = Math.max(
      ...Object.values(hourlyData).map((d) => d.occupied),
      1
    );

    // Get prediction data if available
    const pred = this.data.prediction;
    const hourlyPred = pred?.hourly || {};

    // Calculate max predicted occupancy to include in scale
    let maxPredOcc = 0;
    for (const [hour, hp] of Object.entries(hourlyPred)) {
      const predVal = hp.adjustedOccupied ?? hp.predictedOccupied ?? 0;
      if (predVal > maxPredOcc) maxPredOcc = predVal;
    }

    // Use the larger of real or predicted as the scale
    // This ensures predictions are properly differentiated in height
    let maxOcc = Math.max(maxRealOcc, maxPredOcc, 1);

    let html = "";
    let labelsHtml = "";

    for (let h = 10; h <= 22; h++) {
      const d = hourlyData[h];
      const pct = d.total > 0 ? Math.round((d.occupied / d.total) * 100) : 0;
      const height =
        d.occupied > 0 ? Math.max(8, (d.occupied / maxOcc) * 100) : 0;
      const colorClass =
        d.occupied < 100 ? "low" : d.occupied < 150 ? "medium" : "high";
      const hasData = d.screenings > 0;

      // Prediction overlay - use adjustedOccupied (includes multipliers) for accurate predictions
      const hp = hourlyPred[h];
      // Use adjustedOccupied if available, fallback to predictedOccupied
      const predValue = hp ? hp.adjustedOccupied ?? hp.predictedOccupied : 0;
      const predAdditional = hp ? Math.max(0, predValue - d.occupied) : 0;

      // Calculate prediction height relative to maxOcc
      let predDiffHeight = (predAdditional / maxOcc) * 100;

      // If (height + predDiffHeight) > 100, clamp it so it doesn't overflow container
      if (height + predDiffHeight > 100) {
        predDiffHeight = Math.max(0, 100 - height);
      }

      const predHeight = predAdditional > 0 ? Math.max(4, predDiffHeight) : 0;

      // Hour group containing main bar + hidden sub-bars
      html += `<div class="hour-group" data-hour="${h}">`;

      // Calculate total prediction height for background
      // Use adjustedOccupied for accurate height, clamp to 100%
      const totalPredOcc = predValue;
      const totalPredHeight = Math.min(100, (totalPredOcc / maxOcc) * 100);

      // Main bar with prediction background (visible when collapsed)
      html += `
        <div class="main-bar" onclick="window.heliosApp.toggleHour(${h})">
          <div class="bar-stack">
            ${
              totalPredHeight > height && predAdditional > 0
                ? `<div class="prediction-bg" style="height: ${totalPredHeight}%">
              <span class="pred-label">+${predAdditional}</span>
            </div>`
                : ""
            }
            <div class="histogram-bar ${colorClass} ${
        hasData ? "" : "empty"
      }" style="height: ${Math.max(
        height,
        hasData && d.occupied > 0 ? 18 : 0
      )}%">
              ${
                hasData && d.occupied > 0
                  ? `<span class="bar-value">${d.occupied}</span>`
                  : ""
              }
            </div>
          </div>
          ${hasData ? `<span class="bar-pct">${pct}%</span>` : ""}
        </div>
      `;

      // Sub-bars container (hidden when collapsed) - click to close
      html += `<div class="sub-bars" onclick="window.heliosApp.toggleHour(${h})">`;

      let localMax = Math.max(
        ...Object.values(d.minuteData).map((md) => md.occupied),
        1
      );

      // Get hourly prediction and distribute across sub-bars
      const hourlyPredData = hourlyPred[h];
      // Use adjustedOccupied (with multipliers) for accurate predictions
      const predOccupied = hourlyPredData
        ? hourlyPredData.adjustedOccupied ?? hourlyPredData.predictedOccupied
        : 0;
      const predPerMinute = predOccupied > 0 ? Math.round(predOccupied / 7) : 0;

      // Adjust localMax for predictions
      if (predPerMinute > localMax) localMax = predPerMinute;

      for (let m = 0; m <= 60; m += 10) {
        const md =
          m === 60
            ? hourlyData[h + 1]?.minuteData[0] || {
                occupied: 0,
                total: 0,
                screenings: 0,
              }
            : d.minuteData[m];
        const subPct =
          md.total > 0 ? Math.round((md.occupied / md.total) * 100) : 0;
        const subHeight =
          md.occupied > 0 ? Math.max(10, (md.occupied / localMax) * 100) : 0;
        const subColor =
          md.occupied < 100 ? "low" : md.occupied < 150 ? "medium" : "high";
        const subHasData = md.screenings > 0;
        const timeLabel =
          m === 60
            ? `${h + 1}:00`
            : m === 0
            ? `${h}:00`
            : `:${m.toString().padStart(2, "0")}`;

        // Sub-bar prediction
        const subPredAdd = Math.max(0, predPerMinute - md.occupied);
        const subTotalPredHeight =
          predPerMinute > 0
            ? Math.max(10, (predPerMinute / localMax) * 100)
            : 0;

        // When no data but prediction exists, show prediction as main bar
        const showPredAsMain = !subHasData && subTotalPredHeight > 0;

        html += `
          <div class="sub-bar" data-time="${timeLabel}">
            <div class="bar-stack">
              ${
                !showPredAsMain && subTotalPredHeight > subHeight
                  ? `<div class="prediction-bg" style="height: ${subTotalPredHeight}%"><span class="pred-label">+${subPredAdd}</span></div>`
                  : ""
              }
              ${
                showPredAsMain
                  ? `<div class="histogram-bar prediction-only" style="height: ${subTotalPredHeight}%"><span class="bar-value">+${predPerMinute}</span></div>`
                  : `<div class="histogram-bar ${subColor} ${
                      subHasData ? "" : "empty"
                    }" style="height: ${subHeight}%">
                    ${
                      subHasData && md.occupied > 0
                        ? `<span class="bar-value">${md.occupied}</span>`
                        : ""
                    }
                  </div>`
              }
            </div>
            ${
              subHasData
                ? `<span class="bar-pct">${subPct}%</span>`
                : showPredAsMain
                ? `<span class="bar-pct pred">~</span>`
                : ""
            }
          </div>
        `;
      }

      html += `</div></div>`; // close sub-bars and hour-group

      labelsHtml += `<span class="hour-label" data-hour="${h}">${h}:00</span>`;
    }

    histogram.innerHTML = html;
    labels.innerHTML = labelsHtml;
  }

  toggleHour(hour) {
    const group = document.querySelector(`.hour-group[data-hour="${hour}"]`);
    const wasExpanded = group?.classList.contains("expanded");

    // Close all
    document.querySelectorAll(".hour-group.expanded").forEach((g) => {
      g.classList.remove("expanded");
    });

    // Toggle clicked one
    if (!wasExpanded && group) {
      group.classList.add("expanded");
    }
  }

  /* HISTORY METHODS */

  toggleHistoryModal() {
    const modal = document.getElementById("historyModal");
    if (!modal) return;

    if (modal.style.display === "none") {
      modal.style.display = "flex";
      this.loadHistoryList();
    } else {
      modal.style.display = "none";
    }
  }

  async loadHistoryList() {
    const listContainer = document.getElementById("historyList");
    listContainer.innerHTML = '<div class="spinner"></div>';

    try {
      const response = await fetch("api.php?action=history_list");
      const data = await response.json();

      if (data && data.dates) {
        this.renderHistoryList(data.dates);
      } else {
        listContainer.innerHTML = '<p class="error">Brak danych w archiwum</p>';
      }
    } catch (error) {
      console.error("History load error:", error);
      listContainer.innerHTML = `<p class="error">Błąd ładowania archiwum: ${error.message}</p>`;
    }
  }

  renderHistoryList(dates) {
    const listContainer = document.getElementById("historyList");
    if (dates.length === 0) {
      listContainer.innerHTML = "<p>Brak zapisanych dni w archiwum.</p>";
      return;
    }

    const html = dates
      .map((date) => {
        // Format date nicely (YYYY-MM-DD -> DD.MM.YYYY)
        const d = new Date(date);
        const dayNames = [
          "Niedziela",
          "Poniedziałek",
          "Wtorek",
          "Środa",
          "Czwartek",
          "Piątek",
          "Sobota",
        ];
        const plDate = `${d.getDate()}.${
          d.getMonth() + 1
        }.${d.getFullYear()} (${dayNames[d.getDay()]})`;

        return `
            <button class="history-item" onclick="window.heliosApp.loadHistoryData('${date}')">
                <span class="material-symbols-rounded">calendar_today</span>
                ${plDate}
            </button>
        `;
      })
      .join("");

    listContainer.innerHTML = html;
  }

  async loadHistoryData(date) {
    // 1. Close modal
    this.toggleHistoryModal();

    // 2. Show loader
    const loader = document.getElementById("fullPageLoader");
    if (loader) loader.classList.remove("hidden");

    try {
      const response = await fetch(`api.php?action=history_data&date=${date}`);
      if (!response.ok) throw new Error("History load failed");

      const rawData = await response.json();

      // Normalize history data structure to match live data expected by render()
      if (rawData && rawData.movies) {
        rawData.movies.forEach((movie) => {
          if (movie.screenings) {
            movie.screenings.forEach((s) => {
              // History has flat structure (occupied, total), app expects s.stats
              if (!s.stats && typeof s.occupied !== "undefined") {
                s.stats = {
                  occupied: s.occupied,
                  total: s.total, // History uses 'total' as seats count property
                  seatsLeft: (s.total || 0) - (s.occupied || 0),
                  occupancyPercent:
                    s.total > 0 ? Math.round((s.occupied / s.total) * 100) : 0,
                };
              }
              // History uses 'room' instead of 'hall'
              if (!s.hall && s.room) {
                s.hall = s.room;
              }
              // Ensure time format HH:MM
              if (s.time && s.time.length === 5) {
                // OK
              }
            });
          }

          // Map title -> movieTitle
          if (!movie.movieTitle && movie.title) {
            movie.movieTitle = movie.title;
          }
        });
      }

      this.data = rawData;
      this.currentDate = date; // Set app date to history date
      this.isArchiveMode = true; // Set archive mode explicit flag

      // 3. Render in HISTORY MODE
      this.render();

      // 4. Update UI to show we are in history
      document.getElementById("predictionTitle").innerHTML =
        '<span style="color:#ffcc00">⚠️ TRYB ARCHIWALNY</span> - ' + date;

      // Disable refresh button to avoid confusion
      const refreshBtn = document.querySelector(".refresh-btn");
      if (refreshBtn) refreshBtn.style.display = "none";
    } catch (e) {
      console.error(e);
      alert("Nie udało się załadować danych archiwalnych.");
    } finally {
      if (loader) loader.classList.add("hidden");
    }
  }

  renderMovies() {
    const container = document.getElementById("moviesContainer");
    const movies = this.data.movies || [];

    if (movies.length === 0) {
      container.innerHTML = `
                <div class="empty-state">
                    <span class="material-symbols-rounded">movie_off</span>
                    <h3>Brak seansów</h3>
                    <p>Nie znaleziono seansów na wybrany dzień</p>
                </div>
            `;
      return;
    }

    container.innerHTML = movies
      .map((movie) => this.createMovieCard(movie))
      .join("");

    // Add click handlers for movie cards
    container.querySelectorAll(".movie-card").forEach((card) => {
      card.style.cursor = "pointer";
      card.onclick = (e) => {
        // Don't trigger if clicking on a link (screening badge)
        if (e.target.closest("a")) return;
        const title = card.dataset.movieTitle;
        const genres = card.dataset.movieGenres
          ? card.dataset.movieGenres.split(",")
          : [];
        this.showMovieTrend(title, genres);
      };
    });
  }

  createMovieCard(movie) {
    try {
      // Calculate totals for this movie
      let totalOcc = 0,
        totalSeats = 0;

      const screenings = Array.isArray(movie.screenings)
        ? movie.screenings
        : [];

      screenings.forEach((s) => {
        totalOcc += s.stats?.occupied || 0;
        totalSeats += s.stats?.total || 0;
      });
      const avgOcc =
        totalSeats > 0 ? Math.round((totalOcc / totalSeats) * 100) : 0;

      // Movie info
      const infoParts = [];
      if (movie.duration) infoParts.push(`${movie.duration} min`);
      if (Array.isArray(movie.genres) && movie.genres.length)
        infoParts.push(movie.genres.slice(0, 2).join(", "));
      if (movie.rating) infoParts.push(`Od lat ${movie.rating}`);

      // IMDB Rating
      const imdbHtml = movie.imdbRating
        ? `<span class="imdb-rating"><span class="material-symbols-rounded filled">star</span> ${movie.imdbRating}</span>`
        : "";

      // Popularity badge (from API, pre-calculated)
      const popularity = movie.popularity || avgOcc;
      const popClass =
        popularity >= 50 ? "hot" : popularity >= 25 ? "warm" : "cool";
      const popHtml = `<span class="popularity-badge ${popClass}">${popularity}% <span class="material-symbols-rounded">local_fire_department</span></span>`;

      // Screenings - showing seats sold/total
      // Detect special badges
      let badgeHtml = "";

      if (movie.isSpecialEvent) {
        badgeHtml = '<span class="event-badge">Wydarzenie</span>';
      } else if (movie.genres) {
        const lowerGenres = Array.isArray(movie.genres)
          ? movie.genres.map((g) => g.toLowerCase())
          : [];
        const titleLower = (movie.movieTitle || "").toLowerCase();

        if (
          lowerGenres.some(
            (g) => g.includes("anime") || titleLower.includes("anime")
          )
        ) {
          badgeHtml = '<span class="event-badge anime">Helios Anime</span>';
        } else if (
          lowerGenres.some(
            (g) => g.includes("koneser") || titleLower.includes("koneser")
          )
        ) {
          badgeHtml =
            '<span class="event-badge kino-konesera">Kino Konesera</span>';
        } else if (
          lowerGenres.some(
            (g) => g.includes("maraton") || titleLower.includes("maraton")
          )
        ) {
          badgeHtml = '<span class="event-badge maraton">Maraton</span>';
        } else if (
          lowerGenres.some(
            (g) => g.includes("kultura") || titleLower.includes("kultura")
          )
        ) {
          badgeHtml =
            '<span class="event-badge kultura">Kultura Dostępna</span>';
        } else if (titleLower.includes("projekt specjalny")) {
          badgeHtml = '<span class="event-badge">Projekt Specjalny</span>';
        }
      }

      const screeningsHtml = screenings
        .map((s) => {
          const stats = s.stats || {
            total: 0,
            free: 0,
            occupied: 0,
            occupancyPercent: 0,
          };
          const occClass =
            stats.occupancyPercent < 30
              ? "low"
              : stats.occupancyPercent < 70
              ? "medium"
              : "high";

          // Prediction for tooltip only

          const pred = s.prediction || null;
          const hasPred =
            pred &&
            pred.additional &&
            (pred.additional.min > 0 || pred.additional.max > 0);
          const predRange = hasPred
            ? `+${pred.additional.min}-${pred.additional.max}`
            : "";
          const predTooltip = hasPred ? ` | Predykcja: ${predRange} osób` : "";
          const predIndicator = hasPred
            ? `<div class="pred-indicator">${predRange}</div>`
            : "";

          const time = s.time || s.screeningTime || "N/A";
          const url = s.url || "#";
          const hall = s.hall || "?";

          return `
                    <a href="${this.esc(
                      url
                    )}" target="_blank" class="screening-badge ${occClass}${
            hasPred ? " has-pred" : ""
          }" title="${this.esc(hall)}${predTooltip}">
                        <span class="screening-time">${time}</span>
                        <span class="screening-seats">${stats.occupied}/${
            stats.total
          }</span>
                        ${predIndicator}
                    </a>
                `;
        })
        .join("");

      // Use placeholder poster from helios.pl if missing
      let posterHtml;

      if (this.isArchiveMode) {
        // CSS-only placeholder for archive
        posterHtml = `
            <div class="archive-poster">
                <span class="material-symbols-rounded">movie</span>
                <span class="archive-poster-title">${this.esc(
                  movie.movieTitle
                )}</span>
            </div>
          `;
      } else {
        const posterUrl =
          movie.poster ||
          "https://ukfn.pl/wp-content/uploads/2022/03/placeholder.png";
        posterHtml = `<img src="${this.esc(posterUrl)}" alt="${this.esc(
          movie.movieTitle
        )}" loading="lazy">`;
      }

      const adDuration = this.estimateAdDuration(movie);

      return `
                <article class="movie-card ${
                  movie.isSpecialEvent ? "special-event" : ""
                }" data-movie-title="${this.esc(
        movie.movieTitle
      )}" data-movie-genres="${(movie.genres || []).join(",")}">
                    <div class="movie-poster">
                        ${posterHtml}
                        ${badgeHtml}
                    </div>
                    <div class="movie-card-content">
                        <div class="movie-title-row">
                            <h3 class="movie-title" title="${this.esc(
                              movie.movieTitle
                            )}">${this.esc(movie.movieTitle)}</h3>
                            ${imdbHtml}

                        </div>
                        ${
                          infoParts.length
                            ? `<div class="movie-info">${infoParts.join(
                                " • "
                              )}</div>`
                            : ""
                        }
                        <div class="movie-stats-mini">
                            <span class="material-symbols-rounded">schedule</span>
                            <span>${screenings.length} ${this.pl(
        screenings.length
      )}</span>
                            <span class="divider">•</span>
                            <span class="material-symbols-rounded">event_seat</span>
                            <span>${totalOcc}/${totalSeats}</span>
                            <span class="divider">•</span>
                            <span class="material-symbols-rounded" title="Szacowany czas reklam">timer</span>
                            <span title="Szacowany blok reklamowy">~${adDuration} min reklam</span>
                            <span class="divider">•</span>
                            ${popHtml}
                        </div>
                        <div class="screenings-grid">
                            ${screeningsHtml}
                        </div>
                    </div>
                </article>
            `;
    } catch (e) {
      console.error("Error creating movie card", e, movie);
      return "";
    }
  }

  pl(n) {
    if (n === 1) return "seans";
    if (n >= 2 && n <= 4) return "seanse";
    return "seansów";
  }

  updateLastUpdate() {
    const el = document.getElementById("lastUpdate");
    if (this.data.scrapedAt) {
      const d = new Date(this.data.scrapedAt);
      el.textContent = d.toLocaleTimeString("pl-PL", {
        hour: "2-digit",
        minute: "2-digit",
      });
    }
  }

  esc(t) {
    const d = document.createElement("div");
    d.textContent = t || "";
    return d.innerHTML;
  }

  estimateAdDuration(movie) {
    if (movie.isForChildren) return 15;

    // Check for premiere (within 7 days)
    if (movie.premiereDate) {
      const pDate = new Date(movie.premiereDate);
      const now = new Date(); // Or validation date
      const diffDays = (now - pDate) / (1000 * 60 * 60 * 24);
      if (diffDays <= 7) return 25;
      if (diffDays <= 14) return 22;
    }

    return 20; // Default
  }

  // ============== COMING SOON METHODS ==============

  async showComingSoon() {
    // Hide schedule sections, show coming soon
    document.getElementById("hallSection").style.display = "none";
    document.querySelector(".histogram-section").style.display = "none";
    document.getElementById("repertoireSection").style.display = "none";
    document.querySelector(".stats-bar").style.display = "none";
    document.getElementById("predictionBanner").style.display = "none";
    document.getElementById("aiInsightsSection").style.display = "none";
    document.getElementById("timelineSection").style.display = "none";
    document.getElementById("comingSoonSection").style.display = "block";

    // Mark tab as active
    document.getElementById("comingSoonTab").classList.add("active");
    document
      .querySelectorAll(".day-btn")
      .forEach((b) => b.classList.remove("active"));

    // Load data if not cached
    if (!this.comingSoonData) {
      await this.loadComingSoon();
    } else {
      this.renderComingSoon();
    }
  }

  showSchedule() {
    // Show schedule sections, hide all others
    document.getElementById("hallSection").style.display = "";
    document.querySelector(".histogram-section").style.display = "";
    document.getElementById("repertoireSection").style.display = "";
    document.querySelector(".stats-bar").style.display = "";

    // Hide other views
    document.getElementById("comingSoonSection").style.display = "none";
    document.getElementById("aiInsightsSection").style.display = "none";
    document.getElementById("timelineSection").style.display = "none";

    // Remove active from coming soon tab
    document.getElementById("comingSoonTab").classList.remove("active");

    // Update day picker to show current date as active (without triggering events)
    const container = document.getElementById("fastDayPicker");
    container.querySelectorAll(".day-btn").forEach((b) => {
      b.classList.toggle("active", b.dataset.date === this.currentDate);
    });
  }

  async loadComingSoon() {
    const container = document.getElementById("comingSoonContainer");
    container.innerHTML = `
      <div class="loading">
        <div class="spinner"></div>
        <p>Ładowanie zapowiedzi...</p>
      </div>
    `;

    try {
      const response = await fetch(
        `api.php?action=comingsoon&_t=${Date.now()}`
      );
      if (!response.ok) throw new Error("Failed");
      this.comingSoonData = await response.json();
      this.renderComingSoon();
    } catch (error) {
      console.error("Coming soon error:", error);
      container.innerHTML = `
        <div class="empty-state">
          <span class="material-symbols-rounded">error</span>
          <h3>Błąd ładowania</h3>
          <p>Nie udało się pobrać zapowiedzi</p>
        </div>
      `;
    }
  }

  renderComingSoon() {
    const container = document.getElementById("comingSoonContainer");
    const movies = this.comingSoonData?.comingSoon || [];

    if (movies.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <span class="material-symbols-rounded">upcoming</span>
          <h3>Brak zapowiedzi</h3>
          <p>Nie znaleziono nadchodzących premier</p>
        </div>
      `;
      return;
    }

    // Render stacked layout: movies grid on top, marathons below
    container.innerHTML = `
      <div class="coming-soon-section-wrapper">
        <h3 class="coming-soon-subtitle">
          <span class="material-symbols-rounded">movie</span>
          Nadchodzące premiery
        </h3>
        <div class="coming-soon-grid">
          ${movies.map((movie) => this.createComingSoonCard(movie)).join("")}
        </div>
      </div>
      <div class="coming-soon-section-wrapper" id="marathonsSection">
        <h3 class="coming-soon-subtitle marathons-subtitle">
          <span class="material-symbols-rounded">theaters</span>
          Maratony filmowe
        </h3>
        <div class="marathons-list">
          <div class="loading"><div class="spinner"></div></div>
        </div>
      </div>
    `;

    // Load marathons
    this.loadMarathons();
  }

  async loadMarathons() {
    try {
      const response = await fetch(`api.php?action=marathons&_t=${Date.now()}`);
      if (!response.ok) {
        console.error("Marathons response not ok:", response.status);
        return;
      }
      const data = await response.json();
      const marathons = data?.marathons || [];
      console.log("Marathons loaded:", marathons.length);

      const list = document.querySelector("#marathonsSection .marathons-list");
      console.log("Marathon list element:", list);

      if (list) {
        if (marathons.length > 0) {
          list.innerHTML = marathons
            .map((m) => this.createMarathonCard(m))
            .join("");
        } else {
          list.innerHTML = `<p class="no-marathons">Brak zaplanowanych maratonów</p>`;
        }
      } else {
        console.error("Marathon list element not found!");
      }
    } catch (error) {
      console.error("Marathons load error:", error);
    }
  }

  createMarathonCard(marathon) {
    const poster =
      marathon.poster || "https://via.placeholder.com/180x270?text=Maraton";

    // Format date
    const eventDate = new Date(marathon.eventDate);
    const formattedDate = eventDate.toLocaleDateString("pl-PL", {
      weekday: "long",
      day: "numeric",
      month: "long",
      year: "numeric",
    });

    return `
      <div class="marathon-card">
        <div class="coming-soon-poster">
          <img src="${this.esc(poster)}" alt="${this.esc(
      marathon.title
    )}" loading="lazy" />
        </div>
        <div class="coming-soon-info">
          <h3 class="coming-soon-title">${this.esc(marathon.title)}</h3>
          <div class="marathon-date">
            <span class="material-symbols-rounded">calendar_today</span>
            ${formattedDate}
          </div>
          ${
            marathon.time
              ? `
            <div class="marathon-time">
              <span class="material-symbols-rounded">schedule</span>
              ${marathon.time}
            </div>
          `
              : ""
          }
          ${
            marathon.description
              ? `<p class="marathon-description">${this.esc(
                  marathon.description
                )}</p>`
              : ""
          }
        </div>
      </div>
    `;
  }

  createComingSoonCard(movie) {
    const premiereDate = new Date(movie.premiereDate);
    const formattedDate = premiereDate.toLocaleDateString("pl-PL", {
      day: "numeric",
      month: "long",
      year: "numeric",
    });

    const daysUntil = Math.ceil(
      (premiereDate - new Date()) / (1000 * 60 * 60 * 24)
    );
    const daysText =
      daysUntil === 1
        ? "Jutro!"
        : daysUntil <= 7
        ? `Za ${daysUntil} dni`
        : `Za ${Math.ceil(daysUntil / 7)} tyg.`;

    const genres = (movie.genres || []).slice(0, 3).join(", ");
    const poster =
      movie.poster || "https://via.placeholder.com/300x450?text=Brak+plakatu";
    const description = movie.description
      ? movie.description.length > 150
        ? movie.description.substring(0, 150) + "..."
        : movie.description
      : "";

    return `
      <div class="coming-soon-card">
        <div class="coming-soon-poster">
          <img src="${this.esc(poster)}" alt="${this.esc(
      movie.title
    )}" loading="lazy" />
          ${
            movie.onSale
              ? `<div class="on-sale-badge">JUŻ W SPRZEDAŻY!</div>`
              : ""
          }
          <div class="premiere-badge">
            <span class="material-symbols-rounded">calendar_today</span>
            ${formattedDate}
          </div>
          <div class="days-until">${daysText}</div>
        </div>
        <div class="coming-soon-info">
          <h3 class="coming-soon-title">${this.esc(movie.title)}</h3>
          <p class="premiere-date">od ${formattedDate}</p>
          ${
            movie.originalTitle && movie.originalTitle !== movie.title
              ? `<p class="original-title">${this.esc(movie.originalTitle)}</p>`
              : ""
          }
          <div class="coming-soon-meta">
            ${
              movie.duration
                ? `<span><span class="material-symbols-rounded">schedule</span> ${movie.duration} min</span>`
                : ""
            }
            ${
              genres
                ? `<span><span class="material-symbols-rounded">category</span> ${this.esc(
                    genres
                  )}</span>`
                : ""
            }
            ${
              movie.rating
                ? `<span class="age-rating">${movie.rating}+</span>`
                : ""
            }
            ${
              movie.isForChildren
                ? `<span class="kids-badge"><span class="material-symbols-rounded">child_care</span></span>`
                : ""
            }
          </div>
          ${
            description
              ? `<p class="coming-soon-description">${this.esc(
                  description
                )}</p>`
              : ""
          }
          ${
            movie.director
              ? `<p class="coming-soon-director"><strong>Reżyseria:</strong> ${this.esc(
                  movie.director
                )}</p>`
              : ""
          }
        </div>
      </div>
    `;
  }

  // ==================== TIMELINE VIEW ====================
  toggleTimeline() {
    const section = document.getElementById("timelineSection");
    const mainSections = [
      "hallSection",
      "repertoireSection",
      "comingSoonSection",
      "aiInsightsSection",
    ];
    const histogramSection = document.querySelector(".histogram-section");
    const statsBar = document.querySelector(".stats-bar");
    const predictionBanner = document.getElementById("predictionBanner");

    if (section.style.display === "none") {
      // Show timeline
      mainSections.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.style.display = "none";
      });
      if (histogramSection) histogramSection.style.display = "none";
      if (statsBar) statsBar.style.display = "none";
      if (predictionBanner) predictionBanner.style.display = "none";
      section.style.display = "block";
      this.renderTimeline();

      // Start live timer to update NOW indicator every 30 seconds
      this.timelineTimer = setInterval(() => {
        this.updateTimelineNowIndicator();
      }, 30000);
    } else {
      section.style.display = "none";
      // Stop timer when hiding timeline
      if (this.timelineTimer) {
        clearInterval(this.timelineTimer);
        this.timelineTimer = null;
      }
      this.showSchedule();
    }
  }

  updateTimelineNowIndicator() {
    const nowBadge = document.querySelector(".timeline-now-badge");
    if (!nowBadge) return;

    const now = new Date();
    const nowHour = now.getHours();
    const nowMinutes = now.getMinutes();
    const nowTimeStr = `${String(nowHour).padStart(2, "0")}:${String(
      nowMinutes
    ).padStart(2, "0")}`;

    // Update badge text
    nowBadge.innerHTML = `
      <span class="material-symbols-rounded">schedule</span>
      TERAZ ${nowTimeStr}
    `;

    // Flash animation
    nowBadge.classList.add("flash");
    setTimeout(() => nowBadge.classList.remove("flash"), 500);
  }

  setTimelineSort(sortType) {
    this.timelineSort = sortType;
    localStorage.setItem("helios-timeline-sort", sortType);

    // Update UI active state
    document.querySelectorAll(".sort-btn").forEach((btn) => {
      if (btn.dataset.sort === sortType) btn.classList.add("active");
      else btn.classList.remove("active");
    });

    this.renderTimeline();
  }

  renderTimeline() {
    const container = document.getElementById("timelineContainer");
    if (!this.data || !this.data.movies) {
      container.innerHTML =
        '<p class="no-data">Brak danych do wyświetlenia.</p>';
      return;
    }

    // Set initial UI state if needed (on first load)
    document.querySelectorAll(".sort-btn").forEach((btn) => {
      if (btn.dataset.sort === this.timelineSort) btn.classList.add("active");
      else btn.classList.remove("active");
    });

    const allScreenings = [];
    this.data.movies.forEach((movie) => {
      (movie.screenings || []).forEach((s) => {
        // Calculate end time immediately for sorting
        let endTimestamp = s.endTs;
        if (!endTimestamp) {
          const duration = movie.duration || s.duration || 120; // fallback duration
          const adDuration = s.adDuration || 20;
          const totalDurationMin = duration + adDuration;
          endTimestamp = (s.timestamp || 0) + totalDurationMin * 60;
        }

        allScreenings.push({
          ...s,
          movieTitle: movie.movieTitle,
          poster: movie.poster,
          duration: movie.duration || s.duration,
          genres: movie.genres,
          calculatedEndTs: endTimestamp,
        });
      });
    });

    // Sort based on preference
    if (this.timelineSort === "end") {
      allScreenings.sort((a, b) => a.calculatedEndTs - b.calculatedEndTs);
    } else {
      allScreenings.sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0));
    }

    // Update dropdown state - NOT NEEDED for custom buttons, handled above
    // const sortSelect = document.getElementById('timelineSort');
    // if (sortSelect) sortSelect.value = this.timelineSort;

    if (allScreenings.length === 0) {
      container.innerHTML =
        '<p class="no-data">Brak seansów do wyświetlenia.</p>';
      return;
    }

    // Get current time for "NOW" indicator
    const now = new Date();
    const nowHour = now.getHours();
    const nowMinutes = now.getMinutes();
    const nowTimestamp = Math.floor(now.getTime() / 1000);
    const nowTimeStr = `${String(nowHour).padStart(2, "0")}:${String(
      nowMinutes
    ).padStart(2, "0")}`;

    let currentHour = null;
    let nowIndicatorInserted = false;
    let html = '<div class="timeline">';

    allScreenings.forEach((s, index) => {
      // Determine what time to check for wrapping (start or end)
      const checkTimestamp =
        this.timelineSort === "end" ? s.calculatedEndTs : s.timestamp || 0;

      // Determine grouping hour
      let hour;
      if (this.timelineSort === "end") {
        // Group by end hour
        const endDate = new Date(s.calculatedEndTs * 1000);
        hour = String(endDate.getHours()).padStart(2, "0");
      } else {
        // Group by start hour
        hour = s.time ? s.time.split(":")[0] : "00";
      }

      // Insert NOW indicator before future items
      if (!nowIndicatorInserted && checkTimestamp > nowTimestamp) {
        // Close previous hour group if open
        if (currentHour !== null) {
          html += "</div></div>";
        }

        // Insert NOW marker
        html += `
          <div class="timeline-now-indicator">
            <div class="timeline-now-line"></div>
            <div class="timeline-now-badge">
              <span class="material-symbols-rounded">schedule</span>
              TERAZ ${nowTimeStr}
            </div>
            <div class="timeline-now-line"></div>
          </div>
        `;
        nowIndicatorInserted = true;
        currentHour = null; // Reset to force new hour group
      }

      if (hour !== currentHour) {
        if (currentHour !== null) html += "</div></div>";
        html += `<div class="timeline-hour-group">
          <div class="timeline-hour-marker">${hour}:00</div>
          <div class="timeline-screenings">`;
        currentHour = hour;
      }

      const occupied = s.stats?.occupied || 0;
      const total = s.stats?.total || 1;
      const pct = Math.round((occupied / total) * 100);
      const pctClass = pct > 70 ? "high" : pct > 30 ? "medium" : "low";
      const isPast = checkTimestamp < nowTimestamp;

      // Calculate end time string
      let endTimeStr = "";
      if (s.calculatedEndTs) {
        const endDate = new Date(s.calculatedEndTs * 1000);
        endTimeStr = `${String(endDate.getHours()).padStart(2, "0")}:${String(
          endDate.getMinutes()
        ).padStart(2, "0")}`;
      }

      // Time display logic based on sort type
      let timeDisplayHtml = "";
      if (this.timelineSort === "end") {
        // Emphasize END time
        timeDisplayHtml = `
            <span class="timeline-main-time is-end">${endTimeStr}</span>
            <span class="timeline-sub-time">Start: ${this.esc(
              s.time || ""
            )}</span>
          `;
      } else {
        // Standard: Start time dominant
        timeDisplayHtml = `
            ${this.esc(s.time || "")}
            ${
              endTimeStr
                ? `<span class="timeline-end-time">→${endTimeStr}</span>`
                : ""
            }
          `;
      }

      // Find next screening in same hall
      let nextInHall = null;
      for (let i = index + 1; i < allScreenings.length; i++) {
        if (allScreenings[i].hall === s.hall) {
          nextInHall = allScreenings[i];
          break;
        }
      }

      html += `
        <div class="timeline-item ${isPast ? "past" : ""}">
          <div class="timeline-time ${
            this.timelineSort === "end" ? "end-mode" : ""
          }">
            ${timeDisplayHtml}
          </div>
          <div class="timeline-content">
            <img class="timeline-poster" src="${
              s.poster || ""
            }" alt="" onerror="this.style.display='none'" />
            <div class="timeline-info">
              <div class="timeline-title">${this.esc(s.movieTitle || "")}</div>
            <div class="timeline-meta">
                <span class="timeline-hall">${
                  !nextInHall
                    ? `<span class="timeline-last-icon material-symbols-rounded" title="Ostatni seans w tej sali">line_end_circle</span>`
                    : ""
                }${this.esc(s.hall || "")}</span>
                <span class="timeline-occupancy ${pctClass}">${occupied}/${total} (${pct}%)</span>
              </div>
            </div>
          </div>
        </div>
      `;
    });

    // If all screenings are in the past, add NOW at the end
    if (!nowIndicatorInserted) {
      html += "</div></div>";
      html += `
        <div class="timeline-now-indicator">
          <div class="timeline-now-line"></div>
          <div class="timeline-now-badge">
            <span class="material-symbols-rounded">schedule</span>
            TERAZ ${nowTimeStr}
          </div>
          <div class="timeline-now-line"></div>
        </div>
      `;
    } else {
      html += "</div></div>";
    }

    html += "</div>";
    container.innerHTML = html;

    // Scroll to NOW indicator
    setTimeout(() => {
      const nowEl = container.querySelector(".timeline-now-indicator");
      if (nowEl) {
        nowEl.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    }, 100);
  }

  // ==================== AI INSIGHTS ====================
  toggleAIInsights() {
    const section = document.getElementById("aiInsightsSection");
    const mainSections = [
      "hallSection",
      "repertoireSection",
      "comingSoonSection",
      "timelineSection",
    ];
    const histogramSection = document.querySelector(".histogram-section");
    const statsBar = document.querySelector(".stats-bar");
    const predictionBanner = document.getElementById("predictionBanner");

    if (section.style.display === "none") {
      // Show insights
      mainSections.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.style.display = "none";
      });
      if (histogramSection) histogramSection.style.display = "none";
      if (statsBar) statsBar.style.display = "none";
      if (predictionBanner) predictionBanner.style.display = "none";
      section.style.display = "block";
      this.renderAIInsights();
    } else {
      // Hide insights, show schedule
      section.style.display = "none";
      this.showSchedule();
    }
  }

  async renderAIInsights() {
    const container = document.getElementById("aiInsightsContainer");

    // Show loading
    container.innerHTML = `
      <div class="loading">
        <div class="spinner"></div>
        <p>Ładowanie myśli AI...</p>
      </div>
    `;

    try {
      // Fetch insights from API
      const response = await fetch(
        `api.php?action=insights&limit=10000&_t=${Date.now()}`
      );
      if (!response.ok) throw new Error("Failed to fetch insights");
      const data = await response.json();

      const insights = data.insights || [];
      const stats = data.stats || {};

      if (insights.length === 0) {
        container.innerHTML = `
          <div class="ai-insights-empty">
            <span class="material-symbols-rounded">psychology</span>
            <h3>Brak danych do analizy</h3>
            <p>Algorytm potrzebuje więcej danych historycznych żeby generować wnioski. 
            Dane zbierane są automatycznie każdego dnia.</p>
          </div>
        `;
        return;
      }

      // Build stats header
      const statsHtml = `
        <div class="ai-stats-header">
          <div class="ai-stat">
            <span class="material-symbols-rounded">insights</span>
            <div>
              <div class="stat-value">${stats.totalInsights || 0}</div>
              <div class="stat-label">Wnioski</div>
            </div>
          </div>
          <div class="ai-stat">
            <span class="material-symbols-rounded">check_circle</span>
            <div>
              <div class="stat-value">${stats.verifications || 0}</div>
              <div class="stat-label">Trafne</div>
            </div>
          </div>
          <div class="ai-stat">
            <span class="material-symbols-rounded">school</span>
            <div>
              <div class="stat-value">${stats.learnings || 0}</div>
              <div class="stat-label">Nauka</div>
            </div>
          </div>
          <div class="ai-stat ${
            stats.avgAccuracy > 80
              ? "good"
              : stats.avgAccuracy > 50
              ? "medium"
              : "low"
          }">
            <span class="material-symbols-rounded">speed</span>
            <div>
              <div class="stat-value">${stats.avgAccuracy || "?"}%</div>
              <div class="stat-label">Celność</div>
            </div>
          </div>
        </div>
      `;

      // Extract unique dates from insights (forDate in details)
      const uniqueDates = [
        ...new Set(insights.map((i) => i.details?.forDate).filter((d) => d)),
      ]
        .sort()
        .reverse();

      // Build custom date filter with search
      const dateFilterHtml =
        uniqueDates.length > 0
          ? `
        <div class="ai-date-filter">
          <div class="filter-group">
            <div class="date-filter-label">
              <span class="material-symbols-rounded">filter_list</span>
              <span>Typ:</span>
            </div>
            <div class="type-filter-wrapper">
              <button type="button" id="typeFilterBtn" class="type-filter-btn">
                <span class="material-symbols-rounded">apps</span>
                <span class="type-filter-text">Wszystkie</span>
                <span class="material-symbols-rounded expand-icon">expand_more</span>
              </button>
              <div id="typeFilterDropdown" class="type-filter-dropdown">
                <div class="type-option" data-value="">
                  <span class="material-symbols-rounded">apps</span>
                  <span>Wszystkie</span>
                </div>
                <div class="type-option" data-value="reports">
                  <span class="material-symbols-rounded">assessment</span>
                  <span>Tylko raporty</span>
                </div>
                <div class="type-option" data-value="verification">
                  <span class="material-symbols-rounded">check_circle</span>
                  <span>Weryfikacje</span>
                </div>
                <div class="type-option" data-value="correction">
                  <span class="material-symbols-rounded">edit</span>
                  <span>Korekty</span>
                </div>
                <div class="type-option" data-value="learning">
                  <span class="material-symbols-rounded">school</span>
                  <span>Nauka</span>
                </div>
                <div class="type-option" data-value="pattern">
                  <span class="material-symbols-rounded">trending_up</span>
                  <span>Wzorce</span>
                </div>
              </div>
            </div>
          </div>
          <div class="filter-group">
            <div class="date-filter-label">
              <span class="material-symbols-rounded">calendar_month</span>
              <span>Data:</span>
            </div>
            <div class="date-filter-input-wrapper">
              <input 
                type="text" 
                id="insightDateSearch" 
                placeholder="Wpisz datę..."
                autocomplete="off"
              />
              <button type="button" id="insightDateClear" class="date-clear-btn" title="Wyczyść">
                <span class="material-symbols-rounded">close</span>
              </button>
              <div id="insightDateSuggestions" class="date-suggestions"></div>
            </div>
          </div>
          <div class="date-filter-count">
            <span id="insightFilteredCount">${insights.length}</span> / ${insights.length}
          </div>
        </div>
      `
          : "";

      // Store dates for filtering
      this.insightDates = uniqueDates;
      this.allInsightsCount = insights.length;

      // Build timeline (will be filtered by JS)
      const timelineHtml = insights
        .map((insight) => {
          const date = new Date(insight.timestamp || insight.date);
          const formattedDate = date.toLocaleDateString("pl-PL", {
            day: "numeric",
            month: "short",
          });
          const formattedTime = date.toLocaleTimeString("pl-PL", {
            hour: "2-digit",
            minute: "2-digit",
          });

          const typeIcons = {
            correction: "edit",
            learning: "school",
            verification: "check_circle",
            pattern: "trending_up",
            weekly_report: "date_range",
            monthly_report: "calendar_month",
          };
          const typeColors = {
            correction: "orange",
            learning: "blue",
            verification: "green",
            pattern: "purple",
            weekly_report: "teal",
            monthly_report: "indigo",
          };
          const typeLabels = {
            correction: "Korekta",
            learning: "Nauka",
            verification: "Weryfikacja",
            pattern: "Wzorzec",
            weekly_report: "Raport tygodniowy",
            monthly_report: "Raport miesięczny",
          };

          const icon = typeIcons[insight.type] || "psychology";
          const color = typeColors[insight.type] || "gray";
          const label = typeLabels[insight.type] || insight.type;
          const forDate = insight.details?.forDate || "";

          return `
          <div class="insight-timeline-item ${
            insight.type
          }" data-for-date="${forDate}">
            <div class="insight-date">
              <span class="date">${formattedDate}</span>
              <span class="time">${formattedTime}</span>
            </div>
            <div class="insight-marker ${color}">
              <span class="material-symbols-rounded">${icon}</span>
            </div>
            <div class="insight-content">
              <div class="insight-type-badge ${color}">${label}</div>
              <h4>${this.esc(insight.title)}</h4>
              <p>${this.replaceArrowsWithIcons(this.esc(insight.message))}</p>
              ${
                insight.details &&
                insight.details.factorsArray &&
                insight.details.factorsArray.length > 0
                  ? `<div class="insight-factors-badges">
                      ${insight.details.factorsArray
                        .map(
                          (f) => `
                        <div class="insight-factor-badge ${
                          f.type || "neutral"
                        }">
                          <span class="material-symbols-rounded">${this.esc(
                            f.icon
                          )}</span>
                          <span class="factor-name">${this.esc(f.name)}</span>
                          ${
                            f.impact
                              ? `<span class="factor-impact">${this.replaceArrowsWithIcons(
                                  this.esc(f.impact)
                                )}</span>`
                              : ""
                          }
                        </div>
                      `
                        )
                        .join("")}
                    </div>`
                  : insight.details && insight.details.factors
                  ? `<div class="insight-factors-section">
                        <span class="material-symbols-rounded">tune</span>
                        <span class="insight-factors-text">${this.esc(
                          insight.details.factors
                        )}</span>
                      </div>`
                  : ""
              }
              ${
                insight.details && insight.details.multipliers
                  ? `<div class="insight-multipliers-section">
                      <span class="material-symbols-rounded">calculate</span>
                      <span>Modyfikatory: ${this.esc(
                        insight.details.multipliers
                      )}</span>
                    </div>`
                  : ""
              }
              ${
                insight.details && insight.details.correction
                  ? `<div class="insight-correction-section">
                      <span class="material-symbols-rounded">build</span>
                      <span>${this.esc(insight.details.correction)}</span>
                    </div>`
                  : ""
              }
              ${
                insight.details &&
                insight.details.adjustments &&
                insight.details.adjustments.message
                  ? `<div class="insight-adjustments-section">
                      <span class="material-symbols-rounded">psychology</span>
                      <span>${this.esc(
                        insight.details.adjustments.message
                      )}</span>
                    </div>`
                  : ""
              }
              ${
                insight.details && insight.details.movie
                  ? `<div class="insight-movie"><span class="material-symbols-rounded">movie</span>${this.esc(
                      insight.details.movie
                    )}</div>`
                  : ""
              }
              ${
                insight.details && insight.details.diff
                  ? `<div class="insight-diff ${
                      insight.details.diff > 0 ? "positive" : "negative"
                    }">${insight.details.diff > 0 ? "+" : ""}${
                      insight.details.diff
                    }%</div>`
                  : ""
              }
              ${
                (insight.type === "weekly_report" ||
                  insight.type === "monthly_report") &&
                insight.details
                  ? `<div class="report-stats-grid">
                      <div class="report-stat primary">
                        <span class="material-symbols-rounded">speed</span>
                        <div class="stat-value">${
                          insight.details.avgAccuracy || "?"
                        }%</div>
                        <div class="stat-label">Średnia celność</div>
                      </div>
                      <div class="report-stat">
                        <span class="material-symbols-rounded">swap_vert</span>
                        <div class="stat-value">${
                          insight.details.minAccuracy || "?"
                        }% - ${insight.details.maxAccuracy || "?"}%</div>
                        <div class="stat-label">Zakres</div>
                      </div>
                      ${
                        insight.details.bestDay
                          ? `
                      <div class="report-stat success">
                        <span class="material-symbols-rounded">thumb_up</span>
                        <div class="stat-value">${insight.details.bestDay}</div>
                        <div class="stat-label">Najlepiej</div>
                      </div>`
                          : ""
                      }
                      ${
                        insight.details.worstDay
                          ? `
                      <div class="report-stat warning">
                        <span class="material-symbols-rounded">thumb_down</span>
                        <div class="stat-value">${insight.details.worstDay}</div>
                        <div class="stat-label">Najtrudniej</div>
                      </div>`
                          : ""
                      }
                    </div>
                    <div class="report-metrics">
                      <span class="metric"><span class="material-symbols-rounded">check_circle</span>${
                        insight.details.verifications || 0
                      } weryfikacji</span>
                      <span class="metric"><span class="material-symbols-rounded">edit</span>${
                        insight.details.corrections || 0
                      } korekt</span>
                      <span class="metric"><span class="material-symbols-rounded">school</span>${
                        insight.details.learnings || 0
                      } nauka</span>
                    </div>
                    ${
                      insight.details.trend
                        ? `
                    <div class="report-trend ${
                      insight.details.trend === "świetny"
                        ? "excellent"
                        : insight.details.trend === "dobry"
                        ? "good"
                        : insight.details.trend === "umiarkowany"
                        ? "moderate"
                        : "attention"
                    }">
                      <span class="material-symbols-rounded">${
                        insight.details.trend === "świetny"
                          ? "rocket_launch"
                          : insight.details.trend === "dobry"
                          ? "trending_up"
                          : insight.details.trend === "umiarkowany"
                          ? "trending_flat"
                          : "warning"
                      }</span>
                      Trend: ${insight.details.trend}
                    </div>`
                        : ""
                    }`
                  : ""
              }
            </div>
          </div>
        `;
        })
        .join("");

      // Fetch all factors section (async)
      const allFactorsHtml = await this.renderAllFactorsSection();

      container.innerHTML = `
        ${statsHtml}

        ${allFactorsHtml}
        
        <div class="chart-controls-wrapper" style="display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 16px; margin-bottom: 24px;">
          <div class="chart-controls" style="margin-bottom: 0;">
            <button class="chart-range-btn active" data-range="7">7 dni</button>
            <button class="chart-range-btn" data-range="30">30 dni</button>
            <button class="chart-range-btn" data-range="365">Rok</button>
            <button class="chart-range-btn" data-range="all">Wszystko</button>
          </div>
          
          <!-- Extra controls for filtering -->
          <div id="chart-extra-controls" style="display: flex; align-items: center; gap: 12px; height: 32px;">
            <!-- 30 Days: End Date Picker (Custom) -->
            <div id="control-30-days" style="display: none; align-items: center; gap: 8px;">
               <div class="custom-picker-wrapper" id="custom-date-wrapper">
                  <div class="custom-picker-trigger" id="custom-date-trigger">
                    <span class="material-symbols-rounded">calendar_today</span>
                    <span id="custom-date-text">Wybierz datę</span>
                  </div>
                  <div class="custom-picker-popup custom-calendar" id="custom-date-popup"></div>
               </div>
            </div>
            <!-- Year: Year Select (Custom) -->
            <div id="control-year" style="display: none; align-items: center; gap: 8px;">
               <div class="custom-picker-wrapper" id="custom-year-wrapper">
                  <div class="custom-picker-trigger" id="custom-year-trigger" style="min-width: 100px;">
                    <span class="material-symbols-rounded">calendar_month</span>
                    <span id="custom-year-text">Rok</span>
                    <span class="material-symbols-rounded" style="font-size: 16px; color: #cbd5e1;">expand_more</span>
                  </div>
                  <div class="custom-picker-popup custom-year-dropdown" id="custom-year-popup"></div>
               </div>
            </div>
          </div>
        </div>

        <div class="ai-charts-container">
          <div class="chart-wrapper">
            <div class="chart-scroll-container" style="width: 100%; height: 100%; position: relative;">
                <canvas id="accuracyChart"></canvas>
            </div>
          </div>
          <div class="chart-wrapper">
            <div class="chart-scroll-container" style="width: 100%; height: 100%; position: relative;">
                <canvas id="comparisonChart"></canvas>
            </div>
          </div>
        </div>
        <div id="heatmapContainer"></div>
        ${dateFilterHtml}
        <div class="ai-insights-timeline">
          ${timelineHtml}
        </div>
        <div class="ai-insights-note">
          <span class="material-symbols-rounded">info</span>
          <span>Te wnioski są automatycznie generowane na podstawie porównania predykcji z rzeczywistością. Algorytm uczy się z każdym dniem!</span>
        </div>
      `;

      // Add custom date filter functionality
      this.setupInsightDateFilter(container);

      // Setup chart controls (listeners)
      this.setupChartControls();

      // Initialize charts
      this.initAICharts(insights);

      // Add heatmap after charts
      const heatmapContainer = document.getElementById("heatmapContainer");
      if (heatmapContainer) this.renderHeatmap(heatmapContainer);
    } catch (error) {
      console.error("AI Insights error:", error);

      // Fallback to prediction-based insights
      this.renderPredictionInsights(container);
    }
  }

  // Custom date filter with search and suggestions
  setupInsightDateFilter(container) {
    const searchInput = document.getElementById("insightDateSearch");
    const suggestionsDiv = document.getElementById("insightDateSuggestions");
    const clearBtn = document.getElementById("insightDateClear");
    const countSpan = document.getElementById("insightFilteredCount");

    if (!searchInput || !suggestionsDiv || !this.insightDates) return;

    const dates = this.insightDates;
    let selectedIndex = -1;
    let currentType = "";
    let currentDate = "";

    // Combined filter function (type + date)
    const filterItems = () => {
      const items = container.querySelectorAll(".insight-timeline-item");
      let visibleCount = 0;
      items.forEach((item) => {
        const itemDate = item.dataset.forDate || "";
        const itemType =
          item.classList.contains("weekly_report") ||
          item.classList.contains("monthly_report")
            ? "reports"
            : [...item.classList].find((c) =>
                ["verification", "correction", "learning", "pattern"].includes(
                  c
                )
              ) || "";

        const matchesDate = !currentDate || itemDate === currentDate;
        const matchesType =
          !currentType ||
          (currentType === "reports"
            ? item.classList.contains("weekly_report") ||
              item.classList.contains("monthly_report")
            : itemType === currentType);

        if (matchesDate && matchesType) {
          item.style.display = "";
          visibleCount++;
        } else {
          item.style.display = "none";
        }
      });
      if (countSpan) countSpan.textContent = visibleCount;
    };

    // Custom type filter dropdown
    const typeFilterBtn = document.getElementById("typeFilterBtn");
    const typeFilterDropdown = document.getElementById("typeFilterDropdown");

    if (typeFilterBtn && typeFilterDropdown) {
      // Toggle dropdown
      typeFilterBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        typeFilterDropdown.classList.toggle("open");
        typeFilterBtn.classList.toggle("open");
      });

      // Option click
      typeFilterDropdown.querySelectorAll(".type-option").forEach((option) => {
        option.addEventListener("click", () => {
          currentType = option.dataset.value;
          // Update button text and icon
          const icon = option.querySelector(
            ".material-symbols-rounded"
          ).textContent;
          const text = option.querySelector("span:last-child").textContent;
          typeFilterBtn.querySelector(
            ".material-symbols-rounded:first-child"
          ).textContent = icon;
          typeFilterBtn.querySelector(".type-filter-text").textContent = text;
          // Close dropdown
          typeFilterDropdown.classList.remove("open");
          typeFilterBtn.classList.remove("open");
          // Apply filter
          filterItems();
        });
      });

      // Close on click outside
      document.addEventListener("click", (e) => {
        if (!e.target.closest(".type-filter-wrapper")) {
          typeFilterDropdown.classList.remove("open");
          typeFilterBtn.classList.remove("open");
        }
      });
    }

    // Show suggestions
    const showSuggestions = (query = "") => {
      const filtered = query
        ? dates.filter((d) => d.includes(query))
        : dates.slice(0, 10); // Show first 10 if no query

      if (filtered.length === 0) {
        suggestionsDiv.innerHTML =
          '<div class="date-suggestion-empty">Brak wyników</div>';
        suggestionsDiv.classList.add("open");
        return;
      }

      suggestionsDiv.innerHTML = filtered
        .map(
          (d, i) => `
        <div class="date-suggestion-item ${
          i === selectedIndex ? "selected" : ""
        }" data-date="${d}">
          <span class="material-symbols-rounded">event</span>
          ${d}
        </div>
      `
        )
        .join("");
      suggestionsDiv.classList.add("open");

      // Add click handlers
      suggestionsDiv
        .querySelectorAll(".date-suggestion-item")
        .forEach((item) => {
          item.addEventListener("click", () => {
            searchInput.value = item.dataset.date;
            currentDate = item.dataset.date;
            filterItems();
            suggestionsDiv.classList.remove("open");
          });
        });
    };

    // Input events
    searchInput.addEventListener("focus", () => {
      selectedIndex = -1;
      showSuggestions(searchInput.value);
    });

    searchInput.addEventListener("input", (e) => {
      selectedIndex = -1;
      const value = e.target.value.trim();
      showSuggestions(value);

      // Live filtering
      if (dates.includes(value)) {
        currentDate = value;
        filterItems();
      } else if (!value) {
        currentDate = "";
        filterItems();
      }
    });

    // Keyboard navigation
    searchInput.addEventListener("keydown", (e) => {
      const items = suggestionsDiv.querySelectorAll(".date-suggestion-item");

      if (e.key === "ArrowDown") {
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
        items.forEach((item, i) =>
          item.classList.toggle("selected", i === selectedIndex)
        );
        if (items[selectedIndex])
          items[selectedIndex].scrollIntoView({ block: "nearest" });
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, 0);
        items.forEach((item, i) =>
          item.classList.toggle("selected", i === selectedIndex)
        );
        if (items[selectedIndex])
          items[selectedIndex].scrollIntoView({ block: "nearest" });
      } else if (e.key === "Enter" && selectedIndex >= 0) {
        e.preventDefault();
        const selected = items[selectedIndex];
        if (selected) {
          searchInput.value = selected.dataset.date;
          currentDate = selected.dataset.date;
          filterItems();
          suggestionsDiv.classList.remove("open");
        }
      } else if (e.key === "Escape") {
        suggestionsDiv.classList.remove("open");
      }
    });

    // Clear button
    if (clearBtn) {
      clearBtn.addEventListener("click", () => {
        searchInput.value = "";
        currentDate = "";
        filterItems();
        suggestionsDiv.classList.remove("open");
      });
    }

    // Close on click outside
    document.addEventListener("click", (e) => {
      if (!e.target.closest(".date-filter-input-wrapper")) {
        suggestionsDiv.classList.remove("open");
      }
    });
  }

  setupChartControls() {
    const controls = document.querySelectorAll(".chart-range-btn");
    const container30 = document.getElementById("control-30-days");
    const containerYear = document.getElementById("control-year");

    // Initialize Custom Pickers
    this.setupCustomPickers();

    controls.forEach((btn) => {
      btn.addEventListener("click", () => {
        try {
          // Update UI
          document
            .querySelectorAll(".chart-range-btn")
            .forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");

          const range = btn.dataset.range;

          // Toggle extra controls
          if (container30)
            container30.style.display = range === "30" ? "flex" : "none";
          if (containerYear)
            containerYear.style.display = range === "365" ? "flex" : "none";

          // Re-init charts with new range
          this.initAICharts(null, range);
        } catch (err) {
          console.error("Chart control error:", err);
        }
      });
    });
  }

  setupCustomPickers() {
    // --- Date Picker Logic ---
    const dateTrigger = document.getElementById("custom-date-trigger");
    const datePopup = document.getElementById("custom-date-popup");
    const dateText = document.getElementById("custom-date-text");
    const dateWrapper = document.getElementById("custom-date-wrapper");

    // Create hidden input for compatibility if not exists
    let hiddenDateInput = document.getElementById("chart-end-date");
    if (!hiddenDateInput) {
      hiddenDateInput = document.createElement("input");
      hiddenDateInput.type = "hidden";
      hiddenDateInput.id = "chart-end-date";
      document.body.appendChild(hiddenDateInput);
    }

    // Initialize state
    let currentCalendarDate = new Date();
    let selectedDate = new Date(); // Default today

    // Initial value
    hiddenDateInput.value = selectedDate.toISOString().split("T")[0];
    if (dateText)
      dateText.textContent = selectedDate.toLocaleDateString("pl-PL");

    // Functions
    const closeDatePopup = () => {
      if (datePopup) datePopup.classList.remove("open");
      if (dateTrigger) dateTrigger.classList.remove("active");
    };

    const renderCalendar = (baseDate) => {
      if (!datePopup) return;
      const year = baseDate.getFullYear();
      const month = baseDate.getMonth();

      // Header
      const monthName = baseDate.toLocaleDateString("pl-PL", { month: "long" });
      let html = `
             <div class="calendar-header">
                <button class="calendar-nav-btn" data-action="prev"><span class="material-symbols-rounded" style="font-size:16px">chevron_left</span></button>
                <div class="calendar-current-month">${monthName} ${year}</div>
                <button class="calendar-nav-btn" data-action="next"><span class="material-symbols-rounded" style="font-size:16px">chevron_right</span></button>
             </div>
             <div class="calendar-grid">
               <div class="calendar-day-label">Pn</div><div class="calendar-day-label">Wt</div><div class="calendar-day-label">Śr</div><div class="calendar-day-label">Cz</div><div class="calendar-day-label">Pt</div><div class="calendar-day-label">So</div><div class="calendar-day-label">Nd</div>
          `;

      // Days
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const daysInMonth = lastDay.getDate();

      let startDay = firstDay.getDay() || 7; // 1 (Mon) - 7 (Sun)
      startDay -= 1; // 0 (Mon) - 6 (Sun) for loop

      // Empty slots
      for (let i = 0; i < startDay; i++) {
        html += `<div class="calendar-day empty"></div>`;
      }

      // Days
      const todayGrid = new Date();
      todayGrid.setHours(0, 0, 0, 0);

      // Calculate Range
      const rangeEnd = new Date(selectedDate);
      rangeEnd.setHours(0, 0, 0, 0);
      const rangeStart = new Date(rangeEnd);
      rangeStart.setDate(rangeEnd.getDate() - 29); // 30 days inclusive
      rangeStart.setHours(0, 0, 0, 0);

      for (let d = 1; d <= daysInMonth; d++) {
        const dDate = new Date(year, month, d);
        dDate.setHours(0, 0, 0, 0);

        let classes = "calendar-day";

        // Range Check
        if (dDate >= rangeStart && dDate <= rangeEnd) {
          classes += " in-range";
        }
        if (dDate.getTime() === rangeStart.getTime()) {
          classes += " range-start";
        }

        // Check if selected
        if (
          selectedDate &&
          dDate.getTime() === selectedDate.setHours(0, 0, 0, 0)
        ) {
          classes += " selected";
        }
        // Check if today
        if (dDate.getTime() === todayGrid.getTime()) {
          classes += " today";
        }

        html += `<div class="${classes}" data-day="${d}">${d}</div>`;
      }
      html += `</div>`; // Close grid

      // Add "Today" shortcut
      html += `<div style="margin-top:12px;display:flex;justify-content:space-between;border-top:1px solid #f1f5f9;padding-top:8px;">
             <button class="text-btn-sm" id="calendar-clear" style="color:#ef4444;font-size:0.8rem;background:none;border:none;cursor:pointer;font-weight:500;">Anuluj</button>
             <button class="text-btn-sm" id="calendar-today" style="color:#10b981;font-size:0.8rem;background:none;border:none;cursor:pointer;font-weight:500;">Dzisiaj</button>
          </div>`;

      datePopup.innerHTML = html;

      // Listeners inside popup
      datePopup.querySelectorAll(".calendar-nav-btn").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          const action = btn.dataset.action;
          currentCalendarDate.setMonth(
            currentCalendarDate.getMonth() + (action === "next" ? 1 : -1)
          );
          renderCalendar(currentCalendarDate);
        });
      });

      datePopup
        .querySelectorAll(".calendar-day:not(.empty)")
        .forEach((dayEl) => {
          dayEl.addEventListener("click", (e) => {
            e.stopPropagation();
            const day = parseInt(dayEl.dataset.day);
            selectedDate = new Date(
              currentCalendarDate.getFullYear(),
              currentCalendarDate.getMonth(),
              day
            );

            // Update visual
            dateText.textContent = selectedDate.toLocaleDateString("pl-PL");

            // Update Hidden Input
            const y = selectedDate.getFullYear();
            const m = String(selectedDate.getMonth() + 1).padStart(2, "0");
            const dC = String(selectedDate.getDate()).padStart(2, "0");
            hiddenDateInput.value = `${y}-${m}-${dC}`; // YYYY-MM-DD

            closeDatePopup();

            // Refresh Chart
            this.initAICharts(null, "30"); // Force 30 days
          });
        });

      const todayBtn = datePopup.querySelector("#calendar-today");
      if (todayBtn)
        todayBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          selectedDate = new Date();
          currentCalendarDate = new Date();
          dateText.textContent = selectedDate.toLocaleDateString("pl-PL");
          hiddenDateInput.value = new Date().toISOString().split("T")[0];
          closeDatePopup();
          this.initAICharts(null, "30");
        });

      const clearBtn = datePopup.querySelector("#calendar-clear");
      if (clearBtn)
        clearBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          closeDatePopup();
        });
    };

    // Trigger Event
    if (dateTrigger) {
      dateTrigger.addEventListener("click", (e) => {
        e.stopPropagation();
        if (datePopup.classList.contains("open")) {
          closeDatePopup();
        } else {
          // Render fresh
          currentCalendarDate = new Date(selectedDate || new Date());
          renderCalendar(currentCalendarDate);
          datePopup.classList.add("open");
          dateTrigger.classList.add("active");
        }
      });
    }

    // --- Year Picker Logic ---
    const yearTrigger = document.getElementById("custom-year-trigger");
    const yearPopup = document.getElementById("custom-year-popup");
    const yearText = document.getElementById("custom-year-text");

    // Hidden Input
    let hiddenYearInput = document.getElementById("chart-year-select");
    if (!hiddenYearInput) {
      hiddenYearInput = document.createElement("input"); // Input works fine for fetching value
      hiddenYearInput.type = "hidden";
      hiddenYearInput.id = "chart-year-select";
      document.body.appendChild(hiddenYearInput);
    }

    const currentYear = new Date().getFullYear();
    let selectedYear = currentYear;
    hiddenYearInput.value = selectedYear;
    if (yearText) yearText.textContent = selectedYear;

    const renderYears = () => {
      if (!yearPopup) return;

      // Filter available years from data
      let years = new Set();
      if (
        this.rawInsightsForCharts &&
        Array.isArray(this.rawInsightsForCharts)
      ) {
        this.rawInsightsForCharts.forEach((item) => {
          if (item.date) {
            const y = new Date(item.date).getFullYear();
            if (y) years.add(y);
          }
        });
      }

      let yearsArr = Array.from(years).sort((a, b) => b - a);

      // Fallback
      if (yearsArr.length === 0) {
        const cy = new Date().getFullYear();
        yearsArr = [cy, cy - 1, cy - 2];
      }

      let html = "";
      yearsArr.forEach((y) => {
        const isSel = y === selectedYear;
        html += `<div class="custom-option ${
          isSel ? "selected" : ""
        }" data-value="${y}">${y}</div>`;
      });
      yearPopup.innerHTML = html;

      // Listeners
      yearPopup.querySelectorAll(".custom-option").forEach((opt) => {
        opt.addEventListener("click", (e) => {
          e.stopPropagation();
          selectedYear = parseInt(opt.dataset.value);
          yearText.textContent = selectedYear;
          hiddenYearInput.value = selectedYear;
          closeYearPopup();
          this.initAICharts(null, "365");
        });
      });
    };

    const closeYearPopup = () => {
      if (yearPopup) yearPopup.classList.remove("open");
      if (yearTrigger) yearTrigger.classList.remove("active");
    };

    if (yearTrigger) {
      yearTrigger.addEventListener("click", (e) => {
        e.stopPropagation();
        if (yearPopup.classList.contains("open")) {
          closeYearPopup();
        } else {
          renderYears();
          yearPopup.classList.add("open");
          yearTrigger.classList.add("active");
        }
      });
    }

    // Global Click to Close
    document.addEventListener("click", (e) => {
      if (dateWrapper && !dateWrapper.contains(e.target)) closeDatePopup();
      if (
        yearTrigger &&
        !document.getElementById("custom-year-wrapper").contains(e.target)
      )
        closeYearPopup();
    });
  }

  // Helper to aggregate data by month
  aggregateByMonth(dailyData) {
    const monthly = {};

    dailyData.forEach((item) => {
      const date = item.details.forDate; // YYYY-MM-DD
      const monthKey = date.substring(0, 7); // YYYY-MM

      if (!monthly[monthKey]) {
        monthly[monthKey] = {
          date: monthKey,
          sumPredicted: 0,
          sumActual: 0,
          sumAccuracy: 0,
          count: 0,
          countAccuracy: 0,
        };
      }

      const rec = monthly[monthKey];
      rec.sumPredicted += item.details.predicted || 0;
      rec.sumActual += item.details.actual || 0;
      rec.count++;

      if (item.details.accuracy !== undefined) {
        rec.sumAccuracy += item.details.accuracy;
        rec.countAccuracy++;
      }
    });

    // Convert to array and average
    return Object.values(monthly)
      .sort((a, b) => a.date.localeCompare(b.date))
      .map((m) => {
        return {
          details: {
            forDate: m.date, // Display as month
            predicted: Math.round(m.sumPredicted / m.count),
            actual: Math.round(m.sumActual / m.count),
            accuracy: m.countAccuracy > 0 ? m.sumAccuracy / m.countAccuracy : 0,
          },
          type: "monthly_aggregate",
        };
      });
  }

  initAICharts(insights, range = "7") {
    try {
      if (typeof Chart === "undefined") return;

      // Destroy old charts to prevent "Canvas is already in use" error
      if (this.accuracyChartInstance) {
        this.accuracyChartInstance.destroy();
        this.accuracyChartInstance = null;
      }
      if (this.comparisonChartInstance) {
        this.comparisonChartInstance.destroy();
        this.comparisonChartInstance = null;
      }

      // Store raw insights for filtering if not already stored or if provided (initial load)
      if (insights) {
        this.rawInsightsForCharts = insights;
      } else {
        insights = this.rawInsightsForCharts || [];
      }

      // Filter only Verification items
      let verificationData = insights
        .filter(
          (i) =>
            (i.type === "verification" ||
              i.type === "correction" ||
              (i.type === "learning" &&
                i.details?.predicted &&
                i.details?.actual)) &&
            i.details &&
            i.details.forDate &&
            i.details.predicted !== undefined && // Ensure predicted is present
            i.details.actual !== undefined // Ensure actual is present
        )
        .sort(
          (a, b) =>
            new Date(a.details.forDate).getTime() -
            new Date(b.details.forDate).getTime()
        );

      // Filter Logic based on Range and Controls
      if (range === "30") {
        const dateInput = document.getElementById("chart-end-date");
        let endDate = new Date();
        if (dateInput && dateInput.value) {
          endDate = new Date(dateInput.value);
        }
        // Start date is endDate - 30 days
        const startDate = new Date(endDate);
        startDate.setDate(endDate.getDate() - 30);

        // Use reliable string comparison YYYY-MM-DD
        const startStr = startDate.toISOString().split("T")[0];
        const endStr = endDate.toISOString().split("T")[0];

        verificationData = verificationData.filter((item) => {
          const itemDate = item.details.forDate;
          return itemDate >= startStr && itemDate <= endStr;
        });
      } else if (range === "365") {
        const yearSelect = document.getElementById("chart-year-select");
        let year = new Date().getFullYear();
        if (yearSelect && yearSelect.value) {
          year = parseInt(yearSelect.value);
        }

        verificationData = verificationData.filter((item) => {
          const d = new Date(item.details.forDate);
          return d.getFullYear() === year;
        });

        // Aggregate by Month
        verificationData = this.aggregateByMonth(verificationData);
      } else if (range === "7") {
        verificationData = verificationData.slice(-7);
      } else if (range === "all") {
        // All data, but aggregated by month for readability if too many points?
        // User requested: "tryb wszystko powinien pokazywac to samo ale nie byc limitowany rokiem"
        // implying it should ALSO be monthly aggregated.
        verificationData = this.aggregateByMonth(verificationData);
      }

      // Deduplicate (handle potentially multiple entries per day/month if raw data has dups)
      // For monthly agg, keys are unique already. For daily, might need uniq.
      // This deduplication is for daily data, taking the latest entry for a given forDate
      // Deduplicate only if NOT aggregated (daily data)
      // Monthly aggregation already produces unique keys
      if (range !== "365" && range !== "all") {
        const uniqueMap = new Map();
        verificationData.forEach((i) => {
          const existing = uniqueMap.get(i.details.forDate);
          // Prioritize verification/correction with latest timestamp
          if (
            !existing ||
            (i.timestamp &&
              existing.timestamp &&
              new Date(i.timestamp) > new Date(existing.timestamp))
          ) {
            uniqueMap.set(i.details.forDate, i);
          }
        });
        verificationData = Array.from(uniqueMap.values());
      }

      // Final sort by date string (works for YYYY-MM-DD and YYYY-MM)
      verificationData.sort((a, b) =>
        a.details.forDate.localeCompare(b.details.forDate)
      );

      // Allow displaying even with 1 point, just to show something
      if (verificationData.length === 0) return;

      const labels = verificationData.map((i) => {
        // Use forDate for the label
        const dateParts = i.details.forDate.split("-"); // YYYY-MM-DD or YYYY-MM
        if (dateParts.length === 3) {
          return `${dateParts[2]}.${dateParts[1]}`; // DD.MM
        } else if (dateParts.length === 2) {
          // YYYY-MM
          const months = [
            "Sty",
            "Lut",
            "Mar",
            "Kwi",
            "Maj",
            "Cze",
            "Lip",
            "Sie",
            "Wrz",
            "Paź",
            "Lis",
            "Gru",
          ];
          const mIndex = parseInt(dateParts[1]) - 1;
          return `${months[mIndex]} ${dateParts[0]}`;
        }
        return `${date.getDate()}.${date.getMonth() + 1}`;
      });

      const accuracyData = verificationData.map((i) => {
        // Use provided accuracy, or calculate from predicted/actual
        if (i.details.accuracy) {
          return (i.details.accuracy * 100).toFixed(1);
        }
        // Calculate accuracy: 1 - |predicted - actual| / actual
        const pred = i.details.predicted || 0;
        const act = i.details.actual || 1;
        const acc = 1 - Math.abs(pred - act) / act;
        return (Math.max(0, acc) * 100).toFixed(1);
      });
      const predictedData = verificationData.map(
        (i) => i.details.predicted || 0
      );
      const actualData = verificationData.map((i) => i.details.actual || 0);

      // Handle scrolling for 'all' mode
      const scrollContainers = document.querySelectorAll(
        ".chart-scroll-container"
      );
      const wrappers = document.querySelectorAll(".chart-wrapper");

      if (range === "all") {
        const computedWidth =
          Math.max(100, verificationData.length * 60) + "px"; // 60px per point
        scrollContainers.forEach((el) => {
          el.style.minWidth = "100%"; // Ensure full width if content is small
          el.style.width = computedWidth; // Expand if content is large
        });
        wrappers.forEach((el) => (el.style.overflowX = "auto"));
      } else {
        scrollContainers.forEach((el) => {
          el.style.width = "100%";
          el.style.minWidth = "100%";
        });
        wrappers.forEach((el) => (el.style.overflowX = "auto")); // Keep auto for safeguards
      }

      // Accuracy Chart - Enhanced with gradient
      const accuracyCtx = document
        .getElementById("accuracyChart")
        ?.getContext("2d");
      if (accuracyCtx) {
        // Create premium gradient fill with vibrant colors
        const gradient = accuracyCtx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, "rgba(16, 185, 129, 0.35)");
        gradient.addColorStop(0.4, "rgba(16, 185, 129, 0.15)");
        gradient.addColorStop(1, "rgba(16, 185, 129, 0.02)");

        // Create glow effect gradient for the line
        const lineGradient = accuracyCtx.createLinearGradient(
          0,
          0,
          accuracyCtx.canvas.width,
          0
        );
        lineGradient.addColorStop(0, "#059669");
        lineGradient.addColorStop(0.5, "#10b981");
        lineGradient.addColorStop(1, "#34d399");

        this.accuracyChartInstance = new Chart(accuracyCtx, {
          type: "line",
          data: {
            labels: labels,
            datasets: [
              {
                label: "Dokładność (%)",
                data: accuracyData,
                borderColor: lineGradient,
                backgroundColor: gradient,
                borderWidth: 3.5,
                fill: true,
                tension: 0.45,
                pointRadius: 6,
                pointBackgroundColor: "#fff",
                pointBorderColor: "#10b981",
                pointBorderWidth: 3,
                pointHoverRadius: 9,
                pointHoverBackgroundColor: "#10b981",
                pointHoverBorderColor: "#fff",
                pointHoverBorderWidth: 3,
                shadowOffsetX: 0,
                shadowOffsetY: 4,
                shadowBlur: 12,
                shadowColor: "rgba(16, 185, 129, 0.4)",
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
              duration: 1200,
              easing: "easeOutQuart",
            },
            interaction: {
              mode: "index",
              intersect: false,
            },
            plugins: {
              legend: { display: false },
              title: {
                display: true,
                text: "Trend Dokładności",
                font: {
                  size: 16,
                  family: "'Outfit', sans-serif",
                  weight: "700",
                },
                color: "#1f2937",
                padding: { bottom: 20, top: 8 },
              },
              tooltip: {
                enabled: false,
                external: (context) => {
                  // Get or create tooltip element
                  let tooltip = document.getElementById(
                    "accuracy-chart-tooltip"
                  );
                  if (!tooltip) {
                    tooltip = document.createElement("div");
                    tooltip.id = "accuracy-chart-tooltip";
                    tooltip.className = "custom-chart-tooltip";
                    document.body.appendChild(tooltip);
                  }

                  const tooltipModel = context.tooltip;

                  // Hide if no tooltip
                  if (tooltipModel.opacity === 0) {
                    tooltip.style.opacity = "0";
                    tooltip.style.pointerEvents = "none";
                    return;
                  }

                  // Get data
                  const dataPoint = tooltipModel.dataPoints?.[0];
                  if (!dataPoint) return;

                  const val = dataPoint.parsed.y;
                  const label = dataPoint.label;
                  const icon =
                    val >= 90
                      ? "check_circle"
                      : val >= 70
                      ? "verified"
                      : "trending_down";
                  const iconColor =
                    val >= 90 ? "#10b981" : val >= 70 ? "#f59e0b" : "#ef4444";
                  const statusText =
                    val >= 90 ? "Świetna" : val >= 70 ? "Dobra" : "Niska";

                  // Build HTML with Material Icons
                  tooltip.innerHTML = `
                  <div class="tooltip-header">
                    <span class="material-symbols-rounded tooltip-icon" style="font-size: 18px;">calendar_month</span>
                    <span class="tooltip-date">${label}</span>
                  </div>
                  <div class="tooltip-body">
                    <span class="material-symbols-rounded tooltip-icon" style="color: ${iconColor}">${icon}</span>
                    <span class="tooltip-label">${statusText} dokładność: <strong>${val}%</strong></span>
                  </div>
                `;

                  // Position tooltip with boundary checking
                  const position = context.chart.canvas.getBoundingClientRect();
                  tooltip.style.opacity = "1";

                  // Calculate position
                  let left =
                    position.left + window.scrollX + tooltipModel.caretX;
                  let top =
                    position.top + window.scrollY + tooltipModel.caretY - 60;

                  // Check right boundary
                  const tooltipWidth = tooltip.offsetWidth || 200;
                  if (left + tooltipWidth > window.innerWidth - 10) {
                    left = window.innerWidth - tooltipWidth - 10;
                  }
                  // Check left boundary
                  if (left < 10) {
                    left = 10;
                  }

                  tooltip.style.left = left + "px";
                  tooltip.style.top = top + "px";
                },
              },
            },
            scales: {
              y: {
                beginAtZero: true,
                suggestedMax: 100,
                grid: {
                  color: "rgba(0,0,0,0.04)",
                  drawBorder: false,
                  lineWidth: 1,
                },
                border: { display: false },
                ticks: {
                  font: { size: 11, weight: "500" },
                  color: "#6b7280",
                  callback: (value) => value + "%",
                  padding: 8,
                },
              },
              x: {
                grid: { display: false },
                border: { display: false },
                ticks: {
                  font: { size: 11, weight: "500" },
                  color: "#6b7280",
                  padding: 8,
                },
              },
            },
          },
        });
      }

      // Comparison Chart (Predicted vs Actual) - Premium Redesign
      const comparisonCtx = document
        .getElementById("comparisonChart")
        ?.getContext("2d");
      if (comparisonCtx) {
        // Create vibrant gradients for bars
        const indigoGradient = comparisonCtx.createLinearGradient(0, 0, 0, 280);
        indigoGradient.addColorStop(0, "rgba(99, 102, 241, 0.95)");
        indigoGradient.addColorStop(0.5, "rgba(99, 102, 241, 0.75)");
        indigoGradient.addColorStop(1, "rgba(129, 140, 248, 0.55)");

        const emeraldGradient = comparisonCtx.createLinearGradient(
          0,
          0,
          0,
          280
        );
        emeraldGradient.addColorStop(0, "rgba(16, 185, 129, 0.95)");
        emeraldGradient.addColorStop(0.5, "rgba(16, 185, 129, 0.75)");
        emeraldGradient.addColorStop(1, "rgba(52, 211, 153, 0.55)");

        this.comparisonChartInstance = new Chart(comparisonCtx, {
          type: "bar",
          data: {
            labels: labels,
            datasets: [
              {
                label: "Prognoza AI",
                data: predictedData,
                backgroundColor: indigoGradient,
                hoverBackgroundColor: "rgba(99, 102, 241, 1)",
                borderRadius: 8,
                borderSkipped: false,
                borderWidth: 0,
              },
              {
                label: "Rzeczywistość",
                data: actualData,
                backgroundColor: emeraldGradient,
                hoverBackgroundColor: "rgba(16, 185, 129, 1)",
                borderRadius: 8,
                borderSkipped: false,
                borderWidth: 0,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
              duration: 1000,
              easing: "easeOutQuart",
              delay: (context) => context.dataIndex * 100,
            },
            interaction: {
              mode: "index",
              intersect: false,
            },
            plugins: {
              legend: {
                position: "bottom",
                labels: {
                  padding: 20,
                  usePointStyle: true,
                  pointStyle: "circle",
                  font: { size: 12, weight: "500" },
                  color: "#4b5563",
                },
              },
              title: {
                display: true,
                text: "Prognoza vs Rzeczywistość",
                font: {
                  size: 16,
                  family: "'Outfit', sans-serif",
                  weight: "700",
                },
                color: "#1f2937",
                padding: { bottom: 20, top: 8 },
              },
              tooltip: {
                enabled: false,
                external: (context) => {
                  // Get or create tooltip element
                  let tooltip = document.getElementById(
                    "comparison-chart-tooltip"
                  );
                  if (!tooltip) {
                    tooltip = document.createElement("div");
                    tooltip.id = "comparison-chart-tooltip";
                    tooltip.className = "custom-chart-tooltip";
                    document.body.appendChild(tooltip);
                  }

                  const tooltipModel = context.tooltip;

                  // Hide if no tooltip
                  if (tooltipModel.opacity === 0) {
                    tooltip.style.opacity = "0";
                    tooltip.style.pointerEvents = "none";
                    return;
                  }

                  // Get data
                  const dataPoints = tooltipModel.dataPoints;
                  if (!dataPoints || dataPoints.length === 0) return;

                  const label = dataPoints[0].label;
                  const predicted = dataPoints[0]?.parsed.y || 0;
                  const actual = dataPoints[1]?.parsed.y || 0;
                  const diff = actual - predicted;
                  const diffIcon =
                    diff > 0
                      ? "trending_up"
                      : diff < 0
                      ? "trending_down"
                      : "check_circle";
                  const diffColor =
                    diff > 0 ? "#10b981" : diff < 0 ? "#ef4444" : "#6366f1";
                  const sign = diff > 0 ? "+" : "";

                  // Build HTML with Material Icons
                  tooltip.innerHTML = `
                  <div class="tooltip-header">
                    <span class="material-symbols-rounded tooltip-icon" style="font-size: 18px;">calendar_month</span>
                    <span class="tooltip-date">${label}</span>
                  </div>
                  <div class="tooltip-body">
                    <div class="tooltip-row">
                      <span class="material-symbols-rounded tooltip-icon" style="color: #6366f1;">psychology</span>
                      <span class="tooltip-label">Prognoza AI: <strong>${predicted}</strong></span>
                    </div>
                    <div class="tooltip-row">
                      <span class="material-symbols-rounded tooltip-icon" style="color: #10b981;">groups</span>
                      <span class="tooltip-label">Rzeczywistość: <strong>${actual}</strong></span>
                    </div>
                    <div class="tooltip-divider"></div>
                    <div class="tooltip-row">
                      <span class="material-symbols-rounded tooltip-icon" style="color: ${diffColor}">${diffIcon}</span>
                      <span class="tooltip-label">Różnica: <strong>${sign}${diff}</strong> widzów</span>
                    </div>
                  </div>
                `;

                  // Position tooltip with boundary checking
                  const position = context.chart.canvas.getBoundingClientRect();
                  tooltip.style.opacity = "1";

                  // Calculate position
                  let left =
                    position.left + window.scrollX + tooltipModel.caretX;
                  let top =
                    position.top + window.scrollY + tooltipModel.caretY - 100;

                  // Check right boundary
                  const tooltipWidth = tooltip.offsetWidth || 220;
                  if (left + tooltipWidth > window.innerWidth - 10) {
                    left = window.innerWidth - tooltipWidth - 10;
                  }
                  // Check left boundary
                  if (left < 10) {
                    left = 10;
                  }

                  tooltip.style.left = left + "px";
                  tooltip.style.top = top + "px";
                },
              },
            },
            scales: {
              y: {
                beginAtZero: true,
                grid: {
                  color: "rgba(0,0,0,0.04)",
                  drawBorder: false,
                  lineWidth: 1,
                },
                border: { display: false },
                ticks: {
                  font: { size: 11, weight: "500" },
                  color: "#6b7280",
                  padding: 8,
                },
              },
              x: {
                grid: { display: false },
                border: { display: false },
                ticks: {
                  font: { size: 11, weight: "500" },
                  color: "#6b7280",
                  padding: 8,
                },
              },
            },
          },
        });
      }
    } catch (e) {
      console.error("AI Charts Error:", e);
    }
  }

  // Fallback method for when API fails
  renderPredictionInsights(container) {
    if (!this.data || !this.data.prediction) {
      container.innerHTML =
        '<p class="no-data">Brak danych predykcji. Algorytm potrzebuje danych, żeby się uczyć!</p>';
      return;
    }

    const pred = this.data.prediction;
    const factors = pred.factors || {};
    const confidence = pred.confidence || 0;

    // Generate insights based on prediction factors
    const insights = [];

    if (factors.weekend !== undefined) {
      insights.push({
        icon: "weekend",
        title: "Wpływ weekendu",
        text:
          factors.weekend > 1
            ? `Weekend zwiększa frekwencję o ~${Math.round(
                (factors.weekend - 1) * 100
              )}%`
            : `Weekend nie ma dużego wpływu (${factors.weekend.toFixed(2)})`,
        value: factors.weekend,
      });
    }

    if (factors.weather !== undefined) {
      insights.push({
        icon: "cloud",
        title: "Wpływ pogody",
        text:
          factors.weather > 1.1
            ? `Zła pogoda sprzyja kinu! (+${Math.round(
                (factors.weather - 1) * 100
              )}%)`
            : `Pogoda ma umiarkowany wpływ`,
        value: factors.weather,
      });
    }

    insights.push({
      icon: "psychology",
      title: "Pewność predykcji",
      text: `Niska pewność (${Math.round(
        confidence * 100
      )}%) - uczę się dopiero!`,
      value: confidence,
      isConfidence: true,
    });

    container.innerHTML = `
      <div class="ai-insights-grid">
        ${insights
          .map(
            (ins) => `
          <div class="ai-insight-card ${ins.isConfidence ? "confidence" : ""}">
            <div class="insight-icon">
              <span class="material-symbols-rounded">${ins.icon}</span>
            </div>
            <div class="insight-content">
              <h4>${ins.title}</h4>
              <p>${ins.text}</p>
              ${
                ins.value !== undefined && !ins.isConfidence
                  ? `<div class="insight-value">Mnożnik: ${ins.value.toFixed(
                      2
                    )}x</div>`
                  : ""
              }
            </div>
          </div>
        `
          )
          .join("")}
      </div>
      <div class="ai-insights-note">
        <span class="material-symbols-rounded">info</span>
        <span>Te wnioski są generowane na podstawie bieżących czynników. Więcej danych = lepsze wnioski!</span>
      </div>
    `;
  }
  checkOnboarding() {
    const isOnboardingComplete = localStorage.getItem(
      "helios_onboarding_complete"
    );
    if (!isOnboardingComplete) {
      this.renderOnboardingModal();
    }
  }

  // ==================== ALL FACTORS SECTION (System Factors Catalog) ====================
  async renderAllFactorsSection() {
    try {
      const response = await fetch(
        `api.php?action=all_factors&_t=${Date.now()}`
      );
      if (!response.ok) return "";
      const data = await response.json();

      if (!data.success || !data.factors || data.factors.length === 0) {
        return "";
      }

      const factors = data.factors;
      const stats = data.stats || {};
      const totalCount = factors.reduce((sum, f) => sum + f.values.length, 0);
      const learnedCount = factors.reduce(
        (sum, cat) => sum + cat.values.filter((v) => v.isLearned).length,
        0
      );

      return `
        <div class="all-factors-panel">
          <div class="all-factors-header" onclick="this.parentElement.classList.toggle('expanded')">
            <span class="material-symbols-rounded">tune</span>
            <h3>Czynniki predykcji</h3>
            <span class="factors-stats">${totalCount} czynników</span>
            <span class="material-symbols-rounded expand-icon">expand_more</span>
          </div>
          <div class="all-factors-content">
            
            <!-- Jak to działa -->
            <div class="factors-explainer">
              <div class="explainer-title">
                <span class="material-symbols-rounded">lightbulb</span>
                Jak działają czynniki?
              </div>
              <div class="explainer-content">
                <p>Każdy czynnik to <strong>mnożnik</strong> który modyfikuje bazową predykcję:</p>
                <div class="explainer-example">
                  <div class="example-row">
                    <span class="example-label">Bazowa predykcja:</span>
                    <span class="example-value">100 osób</span>
                  </div>
                  <div class="example-row highlight">
                    <span class="example-label">Czynnik "Boże Narodzenie":</span>
                    <span class="example-value text-positive">+30%</span>
                  </div>
                  <div class="example-row result">
                    <span class="example-label">Wynik:</span>
                    <span class="example-value">100 × 1.30 = <strong>130 osób</strong></span>
                  </div>
                </div>
                <p class="explainer-note">
                  <span class="text-positive">Zielone (+)</span> = więcej ludzi w kinie &nbsp;|&nbsp; 
                  <span class="text-negative">Czerwone (−)</span> = mniej ludzi
                </p>
              </div>
            </div>

            <!-- Liczniki -->
            <div class="factors-counters">
              <div class="counter-item">
                <span class="material-symbols-rounded">calendar_month</span>
                <div class="counter-data">
                  <span class="counter-value">${
                    stats.historyEntries || 0
                  }</span>
                  <span class="counter-label">dni w historii</span>
                </div>
              </div>
              <div class="counter-item">
                <span class="material-symbols-rounded">build</span>
                <div class="counter-data">
                  <span class="counter-value">${stats.totalSamples || 0}</span>
                  <span class="counter-label">korekt AI</span>
                </div>
              </div>
              <div class="counter-item">
                <span class="material-symbols-rounded">school</span>
                <div class="counter-data">
                  <span class="counter-value">${learnedCount}</span>
                  <span class="counter-label">czynniki skorygowane</span>
                </div>
              </div>
              <div class="counter-item">
                <span class="material-symbols-rounded">speed</span>
                <div class="counter-data">
                  <span class="counter-value">${stats.avgError || "—"}</span>
                  <span class="counter-label">śr. błąd</span>
                </div>
              </div>
            </div>

            <!-- Kategorie czynników -->
            <div class="factors-categories">
              ${factors
                .map(
                  (category) => `
                <div class="factor-category">
                  <div class="category-header">
                    <span class="material-symbols-rounded">${this.esc(
                      category.icon
                    )}</span>
                    <span class="category-name">${this.esc(
                      category.name
                    )}</span>
                  </div>
                  <div class="factor-values-grid">
                    ${category.values
                      .map(
                        (v) => `
                      <div class="factor-value-card ${
                        v.current > 1
                          ? "positive"
                          : v.current < 1
                          ? "negative"
                          : "neutral"
                      }${v.isLearned ? " learned" : ""}">
                        ${
                          v.isLearned
                            ? '<span class="material-symbols-rounded learned-mark">auto_fix_high</span>'
                            : ""
                        }
                        <span class="factor-label">${this.esc(v.label)}</span>
                        <span class="factor-multiplier">${
                          v.impact
                        }<span class="factor-weight">${v.current.toFixed(
                          2
                        )}</span></span>
                      </div>
                    `
                      )
                      .join("")}
                  </div>
                </div>
              `
                )
                .join("")}
            </div>
          </div>
        </div>
      `;
    } catch (error) {
      console.error("Error rendering all factors section:", error);
      return "";
    }
  }

  // ==================== ONBOARDING ====================
  renderOnboardingModal() {
    const steps = [
      {
        type: "welcome",
        icon: "movie",
        title: "Witaj w Helios Info!",
        subtitle: "Twoje centrum informacji o kinie Helios w Łodzi",
        features: [
          { icon: "analytics", text: "Predykcje obłożenia AI" },
          { icon: "event_seat", text: "Status sal w czasie rzeczywistym" },
          { icon: "calendar_month", text: "Pełny repertuar" },
        ],
        color: "linear-gradient(135deg, #d32f2f, #b71c1c)",
      },
      {
        type: "feature",
        icon: "analytics",
        title: "Predykcja Obłożenia",
        description:
          "Na górze strony znajdziesz baner z prognozą obłożenia kina.",
        details: [
          "Algorytm analizuje dane historyczne z ostatnich 30 dni",
          "Uwzględnia pogodę, dzień tygodnia i święta",
          "Pokazuje mnożnik (np. +40%) względem średniej",
        ],
        tip: "Kliknij baner, aby zobaczyć szczegółowe czynniki wpływające na prognozę.",
        color: "linear-gradient(135deg, #2196f3, #1565c0)",
      },
      {
        type: "feature",
        icon: "event_seat",
        title: "Status Sal",
        description:
          "Sekcja 'Status sal' pokazuje aktualny stan wszystkich sal kinowych.",
        details: [
          "Zielony pasek = mało zajętych miejsc",
          "Żółty pasek = średnie obłożenie",
          "Czerwony pasek = prawie pełna sala",
        ],
        tip: "Dane odświeżają się automatycznie co 30 sekund.",
        color: "linear-gradient(135deg, #00bcd4, #0097a7)",
      },
      {
        type: "feature",
        icon: "schedule",
        title: "Seanse i Statystyki",
        description:
          "Każdy film pokazuje listę seansów z kolorowanymi chipami.",
        details: [
          "Kolor chipa odpowiada obłożeniu seansu",
          "Kliknij chip, aby kupić bilet na Helios.pl",
          "Widoczne jest dokładne zajęcie: np. 45/120",
        ],
        tip: "Najedź na chip, aby zobaczyć prognozę dodatkowych widzów.",
        color: "linear-gradient(135deg, #ff9800, #e65100)",
      },
      {
        type: "feature",
        icon: "psychology",
        title: "Myśli AI",
        description: "Sekcja 'Myśli AI' to centrum inteligencji aplikacji.",
        details: [
          "Wykres dokładności pokazuje trafność prognoz",
          "Timeline zawiera szczegółowe korekty i weryfikacje",
          "Możesz śledzić jak algorytm się uczy",
        ],
        tip: "Sprawdzaj regularnie - AI uczy się i poprawia prognozy!",
        color: "linear-gradient(135deg, #9c27b0, #6a1b9a)",
      },
      {
        type: "finish",
        icon: "rocket_launch",
        title: "Gotowe!",
        subtitle: "Teraz wiesz już wszystko.",
        message: "Możesz w każdej chwili wrócić do tego poradnika w menu.",
        color: "linear-gradient(135deg, #4caf50, #2e7d32)",
      },
    ];

    let currentStep = 0;
    const modal = document.createElement("div");
    modal.className = "onboarding-overlay";

    const completeOnboarding = () => {
      localStorage.setItem("helios_onboarding_complete", "true");
      modal.classList.remove("visible");
      setTimeout(() => modal.remove(), 300);
    };

    const renderStep = () => {
      const step = steps[currentStep];
      const isLast = currentStep === steps.length - 1;
      const isFirst = currentStep === 0;
      const progress = ((currentStep + 1) / steps.length) * 100;

      let contentHtml = "";

      if (step.type === "welcome") {
        contentHtml = `
          <p class="onboarding-subtitle">${step.subtitle}</p>
          <div class="onboarding-features">
            ${step.features
              .map(
                (f) => `
              <div class="onboarding-feature-item">
                <span class="material-symbols-rounded">${f.icon}</span>
                <span>${f.text}</span>
              </div>
            `
              )
              .join("")}
          </div>
        `;
      } else if (step.type === "feature") {
        contentHtml = `
          <p class="onboarding-description">${step.description}</p>
          <ul class="onboarding-details">
            ${step.details
              .map(
                (d) =>
                  `<li><span class="material-symbols-rounded">check_circle</span>${d}</li>`
              )
              .join("")}
          </ul>
          <div class="onboarding-tip">
            <span class="material-symbols-rounded">lightbulb</span>
            <span>${step.tip}</span>
          </div>
        `;
      } else if (step.type === "finish") {
        contentHtml = `
          <p class="onboarding-subtitle">${step.subtitle}</p>
          <p class="onboarding-message">${step.message}</p>
        `;
      }

      modal.innerHTML = `
        <div class="onboarding-modal">
          <div class="onboarding-progress-bar">
            <div class="onboarding-progress-fill" style="width: ${progress}%"></div>
          </div>
          <div class="onboarding-step-counter">Krok ${currentStep + 1} z ${
        steps.length
      }</div>
          
          <div class="onboarding-header">
            <div class="onboarding-icon" style="background: ${step.color}">
              <span class="material-symbols-rounded">${step.icon}</span>
            </div>
            <h2>${step.title}</h2>
          </div>
          
          <div class="onboarding-content">
            ${contentHtml}
          </div>
          
          <div class="onboarding-footer-actions">
            ${
              isFirst
                ? `<button class="text-btn" id="skipBtn">Pomiń wprowadzenie</button>`
                : `<button class="text-btn" id="prevStepBtn"><span class="material-symbols-rounded">arrow_back</span> Wstecz</button>`
            }
            <button class="primary-btn" id="nextStepBtn">
              ${isLast ? "Zaczynamy!" : "Dalej"} 
              ${
                !isLast
                  ? '<span class="material-symbols-rounded">arrow_forward</span>'
                  : ""
              }
            </button>
          </div>
        </div>
      `;

      // Animate modal content
      const modalContent = modal.querySelector(".onboarding-modal");
      if (modalContent) {
        modalContent.style.animation = "none";
        modalContent.offsetHeight;
        modalContent.style.animation = "fadeInUp 0.35s ease";
      }

      // Attach event listeners
      const nextBtn = document.getElementById("nextStepBtn");
      const prevBtn = document.getElementById("prevStepBtn");
      const skipBtn = document.getElementById("skipBtn");

      if (nextBtn) {
        nextBtn.onclick = () => {
          if (isLast) {
            completeOnboarding();
          } else {
            currentStep++;
            renderStep();
          }
        };
      }

      if (prevBtn) {
        prevBtn.onclick = () => {
          if (currentStep > 0) {
            currentStep--;
            renderStep();
          }
        };
      }

      if (skipBtn) {
        skipBtn.onclick = () => completeOnboarding();
      }
    };

    document.body.appendChild(modal);
    renderStep();
    setTimeout(() => modal.classList.add("visible"), 50);
  }

  // Show alert settings modal
  showAlertSettings() {
    const existingModal = document.querySelector(".alert-settings-modal");
    if (existingModal) existingModal.remove();

    const modal = document.createElement("div");
    modal.className = "alert-settings-modal";
    modal.onclick = (e) => {
      if (e.target === modal) modal.remove();
    };

    modal.innerHTML = `
      <div class="alert-settings-content">
        <h3>
          <span class="material-symbols-rounded">notifications</span>
          Ustawienia alertów
        </h3>
        <p>Pokaż alert gdy obłożenie filmu przekracza:</p>
        <div class="alert-threshold-input">
          <input type="range" id="alertThresholdSlider" min="5" max="100" step="5" value="${this.alertThreshold}">
          <span class="alert-threshold-value" id="alertThresholdValue">${this.alertThreshold}%</span>
        </div>
        <div class="alert-settings-btns">
          <button class="cancel-btn" onclick="this.closest('.alert-settings-modal').remove()">Anuluj</button>
          <button class="save-btn" id="saveAlertThreshold">Zapisz</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    // Live update value display
    const slider = document.getElementById("alertThresholdSlider");
    const valueDisplay = document.getElementById("alertThresholdValue");
    slider.oninput = () => {
      valueDisplay.textContent = slider.value + "%";
    };

    // Save button
    document.getElementById("saveAlertThreshold").onclick = () => {
      this.alertThreshold = parseInt(slider.value);
      localStorage.setItem("alertThreshold", this.alertThreshold);
      this.showToast(`Alert ustawiony na ${this.alertThreshold}%`, "success");
      modal.remove();
      this.renderMovies(); // Re-render to apply new threshold
    };
  }

  // Calendar Modal
  toggleCalendarModal() {
    const modal = document.getElementById("calendarModal");
    if (!modal) return;

    if (modal.style.display === "none") {
      modal.style.display = "flex";
      this.calendarMonth = new Date().toISOString().slice(0, 7);
      this.renderCalendar();

      document.getElementById("prevMonth").onclick = () => {
        const d = new Date(this.calendarMonth + "-01");
        d.setMonth(d.getMonth() - 1);
        this.calendarMonth = d.toISOString().slice(0, 7);
        this.renderCalendar();
      };
      document.getElementById("nextMonth").onclick = () => {
        const d = new Date(this.calendarMonth + "-01");
        d.setMonth(d.getMonth() + 1);
        this.calendarMonth = d.toISOString().slice(0, 7);
        this.renderCalendar();
      };

      // Month picker toggle
      const titleBtn = document.getElementById("calendarTitleBtn");
      const monthPicker = document.getElementById("monthPickerDropdown");
      if (titleBtn && monthPicker) {
        titleBtn.onclick = () => {
          monthPicker.style.display =
            monthPicker.style.display === "none" ? "block" : "none";
          if (monthPicker.style.display === "block") {
            this.renderMonthPicker();
          }
        };
      }

      // Year navigation
      document.getElementById("prevYear").onclick = () => {
        this.pickerYear = (this.pickerYear || new Date().getFullYear()) - 1;
        this.renderMonthPicker();
      };
      document.getElementById("nextYear").onclick = () => {
        this.pickerYear = (this.pickerYear || new Date().getFullYear()) + 1;
        this.renderMonthPicker();
      };
    } else {
      modal.style.display = "none";
      const monthPicker = document.getElementById("monthPickerDropdown");
      if (monthPicker) monthPicker.style.display = "none";
    }
  }

  renderMonthPicker() {
    const monthGrid = document.getElementById("monthGrid");
    const yearSpan = document.getElementById("pickerYear");
    if (!monthGrid || !yearSpan) return;

    const year = this.pickerYear || parseInt(this.calendarMonth.split("-")[0]);
    this.pickerYear = year;
    yearSpan.textContent = year;

    const currentMonth = this.calendarMonth;
    const months = [
      "Sty",
      "Lut",
      "Mar",
      "Kwi",
      "Maj",
      "Cze",
      "Lip",
      "Sie",
      "Wrz",
      "Paź",
      "Lis",
      "Gru",
    ];

    monthGrid.innerHTML = months
      .map((m, i) => {
        const monthStr = `${year}-${String(i + 1).padStart(2, "0")}`;
        const isCurrent = monthStr === currentMonth;
        return `<button class="${
          isCurrent ? "current" : ""
        }" data-month="${monthStr}">${m}</button>`;
      })
      .join("");

    monthGrid.querySelectorAll("button").forEach((btn) => {
      btn.onclick = () => {
        this.calendarMonth = btn.dataset.month;
        this.renderCalendar();
        document.getElementById("monthPickerDropdown").style.display = "none";
      };
    });
  }

  async renderCalendar() {
    const grid = document.getElementById("calendarGrid");
    const title = document.getElementById("calendarTitle");
    if (!grid || !title) return;

    grid.innerHTML = '<div class="spinner"></div>';

    try {
      const res = await fetch(
        `api.php?action=calendar_data&month=${this.calendarMonth}`
      );
      const data = await res.json();
      if (!data.success) throw new Error("API error");

      const monthNames = [
        "Styczeń",
        "Luty",
        "Marzec",
        "Kwiecień",
        "Maj",
        "Czerwiec",
        "Lipiec",
        "Sierpień",
        "Wrzesień",
        "Październik",
        "Listopad",
        "Grudzień",
      ];
      const [year, month] = this.calendarMonth.split("-");
      title.textContent = `${monthNames[parseInt(month) - 1]} ${year}`;

      const firstDay = new Date(data.days[0].date);
      let offset = firstDay.getDay() - 1;
      if (offset < 0) offset = 6;

      const today = new Date().toISOString().slice(0, 10);
      let html = "";

      for (let i = 0; i < offset; i++) {
        html += '<div class="calendar-day empty"></div>';
      }

      for (const day of data.days) {
        const dayNum = parseInt(day.date.split("-")[2]);
        const isToday = day.date === today;
        let occClass = "no-data";
        let occText = "";

        if (day.hasData) {
          occClass =
            day.occupancy < 20 ? "low" : day.occupancy < 50 ? "medium" : "high";
          occText = `${Math.round(day.occupancy)}%`;
        }

        html += `
          <div class="calendar-day ${occClass} ${isToday ? "today" : ""}" 
               data-date="${day.date}" 
               data-occ="${day.hasData ? Math.round(day.occupancy) : 0}"
               data-screenings="${day.screenings || 0}"
               data-occupied="${day.occupied || 0}"
               data-total="${day.total || 0}"
               data-has-data="${day.hasData}"
               title="${day.date}: ${day.hasData ? occText : "Brak danych"}">
            <span class="day-num">${dayNum}</span>
            ${day.hasData ? `<span class="day-occ">${occText}</span>` : ""}
          </div>
        `;
      }

      grid.innerHTML = html;

      // Store selected date for preview
      this.selectedCalendarDate = null;

      grid.querySelectorAll(".calendar-day:not(.empty)").forEach((el) => {
        el.onclick = () => {
          // Remove previous selection
          grid
            .querySelectorAll(".calendar-day.selected")
            .forEach((d) => d.classList.remove("selected"));
          el.classList.add("selected");

          const date = el.dataset.date;
          const hasData = el.dataset.hasData === "true";
          this.selectedCalendarDate = date;

          // Show preview section
          const preview = document.getElementById("calendarDayPreview");
          preview.style.display = "block";

          // Update preview content
          document.getElementById("previewDate").textContent = new Date(
            date
          ).toLocaleDateString("pl-PL", {
            day: "numeric",
            month: "long",
            year: "numeric",
          });

          if (hasData) {
            document.getElementById("previewOcc").textContent =
              el.dataset.occ + "%";
            document.getElementById("previewScreenings").textContent =
              el.dataset.screenings;
            document.getElementById("previewSeats").textContent =
              el.dataset.occupied + "/" + el.dataset.total;
          } else {
            document.getElementById("previewOcc").textContent = "-";
            document.getElementById("previewScreenings").textContent = "-";
            document.getElementById("previewSeats").textContent = "-";
          }
        };
      });

      // Load History button handler
      const loadBtn = document.getElementById("loadHistoryBtn");
      if (loadBtn) {
        loadBtn.onclick = () => {
          if (this.selectedCalendarDate) {
            this.currentDate = this.selectedCalendarDate;
            this.toggleCalendarModal();
            this.loadData();
            document
              .querySelectorAll(".day-btn")
              .forEach((b) => b.classList.remove("active"));
          }
        };
      }
    } catch (e) {
      console.error("Calendar error:", e);
      grid.innerHTML =
        '<p style="text-align:center;color:var(--text-muted)">Błąd ładowania</p>';
    }
  }

  // Render Heatmap in AI Insights
  async renderHeatmap(container) {
    const heatmapDiv = document.createElement("div");
    heatmapDiv.className = "heatmap-section";
    heatmapDiv.innerHTML = `
      <h3 style="margin:0 0 12px;display:flex;align-items:center;gap:8px;">
        <span class="material-symbols-rounded" style="color:#e53935;">calendar_view_week</span>
        Popularność według dnia i godziny
      </h3>
      <div class="heatmap-container">
        <div class="heatmap-grid" id="heatmapGrid">
          <div class="spinner"></div>
        </div>
        <div class="heatmap-legend">
          <span>Niskie</span>
          <div class="heatmap-legend-bar">
            <span style="background:#c8e6c9"></span>
            <span style="background:#a5d6a7"></span>
            <span style="background:#81c784"></span>
            <span style="background:#fff59d"></span>
            <span style="background:#ffcc80"></span>
            <span style="background:#ffab91"></span>
            <span style="background:#ef9a9a"></span>
            <span style="background:#e57373"></span>
          </div>
          <span>Wysokie</span>
        </div>
      </div>
    `;
    container.appendChild(heatmapDiv);

    try {
      const res = await fetch("api.php?action=heatmap_data");
      const data = await res.json();
      if (!data.success) throw new Error("API error");

      const grid = document.getElementById("heatmapGrid");
      const maxVisitors = data.maxVisitors || 100;
      let html = '<div class="heatmap-header"></div>';

      // Hour headers (10:00 - 22:00)
      for (let h = 10; h <= 22; h++) {
        html += `<div class="heatmap-header">${h}</div>`;
      }

      // Reorder: start with Monday (index 1), end with Sunday (index 0)
      const dayOrder = [1, 2, 3, 4, 5, 6, 0];

      for (const dayIdx of dayOrder) {
        const dayData = data.heatmap[dayIdx];
        html += `<div class="heatmap-day-label">${dayData.dayName.slice(
          0,
          3
        )}</div>`;

        for (let h = 10; h <= 22; h++) {
          const visitors = dayData.hours[h] || 0;
          // Scale 0-maxVisitors to levels 0-10
          const level = Math.min(10, Math.round((visitors / maxVisitors) * 10));
          html += `<div class="heatmap-cell level-${level}" title="${dayData.dayName} ${h}:00 - ${visitors} osób"></div>`;
        }
      }

      grid.innerHTML = html;
    } catch (e) {
      console.error("Heatmap error:", e);
      document.getElementById("heatmapGrid").innerHTML = "<p>Brak danych</p>";
    }
  }

  // Show Movie Trend Modal
  async showMovieTrend(title, genres = []) {
    const existingModal = document.querySelector(".movie-trend-modal");
    if (existingModal) existingModal.remove();

    const modal = document.createElement("div");
    modal.className = "movie-trend-modal";
    modal.onclick = (e) => {
      if (e.target === modal) modal.remove();
    };

    modal.innerHTML = `
      <div class="trend-modal-content">
        <div class="trend-modal-header">
          <div class="trend-modal-title">
            <span class="material-symbols-rounded">movie</span>
            <div>
              <h3>${this.esc(title)}</h3>
              <span class="trend-modal-subtitle">Historia obłożenia</span>
            </div>
          </div>
          <button class="trend-close-btn" onclick="this.closest('.movie-trend-modal').remove()">
            <span class="material-symbols-rounded">close</span>
          </button>
        </div>
        <div class="trend-modal-body">
          <div class="trend-chart-section">
            <div class="trend-section-header">
              <span class="material-symbols-rounded">show_chart</span>
              Trend obłożenia
            </div>
            <div id="trendChartContainer" class="trend-chart-wrapper">
              <div class="spinner"></div>
            </div>
          </div>
          <div class="trend-similar-section" id="similarMoviesContainer"></div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    try {
      const trendRes = await fetch(
        "api.php?action=movie_trend&title=" + encodeURIComponent(title)
      );
      const trendData = await trendRes.json();
      const chartContainer = document.getElementById("trendChartContainer");

      if (trendData.success && trendData.trend.length > 1) {
        chartContainer.innerHTML = '<canvas id="trendChart"></canvas>';
        const ctx = document.getElementById("trendChart").getContext("2d");

        // Create gradient fill (same as AI accuracy chart)
        const gradient = ctx.createLinearGradient(0, 0, 0, 180);
        gradient.addColorStop(0, "rgba(16, 185, 129, 0.25)");
        gradient.addColorStop(0.5, "rgba(16, 185, 129, 0.08)");
        gradient.addColorStop(1, "rgba(16, 185, 129, 0.01)");

        // Create line gradient
        const lineGradient = ctx.createLinearGradient(
          0,
          0,
          ctx.canvas.width,
          0
        );
        lineGradient.addColorStop(0, "#059669");
        lineGradient.addColorStop(0.5, "#10b981");
        lineGradient.addColorStop(1, "#34d399");

        new Chart(ctx, {
          type: "line",
          data: {
            labels: trendData.trend.map((d) => {
              const date = new Date(d.date);
              return date
                .toLocaleDateString("pl-PL", {
                  day: "numeric",
                  month: "2-digit",
                })
                .replace(".", ".");
            }),
            datasets: [
              {
                label: "Obłożenie (%)",
                data: trendData.trend.map((d) => d.percent),
                borderColor: lineGradient,
                backgroundColor: gradient,
                borderWidth: 3.5,
                fill: true,
                tension: 0.45,
                pointRadius: 6,
                pointBackgroundColor: "#fff",
                pointBorderColor: "#10b981",
                pointBorderWidth: 3,
                pointHoverRadius: 9,
                pointHoverBackgroundColor: "#10b981",
                pointHoverBorderColor: "#fff",
                pointHoverBorderWidth: 3,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
              duration: 1200,
              easing: "easeOutQuart",
            },
            interaction: {
              mode: "index",
              intersect: false,
            },
            plugins: {
              legend: { display: false },
              title: {
                display: true,
                text: "Trend Obłożenia",
                font: {
                  size: 16,
                  family: "'Outfit', sans-serif",
                  weight: "700",
                },
                color: "#1f2937",
                padding: { bottom: 16, top: 8 },
              },
              tooltip: {
                enabled: false,
                external: (context) => {
                  let tooltip = document.getElementById("trend-chart-tooltip");
                  if (!tooltip) {
                    tooltip = document.createElement("div");
                    tooltip.id = "trend-chart-tooltip";
                    tooltip.className = "custom-chart-tooltip";
                    document.body.appendChild(tooltip);
                  }

                  const tooltipModel = context.tooltip;
                  if (tooltipModel.opacity === 0) {
                    tooltip.style.opacity = "0";
                    tooltip.style.pointerEvents = "none";
                    return;
                  }

                  const dataPoint = tooltipModel.dataPoints?.[0];
                  if (!dataPoint) return;

                  const val = dataPoint.parsed.y;
                  const idx = dataPoint.dataIndex;
                  // Get full date from original data
                  const fullDate =
                    trendData.trend[idx]?.date || dataPoint.label;
                  const formattedDate = fullDate
                    ? new Date(fullDate).toLocaleDateString("pl-PL", {
                        day: "numeric",
                        month: "long",
                      })
                    : dataPoint.label;

                  const icon =
                    val >= 50
                      ? "trending_up"
                      : val >= 25
                      ? "trending_flat"
                      : "trending_down";
                  const color =
                    val >= 50 ? "#10b981" : val >= 25 ? "#f59e0b" : "#ef4444";

                  tooltip.innerHTML = `
                    <div class="tooltip-header">
                      <span class="material-symbols-rounded tooltip-icon" style="color: ${color}">${icon}</span>
                      <span class="tooltip-date">${formattedDate}</span>
                    </div>
                    <div class="tooltip-body">
                      <div class="tooltip-row">
                        <span class="tooltip-label">Obłożenie</span>
                        <strong style="color: ${color}; font-size: 1.2rem;">${val}%</strong>
                      </div>
                    </div>
                  `;

                  const position = context.chart.canvas.getBoundingClientRect();
                  tooltip.style.opacity = "1";

                  // Calculate position with boundary checks
                  let left =
                    position.left + window.scrollX + tooltipModel.caretX;
                  let top =
                    position.top + window.scrollY + tooltipModel.caretY - 70;

                  // Check right boundary
                  const tooltipWidth = tooltip.offsetWidth || 150;
                  if (left + tooltipWidth > window.innerWidth - 10) {
                    left = window.innerWidth - tooltipWidth - 10;
                  }
                  // Check left boundary
                  if (left < 10) {
                    left = 10;
                  }

                  tooltip.style.left = left + "px";
                  tooltip.style.top = top + "px";
                  tooltip.style.pointerEvents = "none";
                },
              },
            },
            scales: {
              x: {
                grid: {
                  color: (context) => {
                    if (
                      context.tick &&
                      context.tick.label &&
                      context.tick.label.toString().includes("Sty")
                    ) {
                      return "rgba(31, 41, 55, 0.2)"; // Prominent for Jan
                    }
                    return "rgba(0, 0, 0, 0.05)";
                  },
                  lineWidth: (context) => {
                    if (
                      context.tick &&
                      context.tick.label &&
                      context.tick.label.toString().includes("Sty")
                    ) {
                      return 2;
                    }
                    return 1;
                  },
                },
                ticks: {
                  color: "#6b7280",
                  font: { family: "'Outfit', sans-serif", size: 11 },
                },
                border: { display: false },
              },
              y: {
                min: 0,
                max: 100,
                grid: {
                  color: "rgba(0, 0, 0, 0.05)",
                  drawBorder: false,
                },
                ticks: {
                  callback: (value) => value + "%",
                  color: "#6b7280",
                  stepSize: 25,
                  padding: 10,
                  font: { family: "'Outfit', sans-serif" },
                },
                border: { display: false },
              },
            },
          },
        });
      } else {
        chartContainer.innerHTML =
          '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-muted);"><span class="material-symbols-rounded" style="font-size:40px;opacity:0.3;">show_chart</span><p style="margin-top:8px;">Brak danych historycznych</p></div>';
      }

      const genreStr = genres.join(",");
      const simRes = await fetch(
        "api.php?action=similar_movies&title=" +
          encodeURIComponent(title) +
          "&genres=" +
          genreStr
      );
      const simData = await simRes.json();
      const simContainer = document.getElementById("similarMoviesContainer");

      if (simData.success && simData.similar.length > 0) {
        simContainer.innerHTML = `
          <h4><span class="material-symbols-rounded">compare</span>Podobne filmy</h4>
          ${simData.similar
            .map((m) => {
              const pctClass =
                m.avgPercent > 40
                  ? "high"
                  : m.avgPercent > 20
                  ? "medium"
                  : "low";
              return `
              <div class="trend-similar-item">
                <span class="trend-similar-title">${this.esc(m.title)}</span>
                <span class="trend-similar-percent ${pctClass}">${
                m.avgPercent
              }%</span>
              </div>
            `;
            })
            .join("")}
        `;
      }
    } catch (e) {
      console.error("Trend error:", e);
    }
  }

  // Toggle More Menu Dropdown
  toggleMoreMenu() {
    const dropdown = document.getElementById("moreMenuDropdown");
    if (!dropdown) return;

    const isVisible = dropdown.style.display !== "none";
    dropdown.style.display = isVisible ? "none" : "block";

    // Close on click outside
    if (!isVisible) {
      const closeHandler = (e) => {
        if (!e.target.closest(".more-menu-wrapper")) {
          dropdown.style.display = "none";
          document.removeEventListener("click", closeHandler);
        }
      };
      setTimeout(() => document.addEventListener("click", closeHandler), 10);
    }
  }

  // ==================== MORE DROPDOWN MENU ====================

  toggleMoreDropdown() {
    const dropdown = document.getElementById("moreDropdown");
    if (!dropdown) return;

    const isVisible = dropdown.style.display !== "none";
    dropdown.style.display = isVisible ? "none" : "block";

    // Close on click outside
    if (!isVisible) {
      const closeHandler = (e) => {
        if (!e.target.closest(".more-menu-wrapper")) {
          dropdown.style.display = "none";
          document.removeEventListener("click", closeHandler);
        }
      };
      setTimeout(() => document.addEventListener("click", closeHandler), 10);
    }
  }

  // ==================== MOVIE LIBRARY ====================

  showMovieLibrary() {
    // Hide all other sections
    const sectionsToHide = [
      "hallSection",
      "repertoireSection",
      "comingSoonSection",
      "aiInsightsSection",
      "timelineSection",
    ];
    sectionsToHide.forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.style.display = "none";
    });

    const histogramSection = document.querySelector(".histogram-section");
    const statsBar = document.querySelector(".stats-bar");
    const predictionBanner = document.getElementById("predictionBanner");
    if (histogramSection) histogramSection.style.display = "none";
    if (statsBar) statsBar.style.display = "none";
    if (predictionBanner) predictionBanner.style.display = "none";

    // Show movie library section (create if doesn't exist)
    let section = document.getElementById("movieLibrarySection");
    if (!section) {
      section = document.createElement("section");
      section.id = "movieLibrarySection";
      section.className = "movie-library-section";
      document.querySelector("main").appendChild(section);
    }
    section.style.display = "block";

    this.loadMovieLibrary();
  }

  async loadMovieLibrary() {
    const section = document.getElementById("movieLibrarySection");
    section.innerHTML = `
      <div class="movie-library-header">
        <h2><span class="material-symbols-rounded">video_library</span> Biblioteka Filmów</h2>
        <button class="close-library-btn" onclick="heliosApp.closeMovieLibrary()">
          <span class="material-symbols-rounded">close</span>
        </button>
      </div>
      <div class="movie-library-controls">
        <div class="library-search-wrapper">
          <span class="material-symbols-rounded">search</span>
          <input type="text" id="librarySearchInput" placeholder="Szukaj filmu..." oninput="heliosApp.filterMovieLibrary()">
        </div>
        <div class="library-filters">
          <div class="custom-picker" id="sortPicker">
            <button class="picker-trigger" onclick="heliosApp.togglePicker('sortPicker')">
              <span class="picker-label">Najpopularniejsze</span>
              <span class="material-symbols-rounded">expand_more</span>
            </button>
            <div class="picker-dropdown">
              <button class="picker-option active" data-value="viewers" onclick="heliosApp.selectSort('viewers', 'Najpopularniejsze')">
                <span class="material-symbols-rounded">trending_up</span> Najpopularniejsze
              </button>
              <button class="picker-option" data-value="occupancy" onclick="heliosApp.selectSort('occupancy', 'Najwyższe obłożenie')">
                <span class="material-symbols-rounded">percent</span> Najwyższe obłożenie
              </button>
              <button class="picker-option" data-value="recent" onclick="heliosApp.selectSort('recent', 'Ostatnio grane')">
                <span class="material-symbols-rounded">schedule</span> Ostatnio grane
              </button>
              <button class="picker-option" data-value="alpha" onclick="heliosApp.selectSort('alpha', 'Alfabetycznie')">
                <span class="material-symbols-rounded">sort_by_alpha</span> Alfabetycznie
              </button>
              <button class="picker-option" data-value="days" onclick="heliosApp.selectSort('days', 'Najdłużej grane')">
                <span class="material-symbols-rounded">history</span> Najdłużej grane
              </button>
            </div>
          </div>
          <div class="custom-picker" id="genrePicker">
            <button class="picker-trigger" onclick="heliosApp.togglePicker('genrePicker')">
              <span class="picker-label">Wszystkie gatunki</span>
              <span class="material-symbols-rounded">expand_more</span>
            </button>
            <div class="picker-dropdown" id="genrePickerOptions">
              <button class="picker-option active" data-value="" onclick="heliosApp.selectGenre('', 'Wszystkie gatunki')">
                <span class="material-symbols-rounded">category</span> Wszystkie gatunki
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="movie-library-stats">
        <span class="library-stat"><span class="material-symbols-rounded">movie</span> <span id="libraryCount">...</span> filmów</span>
        <span class="library-stat"><span class="material-symbols-rounded">groups</span> <span id="libraryTotalViewers">...</span> widzów</span>
        <button class="icon-btn" onclick="heliosApp.filterMovieLibrary()" title="Odśwież" style="margin-left: auto;">
            <span class="material-symbols-rounded">refresh</span>
        </button>
      </div>
      <div class="movie-library-grid" id="movieLibraryGrid">
        <div class="loading">
          <div class="spinner"></div>
          <p>Ładowanie biblioteki filmów...</p>
        </div>
      </div>
    `;

    try {
      const response = await fetch(
        `api.php?action=movie_library&_t=${Date.now()}`
      );
      if (!response.ok) throw new Error("Failed to fetch");
      this.movieLibraryData = await response.json();

      // Initialize selected genres set if not exists
      if (!this.selectedGenres) this.selectedGenres = new Set();

      // Populate genre filter with Search and Checkboxes
      const genres = new Set();
      this.movieLibraryData.movies.forEach((m) => {
        (m.genres || []).forEach((g) => genres.add(g));
      });

      const genreDropdown = document.getElementById("genrePickerOptions");
      // Reset content
      genreDropdown.innerHTML = `
        <div class="picker-search">
            <input type="text" placeholder="Szukaj gatunku..." onkeyup="heliosApp.searchGenres(this.value)" onclick="event.stopPropagation()">
        </div>
        <div class="picker-options-list" id="genreOptionsList">
            <button class="picker-option active" data-value="" onclick="heliosApp.clearGenreSelection()">
                <span class="picker-checkbox"><i class="material-symbols-rounded">check</i></span>
                <span class="material-symbols-rounded">category</span> Wszystkie gatunki
            </button>
        </div>
      `;

      const optionsList = document.getElementById("genreOptionsList");
      Array.from(genres)
        .sort()
        .forEach((g) => {
          const isSelected = this.selectedGenres.has(g);
          optionsList.innerHTML += `
            <button class="picker-option ${
              isSelected ? "selected" : ""
            }" data-value="${this.esc(
            g
          )}" onclick="heliosApp.toggleGenre('${this.esc(g).replace(
            /'/g,
            "\\'"
          )}', this)">
                <span class="picker-checkbox"><i class="material-symbols-rounded">check</i></span>
                <span class="material-symbols-rounded">local_movies</span> ${this.esc(
                  g
                )}
            </button>`;
        });

      // Update stats
      document.getElementById("libraryCount").textContent =
        this.movieLibraryData.count;
      const totalViewers = this.movieLibraryData.movies.reduce(
        (sum, m) => sum + m.totalViewers,
        0
      );
      document.getElementById("libraryTotalViewers").textContent =
        totalViewers.toLocaleString("pl-PL");

      this.renderMovieLibrary();
    } catch (error) {
      console.error("Movie library error:", error);
      document.getElementById("movieLibraryGrid").innerHTML = `
        <div class="empty-state">
          <span class="material-symbols-rounded">error</span>
          <h3>Błąd ładowania</h3>
          <p>Nie udało się pobrać biblioteki filmów</p>
        </div>
      `;
    }
  }

  renderMovieLibrary(movies = null) {
    const container = document.getElementById("movieLibraryGrid");
    const data = movies || this.movieLibraryData?.movies || [];

    if (data.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <span class="material-symbols-rounded">search_off</span>
          <h3>Brak wyników</h3>
          <p>Nie znaleziono filmów pasujących do kryteriów</p>
        </div>
      `;
      return;
    }

    container.innerHTML = data
      .map((movie) => this.createLibraryMovieCard(movie))
      .join("");
  }

  createLibraryMovieCard(movie) {
    const poster = movie.poster || null;
    const genres = (movie.genres || []).slice(0, 2).join(", ");
    const trendIcon =
      movie.trendDirection === "up"
        ? "trending_up"
        : movie.trendDirection === "down"
        ? "trending_down"
        : "trending_flat";
    const trendClass =
      movie.trendDirection === "up"
        ? "trend-up"
        : movie.trendDirection === "down"
        ? "trend-down"
        : "trend-stable";
    const occupancyClass =
      movie.avgOccupancy > 40
        ? "high"
        : movie.avgOccupancy > 20
        ? "medium"
        : "low";

    const posterHtml = poster
      ? `<img src="${this.esc(poster)}" alt="${this.esc(
          movie.title
        )}" loading="lazy" onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\\'poster-placeholder\\'><span class=\\'material-symbols-rounded\\'>movie</span></div>'">`
      : `<div class="poster-placeholder"><span class="material-symbols-rounded">movie</span></div>`;

    return `
      <div class="library-movie-card" onclick="heliosApp.showMovieDetail('${this.esc(
        movie.title
      ).replace(/'/g, "\\'")}')">
        <div class="library-movie-poster">
          ${posterHtml}
          <div class="library-movie-trend ${trendClass}">
            <span class="material-symbols-rounded">${trendIcon}</span>
          </div>
        </div>
        <div class="library-movie-info">
          <h3 class="library-movie-title">${this.esc(movie.title)}</h3>
          ${
            genres
              ? `<p class="library-movie-genres">${this.esc(genres)}</p>`
              : ""
          }
          <div class="library-movie-stats">
            <span class="library-stat-item">
              <span class="material-symbols-rounded">groups</span>
              ${movie.totalViewers.toLocaleString("pl-PL")}
            </span>
            <span class="library-stat-item occupancy-${occupancyClass}">
              <span class="material-symbols-rounded">percent</span>
              ${movie.avgOccupancy}%
            </span>
          </div>
          <div class="library-movie-meta">
            <span>${movie.daysInCinema} ${
      movie.daysInCinema === 1 ? "dzień" : "dni"
    }</span>
            ${movie.imdbRating ? `<span>⭐ ${movie.imdbRating}</span>` : ""}
          </div>
        </div>
      </div>
    `;
  }

  filterMovieLibrary() {
    const searchTerm =
      document.getElementById("librarySearchInput")?.value?.toLowerCase() || "";

    // Ensure selectedGenres is initialized
    if (!this.selectedGenres) this.selectedGenres = new Set();
    const hasGenres = this.selectedGenres.size > 0;

    let filtered = this.movieLibraryData?.movies || [];

    if (searchTerm) {
      filtered = filtered.filter(
        (m) =>
          m.title.toLowerCase().includes(searchTerm) ||
          (m.director || "").toLowerCase().includes(searchTerm)
      );
    }

    if (hasGenres) {
      filtered = filtered.filter((m) => {
        // Check if movie has ANY of the selected genres
        const movieGenres = m.genres || [];
        for (let g of this.selectedGenres) {
          if (movieGenres.includes(g)) return true;
        }
        return false;
      });
    }

    this.sortMovieLibrary(filtered);
  }

  sortMovieLibrary(movies = null) {
    const sortBy = this.selectedSort || "viewers";
    let data = movies || this.movieLibraryData?.movies || [];

    // Apply filter if not already filtered (helper when called directly)
    if (!movies) {
      this.filterMovieLibrary(); // Re-run full filter logic
      return;
    }

    // Sort
    switch (sortBy) {
      case "viewers":
        data.sort((a, b) => b.totalViewers - a.totalViewers);
        break;
      case "occupancy":
        data.sort((a, b) => b.avgOccupancy - a.avgOccupancy);
        break;
      case "recent":
        data.sort((a, b) => b.lastDay?.localeCompare(a.lastDay || "") || 0);
        break;
      case "alpha":
        data.sort((a, b) => a.title.localeCompare(b.title, "pl"));
        break;
      case "days":
        data.sort((a, b) => b.daysInCinema - a.daysInCinema);
        break;
    }

    this.renderMovieLibrary(data);
  }

  // Genre Picker Methods
  toggleGenre(genre, btnElement) {
    if (!this.selectedGenres) this.selectedGenres = new Set();

    if (this.selectedGenres.has(genre)) {
      this.selectedGenres.delete(genre);
      btnElement.classList.remove("selected");
    } else {
      this.selectedGenres.add(genre);
      btnElement.classList.add("selected");
    }

    // Update "All" button state
    const allBtn = document.querySelector(
      '#genreOptionsList .picker-option[data-value=""]'
    );
    if (this.selectedGenres.size > 0) {
      allBtn.classList.remove("active");
    } else {
      allBtn.classList.add("active");
    }

    // Update Label
    const label = document.querySelector("#genrePicker .picker-label");
    if (this.selectedGenres.size === 0) {
      label.textContent = "Wszystkie gatunki";
    } else if (this.selectedGenres.size === 1) {
      label.textContent = Array.from(this.selectedGenres)[0];
    } else {
      label.textContent = `${this.selectedGenres.size} wybranych`;
    }

    this.filterMovieLibrary();
  }

  clearGenreSelection() {
    this.selectedGenres = new Set();
    document
      .querySelectorAll("#genreOptionsList .picker-option")
      .forEach((btn) => btn.classList.remove("selected"));
    document
      .querySelector('#genreOptionsList .picker-option[data-value=""]')
      .classList.add("active");
    document.querySelector("#genrePicker .picker-label").textContent =
      "Wszystkie gatunki";
    this.filterMovieLibrary();
    this.togglePicker("genrePicker"); // Close picker
  }

  searchGenres(query) {
    const q = query.toLowerCase();
    document
      .querySelectorAll("#genreOptionsList .picker-option")
      .forEach((btn) => {
        const val = btn.getAttribute("data-value").toLowerCase();
        if (val === "") return; // Skip "All" button
        if (val.includes(q)) {
          btn.style.display = "flex";
        } else {
          btn.style.display = "none";
        }
      });
  }

  // Custom Picker Methods
  togglePicker(pickerId) {
    const picker = document.getElementById(pickerId);
    if (!picker) return;

    const dropdown = picker.querySelector(".picker-dropdown");
    const isOpen = picker.classList.contains("active");

    // Close all other pickers
    document.querySelectorAll(".custom-picker.active").forEach((p) => {
      p.classList.remove("active");
    });

    if (!isOpen) {
      picker.classList.add("active");

      // Close on click outside
      setTimeout(() => {
        const closeHandler = (e) => {
          if (!e.target.closest(".custom-picker")) {
            picker.classList.remove("active");
            document.removeEventListener("click", closeHandler);
          }
        };
        document.addEventListener("click", closeHandler);
      }, 10);
    }
  }

  selectSort(value, label) {
    this.selectedSort = value;

    const picker = document.getElementById("sortPicker");
    picker.querySelector(".picker-label").textContent = label;
    picker.classList.remove("open");

    // Update active state
    picker.querySelectorAll(".picker-option").forEach((opt) => {
      opt.classList.toggle("active", opt.dataset.value === value);
    });

    this.sortMovieLibrary();
  }

  selectGenre(value, label) {
    this.selectedGenre = value;

    const picker = document.getElementById("genrePicker");
    picker.querySelector(".picker-label").textContent = label;
    picker.classList.remove("open");

    // Update active state
    picker.querySelectorAll(".picker-option").forEach((opt) => {
      opt.classList.toggle("active", opt.dataset.value === value);
    });

    this.filterMovieLibrary();
  }

  closeMovieLibrary() {
    const section = document.getElementById("movieLibrarySection");
    if (section) section.style.display = "none";
    this.showSchedule();
  }

  async showMovieDetail(title) {
    // Create/show modal
    let modal = document.getElementById("movieDetailModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "movieDetailModal";
      modal.className = "movie-detail-modal";
      document.body.appendChild(modal);
    }

    modal.style.display = "flex";
    modal.innerHTML = `
      <div class="movie-detail-content">
        <button class="modal-close" onclick="document.getElementById('movieDetailModal').style.display='none'">
          <span class="material-symbols-rounded">close</span>
        </button>
        <div class="movie-detail-loading">
          <div class="spinner"></div>
          <p>Ładowanie danych filmu...</p>
        </div>
      </div>
    `;

    try {
      const response = await fetch(
        `api.php?action=movie_detail&title=${encodeURIComponent(
          title
        )}&_t=${Date.now()}`
      );
      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error || "Nie znaleziono filmu");
      }

      this.renderMovieDetail(data);
    } catch (error) {
      console.error("Movie detail error:", error);
      modal.querySelector(".movie-detail-content").innerHTML = `
        <button class="modal-close" onclick="document.getElementById('movieDetailModal').style.display='none'">
          <span class="material-symbols-rounded">close</span>
        </button>
        <div class="empty-state">
          <span class="material-symbols-rounded">error</span>
          <h3>Błąd</h3>
          <p>${error.message}</p>
        </div>
      `;
    }
  }

  renderMovieDetail(data) {
    const modal = document.getElementById("movieDetailModal");
    const movie = data.movie;
    const stats = data.stats;

    const poster =
      movie.poster || "https://via.placeholder.com/200x300?text=Brak+plakatu";
    const genres = (movie.genres || []).join(", ");

    const occupancyClass =
      stats.avgOccupancy > 40
        ? "high"
        : stats.avgOccupancy > 20
        ? "medium"
        : "low";

    modal.querySelector(".movie-detail-content").innerHTML = `
      <button class="modal-close" onclick="document.getElementById('movieDetailModal').style.display='none'">
        <span class="material-symbols-rounded">close</span>
      </button>
      
      <div class="movie-detail-header">
        <div class="movie-detail-poster">
          <img src="${this.esc(poster)}" alt="${this.esc(movie.title)}">
        </div>
        <div class="movie-detail-info">
          <h2>${this.esc(movie.title)}</h2>
          ${
            genres
              ? `<p class="movie-detail-genres"><span class="material-symbols-rounded">category</span> ${this.esc(
                  genres
                )}</p>`
              : ""
          }
          ${
            movie.director
              ? `<p class="movie-detail-director"><span class="material-symbols-rounded">movie</span> ${this.esc(
                  movie.director
                )}</p>`
              : ""
          }
          <div class="movie-detail-meta">
            ${movie.year ? `<span>${movie.year}</span>` : ""}
            ${movie.country ? `<span>${this.esc(movie.country)}</span>` : ""}
            ${movie.duration ? `<span>${movie.duration} min</span>` : ""}
            ${movie.imdbRating ? `<span>⭐ ${movie.imdbRating}</span>` : ""}
          </div>
          ${
            movie.description
              ? `<p class="movie-detail-description">${this.esc(
                  movie.description
                )}</p>`
              : ""
          }
        </div>
      </div>
      
      <div class="movie-detail-stats-grid">
        <div class="movie-stat-card">
          <span class="stat-icon"><span class="material-symbols-rounded">groups</span></span>
          <span class="stat-value">${stats.totalViewers.toLocaleString(
            "pl-PL"
          )}</span>
          <span class="stat-label">Łączna widownia</span>
        </div>
        <div class="movie-stat-card occupancy-${occupancyClass}">
          <span class="stat-icon"><span class="material-symbols-rounded">percent</span></span>
          <span class="stat-value">${stats.avgOccupancy}%</span>
          <span class="stat-label">Średnie obłożenie</span>
        </div>
        <div class="movie-stat-card">
          <span class="stat-icon"><span class="material-symbols-rounded">event_seat</span></span>
          <span class="stat-value">${stats.totalScreenings}</span>
          <span class="stat-label">Seansów</span>
        </div>
        <div class="movie-stat-card">
          <span class="stat-icon"><span class="material-symbols-rounded">calendar_month</span></span>
          <span class="stat-value">${stats.daysInCinema}</span>
          <span class="stat-label">Dni w kinie</span>
        </div>
        ${
          stats.peakHour
            ? `
        <div class="movie-stat-card">
          <span class="stat-icon"><span class="material-symbols-rounded">schedule</span></span>
          <span class="stat-value">${stats.peakHour}</span>
          <span class="stat-label">Szczytowa godzina</span>
        </div>
        `
            : ""
        }
      </div>
      
      <div class="movie-detail-charts">
        <div class="chart-section">
          <h3><span class="material-symbols-rounded">show_chart</span> Trend dzienny</h3>
          <div class="chart-container" id="movieDailyChart" style="height: 200px;"></div>
        </div>
        
        <div class="chart-section">
          <h3><span class="material-symbols-rounded">bar_chart</span> Rozkład godzinowy</h3>
          <div class="chart-container" id="movieHourlyChart" style="height: 180px;"></div>
        </div>
        
        <div class="chart-section">
          <h3><span class="material-symbols-rounded">calendar_view_week</span> Dni tygodnia</h3>
          <div class="weekday-chart" id="movieWeekdayChart"></div>
        </div>
        
        ${
          data.similarMovies.length > 0
            ? `
        <div class="chart-section">
          <h3><span class="material-symbols-rounded">compare</span> Podobne filmy (${
            genres.split(",")[0] || "ten sam gatunek"
          })</h3>
          <div class="similar-movies-list" id="movieSimilarList"></div>
        </div>
        `
            : ""
        }
      </div>
    `;

    // Render charts
    this.renderMovieDetailCharts(data);
  }

  renderMovieDetailCharts(data) {
    const isDark =
      document.documentElement.getAttribute("data-theme") === "dark";
    const gridColor = isDark ? "rgba(255,255,255,0.1)" : "rgba(0,0,0,0.1)";
    const textColor = isDark ? "#e0e0e0" : "#333333";

    // Custom tooltip styling
    const customTooltip = {
      backgroundColor: isDark ? "#1e1e1e" : "#ffffff",
      titleColor: isDark ? "#ffffff" : "#1a1f36",
      bodyColor: isDark ? "#e0e0e0" : "#333333",
      borderColor: isDark ? "#333" : "#e5e7eb",
      borderWidth: 1,
      cornerRadius: 8,
      padding: 12,
      displayColors: false,
      titleFont: { weight: "bold", size: 13 },
      bodyFont: { size: 12 },
    };

    // Daily trend chart
    const dailyContainer = document.getElementById("movieDailyChart");
    if (dailyContainer && data.dailyHistory.length > 0) {
      const canvas = document.createElement("canvas");
      canvas.style.height = "200px";
      dailyContainer.appendChild(canvas);

      new Chart(canvas.getContext("2d"), {
        type: "line",
        data: {
          labels: data.dailyHistory.map((d) => {
            const date = new Date(d.date);
            return date.toLocaleDateString("pl-PL", {
              day: "numeric",
              month: "short",
            });
          }),
          datasets: [
            {
              label: "Widzowie",
              data: data.dailyHistory.map((d) => d.occupied),
              borderColor: "#e50914",
              backgroundColor: "rgba(229, 9, 20, 0.15)",
              fill: true,
              tension: 0.4,
              pointRadius: 4,
              pointHoverRadius: 6,
              pointBackgroundColor: "#e50914",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { intersect: false, mode: "index" },
          plugins: {
            legend: { display: false },
            tooltip: {
              ...customTooltip,
              callbacks: {
                title: (items) => `📅 ${items[0].label}`,
                label: (item) =>
                  `👥 ${item.raw.toLocaleString("pl-PL")} widzów`,
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: gridColor },
              ticks: { color: textColor },
            },
            x: {
              grid: { display: false },
              ticks: { color: textColor },
            },
          },
        },
      });
    }

    // Hourly distribution chart
    const hourlyContainer = document.getElementById("movieHourlyChart");
    if (
      hourlyContainer &&
      data.hourlyDistribution &&
      Object.keys(data.hourlyDistribution).length > 0
    ) {
      const canvas = document.createElement("canvas");
      canvas.style.height = "180px";
      hourlyContainer.appendChild(canvas);

      const hours = Object.keys(data.hourlyDistribution)
        .map(Number)
        .sort((a, b) => a - b);
      const avgViewers = hours.map((h) => {
        const d = data.hourlyDistribution[h];
        return d && d.screenings > 0 ? Math.round(d.viewers / d.screenings) : 0;
      });
      const maxAvg = Math.max(...avgViewers, 1);

      new Chart(canvas.getContext("2d"), {
        type: "bar",
        data: {
          labels: hours.map((h) => `${h}:00`),
          datasets: [
            {
              label: "Śr. widzów/seans",
              data: avgViewers,
              backgroundColor: avgViewers.map((val) => {
                const intensity = val / maxAvg;
                return `rgba(229, 9, 20, ${0.4 + intensity * 0.6})`;
              }),
              borderRadius: 6,
              borderSkipped: false,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              ...customTooltip,
              callbacks: {
                title: (items) => `🕐 Godzina ${items[0].label}`,
                label: (item) => {
                  const h = hours[item.dataIndex];
                  const d = data.hourlyDistribution[h];
                  return [
                    `👥 Śr. ${item.raw} widzów/seans`,
                    `📊 ${d?.screenings || 0} seansów łącznie`,
                  ];
                },
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: { color: gridColor },
              ticks: { color: textColor },
            },
            x: {
              grid: { display: false },
              ticks: { color: textColor },
            },
          },
        },
      });
    } else if (hourlyContainer) {
      hourlyContainer.innerHTML =
        '<p style="text-align: center; color: var(--text-muted); padding: 20px;">Brak danych godzinowych</p>';
    }

    // Weekday chart (simple bars)
    const weekdayContainer = document.getElementById("movieWeekdayChart");
    if (weekdayContainer && data.weekdayAverages) {
      const dayNames = {
        monday: "Pon",
        tuesday: "Wt",
        wednesday: "Śr",
        thursday: "Czw",
        friday: "Pt",
        saturday: "Sob",
        sunday: "Nd",
      };
      const order = [
        "monday",
        "tuesday",
        "wednesday",
        "thursday",
        "friday",
        "saturday",
        "sunday",
      ];
      const maxVal = Math.max(...Object.values(data.weekdayAverages));

      weekdayContainer.innerHTML = order
        .map((day) => {
          const val = data.weekdayAverages[day] || 0;
          const pct = maxVal > 0 ? (val / maxVal) * 100 : 0;
          const isWeekend = ["friday", "saturday", "sunday"].includes(day);
          return `
          <div class="weekday-bar-item ${isWeekend ? "weekend" : ""}">
            <span class="weekday-label">${dayNames[day]}</span>
            <div class="weekday-bar-wrapper">
              <div class="weekday-bar" style="width: ${pct}%"></div>
            </div>
            <span class="weekday-value">${val}</span>
          </div>
        `;
        })
        .join("");
    }

    // Similar movies
    const simContainer = document.getElementById("movieSimilarList");
    if (simContainer && data.similarMovies.length > 0) {
      simContainer.innerHTML = data.similarMovies
        .map((m) => {
          const occupancyClass =
            m.avgOccupancy > 40
              ? "high"
              : m.avgOccupancy > 20
              ? "medium"
              : "low";
          return `
          <div class="similar-movie-item" onclick="heliosApp.showMovieDetail('${this.esc(
            m.title
          ).replace(/'/g, "\\'")}')">
            <span class="similar-movie-title">${this.esc(m.title)}</span>
            <span class="similar-movie-stats">
              <span class="similar-viewers">${m.totalViewers.toLocaleString(
                "pl-PL"
              )}</span>
              <span class="similar-occupancy ${occupancyClass}">${
            m.avgOccupancy
          }%</span>
            </span>
          </div>
        `;
        })
        .join("");
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  window.heliosApp = new HeliosApp();
});
