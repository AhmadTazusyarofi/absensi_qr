<?php
require_once '../../includes/auth_check.php';
require_auth(['admin', 'guru']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$scanHariIni  = 0;
$totalAktif   = 0;
try {
    $pdo = getDB();
    $s = $pdo->prepare('SELECT COUNT(DISTINCT siswa_id) FROM absensi WHERE tanggal_absensi = ?');
    $s->execute([date('Y-m-d')]);
    $scanHariIni = (int) $s->fetchColumn();
    $totalAktif  = (int) $pdo->query('SELECT COUNT(*) FROM siswa WHERE status = "aktif"')->fetchColumn();
} catch (PDOException $e) {}

$pageTitle = 'Pemindai QR';
require_once $basePath . 'includes/header.php';
?>

<style>
@keyframes scan-line {
    0%   { top: 4px; }
    50%  { top: calc(100% - 4px); }
    100% { top: 4px; }
}
.scan-line {
    animation: scan-line 2s ease-in-out infinite;
}
@keyframes pulse-border {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.5; }
}
.scan-frame-active .scan-corner {
    animation: pulse-border 1.5s ease-in-out infinite;
}
</style>

<div class="flex h-screen overflow-hidden bg-gray-50">

    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 overflow-y-auto">

            <!-- Page Header -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Scan Absensi Siswa, disini!</h2>
                <p class="text-gray-400 text-sm mt-0.5">Posisikan kartu ID Siswa ke dalam bingkai untuk pencatatan otomatis</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

                <!-- Left: Camera -->
                <div class="lg:col-span-3 space-y-4">

                    <!-- Camera Container -->
                    <div class="relative bg-gray-900 rounded-2xl overflow-hidden" style="aspect-ratio: 4/3;">

                        <!-- Video -->
                        <video id="camera-video" class="absolute inset-0 w-full h-full object-cover" playsinline muted></video>

                        <!-- Canvas (hidden, used for QR decode) -->
                        <canvas id="scan-canvas" class="hidden"></canvas>

                        <!-- Overlay gradient -->
                        <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-transparent to-black/50 pointer-events-none"></div>

                        <!-- Scan frame -->
                        <div id="scan-frame" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div class="relative w-56 h-44">

                                <!-- Animated scan line -->
                                <div class="scan-line absolute left-1 right-1 h-0.5 bg-primary/80 rounded-full shadow-lg shadow-primary/50" style="top: 4px;"></div>

                                <!-- Corner pieces -->
                                <div class="scan-corner absolute top-0 left-0 w-7 h-7 border-t-2 border-l-2 border-primary rounded-tl-lg"></div>
                                <div class="scan-corner absolute top-0 right-0 w-7 h-7 border-t-2 border-r-2 border-primary rounded-tr-lg"></div>
                                <div class="scan-corner absolute bottom-0 left-0 w-7 h-7 border-b-2 border-l-2 border-primary rounded-bl-lg"></div>
                                <div class="scan-corner absolute bottom-0 right-0 w-7 h-7 border-b-2 border-r-2 border-primary rounded-br-lg"></div>

                            </div>
                        </div>

                        <!-- Camera off placeholder -->
                        <div id="camera-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-white/60">
                            <i class="bi bi-camera-video-off text-5xl mb-3"></i>
                            <p class="text-sm">Kamera belum aktif</p>
                            <button id="btn-start-camera" onclick="startCamera()"
                                    class="mt-4 px-5 py-2 text-sm font-semibold bg-primary text-white rounded-xl hover:bg-primary/90 transition">
                                Aktifkan Kamera
                            </button>
                        </div>

                        <!-- Status badge (top-left) -->
                        <div class="absolute top-4 left-4">
                            <div id="cam-status-badge" class="hidden items-center gap-1.5 px-3 py-1.5 bg-black/50 backdrop-blur rounded-full text-xs font-medium text-white">
                                <span id="cam-status-dot" class="w-2 h-2 rounded-full bg-red-400"></span>
                                <span id="cam-status-text">Menginisialisasi...</span>
                            </div>
                        </div>

                        <!-- Camera controls (bottom) -->
                        <div class="absolute bottom-4 left-0 right-0 flex justify-center items-center gap-4">
                            <button onclick="toggleFlip()"
                                    class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 backdrop-blur text-white hover:bg-white/20 transition"
                                    title="Flip kamera">
                                <i class="bi bi-arrow-repeat text-lg"></i>
                            </button>
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-white/90 text-gray-800 shadow-lg">
                                <i class="bi bi-camera-fill text-xl"></i>
                            </div>
                            <button id="btn-pause"
                                    onclick="togglePause()"
                                    class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 backdrop-blur text-white hover:bg-white/20 transition"
                                    title="Jeda scan">
                                <i class="bi bi-pause-fill text-lg"></i>
                            </button>
                        </div>

                    </div>

                    <!-- Stat Cards -->
                    <div class="grid grid-cols-2 gap-4">

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 shrink-0">
                                <i class="bi bi-lightning-charge-fill text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium">Efisiensi Scan</p>
                                <p class="text-base font-bold text-gray-800">Otomatis</p>
                                <p class="text-xs text-gray-400">Deteksi real-time</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-green-500 shrink-0">
                                <i class="bi bi-calendar-check-fill text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium">Hitungan Harian</p>
                                <p class="text-base font-bold text-gray-800" id="scan-count"><?= number_format($scanHariIni, 0, ',', '.') ?></p>
                                <p class="text-xs text-gray-400">Siswa scan kehadiran hari ini</p>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Right: Result Panel -->
                <div class="lg:col-span-2">

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

                        <!-- Status bar -->
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                            <div id="panel-status" class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-gray-300" id="panel-dot"></span>
                                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider" id="panel-label">Menunggu Scan</span>
                            </div>
                            <span class="text-xs text-gray-400" id="panel-time"></span>
                        </div>

                        <!-- Result content -->
                        <div class="p-6">

                            <!-- Waiting state -->
                            <div id="state-waiting" class="text-center py-10">
                                <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-qr-code-scan text-3xl text-gray-300"></i>
                                </div>
                                <p class="text-gray-400 text-sm font-medium">Arahkan kamera ke</p>
                                <p class="text-gray-400 text-sm">QR code kartu siswa</p>
                            </div>

                            <!-- Loading state -->
                            <div id="state-loading" class="text-center py-10 hidden">
                                <div class="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-arrow-repeat text-3xl text-primary animate-spin"></i>
                                </div>
                                <p class="text-gray-500 text-sm">Memproses...</p>
                            </div>

                            <!-- Success state -->
                            <div id="state-result" class="hidden">

                                <!-- Student avatar -->
                                <div class="flex flex-col items-center mb-5">
                                    <div id="result-avatar-wrap" class="relative mb-3">
                                        <div id="result-avatar-img" class="hidden w-20 h-20 rounded-full overflow-hidden border-4 border-white shadow-md">
                                            <img id="result-foto" src="" alt="" class="w-full h-full object-cover">
                                        </div>
                                        <div id="result-avatar-initials" class="w-20 h-20 rounded-full bg-primary/10 border-4 border-white shadow-md flex items-center justify-center text-primary text-2xl font-bold">
                                            ?
                                        </div>
                                        <!-- Status indicator -->
                                        <div id="result-status-dot" class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center">
                                            <i id="result-status-icon" class="bi text-xs text-white"></i>
                                        </div>
                                    </div>

                                    <h3 id="result-nama" class="text-lg font-bold text-gray-800 text-center"></h3>
                                    <p id="result-nis" class="text-sm text-primary font-semibold font-mono mt-0.5"></p>
                                </div>

                                <!-- Status badge -->
                                <div class="flex justify-center mb-5">
                                    <span id="result-status-badge" class="px-6 py-1.5 text-sm font-bold rounded-full uppercase tracking-wide"></span>
                                </div>

                                <!-- Info rows -->
                                <div class="space-y-3 mb-5">
                                    <div class="flex items-center justify-between text-sm border-b border-gray-50 pb-3">
                                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Kelas</span>
                                        <span id="result-kelas" class="font-semibold text-gray-700"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Jam Masuk</span>
                                        <span id="result-waktu" class="font-semibold text-gray-700"></span>
                                    </div>
                                </div>

                                <!-- Message -->
                                <div id="result-message-box" class="px-4 py-3 rounded-xl text-xs font-medium text-center"></div>

                            </div>

                            <!-- Error state -->
                            <div id="state-error" class="text-center py-10 hidden">
                                <div class="w-20 h-20 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-x-circle-fill text-3xl text-red-400"></i>
                                </div>
                                <p class="text-gray-700 text-sm font-semibold" id="error-title">QR Tidak Dikenali</p>
                                <p class="text-gray-400 text-xs mt-1" id="error-msg">Barcode tidak ditemukan dalam sistem.</p>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </main>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsqr/1.4.0/jsQR.min.js"></script>
