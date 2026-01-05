# 📁 Folder INSPIRACJA

## 📋 Zawartość

Ten folder zawiera **redesign strony S1Projects** w nowoczesnym stylu z glassmorphism.

---

## 📄 Pliki

### Strony PHP:
- **`index_new.php`** - Nowa strona główna
- **`whatsNew_new.php`** - Nowa strona aktualizacji (timeline)
- **`plans_new.php`** - Nowa strona planów (kanban board)

### Style CSS:
- **`style_new.css`** - Style dla strony głównej
- **`whatsStyle_new.css`** - Style dla strony aktualizacji
- **`plans_new.css`** - Style dla strony planów
- **`variables.css`** - Wspólne CSS variables (design system)

### Dokumentacja:
- **`REDESIGN_README.md`** - Pełna dokumentacja redesignu
- **`LIGHT_MODE_UPDATE.md`** - Dokumentacja jasnego motywu
- **`README.md`** - Ten plik

---

## 🎨 Design

**Motyw:** Jasny (Light Mode)  
**Kolory:** Białe tło z fioletowo-różowymi gradientami  
**Efekty:** Glassmorphism, animacje, glow effects  
**Czcionki:** Rubik + Varela Round  

---

## 🚀 Jak użyć

### OPCJA 1: Zobacz w przeglądarce
Po prostu otwórz pliki `.php` w przeglądarce (wymaga PHP).

### OPCJA 2: Testuj lokalnie
```powershell
# Uruchom z folderu inspiracja
php -S localhost:8000
```
Potem otwórz: `http://localhost:8000/index_new.php`

### OPCJA 3: Zastąp stare pliki
```powershell
# UWAGA: Zrób backup przed zastąpieniem!
Copy-Item index.php index_OLD.php
Copy-Item style.css style_OLD.css

# Następnie skopiuj nowe
Copy-Item inspiracja\index_new.php ..\index.php
Copy-Item inspiracja\style_new.css ..\style.css
# itd...
```

---

## ✨ Kluczowe funkcje

### Strona główna (index_new.php):
- Hero section z statystykami
- Featured projects grid
- Timeline aktualizacji
- Floating decorations

### Aktualizacje (whatsNew_new.php):
- Timeline Pinterest-style
- Kolorowa gradient linia
- Alternujące karty (lewo-prawo)
- Scroll-to-top button

### Plany (plans_new.php):
- Kanban board (2 kolumny)
- W trakcie vs Ukończone
- Sticky headers z licznikami
- Status badges

---

## 📖 Więcej informacji

Przeczytaj:
- `REDESIGN_README.md` - pełna dokumentacja
- `LIGHT_MODE_UPDATE.md` - zmiany w jasnym motywie

---

**Status:** ✅ Gotowe do użycia  
**Wersja:** Light Mode  
**Data:** 13.11.2025
