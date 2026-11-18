<?php
// ==============================================================================
// TASKFLOW PRO v3.4 — WEBHOOK & JADWAL BOT TELEGRAM
// FILE       : telegram.php
//  - POST dari Telegram (webhook)   → perintah & tombol bot
//  - GET ?cron=KEY&job=pagi|sore    → pengingat terjadwal (Cron Jobs cPanel)
// ==============================================================================

require_once 'config.php';
require_once 'lib.php';
try { runFeatureMigrations(); } catch (Exception $e) { }

header('Content-Type: application/json; charset=utf-8');

// ---------------- CRON ----------------
if (isset($_GET['cron'])) {
    $key = getSetting('tg_cron_key', '');
    if ($key === '' || !hash_equals($key, (string)$_GET['cron'])) { http_response_code(403); echo '{"ok":false}'; exit; }
    if (!tgEnabled()) { echo json_encode(['ok' => false, 'message' => 'Bot belum diatur']); exit; }
    $job = $_GET['job'] ?? '';
    $sent = $job === 'pagi' ? cronPagi() : ($job === 'sore' ? cronSore() : -1);
    echo json_encode(['ok' => $sent >= 0, 'job' => $job, 'sent' => $sent]);
    exit;
}

// ---------------- WEBHOOK ----------------
$secret = getSetting('tg_webhook_secret', '');
if ($secret === '' || !hash_equals($secret, $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '')) {
    http_response_code(403);
    echo '{"ok":false}';
    exit;
}
$update = json_decode(file_get_contents('php://input'), true) ?: [];
echo '{"ok":true}';
// Balas Telegram secepatnya, lanjut proses
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

try {
    if (isset($update['callback_query'])) handleCallback($update['callback_query']);
    elseif (isset($update['message']['text'])) handleMessage($update['message']);
} catch (Exception $e) {
    error_log('[telegram] ' . $e->getMessage());
}
exit;

// =============================================================================
function linkedUser($tgUserId) {
    $stmt = getDB()->prepare("SELECT * FROM users WHERE telegram_chat_id = ? AND status = 'Aktif' LIMIT 1");
    $stmt->execute([(int)$tgUserId]);
    return $stmt->fetch() ?: null;
}

function isKepala($row) { return in_array($row['role'], ['Kepala Divisi', 'Kepala Unit']); }

function helpText($row = null) {
    $t = "🤖 <b>Bot TaskFlow Pro</b>\n\n"
       . "/tugas — tugas saya yang belum selesai (ada tombol Mulai/Selesai)\n"
       . "/mulai <code>ID</code> — tandai tugas sedang dikerjakan\n"
       . "/selesai <code>ID</code> — tandai tugas selesai\n"
       . "/eod — teks laporan EOD hari ini, siap salin\n"
       . "/eod <code>2026-11-17</code> — EOD tanggal tertentu\n"
       . "/terlambat — tugas yang lewat deadline\n";
    if (!$row || isKepala($row)) {
        $t .= "\n<b>Khusus Kepala:</b>\n"
            . "/tim — ringkasan kerja tim hari ini\n"
            . "/baru <code>Nama | Judul | 2026-11-20</code> — beri tugas\n"
            . "   (opsional: <code>| Tinggi/Sedang/Rendah | Harian/Mingguan/Bulanan</code>)\n";
    }
    $t .= "\n/id — tampilkan Chat ID (untuk pengaturan grup)";
    return $t;
}

