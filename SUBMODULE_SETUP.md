# Instrukcja: Jak utworzyć submoduły dla każdego projektu

Ten dokument opisuje kroki potrzebne do rozbicia projektów w `projects/` na osobne repozytoria i dodania ich jako submoduły do głównego repozytorium `S1Projects`.

1) Przygotowanie lokalne

- Otwórz PowerShell i przejdź do folderu release:

```powershell
cd "c:\Users\szcal\OneDrive\Pulpit\Programowanie\strony internetowe\S1Projects\.github\s1projects-release"
```

- (Opcjonalnie) Przejrzyj listę projektów:

```powershell
Get-ChildItem projects -Directory | Select-Object Name
```

2) Zainicjalizuj każde projektowe repo i wypchnij na GitHub

- Uruchom dostarczony skrypt `setup_submodules.ps1` i podaj swoje `YOUR_USERNAME` przy promptach.

```powershell
# Uruchom skrypt (może wymagać ustawienia ExecutionPolicy)
.
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force
.
\setup_submodules.ps1
```

- Skrypt zapyta, czy zainicjalizować lokalne repo dla każdego projektu, utworzy pierwszy commit i pomoże dodać zdalny `origin`.
- Jeśli wybierzesz dodanie remote, musisz wcześniej utworzyć na GitHub repo o wskazanej nazwie (np. `generatorDyspozycyjnosci`).

3) Dodaj submoduły do głównego repo (po wypchnięciu wszystkich projektów)

- W katalogu głównym `s1projects-release` wykonaj przykładowe polecenia:

```bash
git init
# jeśli repo już istnieje na GitHub, dodaj remote do głównego repo
git remote add origin https://github.com/YOUR_USERNAME/S1Projects.git

# Dodawanie submodułów (przykładowe polecenia):
# Wykonaj je po tym, gdy każde projektowe repo będzie dostępne na GitHub
git submodule add https://github.com/YOUR_USERNAME/generatorDyspozycyjnosci.git projects/generatorDyspozycyjnosci
git submodule add https://github.com/YOUR_USERNAME/kalkulatorArgusow.git projects/kalkulatorArgusow
# ... powtórz dla każdego projektu

# Po dodaniu submodule
git add .gitmodules
git commit -m "Add project submodules"
git push -u origin main
```

4) Klonowanie repo z submodułami

- Aby poprawnie sklonować repo zawierające submoduły, użyj:

```bash
git clone --recursive https://github.com/YOUR_USERNAME/S1Projects.git

# lub po zwykłym klonowaniu
git submodule update --init --recursive
```

5) Uwagi i bezpieczeństwo

- Nie wrzucaj plików `dbcon.php` ani `mail_config.php` z prawdziwymi credentialami. Używaj plików `*.example` jako szablonów.
- Jeżeli coś przypadkowo wypchniesz z hasłami, przerwij i skontaktuj się — pomogę usunąć historię (BFG/git filter-repo).

---

Jeśli chcesz, mogę teraz:
- Automatycznie zainicjalizować lokalne repo w każdym projekcie (nie wypycham niczego), lub
- Przygotować i wykonać pełny proces (utworzyć repo na GitHub + wypchnąć) — ale do tego potrzebuję dostęp do Twojego konta GitHub (token) lub Twojej zgody na wykonanie pewnych komend lokalnie.  

Którą opcję wybierasz?