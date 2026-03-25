# Dokumentacja Techniczna - Sivis Drive 📂

Sivis Drive to zaawansowany system zarzadzania dokumentami, oferujący strukturę drzewiastą folderów, prywatne przestrzenie użytkowników oraz precyzyjne limity składowania.

## 🛠 Struktura Projektu (Modułowa)

Aplikacja została zrefaktoryzowana do modelu modułowego (`inc/`), co ułatwia rozwój i utrzymanie kodu:

- **`index.php`**: Główny punkt wejściowy aplikacji. Odpowiada za inicjalizację danych i montowanie interfejsu z modułów.
- **`inc/functions.php`**: Logika biznesowa. Zawiera funkcje rekurencyjne do sprawdzania uprawnień w drzewie folderów (`is_private_tree`) oraz obliczania zajętości miejsca (`get_private_usage`).
- **`inc/actions.php`**: Obsługa protokołu HTTP POST. Zarządza wgrywaniem plików (Upload), tworzeniem podfolderów, usuwaniem oraz przenoszeniem dokumentów.
- **`inc/ajax.php`**: API wewnętrzne dla frontendu. Obsługuje asynchroniczne ładowanie zawartości folderów bez odświeżania strony.
- **`inc/header.php`**: Definicje `<head>`, style Tailwind CSS oraz nawigacja górna (Navbar).
- **`inc/sidebar.php`**: Panel boczny z podziałem na sekcje folderów i statystyki limitów.
- **`inc/footer.php`**: Stopka oraz kompletna logika JavaScript (Lucide Icons, AJAX, Drag & Drop).

---

## 🛡 System Uprawnień i Widoczności

W Sivis Drive obowiązuje hierarchia dostępu oparta na rolach:

| Rola | Widoczność | Uprawnienia Edycji |
| :--- | :--- | :--- |
| **Pracownik** | Własny folder + wybrane Udostępnione | Tylko we własnym folderze prywatnym |
| **Zarząd** | Własny folder + Udostępnione + **Foldery Pracowników** | Pełne (wszystkie widoczne pliki i foldery) |
| **Admin** | Absolutnie wszystko | Pełne + Panel Administratora (`admin.php`) |

### Specyfika Folderów Prywatnych
Przy pierwszym logowaniu każdego użytkownika, system automatycznie tworzy dla niego główny folder prywatny. 
- Folder ten jest widoczny tylko dla **Właściciela** oraz osób z rolą **Zarząd** i **Admin**.
- Właściciel może wewnątrz niego tworzyć dowolną liczbę podfolderów.

---

## 📊 Limity i Ograniczenia

Dla każdego drzewa prywatnego użytkownika (wszystkie jego pliki we wszystkich podfolderach) obowiązują twarde limity:

- **Liczba plików:** Maksymalnie **500**.
- **Pojemność:** Maksymalnie **500 MB**.
- **Pojedynczy plik:** Maksymalnie **100 MB**.

System weryfikuje limity przed każdym zapisem (zarówno po stronie interfejsu - paski postępu, jak i po stronie serwera - blokada uploadu).

---

## 🏗 Technologia

- **Backend:** PHP 8.x + PDO (SQLite).
- **Frontend:** Tailwind CSS (Modern UI), Lucide Icons (Ikony wektorowe).
- **UX:** AJAX (Dynamiczne ładowanie treści), Drag & Drop (Wgrywanie plików).
- **Bezpieczeństwo:** `password_hash` (BCRYPT), brak bezpośredniego dostępu do `/uploads` (wszystkie pliki serwowane przez `download.php` po weryfikacji sesji).

---
*Dokumentacja aktualna na dzień: 25.03.2026*
*Autor: WowkDigital / Antigraviti Condes*
