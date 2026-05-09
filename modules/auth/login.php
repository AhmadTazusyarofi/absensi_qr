<?php
$pageTitle = 'Login';
$basePath  = '../../';
require_once $basePath . 'includes/header.php';

$error = $_GET['error'] ?? '';
?>

<div class="flex min-h-screen">

    <!-- Left Branding Panel -->
    <div class="hidden lg:flex lg:w-1/2 flex-col items-center justify-center gap-8 p-12"
        style="background: linear-gradient(135deg, #0084d4 0%, #005a94 100%);">

        <img src="<?= $basePath ?>assets/images/logo.png" alt="Logo SMAN 10"
            class="w-40 h-40 object-contain drop-shadow-xl">

        <div class="text-center text-white">
            <h1 class="text-4xl font-bold leading-tight">Sistem Absensi</h1>
            <h2 class="text-4xl font-bold leading-tight">SMA 10 Tangerang</h2>
        </div>

    </div>

    <!-- Right Form Panel -->
    <div class="w-full lg:w-1/2 flex flex-col bg-white px-8 py-12">

        <div class="flex-1 flex flex-col items-center justify-center">
            <div class="w-full max-w-sm">

                <h2 class="text-2xl font-bold text-gray-800 mb-1">Selamat Datang Kembali!</h2>
                <p class="text-gray-400 text-sm mb-8 leading-snug">
                    Silahkan Masuk Ke Akun Anda Untuk Mengelola Presensi Siswa
                </p>

                <?php if ($error): ?>
                    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="process_login.php" method="POST" novalidate>

                    <!-- Email -->
                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">
                            Email
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="email" name="email" placeholder="Masukan Email Anda Disini" required
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                class="w-full pl-9 pr-4 py-3 text-sm border border-gray-200 rounded-lg bg-gray-50 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary focus:bg-white transition">
                        </div>
                    </div>

                    <!-- Kata Sandi -->
                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1.5">
                            Kata Sandi
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input id="password" type="password" placeholder="Masukan Password Anda Disini"
                                name="password" required
                                class="w-full pl-9 pr-10 py-3 text-sm border border-gray-200 rounded-lg bg-gray-50 placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary focus:bg-white transition">
                            <button type="button" onclick="togglePw('password', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition text-base"
                                tabindex="-1">
                                <i class="bi bi-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Ingat Saya + Lupa Password -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center gap-2 text-sm text-gray-500 cursor-pointer select-none">
                            <input type="checkbox" name="remember"
                                class="rounded border-gray-300 text-primary focus:ring-primary">
                            Ingat Saya
                        </label>
                        <a href="#" class="text-sm font-medium text-primary hover:underline">Lupa Password?</a>
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                        class="w-full bg-primary hover:bg-primary/90 active:scale-[.98] text-white font-semibold py-3 rounded-lg flex items-center justify-center gap-2 transition">
                        Masuk
                        <i class="bi bi-arrow-right"></i>
                    </button>

                </form>
            </div>
        </div>

        <p class="text-xs text-gray-300 text-center tracking-widest uppercase">
            &copy; 2026 Sistem Absensi SMAN 10 Tangerang Selatan. All rights reserved.
        </p>

    </div>

</div>

<?php require_once $basePath . 'includes/footer.php'; ?>