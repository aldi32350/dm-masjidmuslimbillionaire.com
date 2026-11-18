<?php
// ==============================================================================
// TASKFLOW PRO v3.4 — FITUR TAMBAHAN: Teks Siap Salin (EOD) & Bot Telegram
// FILE       : lib.php  (di-include oleh api.php dan telegram.php)
// ==============================================================================

// ========== MIGRASI TABEL/KOLOM BARU ==========
function runFeatureMigrations() {
    $db = getDB();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `app_settings` (
            `skey` VARCHAR(64) NOT NULL,
            `svalue` TEXT DEFAULT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`skey`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) { /* abaikan */ }

    $newCols = [
        'telegram_chat_id'   => "BIGINT DEFAULT NULL",
        'telegram_link_code' => "VARCHAR(16) DEFAULT NULL",
        'eod_unit'           => "VARCHAR(150) DEFAULT NULL",
        'eod_target'         => "TEXT DEFAULT NULL",
    ];
    foreach ($newCols as $col => $def) {
        try {
            $exists = $db->query("SHOW COLUMNS FROM users LIKE '$col'")->fetchAll();
            if (empty($exists)) $db->exec("ALTER TABLE users ADD COLUMN `$col` $def");
        } catch (Exception $e) { /* abaikan */ }
    }
}

// ========== PENGATURAN (key-value) ==========
function getSetting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (getDB()->query("SELECT skey, svalue FROM app_settings")->fetchAll() as $r) $cache[$r['skey']] = $r['svalue'];
        } catch (Exception $e) { /* tabel belum ada */ }
    }
    if (func_num_args() === 3) { $cache[$key] = func_get_arg(2); return $cache[$key]; } // update cache internal
    return array_key_exists($key, $cache) && $cache[$key] !== null ? $cache[$key] : $default;
}

function setSetting($key, $value) {
    getDB()->prepare("INSERT INTO app_settings (skey, svalue, updated_at) VALUES (?, ?, NOW())
                      ON DUPLICATE KEY UPDATE svalue = VALUES(svalue), updated_at = NOW()")->execute([$key, $value]);
    getSetting($key, '', $value);
}

function randomToken($len = 32) {
    return substr(bin2hex(random_bytes((int)ceil($len / 2))), 0, $len);
}

// Teks dari DB disimpan ter-escape (htmlspecialchars), kembalikan ke teks biasa
function plain($s) {
    return html_entity_decode((string)$s, ENT_QUOTES, 'UTF-8');
}

// ========== DATA USER ==========
function getUserRowById($id) {
    $stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$id]);
    return $stmt->fetch() ?: null;
}

function getUserRowByName($nama) {
    $stmt = getDB()->prepare("SELECT * FROM users WHERE nama_lengkap = ? LIMIT 1");
    $stmt->execute([$nama]);
    return $stmt->fetch() ?: null;
}

// Bentuk array "session user" dari baris tabel users (dipakai getVisibleNames dll.)
function sessionShape($row) {
    return [
        'db_id' => $row['id'],
        'id' => $row['user_id'],
        'username' => $row['username'],
        'nama' => $row['nama_lengkap'],
        'email' => $row['email'] ?? '',
        'posisi' => $row['posisi'] ?? '',
        'role' => $row['role'],
        'supervisor_id' => $row['supervisor_id'] ?? null,
    ];
}

// Apakah $cur (session user) boleh melihat/mengelola data milik $targetRow
function canAccessUser($cur, $targetRow) {
    if (!$targetRow) return false;
    $visible = getVisibleNames($cur);
    return $visible === null || in_array($targetRow['nama_lengkap'], $visible);
}