<script>
const video      = document.getElementById('camera-video');
const canvas     = document.getElementById('scan-canvas');
const ctx        = canvas.getContext('2d');
const placeholder = document.getElementById('camera-placeholder');
const camBadge   = document.getElementById('cam-status-badge');
const camDot     = document.getElementById('cam-status-dot');
const camText    = document.getElementById('cam-status-text');

let stream       = null;
let scanning     = true;
let paused       = false;
let lastBarcode  = '';
let cooldown     = false;
let facingMode   = 'environment';
let rafId        = null;

// Update clock
function updateClock() {
    const now  = new Date();
    const hh   = String(now.getHours()).padStart(2, '0');
    const mm   = String(now.getMinutes()).padStart(2, '0');
    const ss   = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('panel-time').textContent = `${hh}:${mm}:${ss}`;
}
setInterval(updateClock, 1000);
updateClock();

async function startCamera() {
    try {
        if (stream) stream.getTracks().forEach(t => t.stop());
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode, width: { ideal: 1280 }, height: { ideal: 960 } }
        });
        video.srcObject = stream;
        await video.play();

        placeholder.classList.add('hidden');
        camBadge.classList.remove('hidden');
        camBadge.classList.add('flex');
        setStatus('active');

        requestAnimationFrame(scanLoop);
    } catch (err) {
        setStatus('error');
        placeholder.innerHTML = `
            <i class="bi bi-exclamation-triangle-fill text-4xl text-red-400 mb-3"></i>
            <p class="text-sm text-white/70 text-center px-4">${err.name === 'NotAllowedError' ? 'Akses kamera ditolak. Izinkan akses kamera di browser.' : 'Kamera tidak tersedia.'}</p>
        `;
    }
}

