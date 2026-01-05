# 📚 Plan Lekcji

<div align="center">

**Interaktywny plan lekcji z bazą danych**

[![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)](#)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)](#)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)](#)

</div>

---

## 📖 Opis

Interaktywny plan lekcji pobierający dane z bazy MySQL. Wyświetla aktualną lekcję, czas do jej końca oraz następną lekcję. Pokazuje pełną tabelę wszystkich lekcji danego dnia.

## ✨ Funkcje

- 📅 **Dzienny plan** - automatyczne wykrywanie dnia tygodnia
- ⏰ **Aktualna lekcja** - podświetlenie bieżącej lekcji
- ⏱️ **Odliczanie** - czas do końca aktualnej lekcji
- 📍 **Następna lekcja** - informacja o nadchodzącej lekcji
- 🏫 **Sale lekcyjne** - numery sal przy każdym przedmiocie

## ⚙️ Wymagania

- PHP 7.4+
- MySQL 5.7+
- Konfiguracja `dbcon.php` w katalogu nadrzędnym

## 🗃️ Struktura bazy danych

```sql
CREATE TABLE plan (
  id INT PRIMARY KEY,
  dzien VARCHAR(20),
  godzina_od TIME,
  godzina_do TIME,
  przedmiot VARCHAR(100),
  sala VARCHAR(20)
);
```

## 📁 Struktura plików

```
plan/
├── index.php           # Główny plik
├── script.js           # Logika odliczania
└── style.css           # Style
```