// ========== PERUBAHAN STATUS TUGAS (dipakai web & bot) ==========
// Mengembalikan ['success'=>bool, 'message'=>string]
function applyTaskStatus($task, $newStatus, $cur) {
    $statusOrder = ['Belum Dimulai' => 0, 'Sedang Diproses' => 1, 'Selesai' => 2];
    if (!isset($statusOrder[$newStatus])) return ['success' => false, 'message' => 'Status tidak valid!'];

    $visibleNames = getVisibleNames($cur);
    if ($visibleNames !== null && !in_array($task['assignee'], $visibleNames)) {
        return ['success' => false, 'message' => 'Anda tidak memiliki akses untuk mengubah status tugas ini!'];
    }
    $oldLevel = $statusOrder[$task['status']] ?? 0;
    $newLevel = $statusOrder[$newStatus];
    if ($newLevel < $oldLevel && $cur['role'] !== 'Kepala Divisi') {
        return ['success' => false, 'message' => 'Tugas tidak bisa dikembalikan ke fase sebelumnya. Hanya Kepala Divisi yang dapat memundurkan status.'];
    }

    $tglSelesai = null;
    $waktuMulai = $task['waktu_mulai'] ?? null;
    $durasiKerja = $task['durasi_kerja'] ?? null;

    if ($newStatus === 'Sedang Diproses' && $task['status'] !== 'Sedang Diproses') {
        $waktuMulai = date('Y-m-d H:i:s');
        $durasiKerja = null;
    }
    if ($newStatus === 'Selesai') {
        $tglSelesai = date('Y-m-d H:i:s');
        if ($waktuMulai) $durasiKerja = strtotime($tglSelesai) - strtotime($waktuMulai);
    }
    if ($newStatus === 'Belum Dimulai') {
        $waktuMulai = null;
        $durasiKerja = null;
    }

    getDB()->prepare("UPDATE tasks SET status=?, waktu_mulai=?, durasi_kerja=?, tgl_selesai=? WHERE task_id=?")
        ->execute([$newStatus, $waktuMulai, $durasiKerja, $tglSelesai, $task['task_id']]);
    logActivity($cur['nama'], 'UPDATE STATUS', 'Status "' . $task['judul'] . '" → ' . $newStatus);
    return ['success' => true, 'message' => 'Status berhasil diubah!'];
}

function getTaskById($taskId) {
    $stmt = getDB()->prepare("SELECT * FROM tasks WHERE task_id = ? LIMIT 1");
    $stmt->execute([$taskId]);
    return $stmt->fetch() ?: null;
}

// ========== TANGGAL INDONESIA ==========
function tanggalIndo($ymd, $withDay = true) {
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($ymd);
    $s = date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    return $withDay ? $hari[(int)date('w', $ts)] . ', ' . $s : $s;
}

function validDate($s) {
    return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s) !== false;
}

// ========== TEKS SIAP SALIN (EOD) ==========
// Buang penomoran lama ("1. ", "2) ", "- ") supaya bisa dinomori ulang
function cleanListLines($text) {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
        $line = trim(preg_replace('/^\s*(\d+[\.\)]|[-•*])\s*/u', '', $line));
        if ($line !== '') $out[] = $line;
    }
    return $out;
}

function numbered($lines) {
    $out = [];
    foreach (array_values($lines) as $i => $l) $out[] = ($i + 1) . '. ' . $l;
    return implode("\n", $out);
}

// Daftar tugas untuk laporan EOD seseorang pada tanggal tertentu
function getEodTasks($nama, $date, $includeProgress = true) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tasks WHERE assignee = ? AND status = 'Selesai' AND DATE(tgl_selesai) = ? ORDER BY tgl_selesai ASC, urutan ASC");
    $stmt->execute([$nama, $date]);
    $done = $stmt->fetchAll();
    $progress = [];
    if ($includeProgress) {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE assignee = ? AND status = 'Sedang Diproses' ORDER BY urutan ASC, tgl_dibuat ASC");
        $stmt->execute([$nama]);
        $progress = $stmt->fetchAll();
    }
    return ['done' => $done, 'progress' => $progress];
}

// Target perpekan: pakai template tersimpan; kalau kosong, ambil tugas Mingguan minggu ini
function getEodTargets($userRow, $date) {
    $lines = cleanListLines(plain($userRow['eod_target'] ?? ''));
    if ($lines) return $lines;
    $ts = strtotime($date);
    $monday = date('Y-m-d', strtotime('monday this week', $ts));
    $sunday = date('Y-m-d', strtotime('sunday this week', $ts));
    $stmt = getDB()->prepare("SELECT judul FROM tasks WHERE assignee = ? AND tipe = 'Mingguan' AND batas_waktu BETWEEN ? AND ? ORDER BY urutan ASC");
    $stmt->execute([$userRow['nama_lengkap'], $monday, $sunday]);
    return array_map(fn($r) => plain($r['judul']), $stmt->fetchAll());
}

