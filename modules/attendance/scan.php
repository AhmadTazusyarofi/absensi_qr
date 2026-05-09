<?php
require_once '../../includes/auth_check.php';
require_auth(['admin', 'guru']);

$basePath = '../../';
require_once $basePath . 'config/database.php';

$scanHariIni = 0;
try {
    $pdo = getDB();
    $s = $pdo->prepare('SELECT COUNT(DISTINCT siswa_id) FROM absensi WHERE tanggal_absensi = ?');
    $s->execute([date('Y-m-d')]);
    $scanHariIni = (int) $s->fetchColumn();
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
.scan-line { animation: scan-line 2s ease-in-out infinite; }

@keyframes toast-in  { from { transform:translateX(-50%) translateY(80px); opacity:0; } to { transform:translateX(-50%) translateY(0); opacity:1; } }
@keyframes toast-out { from { transform:translateX(-50%) translateY(0); opacity:1; } to { transform:translateX(-50%) translateY(80px); opacity:0; } }
.toast-in  { animation: toast-in  .35s cubic-bezier(.22,.68,0,1.2) forwards; }
.toast-out { animation: toast-out .3s ease-in forwards; }
</style>

<div class="flex h-screen overflow-hidden bg-gray-50">
    <?php require_once $basePath . 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <?php require_once $basePath . 'includes/navbar.php'; ?>

        <main class="flex-1 p-6 overflow-y-auto">

            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Scan Absensi Siswa</h2>
                <p class="text-gray-400 text-sm mt-0.5">Arahkan kamera ke QR code / barcode kartu siswa</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

                <!-- Kamera -->
                <div class="lg:col-span-3 space-y-4">
                    <div class="relative bg-gray-900 rounded-2xl overflow-hidden" style="aspect-ratio:4/3;">

                        <!-- Video kamera (z-index dasar, TIDAK ditutup overlay interaktif) -->
                        <video id="cam-video"
                               class="absolute inset-0 w-full h-full object-cover"
                               playsinline muted autoplay></video>
                        <canvas id="cam-canvas" class="hidden"></canvas>

                        <!-- Gradient dekoratif — pointer-events:none, tidak memblok apapun -->
                        <div class="absolute inset-0 pointer-events-none z-10"
                             style="background:linear-gradient(to bottom, rgba(0,0,0,.25) 0%, transparent 40%, transparent 60%, rgba(0,0,0,.45) 100%);"></div>

                        <!-- Bingkai scan — hanya dekorasi -->
                        <div id="scan-frame" class="absolute inset-0 items-center justify-center pointer-events-none z-20 hidden">
                            <div class="relative w-60 h-48">
                                <div class="scan-line absolute left-1 right-1 h-0.5 bg-primary/80 rounded-full shadow-lg shadow-primary/40" style="top:4px;"></div>
                                <div class="absolute top-0 left-0 w-8 h-8 border-t-2 border-l-2 border-primary rounded-tl-lg"></div>
                                <div class="absolute top-0 right-0 w-8 h-8 border-t-2 border-r-2 border-primary rounded-tr-lg"></div>
                                <div class="absolute bottom-0 left-0 w-8 h-8 border-b-2 border-l-2 border-primary rounded-bl-lg"></div>
                                <div class="absolute bottom-0 right-0 w-8 h-8 border-b-2 border-r-2 border-primary rounded-br-lg"></div>
                            </div>
                        </div>

                        <!-- Placeholder (disembunyikan sepenuhnya saat kamera aktif) -->
                        <div id="cam-placeholder" class="absolute inset-0 flex flex-col items-center justify-center gap-4 px-6 z-30 bg-gray-900">
                            <div class="w-20 h-20 rounded-full bg-white/10 flex items-center justify-center">
                                <i class="bi bi-camera-video text-white text-4xl"></i>
                            </div>
                            <div class="text-center">
                                <p id="ph-title" class="text-white font-semibold text-base mb-1">Kamera Belum Aktif</p>
                                <p id="ph-sub"   class="text-white/60 text-sm leading-snug">Tekan tombol di bawah untuk mulai scan</p>
                            </div>
                            <button id="btn-cam" onclick="startCamera()"
                                    class="px-8 py-3 text-sm font-bold bg-primary text-white rounded-2xl hover:bg-primary/90 active:scale-95 transition shadow-lg">
                                <i class="bi bi-camera-video-fill me-2"></i>Aktifkan Kamera
                            </button>
                        </div>

                        <!-- Badge status (atas-kiri) -->
                        <div class="absolute top-4 left-4 z-40 hidden" id="cam-badge">
                            <div class="flex items-center gap-1.5 px-3 py-1.5 bg-black/50 backdrop-blur rounded-full text-xs font-medium text-white">
                                <span id="badge-dot" class="w-2 h-2 rounded-full bg-green-400"></span>
                                <span id="badge-txt">Scan Aktif</span>
                            </div>
                        </div>

                        <!-- Kontrol kamera (bawah) -->
                        <div id="cam-controls" class="absolute bottom-4 left-0 right-0 hidden justify-center items-center gap-4 z-40">
                            <button onclick="flipCam()"
                                    class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 backdrop-blur text-white hover:bg-white/20 transition">
                                <i class="bi bi-arrow-repeat text-lg"></i>
                            </button>
                            <div class="w-12 h-12 flex items-center justify-center rounded-full bg-white/90 text-gray-800 shadow-lg">
                                <i class="bi bi-camera-fill text-xl"></i>
                            </div>
                            <button id="btn-pause" onclick="togglePause()"
                                    class="w-10 h-10 flex items-center justify-center rounded-full bg-white/10 backdrop-blur text-white hover:bg-white/20 transition">
                                <i class="bi bi-pause-fill text-lg"></i>
                            </button>
                        </div>

                    </div>

                    <!-- Stat cards -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 shrink-0">
                                <i class="bi bi-lightning-charge-fill text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium">Mode Scan</p>
                                <p class="text-base font-bold text-gray-800" id="scan-engine">Otomatis</p>
                                <p class="text-xs text-gray-400">Deteksi real-time</p>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-green-500 shrink-0">
                                <i class="bi bi-calendar-check-fill text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium">Scan Hari Ini</p>
                                <p class="text-base font-bold text-gray-800" id="scan-count"><?= number_format($scanHariIni, 0, ',', '.') ?></p>
                                <p class="text-xs text-gray-400">siswa tercatat hadir</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Hasil -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-gray-300" id="panel-dot"></span>
                                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider" id="panel-lbl">Menunggu Scan</span>
                            </div>
                            <span class="text-xs text-gray-400 font-mono" id="panel-time"></span>
                        </div>
                        <div class="p-6">

                            <div id="st-wait" class="text-center py-10">
                                <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-qr-code-scan text-3xl text-gray-300"></i>
                                </div>
                                <p class="text-gray-400 text-sm">Arahkan kamera ke QR code / barcode kartu siswa</p>
                            </div>

                            <div id="st-load" class="text-center py-10 hidden">
                                <div class="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-arrow-repeat text-3xl text-primary animate-spin"></i>
                                </div>
                                <p class="text-gray-500 text-sm">Memproses...</p>
                            </div>

                            <div id="st-result" class="hidden">
                                <div class="flex flex-col items-center mb-5">
                                    <div class="relative mb-3">
                                        <div id="av-img" class="hidden w-20 h-20 rounded-full overflow-hidden border-4 border-white shadow-md">
                                            <img id="av-foto" src="" alt="" class="w-full h-full object-cover">
                                        </div>
                                        <div id="av-init" class="w-20 h-20 rounded-full bg-primary/10 border-4 border-white shadow-md flex items-center justify-center text-primary text-2xl font-bold">?</div>
                                        <div id="av-dot" class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center">
                                            <i id="av-icon" class="bi text-xs text-white"></i>
                                        </div>
                                    </div>
                                    <h3 id="r-nama"  class="text-lg font-bold text-gray-800 text-center"></h3>
                                    <p  id="r-nis"   class="text-sm text-primary font-semibold font-mono mt-0.5"></p>
                                </div>
                                <div class="flex justify-center mb-5">
                                    <span id="r-badge" class="px-6 py-1.5 text-sm font-bold rounded-full uppercase tracking-wide"></span>
                                </div>
                                <div class="space-y-3 mb-5">
                                    <div class="flex items-center justify-between text-sm border-b border-gray-50 pb-3">
                                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Kelas</span>
                                        <span id="r-kelas" class="font-semibold text-gray-700"></span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Jam Masuk</span>
                                        <span id="r-waktu" class="font-semibold text-gray-700"></span>
                                    </div>
                                </div>
                                <div id="r-msg" class="px-4 py-3 rounded-xl text-xs font-medium text-center"></div>
                            </div>

                            <div id="st-err" class="text-center py-10 hidden">
                                <div class="w-20 h-20 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                                    <i class="bi bi-x-circle-fill text-3xl text-red-400"></i>
                                </div>
                                <p class="text-gray-700 text-sm font-semibold" id="err-title">QR Tidak Dikenali</p>
                                <p class="text-gray-400 text-xs mt-1" id="err-msg"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-8 left-1/2 z-50 pointer-events-none"
     style="transform:translateX(-50%); display:none; min-width:300px; max-width:90vw;">
    <div id="toast-inner" class="flex items-center gap-4 px-5 py-4 rounded-2xl shadow-2xl text-white">
        <div id="toast-av"   class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-xl font-bold shrink-0"></div>
        <div class="flex-1 min-w-0">
            <p id="toast-name" class="font-bold text-base leading-tight truncate"></p>
            <p id="toast-msg"  class="text-sm opacity-85 mt-0.5"></p>
        </div>
        <i id="toast-icon" class="bi text-2xl shrink-0"></i>
    </div>
</div>

<!-- jsQR: fallback untuk browser tanpa BarcodeDetector (desktop Firefox, Safari) -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
/* ─── State ─────────────────────────────────────────────────────────────────── */
const video   = document.getElementById('cam-video');
const canvas  = document.getElementById('cam-canvas');
const ctx     = canvas.getContext('2d');

let mediaStream  = null;
let paused       = false;
let cooldown     = false;
let lastCode     = '';
let facingMode   = 'environment';
let toastTimer   = null;
let detector     = null; // native BarcodeDetector jika tersedia

/* ─── Inisialisasi BarcodeDetector ──────────────────────────────────────────── */
(async () => {
    if ('BarcodeDetector' in window) {
        try {
            const supported = await BarcodeDetector.getSupportedFormats();
            detector = new BarcodeDetector({ formats: supported });
            document.getElementById('scan-engine').textContent = 'BarcodeDetector';
        } catch (_) { detector = null; }
    }
})();

/* ─── Jam ────────────────────────────────────────────────────────────────────── */
setInterval(() => {
    const d = new Date();
    document.getElementById('panel-time').textContent =
        [d.getHours(), d.getMinutes(), d.getSeconds()].map(n => String(n).padStart(2,'0')).join(':');
}, 1000);

/* ─── Kamera ─────────────────────────────────────────────────────────────────── */
async function startCamera() {
    const btn = document.getElementById('btn-cam');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin me-2"></i>Membuka...';

    try {
        if (mediaStream) mediaStream.getTracks().forEach(t => t.stop());

        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: facingMode } },
            audio: false
        });

        video.srcObject = mediaStream;
        await new Promise(resolve => {
            video.onloadedmetadata = () => { video.play().then(resolve).catch(resolve); };
        });

        // Sembunyikan placeholder SEPENUHNYA
        const ph = document.getElementById('cam-placeholder');
        ph.style.display = 'none';

        // Tampilkan UI kamera
        show('cam-badge');
        show('scan-frame');
        showFlex('cam-controls');
        setStatus('active');

        // Mulai loop deteksi
        requestAnimationFrame(detectLoop);

    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-camera-video-fill me-2"></i>Coba Lagi';

        const name = (err.name || '').toLowerCase();
        const isPermission = name.includes('notallowed') || name.includes('permission') || name.includes('denied');
        document.getElementById('ph-title').textContent = isPermission ? 'Akses Kamera Ditolak' : 'Kamera Gagal Dibuka';
        document.getElementById('ph-sub').textContent   = isPermission
            ? 'Izinkan akses kamera di browser, lalu tekan Coba Lagi.'
            : (err.message || err.name || 'Tidak dapat membuka kamera.');
    }
}