function setStatus(state) {
    const states = {
        active: { dot: 'bg-green-400', text: 'Scan Aktif' },
        paused: { dot: 'bg-yellow-400', text: 'Dijeda' },
        error:  { dot: 'bg-red-400',   text: 'Error' },
    };
    const s = states[state] || states.active;
    camDot.className  = `w-2 h-2 rounded-full ${s.dot}`;
    camText.textContent = s.text;
}

function scanLoop() {
    if (!paused && video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, canvas.width, canvas.height, {
            inversionAttempts: 'dontInvert',
        });

        if (code && code.data && !cooldown && code.data !== lastBarcode) {
            lastBarcode = code.data;
            cooldown    = true;
            processBarcode(code.data);
            setTimeout(() => {
                cooldown    = false;
                lastBarcode = '';
            }, 3000);
        }
    }
    rafId = requestAnimationFrame(scanLoop);
}

function togglePause() {
    paused = !paused;
    const btn = document.getElementById('btn-pause');
    btn.innerHTML = paused
        ? '<i class="bi bi-play-fill text-lg"></i>'
        : '<i class="bi bi-pause-fill text-lg"></i>';
    setStatus(paused ? 'paused' : 'active');
    if (!paused) lastBarcode = '';
}

async function toggleFlip() {
    facingMode = facingMode === 'environment' ? 'user' : 'environment';
    await startCamera();
}

