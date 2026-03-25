#!/bin/bash
# ==============================================================================
# Skrypt aktualizacji Sivis Drive (Produkcja)
# Synchronizacja z GitHub, kopie zapasowe, utrzymywanie archiwum do 7 dni.
# ==============================================================================

# Ustawienia
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$(dirname "$APP_DIR")/sivis_drive_backups" # Kopie będą wyżej niż folder aplikacji
GIT_BRANCH="main"                       # Gałąź na GitHubie do synchronizacji
DB_FILE="database.sqlite"               # Nazwa bazy SQLite

# Data i czas aktualizacji (formatowany do nazw plików)
DATE_STAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/backup_${DATE_STAMP}.zip"

# Kolory do wypisywania statusów w terminalu
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${YELLOW}Rozpoczęto proces aktualizacji produkcji: ${DATE_STAMP}${NC}"

# ==============================================================================
# 0. Sprawdzanie wymagań
# ==============================================================================

if [ ! -d "$APP_DIR" ]; then
    echo -e "${RED}[BEZPIECZEŃSTWO] Katalog aplikacji $APP_DIR nie istnieje. Przerwanie.${NC}"
    exit 1
fi

if [ ! -d "$APP_DIR/.git" ]; then
    echo -e "${RED}[BEZPIECZEŃSTWO] Katalog aplikacji to nie jest repozytorium git (brak .git). Przerwanie.${NC}"
    exit 1
fi

if ! command -v zip &> /dev/null; then
    echo -e "${RED}[WYMAGANIA] Narzędzie 'zip' nie jest zainstalowane. Zainstaluj je poleceniem 'apt install zip'. Przerwanie.${NC}"
    exit 1
fi

# ==============================================================================
# 1. Tworzenie folderu na kopie zapasowe (jeśli nie istnieje)
# ==============================================================================

mkdir -p "$BACKUP_DIR"

# ==============================================================================
# 2. Tworzenie Kopii Zapasowej (.zip) dla plików i bazy danych przed aktualizacją
# ==============================================================================

echo -e "Tworzenie kopii zabezpieczającej w katalogu: ${BACKUP_FILE} ..."
cd "$APP_DIR" || exit 1

# Pomiń foldery takie jak '.git' aby zmniejszyć rozmiar wielkich backupów
zip -r "$BACKUP_FILE" . -x "*.git*" > /dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}[OK] Kopia zapasowa wygenerowana pomyślnie.${NC}"
else
    echo -e "${RED}[ZAGROŻENIE] Wystąpił błąd podaczas generowania kopii zip! Aktualizacja przerwana.${NC}"
    exit 1
fi

# ==============================================================================
# 3. Aktualizacja systemu (Synchronizacja Repozytorium GitHub)
# ==============================================================================

echo -e "Pobieranie aktualizacji z repozytorium Git (gałąź: ${GIT_BRANCH}) ..."

# Usunięcie niedokonczonych zmian z serwera, reset do głownego brancha
git fetch origin
git reset --hard "origin/${GIT_BRANCH}"

# Zaktualizuj pliki dla danego brancha
git pull origin "$GIT_BRANCH"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}[OK] Synchronizacja repozytorium powiodła się.${NC}"
else
    echo -e "${RED}[BŁĄD KRYTYCZNY] Wystąpił błąd synchronizacji Git! Opcjonalnie wczytaj kopię ${BACKUP_FILE}${NC}"
    exit 1
fi

# ==============================================================================
# 4. Nadanie uprawnień systemowych (Opcjonalnie: Chown / Chmod na produkcję)
# ==============================================================================
echo -e "Weryfikacja praw dostępu (np. katalog /uploads i db)..."

# Poniżej znajduje się bezpieczny standard. Jeśli www-data to właściciel.
# chown -R www-data:www-data "$APP_DIR"
# chmod -R 755 "$APP_DIR"

if [ -f "$APP_DIR/$DB_FILE" ]; then
    chmod 664 "$APP_DIR/$DB_FILE"
    echo -e "${GREEN}[OK] Zaktualizowano uprawnienia bazy danych.${NC}"
fi

if [ -d "$APP_DIR/uploads" ]; then
    chmod -R 775 "$APP_DIR/uploads"
    echo -e "${GREEN}[OK] Zaktualizowano uprawnienia katalogu uploads.${NC}"
fi

# ==============================================================================
# 5. Oczyszczanie przestrzeni serwera przed przedawnieniem.
# ==============================================================================
echo -e "Oczyszczanie przedawnionych plików kopii zapasowej starszych niż 7 dni..."

# Mtime +7 (Oznacza pliki zmodyfikowane wcześniej niż 7 dni temu).
find "$BACKUP_DIR" -type f -name "backup_*.zip" -mtime +7 -exec rm {} \;

echo -e "${GREEN}[OK] Wyczyszczono stare zrzuty bazy oraz pliki.${NC}"

# ==============================================================================
echo -e "${GREEN}====================================================${NC}"
echo -e "${GREEN}Aktualizacja i pełna polityka zrzutu wykonana poprawnie.${NC}"
echo -e "${GREEN}====================================================${NC}"