function setStatus(s) {
    const map = { active:{dot:'bg-green-400',txt:'Scan Aktif'}, paused:{dot:'bg-yellow-400',txt:'Dijeda'} };
    const c = map[s] || map.active;
    document.getElementById('badge-dot').className = `w-2 h-2 rounded-full ${c.dot}`;
    document.getElementById('badge-txt').textContent = c.txt;
}

async function flipCam() {
    facingMode = facingMode === 'environment' ? 'user' : 'environment';
    if (mediaStream) mediaStream.getTracks().forEach(t => t.stop());
    mediaStream = null;
    await startCamera();
}

function togglePause() {
    paused = !paused;
    document.getElementById('btn-pause').innerHTML = paused
        ? '<i class="bi bi-play-fill text-lg"></i>'
        : '<i class="bi bi-pause-fill text-lg"></i>';
    setStatus(paused ? 'paused' : 'active');
    if (!paused) lastCode = '';
}

/* ─── Loop deteksi ───────────────────────────────────────────────────────────── */
async function detectLoop() {
    if (!paused && video.readyState >= 2 && !cooldown) {
        const code = await detectBarcode();
        if (code && code !== lastCode) {
            lastCode = code;
            cooldown = true;
            processBarcode(code);
            setTimeout(() => { cooldown = false; lastCode = ''; }, 3500);
        }
    }
    if (mediaStream) requestAnimationFrame(detectLoop);
}

