# ⛽ Kalkulator Spalania Paliwa

<div align="center">

**Zaawansowany kalkulator kosztów podróży samochodem**

[![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white)](#)
[![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white)](#)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)](#)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)](#)

</div>

---

## 📖 Opis

Zaawansowany kalkulator kosztów podróży samochodem z automatycznym pobieraniem aktualnych cen paliw z e-petrol.pl. Uwzględnia spalanie, dystans, liczbę pasażerów i ich wagę do korekty rzeczywistego spalania.

## ✨ Funkcje

- ⛽ **Aktualne ceny paliw** - automatyczne pobieranie z e-petrol.pl (PHP scraper)
- 🚗 **Kalkulacja spalania** - średnie spalanie na 100km
- 📍 **Dystans** - podaj trasę w kilometrach
- 👥 **Pasażerowie** - uwzględnij liczbę osób i ich wagę
- ⚖️ **Korekta spalania** - automatyczna korekta bazująca na obciążeniu
- 📊 **Szczegółowe podsumowanie** - zużycie paliwa, koszt całkowity, koszt na osobę

## 🚀 Użycie

1. Wybierz rodzaj paliwa (benzyna/diesel/LPG)
2. Wprowadź średnie spalanie pojazdu
3. Podaj dystans podróży
4. Opcjonalnie dodaj pasażerów i ich wagę
5. Kliknij "Oblicz" - otrzymasz szczegółowe podsumowanie

## 🛠️ Technologie

- **HTML5** - struktura interfejsu
- **CSS3** - responsywne stylowanie
- **Vanilla JavaScript** - logika kalkulatora
- **PHP** - scraper cen paliw (cURL, DOMDocument, XPath)
- **Google Material Symbols** - ikony

## 📁 Struktura

```
kalkulatorPaliwa/
├── index.html          # Interfejs użytkownika
├── script.js           # Logika kalkulatora
├── scraper.php         # Scraper cen z e-petrol.pl
├── style.css           # Style desktop
└── styleMobile.css     # Style mobilne
```

## ⚠️ Uwaga

Scraper wymaga działającego serwera PHP z włączonym rozszerzeniem cURL.