function handleMessage($m) {
    $chatId = $m['chat']['id'];
    $fromId = $m['from']['id'] ?? 0;
    $isPrivate = ($m['chat']['type'] ?? '') === 'private';
    $text = trim($m['text']);
    if ($text === '' || $text[0] !== '/') {
        if ($isPrivate) tgSend($chatId, "Ketik /bantuan untuk melihat daftar perintah.");
        return;
    }
    $parts = preg_split('/\s+/', $text, 2);
    $cmd = strtolower(preg_replace('/@.*$/', '', $parts[0]));
    $arg = trim($parts[1] ?? '');

    if ($cmd === '/id') {
        tgSend($chatId, "Chat ID: <code>{$chatId}</code>" . ($isPrivate ? '' : "\nSalin angka ini ke menu <b>Bot Telegram</b> → Chat ID Grup."));
        return;
    }

    if ($cmd === '/start' || $cmd === '/link') {
        if ($arg === '') {
            $u = linkedUser($fromId);
            tgSend($chatId, $u ? "Assalamu'alaikum <b>" . tgEsc($u['nama_lengkap']) . "</b>! Akun Anda sudah terhubung.\n\n" . helpText($u)
                : "Assalamu'alaikum! Untuk menghubungkan akun, buka aplikasi TaskFlow Pro → menu profil (kanan atas) → <b>Hubungkan Telegram</b>, lalu tekan tombolnya.");
            return;
        }
        if (!$isPrivate) { tgSend($chatId, "Kirim kode penghubung lewat chat pribadi ke bot, bukan di grup."); return; }
        $stmt = getDB()->prepare("SELECT * FROM users WHERE telegram_link_code = ? AND status = 'Aktif' LIMIT 1");
        $stmt->execute([strtoupper($arg)]);
        $u = $stmt->fetch();
        if (!$u) { tgSend($chatId, "❌ Kode tidak valid atau sudah dipakai. Buat kode baru dari aplikasi."); return; }
        $db = getDB();
        $db->prepare("UPDATE users SET telegram_chat_id = NULL WHERE telegram_chat_id = ?")->execute([(int)$fromId]);
        $db->prepare("UPDATE users SET telegram_chat_id = ?, telegram_link_code = NULL WHERE id = ?")->execute([(int)$fromId, $u['id']]);
        logActivity($u['nama_lengkap'], 'TELEGRAM', 'Akun terhubung ke Telegram');
        tgSend($chatId, "✅ Terhubung sebagai <b>" . tgEsc($u['nama_lengkap']) . "</b> (" . tgEsc($u['role']) . ").\n\n" . helpText($u));
        return;
    }

    $u = linkedUser($fromId);
    if (!$u) {
        tgSend($chatId, "Akun Telegram Anda belum terhubung. Buka aplikasi TaskFlow Pro → profil → <b>Hubungkan Telegram</b>.");
        return;
    }
    $cur = sessionShape($u);

    switch ($cmd) {
        case '/bantuan': case '/help':
            tgSend($chatId, helpText($u));
            break;

        case '/tugas':
            $stmt = getDB()->prepare("SELECT * FROM tasks WHERE assignee = ? AND status <> 'Selesai' ORDER BY (batas_waktu < CURDATE()) DESC, batas_waktu ASC, urutan ASC LIMIT 15");
            $stmt->execute([$u['nama_lengkap']]);
            $tasks = $stmt->fetchAll();
            if (!$tasks) { tgSend($chatId, "🎉 Tidak ada tugas yang belum selesai. Barakallahu fiik!"); break; }
            tgSend($chatId, "📋 <b>Tugas Anda (" . count($tasks) . ")</b>");
            foreach ($tasks as $t) tgSend($chatId, tgTaskLine($t), tgTaskButtons($t));
            break;

        case '/mulai': case '/selesai':
            $task = getTaskById(strtoupper($arg));
            if (!$task) { tgSend($chatId, "Tugas tidak ditemukan. Contoh: <code>{$cmd} TSK-20261117-1234</code>\nLihat ID lewat /tugas"); break; }
            $new = $cmd === '/mulai' ? 'Sedang Diproses' : 'Selesai';
            $r = applyTaskStatus($task, $new, $cur);
            tgSend($chatId, ($r['success'] ? '✅ ' : '❌ ') . tgEsc($r['message']) . "\n" . tgEsc($task['judul']));
            if ($r['success'] && $task['status'] !== $new) notifyTaskStatus($task['task_id'], $new, $u['nama_lengkap']);
            break;

        case '/eod':
            $date = validDate($arg) ? $arg : date('Y-m-d');
            $eod = buildEodText($u, $date);
            tgSendPlain($chatId, $eod['text']);
            if ($isPrivate) tgSend($chatId, "☝️ Tekan lama pesan di atas → <b>Salin</b>. Isi Problem &amp; Solusi sebelum dikirim.");
            break;

        case '/terlambat':
            $visible = getVisibleNames($cur);
            $rows = getDB()->query("SELECT * FROM tasks WHERE status <> 'Selesai' AND batas_waktu < CURDATE() ORDER BY batas_waktu ASC")->fetchAll();
            $rows = array_values(array_filter($rows, fn($t) => $visible === null || in_array($t['assignee'], $visible)));
            if (!$rows) { tgSend($chatId, "👍 Tidak ada tugas yang terlambat."); break; }
            $lines = array_map(fn($t) => tgTaskLine($t, isKepala($u)), array_slice($rows, 0, 30));
            tgSend($chatId, "⚠️ <b>Tugas terlambat (" . count($rows) . ")</b>\n\n" . implode("\n", $lines));
            break;

        case '/tim':
            if (!isKepala($u)) { tgSend($chatId, "Perintah ini khusus Kepala Divisi / Kepala Unit."); break; }
            tgSend($chatId, teamSummary($cur, date('Y-m-d')));
            break;

        case '/baru':
            if (!isKepala($u)) { tgSend($chatId, "Perintah ini khusus Kepala Divisi / Kepala Unit."); break; }
            tgSend($chatId, createTaskFromBot($cur, $arg));
            break;

        default:
            tgSend($chatId, "Perintah tidak dikenal. " . helpText($u));
    }
}

