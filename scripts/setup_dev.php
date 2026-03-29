<?php
/**
 * Sivis Drive - Dev Setup Script
 * Resets the database and creates a default admin user.
 */

// If we are running from CLI, get arguments
$email = $argv[1] ?? 'admin@sivis.pl';
$password = $argv[2] ?? 'admin123';

echo "\n--------------------------------------------------\n";
echo "Sivis Drive - Resetowanie środowiska deweloperskiego\n";
echo "--------------------------------------------------\n";

$base_dir = dirname(__DIR__);
$data_dir = $base_dir . '/data';
$db_file = $data_dir . '/database.sqlite';
$uploads_dir = $base_dir . '/uploads';

// 1. Delete database
if (file_exists($db_file)) {
    echo "[1/4] Usuwanie starej bazy danych...\n";
    // We close connection if any (not needed here as we haven't opened it yet)
    if (!unlink($db_file)) {
        echo "BŁĄD: Nie można usunąć pliku bazy danych. Upewnij się, że serwer jest wyłączony.\n";
        exit(1);
    }
} else {
    echo "[1/4] Baza danych nie istnieje, pomijam usuwanie.\n";
}

// 2. Clean uploads
echo "[2/4] Czyszczenie folderu uploads...\n";
if (is_dir($uploads_dir)) {
    $files = glob($uploads_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
} else {
    mkdir($uploads_dir, 0777, true);
}

// 3. Initialize DB
// We set a flag to let db.php know we are a setup script if needed
$is_setup_script = true;
echo "[3/4] Inicjalizacja tabel...\n";
try {
    require_once $base_dir . '/core/db.php';
    require_once $base_dir . '/core/functions.php';
} catch (Exception $e) {
    echo "BŁĄD podczas inicjalizacji DB: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Create example users
echo "[4/4] Tworzenie kont testowych...\n";
$users_to_create = [
    [
        'email' => $email,
        'password' => $password,
        'role' => 'admin',
        'group' => 'Zarząd',
        'name' => 'Główny Administrator'
    ],
    [
        'email' => 'admin2@sivis.pl',
        'password' => 'admin789',
        'role' => 'admin',
        'group' => 'Zarząd',
        'name' => 'Administrator Pomocniczy'
    ],
    [
        'email' => 'zarzad@sivis.pl',
        'password' => 'zarzad123',
        'role' => 'zarząd',
        'group' => 'Zarząd',
        'name' => 'Kierownik Projektu'
    ],
    [
        'email' => 'pracownik@sivis.pl',
        'password' => 'pracownik123',
        'role' => 'pracownik',
        'group' => 'Pracownicy',
        'name' => 'Jan Kowalski'
    ]
];

$login_info = "==================================================\n";
$login_info .= "DANE LOGOWANIA DLA ŚRODOWISKA TESTOWEGO\n";
$login_info .= "==================================================\n\n";

try {
    foreach ($users_to_create as $u) {
        $hash = password_hash($u['password'], PASSWORD_BCRYPT);
        $public_id = generate_nanoid();
        
        $stmt = $db->prepare("INSERT INTO users (public_id, email, password_hash, role, user_group, display_name) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$public_id, $u['email'], $hash, $u['role'], $u['group'], $u['name']]);
        $user_id = $db->lastInsertId();
        
        // Create private root folder for each user
        $folder_public_id = generate_nanoid();
        $stmt = $db->prepare("INSERT INTO folders (public_id, name, owner_id) VALUES (?, ?, ?)");
        $stmt->execute([$folder_public_id, 'Pliki ' . $u['name'], $user_id]);
        
        $login_info .= "Użytkownik: " . $u['name'] . "\n";
        $login_info .= "Rola:       " . $u['role'] . "\n";
        $login_info .= "Grupa:      " . $u['group'] . "\n";
        $login_info .= "Email:      " . $u['email'] . "\n";
        $login_info .= "Hasło:      " . $u['password'] . "\n";
        $login_info .= "--------------------------------------------------\n";
    }

    // Save login info to file
    file_put_contents($base_dir . '/dane_logowania.txt', $login_info);

    echo "\n--------------------------------------------------\n";
    echo "SUKCES: Środowisko gotowe! 🎉\n";
    echo "Dane logowania zapisano w: dane_logowania.txt\n";
    echo "--------------------------------------------------\n\n";
} catch (Exception $e) {
    echo "BŁĄD podczas tworzenia użytkowników: " . $e->getMessage() . "\n";
    exit(1);
}
