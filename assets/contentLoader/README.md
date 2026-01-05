# Content Loader Component

Reużywalny loader z efektem "lava lamp" pasujący do stylu SONE Projects.

## Dostępne warianty

| Plik | Opis |
|------|------|
| `contentLoader.php` | Pełny loader (logo + blob + tekst "ŁADOWANIE") |
| `contentLoaderMinimal.php` | Minimalny loader (tylko blob) |

## Użycie

### Podstawowe użycie z wyborem motywu

```php
<?php $loaderTheme = 'dark'; include 'assets/contentLoader/contentLoader.php'; ?>
```

```php
<?php $loaderTheme = 'light'; include 'assets/contentLoader/contentLoader.php'; ?>
```

### Motywy

| Motyw | Użycie | Opis |
|-------|--------|------|
| `dark` | Ciemne tło strony | Biały tekst, inwertowane logo |
| `light` | Jasne tło strony | Ciemny tekst, normalne logo |

**Domyślny motyw:** `light`

### Minimalny loader (tylko blob)

```php
<?php $loaderTheme = 'dark'; include 'assets/contentLoader/contentLoaderMinimal.php'; ?>
```

### Custom klasa (opcjonalne)

Możesz dodać własną klasę CSS do loadera:

```php
<?php 
$loaderTheme = 'dark'; 
$loaderClass = 'myCustomLoader'; 
include 'assets/contentLoader/contentLoader.php'; 
?>
```

Wynik: `<div class="loaderOverlay dark myCustomLoader" ...>`

Przydatne gdy używasz loadera w kilku miejscach z różnymi stylami.

## Sterowanie loaderem

```javascript
// Ukryj loader
document.getElementById('pageLoader').classList.add('hidden');

// Pokaż loader
document.getElementById('pageLoader').classList.remove('hidden');
```

## Właściwości

- **Click-through** - loader nie blokuje interakcji z elementami pod spodem (`pointer-events: none`)
- **Transparentne tło** - brak tła, dopasowuje się do strony
- **Animacja lava lamp** - 4 bloby z efektem metaball (SVG goo filter)
- **Tekst typewriter** - animacja pisania "ŁADOWANIE"
- **Płynne przejścia** - animacja ukrywania/pokazywania

## Customizacja

Loader używa CSS variable:
- `--accent-blue` - kolor blobów (domyślnie: `#3C7EF5`)

## Struktura HTML

```
#pageLoader.loaderOverlay.[dark|light]
├── img (logo S1)
├── .loader (kontener blobów z goo filter)
│   └── span × 4 (bloby)
└── p.loaderText (tekst "ŁADOWANIE")
```

Możesz nadpisać zmienne lokalnie:
```css
.loaderOverlay {
    --accent-blue: #ff6b6b;
}
```

## Warianty

| Klasa | Opis |
|-------|------|
| `.loaderOverlay` | Pełnoekranowy overlay z loaderem |
| `.loader` | Główny spinner (80x80px) |
| `.loaderSmall` | Mały spinner inline (24x24px) |
| `.loaderButton` | Kontener dla loadera w przycisku |