async function detectBarcode() {
    // 1. Coba BarcodeDetector (native, support Code128 & semua format)
    if (detector) {
        try {
            const results = await detector.detect(video);
            if (results.length > 0) return results[0].rawValue;
        } catch (_) {}
    }

    // 2. Fallback: jsQR (QR code only)
    if (typeof jsQR !== 'undefined' && video.videoWidth > 0) {
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const r   = jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
        if (r && r.data) return r.data;
    }

    return null;
}

/* ─── Proses barcode ─────────────────────────────────────────────────────────── */
async function processBarcode(barcode) {
    showState('load');
    panelStatus('idle', 'Memproses...');

    try {
        const res  = await fetch('process_scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'barcode=' + encodeURIComponent(barcode),
        });
        const data = await res.json();

        if (!data.success) {
            const t = data.error === 'not_found' ? 'QR Tidak Dikenali' : 'Terjadi Kesalahan';
            showErr(t, data.message);
            showToast('error', t, data.message);
            return;
        }

        showResult(data);
        if (!data.already_attended) {
            const el = document.getElementById('scan-count');
            el.textContent = (parseInt(el.textContent.replace(/\./g,'')) + 1).toLocaleString('id-ID');
        }
    } catch (_) {
        showErr('Koneksi Gagal', 'Tidak dapat menghubungi server.');
        showToast('error', 'Koneksi Gagal', 'Tidak dapat menghubungi server.');
    }
}