function handleCallback($cb) {
    $u = linkedUser($cb['from']['id'] ?? 0);
    $data = $cb['data'] ?? '';
    if (!$u) { tgApi('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'Akun belum terhubung', 'show_alert' => true]); return; }
    [$op, $taskId] = array_pad(explode(':', $data, 2), 2, '');
    $task = getTaskById($taskId);
    if (!$task || !in_array($op, ['s', 'd'])) { tgApi('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'Tugas tidak ditemukan']); return; }
    $new = $op === 's' ? 'Sedang Diproses' : 'Selesai';
    if ($task['status'] === $new) { tgApi('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'Status sudah ' . $new]); return; }
    $r = applyTaskStatus($task, $new, sessionShape($u));
    tgApi('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => $r['success'] ? "✅ $new" : $r['message'], 'show_alert' => !$r['success']]);
    if ($r['success']) {
        $fresh = getTaskById($taskId);
        if (isset($cb['message']['message_id'])) {
            tgApi('editMessageText', array_merge([
                'chat_id' => $cb['message']['chat']['id'], 'message_id' => $cb['message']['message_id'],
                'text' => tgTaskLine($fresh), 'parse_mode' => 'HTML',
            ], tgTaskButtons($fresh) ?: ['reply_markup' => ['inline_keyboard' => []]]));
        }
        notifyTaskStatus($taskId, $new, $u['nama_lengkap']);
    }
}

// Ringkasan tim: per orang — selesai hari ini, sedang proses, belum mulai, terlambat
function teamSummary($cur, $date) {
    $visible = getVisibleNames($cur);
    $people = getDB()->query("SELECT nama_lengkap FROM users WHERE status = 'Aktif' ORDER BY nama_lengkap")->fetchAll(PDO::FETCH_COLUMN);
    if ($visible !== null) $people = array_values(array_intersect($people, $visible));
    $tasks = getDB()->query("SELECT assignee, status, batas_waktu, tgl_selesai FROM tasks")->fetchAll();
    $stat = [];
    foreach ($people as $p) $stat[$p] = ['done' => 0, 'proses' => 0, 'belum' => 0, 'telat' => 0];
    foreach ($tasks as $t) {
        if (!isset($stat[$t['assignee']])) continue;
        $s = &$stat[$t['assignee']];
        if ($t['status'] === 'Selesai') { if (substr((string)$t['tgl_selesai'], 0, 10) === $date) $s['done']++; }
        else {
            $t['status'] === 'Sedang Diproses' ? $s['proses']++ : $s['belum']++;
            if ($t['batas_waktu'] && $t['batas_waktu'] < $date) $s['telat']++;
        }
        unset($s);
    }
    $lines = [];
    $idle = [];
    foreach ($stat as $p => $s) {
        if ($s['done'] === 0 && $s['proses'] === 0) $idle[] = tgEsc($p);
        $lines[] = "• <b>" . tgEsc($p) . "</b>: ✅{$s['done']} ⏳{$s['proses']} ⬜{$s['belum']}" . ($s['telat'] ? " ⚠️{$s['telat']}" : '');
    }
    $msg = "👥 <b>Ringkasan Tim — " . tanggalIndo($date) . "</b>\n<i>✅ selesai hari ini · ⏳ proses · ⬜ belum mulai · ⚠️ terlambat</i>\n\n" . implode("\n", $lines ?: ['(belum ada anggota)']);
    if ($idle) $msg .= "\n\n💤 Belum ada aktivitas: " . implode(', ', $idle);
    return $msg;
}

function createTaskFromBot($cur, $arg) {
    $p = array_map('trim', explode('|', $arg));
    if (count($p) < 3 || $p[0] === '' || $p[1] === '') {
        return "Format: <code>/baru Nama | Judul tugas | 2026-11-20</code>\nOpsional: <code>| Tinggi | Harian</code>";
    }
    [$nama, $judul, $deadline] = $p;
    $prioritas = ucfirst(strtolower($p[3] ?? 'Sedang'));
    $tipe = ucfirst(strtolower($p[4] ?? 'Harian'));
    if (!validDate($deadline)) return "❌ Deadline harus format YYYY-MM-DD, contoh 2026-11-20.";
    if (!in_array($prioritas, ['Tinggi', 'Sedang', 'Rendah'])) return "❌ Prioritas harus Tinggi, Sedang, atau Rendah.";
    if (!in_array($tipe, ['Harian', 'Mingguan', 'Bulanan'])) return "❌ Tipe harus Harian, Mingguan, atau Bulanan.";
    if (mb_strlen($judul) > 200) return "❌ Judul maksimal 200 karakter.";

    // Cari anggota: nama persis, lalu awalan nama
    $visible = getVisibleNames($cur);
    $all = getDB()->query("SELECT nama_lengkap FROM users WHERE status = 'Aktif'")->fetchAll(PDO::FETCH_COLUMN);
    if ($visible !== null) $all = array_values(array_intersect($all, $visible));
    $match = array_values(array_filter($all, fn($n) => mb_strtolower($n) === mb_strtolower($nama)));
    if (!$match) $match = array_values(array_filter($all, fn($n) => mb_stripos($n, $nama) === 0));
    if (count($match) !== 1) {
        return count($match) > 1 ? "❌ Nama \"" . tgEsc($nama) . "\" cocok dengan beberapa orang: " . tgEsc(implode(', ', $match)) . ". Tulis lebih lengkap."
                                 : "❌ Anggota \"" . tgEsc($nama) . "\" tidak ditemukan di tim Anda.";
    }
    $assignee = $match[0];
    $db = getDB();
    $newId = generateId('TSK');
    $urutan = $db->query("SELECT COALESCE(MAX(urutan),0)+1 FROM tasks")->fetchColumn();
    $db->prepare("INSERT INTO tasks (task_id, judul, deskripsi, tipe, assignee, prioritas, batas_waktu, status, waktu_mulai, urutan, tgl_dibuat) VALUES (?,?,?,?,?,?,?,'Belum Dimulai',NULL,?,NOW())")
       ->execute([$newId, sanitize($judul), '', $tipe, $assignee, $prioritas, $deadline, $urutan]);
    logActivity($cur['nama'], 'TAMBAH TUGAS', 'Menambah tugas (Telegram): ' . sanitize($judul));
    notifyTaskAssigned($newId, $cur['nama']);
    return "✅ Tugas dibuat untuk <b>" . tgEsc($assignee) . "</b>\n" . tgTaskLine(getTaskById($newId));
}

// ---------------- JADWAL ----------------
// Pagi: tiap orang dapat daftar tugasnya; grup dapat ringkasan tim
function cronPagi() {
    $sent = 0;
    $today = date('Y-m-d');
    $users = getDB()->query("SELECT * FROM users WHERE status = 'Aktif' AND telegram_chat_id IS NOT NULL")->fetchAll();
    foreach ($users as $u) {
        $stmt = getDB()->prepare("SELECT * FROM tasks WHERE assignee = ? AND status <> 'Selesai' AND (batas_waktu <= ? OR status = 'Sedang Diproses') ORDER BY batas_waktu ASC LIMIT 20");
        $stmt->execute([$u['nama_lengkap'], $today]);
        $tasks = $stmt->fetchAll();
        $msg = "🌅 <b>Selamat pagi, " . tgEsc($u['nama_lengkap']) . "!</b>\n" . tanggalIndo($today) . "\n\n";
        $msg .= $tasks ? "Tugas hari ini &amp; yang tertunda:\n\n" . implode("\n", array_map('tgTaskLine', $tasks)) . "\n\nKetik /tugas untuk tombol Mulai/Selesai."
                       : "Belum ada tugas jatuh tempo hari ini. Semangat berkarya! 💪";
        if (tgSend($u['telegram_chat_id'], $msg)['ok'] ?? false) $sent++;
    }
    $group = trim(getSetting('tg_group_chat_id', ''));
    if ($group !== '') {
        $rows = getDB()->query("SELECT * FROM tasks WHERE status <> 'Selesai' AND batas_waktu < CURDATE() ORDER BY assignee, batas_waktu")->fetchAll();
        $msg = "🌅 <b>Briefing Pagi — " . tanggalIndo($today) . "</b>\n\n";
        $msg .= $rows ? "⚠️ Tugas terlambat (" . count($rows) . "):\n" . implode("\n", array_map(fn($t) => tgTaskLine($t, true), array_slice($rows, 0, 25)))
                      : "Tidak ada tugas yang terlambat. Alhamdulillah 👍";
        if (tgSend($group, $msg)['ok'] ?? false) $sent++;
    }
    return $sent;
}

// Sore: tiap orang dapat draf EOD; grup dapat rekap tim
function cronSore() {
    $sent = 0;
    $today = date('Y-m-d');
    $users = getDB()->query("SELECT * FROM users WHERE status = 'Aktif' AND telegram_chat_id IS NOT NULL")->fetchAll();
    foreach ($users as $u) {
        $eod = buildEodText($u, $today);
        tgSend($u['telegram_chat_id'], "🌇 <b>Waktunya laporan EOD.</b> Ini draf Anda — salin, lengkapi Problem &amp; Solusi, lalu kirim ke grup:");
        if (tgSendPlain($u['telegram_chat_id'], $eod['text'])['ok'] ?? false) $sent++;
    }
    $group = trim(getSetting('tg_group_chat_id', ''));
    if ($group !== '') {
        $admin = ['role' => 'Kepala Divisi', 'db_id' => 0, 'nama' => 'Bot'];
        if (tgSend($group, teamSummary($admin, $today))['ok'] ?? false) $sent++;
    }
    return $sent;
}
