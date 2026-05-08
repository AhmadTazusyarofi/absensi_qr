<?php

function require_auth(array $roles = []): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: /absensi/modules/auth/login.php');
        exit;
    }

    if (!empty($roles) && !in_array($_SESSION['role'], $roles, true)) {
        header('Location: /absensi/dashboard.php');
        exit;
    }
}