async function processBarcode(barcode) {
    showState('loading');

    try {
        const res  = await fetch('process_scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `barcode=${encodeURIComponent(barcode)}`,
        });
        const data = await res.json();

        if (!data.success) {
            showError(
                data.error === 'not_found' ? 'QR Tidak Dikenali' : 'Terjadi Kesalahan',
                data.message
            );
            return;
        }

        showResult(data);

        // Update daily count if new attendance recorded
        if (!data.already_attended) {
            const el = document.getElementById('scan-count');
            el.textContent = (parseInt(el.textContent.replace(/\./g, '')) + 1).toLocaleString('id-ID');
        }

    } catch (err) {
        showError('Koneksi Gagal', 'Tidak dapat menghubungi server.');
    }
}

function showState(state) {
    ['waiting', 'loading', 'result', 'error'].forEach(s => {
        document.getElementById(`state-${s}`).classList.toggle('hidden', s !== state);
    });
}

function showResult(data) {
    const s = data.student;

    // Avatar
    if (s.foto) {
        document.getElementById('result-avatar-img').classList.remove('hidden');
        document.getElementById('result-avatar-initials').classList.add('hidden');
        document.getElementById('result-foto').src = `/absensi/uploads/students/${s.foto}`;
    } else {
        document.getElementById('result-avatar-img').classList.add('hidden');
        document.getElementById('result-avatar-initials').classList.remove('hidden');
        document.getElementById('result-avatar-initials').textContent = s.nama.charAt(0).toUpperCase();
    }

    document.getElementById('result-nama').textContent  = s.nama;
    document.getElementById('result-nis').textContent   = 'ID: ' + s.nis;
    document.getElementById('result-kelas').textContent = s.kelas;
    document.getElementById('result-waktu').textContent = data.attendance?.waktu ?? '—';

    const dot   = document.getElementById('result-status-dot');
    const icon  = document.getElementById('result-status-icon');
    const badge = document.getElementById('result-status-badge');
    const msgBox = document.getElementById('result-message-box');

    if (data.already_attended) {
        dot.className   = 'absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center bg-yellow-400';
        icon.className  = 'bi bi-exclamation text-xs text-white';
        badge.className = 'px-6 py-1.5 text-sm font-bold rounded-full uppercase tracking-wide bg-yellow-50 text-yellow-700';
        badge.textContent = 'SUDAH ABSEN';
        msgBox.className = 'px-4 py-3 rounded-xl text-xs font-medium text-center bg-yellow-50 text-yellow-700';
        msgBox.textContent = 'Siswa ini sudah tercatat hadir hari ini.';
        setPanelStatus('warning', 'Sudah Absen');
    } else {
        dot.className   = 'absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center bg-green-500';
        icon.className  = 'bi bi-check text-xs text-white';
        badge.className = 'px-6 py-1.5 text-sm font-bold rounded-full uppercase tracking-wide bg-green-50 text-green-700';
        badge.textContent = 'HADIR';
        msgBox.className = 'px-4 py-3 rounded-xl text-xs font-medium text-center bg-green-50 text-green-700';
        msgBox.textContent = 'Kehadiran berhasil dicatat!';
        setPanelStatus('success', 'Scan Berhasil');
    }

    showState('result');
}

function showError(title, msg) {
    document.getElementById('error-title').textContent = title;
    document.getElementById('error-msg').textContent   = msg;
    setPanelStatus('error', 'Gagal');
    showState('error');

    setTimeout(() => {
        showState('waiting');
        setPanelStatus('idle', 'Menunggu Scan');
    }, 2500);
}

function setPanelStatus(type, label) {
    const colors = {
        success: 'bg-green-400',
        warning: 'bg-yellow-400',
        error:   'bg-red-400',
        idle:    'bg-gray-300',
    };
    document.getElementById('panel-dot').className   = `w-2 h-2 rounded-full ${colors[type] ?? 'bg-gray-300'}`;
    document.getElementById('panel-label').textContent = label;
}

// Auto-start camera on load
window.addEventListener('load', () => {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        startCamera();
    }
});

// Stop camera when leaving page
window.addEventListener('beforeunload', () => {
    if (stream) stream.getTracks().forEach(t => t.stop());
});
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
