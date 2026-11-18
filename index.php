<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaskFlow Pro - Monitoring Pekerjaan</title>
<meta name="description" content="TaskFlow Pro v3.0 - Sistem Monitoring Pekerjaan dengan Hierarki Organisasi">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Inter','sans-serif']},colors:{navy:{800:'#1e2235',900:'#161929',950:'#0f111d'},brand:{50:'#f0fdf4',100:'#dcfce7',500:'#22c55e',600:'#16a34a',700:'#15803d'}}}}}
</script>
<style>
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif}
::selection{background:#22c55e;color:#fff}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
.dark ::-webkit-scrollbar-thumb{background:#334155}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-20px)}}
@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
.animate-float{animation:float 6s ease-in-out infinite}
.animate-float-r{animation:float 8s ease-in-out infinite reverse}
.animate-slideUp{animation:slideUp .5s ease}
.animate-fadeIn{animation:fadeIn .35s ease}
.animate-spin-custom{animation:spin .8s linear infinite}
.glass{background:rgba(255,255,255,.95);box-shadow:0 1px 3px rgba(0,0,0,.04)}
.dark .glass{background:rgba(30,34,53,.95);box-shadow:0 1px 3px rgba(0,0,0,.2)}
.modal-backdrop{background:rgba(0,0,0,.45);backdrop-filter:blur(6px)}
.nav-link.active{background:rgba(34,197,94,.15)!important;color:#4ade80!important;border-left:3px solid #4ade80}
.dash-filter-btn{background:transparent;color:#94a3b8;border:1px solid #e2e8f0}
.dash-filter-btn:hover{background:#f1f5f9;color:#475569;border-color:#cbd5e1}
.dash-filter-btn.active{background:#22c55e!important;color:#fff!important;border-color:#22c55e!important;box-shadow:0 2px 8px rgba(34,197,94,.3)}
.dark .dash-filter-btn{border-color:#334155;color:#64748b}
.dark .dash-filter-btn:hover{background:#1e293b;color:#94a3b8}
.dark .dash-filter-btn.active{background:#22c55e!important;color:#fff!important;border-color:#22c55e!important}
.task-filter-btn{background:transparent;color:#94a3b8;border:1px solid #e2e8f0}
.task-filter-btn:hover{background:#f1f5f9;color:#475569;border-color:#cbd5e1}
.task-filter-btn.active{background:#22c55e!important;color:#fff!important;border-color:#22c55e!important;box-shadow:0 2px 8px rgba(34,197,94,.3)}
.dark .task-filter-btn{border-color:#334155;color:#64748b}
.dark .task-filter-btn:hover{background:#1e293b;color:#94a3b8}
.dark .task-filter-btn.active{background:#22c55e!important;color:#fff!important;border-color:#22c55e!important}
.goal-tab-btn{background:transparent;color:#94a3b8;border:1px solid #e2e8f0}
.goal-tab-btn:hover{background:#f1f5f9;color:#475569}
.goal-tab-btn.active{background:#22c55e!important;color:#fff!important;border-color:#22c55e!important;box-shadow:0 2px 8px rgba(34,197,94,.3)}
.dark .goal-tab-btn{border-color:#334155;color:#64748b}
.dark .goal-tab-btn.active{background:#22c55e!important;color:#fff!important;border-color:#22c55e!important}
.scope-btn{background:transparent;color:#94a3b8;border:1px solid #e2e8f0}
.scope-btn.active{background:#10b981!important;color:#fff!important;border-color:#10b981!important;box-shadow:0 2px 8px rgba(16,185,129,.3)}
.dark .scope-btn{border-color:#334155}.dark .scope-btn.active{background:#10b981!important;color:#fff!important}
.kanban-card{cursor:grab;user-select:none}
.kanban-card:active{cursor:grabbing}
.kanban-card.dragging{opacity:.4;transform:scale(.96);box-shadow:none!important}
.kanban-col.drag-over{background:rgba(34,197,94,.08)!important;border-color:rgba(34,197,94,.4)!important;box-shadow:inset 0 0 0 2px rgba(34,197,94,.2)}
.kanban-col.drag-over .kanban-drop-hint{display:flex}
.kanban-drop-hint{display:none;align-items:center;justify-content:center;padding:12px;border:2px dashed rgba(34,197,94,.3);border-radius:12px;color:#22c55e;font-size:12px;font-weight:600;margin-bottom:8px;animation:fadeIn .2s ease}
.kanban-card.sortable-ghost{opacity:.3;transform:scale(.95)}
.kanban-card.sortable-chosen{box-shadow:0 8px 25px rgba(34,197,94,.2);transform:scale(1.02);z-index:10}
.kanban-card.sortable-drag{box-shadow:0 12px 35px rgba(0,0,0,.15);transform:rotate(2deg)}
@keyframes pulse-timer{0%,100%{opacity:1}50%{opacity:.6}}
.live-timer{animation:pulse-timer 2s ease-in-out infinite}
.duration-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:999px}
@media print{.no-print{display:none!important}.sidebar-wrap{display:none!important}.main-content{margin-left:0!important}}
</style>
</head>
<body class="bg-gray-100 dark:bg-navy-950 text-slate-700 dark:text-slate-200 transition-colors duration-300 overflow-x-hidden">

<!-- LOADER -->
<div id="loader" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center gap-4 bg-white/80 dark:bg-navy-950/80 backdrop-blur-lg">
<div class="w-12 h-12 border-4 border-slate-200 dark:border-slate-700 border-t-brand-500 rounded-full animate-spin-custom"></div>
<span class="font-semibold text-slate-400">Memuat...</span></div>

<!-- LOGIN -->
<div id="loginPage" class="hidden min-h-screen flex items-center justify-center p-5 bg-slate-100">
<div class="animate-slideUp w-full max-w-[900px] bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row">
    <!-- LEFT PANEL -->
    <div class="relative w-full md:w-[45%] min-h-[200px] md:min-h-[540px] flex flex-col items-center justify-center text-center text-white overflow-hidden" style="background:linear-gradient(180deg,#22c55e 0%,#16a34a 40%,#15803d 100%)">
        <!-- Wave decoration -->
        <svg class="absolute bottom-0 left-0 w-full" viewBox="0 0 400 80" preserveAspectRatio="none" style="height:80px"><path d="M0,40 C50,80 100,0 150,40 C200,80 250,0 300,40 C350,80 400,0 400,40 L400,80 L0,80 Z" fill="rgba(255,255,255,0.08)"/></svg>
        <svg class="absolute bottom-0 left-0 w-full" viewBox="0 0 400 60" preserveAspectRatio="none" style="height:60px"><path d="M0,30 C60,60 120,0 180,30 C240,60 300,0 360,30 C390,50 400,20 400,30 L400,60 L0,60 Z" fill="rgba(255,255,255,0.05)"/></svg>
        <!-- Decorative circles -->
        <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full bg-white/[0.06]"></div>
        <div class="absolute -bottom-10 -left-10 w-40 h-40 rounded-full bg-white/[0.04]"></div>

        <div class="relative z-10 px-8 py-10">
            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-white flex items-center justify-center shadow-lg">
                <i class="bi bi-ui-checks-grid text-green-600 text-3xl"></i>
            </div>
            <h2 class="font-extrabold text-2xl md:text-3xl mb-3 leading-tight">&#1576;&#1616;&#1587;&#1618;&#1605;&#1616; &#1575;&#1604;&#1604;&#1617;&#1614;&#1607;&#1616; &#1575;&#1604;&#1585;&#1617;&#1614;&#1581;&#1618;&#1605;&#1614;&#1648;&#1606;&#1616; &#1575;&#1604;&#1585;&#1617;&#1614;&#1581;&#1616;&#1610;&#1605;&#1616;</h2>
            <p class="text-white/80 text-sm md:text-base mb-6 leading-relaxed max-w-[280px] mx-auto">"Dengan menyebut nama Allah Yang Maha Pengasih lagi Maha Penyayang"</p>
            <div class="w-12 h-0.5 bg-white/30 mx-auto mb-6 rounded-full"></div>
            <p class="text-white/60 text-xs leading-relaxed max-w-[260px] mx-auto">Monitoring pekerjaan tim Anda dengan lebih mudah, terstruktur, dan efisien. Semoga berkah! &#128640;</p>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="w-full md:w-[55%] flex flex-col items-center justify-center p-8 md:p-12">
        <div class="w-full max-w-[360px]">
            <h2 class="font-extrabold text-2xl md:text-3xl text-slate-800 mb-1">Masuk ke Akun</h2>
            <p class="text-slate-400 mb-8 text-sm">TaskFlow Pro &mdash; Sistem Monitoring Pekerjaan</p>
            <div id="loginError" class="hidden bg-red-50 border border-red-200 text-red-600 rounded-xl p-3 mb-4 text-sm"></div>
            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Username</label>
                    <input type="text" id="loginUser" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 placeholder-slate-300 focus:bg-white focus:border-green-500 focus:ring-2 focus:ring-green-500/20 outline-none transition text-sm" placeholder="Masukkan username" required>
                </div>
                <div class="mb-6">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" id="loginPass" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 pr-11 placeholder-slate-300 focus:bg-white focus:border-green-500 focus:ring-2 focus:ring-green-500/20 outline-none transition text-sm" placeholder="Masukkan password" required>
                        <button type="button" onclick="toggleLoginPassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition" tabindex="-1">
                            <i class="bi bi-eye text-lg" id="loginPassToggleIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" id="btnLogin" class="w-full py-3.5 rounded-xl font-semibold text-white bg-gradient-to-r from-green-600 to-green-500 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-green-500/30 transition-all duration-300 text-sm"><i class="bi bi-box-arrow-in-right mr-2"></i>Masuk</button>
            </form>
        </div>
    </div>
</div></div>

<!-- APP -->
<div id="appWrapper" class="hidden flex min-h-screen">
<!-- SIDEBAR -->
<nav id="sidebar" class="fixed top-0 left-0 h-full w-[240px] z-[1040] flex flex-col text-white transition-transform duration-300 -translate-x-[240px] md:translate-x-0 overflow-y-auto bg-navy-900 dark:bg-navy-950 border-r border-white/[0.06]">
    <div class="px-5 py-6"><div class="flex items-center gap-3"><div class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-emerald-400 flex items-center justify-center"><i class="bi bi-ui-checks-grid text-sm"></i></div><div><h4 class="font-bold text-sm leading-tight">TaskFlow Pro</h4><span class="text-[10px] text-slate-500">v3.0 Monitoring</span></div></div></div>
    <div class="px-3 mb-2"><span class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest px-3">Menu</span></div>
    <div class="flex-1 px-3 space-y-0.5" id="sidebarNav">
        <button onclick="switchView('dashboard',this)" class="nav-link active w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition text-[13px] font-medium text-left"><i class="bi bi-grid-1x2 text-base w-5 text-center"></i>Dashboard</button>
        <button onclick="switchView('tasks',this)" class="nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition text-[13px] font-medium text-left"><i class="bi bi-list-task text-base w-5 text-center"></i>Data Tugas</button>
        <button onclick="switchView('users',this)" class="nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition text-[13px] font-medium text-left hidden" id="navUsers"><i class="bi bi-people text-base w-5 text-center"></i>Manajemen Personil</button>
        <div class="pt-3 pb-1"><span class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest px-3">Sistem</span></div>
        <button onclick="handleBackup()" class="nav-link w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.06] transition text-[13px] font-medium text-left hidden" id="navBackup"><i class="bi bi-cloud-arrow-up text-base w-5 text-center"></i>Backup Data</button>
    </div>
    <div class="p-4 mx-3 mb-3 rounded-xl bg-gradient-to-br from-brand-600/20 to-brand-500/10 border border-brand-500/10">
        <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-500 to-emerald-400 flex items-center justify-center text-white font-bold text-sm" id="sideAvatar">A</div><div class="min-w-0"><div class="font-semibold text-sm truncate" id="sideName">Admin</div><div class="text-[11px] text-slate-400 truncate" id="sideRole">Kepala Divisi</div></div></div>
    </div>
</nav>
<div id="mobileOverlay" class="hidden fixed inset-0 bg-black/50 z-[1035]" onclick="toggleSidebar()"></div>

<!-- MAIN -->
<div id="mainContent" class="flex-1 md:ml-[240px] transition-all duration-300 min-w-0">
<!-- TOPBAR -->
<div class="bg-white/80 dark:bg-navy-900/80 backdrop-blur-lg border-b border-slate-200/60 dark:border-white/[0.06] px-4 md:px-6 py-3 flex justify-between items-center sticky top-0 z-[1030]">
    <div class="flex items-center gap-3">
        <button onclick="toggleSidebar()" class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-navy-800 transition md:hidden"><i class="bi bi-list text-xl"></i></button>
        <h6 class="font-bold text-base" id="pageTitle">Dashboard</h6>
        <span class="hidden md:inline-flex text-xs text-slate-400 bg-slate-100 dark:bg-navy-800 px-2.5 py-1 rounded-lg" id="todayDate"></span>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="toggleTheme()" class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-navy-800 transition" title="Toggle Tema"><i class="bi bi-moon-stars text-sm" id="themeIcon"></i></button>
        <div class="relative" id="userDropdown">
            <button onclick="document.getElementById('userMenu').classList.toggle('hidden')" class="flex items-center gap-2 px-2 py-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-navy-800 transition">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-brand-500 to-emerald-400 flex items-center justify-center text-white font-bold text-xs" id="userAvatar">A</div>
                <div class="hidden md:block leading-tight text-left"><div class="font-semibold text-xs" id="userName">User</div><div class="text-[11px] text-slate-400" id="userRole">-</div></div>
                <i class="bi bi-chevron-down text-[10px] text-slate-400"></i>
            </button>
            <div id="userMenu" class="hidden absolute right-0 top-12 w-52 bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl py-1 z-50">
                <a href="#" onclick="showPasswordModal();document.getElementById('userMenu').classList.add('hidden')" class="flex items-center gap-2 px-4 py-2.5 hover:bg-slate-50 dark:hover:bg-navy-900 text-sm transition"><i class="bi bi-key"></i>Ganti Password</a>
                <hr class="border-slate-100 dark:border-slate-700"><a href="#" onclick="handleLogout()" class="flex items-center gap-2 px-4 py-2.5 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-500 text-sm transition"><i class="bi bi-box-arrow-right"></i>Logout</a>
            </div>
        </div>
    </div>
</div>

<div class="p-4 md:p-6 max-w-[1400px] mx-auto">

<!-- ===== VIEW: DASHBOARD ===== -->
<div id="view-dashboard" class="app-view animate-fadeIn">
<div class="flex flex-wrap justify-between items-center mb-4 gap-2">
    <div><h4 class="font-bold text-lg">Dashboard</h4><p class="text-xs text-slate-400" id="dashSubtitle">Ringkasan aktivitas & performa</p></div>
    <div class="flex gap-2 no-print">
        <button onclick="window.print()" class="px-3 py-1.5 text-xs border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-navy-800 transition"><i class="bi bi-printer mr-1"></i>Cetak</button>
        <button onclick="loadDashboard()" class="px-3 py-1.5 text-xs bg-brand-500 text-white rounded-lg hover:bg-brand-600 hover:shadow-lg hover:shadow-brand-500/20 transition"><i class="bi bi-arrow-clockwise mr-1"></i>Refresh</button>
    </div>
</div>
<!-- Scope Toggle (Kepala Divisi & Kepala Unit) -->
<div id="scopeFilterWrap" class="hidden glass rounded-2xl p-3 border border-slate-200/60 dark:border-white/[0.06] mb-3 no-print">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mr-1"><i class="bi bi-eye mr-1"></i>Cakupan:</span>
        <button onclick="setDashScope('team')" class="scope-btn active px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-scope="team"><i class="bi bi-people mr-1"></i>Seluruh Tim</button>
        <button onclick="setDashScope('me')" class="scope-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-scope="me"><i class="bi bi-person mr-1"></i>Pekerjaan Saya</button>
    </div>
</div>
<!-- Date Filter Pills -->
<div class="glass rounded-2xl p-3 border border-slate-200/60 dark:border-white/[0.06] mb-5 no-print">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mr-1"><i class="bi bi-funnel mr-1"></i>Periode:</span>
        <button onclick="setDashFilter('today')" class="dash-filter-btn active px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-filter="today"><i class="bi bi-calendar-day mr-1"></i>Hari Ini</button>
        <button onclick="setDashFilter('yesterday')" class="dash-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-filter="yesterday"><i class="bi bi-calendar-minus mr-1"></i>Kemarin</button>
        <button onclick="setDashFilter('tomorrow')" class="dash-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-filter="tomorrow"><i class="bi bi-calendar-plus mr-1"></i>Besok</button>
        <button onclick="setDashFilter('week')" class="dash-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-filter="week"><i class="bi bi-calendar-week mr-1"></i>Minggu Ini</button>
        <button onclick="setDashFilter('month')" class="dash-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-filter="month"><i class="bi bi-calendar-month mr-1"></i>Bulan Ini</button>
        <button onclick="setDashFilter('all')" class="dash-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-filter="all"><i class="bi bi-calendar-range mr-1"></i>Semua</button>
        <div class="flex-1"></div>
        <span class="text-[11px] text-slate-400 bg-slate-100 dark:bg-navy-800 px-2.5 py-1 rounded-lg" id="dashFilterLabel"><i class="bi bi-calendar-day"></i> Hari Ini</span>
    </div>
</div>
<!-- Goal & Strategi Card -->
<div class="glass rounded-2xl border border-slate-200/60 dark:border-white/[0.06] mb-5 overflow-hidden" id="goalCardWrap">
    <div class="bg-gradient-to-r from-brand-600 to-emerald-500 px-5 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2 text-white">
            <i class="bi bi-bullseye text-lg"></i>
            <h6 class="font-bold text-sm">Goal & Strategi</h6>
            <span class="text-[11px] opacity-80 bg-white/20 px-2 py-0.5 rounded-full" id="goalPeriodeLabel"></span>
        </div>
        <div class="flex gap-2 no-print">
            <button onclick="showGoalModal()" class="px-3 py-1 text-xs bg-white/20 hover:bg-white/30 text-white rounded-lg transition" id="btnAddGoal"><i class="bi bi-plus-lg mr-1"></i>Tambah</button>
        </div>
    </div>
    <div class="px-5 pt-3 pb-1">
        <div class="flex flex-wrap gap-2 mb-2">
            <button onclick="setGoalTab('Divisi')" class="goal-tab-btn active px-3 py-1.5 text-xs font-medium rounded-lg transition-all" data-gtab="Divisi"><i class="bi bi-building mr-1"></i>Divisi</button>
            <button onclick="setGoalTab('Unit')" class="goal-tab-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-all" data-gtab="Unit"><i class="bi bi-people mr-1"></i>Unit</button>
            <button onclick="setGoalTab('Personal')" class="goal-tab-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-all" data-gtab="Personal"><i class="bi bi-person mr-1"></i>Personal</button>
            <button onclick="setGoalTab('Tim')" class="goal-tab-btn px-3 py-1.5 text-xs font-medium rounded-lg transition-all hidden" data-gtab="Tim" id="tabTimSaya"><i class="bi bi-people-fill mr-1"></i>Tim Saya</button>
        </div>
    </div>
    <div class="px-5 pb-4" id="goalContent">
        <p class="text-slate-400 text-sm py-4 text-center"><i class="bi bi-bullseye mr-1"></i>Belum ada goal untuk periode ini.</p>
    </div>
</div>
<!-- Stat Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <div class="glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06] hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 group">
        <div class="flex items-center justify-between mb-3"><span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Tugas</span><div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center text-blue-500 group-hover:scale-110 transition"><i class="bi bi-files"></i></div></div>
        <div class="text-3xl font-extrabold text-slate-800 dark:text-white" id="dashTotal">0</div>
        <div class="text-[11px] text-slate-400 mt-1"><i class="bi bi-arrow-up text-green-500"></i> Semua tugas tercatat</div>
    </div>
    <div class="glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06] hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 group">
        <div class="flex items-center justify-between mb-3"><span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Selesai</span><div class="w-9 h-9 rounded-xl bg-green-50 dark:bg-green-500/10 flex items-center justify-center text-green-500 group-hover:scale-110 transition"><i class="bi bi-check-circle"></i></div></div>
        <div class="text-3xl font-extrabold text-slate-800 dark:text-white" id="dashCompleted">0</div>
        <div class="text-[11px] text-slate-400 mt-1"><i class="bi bi-check2-all text-green-500"></i> Tugas selesai</div>
    </div>
    <div class="glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06] hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 group">
        <div class="flex items-center justify-between mb-3"><span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Diproses</span><div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center text-amber-500 group-hover:scale-110 transition"><i class="bi bi-hourglass-split"></i></div></div>
        <div class="text-3xl font-extrabold text-slate-800 dark:text-white" id="dashProgress">0</div>
        <div class="text-[11px] text-slate-400 mt-1"><i class="bi bi-play-circle text-amber-500"></i> Sedang dikerjakan</div>
    </div>
    <div class="glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06] hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 group">
        <div class="flex items-center justify-between mb-3"><span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Overdue</span><div class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-500/10 flex items-center justify-center text-red-500 group-hover:scale-110 transition"><i class="bi bi-exclamation-triangle"></i></div></div>
        <div class="text-3xl font-extrabold text-slate-800 dark:text-white" id="dashOverdue">0</div>
        <div class="text-[11px] text-slate-400 mt-1"><i class="bi bi-clock text-red-500"></i> Melewati deadline</div>
    </div>
</div>
<!-- Progress -->
<div class="glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06] mb-5"><div class="flex justify-between items-center"><span class="text-sm font-semibold">Progress Penyelesaian</span><span class="font-bold text-brand-500" id="progressPct">0%</span></div><div class="h-2.5 bg-slate-100 dark:bg-navy-800 rounded-full mt-3 overflow-hidden"><div class="h-full bg-gradient-to-r from-brand-600 to-brand-500 rounded-full transition-all duration-1000 ease-out" id="progressBar" style="width:0%"></div></div></div>
<!-- Quick Actions -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5 no-print">
    <div onclick="switchView('tasks',null);setTimeout(()=>showTaskModal(),200)" class="flex items-center gap-4 p-4 rounded-2xl glass border border-slate-200/60 dark:border-white/[0.06] cursor-pointer hover:border-brand-500/30 hover:-translate-y-0.5 hover:shadow-lg transition-all group"><div class="w-10 h-10 rounded-xl bg-brand-50 dark:bg-brand-500/10 flex items-center justify-center text-brand-500 group-hover:scale-110 transition"><i class="bi bi-plus-circle text-lg"></i></div><div><div class="font-semibold text-sm">Tugas Baru</div><div class="text-[11px] text-slate-400">Tambah tugas baru</div></div></div>
    <div onclick="switchView('tasks',null)" class="flex items-center gap-4 p-4 rounded-2xl glass border border-slate-200/60 dark:border-white/[0.06] cursor-pointer hover:border-amber-500/30 hover:-translate-y-0.5 hover:shadow-lg transition-all group"><div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center text-amber-500 group-hover:scale-110 transition"><i class="bi bi-kanban text-lg"></i></div><div><div class="font-semibold text-sm">Lihat Tugas</div><div class="text-[11px] text-slate-400">Data tugas & kanban</div></div></div>
    <div onclick="switchView('users',null)" id="quickPersonil" class="flex items-center gap-4 p-4 rounded-2xl glass border border-slate-200/60 dark:border-white/[0.06] cursor-pointer hover:border-green-500/30 hover:-translate-y-0.5 hover:shadow-lg transition-all group hidden"><div class="w-10 h-10 rounded-xl bg-green-50 dark:bg-green-500/10 flex items-center justify-center text-green-500 group-hover:scale-110 transition"><i class="bi bi-person-plus text-lg"></i></div><div><div class="font-semibold text-sm">Kelola Personil</div><div class="text-[11px] text-slate-400">Manajemen personil</div></div></div>
</div>
<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
    <div class="lg:col-span-2 glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06]"><div class="flex items-center justify-between mb-4"><h6 class="font-bold text-sm">Performa Tim</h6><span class="text-[11px] text-slate-400 bg-slate-100 dark:bg-navy-800 px-2 py-0.5 rounded-md" id="chartYear">2026</span></div><canvas id="teamChart" height="130"></canvas></div>
    <div class="glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06]"><h6 class="font-bold text-sm mb-4">Distribusi Status</h6><canvas id="statusChart" height="200"></canvas></div>
</div>
<!-- Recent Tasks & Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06]"><div class="flex items-center justify-between mb-4"><h6 class="font-bold text-sm">Tugas Terbaru</h6><button onclick="switchView('tasks',null)" class="text-[11px] text-brand-500 hover:underline">Lihat Semua â†’</button></div><div id="recentTasksList" class="space-y-2.5 max-h-72 overflow-y-auto"><p class="text-slate-400 text-sm">Memuat...</p></div></div>
    <div class="glass rounded-2xl p-5 border border-slate-200/60 dark:border-white/[0.06]"><div class="flex items-center justify-between mb-4"><h6 class="font-bold text-sm">Aktivitas Terkini</h6><span class="text-[11px] text-slate-400">Log terakhir</span></div><div id="activityLog" class="space-y-3 max-h-72 overflow-y-auto"><p class="text-slate-400 text-sm">Memuat...</p></div></div>
</div>
</div>

<!-- ===== VIEW: TASKS ===== -->
<div id="view-tasks" class="app-view hidden animate-fadeIn">
<div class="flex flex-wrap justify-between items-center mb-5 gap-2">
    <div><h4 class="font-bold text-lg">Manajemen Tugas</h4><p class="text-xs text-slate-400">Kelola dan pantau semua tugas</p></div>
    <div class="flex gap-2 no-print">
        <div class="flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden"><button id="btnTableView" onclick="setTaskView('table')" class="px-3 py-1.5 text-xs bg-brand-500 text-white transition"><i class="bi bi-table"></i></button><button id="btnKanbanView" onclick="setTaskView('kanban')" class="px-3 py-1.5 text-xs hover:bg-slate-100 dark:hover:bg-navy-800 transition"><i class="bi bi-kanban"></i></button></div>
        <button onclick="window.print()" class="px-3 py-1.5 text-xs border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-navy-800 transition"><i class="bi bi-printer"></i></button>
        <button onclick="exportData()" class="px-3 py-1.5 text-xs bg-green-500 text-white rounded-lg hover:bg-green-600 transition"><i class="bi bi-file-earmark-excel mr-1"></i>Export</button>
        <button onclick="showTaskModal()" class="px-3 py-1.5 text-xs bg-brand-500 text-white rounded-lg hover:bg-brand-600 hover:shadow-lg hover:shadow-brand-500/20 transition"><i class="bi bi-plus-lg mr-1"></i>Tambah</button>
    </div>
</div>
<div class="glass rounded-2xl p-4 mb-4 border border-slate-200/60 dark:border-white/[0.06] no-print">
    <div class="flex flex-wrap items-center gap-2 mb-3 pb-3 border-b border-slate-200/60 dark:border-slate-700/50">
        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mr-1"><i class="bi bi-funnel mr-1"></i>Periode:</span>
        <button onclick="setTaskDateFilter('today')" class="task-filter-btn active px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-tfilter="today"><i class="bi bi-calendar-day mr-1"></i>Hari Ini</button>
        <button onclick="setTaskDateFilter('tomorrow')" class="task-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-tfilter="tomorrow"><i class="bi bi-calendar-plus mr-1"></i>Besok</button>
        <button onclick="setTaskDateFilter('yesterday')" class="task-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-tfilter="yesterday"><i class="bi bi-calendar-minus mr-1"></i>Kemarin</button>
        <button onclick="setTaskDateFilter('week')" class="task-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-tfilter="week"><i class="bi bi-calendar-week mr-1"></i>Minggu Ini</button>
        <button onclick="setTaskDateFilter('month')" class="task-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-tfilter="month"><i class="bi bi-calendar-month mr-1"></i>Bulan Ini</button>
        <button onclick="setTaskDateFilter('all')" class="task-filter-btn px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all" data-tfilter="all"><i class="bi bi-calendar-range mr-1"></i>Semua</button>
        <div class="flex-1"></div>
        <span class="text-[11px] text-slate-400 bg-slate-100 dark:bg-navy-800 px-2.5 py-1 rounded-lg" id="taskFilterLabel"><i class="bi bi-calendar-day"></i> Hari Ini</span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
        <input type="text" id="searchTask" class="col-span-2 md:col-span-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition" placeholder="Cari tugas..." oninput="debounceSearch()">
        <select id="filterTipe" onchange="filterTasks()" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-3 py-2 text-sm focus:border-brand-500 outline-none"><option value="">Semua Tipe</option><option>Harian</option><option>Mingguan</option><option>Bulanan</option></select>
        <select id="filterStatus" onchange="filterTasks()" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-3 py-2 text-sm focus:border-brand-500 outline-none"><option value="">Semua Status</option><option>Belum Dimulai</option><option>Sedang Diproses</option><option>Selesai</option></select>
        <select id="filterAssignee" onchange="filterTasks()" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-3 py-2 text-sm focus:border-brand-500 outline-none"><option value="">Semua Assignee</option></select>
        <select id="filterPrioritas" onchange="filterTasks()" class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-3 py-2 text-sm focus:border-brand-500 outline-none"><option value="">Semua Prioritas</option><option>Tinggi</option><option>Sedang</option><option>Rendah</option></select>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mt-2 items-center">
        <div class="flex items-center gap-2 col-span-2 md:col-span-2"><span class="text-xs text-slate-400 whitespace-nowrap"><i class="bi bi-calendar-range mr-1"></i>Dari:</span><input type="date" id="filterDateFrom" onchange="clearTaskFilterPills();filterTasks()" class="flex-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-3 py-2 text-sm focus:border-brand-500 outline-none"></div>
        <div class="flex items-center gap-2 col-span-2 md:col-span-2"><span class="text-xs text-slate-400 whitespace-nowrap">Sampai:</span><input type="date" id="filterDateTo" onchange="clearTaskFilterPills();filterTasks()" class="flex-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-3 py-2 text-sm focus:border-brand-500 outline-none"></div>
        <button onclick="setTaskDateFilter('all')" class="px-3 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-red-50 hover:border-red-300 dark:hover:bg-red-900/20 text-slate-400 hover:text-red-500 transition"><i class="bi bi-x-circle mr-1"></i>Reset</button>
    </div>
</div>
<div id="taskTableWrap" class="glass rounded-2xl overflow-hidden border border-slate-200/60 dark:border-white/[0.06]">
    <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b border-slate-200 dark:border-slate-700/60">
        <th onclick="sortTable('judul')" class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 cursor-pointer hover:text-brand-500">Judul <i class="bi bi-arrow-down-up text-[10px]"></i></th>
        <th onclick="sortTable('tipe')" class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 cursor-pointer hover:text-brand-500 hidden md:table-cell">Tipe</th>
        <th onclick="sortTable('assignee')" class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 cursor-pointer hover:text-brand-500">Assignee</th>
        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 hidden sm:table-cell">Prioritas</th>
        <th onclick="sortTable('batas_waktu')" class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 cursor-pointer hover:text-brand-500 hidden md:table-cell">Deadline</th>
        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
        <th class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 no-print">Aksi</th>
    </tr></thead><tbody id="taskTableBody"></tbody></table></div>
    <div id="emptyTask" class="hidden text-center py-16"><div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-navy-800 flex items-center justify-center"><i class="bi bi-inboxes text-3xl text-slate-300 dark:text-slate-600"></i></div><p class="text-slate-400 text-sm">Belum ada tugas.</p></div>
    <div id="paginationWrap" class="px-4 py-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/60 dark:border-white/[0.06] no-print"></div>
</div>
<div id="kanbanWrap" class="hidden"></div>
</div>

<!-- ===== VIEW: USERS (Manajemen Personil) ===== -->
<div id="view-users" class="app-view hidden animate-fadeIn">
<div class="flex justify-between items-center mb-5"><div><h4 class="font-bold text-lg">Manajemen Personil</h4><p class="text-xs text-slate-400">Kelola akun & struktur organisasi</p></div><button onclick="showUserModal()" class="px-3 py-1.5 text-xs bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition"><i class="bi bi-person-plus mr-1"></i>Tambah Personil</button></div>
<div class="max-w-6xl mx-auto glass rounded-2xl overflow-hidden border border-slate-200/60 dark:border-white/[0.06]">
    <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b border-slate-200 dark:border-slate-700/60">
    <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nama</th>
    <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 hidden md:table-cell">Username</th>
    <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 hidden lg:table-cell">Posisi</th>
    <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Role</th>
    <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 hidden md:table-cell">Atasan</th>
    <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
    <th class="text-center px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Aksi</th>
    </tr></thead><tbody id="userTableBody"></tbody></table></div>
</div>
</div>

</div></div></div>

<!-- ===== MODALS ===== -->
<!-- Task Modal -->
<div id="taskModal" class="hidden modal-backdrop fixed inset-0 z-[1050] flex items-center justify-center p-4">
<div class="bg-white dark:bg-navy-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto border border-slate-200 dark:border-slate-700 animate-slideUp">
<form id="taskForm" onsubmit="handleTaskSubmit(event)">
<div class="flex justify-between items-center p-5 border-b border-slate-100 dark:border-slate-700"><h5 class="font-bold" id="taskModalTitle">Tambah Tugas</h5><button type="button" onclick="closeModal('taskModal')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-navy-900 flex items-center justify-center text-slate-400 transition">&times;</button></div>
<div class="p-5 space-y-4"><input type="hidden" id="taskId">
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Judul Pekerjaan <span class="text-red-500">*</span></label><input type="text" id="taskJudul" required maxlength="200" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition" placeholder="Contoh: Membuat Laporan"></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Deskripsi</label><textarea id="taskDeskripsi" rows="3" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition" placeholder="Rincian..."></textarea></div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Tipe <span class="text-red-500">*</span></label><select id="taskTipe" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none"><option>Harian</option><option>Mingguan</option><option>Bulanan</option></select></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Assignee <span class="text-red-500">*</span></label><select id="taskAssignee" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none"><option value="">Pilih...</option></select></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Prioritas <span class="text-red-500">*</span></label><select id="taskPrioritas" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none"><option>Tinggi</option><option selected>Sedang</option><option>Rendah</option></select></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Deadline <span class="text-red-500">*</span></label><input type="date" id="taskBatasWaktu" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none"></div>
<div id="statusGroup" class="hidden col-span-full"><label class="block text-xs font-semibold text-slate-500 mb-1.5">Status</label><select id="taskStatus" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none"><option>Belum Dimulai</option><option>Sedang Diproses</option><option>Selesai</option></select></div>
</div></div>
<div class="flex justify-end gap-2 p-5 border-t border-slate-100 dark:border-slate-700"><button type="button" onclick="closeModal('taskModal')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-navy-900 transition">Batal</button><button type="submit" id="btnSaveTask" class="px-5 py-2 rounded-xl bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600 hover:shadow-lg transition">Simpan</button></div>
</form></div></div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden modal-backdrop fixed inset-0 z-[1050] flex items-center justify-center p-4">
<div class="bg-white dark:bg-navy-800 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto border border-slate-200 dark:border-slate-700 animate-slideUp">
<div class="flex justify-between items-center p-5 border-b border-slate-100 dark:border-slate-700"><h5 class="font-bold">Detail Tugas</h5><button onclick="closeModal('detailModal')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-navy-900 flex items-center justify-center text-slate-400 transition">&times;</button></div>
<div class="p-5" id="detailBody"></div></div></div>

<!-- User/Personnel Modal -->
<div id="userModal" class="hidden modal-backdrop fixed inset-0 z-[1050] flex items-center justify-center p-4">
<div class="bg-white dark:bg-navy-800 rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto border border-slate-200 dark:border-slate-700 animate-slideUp">
<form id="userForm" onsubmit="handleUserSubmit(event)">
<div class="flex justify-between items-center p-5 border-b border-slate-100 dark:border-slate-700"><h5 class="font-bold" id="userModalTitle">Tambah Personil</h5><button type="button" onclick="closeModal('userModal')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-navy-900 flex items-center justify-center text-slate-400 transition">&times;</button></div>
<div class="p-5 space-y-4"><input type="hidden" id="userId">
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label><input type="text" id="userNama" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition"></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Username <span class="text-red-500">*</span></label><input type="text" id="userUsername" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition"></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Password <span class="text-red-500" id="pwdReq">*</span></label><input type="password" id="userPassword" minlength="6" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition" placeholder="Min 6 karakter"><small class="text-slate-400 text-xs hidden" id="pwdHint">Kosongkan jika tidak ingin mengubah password</small></div>
<div class="grid grid-cols-2 gap-4">
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Email</label><input type="email" id="userEmail" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition"></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Posisi/Jabatan</label><input type="text" id="userPosisi" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition" placeholder="Contoh: Staff IT"></div>
</div>
<div class="grid grid-cols-2 gap-4">
<div id="roleGroup"><label class="block text-xs font-semibold text-slate-500 mb-1.5">Role</label><select id="userRoleSelect" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none" onchange="toggleSupervisorField()"><option>Staff</option><option>Kepala Unit</option><option>Kepala Divisi</option></select></div>
<div id="supervisorGroup"><label class="block text-xs font-semibold text-slate-500 mb-1.5">Atasan</label><select id="userSupervisor" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none"><option value="">- Tidak Ada -</option></select></div>
</div>
<div id="userStatusGroup" class="hidden"><label class="block text-xs font-semibold text-slate-500 mb-1.5">Status</label><select id="userStatusSelect" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none"><option>Aktif</option><option>Non-Aktif</option></select></div>
</div>
<div class="flex justify-end gap-2 p-5 border-t border-slate-100 dark:border-slate-700"><button type="button" onclick="closeModal('userModal')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-navy-900 transition">Batal</button><button type="submit" class="px-5 py-2 rounded-xl bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600 hover:shadow-lg transition">Simpan</button></div>
</form></div></div>

<!-- Password Modal -->
<div id="pwdModal" class="hidden modal-backdrop fixed inset-0 z-[1050] flex items-center justify-center p-4">
<div class="bg-white dark:bg-navy-800 rounded-2xl shadow-2xl w-full max-w-sm border border-slate-200 dark:border-slate-700 animate-slideUp">
<form onsubmit="handleChangePwd(event)">
<div class="flex justify-between items-center p-5 border-b border-slate-100 dark:border-slate-700"><h5 class="font-bold">Ganti Password</h5><button type="button" onclick="closeModal('pwdModal')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-navy-900 flex items-center justify-center text-slate-400 transition">&times;</button></div>
<div class="p-5 space-y-4">
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Password Lama</label><input type="password" id="oldPwd" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition"></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Password Baru</label><input type="password" id="newPwd" required minlength="6" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition"></div>
</div>
<div class="flex justify-end p-5 border-t border-slate-100 dark:border-slate-700"><button type="submit" class="px-5 py-2 rounded-xl bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600 hover:shadow-lg transition">Simpan</button></div>
</form></div></div>

<div id="statusDropdownMenu" class="hidden absolute z-50 bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl py-1 w-44"></div>

<!-- Goal Modal -->
<div id="goalModal" class="hidden modal-backdrop fixed inset-0 z-[1050] flex items-center justify-center p-4">
<div class="bg-white dark:bg-navy-800 rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto border border-slate-200 dark:border-slate-700 animate-slideUp">
<form id="goalForm" onsubmit="handleGoalSubmit(event)">
<div class="flex justify-between items-center p-5 border-b border-slate-100 dark:border-slate-700"><h5 class="font-bold" id="goalModalTitle">Tambah Goal</h5><button type="button" onclick="closeModal('goalModal')" class="w-8 h-8 rounded-lg hover:bg-slate-100 dark:hover:bg-navy-900 flex items-center justify-center text-slate-400 transition">&times;</button></div>
<div class="p-5 space-y-4"><input type="hidden" id="goalId">
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Scope</label><select id="goalScope" onchange="onGoalScopeChange()" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none"><option value="Divisi">&#127970; Divisi</option><option value="Unit">&#128101; Unit</option><option value="Personal">&#128100; Personal</option></select></div>
<!-- Target Tim (hanya muncul jika scope Personal & user adalah Kepala) -->
<div id="goalAssignedToGroup" class="hidden">
    <label class="block text-xs font-semibold text-slate-500 mb-1.5"><i class="bi bi-person-check mr-1 text-brand-500"></i>Target Untuk <span class="text-[11px] text-slate-400">(pilih anggota tim atau kosongkan untuk target sendiri)</span></label>
    <select id="goalAssignedTo" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none">
        <option value="">&#128100; Saya Sendiri</option>
    </select>
</div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Judul Target <span class="text-red-500">*</span></label><input type="text" id="goalJudul" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition" placeholder="Contoh: Meningkatkan produktivitas 20%"></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Deskripsi</label><textarea id="goalDeskripsi" rows="2" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition resize-none" placeholder="Penjelasan singkat tentang goal ini..."></textarea></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Strategi <span class="text-[11px] text-slate-400">(1 baris = 1 poin strategi)</span></label><textarea id="goalStrategi" rows="4" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition resize-none" placeholder="Tingkatkan kolaborasi antar tim
Kurangi waktu meeting 30%
Optimasi workflow harian"></textarea></div>
<div><label class="block text-xs font-semibold text-slate-500 mb-1.5">Periode</label><input type="month" id="goalPeriode" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-navy-900 px-4 py-2.5 text-sm focus:border-brand-500 outline-none transition"></div>
</div>
<div class="flex justify-end gap-2 p-5 border-t border-slate-100 dark:border-slate-700"><button type="button" onclick="closeModal('goalModal')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-navy-900 transition">Batal</button><button type="submit" id="btnSaveGoal" class="px-5 py-2 rounded-xl bg-brand-500 text-white text-sm font-semibold hover:bg-brand-600 hover:shadow-lg transition">Simpan</button></div>
</form></div></div>

<script src="app.js"></script>
</body></html>
