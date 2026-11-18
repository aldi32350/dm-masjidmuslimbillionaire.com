<?php
// ==============================================================================
// TASKFLOW PRO v3.0 — REST API BACKEND
// FILE       : api.php
// Hierarki: Kepala Divisi > Kepala Unit > Staff
// ==============================================================================

require_once 'config.php';
require_once 'lib.php';
try { runFeatureMigrations(); } catch (Exception $e) { /* DB belum siap */ }

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // =================== AUTH ===================
    case 'login':
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$username || !$password) {
            jsonResponse(['success' => false, 'message' => 'Username dan password wajib diisi!']);
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) jsonResponse(['success' => false, 'message' => 'Username atau password salah!']);
        if (!verifyPwd($password, $user['password_hash'])) jsonResponse(['success' => false, 'message' => 'Username atau password salah!']);
        if ($user['status'] !== 'Aktif') jsonResponse(['success' => false, 'message' => 'Akun dinonaktifkan. Hubungi atasan.']);
        $userData = [
            'db_id' => $user['id'],
            'id' => $user['user_id'],
            'username' => $user['username'],
            'nama' => $user['nama_lengkap'],
            'email' => $user['email'],
            'posisi' => $user['posisi'] ?? '',
            'role' => $user['role'],
            'supervisor_id' => $user['supervisor_id']
        ];
        $_SESSION['tfp_user'] = $userData;
        logActivity($userData['nama'], 'LOGIN', 'Login berhasil');
        jsonResponse(['success' => true, 'loggedIn' => true, 'user' => $userData]);
        break;

    case 'logout':
        $user = getSessionUser();
        if ($user) logActivity($user['nama'], 'LOGOUT', 'User logout');
        unset($_SESSION['tfp_user']);
        session_destroy();
        jsonResponse(['success' => true]);
        break;

    case 'get_current_user':
        if (isLoggedIn()) {
            jsonResponse(['success' => true, 'loggedIn' => true, 'user' => getSessionUser()]);
        }
        jsonResponse(['success' => true, 'loggedIn' => false]);
        break;

    case 'change_password':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Sesi tidak valid.']);
        $cur = getSessionUser();
        $oldPwd = $_POST['old_password'] ?? '';
        $newPwd = $_POST['new_password'] ?? '';
        if (strlen($newPwd) < 6) jsonResponse(['success' => false, 'message' => 'Password baru minimal 6 karakter!']);
        $db = getDB();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$cur['db_id']]);
        $row = $stmt->fetch();
        if (!$row || !verifyPwd($oldPwd, $row['password_hash'])) {
            jsonResponse(['success' => false, 'message' => 'Password lama salah!']);
        }
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([hashPwd($newPwd), $cur['db_id']]);
        logActivity($cur['nama'], 'GANTI PASSWORD', 'Password berhasil diganti');
        jsonResponse(['success' => true, 'message' => 'Password berhasil diganti!']);
        break;

    // =================== GET ALL DATA ===================
    case 'get_all_data':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $db = getDB();
        $cur = getSessionUser();

        // Users/Personnel — all roles can see active users for assignee dropdown
        $users = $db->query("SELECT id, user_id, username, nama_lengkap, email, posisi, role, supervisor_id, status, tgl_dibuat FROM users ORDER BY tgl_dibuat DESC")->fetchAll();

        // Add supervisor name to each user
        $userMap = [];
        foreach ($users as $u) $userMap[$u['id']] = $u['nama_lengkap'];
        foreach ($users as &$u) {
            $u['supervisor_nama'] = isset($u['supervisor_id']) && isset($userMap[$u['supervisor_id']]) ? $userMap[$u['supervisor_id']] : '';
        }
        unset($u);

        // Tasks — filter based on role
        $allTasks = $db->query("SELECT * FROM tasks ORDER BY urutan ASC, tgl_dibuat DESC")->fetchAll();
        $visibleNames = getVisibleNames($cur);
        if ($visibleNames !== null) {
            $tasks = array_values(array_filter($allTasks, function($t) use ($visibleNames) {
                return in_array($t['assignee'], $visibleNames);
            }));
        } else {
            $tasks = $allTasks;
        }

        // Dashboard stats from visible tasks
        $totalTasks = count($tasks);
        $completedTasks = 0; $inProgressTasks = 0; $notStartedTasks = 0; $overdueTasks = 0;
        $taskByAssignee = [];
        $today = date('Y-m-d');
        foreach ($tasks as $t) {
            switch ($t['status']) {
                case 'Selesai': $completedTasks++; break;
                case 'Sedang Diproses': $inProgressTasks++; break;
                default: $notStartedTasks++; break;
            }
            $assignee = $t['assignee'];
            if ($assignee) {
                if (!isset($taskByAssignee[$assignee])) $taskByAssignee[$assignee] = ['total' => 0, 'selesai' => 0];
                $taskByAssignee[$assignee]['total']++;
                if ($t['status'] === 'Selesai') $taskByAssignee[$assignee]['selesai']++;
            }
            if ($t['status'] !== 'Selesai' && $t['batas_waktu'] && $t['batas_waktu'] < $today) $overdueTasks++;
        }
        $assigneeChart = [];
        foreach ($taskByAssignee as $name => $data) {
            $assigneeChart[] = ['name' => $name, 'total' => $data['total'], 'selesai' => $data['selesai']];
        }

        // Recent logs
        $logs = $db->query("SELECT * FROM activity_log ORDER BY waktu DESC LIMIT 10")->fetchAll();
        $recentLogs = [];
        foreach ($logs as $l) {
            $recentLogs[] = ['waktu' => formatDate($l['waktu']), 'user' => $l['user'], 'aksi' => $l['aksi'], 'detail' => $l['detail']];
        }

        // Goals for current period
        $goalsData = getGoalsForUser($cur, date('Y-m'));

        jsonResponse([
            'success' => true,
            'users' => $users,
            'tasks' => $tasks,
            'goals' => $goalsData,
            'dashboard' => [
                'summary' => [
                    'totalTasks' => $totalTasks,
                    'completedTasks' => $completedTasks,
                    'inProgressTasks' => $inProgressTasks,
                    'notStartedTasks' => $notStartedTasks,
                    'overdueTasks' => $overdueTasks,
                    'personnelCount' => count(array_filter($users, fn($u) => $u['status'] === 'Aktif'))
                ],
                'chartData' => ['assignee' => $assigneeChart],
                'recentActivities' => $recentLogs
            ]
        ]);
        break;

    // =================== DASHBOARD ===================
    case 'get_dashboard':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $db = getDB();
        $cur = getSessionUser();
        $allTasks = $db->query("SELECT * FROM tasks")->fetchAll();
        $visibleNames = getVisibleNames($cur);
        if ($visibleNames !== null) {
            $tasks = array_values(array_filter($allTasks, function($t) use ($visibleNames) {
                return in_array($t['assignee'], $visibleNames);
            }));
        } else {
            $tasks = $allTasks;
        }

        $totalTasks = count($tasks);
        $completedTasks = 0; $inProgressTasks = 0; $notStartedTasks = 0; $overdueTasks = 0;
        $taskByAssignee = [];
        $today = date('Y-m-d');
        foreach ($tasks as $t) {
            switch ($t['status']) {
                case 'Selesai': $completedTasks++; break;
                case 'Sedang Diproses': $inProgressTasks++; break;
                default: $notStartedTasks++; break;
            }
            $a = $t['assignee'];
            if ($a) {
                if (!isset($taskByAssignee[$a])) $taskByAssignee[$a] = ['total' => 0, 'selesai' => 0];
                $taskByAssignee[$a]['total']++;
                if ($t['status'] === 'Selesai') $taskByAssignee[$a]['selesai']++;
            }
            if ($t['status'] !== 'Selesai' && $t['batas_waktu'] && $t['batas_waktu'] < $today) $overdueTasks++;
        }
        $assigneeChart = [];
        foreach ($taskByAssignee as $n => $d) $assigneeChart[] = ['name' => $n, 'total' => $d['total'], 'selesai' => $d['selesai']];

        $logs = $db->query("SELECT * FROM activity_log ORDER BY waktu DESC LIMIT 10")->fetchAll();
        $recentLogs = [];
        foreach ($logs as $l) $recentLogs[] = ['waktu' => formatDate($l['waktu']), 'user' => $l['user'], 'aksi' => $l['aksi'], 'detail' => $l['detail']];

        jsonResponse([
            'success' => true,
            'data' => [
                'summary' => compact('totalTasks','completedTasks','inProgressTasks','notStartedTasks','overdueTasks'),
                'chartData' => ['assignee' => $assigneeChart],
                'recentActivities' => $recentLogs
            ]
        ]);
        break;

    // =================== TASKS CRUD ===================
    case 'get_tasks':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $allTasks = getDB()->query("SELECT * FROM tasks ORDER BY urutan ASC, tgl_dibuat DESC")->fetchAll();
        $visibleNames = getVisibleNames($cur);
        if ($visibleNames !== null) {
            $tasks = array_values(array_filter($allTasks, fn($t) => in_array($t['assignee'], $visibleNames)));
        } else {
            $tasks = $allTasks;
        }
        jsonResponse(['success' => true, 'data' => $tasks]);
        break;

    case 'save_task':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $taskId = sanitize($_POST['task_id'] ?? '');
        $judul = sanitize($_POST['judul'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        $tipe = sanitize($_POST['tipe'] ?? '');
        $assignee = sanitize($_POST['assignee'] ?? '');
        $prioritas = sanitize($_POST['prioritas'] ?? '');
        $batasWaktu = sanitize($_POST['batas_waktu'] ?? '');
        $status = sanitize($_POST['status'] ?? 'Belum Dimulai');

        if (!$judul) jsonResponse(['success' => false, 'message' => 'Judul wajib diisi!']);
        if (strlen($judul) > 200) jsonResponse(['success' => false, 'message' => 'Judul maks 200 karakter!']);
        if (!in_array($tipe, ['Harian','Mingguan','Bulanan'])) jsonResponse(['success' => false, 'message' => 'Tipe tidak valid!']);
        if (!$assignee) jsonResponse(['success' => false, 'message' => 'Assignee wajib diisi!']);
        if (!in_array($prioritas, ['Tinggi','Sedang','Rendah'])) jsonResponse(['success' => false, 'message' => 'Prioritas tidak valid!']);
        if (!$batasWaktu) jsonResponse(['success' => false, 'message' => 'Batas waktu wajib diisi!']);

        $db = getDB();
        if (empty($taskId)) {
            $newId = generateId('TSK');
            // Auto-set urutan: max+1
            $maxUrutan = $db->query("SELECT COALESCE(MAX(urutan),0)+1 as next_urutan FROM tasks")->fetch()['next_urutan'];
            $waktuMulai = $status === 'Sedang Diproses' ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare("INSERT INTO tasks (task_id, judul, deskripsi, tipe, assignee, prioritas, batas_waktu, status, waktu_mulai, urutan, tgl_dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$newId, $judul, $deskripsi, $tipe, $assignee, $prioritas, $batasWaktu, $status, $waktuMulai, $maxUrutan]);
            logActivity($cur['nama'], 'TAMBAH TUGAS', 'Menambah tugas: ' . $judul);
            notifyTaskAssigned($newId, $cur['nama']);
            jsonResponse(['success' => true, 'message' => 'Tugas berhasil ditambahkan!']);
        } else {
            $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
            $stmt->execute([$taskId]);
            $existing = $stmt->fetch();
            if (!$existing) jsonResponse(['success' => false, 'message' => 'Tugas tidak ditemukan.']);

            // Permission: Staff only own, Kepala Unit own+subordinates, Kepala Divisi all
            $visibleNames = getVisibleNames($cur);
            if ($visibleNames !== null && !in_array($existing['assignee'], $visibleNames)) {
                jsonResponse(['success' => false, 'message' => 'Anda tidak memiliki akses untuk mengedit tugas ini!']);
            }

            // === VALIDASI FASE SATU ARAH (save_task) ===
            $statusOrder = ['Belum Dimulai' => 0, 'Sedang Diproses' => 1, 'Selesai' => 2];
            $oldLevel = $statusOrder[$existing['status']] ?? 0;
            $newLevel = $statusOrder[$status] ?? 0;
            if ($newLevel < $oldLevel && $cur['role'] !== 'Kepala Divisi') {
                jsonResponse(['success' => false, 'message' => 'Tugas tidak bisa dikembalikan ke fase sebelumnya. Hanya Kepala Divisi yang dapat memundurkan status.']);
            }

            $tglSelesai = null;
            $waktuMulai = $existing['waktu_mulai'] ?? null;
            $durasiKerja = $existing['durasi_kerja'] ?? null;

            // Duration tracking logic
            if ($status === 'Sedang Diproses' && $existing['status'] !== 'Sedang Diproses') {
                $waktuMulai = date('Y-m-d H:i:s');
                $durasiKerja = null; // reset
            }
            if ($status === 'Selesai' && $existing['status'] !== 'Selesai') {
                $tglSelesai = date('Y-m-d H:i:s');
                if ($waktuMulai) {
                    $durasiKerja = strtotime($tglSelesai) - strtotime($waktuMulai);
                }
            } elseif ($status === 'Selesai') {
                $tglSelesai = $existing['tgl_selesai'];
            }
            if ($status === 'Belum Dimulai') {
                $waktuMulai = null;
                $durasiKerja = null;
            }

            $stmt = $db->prepare("UPDATE tasks SET judul=?, deskripsi=?, tipe=?, assignee=?, prioritas=?, batas_waktu=?, status=?, waktu_mulai=?, durasi_kerja=?, tgl_selesai=? WHERE task_id=?");
            $stmt->execute([$judul, $deskripsi, $tipe, $assignee, $prioritas, $batasWaktu, $status, $waktuMulai, $durasiKerja, $tglSelesai, $taskId]);
            logActivity($cur['nama'], 'UPDATE TUGAS', 'Memperbarui tugas: ' . $judul);
            if ($assignee !== $existing['assignee']) notifyTaskAssigned($taskId, $cur['nama']);
            if ($status === 'Selesai' && $existing['status'] !== 'Selesai') notifyTaskStatus($taskId, 'Selesai', $cur['nama']);
            jsonResponse(['success' => true, 'message' => 'Tugas berhasil diperbarui!']);
        }
        break;

    case 'delete_task':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $taskId = sanitize($_POST['task_id'] ?? '');
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task) jsonResponse(['success' => false, 'message' => 'Tugas tidak ditemukan.']);
        $visibleNames = getVisibleNames($cur);
        if ($visibleNames !== null && !in_array($task['assignee'], $visibleNames)) {
            jsonResponse(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus tugas ini!']);
        }
        $db->prepare("DELETE FROM tasks WHERE task_id = ?")->execute([$taskId]);
        logActivity($cur['nama'], 'HAPUS TUGAS', 'Menghapus tugas: ' . $task['judul']);
        jsonResponse(['success' => true, 'message' => 'Tugas berhasil dihapus!']);
        break;

    case 'update_task_status':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $taskId = sanitize($_POST['task_id'] ?? '');
        $newStatus = sanitize($_POST['status'] ?? '');
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM tasks WHERE task_id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        if (!$task) jsonResponse(['success' => false, 'message' => 'Tugas tidak ditemukan.']);
        $res = applyTaskStatus($task, $newStatus, $cur);
        if ($res['success'] && $task['status'] !== $newStatus) notifyTaskStatus($taskId, $newStatus, $cur['nama']);
        jsonResponse($res);
        break;

    // =================== TEKS SIAP SALIN (EOD) ===================
    case 'eod_get':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $target = getUserRowById($_POST['user_db_id'] ?? $cur['db_id']);
        if (!canAccessUser($cur, $target)) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        if (!validDate($tanggal)) $tanggal = date('Y-m-d');
        $opts = [
            'problem' => $_POST['problem'] ?? '',
            'solusi' => $_POST['solusi'] ?? '',
            'include_progress' => ($_POST['include_progress'] ?? '1') === '1',
        ];
        if (isset($_POST['unit'])) $opts['unit'] = $_POST['unit'];
        if (isset($_POST['target'])) $opts['target'] = $_POST['target'];
        $eod = buildEodText($target, $tanggal, $opts);
        $eod['saved_unit'] = plain($target['eod_unit'] ?: ($target['posisi'] ?? ''));
        $eod['saved_target'] = implode("\n", getEodTargets($target, $tanggal));
        jsonResponse(['success' => true, 'data' => $eod]);
        break;

    case 'eod_save_template':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $target = getUserRowById($_POST['user_db_id'] ?? $cur['db_id']);
        if (!canAccessUser($cur, $target)) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        $unit = mb_substr(trim($_POST['unit'] ?? ''), 0, 150);
        $tgt = implode("\n", cleanListLines($_POST['target'] ?? ''));
        getDB()->prepare("UPDATE users SET eod_unit = ?, eod_target = ? WHERE id = ?")->execute([$unit, $tgt, $target['id']]);
        logActivity($cur['nama'], 'UPDATE TEMPLATE EOD', 'Template EOD: ' . $target['nama_lengkap']);
        jsonResponse(['success' => true, 'message' => 'Unit & target perpekan disimpan!']);
        break;

    // =================== TELEGRAM: PER USER ===================
    case 'tg_link_info':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $row = getUserRowById($cur['db_id']);
        if (!tgEnabled()) jsonResponse(['success' => true, 'enabled' => false]);
        $code = $row['telegram_link_code'];
        if (!$code || ($_POST['regenerate'] ?? '') === '1') {
            $code = strtoupper(randomToken(8));
            getDB()->prepare("UPDATE users SET telegram_link_code = ? WHERE id = ?")->execute([$code, $row['id']]);
        }
        jsonResponse(['success' => true, 'enabled' => true, 'linked' => !empty($row['telegram_chat_id']),
            'code' => $code, 'bot_username' => getSetting('tg_bot_username', '')]);
        break;

    case 'tg_unlink':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $targetId = (int)($_POST['user_db_id'] ?? $cur['db_id']);
        if ($targetId !== (int)$cur['db_id'] && !isKepalaDivisi()) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        getDB()->prepare("UPDATE users SET telegram_chat_id = NULL, telegram_link_code = NULL WHERE id = ?")->execute([$targetId]);
        logActivity($cur['nama'], 'TELEGRAM', 'Memutus sambungan Telegram user #' . $targetId);
        jsonResponse(['success' => true, 'message' => 'Sambungan Telegram diputus.']);
        break;

    // =================== TELEGRAM: ADMIN (Kepala Divisi) ===================
    case 'tg_get_settings':
        if (!isKepalaDivisi()) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        if (getSetting('tg_cron_key', '') === '') setSetting('tg_cron_key', randomToken(24));
        $token = tgToken();
        $users = getDB()->query("SELECT id, nama_lengkap, role, posisi, status, telegram_chat_id IS NOT NULL AS linked FROM users ORDER BY nama_lengkap")->fetchAll();
        $info = null;
        if ($token !== '') {
            $wh = tgApi('getWebhookInfo', [], 5);
            $info = $wh['ok'] ? ['url' => $wh['result']['url'] ?? '', 'pending' => $wh['result']['pending_update_count'] ?? 0,
                'last_error' => $wh['result']['last_error_message'] ?? ''] : ['error' => $wh['description'] ?? 'Gagal'];
        }
        $base = appBaseUrl() . '/telegram.php?cron=' . getSetting('tg_cron_key');
        jsonResponse(['success' => true, 'data' => [
            'has_token' => $token !== '',
            'token_masked' => $token !== '' ? substr($token, 0, strpos($token, ':') ?: 4) . ':••••••' . substr($token, -4) : '',
            'bot_username' => getSetting('tg_bot_username', ''),
            'group_chat_id' => getSetting('tg_group_chat_id', ''),
            'notify_assign' => getSetting('tg_notify_assign', '1') === '1',
            'notify_done_supervisor' => getSetting('tg_notify_done_supervisor', '1') === '1',
            'notify_done_group' => getSetting('tg_notify_done_group', '0') === '1',
            'webhook' => $info,
            'webhook_target' => appBaseUrl() . '/telegram.php',
            'cron_pagi' => $base . '&job=pagi',
            'cron_sore' => $base . '&job=sore',
            'users' => $users,
        ]]);
        break;

    case 'tg_save_settings':
        if (!isKepalaDivisi()) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        $cur = getSessionUser();
        $newToken = trim($_POST['bot_token'] ?? '');
        if ($newToken !== '') {
            if (!preg_match('/^\d{5,}:[A-Za-z0-9_-]{30,}$/', $newToken)) jsonResponse(['success' => false, 'message' => 'Format token bot tidak valid. Contoh: 123456789:AAH...']);
            setSetting('tg_bot_token', $newToken);
            $me = tgApi('getMe');
            if (!$me['ok']) { setSetting('tg_bot_token', ''); jsonResponse(['success' => false, 'message' => 'Token ditolak Telegram: ' . ($me['description'] ?? '')]); }
            setSetting('tg_bot_username', $me['result']['username'] ?? '');
        }
        $group = trim($_POST['group_chat_id'] ?? '');
        if ($group !== '' && !preg_match('/^-?\d+$/', $group)) jsonResponse(['success' => false, 'message' => 'Chat ID grup harus berupa angka (contoh: -1001234567890).']);
        setSetting('tg_group_chat_id', $group);
        foreach (['notify_assign', 'notify_done_supervisor', 'notify_done_group'] as $k) setSetting('tg_' . $k, ($_POST[$k] ?? '0') === '1' ? '1' : '0');
        logActivity($cur['nama'], 'TELEGRAM', 'Pengaturan bot Telegram diperbarui');
        jsonResponse(['success' => true, 'message' => 'Pengaturan bot tersimpan!']);
        break;

    case 'tg_set_webhook':
        if (!isKepalaDivisi()) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        if (!tgEnabled()) jsonResponse(['success' => false, 'message' => 'Isi token bot terlebih dahulu.']);
        if (getSetting('tg_webhook_secret', '') === '') setSetting('tg_webhook_secret', randomToken(32));
        $url = appBaseUrl() . '/telegram.php';
        if (strpos($url, 'https://') !== 0) jsonResponse(['success' => false, 'message' => 'Webhook Telegram wajib HTTPS. Buka aplikasi lewat https:// lalu coba lagi.']);
        $r = tgApi('setWebhook', ['url' => $url, 'secret_token' => getSetting('tg_webhook_secret'),
            'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => true]);
        if (!$r['ok']) jsonResponse(['success' => false, 'message' => 'Gagal memasang webhook: ' . ($r['description'] ?? '')]);
        tgApi('setMyCommands', ['commands' => [
            ['command' => 'tugas', 'description' => 'Daftar tugas saya yang belum selesai'],
            ['command' => 'eod', 'description' => 'Teks laporan EOD hari ini'],
            ['command' => 'tim', 'description' => 'Ringkasan kerja tim (Kepala)'],
            ['command' => 'terlambat', 'description' => 'Tugas yang lewat deadline'],
            ['command' => 'baru', 'description' => 'Beri tugas: /baru Nama | Judul | YYYY-MM-DD'],
            ['command' => 'bantuan', 'description' => 'Daftar perintah'],
        ]]);
        logActivity(getSessionUser()['nama'], 'TELEGRAM', 'Webhook bot dipasang: ' . $url);
        jsonResponse(['success' => true, 'message' => 'Webhook terpasang! Bot siap menerima perintah.']);
        break;

    case 'tg_test':
        if (!isKepalaDivisi()) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        $cur = getSessionUser();
        $to = ($_POST['to'] ?? 'me') === 'group' ? getSetting('tg_group_chat_id', '') : (getUserRowById($cur['db_id'])['telegram_chat_id'] ?? '');
        if (!$to) jsonResponse(['success' => false, 'message' => ($_POST['to'] ?? '') === 'group' ? 'Chat ID grup belum diisi.' : 'Akun Anda belum terhubung ke Telegram.']);
        $r = tgSend($to, "🔔 <b>Tes dari TaskFlow Pro</b>\nBot sudah tersambung dengan benar. Dikirim oleh " . tgEsc($cur['nama']) . ".");
        jsonResponse($r['ok'] ? ['success' => true, 'message' => 'Pesan tes terkirim!'] : ['success' => false, 'message' => 'Gagal kirim: ' . ($r['description'] ?? '')]);
        break;

    // =================== PERSONNEL MANAGEMENT ===================
    case 'get_users':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        // Kepala Divisi sees all, Kepala Unit sees subordinates + self
        $db = getDB();
        if ($cur['role'] === 'Kepala Divisi') {
            $users = $db->query("SELECT id, user_id, username, nama_lengkap, email, posisi, role, supervisor_id, status, tgl_dibuat FROM users ORDER BY tgl_dibuat DESC")->fetchAll();
        } elseif ($cur['role'] === 'Kepala Unit') {
            $stmt = $db->prepare("SELECT id, user_id, username, nama_lengkap, email, posisi, role, supervisor_id, status, tgl_dibuat FROM users WHERE id = ? OR supervisor_id = ? ORDER BY tgl_dibuat DESC");
            $stmt->execute([$cur['db_id'], $cur['db_id']]);
            $users = $stmt->fetchAll();
        } else {
            jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        }
        // Add supervisor name
        $allUsers = $db->query("SELECT id, nama_lengkap FROM users")->fetchAll();
        $nameMap = [];
        foreach ($allUsers as $u) $nameMap[$u['id']] = $u['nama_lengkap'];
        foreach ($users as &$u) {
            $u['supervisor_nama'] = isset($u['supervisor_id']) && isset($nameMap[$u['supervisor_id']]) ? $nameMap[$u['supervisor_id']] : '';
        }
        unset($u);
        jsonResponse(['success' => true, 'data' => $users]);
        break;

    case 'get_supervisors':
        // Get list of potential supervisors for dropdown
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $db = getDB();
        $supervisors = $db->query("SELECT id, nama_lengkap, role FROM users WHERE role IN ('Kepala Divisi','Kepala Unit') AND status = 'Aktif' ORDER BY nama_lengkap")->fetchAll();
        jsonResponse(['success' => true, 'data' => $supervisors]);
        break;

    case 'save_user':
        if (!canManagePersonnel()) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        $cur = getSessionUser();
        $userId = sanitize($_POST['user_id'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $namaLengkap = sanitize($_POST['nama_lengkap'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $posisi = sanitize($_POST['posisi'] ?? '');
        $role = sanitize($_POST['role'] ?? 'Staff');
        $supervisorId = $_POST['supervisor_id'] ?? '';
        $status = sanitize($_POST['status'] ?? 'Aktif');

        if (!$username) jsonResponse(['success' => false, 'message' => 'Username wajib diisi!']);
        if (!$namaLengkap) jsonResponse(['success' => false, 'message' => 'Nama wajib diisi!']);
        if (!in_array($role, ['Kepala Divisi','Kepala Unit','Staff'])) jsonResponse(['success' => false, 'message' => 'Role tidak valid!']);

        // Kepala Unit can only create Staff under themselves
        if ($cur['role'] === 'Kepala Unit') {
            $role = 'Staff';
            $supervisorId = $cur['db_id'];
        }

        $supId = !empty($supervisorId) ? (int)$supervisorId : null;

        $db = getDB();
        if (empty($userId)) {
            if (!$password || strlen($password) < 6) jsonResponse(['success' => false, 'message' => 'Password minimal 6 karakter!']);
            $check = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()['cnt'] > 0) jsonResponse(['success' => false, 'message' => 'Username sudah ada!']);
            $newId = generateId('USR');
            $stmt = $db->prepare("INSERT INTO users (user_id, username, password_hash, nama_lengkap, email, posisi, role, supervisor_id, status, tgl_dibuat) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$newId, $username, hashPwd($password), $namaLengkap, $email, $posisi, $role, $supId, $status]);
            logActivity($cur['nama'], 'TAMBAH PERSONIL', 'Menambah personil: ' . $namaLengkap . ' (' . $role . ')');
            jsonResponse(['success' => true, 'message' => 'Personil berhasil ditambahkan!']);
        } else {
            $stmt = $db->prepare("UPDATE users SET username=?, nama_lengkap=?, email=?, posisi=?, role=?, supervisor_id=?, status=? WHERE user_id=?");
            $stmt->execute([$username, $namaLengkap, $email, $posisi, $role, $supId, $status, $userId]);
            if ($password && strlen($password) >= 6) {
                $db->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([hashPwd($password), $userId]);
            }
            logActivity($cur['nama'], 'UPDATE PERSONIL', 'Memperbarui personil: ' . $namaLengkap);
            jsonResponse(['success' => true, 'message' => 'Personil berhasil diperbarui!']);
        }
        break;

    case 'delete_user':
        if (!canManagePersonnel()) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        $cur = getSessionUser();
        $userId = sanitize($_POST['user_id'] ?? '');
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) jsonResponse(['success' => false, 'message' => 'Personil tidak ditemukan.']);
        if ($u['role'] === 'Kepala Divisi') {
            $cnt = $db->query("SELECT COUNT(*) as cnt FROM users WHERE role='Kepala Divisi' AND status='Aktif'")->fetch()['cnt'];
            if ($cnt <= 1) jsonResponse(['success' => false, 'message' => 'Tidak bisa menghapus Kepala Divisi terakhir!']);
        }
        // Kepala Unit can only delete their subordinates
        if ($cur['role'] === 'Kepala Unit' && $u['supervisor_id'] != $cur['db_id']) {
            jsonResponse(['success' => false, 'message' => 'Anda hanya bisa menghapus anggota unit Anda!']);
        }
        $db->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
        logActivity($cur['nama'], 'HAPUS PERSONIL', 'Menghapus personil: ' . $u['nama_lengkap']);
        jsonResponse(['success' => true, 'message' => 'Personil berhasil dihapus!']);
        break;

    // =================== BACKUP ===================
    case 'backup_data':
        if (!isKepalaDivisi()) jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        logActivity('ADMIN', 'BACKUP', 'Backup data diminta');
        $db = getDB();
        $tables = ['users', 'tasks', 'activity_log'];
        $sql = "-- TaskFlow Pro v3.0 Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
        foreach ($tables as $table) {
            $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll();
            if (count($rows) > 0) {
                $sql .= "-- Data for table `{$table}`\n";
                foreach ($rows as $row) {
                    $vals = array_map(function($v) use ($db) {
                        return $v === null ? 'NULL' : $db->quote($v);
                    }, array_values($row));
                    $cols = implode('`, `', array_keys($row));
                    $sql .= "INSERT INTO `{$table}` (`{$cols}`) VALUES (" . implode(', ', $vals) . ");\n";
                }
                $sql .= "\n";
            }
        }
        jsonResponse(['success' => true, 'message' => 'Backup berhasil!', 'sql' => $sql]);
        break;

    // =================== EXPORT CSV ===================
    case 'export_csv':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $allTasks = getDB()->query("SELECT * FROM tasks ORDER BY tgl_dibuat DESC")->fetchAll();
        $visibleNames = getVisibleNames($cur);
        if ($visibleNames !== null) {
            $tasks = array_values(array_filter($allTasks, fn($t) => in_array($t['assignee'], $visibleNames)));
        } else {
            $tasks = $allTasks;
        }
        $csv = "ID,Judul,Deskripsi,Tipe,Assignee,Prioritas,Deadline,Status,Tgl Selesai\n";
        foreach ($tasks as $t) {
            $csv .= '"' . $t['task_id'] . '","' . str_replace('"','""',$t['judul']) . '","' . str_replace('"','""',$t['deskripsi'] ?? '') . '","' . $t['tipe'] . '","' . $t['assignee'] . '","' . $t['prioritas'] . '","' . $t['batas_waktu'] . '","' . $t['status'] . '","' . ($t['tgl_selesai'] ?? '') . "\"\n";
        }
        jsonResponse(['success' => true, 'csv' => $csv]);
        break;

    // =================== GOALS ===================
    case 'get_goals':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $periode = sanitize($_POST['periode'] ?? date('Y-m'));
        jsonResponse(['success' => true, 'data' => getGoalsForUser($cur, $periode)]);
        break;

    case 'get_team_members':
        // Kembalikan daftar anggota tim yang bisa dipilih sebagai target goal
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $db = getDB();
        if ($cur['role'] === 'Kepala Divisi') {
            // KD lihat semua user aktif kecuali dirinya
            $stmt = $db->prepare("SELECT id, user_id, nama_lengkap, role, posisi FROM users WHERE status = 'Aktif' AND id != ? ORDER BY nama_lengkap");
            $stmt->execute([$cur['db_id']]);
        } elseif ($cur['role'] === 'Kepala Unit') {
            // KU lihat bawahannya saja
            $stmt = $db->prepare("SELECT id, user_id, nama_lengkap, role, posisi FROM users WHERE supervisor_id = ? AND status = 'Aktif' ORDER BY nama_lengkap");
            $stmt->execute([$cur['db_id']]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Akses ditolak']);
        }
        jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'save_goal':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $db = getDB();
        $goalId = sanitize($_POST['goal_id'] ?? '');
        $judul = sanitize($_POST['judul'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        $strategi = sanitize($_POST['strategi'] ?? '');
        $scope = sanitize($_POST['scope'] ?? 'Personal');
        $periode = sanitize($_POST['periode'] ?? date('Y-m'));
        // assigned_to_id: goal tim yang dibuat oleh atasan untuk anggota tertentu
        $assignedToId = !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : null;

        if (!$judul) jsonResponse(['success' => false, 'message' => 'Judul goal wajib diisi!']);
        if (!in_array($scope, ['Divisi','Unit','Personal'])) jsonResponse(['success' => false, 'message' => 'Scope tidak valid!']);

        // Permission check
        if ($scope === 'Divisi' && $cur['role'] !== 'Kepala Divisi') {
            jsonResponse(['success' => false, 'message' => 'Hanya Kepala Divisi yang bisa mengatur Goal Divisi!']);
        }
        if ($scope === 'Unit' && !in_array($cur['role'], ['Kepala Divisi','Kepala Unit'])) {
            jsonResponse(['success' => false, 'message' => 'Hanya Kepala Unit/Divisi yang bisa mengatur Goal Unit!']);
        }

        // Jika ada assigned_to_id (goal tim), validasi akses
        $ownerId = $cur['db_id']; // default: pemilik = pembuat
        $createdById = null;
        if ($assignedToId && $scope === 'Personal') {
            // Cek apakah atasan berhak assign ke user tsb
            if ($cur['role'] === 'Kepala Divisi') {
                // KD bisa assign ke siapa saja
                $ownerId = $assignedToId;
                $createdById = $cur['db_id'];
            } elseif ($cur['role'] === 'Kepala Unit') {
                // KU cek apakah anggota tim bawahannya
                $checkStmt = $db->prepare("SELECT id FROM users WHERE id = ? AND supervisor_id = ?");
                $checkStmt->execute([$assignedToId, $cur['db_id']]);
                if (!$checkStmt->fetch()) jsonResponse(['success' => false, 'message' => 'Anggota tim tidak ditemukan atau bukan bawahan Anda!']);
                $ownerId = $assignedToId;
                $createdById = $cur['db_id'];
            } else {
                jsonResponse(['success' => false, 'message' => 'Hanya Kepala yang bisa membuat target untuk anggota tim!']);
            }
        }

        if ($goalId) {
            // Update existing
            $stmt = $db->prepare("SELECT * FROM goals WHERE goal_id = ? LIMIT 1");
            $stmt->execute([$goalId]);
            $existing = $stmt->fetch();
            if (!$existing) jsonResponse(['success' => false, 'message' => 'Goal tidak ditemukan!']);
            // Only owner, creator, or Kepala Divisi can edit
            $canEdit = ($existing['scope_owner_id'] == $cur['db_id'])
                    || ($existing['created_by_id'] == $cur['db_id'])
                    || ($cur['role'] === 'Kepala Divisi');
            if (!$canEdit) {
                jsonResponse(['success' => false, 'message' => 'Anda tidak berhak mengubah goal ini!']);
            }
            $stmt = $db->prepare("UPDATE goals SET judul=?, deskripsi=?, strategi=?, scope=?, periode=? WHERE goal_id=?");
            $stmt->execute([$judul, $deskripsi, $strategi, $scope, $periode, $goalId]);
            logActivity($cur['nama'], 'UPDATE GOAL', "Goal diperbarui: $judul ($scope)");
            jsonResponse(['success' => true, 'message' => 'Goal berhasil diperbarui!']);
        } else {
            // Create new
            $newId = generateId('GOAL');
            $stmt = $db->prepare("INSERT INTO goals (goal_id, judul, deskripsi, strategi, scope, scope_owner_id, created_by_id, periode) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$newId, $judul, $deskripsi, $strategi, $scope, $ownerId, $createdById, $periode]);
            $logDetail = $assignedToId && $createdById ? "Target tim oleh {$cur['nama']}: $judul" : "Goal baru: $judul ($scope)";
            logActivity($cur['nama'], 'TAMBAH GOAL', $logDetail);
            jsonResponse(['success' => true, 'message' => 'Goal berhasil ditambahkan!']);
        }
        break;

    case 'delete_goal':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $cur = getSessionUser();
        $db = getDB();
        $goalId = sanitize($_POST['goal_id'] ?? '');
        $stmt = $db->prepare("SELECT * FROM goals WHERE goal_id = ? LIMIT 1");
        $stmt->execute([$goalId]);
        $goal = $stmt->fetch();
        if (!$goal) jsonResponse(['success' => false, 'message' => 'Goal tidak ditemukan!']);
        $canDelete = ($goal['scope_owner_id'] == $cur['db_id'])
                  || ($goal['created_by_id'] == $cur['db_id'])
                  || ($cur['role'] === 'Kepala Divisi');
        if (!$canDelete) {
            jsonResponse(['success' => false, 'message' => 'Anda tidak berhak menghapus goal ini!']);
        }
        $db->prepare("DELETE FROM goals WHERE goal_id = ?")->execute([$goalId]);
        logActivity($cur['nama'], 'HAPUS GOAL', "Goal dihapus: {$goal['judul']}");
        jsonResponse(['success' => true, 'message' => 'Goal berhasil dihapus!']);
        break;

    // =================== REORDER TASKS (Kanban) ===================
    case 'reorder_tasks':
        if (!isLoggedIn()) jsonResponse(['success' => false, 'message' => 'Belum login']);
        $orderJson = $_POST['order'] ?? '[]';
        $order = json_decode($orderJson, true);
        if (!is_array($order) || empty($order)) {
            jsonResponse(['success' => false, 'message' => 'Data urutan tidak valid!']);
        }
        $db = getDB();
        $stmt = $db->prepare("UPDATE tasks SET urutan = ? WHERE task_id = ?");
        foreach ($order as $item) {
            if (isset($item['task_id']) && isset($item['urutan'])) {
                $stmt->execute([(int)$item['urutan'], $item['task_id']]);
            }
        }
        jsonResponse(['success' => true, 'message' => 'Urutan berhasil disimpan!']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Action tidak valid: ' . $action]);
}

// =================== HELPER: GET GOALS FOR USER ===================
function getGoalsForUser($user, $periode) {
    $db = getDB();
    // Ambil semua goals beserta info pemilik dan pembuat
    $stmt = $db->prepare("
        SELECT g.*,
               u.nama_lengkap as owner_nama,
               u.role as owner_role,
               cb.nama_lengkap as creator_nama,
               cb.role as creator_role
        FROM goals g
        JOIN users u ON g.scope_owner_id = u.id
        LEFT JOIN users cb ON g.created_by_id = cb.id
        WHERE g.periode = ?
        ORDER BY FIELD(g.scope,'Divisi','Unit','Personal'), g.tgl_dibuat DESC
    ");
    $stmt->execute([$periode]);
    $allGoals = $stmt->fetchAll();

    // Ambil daftar ID bawahan untuk KU
    $subIds = [];
    if ($user['role'] === 'Kepala Unit') {
        $stmt2 = $db->prepare("SELECT id FROM users WHERE supervisor_id = ?");
        $stmt2->execute([$user['db_id']]);
        $subIds = array_column($stmt2->fetchAll(), 'id');
    }

    $filtered = [];
    foreach ($allGoals as $g) {
        if ($g['scope'] === 'Divisi') {
            // Semua user lihat goal Divisi
            $filtered[] = $g;
        } elseif ($g['scope'] === 'Unit') {
            if ($user['role'] === 'Kepala Divisi') {
                $filtered[] = $g; // KD lihat semua unit goal
            } elseif ($user['role'] === 'Kepala Unit' && $g['scope_owner_id'] == $user['db_id']) {
                $filtered[] = $g; // KU lihat goal unit miliknya
            } elseif ($user['role'] === 'Staff') {
                // Staff lihat unit goal dari supervisornya
                if ($g['scope_owner_id'] == $user['supervisor_id']) {
                    $filtered[] = $g;
                }
            }
        } elseif ($g['scope'] === 'Personal') {
            if ($user['role'] === 'Kepala Divisi') {
                $filtered[] = $g; // KD lihat semua personal goal
            } elseif ($g['scope_owner_id'] == $user['db_id']) {
                $filtered[] = $g; // Goal milik sendiri
            } elseif ($user['role'] === 'Kepala Unit') {
                // KU lihat personal goal bawahannya
                if (in_array($g['scope_owner_id'], $subIds)) {
                    $filtered[] = $g;
                }
            } elseif ($user['role'] === 'Staff') {
                // Staff lihat juga goal yang dibuat atasan untuknya (created_by != null)
                if ($g['scope_owner_id'] == $user['db_id']) {
                    $filtered[] = $g;
                }
            }
        }
    }
    return $filtered;
}
