<?php
$db = new PDO('sqlite:data/database.sqlite');
$st = $db->query('SELECT * FROM logs ORDER BY created_at DESC LIMIT 5');
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}
