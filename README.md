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
- **System Backups**:
    - **Pełne Archiwum**: Możliwość wykonania pełnego backupu całego systemu (baza + pliki) do formatu `.zip` jednym kliknięciem.
    - **Automatyczna Retencja**: System przechowuje kopie z ostatnich **7 dni**, automatycznie usuwając starsze archiwa.
    - **Tryb Konserwacji**: Podczas trwania backupu system automatycznie przechodzi w bezpieczny tryb przerwy technicznej dla użytkowników.
    - **Automatyzacja CRON (NOWOŚĆ)**: Możliwość pełnej automatyzacji kopii zapasowych poprzez systemowy harmonogram zadań.
- **Powiadomienia Telegram & Bezpieczeństwo (NOWOŚĆ)**:
    - **Raporty Dobowe**: Automatyczny raport co 24h wysyłany na Telegram (statystyki logowań, operacji i zajętego miejsca).
    - **Aktywny IDS**: System wykrywa próby ataków Brute-Force oraz masowe usuwanie plików, natychmiastowo alarmując administratora.
    - **Logowanie Błędów**: Każdy błąd krytyczny aplikacji (np. błąd bazy czy PHP) jest natychmiastowo przesyłany do bota Telegram wraz ze szczegółami.

## 🛠️ Instalacja i Konfiguracja

### Wymagania serwerowe:
- **PHP 8.1+**
- **Rozszerzenia PHP**: `pdo_sqlite`, `zip` (wymagane do backupów), `curl` (opcjonalnie dla testów).
- **Uprawnienia**: Serwer WWW musi mieć uprawnienia do zapisu w folderach `data/` oraz `uploads/`.

### Szybka Instalacja (Produkcja):
1. Prześlij pliki na serwer.
2. Nadaj uprawnienia zapisu dla folderu `data/` oraz `uploads/`.
3. Wejdź na adres swojej domeny – system automatycznie przekieruje Cię do `install.php`.
4. Postępuj zgodnie z instrukcjami instalatora, aby utworzyć konto administratora.
5. **Ważne**: Po zakończeniu instalacji, system jest gotowy do pracy. Domyślne dane (jeśli nie zmieniono w instalatorze): `admin@admin.com` / `admin123`.

---

## ⚡ Tryb Deweloperski i Testy

System Sivis Drive posiada wbudowane narzędzia do szybkiego prototypowania i testowania integralności danych.

### 1. Uruchomienie serwera testowego (Windows)
W katalogu głównym znajduje się skrypt `run_dev.bat`, który:
- Czyści bazę danych i folder uploads (reset do stanu zero).
- Tworzy zestaw kont testowych (Admin, Zarząd, Pracownik).
- Uruchamia lokalny serwer PHP na porcie **8001**.
- Automatycznie otwiera przeglądarkę.

```bash
run_dev.bat
```

### 2. Wykonywanie testów automatycznych
System posiada dedykowany skrypt testowy `tests/permission_test.php`, który weryfikuje:
- Logikę uprawnień (kto co widzi).
- Poprawność działania kosza i automatycznego czyszczenia (GC).
- Zabezpieczenia CSRF i integralność bazy danych.

**Uruchomienie z linii komend:**
```bash
php tests/permission_test.php
```

**Uruchomienie przez przeglądarkę:**
Przejdź pod adres: `http://localhost:8001/tests/permission_test.php` (zwraca wyniki w formacie JSON).

---

## 🕒 Automatyzacja Backupów (CRON)

Aby zapewnić regularne kopie zapasowe, zaleca się dodanie zadania do systemowego harmonogramu (CRON). System Sivis Drive posiada dedykowany skrypt CLI do tego celu.

**Komenda do uruchomienia ręcznego:**
```bash
php core/backup_logic.php
```

**Przykładowy wpis w CRONTAB (codziennie o 3:00 rano):**
```bash
0 3 * * * php /sciezka/do/aplikacji/core/backup_logic.php > /dev/null 2>&1
```

---

## 🤖 Konfiguracja Bota Telegram

Powiadomienia i raporty dobowe wymagają skonfigurowania danych bota w panelu administracyjnym (**Ustawienia Systemowe**).

1. Stwórz bota przez [@BotFather](https://t.me/botfather).
2. Uzyskaj swój `Chat ID` (np. przez [@userinfobot](https://t.me/userinfobot)).
3. Wprowadź dane w panelu Sivis Drive.
4. System automatycznie zacznie wysyłać raporty oraz alarmy bezpieczeństwa.

---

## 📁 Struktura projektu (Modułowa)

- `index.php` – Główny punkt wejściowy.
- `api/` – Obsługa żądań AJAX i akcji systemowych.
- `core/` – Rdzeń systemu: autoryzacja (`auth.php`), funkcje (`functions.php`), baza danych (`db.php`).
- `views/` – Komponenty interfejsu (Header, Footer, Sidebar, Modale).
- `scripts/` – Skrypty pomocnicze (np. `setup_dev.php`).
- `tests/` – Testy jednostkowe i systemowe.
- `data/` – Katalog z bazą danych i kopiami zapasowymi (Backups).
- `uploads/` – Katalog chroniony z dokumentami.

---
**GitHub Repository:** [WowkDigital/sivis_drive](https://github.com/WowkDigital/sivis_drive)

*Created with ❤️ by [Wowk Digital](https://github.com/WowkDigital)*

