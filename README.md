# Sivis Drive - Minimalistyczny System Wymiany Plików

Prosty, bezpieczny i wydajny system udostępniania plików dla firm, zbudowany w PHP 8 z użyciem bazy SQLite i Tailwind CSS.

## 🚀 Funkcje
- **Trzy poziomy uprawnień**:
    - `Admin`: Pełne zarządzanie plikami i użytkownikami (dodawanie/usuwanie).
    - `Zarząd`: Zarządzanie plikami (wgrywanie, usuwanie, pobieranie).
    - `Pracownik`: Tylko wgląd i pobieranie plików.
- **Zasady dostępu**: Grupowanie plików (`zarząd`, `pracownicy`) zapewnia, że wrażliwe dokumenty trafiają tylko do uprawnionych osób.
- **Obsługa dużych plików**: System przygotowany pod wgrane pliki do **100MB**.
- **Nowoczesny design**: Responsywny interfejs zbudowany na Tailwind CSS z ikonami Lucide.
- **Bezpieczeństwo**: Hasła są hashowane (`password_hash`), a bezpośredni dostęp do folderu plików jest zablokowany.

## 🛠️ Instalacja
1. Skopiuj pliki na serwer obsługujący PHP 8.
2. Upewnij się, że serwer ma uprawnienia do zapisu w folderze projektu (potrzebne do utworzenia bazy SQLite i folderu `uploads`).
3. Otwórz stronę w przeglądarce.

### Dane domyślne (zmień po pierwszym zalogowaniu!):
- **Login**: `admin@admin.com`
- **Hasło**: `admin123`

## 💻 Testowanie lokalne (Windows)
W celu przetestowania systemu lokalnie, jeśli masz zainstalowane PHP w zmiennych środowiskowych, po prostu uruchom plik:
`run.bat`

Skrypt ten automatycznie skonfiguruje limity uploadu i otworzy adres `http://localhost:8000`.

## 📁 Struktura projektu
- `/uploads` – folder na wgrane pliki (ignorowany przez git).
- `database.sqlite` – plik bazy danych (generowany automatycznie).
- `db.php` – konfiguracja bazy danych.
- `auth.php` – system logowania i uprawnień.
- `index.php` – główna przeglądarka plików.
- `admin.php` – panel administracyjny.
- `download.php` – bezpieczny menedżer pobierania.

---
*Projekt stworzony jako lekki system do wewnętrznego użytku w firmie.*
