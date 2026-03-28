@echo off
setlocal

echo ==================================================
echo Sivis Drive - Dev Mode (Clean Start)
echo ==================================================

:: Set defaults
set ADMIN_EMAIL=admin@admin.pl
set ADMIN_PASS=admin123

echo [Opcjonalne] Podaj dane dla konta administratora (ENTER = domyslne)
set /p USER_EMAIL="Podaj email admina [domyslnie: %ADMIN_EMAIL%]: "
if not "%USER_EMAIL%"=="" set ADMIN_EMAIL=%USER_EMAIL%

set /p USER_PASS="Podaj haslo [domyslnie: %ADMIN_PASS%]: "
if not "%USER_PASS%"=="" set ADMIN_PASS=%USER_PASS%

echo.
echo Przygotowywanie czystego srodowiska...
php scripts\setup_dev.php %ADMIN_EMAIL% %ADMIN_PASS%

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo BLAD: Nie udalo sie przygotowac srodowiska.
    echo Upewnij sie, ze PHP jest w zmiennej PATH i inne procesy (np. serwer) nie uzywaja bazy danych.
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo Uruchamianie serwera testowego na porcie 8000...
start http://localhost:8000

:: Uruchamia lokalny serwer PHP
:: Ustawiamy limity w locie (100MB na plik, 110MB na cały post)
php -d upload_max_filesize=100M -d post_max_size=110M -S localhost:8000
