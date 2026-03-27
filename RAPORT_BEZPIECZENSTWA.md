# Raport Bezpieczeństwa - Sivis Drive

Przeprowadziłem audyt bezpieczeństwa dostarczonego kodu aplikacji **Sivis Drive**. Znalazłem i załatałem w kodzie kilka istotnych luk bezpieczeństwa. Architektura systemu opiera się na prostych, chociaż sprawdzonych mechanizmach (np. przygotowane zapytania PDO, weryfikacja uwierzytelnienia), ale występowały w niej podatności w nowoczesnej warstwie asynchronicznej.

## 1. Naprawione Podatności

### A. Brak Walidacji CSRF w akcjach AJAX (Cross-Site Request Forgery)
W pliku `api/ajax.php`, żądania modyfikujące stan aplikacji przesyłane metodą `POST` (takie jak wywoływanie `rename_item`, `create_folder`, `create_shared_folder`) **nie weryfikowały ochronnego tokenu CSRF**. W przeciwieństwie do standardowego wejścia przez nową stronę (`api/actions.php`), zapomniano o dodaniu kontroli sesji dla wywołań AJAX.
Skutkowało to podatnością CSRF – złośliwa strona mogła sporządzić ukryty formularz i wysłać żądanie modyfikujące stan (np. nazwę folderu czy samowolne tworzenie katalogów przypisanych innemu użytkownikowi w tle).

**Rozwiązanie:** Wdrożyłem mechanizm walidacji po stronie backendu na żądaniach AJAX. Dla każdego punktu końcowego dopisano następujący kod przed wykonaniem skryptu:
```php
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    echo json_encode(['error' => 'Błąd CSRF']);
    exit;
}
```

### B. Reflected/Stored DOM XSS w aktywności użytkowników (Logi)
Historia logów administratora opiera się w dużej mierze o dynamiczne wczytywanie danych metodą `get_logs` w `api/ajax.php`. Istniała podatność polegająca na budowaniu tabel HTML bezpośrednio poprzez wstawianie w JavaScript zmiennych przez `${l.display_name}` oraz `${l.details}` w `admin.php`.
Backend wysyłał pola użytkowników (w tym np. edytowane w ustawieniach Display Name, które nie są poprawnie sterylizowane w momencie wprowadzania) pozbawione uciekania specjalnych symboli (HTML escaped). Jeśli użytkownik nazwał się `<script>alert(1)</script>`, wyegzekwowałoby to kod JavaScript w zakładce u Administratorów w momencie wczytywania tabeli lub załadowania dodatkowych wpisów.

**Rozwiązanie:** Załatałem API logów w `api/ajax.php` dopisując w pętli obsługującej żądanie formatowania ochronę za pomocą wbudowanej funkcji `htmlspecialchars()`. Gwarantuje to, że przeglądarka wyświetli kod złośliwy w postaci tekstu, a go nie wyegzekwuje.
```php
$l['display_name'] = htmlspecialchars($l['display_name'] ?: 'System');
$l['email'] = htmlspecialchars($l['email'] ?: '-');
$l['details'] = htmlspecialchars($l['details']);
$l['action'] = htmlspecialchars($l['action']);
```

## 2. Prawidłowo Zabezpieczone Fragmenty

* **Przesyłanie plików (Upload vulnerabilities):** Pliki i ich przesyłanie jest obsłużone bezpiecznie. Nazwy plików przechodzą przez rygorystyczny `preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename(...))` wraz z prefiksem `uniqid()`. Chroni to przed `Local File Inclusion` oraz `Path Traversal` na poziomie systemu plików, tak zapobiegając nadpisaniom czy wykonaniom przez inne usługi z powodu dziwnych znaków.
* **Open Downloads (MIME Type confusion & SVG XSS):** W pliku `download.php` istnieje zabezpieczenie przed uruchamianiem niezaufanych typów plików na stronie (szczególnie plików SVG z zaszytym kodem oraz HTML). Wprowadzono listę zaufanych obrazów i dokumentów (tzw. `$viewable_types`), które otrzymują nagłówek `Content-Disposition: inline`. Każdy inny plik jest oddelegowany jako paczka i ląduje z nagłówkiem `attachment` co zmusza przeglądarkę do czystego pobrania bez procesowania, eliminując podatności typu Stored XSS i XXE w obrębie dysku.
* **SQL Injection (SQLi):** Cały główny rdzeń do manipulacji danymi w folderach posiada predefiniowane zapytania PDO w standardzie. Nie znaleziono instrukcji łączących lub interpolowanych stringów narażających na wstrzyknięcie języka zapytań SQL do środowiska deweloperskiego.

Zmiany są już zapisane w plikach i system można uznać za znacznie bezpieczniejszy.
