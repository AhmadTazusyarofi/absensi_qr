<?php
session_start();
session_destroy();
header('Location: /absensi/modules/auth/login.php');
exit;
