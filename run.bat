@echo off
echo Uruchamianie serwera testowego na porcie 8000 dla Sivis Drive...
echo Po chwili aplikacja powinna otworzyc sie w przegladarce.
echo Aby zatrzymac serwer, wcisnij CTRL+C w tym oknie.

:: Otwiera przeglądarkę domyślną
start http://localhost:8000

:: Uruchamia lokalny serwer PHP (musi być zainstalowane PHP w zmiennych środowiskowych)
:: Ustawiamy limity w locie (100MB na plik, 110MB na cały post)
php -d upload_max_filesize=100M -d post_max_size=110M -S localhost:8000
