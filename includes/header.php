<?php
$pageTitle = $pageTitle ?? 'Absensi SMAN 10';
$basePath = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — SMAN 10 Tangerang Selatan</title>
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
