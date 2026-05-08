<?php
$roleLabels = [
    'admin'     => 'Kepala Admin',
    'guru'      => 'Guru',
    'siswa'     => 'Siswa',
    'orang_tua' => 'Orang Tua',
];

$role    = $_SESSION['role'] ?? 'guest';
$nama    = $_SESSION['nama'] ?? '';
$foto    = $_SESSION['foto'] ?? null;
$initial = mb_strtoupper(mb_substr($nama, 0, 1));
?>

<header class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-4 sticky top-0 z-10">

    <!-- Hamburger toggle -->
    <button onclick="toggleSidebar()"
        class="text-gray-500 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition shrink-0">
        <i class="bi bi-list text-xl"></i>
    </button>

    <!-- Search bar -->
    <div class="flex-1 max-w-md">
        <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" placeholder="Cari siswa atau kelas..."
                class="w-full pl-9 pr-4 py-2 text-sm bg-gray-100 border-0 rounded-full placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:bg-white transition">
        </div>
    </div>

    <!-- User info -->
    <div class="ml-auto flex items-center gap-3 shrink-0">
        <div class="text-right hidden sm:block">
            <p class="text-sm font-semibold text-gray-800 leading-tight"><?= htmlspecialchars($nama) ?></p>
            <p class="text-xs text-gray-400 uppercase tracking-wide"><?= $roleLabels[$role] ?? $role ?></p>
        </div>
        <?php if ($foto): ?>
        <img src="/absensi/uploads/<?= htmlspecialchars($foto) ?>" alt="Foto"
            class="w-10 h-10 rounded-full object-cover border-2 border-gray-200">
        <?php else: ?>
        <div
            class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold text-sm shrink-0">
            <?= htmlspecialchars($initial) ?>
        </div>
        <?php endif; ?>
    </div>

</header>