/* ─── UI ─────────────────────────────────────────────────────────────────────── */
function showState(s) {
    ['wait','load','result','err'].forEach(x =>
        document.getElementById('st-'+x).classList.toggle('hidden', x !== s));
}

function showResult(data) {
    const s = data.student;
    if (s.foto) {
        document.getElementById('av-img').classList.remove('hidden');
        document.getElementById('av-init').classList.add('hidden');
        document.getElementById('av-foto').src = '/absensi/uploads/students/' + s.foto;
    } else {
        document.getElementById('av-img').classList.add('hidden');
        document.getElementById('av-init').classList.remove('hidden');
        document.getElementById('av-init').textContent = s.nama.charAt(0).toUpperCase();
    }
    document.getElementById('r-nama').textContent  = s.nama;
    document.getElementById('r-nis').textContent   = 'NIS: ' + s.nis;
    document.getElementById('r-kelas').textContent = s.kelas;
    document.getElementById('r-waktu').textContent = data.attendance?.waktu ?? '—';

    const dot  = document.getElementById('av-dot');
    const icon = document.getElementById('av-icon');
    const bag  = document.getElementById('r-badge');
    const msg  = document.getElementById('r-msg');

    if (data.already_attended) {
        dot.className  = 'absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center bg-yellow-400';
        icon.className = 'bi bi-exclamation text-xs text-white';
        bag.className  = 'px-6 py-1.5 text-sm font-bold rounded-full uppercase tracking-wide bg-yellow-50 text-yellow-700';
        bag.textContent  = 'SUDAH ABSEN';
        msg.className    = 'px-4 py-3 rounded-xl text-xs font-medium text-center bg-yellow-50 text-yellow-700';
        msg.textContent  = 'Siswa ini sudah tercatat hadir hari ini.';
        panelStatus('warning', 'Sudah Absen');
        showToast('warning', s.nama, 'Sudah absen · ' + (data.attendance?.waktu ?? ''));
    } else {
        dot.className  = 'absolute -bottom-1 -right-1 w-6 h-6 rounded-full border-2 border-white flex items-center justify-center bg-green-500';
        icon.className = 'bi bi-check text-xs text-white';
        bag.className  = 'px-6 py-1.5 text-sm font-bold rounded-full uppercase tracking-wide bg-green-50 text-green-700';
        bag.textContent  = 'HADIR';
        msg.className    = 'px-4 py-3 rounded-xl text-xs font-medium text-center bg-green-50 text-green-700';
        msg.textContent  = 'Kehadiran berhasil dicatat!';
        panelStatus('success', 'Scan Berhasil');
        showToast('success', s.nama, 'Hadir · ' + (data.attendance?.waktu ?? '') + ' · ' + s.kelas);
    }
    showState('result');
}

