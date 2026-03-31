<?php
require_once 'core/db.php';
$stmt = $db->query("SELECT * FROM logs WHERE action LIKE 'BACKUP%' ORDER BY id DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