function buildEodText($userRow, $date, $opts = []) {
    $includeProgress = $opts['include_progress'] ?? true;
    $unit = trim(plain($opts['unit'] ?? ($userRow['eod_unit'] ?? '')));
    if ($unit === '') $unit = trim(plain($userRow['posisi'] ?? ''));
    $targets = isset($opts['target']) ? cleanListLines($opts['target']) : getEodTargets($userRow, $date);

    $t = getEodTasks($userRow['nama_lengkap'], $date, $includeProgress);
    $work = [];
    foreach ($t['done'] as $row) $work[] = plain($row['judul']);
    foreach ($t['progress'] as $row) $work[] = plain($row['judul']) . ' (proses)';

    $problem = trim((string)($opts['problem'] ?? ''));
    $solusi = trim((string)($opts['solusi'] ?? ''));

    $txt = "EOD - " . plain($userRow['nama_lengkap']) . "\n";
    if ($unit !== '') $txt .= $unit . "\n";
    $txt .= tanggalIndo($date) . "\n";
    $txt .= "Target perpekan : \n" . ($targets ? numbered($targets) : '-') . "\n\n";
    $txt .= "Yang dikerjakan Hari ini :\n" . ($work ? numbered($work) : '-') . "\n\n";
    $txt .= "Problem\n" . ($problem !== '' ? $problem . "\n" : '') . "\n";
    $txt .= "Solusi :\n" . ($solusi !== '' ? $solusi . "\n" : '');
    return [
        'text' => rtrim($txt) . "\n",
        'done_count' => count($t['done']),
        'progress_count' => count($t['progress']),
        'unit' => $unit,
        'target' => implode("\n", $targets),
    ];
}

// ========== TELEGRAM ==========
function tgToken() { return trim(getSetting('tg_bot_token', '')); }
function tgEnabled() { return tgToken() !== ''; }

