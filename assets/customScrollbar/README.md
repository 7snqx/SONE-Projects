# Custom Scrollbar Component

Reużywalny custom scrollbar dla projektów SONE.

## Szybki start

```html
    <!-- custom scrollbar -->
    <link rel="stylesheet" href="assets/customScrollbar/customScrollbar.css"><script src="assets/customScrollbar/customScrollbar.js"></script>
```
### Lub dla plikow zagniezdzonych w folderach podrzędnych

```html
    <!-- custom scrollbar -->
    <link rel="stylesheet" href="../assets/customScrollbar/customScrollbar.css"><script defer src="../assets/customScrollbar/customScrollbar.js"></script>
```

### Dodaj HTML w `<body>`:
```html
<div class="customScrollbarTrack" id="scrollbarTrack">
    <div class="customScrollbarThumb" id="scrollbarThumb"></div>
</div>
```

## Personalizacja (CSS Variables)

Dodaj te zmienne do swojego CSS aby dostosować wygląd:

```css
:root {
    /* Rozmiary */
    --scrollbar-track-width: 6px;
    --scrollbar-track-height: 140px;
    --scrollbar-position-right: 20px;
    
    /* Kolory */
    --scrollbar-track-bg: rgba(255, 255, 255, 0.1);
    --scrollbar-thumb-bg: #3b82f6;
    --scrollbar-thumb-hover-bg: #60a5fa;
    
    /* Animacje */
    --scrollbar-hover-scale: 1.2;
}
```

## Przykład z variables.css

Jeśli używasz `variables.css` z projektu SONE:

```css
:root {
    --scrollbar-track-bg: var(--accent-gray-light);
    --scrollbar-thumb-bg: var(--accent-blue);
}
```

## API JavaScript

### Funkcje globalne:

```javascript
// Reinicjalizacja z własnymi selektorami
initCustomScrollbar('#myTrack', '#myThumb');

// Ręczna aktualizacja pozycji thumba
updateScrollbarThumb();
```

## Wymagania

- Opcjonalnie: `quickJS.js` dla funkcji `qs()` (skrypt ma wbudowany fallback)

## Kompatybilność

- Chrome, Firefox, Safari, Edge (wszystkie nowoczesne wersje)
- Ukrywa natywny scrollbar we wszystkich przeglądarkach
