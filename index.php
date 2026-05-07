<?php
$pageTitle = 'Selamat Datang';
require_once 'includes/header.php';
?>

<div class="flex flex-col items-center justify-center min-h-screen px-4">
    <div class="bg-white rounded-2xl shadow-lg p-10 max-w-md w-full text-center">

        <img
            src="assets/images/logo.png"
            alt="Logo SMAN 10"
            class="w-24 h-24 mx-auto mb-6 object-contain"
        >

        <h1 class="text-2xl font-bold text-gray-800 mb-2">
            Sistem Absensi Siswa
        </h1>

        <p class="text-gray-500 mb-1 text-sm">
            SMAN 10 Tangerang Selatan
        </p>

        <div class="mt-6 inline-flex items-center gap-2 bg-blue-50 text-blue-700 px-4 py-2 rounded-full text-sm font-medium">
            <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
            Sistem sedang disiapkan
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