function tgApi($method, $params = [], $timeout = 6) {
    $token = tgToken();
    if ($token === '') return ['ok' => false, 'description' => 'Token bot belum diisi'];
    $base = getenv('TG_API_BASE') ?: 'https://api.telegram.org'; // TG_API_BASE hanya untuk pengujian lokal
    $ch = curl_init("{$base}/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'description' => 'Gagal menghubungi Telegram: ' . $err];
    $json = json_decode($res, true);
    return is_array($json) ? $json : ['ok' => false, 'description' => 'Respon Telegram tidak valid'];
}

function tgEsc($s) { return htmlspecialchars(plain($s), ENT_QUOTES, 'UTF-8'); }

// Kirim pesan (HTML). Pesan panjang dipecah per 3800 karakter.
function tgSend($chatId, $html, $extra = []) {
    if (!$chatId || !tgEnabled()) return ['ok' => false];
    $last = ['ok' => false];
    $chunks = mb_strlen($html) > 3800 ? tgSplit($html, 3800) : [$html];
    foreach ($chunks as $i => $chunk) {
        $params = array_merge(['chat_id' => $chatId, 'text' => $chunk, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true],
            $i === count($chunks) - 1 ? $extra : []);
        $last = tgApi('sendMessage', $params);
    }
    return $last;
}

// Kirim teks polos (tanpa format) — untuk teks EOD supaya mudah disalin
function tgSendPlain($chatId, $text, $extra = []) {
    if (!$chatId || !tgEnabled()) return ['ok' => false];
    $last = ['ok' => false];
    foreach (tgSplit($text, 3800) as $chunk) $last = tgApi('sendMessage', array_merge(['chat_id' => $chatId, 'text' => $chunk], $extra));
    return $last;
}

function tgSplit($text, $max) {
    $out = []; $buf = '';
    foreach (explode("\n", $text) as $line) {
        if (mb_strlen($buf) + mb_strlen($line) + 1 > $max && $buf !== '') { $out[] = $buf; $buf = ''; }
        $buf .= ($buf === '' ? '' : "\n") . $line;
    }
    if ($buf !== '') $out[] = $buf;
    return $out ?: [''];
}

function tgTaskButtons($task) {
    $row = [];
    if ($task['status'] === 'Belum Dimulai') $row[] = ['text' => '▶️ Mulai', 'callback_data' => 's:' . $task['task_id']];
    if ($task['status'] !== 'Selesai') $row[] = ['text' => '✅ Selesai', 'callback_data' => 'd:' . $task['task_id']];
    return $row ? ['reply_markup' => ['inline_keyboard' => [$row]]] : [];
}

function tgTaskLine($t, $withAssignee = false) {
    $icon = $t['status'] === 'Selesai' ? '✅' : ($t['status'] === 'Sedang Diproses' ? '⏳' : '⬜');
    $late = ($t['status'] !== 'Selesai' && $t['batas_waktu'] && $t['batas_waktu'] < date('Y-m-d')) ? ' ⚠️<i>terlambat</i>' : '';
    $who = $withAssignee ? ' — ' . tgEsc($t['assignee']) : '';
    return "{$icon} <b>" . tgEsc($t['judul']) . "</b>{$who}\n     <code>" . tgEsc($t['task_id']) . "</code> · {$t['prioritas']} · deadline " . date('d/m', strtotime($t['batas_waktu'])) . $late;
}

// ---------- Notifikasi otomatis dari aplikasi web ----------
function notifyTaskAssigned($taskId, $actorName) {
    try {
        if (!tgEnabled() || getSetting('tg_notify_assign', '1') !== '1') return;
        $task = getTaskById($taskId);
        if (!$task) return;
        $u = getUserRowByName($task['assignee']);
        if (!$u || empty($u['telegram_chat_id']) || $u['nama_lengkap'] === $actorName) return;
        $msg = "📌 <b>Tugas baru untuk Anda</b>\n\n" . tgTaskLine($task) . "\n\nDari: " . tgEsc($actorName);
        if (trim(plain($task['deskripsi'] ?? '')) !== '') $msg .= "\n\n" . tgEsc($task['deskripsi']);
        tgSend($u['telegram_chat_id'], $msg, tgTaskButtons($task));
    } catch (Exception $e) { /* notifikasi tidak boleh mengganggu aplikasi */ }
}

function notifyTaskStatus($taskId, $newStatus, $actorName) {
    try {
        if (!tgEnabled() || $newStatus !== 'Selesai') return;
        $task = getTaskById($taskId);
        if (!$task) return;
        $msg = "✅ <b>" . tgEsc($task['assignee']) . "</b> menyelesaikan tugas:\n" . tgEsc($task['judul']);
        if (!empty($task['durasi_kerja'])) $msg .= "\n⏱ Durasi: " . fmtDurasi((int)$task['durasi_kerja']);
        if ($actorName !== $task['assignee']) $msg .= "\n(ditandai oleh " . tgEsc($actorName) . ")";

        $sent = [];
        if (getSetting('tg_notify_done_supervisor', '1') === '1') {
            $u = getUserRowByName($task['assignee']);
            if ($u && $u['supervisor_id']) {
                $sup = getUserRowById($u['supervisor_id']);
                if ($sup && !empty($sup['telegram_chat_id']) && $sup['nama_lengkap'] !== $actorName) {
                    tgSend($sup['telegram_chat_id'], $msg);
                    $sent[] = $sup['telegram_chat_id'];
                }
            }
        }
        $group = trim(getSetting('tg_group_chat_id', ''));
        if ($group !== '' && getSetting('tg_notify_done_group', '0') === '1' && !in_array($group, $sent)) tgSend($group, $msg);
    } catch (Exception $e) { /* abaikan */ }
}

function fmtDurasi($sec) {
    $h = intdiv($sec, 3600); $m = intdiv($sec % 3600, 60);
    if ($h > 0) return "{$h} jam {$m} menit";
    return max(1, $m) . " menit";
}

// URL dasar aplikasi (untuk webhook)
function appBaseUrl() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}
