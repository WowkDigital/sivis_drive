# Sivis Drive - Inteligentny System Wymiany Dokumentów 🚀

Nowoczesny, bezpieczny i wydajny system udostępniania plików dla firm, zbudowany w **PHP 8.x** z wykorzystaniem bazy **SQLite**, **Tailwind CSS** oraz ikon **Lucide**. System został zaprojektowany z myślą o prostocie wdrożenia i wysokiej kulturze pracy z dokumentami.

## 🌟 Kluczowe Funkcje

Pełna dokumentacja techniczna dostępna w pliku: [DOKUMENTACJA.md](DOKUMENTACJA.md)

- **Hierarchia i Prywatność**: 
    - **Podfoldery**: Tworzenie wielopoziomowych struktur w każdym folderze.
    - **Prywatne Przestrzenie**: Każdy pracownik ma swój folder z limitem **500 plików / 500MB**.
    - **Wgląd Zarządu**: Członkowie Zarządu widzą i mogą zarządzać folderami Pracowników (ale nie widzą folderów innych członków Zarządu ani Administratora).
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
- **System Backups (NOWOŚĆ)**:
    - **Pełne Archiwum**: Możliwość wykonania pełnego backupu całego systemu (baza + pliki) do formatu `.zip` jednym kliknięciem.
    - **Automatyczna Retencja**: System przechowuje kopie z ostatnich **7 dni**, automatycznie usuwając starsze archiwa.
    - **Tryb Konserwacji**: Podczas trwania backupu system automatycznie przechodzi w bezpieczny tryb przerwy technicznej dla użytkowników.
- **Bezpieczeństwo i Technologia**:
    - **Obsługa błędów (NOWOŚĆ)**: Inteligentne wykrywanie brakujących rozszerzeń serwera (np. ZIP) zapobiegające awariom systemu.
    - **Manualny Reset Blokad**: Przycisk „Wyłącz wymuszenie” w panelu administratora pozwalający na ręczne wyprowadzenie systemu z trybu konserwacji w sytuacjach awaryjnych.

## 🛠️ Instalacja i Konfiguracja

1. Prześlij pliki na serwer (PHP 8.1+).
2. **Wymagane rozszerzenia**: Upewnij się, że w PHP włączone jest rozszerzenie **ZIP** (`extension=zip`) dla poprawnego działania kopii zapasowych.
3. Nadaj uprawnienia zapisu dla serwera WWW (`www-data`) do katalogu projektu oraz podfolderu `data/`.
4. System automatycznie wygeneruje bazę danych `database.sqlite` przy pierwszym uruchomieniu.
5. Przejdź proces instalacji przez `install.php` (automatyczne wykrywanie pierwszego uruchomienia).

### Dane domyślne (zmień po zalogowaniu!):
- **Admin**: `admin@admin.com`
- **Hasło**: `admin123`

## 📁 Struktura projektu (Modułowa)

- `index.php` – Główny punkt wejściowy.
- `api/` – Obsługa żądań AJAX i akcji systemowych.
- `core/` – Rdzeń systemu: autoryzacja (`auth.php`), funkcje pomocnicze (`functions.php`), baza danych (`db.php`), logika backupu (`backup_logic.php`).
- `views/` – Komponenty interfejsu (Header, Footer, Sidebar, Modale, Widoki admina).
- `admin.php` – Panel sterowania statystykami i ustawieniami.
- `download.php` – Bezpieczny handler serwowania i podglądu plików.
- `data/` – Katalog z bazą danych i kopiami zapasowymi (Backups).
- `uploads/` – Katalog chroniony z dokumentami.

---
*Created with ❤️ by Wowk Digital*

