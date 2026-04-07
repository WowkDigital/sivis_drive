# Dokumentacja Techniczna - Sivis Drive 📂

Sivis Drive to zaawansowany system zarządzania dokumentami, oferujący strukturę drzewiastą folderów, prywatne przestrzenie użytkowników, kosz systemowy oraz precyzyjne limity składowania.

## 🛠 Struktura Projektu (Modułowa)

Aplikacja posiada przejrzystą strukturę folderów, co ułatwia rozwój i utrzymanie kodu:

- **`index.php`**: Główny punkt wejściowy aplikacji.
- **`core/`**: Rdzeń aplikacji.
    - `auth.php`: Zarządzanie sesjami, rolami i CSRF.
    - `db.php`: Inicjalizacja bazy danych SQLite i migracje.
    - `functions.php`: Logika biznesowa, uprawnienia (`is_private_tree`), statystyki i Kosz.
- **`api/`**: Endpointy dla żądań POST i AJAX.
    - `actions.php`: Procesor akcji (Upload, Move, Trash, Restore).
    - `ajax.php`: Asynchroniczne ładowanie treści folderów.
- **`views/`**: Moduły interfejsu (HTML/JS).
    - `header.php`, `footer.php`, `sidebar.php`.
- **`data/`**: Miejsce przechowywania pliku bazy danych `database.sqlite`.
- **`uploads/`**: Fizyczne pliki na serwerze (nazwy zhashowane dla bezpieczeństwa).

- **`assets/`**: Zasoby statyczne (JS, CSS, obrazy).

---

## 🚀 Instalacja i Pierwsza Konfiguracja

System wspiera automatyczną instalację przy pierwszym uruchomieniu:

1. **Panel Instalacyjny (`install.php`)**: Jeśli baza danych nie istnieje, użytkownik jest przekierowywany do instalatora.
2. **Konto Administratora**: Podczas instalacji tworzone jest pierwsze konto z pełnymi uprawnieniami.
3. **Wymagania**: PHP 8.1+, rozszerzenie `pdo_sqlite`, uprawnienia do zapisu w folderze `data/` i `uploads/`.

---

## 🛡 System Uprawnień i Widoczności

W Sivis Drive obowiązuje hierarchia dostępu oparta na rolach:

| Rola | Widoczność | Uprawnienia Edycji |
| :--- | :--- | :--- |
| **Pracownik** | Własny folder + wybrane Udostępnione | Tylko we własnym folderze prywatnym |
| **Zarząd** | Własny folder + Udostępnione + **Foldery Pracowników** (wyłącznie) | Pełne (widzi tylko Pracowników, nie widzi innych osób z Zarządu) |
| **Admin** | Absolutnie wszystko | Pełne + Panel Administratora (`admin.php`) |

---

## ✨ Zaawansowane Funkcje

### 1. Masowy Upload Plików i Folderów
- Możliwość zaznaczenia wielu plików naraz (`multiple`).
- **Wgrywanie folderów**: Pełne wsparcie dla przesyłania całych struktur katalogów (przez przycisk "Wgraj folder" lub Drag & Drop). System inteligentnie odtwarza strukturę folderów po stronie serwera, przypisując pliki do odpowiednich podfolderów.
- **Przetwarzanie sekwencyjne**: Pliki są wgrywane jeden po drugim przez `XMLHttpRequest`. Zapewnia to stabilność przy limitach `post_max_size` i precyzyjne śledzenie postępu każdego pliku.

### 2. Kosz Systemowy (Recycle Bin)
- **Soft Delete**: Usunięcie przez użytkownika przenosi element do bazy `deleted_at`.
- **Zarządzanie przez Admina**: Administrator ma dostęp do globalnego kosza, skąd może przywracać dane (`RESTORE`) lub usuwać je bezpowrotnie.
- **Auto-Cleanup**: Wbudowany mechanizm usuwa elementy z kosza starsze niż **30 dni**.

### 3. Akcje Masowe (Batch Actions)
- Dynamiczny pasek akcji pojawiający się po zaznaczeniu elementów.
- **Pobieranie ZIP**: Szybkie pakowanie wielu plików do jednego archiwum w locie.
- **Masowe Przenoszenie**: Intuicyjne drzewo folderów w oknie modalnym (AJAX).

### 4. Optymalizacja Mobilna
- **Mobile Actions Menu**: Na urządzeniach mobilnych akcje pliku są schowane pod przyciskiem "Więcej", co zapobiega przeładowaniu UI i ułatwia obsługę dotykiem.

### 5. System Powiadomień i Alertów (Telegram)
- **Raporty Dobowe**: System co 24h generuje raport statystyczny (logowania, operacje na plikach, zajęte miejsce) i wysyła go do bota Telegram.
- **Bezpieczeństwo**: Wszystkie wiadomości wychodzące na Telegram są automatycznie filtrowane przez funkcję `tg_escape()`, co uniemożliwia wstrzykiwanie złośliwego kodu HTML (HTML Injection).
- **Monitoring w Czasie Rzeczywistym**: Każdy krytyczny błąd systemu (`SYSTEM_ERROR`), błąd podczas backupu czy nieautoryzowana próba dostępu jest natychmiastowo zgłaszana administratorowi.
- **Globalna Obsługa Błędów**: System posiada własne procedury `set_error_handler` i `set_exception_handler`, które przekazują błędy PHP bezpośrednio do bota.

### 6. System Wykrywania Zagrożeń (Simple IDS)
- **Ochrona przed Brute-Force**: Wykrywanie wielokrotnych nieudanych logowań z jednego adresu IP w krótkim czasie.
- **Monitorowanie Masowych Działań**: Automatyczny alert przy próbie masowego usuwania plików (np. przez złośliwy skrypt lub włamanie na konto).
- **Weryfikacja CSRF**: Każda próba żądania POST bez poprawnego tokenu jest blokowana i logowana jako incydent bezpieczeństwa.

---

## 📝 Logi i Audyt

Każda istotna akcja w systemie jest rejestrowana w tabeli `logs`:
- Wgrywanie, pobieranie, usuwanie, przenoszenie, zmiana nazw plików.
- Logowania i błędy autoryzacji.
- **Adres IP**: Każdy wpis w logach zawiera adres IP sprawcy/zdarzenia.
- Automatyczne czyszczenie kosza (jako użytkownik `System`).

---

## 📊 Limity i Ograniczenia

Dla każdego drzewa prywatnego użytkownika obowiązują twarde limity:

- **Liczba plików:** Maksymalnie **500**.
- **Pojemność:** Maksymalnie **500 MB**.
- **Pojedynczy plik:** Maksymalnie **100 MB** (zalecane ustawienie `upload_max_filesize` w PHP).

### Automatyzacja Backupów (CLI/CRON)
- Pełny proces kopii zapasowej można wywołać z poziomu terminala: `php core/backup_logic.php`.
- Skrypt automatycznie zarządza retencją (7 dni) i trybem konserwacji.
- Zalecane uruchamianie przez systemowy harmonogram zadań (CRONTAB/Task Scheduler).

---

## 🏗 Technologia

- **Backend:** PHP 8.x + PDO (SQLite).
- **Frontend:** Tailwind CSS, Lucide Icons.
- **UX:** AJAX (SPA-like feel), Drag & Drop, Sequential Uploads.
- **Bezpieczeństwo:** `CSRF Protection`, `password_hash` (BCRYPT), blokada bezpośredniego dostępu do `/uploads`.

---
*Dokumentacja aktualna na dzień: 07.04.2026*
*Autor: Wowk Digital / Antigraviti Condes*
