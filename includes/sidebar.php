<?php
$role        = $_SESSION['role'] ?? '';
$currentPath = $_SERVER['PHP_SELF'];

function sidebarLink(string $href, string $icon, string $label, string $current): string
{
    $isActive = basename($current) === basename($href) ||
                (basename($href) !== 'dashboard.php' && str_contains($current, dirname($href)));
    $active = $isActive
        ? 'bg-primary text-white shadow-sm'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900';

    return "<a href=\"{$href}\" class=\"flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition {$active}\">
                <i class=\"bi {$icon} text-base\"></i> {$label}
            </a>";
}
?>

<aside id="sidebar" class="w-64 min-h-screen flex flex-col shrink-0 bg-white border-r border-gray-200 transition-all duration-300">

    <!-- Brand -->
    <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
        <img src="/absensi/assets/images/logo.png" alt="Logo" class="w-10 h-10 object-contain shrink-0">
        <div class="leading-tight overflow-hidden">
            <p class="text-primary font-bold text-sm truncate">SMA 10 Tangerang</p>
            <p class="text-gray-400 text-xs">Sistem Absensi</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

        <?= sidebarLink('/absensi/dashboard.php', 'bi-grid-fill', 'Dashboard', $currentPath) ?>

        <?php if ($role === 'admin'): ?>
        <?= sidebarLink('/absensi/modules/students/index.php', 'bi-people', 'Data Siswa', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/attendance/scan.php', 'bi-qr-code-scan', 'Pemindai QR', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/attendance/history.php', 'bi-journal-text', 'Log Kehadiran', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/reports/index.php', 'bi-bar-chart-line', 'Laporan', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/parents/index.php', 'bi-people-fill', 'Pantauan Orang Tua', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === 'guru'): ?>
        <?= sidebarLink('/absensi/modules/attendance/scan.php', 'bi-qr-code-scan', 'Pemindai QR', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/attendance/history.php', 'bi-journal-text', 'Log Kehadiran', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/reports/index.php', 'bi-bar-chart-line', 'Laporan', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === 'siswa'): ?>
        <?= sidebarLink('/absensi/modules/attendance/my-attendance.php', 'bi-journal-text', 'Log Kehadiran', $currentPath) ?>
        <?= sidebarLink('/absensi/modules/profile/index.php', 'bi-person-circle', 'Profil Saya', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === 'orang_tua'): ?>
        <?= sidebarLink('/absensi/modules/parents/report.php', 'bi-people-fill', 'Pantauan Anak', $currentPath) ?>
        <?php endif; ?>

    </nav>

    <!-- Logout -->
    <div class="px-3 py-4 border-t border-gray-100">
        <a href="/absensi/modules/auth/logout.php"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-500 hover:bg-red-50 transition">
            <i class="bi bi-box-arrow-left text-base"></i> Logout
        </a>
    </div>

</aside>
