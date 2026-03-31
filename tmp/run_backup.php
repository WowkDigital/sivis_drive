<?php
$data_dir = __DIR__ . '/data';
require_once 'core/db.php';
require_once 'core/backup_logic.php';
var_dump(run_backup($db));
