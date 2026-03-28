# Sivis Drive - Inteligentny System Wymiany Dokumentów 🚀

Nowoczesny, bezpieczny i wydajny system udostępniania plików dla firm, zbudowany w **PHP 8.x** z wykorzystaniem bazy **SQLite**, **Tailwind CSS** oraz ikon **Lucide**. System został zaprojektowany z myślą o prostocie wdrożenia i wysokiej kulturze pracy z dokumentami.

## 🌟 Kluczowe Funkcje

Pełna dokumentacja techniczna dostępna w pliku: [DOKUMENTACJA.md](DOKUMENTACJA.md)

- **Hierarchia i Prywatność**: 
    - **Podfoldery**: Tworzenie wielopoziomowych struktur w każdym folderze.
    - **Prywatne Przestrzenie**: Każdy pracownik ma swój folder z limitem **500 plików / 500MB**.
    - **Wgląd Zarządu**: Członkowie Zarządu widzą i mogą zarządzać folderami wszystkich pracowników.
- **Intuicyjny Interfejs Plików**:
    - **Wygodny Drag & Drop**: Przeciągnij pliki bezpośrednio do przeglądarki, aby błyskawicznie je wgrać.
    - **Podgląd w Aplikacji (NOWOŚĆ)**: Błyskawiczny podgląd plików PDF oraz zdjęć (JPG, PNG, WEBP) bezpośrednio w aplikacji, w nowoczesnym oknie pełnoekranowym.
    - **Nowoczesny Dark Mode**: W pełni responsywny interfejs zoptymalizowany pod urządzenia mobilne i tablety.
    - **Obsługa dużych plików**: Standardowa obsługa dokumentów do **100MB** (konfigurowalne w PHP).
- **Zautomatyzowany Panel Administratora**:
    - **Konfiguracja Systemu (NOWOŚĆ)**: Globalny panel ustawień pozwalający na personalizację funkcji systemu (np. włączanie/wyłączanie podglądu w aplikacji).
    - **Zarządzanie Użytkownikami**: Dodawanie kont z automatycznym przypisywaniem do grup Zarządu lub Pracowników.
    - **Aktywne Uprawnienia**: Możliwość błyskawicznej zmiany roli użytkownika bezpośrednio w tabeli administracyjnej (auto-zapis).
    - **Zarządzanie Folderami**: Tworzenie logicznych grup dokumentów z predefiniowanymi uprawnieniami.
- **Bezpieczeństwo i Technologia**:
    - **NanoID Identifiers**: Zabezpieczenie przed atakami IDOR poprzez unikalne, nieodgadnione identyfikatory dla wszystkich zasobów.
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

## 📁 Struktura projektu (Modułowa)

- `index.php` – Główny punkt wejściowy.
- `api/` – Obsługa żądań AJAX i akcji systemowych.
- `core/` – Rdzeń systemu: autoryzacja (`auth.php`), funkcje pomocnicze (`functions.php`), baza danych (`db.php`).
- `views/` – Komponenty interfejsu (Header, Footer, Sidebar, Modale).
- `admin.php` – Panel sterowania statystykami i ustawieniami.
- `download.php` – Bezpieczny handler serwowania i podglądu plików.
- `data/` – Katalog z bazą danych i kopiami zapasowymi (Backups).
- `uploads/` – Katalog chroniony z dokumentami.

---
*Created with ❤️ by Wowk Digital*
