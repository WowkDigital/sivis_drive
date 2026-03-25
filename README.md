# Sivis Drive - Inteligentny System Wymiany Dokumentów 🚀

Nowoczesny, bezpieczny i wydajny system udostępniania plików dla firm, zbudowany w **PHP 8.x** z wykorzystaniem bazy **SQLite**, **Tailwind CSS** oraz ikon **Lucide**. System został zaprojektowany z myślą o prostocie wdrożenia i wysokiej kulturze pracy z dokumentami.

## 🌟 Kluczowe Funkcje

- **Intuicyjny Interfejs Plików**:
    - **Wygodny Drag & Drop**: Przeciągnij pliki bezpośrednio do przeglądarki, aby błyskawicznie je wgrać.
    - **Nowoczesny Dark Mode**: W pełni responsywny interfejs zoptymalizowany pod urządzenia mobilne i tablety.
    - **Obsługa dużych plików**: Standardowa obsługa dokumentów do **100MB** (konfigurowalne w PHP).
- **Zautomatyzowany Panel Administratora**:
    - **Zarządzanie Użytkownikami**: Dodawanie kont z automatycznym przypisywaniem do grup Zarządu lub Pracowników.
    - **Aktywne Uprawnienia**: Możliwość błyskawicznej zmiany roli użytkownika bezpośrednio w tabeli administracyjnej (auto-zapis).
    - **Zarządzanie Folderami**: Tworzenie logicznych grup dokumentów z predefiniowanymi uprawnieniami (Zarząd i Pracownicy / Tylko Zarząd).
- **Bezpieczeństwo i Technologia**:
    - **Podpisywanie Pobierania**: Pliki są serwowane przez specjalny handler, uniemożliwiając bezpośredni dostęp do katalogu `uploads`.
    - **Bezpieczne Hasłowanie**: Używamy algorytmu `PASSWORD_BCRYPT`.
    - **Baza SQLite**: Brak konieczności konfiguracji MySQL – system działa "out of the box".

## 🛡️ Bezpieczna Aktualizacja Produkcji

System Sivis Drive zawiera dedykowany skrypt `update.sh` przeznaczony dla środowisk Linux, który zapewnia:
1. **Kopię zapasową**: Automatycznie pakuje bazę danych i pliki do archiwum `.zip` przed zmianą.
2. **Synchronizację**: Pobiera najnowsze zmiany z repozytorium GitHub.
3. **Utrzymanie ARCHIWUM**: Skrypt przechowuje backupy przez ostatnie **7 dni**, automatycznie usuwając stare paczki.
4. **Log zmian**: Informuje administratora w terminalu o treści ostatniej aktualizacji.

*Użycie:* `./update.sh` (Pamiętaj o `chmod +x update.sh` przy pierwszej instalacji).

## 🛠️ Instalacja i Konfiguracja

1. Prześlij pliki na serwer (PHP 8.1+).
2. Nadaj uprawnienia zapisu dla serwera WWW (`www-data`) do katalogu projektu.
3. System automatycznie wygeneruje bazę danych `database.sqlite` przy pierwszym uruchomieniu.
4. Przejdź proces instalacji przez `install.php` (automatyczne wykrywanie pierwszego uruchomienia).

### Dane domyślne (zmień po zalogowaniu!):
- **Admin**: `admin@admin.com`
- **Hasło**: `admin123`

## 📁 Struktura projektu

- `index.php` – Przeglądarka dokumentów (Drag & Drop UI).
- `admin.php` – Zarządzanie użytkownikami i uprawnieniami folderów.
- `update.sh` – Skrypt bezpiecznej aktualizacji produkcyjnej.
- `auth.php` – Logika autoryzacji i predefiniowane role.
- `download.php` – Bezpieczny mechanizm pobierania plików.
- `uploads/` – Katalog z wgranymi dokumentami (zabezpieczony).

---
*Created with ❤️ by WowkDigital*