function showErr(title, msg) {
    document.getElementById('err-title').textContent = title;
    document.getElementById('err-msg').textContent   = msg;
    panelStatus('error', 'Gagal');
    showState('err');
    setTimeout(() => { showState('wait'); panelStatus('idle','Menunggu Scan'); }, 2500);
}

function panelStatus(type, label) {
    const c = {success:'bg-green-400',warning:'bg-yellow-400',error:'bg-red-400',idle:'bg-gray-300'};
    document.getElementById('panel-dot').className = 'w-2 h-2 rounded-full ' + (c[type]||'bg-gray-300');
    document.getElementById('panel-lbl').textContent = label;
}

/* ─── Toast ──────────────────────────────────────────────────────────────────── */
function showToast(type, name, msg) {
    const cfg = {
        success: { bg:'bg-green-500',  icon:'bi-check-circle-fill' },
        warning: { bg:'bg-yellow-500', icon:'bi-exclamation-circle-fill' },
        error:   { bg:'bg-red-500',    icon:'bi-x-circle-fill' },
    };
    const c = cfg[type] || cfg.error;
    const el = document.getElementById('toast');
    document.getElementById('toast-inner').className = `flex items-center gap-4 px-5 py-4 rounded-2xl shadow-2xl text-white ${c.bg}`;
    document.getElementById('toast-av').textContent   = name.charAt(0).toUpperCase();
    document.getElementById('toast-name').textContent = name;
    document.getElementById('toast-msg').textContent  = msg;
    document.getElementById('toast-icon').className   = `bi ${c.icon} text-2xl shrink-0`;

    clearTimeout(toastTimer);
    el.style.display = 'block';
    el.className = 'fixed bottom-8 left-1/2 z-50 pointer-events-none toast-in';
    void el.offsetWidth;
    toastTimer = setTimeout(() => {
        el.classList.replace('toast-in','toast-out');
        setTimeout(() => { el.style.display='none'; el.className='fixed bottom-8 left-1/2 z-50 pointer-events-none'; }, 300);
    }, 3500);
}

/* ─── Helpers ────────────────────────────────────────────────────────────────── */
function show(id)     { document.getElementById(id).classList.remove('hidden'); }
function showFlex(id) { const el=document.getElementById(id); el.classList.remove('hidden'); el.style.display='flex'; }

/* ─── Auto-start ─────────────────────────────────────────────────────────────── */
window.addEventListener('load', () => {
    if (!navigator.mediaDevices?.getUserMedia) {
        const isHttp = location.protocol === 'http:' && !['localhost','127.0.0.1'].includes(location.hostname);
        document.getElementById('ph-title').textContent = 'Kamera Tidak Dapat Diakses';
        document.getElementById('ph-sub').textContent   = isHttp
            ? 'Kamera membutuhkan HTTPS. Gunakan ngrok atau aktifkan SSL.'
            : 'Browser tidak mendukung kamera.';
        document.getElementById('btn-cam').disabled = true;
        return;
    }
    startCamera();
});

window.addEventListener('beforeunload', () => {
    if (mediaStream) mediaStream.getTracks().forEach(t => t.stop());
});
</script>

<?php require_once $basePath . 'includes/footer.php'; ?>
