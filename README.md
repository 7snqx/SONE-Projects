# 🚀 S1Projects

<div align="center">

![S1Projects Logo](assets/img/S1ProjectsLogoFavicon.png)

**Portfolio i kolekcja projektów webowych**

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![CSS3](https://img.shields.io/badge/CSS3-Responsive-1572B6?style=for-the-badge&logo=css3&logoColor=white)](https://www.w3.org/Style/CSS/)

[Demo](https://soneprojects.com) • [Projekty](#-projekty) • [Instalacja](#-instalacja) • [Dokumentacja](#-dokumentacja)

</div>

---

## 📖 O Projekcie

**S1Projects** to główna platforma portfolio prezentująca kolekcję narzędzi webowych, kalkulatorów i aplikacji. Strona została zbudowana od podstaw w PHP z systemem kont użytkowników, panelem administracyjnym i dynamiczną biblioteką projektów.

### ✨ Główne Funkcje

- 🔐 **System Kont Użytkowników** - rejestracja, logowanie, weryfikacja email, reset hasła
- 📁 **Biblioteka Projektów** - dynamiczne ładowanie z bazy danych z filtrowaniem i wyszukiwaniem
- 🛠️ **Panel Administracyjny** - zarządzanie projektami, aktualizacjami i planami
- 📱 **Responsywny Design** - pełna kompatybilność z urządzeniami mobilnymi
- 🎨 **System Motywów** - jasny/ciemny motyw z CSS Variables
- 📬 **Integracja Email** - PHPMailer z SMTP dla weryfikacji i powiadomień

---

## 📁 Struktura Projektu

```
S1Projects/
├── 📄 index.php              # Strona główna
├── 📄 dbcon.php.example      # Template konfiguracji bazy danych
├── 📄 .gitignore             # Ignorowane pliki
│
├── 📁 admin/                 # Panel administracyjny
│   ├── admin.php
│   ├── style.css
│   ├── script.js
│   └── php/                  # Handlery PHP (CRUD)
│
├── 📁 pages/                 # Strony aplikacji
│   ├── account.php           # Strona konta użytkownika
│   ├── projectsLibrary.php   # Biblioteka projektów
│   ├── updates.php           # Historia aktualizacji
│   └── plans.php             # Plany rozwoju
│
├── 📁 php/                   # Backend PHP
│   ├── auth/                 # System autentykacji
│   └── badges.php            # Definicje badge'ów projektów
│
├── 📁 css/                   # Style CSS
│   ├── variables.css         # CSS Variables (kolory, typografia)
│   ├── style.css             # Główne style
│   ├── account.css           # Style strony konta
│   └── ...
│
├── 📁 js/                    # JavaScript
│   ├── script.js             # Główna logika
│   └── account.js            # Logika strony konta
│
├── 📁 assets/                # Zasoby statyczne
│   ├── img/                  # Obrazy
│   ├── font/                 # Fonty
│   ├── PHPMailer/            # Biblioteka PHPMailer
│   └── quickCSS/             # Współdzielone komponenty CSS
│
├── 📁 projects/              # Mini-projekty (podrepozytoria)
│   ├── generatorDyspozycyjnosci/
│   ├── kalkulatorArgusow/
│   ├── kalkulatorPaliwa/
│   ├── multiverse/
│   ├── plan/
│   ├── portal/
│   ├── soneque/
│   └── studyTimer/
│
└── 📁 errors/                # Strony błędów
    └── 404/
```

---

## 🔧 Instalacja

### Wymagania

- PHP 8.0+
- MySQL 8.0+
- Serwer WWW (Apache/Nginx)
- Composer (opcjonalnie)

### Krok po kroku

1. **Sklonuj repozytorium**
   ```bash
   git clone https://github.com/YOUR_USERNAME/S1Projects.git
   cd S1Projects
   ```

2. **Skonfiguruj bazę danych**
   ```bash
   cp dbcon.php.example dbcon.php
   ```
   Edytuj `dbcon.php` i wprowadź swoje dane:
   ```php
   $servername = "localhost";
   $username = "your_db_user";
   $password = "your_db_password";
   $dbname = "your_db_name";
   ```

3. **Skonfiguruj email (opcjonalnie)**
   ```bash
   cp php/auth/mail_config.php.example php/auth/mail_config.php
   ```
   Uzupełnij dane SMTP w pliku `mail_config.php`.

4. **Zaimportuj schemat bazy danych**
   ```sql
   -- Tabela projektów
   CREATE TABLE projects (
     id INT AUTO_INCREMENT PRIMARY KEY,
     title VARCHAR(255) NOT NULL,
     url VARCHAR(255) NOT NULL,
     image VARCHAR(255),
     badge VARCHAR(50),
     description TEXT,
     lastUpdate DATE,
     releaseDate DATE
   );

   -- Tabela aktualizacji
   CREATE TABLE updates (
     id INT AUTO_INCREMENT PRIMARY KEY,
     date DATE NOT NULL,
     changes TEXT NOT NULL
   );

   -- Tabela planów
   CREATE TABLE plans (
     id INT AUTO_INCREMENT PRIMARY KEY,
     title VARCHAR(255) NOT NULL,
     description TEXT,
     icon VARCHAR(50) DEFAULT 'pending_actions',
     completed TINYINT(1) DEFAULT 0,
     completion_date DATE
   );

   -- Tabela kont użytkowników
   CREATE TABLE accounts (
     id INT AUTO_INCREMENT PRIMARY KEY,
     username VARCHAR(50) UNIQUE NOT NULL,
     email VARCHAR(255) UNIQUE NOT NULL,
     password VARCHAR(255) NOT NULL,
     email_confirmed TINYINT(1) DEFAULT 0,
     bookmarked_id TEXT,
     creation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   -- Tabela logowania admina
   CREATE TABLE adminLogin (
     id INT AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(50) NOT NULL,
     password VARCHAR(255) NOT NULL
   );
   ```

5. **Uruchom serwer lokalny**
   ```bash
   php -S localhost:8000
   ```

---

## 📚 Projekty

| Projekt | Opis | Technologie |
|---------|------|-------------|
| [Generator Dyspozycyjności](projects/generatorDyspozycyjnosci/) | Narzędzie do tworzenia grafiku dostępności | HTML, CSS, JS |
| [Kalkulator Argusów](projects/kalkulatorArgusow/) | Przelicznik waluty wewnętrznej | HTML, CSS, JS |
| [Kalkulator Paliwa](projects/kalkulatorPaliwa/) | Kalkulator kosztów podróży | HTML, CSS, JS, PHP |
| [Multiverse](projects/multiverse/) | Strona edukacyjna o wieloświatach | HTML, CSS, JS |
| [Plan Lekcji](projects/plan/) | Interaktywny plan lekcji | PHP, MySQL, JS |
| [Portal Star Wars](projects/portal/) | Strona informacyjna | HTML, CSS |
| [Soneque](projects/soneque/) | Mockup sklepu online | PHP, HTML, CSS |
| [Study Timer](projects/studyTimer/) | Timer Pomodoro do nauki | HTML, CSS, JS |

---

## 🎨 System Designu

Projekt wykorzystuje spójny system designu oparty na CSS Variables:

```css
:root {
  /* Kolory główne */
  --bg-main: #F9FAFB;
  --bg-card: #FAF9F6;
  --text-main: #1A1110;
  
  /* Akcenty */
  --accent-blue: #3B82F6;
  --accent-gradient-start: rgba(60, 126, 245, 1);
  --accent-gradient-end: rgba(77, 74, 230, 1);
  
  /* Statusy */
  --accent-green: #26ecb1;
  --accent-red: #EF4444;
}
```

---

## 📝 Dokumentacja API

### Endpointy Autentykacji

| Metoda | Endpoint | Opis |
|--------|----------|------|
| POST | `/php/auth/soneLoginSystem.php` | Logowanie użytkownika |
| POST | `/php/auth/soneRegisterSystem.php` | Rejestracja użytkownika |
| POST | `/php/auth/soneLogoutSystem.php` | Wylogowanie |
| POST | `/php/auth/sonePasswordResetSystem.php` | Reset hasła |
| POST | `/php/auth/verificationCodeSender.php` | Wysłanie kodu weryfikacyjnego |
| POST | `/php/auth/verificationCodeValidator.php` | Walidacja kodu |

### Endpointy Admina

| Metoda | Endpoint | Opis |
|--------|----------|------|
| POST | `/admin/php/addProject.php` | Dodaj projekt |
| POST | `/admin/php/modifyProject.php` | Edytuj projekt |
| POST | `/admin/php/addUpdate.php` | Dodaj aktualizację |
| GET | `/admin/php/delUpdate.php?id=X` | Usuń aktualizację |
| POST | `/admin/php/addPlan.php` | Dodaj plan |
| GET | `/admin/php/modifyPlan.php?id=X&completed=1` | Zmień status planu |

---

## 🔒 Bezpieczeństwo

- ⚠️ **Nigdy nie commituj** plików `dbcon.php` ani `mail_config.php`
- Hasła są hashowane z użyciem `password_hash()` (bcrypt)
- Zapytania SQL używają prepared statements
- Sesje PHP do zarządzania autentykacją
- XSS protection przez `htmlspecialchars()`

---

## 🤝 Wkład w Projekt

1. Fork repozytorium
2. Stwórz branch (`git checkout -b feature/AmazingFeature`)
3. Commit zmiany (`git commit -m 'Add AmazingFeature'`)
4. Push do brancha (`git push origin feature/AmazingFeature`)
5. Otwórz Pull Request

---

## 📄 Licencja

Ten projekt jest udostępniony na licencji MIT. Zobacz plik [LICENSE](LICENSE) po szczegóły.

---

## 📧 Kontakt

**Szymon** - [@7snqx](https://github.com/7snqx)

Link do projektu: [https://github.com/7snqx/S1Projects](https://github.com/7snqx/S1Projects)

---

<div align="center">

Made with ❤️ by [S1Projects](https://soneprojects.com)

</div>
