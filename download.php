<?php
require_once 'auth.php';
require_once 'inc/functions.php';
require_login();

if (!isset($_GET['id'])) {
    die("Brak pliku.");
}

$file_id = (int)$_GET['id'];
$stmt = $db->prepare("SELECT f.*, fol.access_groups, fol.owner_id FROM files f JOIN folders fol ON f.folder_id = fol.id WHERE f.id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    die("Plik nie istnieje.");
}

// Check access
$role = $_SESSION['role'] ?? 'pracownik';
$group = get_user_group();
if (!can_user_access_folder($db, $file['folder_id'], $_SESSION['user_id'], $role, $group)) {
    die("Brak dostępu do tego pliku.");
}

$filepath = __DIR__ . '/uploads/' . $file['name'];

if (file_exists($filepath)) {
    $is_view = isset($_GET['action']) && $_GET['action'] === 'view';
    $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
    
    $viewable_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];

    if ($is_view && isset($viewable_types[$ext])) {
        header('Content-Type: ' . $viewable_types[$ext]);
        header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
    } else {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
    }

    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    die("Plik fizycznie nie istnieje na serwerze.");
}
?>
