<?php
// ==============================================================================
// TASKFLOW PRO v3.0 — KONFIGURASI & HELPER
// FILE       : config.php
// ==============================================================================

date_default_timezone_set('Asia/Jakarta');
session_start();

// ========== KONFIGURASI DATABASE ==========
define('DB_HOST', 'localhost');
define('DB_NAME', 'u3470712_dm'); // Prefix 'u3470712_' dari username cPanel
define('DB_USER', 'u3470712_digitalmarketing');
define('DB_PASS', 'GANTI_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

// ========== KONFIGURASI APP ==========
define('APP_NAME', 'TaskFlow Pro');
define('APP_VERSION', '3.0.0');
define('SESSION_DURATION', 21600); // 6 jam

// ========== KONEKSI DATABASE (PDO) ==========
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ========== HELPER: GENERATE ID ==========
function generateId($prefix) {
    $date = date('Ymd');
    $rand = rand(1000, 9999);
    return "{$prefix}-{$date}-{$rand}";
}

// ========== HELPER: FORMAT TANGGAL ==========
function formatDate($date) {
    if (empty($date)) return '';
    return date('d/m/Y H:i', strtotime($date));
}

// ========== HELPER: HASH PASSWORD ==========
function hashPwd($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPwd($password, $hash) {
    return password_verify($password, $hash);
}

// ========== HELPER: LOG AKTIVITAS ==========
function logActivity($user, $action, $detail) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_log (waktu, user, aksi, detail) VALUES (NOW(), ?, ?, ?)");
        $stmt->execute([$user, $action, $detail]);
    } catch (Exception $e) {
        // Ignore log errors
    }
}

// ========== HELPER: CEK SESSION ==========
function getSessionUser() {
    return $_SESSION['tfp_user'] ?? null;
}

function isLoggedIn() {
    return isset($_SESSION['tfp_user']);
}

function isKepalaDivisi() {
    $user = getSessionUser();
    return $user && $user['role'] === 'Kepala Divisi';
}

function isKepalaUnit() {
    $user = getSessionUser();
    return $user && $user['role'] === 'Kepala Unit';
}

function isAdmin() {
    // Backward compat — Kepala Divisi = full admin
    return isKepalaDivisi();
}

function canManagePersonnel() {
    $user = getSessionUser();
    return $user && in_array($user['role'], ['Kepala Divisi', 'Kepala Unit']);
}

// ========== HELPER: GET SUBORDINATE NAMES ==========
function getSubordinateNames($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT nama_lengkap FROM users WHERE supervisor_id = ? AND status = 'Aktif'");
    $stmt->execute([$userId]);
    $names = [];
    foreach ($stmt->fetchAll() as $row) {
        $names[] = $row['nama_lengkap'];
    }
    return $names;
}

function getVisibleNames($user) {
    if ($user['role'] === 'Kepala Divisi') return null; // null = all
    if ($user['role'] === 'Kepala Unit') {
        $names = getSubordinateNames($user['db_id']);
        $names[] = $user['nama'];
        return $names;
    }
    return [$user['nama']]; // Staff: only self
}

// ========== HELPER: RESPONSE JSON ==========
function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== HELPER: SANITIZE INPUT ==========
function sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// ========== MIGRATION: v2 -> v3 ==========
function runMigrations() {
    $db = getDB();
    try {
        // Check if supervisor_id column exists
        $cols = $db->query("SHOW COLUMNS FROM users LIKE 'supervisor_id'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN `posisi` VARCHAR(100) DEFAULT '' AFTER `email`");
            $db->exec("ALTER TABLE users MODIFY COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'Staff'");
            $db->exec("ALTER TABLE users ADD COLUMN `supervisor_id` INT(11) DEFAULT NULL AFTER `role`");
            $db->exec("ALTER TABLE users ADD KEY `idx_supervisor` (`supervisor_id`)");
            // Migrate old roles
            $db->exec("UPDATE users SET role = 'Kepala Divisi' WHERE role = 'Admin'");
        }
        // Check if posisi column exists (in case migration was partial)
        $cols2 = $db->query("SHOW COLUMNS FROM users LIKE 'posisi'")->fetchAll();
        if (empty($cols2)) {
            $db->exec("ALTER TABLE users ADD COLUMN `posisi` VARCHAR(100) DEFAULT '' AFTER `email`");
        }
    } catch (Exception $e) {
        // Migration may already be done, ignore
    }
    // v3.1 — Goals table
    try {
        $tables = $db->query("SHOW TABLES LIKE 'goals'")->fetchAll();
        if (empty($tables)) {
            $db->exec("CREATE TABLE `goals` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `goal_id` VARCHAR(30) NOT NULL,
                `judul` VARCHAR(200) NOT NULL,
                `deskripsi` TEXT DEFAULT NULL,
                `strategi` TEXT DEFAULT NULL,
                `scope` ENUM('Divisi','Unit','Personal') NOT NULL DEFAULT 'Personal',
                `scope_owner_id` INT(11) NOT NULL,
                `created_by_id` INT(11) DEFAULT NULL,
                `periode` VARCHAR(7) NOT NULL,
                `tgl_dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `tgl_diubah` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_goal_id` (`goal_id`),
                KEY `idx_scope` (`scope`),
                KEY `idx_periode` (`periode`),
                KEY `idx_owner` (`scope_owner_id`),
                KEY `idx_created_by` (`created_by_id`),
                CONSTRAINT `fk_goal_owner` FOREIGN KEY (`scope_owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (Exception $e) {
        // Ignore
    }
    // v3.3 — Add created_by_id to goals (if table existed without it)
    try {
        $cols = $db->query("SHOW COLUMNS FROM goals LIKE 'created_by_id'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE `goals` ADD COLUMN `created_by_id` INT(11) DEFAULT NULL AFTER `scope_owner_id`");
            $db->exec("ALTER TABLE `goals` ADD KEY `idx_created_by` (`created_by_id`)");
        }
    } catch (Exception $e) {
        // Ignore — goals table may not exist yet, handled above
    }
    // v3.2 — Duration tracking & Kanban ordering
    try {
        $cols = $db->query("SHOW COLUMNS FROM tasks LIKE 'waktu_mulai'")->fetchAll();
        if (empty($cols)) {
            $db->exec("ALTER TABLE tasks ADD COLUMN `waktu_mulai` DATETIME DEFAULT NULL COMMENT 'Waktu mulai dikerjakan' AFTER `status`");
            $db->exec("ALTER TABLE tasks ADD COLUMN `durasi_kerja` INT(11) DEFAULT NULL COMMENT 'Total durasi dalam detik' AFTER `waktu_mulai`");
            $db->exec("ALTER TABLE tasks ADD COLUMN `urutan` INT(11) NOT NULL DEFAULT 0 COMMENT 'Urutan di kanban' AFTER `durasi_kerja`");
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// ========== SETUP: BUAT DEFAULT ADMIN ==========
function setupDefaultAdmin() {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE username = 'admin'");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row['cnt'] == 0) {
        $stmt = $db->prepare("INSERT INTO users (user_id, username, password_hash, nama_lengkap, email, posisi, role, status, tgl_dibuat) VALUES (?, 'admin', ?, 'Administrator', '', 'Kepala Divisi', 'Kepala Divisi', 'Aktif', NOW())");
        $stmt->execute([generateId('USR'), hashPwd('admin123')]);
    }
}

// Auto-run on every request
try {
    runMigrations();
    setupDefaultAdmin();
} catch (Exception $e) {
    // Database mungkin belum diimport
}
