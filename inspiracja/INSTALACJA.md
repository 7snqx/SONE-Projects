# Instrukcja testowania redesignu

## Wszystkie poprawki ścieżek zostały wykonane ✅

Wszystkie pliki w folderze `inspiracja/` zostały zaktualizowane i są gotowe do testowania.

## Co zostało poprawione:

### 1. **index_new.php**
- ✅ Zmienione wszystkie `include 'dbcon.php'` na `include '../dbcon.php'`
- ✅ CSS imports używają lokalnego `variables.css` (skopiowany do folderu)
- ✅ Wszystkie obrazy używają `../assets/img/`
- ✅ Nawigacja używa `whatsNew_new.php`, `plans_new.php`, `../account.html`
- ✅ Linki do projektów: `../<url>` (np. `../projects/...`)
- ✅ Footer linki zaktualizowane

### 2. **whatsNew_new.php**
- ✅ Zmienione `include 'dbcon.php'` na `include '../dbcon.php'`
- ✅ CSS imports używają lokalnego `variables.css`
- ✅ Logo i obrazy używają `../assets/img/`
- ✅ Nawigacja do `index_new.php`
- ✅ Footer linki zaktualizowane

### 3. **plans_new.php**
- ✅ Wszystkie 4 wystąpienia `include 'dbcon.php'` zmienione na `include '../dbcon.php'`
- ✅ CSS imports używają lokalnego `variables.css`
- ✅ Obrazy planów używają `../assets/img/`
- ✅ Nawigacja do `index_new.php`
- ✅ Footer linki zaktualizowane

## Jak przetestować lokalnie:

### Metoda 1: PHP Built-in Server (zalecana)
Z głównego folderu projektu uruchom:
```powershell
php -S localhost:8000 -t .
```

Następnie w przeglądarce otwórz:
- `http://localhost:8000/inspiracja/index_new.php`
- `http://localhost:8000/inspiracja/whatsNew_new.php`
- `http://localhost:8000/inspiracja/plans_new.php`

### Metoda 2: XAMPP/WAMP
1. Skopiuj folder projektu do `htdocs/` lub `www/`
2. Otwórz `http://localhost/S1Projects/inspiracja/index_new.php`

## Oczekiwane zachowanie:

### ✅ Strona główna (index_new.php)
- Hero section z gradientem i statystykami (ilość projektów, odwiedzin)
- 6 wyróżnionych projektów w kartkach (grid)
- 4 ostatnie aktualizacje z timeline
- Wszystkie obrazy się ładują
- Nawigacja działa (przejścia między stronami)

### ✅ Aktualizacje (whatsNew_new.php)
- Timeline ze wszystkimi aktualizacjami
- Alternujące pozycje (lewo/prawo)
- Gradient line z kropkami
- Scroll-to-top button

### ✅ Plany (plans_new.php)
- Kanban board z 2 kolumnami
- "W trakcie realizacji" - plany z `ukonczone = false`
- "Ukończone" - plany z `ukonczone = true`
- Liczniki w nagłówkach kolumn
- Status badges na kartkach

## Sprawdź czy:

1. **Baza danych działa**
   - Statystyki się wyświetlają (liczba projektów, odwiedzin)
   - Projekty ładują się z bazy
   - Aktualizacje się wyświetlają
   - Plany się dzielą na ukończone/w trakcie

2. **Obrazki się ładują**
   - Logo w navbar
   - Zdjęcia projektów
   - Obrazy planów
   - Logo w footer

3. **Nawigacja działa**
   - Kliknięcie logo -> index_new.php
   - Menu navbar -> prawidłowe przekierowania
   - Footer linki -> prawidłowe strony
   - Przyciski CTA -> właściwe akcje

4. **Styl jest spójny**
   - Light mode (#f8f9fa tło)
   - Glassmorphism efekty
   - Gradienty (#667eea → #764ba2)
   - Animacje (fade-in, slide-in)
   - Hover efekty na kartkach

## Potencjalne problemy:

### Problem: "Failed to include dbcon.php"
**Rozwiązanie:** Upewnij się, że plik `dbcon.php` istnieje w głównym folderze projektu (poziom wyżej niż `inspiracja/`)

### Problem: Brak połączenia z bazą
**Rozwiązanie:** Sprawdź credentials w `dbcon.php` i upewnij się, że baza MySQL/MariaDB jest uruchomiona

### Problem: Brak obrazków
**Rozwiązanie:** 
- Sprawdź czy folder `assets/img/` istnieje w głównym folderze
- Sprawdź czy obrazy projektów/planów mają poprawne nazwy w bazie

### Problem: Strona wygląda inaczej
**Rozwiązanie:** 
- Wyczyść cache przeglądarki (Ctrl+Shift+R)
- Sprawdź czy `variables.css` się załadował (DevTools -> Network)

## Następne kroki:

Po przetestowaniu redesignu możesz:
1. **Zastąpić oryginalne pliki** - jeśli redesign Ci się podoba
2. **Dostosować kolory** - edytuj `variables.css` (CSS variables na górze)
3. **Dodać responsywność** - media queries dla mobile
4. **Integrować z istniejącym kodem** - przenieść elementy do oryginalnych plików

## Uwagi:

- **Oryginalne pliki są bezpieczne** - znajdują się w głównym folderze
- **Folder `inspiracja/` jest niezależny** - możesz testować bez ryzyka
- **Kopiuj, nie przenoś** - zachowaj backup przed wprowadzeniem zmian

---

Jeśli masz problemy, sprawdź logi PHP:
```powershell
php -S localhost:8000 -t .
# Błędy będą wyświetlane w terminalu
```

Lub włącz error reporting w PHP (dodaj na początku pliku):
```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
```
