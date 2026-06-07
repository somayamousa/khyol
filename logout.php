<?php
require_once 'config/db.php';
session_unset();
session_destroy();
redirect('index.php');
?>
