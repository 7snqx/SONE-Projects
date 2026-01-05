<#
setup_submodules.ps1

Skrypt przygotowuje każdą podfolder projektu jako osobne lokalne repozytorium Git
i podpowiada polecenia do wypchnięcia na GitHub oraz dodania jako submodule do głównego repo.

Uwaga: skrypt NIE dodaje zdalnych remote ani nie wykonuje push automatycznie bez potwierdzenia.
#>

param(
    [string]$GitHubUser
)

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Write-Error "Git nie jest zainstalowany w systemie lub nie jest dostępny w PATH. Zainstaluj Git i spróbuj ponownie."
    exit 1
}

if (-not $GitHubUser) {
    $GitHubUser = Read-Host "Wpisz swoją nazwę użytkownika GitHub (YOUR_USERNAME)"
}

$projectsPath = Join-Path $PSScriptRoot 'projects'
$projectDirs = Get-ChildItem -Path $projectsPath -Directory | Where-Object { $_.Name -ne 'tmp' }

Write-Host "Znaleziono projekty:`n" -ForegroundColor Cyan
$projectDirs | ForEach-Object { Write-Host " - $_.Name" }

foreach ($p in $projectDirs) {
    $pPath = $p.FullName
    Write-Host "`n---`nPrzygotowuję: $($p.Name)" -ForegroundColor Yellow

    # Nie inicjalizujemy niczego automatycznie bez zgody użytkownika
    $initAnswer = Read-Host "Czy chcesz zainicjalizować lokalne repo w '$($p.Name)' i zrobić pierwszy commit? (y/n)"
    if ($initAnswer -match '^[Yy]') {
        Push-Location $pPath
        if (-not (Test-Path (Join-Path $pPath '.git'))) {
            git init | Out-Null
            git add .
            git commit -m "Initial commit - $($p.Name)" | Out-Null
            Write-Host "Lokalne repo zainicjalizowane i pierwszy commit utworzony." -ForegroundColor Green
        } else {
            Write-Host "Repozytorium już zainicjalizowane." -ForegroundColor DarkYellow
        }

        $remoteAnswer = Read-Host "Czy chcesz dodać remote i wypchnąć na GitHub teraz? (wymagane repo na GitHub) (y/n)"
        if ($remoteAnswer -match '^[Yy]') {
            $repoName = Read-Host "Nazwa zdalnego repozytorium dla projektu (np. $($p.Name).git)"
            if (-not $repoName) { $repoName = $p.Name }
            $remoteUrl = "https://github.com/$GitHubUser/$repoName.git"

            git remote add origin $remoteUrl
            Write-Host "Dodano remote: $remoteUrl" -ForegroundColor Green
            Write-Host "Wykonaj teraz: git push -u origin main (jeśli branch główny to 'main')" -ForegroundColor Cyan
            $pushAnswer = Read-Host "Czy chcesz wykonać git push teraz? (y/n)"
            if ($pushAnswer -match '^[Yy]') {
                # Użytkownik musi mieć skonfigurowane poświadczenia (token lub SSH)
                git branch -M main
                git push -u origin main
                Write-Host "Wypchnięto projekt $($p.Name) na $remoteUrl" -ForegroundColor Green
            } else {
                Write-Host "Pomiń push. Możesz zrobić to ręcznie później." -ForegroundColor Yellow
            }
        }
        Pop-Location
    } else {
        Write-Host "Pominięto inicjalizację $($p.Name)." -ForegroundColor DarkYellow
    }
}

Write-Host "`n=== GOTOWE ===`n" -ForegroundColor Magenta
Write-Host "Po wypchnięciu każdego projektu na GitHub użyj z katalogu głównego projektu (s1projects-release) poniższych poleceń, aby dodać submodule:" -ForegroundColor Cyan
foreach ($p in $projectDirs) {
    $repoName = $p.Name
    Write-Host "git submodule add https://github.com/$GitHubUser/$repoName.git projects/$repoName"
}

Write-Host "`nNastępnie: git add .gitmodules && git commit -m 'Add project submodules' && git push" -ForegroundColor Cyan
