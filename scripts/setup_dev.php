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

// 4. Create admin user
echo "[4/4] Tworzenie konta administratora ($email)...\n";
try {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $public_id = generate_nanoid();
    $stmt = $db->prepare("INSERT INTO users (public_id, email, password_hash, role, user_group, display_name) VALUES (?, ?, ?, 'admin', 'Zarząd', ?)");
    $stmt->execute([$public_id, $email, $hash, 'Główny Administrator']);
    
    // Create private root folder for admin
    $folder_public_id = generate_nanoid();
    $admin_user_id = $db->lastInsertId();
    $stmt = $db->prepare("INSERT INTO folders (public_id, name, owner_id) VALUES (?, ?, ?)");
    $stmt->execute([$folder_public_id, 'Pliki Administrator', $admin_user_id]);

    echo "\n--------------------------------------------------\n";
    echo "SUKCES: Środowisko gotowe! 🎉\n";
    echo "Email: $email\n";
    echo "Hasło: $password\n";
    echo "--------------------------------------------------\n\n";
} catch (Exception $e) {
    echo "BŁĄD podczas tworzenia użytkownika: " . $e->getMessage() . "\n";
    exit(1);
}
