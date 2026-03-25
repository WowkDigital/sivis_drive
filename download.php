<?php
require_once 'auth.php';
require_login();

if (!isset($_GET['id'])) {
    die("Brak pliku.");
}

$file_id = (int)$_GET['id'];
$stmt = $db->prepare("SELECT f.*, fol.access_groups FROM files f JOIN folders fol ON f.folder_id = fol.id WHERE f.id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    die("Plik nie istnieje.");
}

// Check access
$has_access = false;
if (is_admin()) {
    $has_access = true;
} else {
    $group = get_user_group();
    $access = array_map('trim', explode(',', $file['access_groups']));
    if (in_array(trim($group), $access) || empty($file['access_groups'])) {
        $has_access = true;
    }
}

if (!$has_access) {
    die("Brak dostępu do tego pliku.");
}

$filepath = __DIR__ . '/uploads/' . $file['name'];

if (file_exists($filepath)) {
    $is_view = isset($_GET['action']) && $_GET['action'] === 'view';
    $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));

    if ($is_view && $ext === 'pdf') {
        header('Content-Type: application/pdf');
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
