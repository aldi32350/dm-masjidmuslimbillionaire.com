// ==============================================================================
// TASKFLOW PRO v3.0 â€” FRONTEND JAVASCRIPT
// FILE       : app.js
// Hierarki: Kepala Divisi > Kepala Unit > Staff
// ==============================================================================

// ========== GLOBAL STATE ==========
let currentUser=null, tasksData=[], usersData=[], goalsData=[], chartInstance=null, statusChartInstance=null;
let currentPage=1, rowsPerPage=10, sortCol=null, sortDir='asc', searchTimeout=null, taskView='table';
let activeDashFilter='today', activeDashScope='team', activeGoalTab='Divisi';
let liveTimerInterval=null; // Live timer for in-progress tasks
let kanbanDragging=false; // Flag to prevent onclick during kanban drag
const API = 'api.php';

// ========== THEME ==========
if(localStorage.getItem('theme')==='dark'){
    document.documentElement.classList.add('dark');
    document.getElementById('themeIcon').classList.replace('bi-moon-stars','bi-sun');
}
document.addEventListener('DOMContentLoaded',()=>{
    const td=document.getElementById('todayDate');
    if(td) td.textContent=new Date().toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
});

// ========== INIT ==========
document.addEventListener('DOMContentLoaded', ()=> checkAuth());

document.addEventListener('click', e => {
    const dd = document.getElementById('userMenu');
    const btn = document.getElementById('userDropdown');
    if(dd && !btn.contains(e.target)) dd.classList.add('hidden');
    const sd = document.getElementById('statusDropdownMenu');
    if(sd && !sd.contains(e.target) && !e.target.classList.contains('status-btn')) sd.classList.add('hidden');
});

// ========== HELPERS ==========
function showLoader(){ document.getElementById('loader').style.display='flex'; }
function hideLoader(){ document.getElementById('loader').style.display='none'; }
function toast(icon,title){ Swal.fire({toast:true,position:'top-end',icon,title,showConfirmButton:false,timer:3000,timerProgressBar:true}); }
function fmtDate(d){ if(!d) return '-'; try{ return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}); }catch(e){ return d; } }
function closeModal(id){ document.getElementById(id).classList.add('hidden'); }
function openModal(id){ document.getElementById(id).classList.remove('hidden'); }

// ========== DURATION HELPERS ==========
function formatDuration(seconds){
    if(!seconds && seconds!==0) return '-';
    seconds=Math.floor(seconds);
    const h=Math.floor(seconds/3600);
    const m=Math.floor((seconds%3600)/60);
    const s=seconds%60;
    if(h>0) return `${h}j ${m}m ${s}d`;
    if(m>0) return `${m}m ${s}d`;
    return `${s}d`;
}

function getElapsedSeconds(waktuMulai){
    if(!waktuMulai) return 0;
    const start=new Date(waktuMulai.replace(' ','T')); // ensure parsing
    const now=new Date();
    return Math.max(0, Math.floor((now-start)/1000));
}

function startLiveTimers(){
    if(liveTimerInterval) clearInterval(liveTimerInterval);
    liveTimerInterval=setInterval(()=>{
        document.querySelectorAll('[data-live-timer]').forEach(el=>{
            const waktu=el.dataset.liveTimer;
            if(waktu) el.textContent=formatDuration(getElapsedSeconds(waktu));
        });
    },1000);
}
startLiveTimers();

async function apiCall(action, data={}) {
    const fd = new FormData();
    fd.append('action', action);
    for(const k in data) fd.append(k, data[k]);
    const res = await fetch(API, { method:'POST', body: fd });
    return res.json();
}

function toggleSidebar(){
    const sb=document.getElementById('sidebar'), ov=document.getElementById('mobileOverlay');
    if(window.innerWidth<=768){ sb.classList.toggle('-translate-x-[240px]'); sb.classList.toggle('translate-x-0'); ov.classList.toggle('hidden'); }
    else{ sb.classList.toggle('-translate-x-[240px]'); document.getElementById('mainContent').classList.toggle('md:ml-[240px]'); document.getElementById('mainContent').classList.toggle('md:ml-0'); }
}

function toggleTheme(){
    const h=document.documentElement, i=document.getElementById('themeIcon');
    if(h.classList.contains('dark')){ h.classList.remove('dark'); localStorage.setItem('theme','light'); i.classList.replace('bi-sun','bi-moon-stars'); }
    else{ h.classList.add('dark'); localStorage.setItem('theme','dark'); i.classList.replace('bi-moon-stars','bi-sun'); }
    if(statusChartInstance){ statusChartInstance.destroy(); statusChartInstance=null; }
    if(chartInstance){ chartInstance.destroy(); chartInstance=null; loadDashboard(); }
}

function switchView(id,el){
    document.querySelectorAll('.nav-link').forEach(n=>n.classList.remove('active'));
    if(el){ el.classList.add('active'); }
    else { document.querySelectorAll('.nav-link').forEach(n=>{ const t=n.textContent.trim().toLowerCase(); if((id==='tasks'&&t.includes('tugas'))||(id==='users'&&t.includes('personil'))||(id==='dashboard'&&t.includes('dashboard'))) n.classList.add('active'); }); }
    document.querySelectorAll('.app-view').forEach(v=>v.classList.add('hidden'));
    const view=document.getElementById('view-'+id); if(view) view.classList.remove('hidden');
    const titles={dashboard:'Dashboard',tasks:'Manajemen Tugas',users:'Manajemen Personil',eod:'Teks Siap Salin',telegram:'Bot Telegram'};
    document.getElementById('pageTitle').textContent=titles[id]||'';
    if(id==='dashboard') loadDashboard();
    if(id==='tasks'){ populateAssigneeDropdowns(); setTaskDateFilter('today'); }
    if(id==='users') loadUsers();
    if(id==='eod' && typeof initEodView==='function') initEodView();
    if(id==='telegram' && typeof loadTgSettings==='function') loadTgSettings();
    if(window.innerWidth<=768) toggleSidebar();
}

function animateCounter(elId, target){
    const el=document.getElementById(elId); if(!el) return;
    let cur=0; const step=Math.max(target/25,1);
    const timer=setInterval(()=>{ cur+=step; if(cur>=target){ cur=target; clearInterval(timer); } el.textContent=Math.floor(cur); },30);
}

// ========== AUTH ==========
async function checkAuth(){
    showLoader();
    try {
        const res = await apiCall('get_current_user');
        hideLoader();
        if(res.success && res.loggedIn) loginSuccess(res.user);
        else { document.getElementById('loginPage').classList.remove('hidden'); document.getElementById('appWrapper').classList.add('hidden'); }
    } catch(e) { hideLoader(); document.getElementById('loginPage').classList.remove('hidden'); }
}

async function handleLogin(e){
    e.preventDefault();
    const btn=document.getElementById('btnLogin'); btn.disabled=true; btn.innerHTML='<span class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin-custom"></span> Masuk...';
    document.getElementById('loginError').classList.add('hidden');
    try {
        const res = await apiCall('login', { username: document.getElementById('loginUser').value, password: document.getElementById('loginPass').value });
        btn.disabled=false; btn.innerHTML='<i class="bi bi-box-arrow-in-right mr-2"></i>Masuk';
        if(res.success && res.loggedIn) loginSuccess(res.user);
        else { document.getElementById('loginError').textContent=res.message||'Login gagal'; document.getElementById('loginError').classList.remove('hidden'); }
    } catch(err) { btn.disabled=false; btn.innerHTML='<i class="bi bi-box-arrow-in-right mr-2"></i>Masuk'; document.getElementById('loginError').textContent='Koneksi gagal'; document.getElementById('loginError').classList.remove('hidden'); }
}

function toggleLoginPassword() {
    const inp = document.getElementById('loginPass');
    const icon = document.getElementById('loginPassToggleIcon');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'bi bi-eye-slash text-lg'; }
    else { inp.type = 'password'; icon.className = 'bi bi-eye text-lg'; }
}

function loginSuccess(user){
    currentUser=user;
    document.getElementById('loginPage').classList.add('hidden');
    document.getElementById('appWrapper').classList.remove('hidden');
    document.getElementById('userName').textContent=user.nama;
    document.getElementById('userRole').textContent=user.role;
    document.getElementById('userAvatar').textContent=user.nama.charAt(0).toUpperCase();
    document.getElementById('sideAvatar').textContent=user.nama.charAt(0).toUpperCase();
    document.getElementById('sideName').textContent=user.nama;
    document.getElementById('sideRole').textContent=user.role;
    // Role-based menu visibility
    const isKD = user.role==='Kepala Divisi';
    const isKU = user.role==='Kepala Unit';
    document.getElementById('navUsers').classList.toggle('hidden', !(isKD||isKU));
    document.getElementById('navBackup').classList.toggle('hidden', !isKD);
    const navTg=document.getElementById('navTelegram'); if(navTg) navTg.classList.toggle('hidden', !isKD);
    document.getElementById('quickPersonil').classList.toggle('hidden', !(isKD||isKU));
    // Scope toggle visibility (only for Kepala Divisi & Kepala Unit)
    document.getElementById('scopeFilterWrap').classList.toggle('hidden', !(isKD||isKU));
    // Tampilkan tab Tim Saya untuk Kepala Divisi & Kepala Unit
    const tabTim = document.getElementById('tabTimSaya');
    if(tabTim) tabTim.classList.toggle('hidden', !(isKD||isKU));
    // Dashboard subtitle
    if(user.role==='Staff') document.getElementById('dashSubtitle').textContent='Pekerjaan saya';
    else if(isKU) document.getElementById('dashSubtitle').textContent='Ringkasan unit saya';
    else document.getElementById('dashSubtitle').textContent='Ringkasan seluruh divisi';
    loadAllData();
}

async function handleLogout(){
    const r = await Swal.fire({title:'Logout?',text:'Anda akan keluar dari aplikasi.',icon:'question',showCancelButton:true,confirmButtonText:'Ya, Logout',confirmButtonColor:'#ef4444'});
    if(r.isConfirmed){ showLoader(); await apiCall('logout'); hideLoader(); currentUser=null; document.getElementById('appWrapper').classList.add('hidden'); document.getElementById('loginPage').classList.remove('hidden'); document.getElementById('loginForm').reset(); }
}

function showPasswordModal(){ openModal('pwdModal'); }
async function handleChangePwd(e){
    e.preventDefault(); showLoader();
    const res = await apiCall('change_password', { old_password: document.getElementById('oldPwd').value, new_password: document.getElementById('newPwd').value });
    hideLoader();
    if(res.success){ closeModal('pwdModal'); toast('success',res.message); }
    else Swal.fire('Gagal',res.message,'error');
}

// ========== DATA LOADING ==========
async function loadAllData(){
    showLoader();
    try {
        const res = await apiCall('get_all_data');
        hideLoader();
        if(res.success){
            usersData=res.users||[]; tasksData=res.tasks||[]; goalsData=res.goals||[];
            populateAssigneeDropdowns(); renderUserTable(); filterTasks();
            if(res.dashboard && res.dashboard.recentActivities) renderActivityLog(res.dashboard.recentActivities);
            applyDashboardFilter();
            renderGoalCard();
        }
    } catch(e){ hideLoader(); toast('error','Gagal memuat data'); }
}

function populateAssigneeDropdowns(){
    let opts='<option value="">Semua Assignee</option>', fOpts='<option value="">Pilih...</option>';
    const nameSet=new Set();
    usersData.forEach(u=>{ if(u.status==='Aktif') nameSet.add(u.nama_lengkap); });
    if(currentUser && currentUser.nama) nameSet.add(currentUser.nama);
    nameSet.forEach(name=>{ opts+=`<option>${name}</option>`; fOpts+=`<option>${name}</option>`; });
    document.getElementById('filterAssignee').innerHTML=opts;
    document.getElementById('taskAssignee').innerHTML=fOpts;
}

// ========== DASHBOARD ==========
async function loadDashboard(){
    try {
        const res = await apiCall('get_dashboard');
        if(!res.success) return; const d=res.data;
        if(d.recentActivities) renderActivityLog(d.recentActivities);
        applyDashboardFilter();
    } catch(e){}
}

function renderDashboardFromData(d){
    if(!d) return;
    if(d.recentActivities) renderActivityLog(d.recentActivities);
    applyDashboardFilter();
}

function renderChart(data){
    const ctx=document.getElementById('teamChart'); if(!ctx) return;
    if(chartInstance) chartInstance.destroy();
    const isDark=document.documentElement.classList.contains('dark');
    if(!data||!data.length){ chartInstance=null; return; }
    chartInstance=new Chart(ctx,{type:'bar',data:{labels:data.map(i=>i.name),datasets:[
        {label:'Selesai',data:data.map(i=>i.selesai),backgroundColor:'#22c55e',borderRadius:8,barPercentage:0.6},
        {label:'Total',data:data.map(i=>i.total),backgroundColor:isDark?'#1e2235':'#e2e8f0',borderRadius:8,barPercentage:0.6}
    ]},options:{responsive:true,plugins:{legend:{labels:{color:isDark?'#94a3b8':'#64748b',font:{family:'Inter',size:11},usePointStyle:true,pointStyle:'circle'}}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,color:isDark?'#475569':'#94a3b8',font:{size:11}},grid:{color:isDark?'#1e293b':'#f1f5f9'}},x:{ticks:{color:isDark?'#94a3b8':'#64748b',font:{size:11}},grid:{display:false}}}}});
}

function renderStatusChart(summary){
    const ctx=document.getElementById('statusChart');
    if(!ctx) return;
    if(statusChartInstance) statusChartInstance.destroy();
    const isDark=document.documentElement.classList.contains('dark');
    const belum=summary.totalTasks-summary.completedTasks-summary.inProgressTasks;
    statusChartInstance=new Chart(ctx,{type:'doughnut',data:{
        labels:['Selesai','Diproses','Belum Dimulai','Overdue'],
        datasets:[{data:[summary.completedTasks,summary.inProgressTasks,Math.max(belum,0),summary.overdueTasks],
        backgroundColor:['#10b981','#22c55e','#94a3b8','#ef4444'],borderWidth:0,hoverOffset:8}]
    },options:{responsive:true,cutout:'68%',plugins:{legend:{position:'bottom',labels:{color:isDark?'#94a3b8':'#64748b',font:{family:'Inter',size:11},usePointStyle:true,pointStyle:'circle',padding:15}}}}});
}

function renderActivityLog(logs){
    const el=document.getElementById('activityLog');
    if(!logs||!logs.length){ el.innerHTML='<p class="text-slate-400 text-sm">Belum ada aktivitas.</p>'; return; }
    el.innerHTML=logs.map(l=>`<div class="pb-3 border-b border-slate-200/60 dark:border-slate-700/50 last:border-0"><div class="flex justify-between"><span class="text-[11px] font-bold text-brand-500">${l.aksi}</span><span class="text-[11px] text-slate-400">${l.waktu}</span></div><p class="text-[11px] text-slate-500 mt-0.5">${l.detail}</p></div>`).join('');
}

// ========== DASHBOARD FILTER & SCOPE ==========
function getDateRange(period){
    const now=new Date(), y=now.getFullYear(), m=now.getMonth(), d=now.getDate();
    const fmt=dt=>`${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}`;
    switch(period){
        case 'today': return {from:fmt(now),to:fmt(now),label:'📅 Hari Ini'};
        case 'yesterday':{ const dt=new Date(y,m,d-1); return {from:fmt(dt),to:fmt(dt),label:'📅 Kemarin'}; }
        case 'tomorrow':{ const dt=new Date(y,m,d+1); return {from:fmt(dt),to:fmt(dt),label:'📅 Besok'}; }
        case 'week':{ const dow=now.getDay()||7; const mon=new Date(y,m,d-(dow-1)); const sun=new Date(y,m,d+(7-dow)); return {from:fmt(mon),to:fmt(sun),label:'📅 Minggu Ini'}; }
        case 'month':{ const first=new Date(y,m,1); const last=new Date(y,m+1,0); return {from:fmt(first),to:fmt(last),label:'📅 Bulan Ini'}; }
        default: return {from:null,to:null,label:'📅 Semua Waktu'};
    }
}

function setDashFilter(period){
    activeDashFilter=period;
    document.querySelectorAll('.dash-filter-btn').forEach(b=>{
        b.classList.toggle('active',b.dataset.filter===period);
    });
    applyDashboardFilter();
}

function setDashScope(scope){
    activeDashScope=scope;
    document.querySelectorAll('.scope-btn').forEach(b=>{
        b.classList.toggle('active',b.dataset.scope===scope);
    });
    applyDashboardFilter();
}

function applyDashboardFilter(){
    const range=getDateRange(activeDashFilter);
    document.getElementById('dashFilterLabel').textContent=range.label;
    // Start with tasksData (already filtered by backend based on role)
    let filtered=tasksData;
    // Apply scope filter for Kepala Divisi & Kepala Unit
    if(activeDashScope==='me' && currentUser){
        filtered=filtered.filter(t=>t.assignee===currentUser.nama);
    }
    // Apply date filter
    if(range.from && range.to){
        filtered=filtered.filter(t=>{
            if(!t.batas_waktu) return false;
            const dl=t.batas_waktu.substring(0,10);
            return dl>=range.from && dl<=range.to;
        });
    }
    // Calculate stats
    const today=new Date(); today.setHours(0,0,0,0);
    const total=filtered.length;
    const completed=filtered.filter(t=>t.status==='Selesai').length;
    const inProgress=filtered.filter(t=>t.status==='Sedang Diproses').length;
    const overdue=filtered.filter(t=>{
        if(t.status==='Selesai'||!t.batas_waktu) return false;
        return new Date(t.batas_waktu)<today;
    }).length;
    animateCounter('dashTotal',total);
    animateCounter('dashCompleted',completed);
    animateCounter('dashProgress',inProgress);
    animateCounter('dashOverdue',overdue);
    const pct=total?Math.round(completed/total*100):0;
    document.getElementById('progressPct').textContent=pct+'%';
    document.getElementById('progressBar').style.width=pct+'%';
    const summary={totalTasks:total,completedTasks:completed,inProgressTasks:inProgress,overdueTasks:overdue};
    renderStatusChart(summary);
    const assigneeMap={};
    filtered.forEach(t=>{
        if(!assigneeMap[t.assignee]) assigneeMap[t.assignee]={name:t.assignee,total:0,selesai:0};
        assigneeMap[t.assignee].total++;
        if(t.status==='Selesai') assigneeMap[t.assignee].selesai++;
    });
    renderChart(Object.values(assigneeMap));
    renderRecentTasks(filtered);
}

function renderRecentTasks(filtered){
    const el=document.getElementById('recentTasksList');
    if(!el) return;
    if(!filtered||!filtered.length){ el.innerHTML='<div class="text-center py-6"><i class="bi bi-inbox text-3xl text-slate-300 dark:text-slate-600"></i><p class="text-slate-400 text-xs mt-2">Tidak ada tugas pada periode ini</p></div>'; return; }
    const recent=filtered.slice(0,8);
    const today=new Date(); today.setHours(0,0,0,0);
    el.innerHTML=recent.map(t=>{
        const sc=t.status==='Selesai'?'bg-green-500':t.status==='Sedang Diproses'?'bg-blue-500':'bg-slate-400';
        const pc=t.prioritas==='Tinggi'?'text-red-500':t.prioritas==='Sedang'?'text-amber-500':'text-green-500';
        let isOverdue=false;
        if(t.status!=='Selesai'&&t.batas_waktu&&new Date(t.batas_waktu)<today) isOverdue=true;
        return `<div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-navy-800/50 transition cursor-pointer" onclick="showDetail('${t.task_id}')">
            <div class="w-2 h-2 rounded-full ${sc} shrink-0"></div>
            <div class="flex-1 min-w-0">
                <div class="font-medium text-sm truncate">${isOverdue?'<i class="bi bi-exclamation-circle text-red-500 mr-1"></i>':''}${t.judul}</div>
                <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5"><span><i class="bi bi-person"></i> ${t.assignee}</span><span class="${pc}">● ${t.prioritas}</span></div>
            </div>
            <div class="text-[11px] text-slate-400 shrink-0 text-right"><div>${fmtDate(t.batas_waktu)}</div><div class="text-xs font-medium px-2 py-0.5 rounded-full ${sc} text-white mt-0.5">${t.status}</div></div>
        </div>`;
    }).join('');
}

// ========== TASKS ==========

// ---- Task Date Filter Pills ----
function setTaskDateFilter(period) {
    const now = new Date(), y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
    const fmt = dt => `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}`;
    let from = '', to = '', label = '';
    switch(period) {
        case 'today':
            from = to = fmt(now); label = '📅 Hari Ini'; break;
        case 'yesterday': {
            const dt = new Date(y, m, d-1); from = to = fmt(dt); label = '📅 Kemarin'; break;
        }
        case 'tomorrow': {
            const dt = new Date(y, m, d+1); from = to = fmt(dt); label = '📅 Besok'; break;
        }
        case 'week': {
            const dow = now.getDay() || 7;
            from = fmt(new Date(y, m, d-(dow-1)));
            to   = fmt(new Date(y, m, d+(7-dow)));
            label = '📅 Minggu Ini'; break;
        }
        case 'month':
            from = fmt(new Date(y, m, 1));
            to   = fmt(new Date(y, m+1, 0));
            label = '📅 Bulan Ini'; break;
        default:
            from = ''; to = ''; label = '📅 Semua Waktu';
    }
    document.getElementById('filterDateFrom').value = from;
    document.getElementById('filterDateTo').value   = to;
    const lbl = document.getElementById('taskFilterLabel');
    if(lbl) lbl.textContent = label;
    // Update active pill
    document.querySelectorAll('.task-filter-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.tfilter === period);
    });
    filterTasks();
}

function clearTaskFilterPills() {
    document.querySelectorAll('.task-filter-btn').forEach(b => b.classList.remove('active'));
    const lbl = document.getElementById('taskFilterLabel');
    if(lbl) lbl.textContent = '📅 Kustom';
}

function debounceSearch(){ clearTimeout(searchTimeout); searchTimeout=setTimeout(filterTasks,300); }

function filterTasks(){
    const s=(document.getElementById('searchTask').value||'').toLowerCase();
    const t=document.getElementById('filterTipe').value;
    const st=document.getElementById('filterStatus').value;
    const a=document.getElementById('filterAssignee').value;
    const p=document.getElementById('filterPrioritas').value;
    const dateFrom=document.getElementById('filterDateFrom').value;
    const dateTo=document.getElementById('filterDateTo').value;
    let filtered=tasksData.filter(tk=>{
        const ms=!s||(tk.judul||'').toLowerCase().includes(s)||(tk.deskripsi||'').toLowerCase().includes(s);
        let dateOk=true;
        if(dateFrom && tk.batas_waktu){ dateOk = tk.batas_waktu >= dateFrom; }
        if(dateTo && tk.batas_waktu && dateOk){ dateOk = tk.batas_waktu <= dateTo; }
        return ms&&(!t||tk.tipe===t)&&(!st||tk.status===st)&&(!a||tk.assignee===a)&&(!p||tk.prioritas===p)&&dateOk;
    });
    if(sortCol){ filtered.sort((a,b)=>{ let va=a[sortCol]||'',vb=b[sortCol]||''; return(va>vb?1:va<vb?-1:0)*(sortDir==='asc'?1:-1); }); }
    currentPage=1;
    if(taskView==='table') renderTaskTable(filtered); else renderKanban(filtered);
}

function sortTable(col){ if(sortCol===col) sortDir=sortDir==='asc'?'desc':'asc'; else{ sortCol=col; sortDir='asc'; } filterTasks(); }

function renderTaskTable(data){
    if(!data) data=tasksData;
    const tbody=document.getElementById('taskTableBody'), empty=document.getElementById('emptyTask'), pw=document.getElementById('paginationWrap');
    if(!data.length){ tbody.innerHTML=''; empty.classList.remove('hidden'); pw.innerHTML=''; return; }
    empty.classList.add('hidden');
    const totalPages=Math.ceil(data.length/rowsPerPage);
    if(currentPage>totalPages) currentPage=totalPages;
    const start=(currentPage-1)*rowsPerPage, pageData=data.slice(start,start+rowsPerPage);
    const today=new Date(); today.setHours(0,0,0,0);
    let html='';
    pageData.forEach(t=>{
        const pb=t.prioritas;
        const pClass=pb==='Tinggi'?'bg-gradient-to-r from-red-500 to-red-400 text-white':pb==='Sedang'?'bg-gradient-to-r from-amber-500 to-amber-400 text-black':'bg-gradient-to-r from-emerald-500 to-emerald-400 text-white';
        let sc='bg-slate-500'; if(t.status==='Sedang Diproses') sc='bg-blue-500'; if(t.status==='Selesai') sc='bg-emerald-500';
        let dc='', wi='';
        const canEdit=currentUser&&(currentUser.role==='Kepala Divisi'||currentUser.role==='Kepala Unit'||t.assignee===currentUser.nama);
        if(t.status!=='Selesai'&&t.batas_waktu){ const dl=new Date(t.batas_waktu); if(dl<today){ sc='bg-red-500'; dc='text-red-500 font-bold'; wi='<i class="bi bi-exclamation-circle text-red-500"></i> '; } else if(dl-today<=172800000) dc='text-amber-500 font-bold'; }
        html+=`<tr class="border-b border-slate-100 dark:border-slate-700/50 hover:bg-brand-50/50 dark:hover:bg-brand-900/10 transition">
            <td class="px-4 py-3"><div class="font-semibold max-w-[250px] truncate">${wi}${t.judul}</div><div class="text-xs text-slate-500 truncate max-w-[250px]">${t.deskripsi||'-'}</div></td>
            <td class="px-4 py-3 hidden md:table-cell">${t.tipe}</td>
            <td class="px-4 py-3"><i class="bi bi-person-circle text-slate-400 mr-1"></i>${t.assignee}</td>
            <td class="px-4 py-3 hidden sm:table-cell"><span class="text-xs font-semibold px-3 py-1 rounded-full ${pClass}">${pb}</span></td>
            <td class="px-4 py-3 hidden md:table-cell ${dc}">${fmtDate(t.batas_waktu)}</td>
            <td class="px-4 py-3">${canEdit?`<button class="status-btn text-xs font-medium px-3 py-1 rounded-full ${sc} text-white cursor-pointer hover:opacity-80 transition" onclick="toggleStatusDropdown(event,'${t.task_id}')">${t.status}</button>`:`<span class="text-xs font-medium px-3 py-1 rounded-full ${sc} text-white">${t.status}</span>`}</td>
            <td class="px-4 py-3 text-center no-print whitespace-nowrap">
                <button class="p-1.5 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition mr-1" onclick="showDetail('${t.task_id}')" title="Detail"><i class="bi bi-eye text-sm"></i></button>
                ${canEdit?`<button class="p-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition mr-1" onclick="editTask('${t.task_id}')" title="Edit"><i class="bi bi-pencil text-sm"></i></button><button class="p-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600 transition" onclick="deleteTaskConfirm('${t.task_id}')" title="Hapus"><i class="bi bi-trash text-sm"></i></button>`:''}</td></tr>`;
    });
    tbody.innerHTML=html;
    let ph=`<div class="flex items-center gap-2"><select class="rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-2 py-1 text-sm" onchange="rowsPerPage=+this.value;currentPage=1;filterTasks()"><option ${rowsPerPage===10?'selected':''}>10</option><option ${rowsPerPage===25?'selected':''}>25</option><option ${rowsPerPage===50?'selected':''}>50</option></select><span class="text-xs text-slate-500">${start+1}-${Math.min(start+rowsPerPage,data.length)} dari ${data.length}</span></div>`;
    ph+=`<div class="flex gap-1">`;
    ph+=`<button class="px-3 py-1 rounded-lg border border-slate-300 dark:border-slate-600 text-sm hover:bg-brand-500 hover:text-white hover:border-brand-500 transition ${currentPage===1?'opacity-50 pointer-events-none':''}" onclick="currentPage--;filterTasks()">‹</button>`;
    for(let i=1;i<=totalPages;i++){
        if(totalPages>7&&i>2&&i<totalPages-1&&Math.abs(i-currentPage)>1){ if(i===3||i===totalPages-2) ph+=`<span class="px-2 py-1 text-sm text-slate-400">…</span>`; continue; }
        ph+=`<button class="px-3 py-1 rounded-lg border text-sm transition ${i===currentPage?'bg-brand-500 text-white border-brand-500':'border-slate-300 dark:border-slate-600 hover:bg-brand-500 hover:text-white hover:border-brand-500'}" onclick="currentPage=${i};filterTasks()">${i}</button>`;
    }
    ph+=`<button class="px-3 py-1 rounded-lg border border-slate-300 dark:border-slate-600 text-sm hover:bg-brand-500 hover:text-white hover:border-brand-500 transition ${currentPage===totalPages?'opacity-50 pointer-events-none':''}" onclick="currentPage++;filterTasks()">›</button></div>`;
    pw.innerHTML=ph;
}

function toggleStatusDropdown(e, taskId){
    e.stopPropagation();
    const menu=document.getElementById('statusDropdownMenu');
    const rect=e.target.getBoundingClientRect();
    menu.style.top=(rect.bottom+window.scrollY+4)+'px';
    menu.style.left=(rect.left+window.scrollX)+'px';
    const task=tasksData.find(t=>t.task_id===taskId);
    const allStatuses=['Belum Dimulai','Sedang Diproses','Selesai'];
    const statusOrder={'Belum Dimulai':0,'Sedang Diproses':1,'Selesai':2};
    const isKepalaDivisi=currentUser&&currentUser.role==='Kepala Divisi';
    const curLevel=task?statusOrder[task.status]??0:0;
    const allowed=allStatuses.filter(s=>{
        if(isKepalaDivisi) return true;
        return (statusOrder[s]??0)>=curLevel;
    });
    menu.innerHTML=allowed.map(s=>`<button class="w-full text-left px-4 py-2 text-sm hover:bg-brand-50 dark:hover:bg-brand-900/20 transition" onclick="changeStatus('${taskId}','${s}')">${s}</button>`).join('');
    menu.classList.toggle('hidden');
}

// ========== KANBAN ==========
function setTaskView(v){
    taskView=v;
    document.getElementById('btnTableView').classList.toggle('bg-brand-500',v==='table');
    document.getElementById('btnTableView').classList.toggle('text-white',v==='table');
    document.getElementById('btnKanbanView').classList.toggle('bg-brand-500',v==='kanban');
    document.getElementById('btnKanbanView').classList.toggle('text-white',v==='kanban');
    document.getElementById('taskTableWrap').classList.toggle('hidden',v==='kanban');
    document.getElementById('kanbanWrap').classList.toggle('hidden',v==='table');
    filterTasks();
}

function renderKanban(data){
    if(!data) data=tasksData;
    const cols={'Belum Dimulai':{color:'#64748b',icon:'bi-circle',items:[]},'Sedang Diproses':{color:'#3b82f6',icon:'bi-play-circle',items:[]},'Selesai':{color:'#10b981',icon:'bi-check-circle',items:[]}};
    data.forEach(t=>{ const s=t.status||'Belum Dimulai'; if(cols[s]) cols[s].items.push(t); else cols['Belum Dimulai'].items.push(t); });
    // Sort each column by urutan, then priority, then deadline
    const prioOrder={'Tinggi':0,'Sedang':1,'Rendah':2};
    Object.values(cols).forEach(col=>{
        col.items.sort((a,b)=>{
            // First by urutan
            const ua=(a.urutan||0), ub=(b.urutan||0);
            if(ua!==ub) return ua-ub;
            const pa=prioOrder[a.prioritas]??1, pb=prioOrder[b.prioritas]??1;
            if(pa!==pb) return pa-pb;
            const da=a.batas_waktu||'9999', db=b.batas_waktu||'9999';
            return da<db?-1:da>db?1:0;
        });
    });
    let html='<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
    Object.entries(cols).forEach(([name,col])=>{
        html+=`<div class="kanban-col glass border border-brand-500/10 rounded-2xl p-4 min-h-[300px] transition-all duration-200"
            data-status="${name}">
            <div class="flex items-center justify-between rounded-xl px-3 py-2 mb-3" style="background:${col.color}15;color:${col.color}">
                <span class="font-bold text-sm"><i class="bi ${col.icon} mr-2"></i>${name}</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full text-white" style="background:${col.color}">${col.items.length}</span>
            </div>
            <div class="kanban-items" data-col-status="${name}">`;
        col.items.forEach(t=>{
            const pc=t.prioritas==='Tinggi'?'from-red-500 to-red-400 text-white':t.prioritas==='Sedang'?'from-amber-500 to-amber-400 text-black':'from-emerald-500 to-emerald-400 text-white';
            const canEdit=currentUser&&(currentUser.role==='Kepala Divisi'||currentUser.role==='Kepala Unit'||t.assignee===currentUser.nama);
            // Duration badge
            let durationHtml='';
            if(t.status==='Sedang Diproses' && t.waktu_mulai){
                durationHtml=`<div class="duration-badge live-timer bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 mt-2"><i class="bi bi-stopwatch"></i><span data-live-timer="${t.waktu_mulai}">${formatDuration(getElapsedSeconds(t.waktu_mulai))}</span></div>`;
            } else if(t.status==='Selesai' && t.durasi_kerja){
                durationHtml=`<div class="duration-badge bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 mt-2"><i class="bi bi-clock-history"></i><span>${formatDuration(t.durasi_kerja)}</span></div>`;
            }
            html+=`<div class="kanban-card glass border border-brand-500/10 rounded-xl p-3.5 mb-2.5 hover:shadow-lg hover:-translate-y-0.5 transition-all"
                data-task-id="${t.task_id}"
                onclick="if(!kanbanDragging) showDetail('${t.task_id}')">
                <div class="flex items-center justify-between text-[10px] text-slate-400 mb-1.5">
                    <div class="flex items-center gap-1"><i class="bi bi-grip-vertical"></i><span>Geser untuk mengatur</span></div>
                    ${canEdit?`<button class="p-1 rounded-md hover:bg-amber-100 dark:hover:bg-amber-500/20 text-amber-500 hover:text-amber-600 transition" onclick="event.stopPropagation();editTask('${t.task_id}')" title="Edit Tugas"><i class="bi bi-pencil text-xs"></i></button>`:''}
                </div>
                <div class="font-semibold text-sm mb-1.5">${t.judul}</div>
                <div class="flex justify-between items-center mt-2"><span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-gradient-to-r ${pc}">${t.prioritas}</span><span class="text-xs text-slate-500 flex items-center gap-1"><i class="bi bi-calendar"></i>${fmtDate(t.batas_waktu)}</span></div>
                <div class="text-xs text-slate-500 mt-2 flex items-center gap-1"><i class="bi bi-person"></i>${t.assignee}</div>
                ${durationHtml}
            </div>`;
        });
        html+='</div></div>';
    });
    html+='</div>';
    document.getElementById('kanbanWrap').innerHTML=html;
    // Initialize SortableJS on each column
    initKanbanSortable();
}

function initKanbanSortable(){
    const statusOrder={'Belum Dimulai':0,'Sedang Diproses':1,'Selesai':2};
    document.querySelectorAll('.kanban-items').forEach(el=>{
        new Sortable(el, {
            group: 'kanban-board',
            animation: 200,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            handle: '.kanban-card',
            delay: 50,
            delayOnTouchOnly: true,
            onStart: function(){
                kanbanDragging=true;
            },
            onEnd: async function(evt){
                setTimeout(()=>{ kanbanDragging=false; }, 300);
                const taskId = evt.item.dataset.taskId;
                const newColStatus = evt.to.dataset.colStatus;
                const task = tasksData.find(t=>t.task_id===taskId);
                if(!task) return;

                // Check if status changed (moved between columns)
                const statusChanged = task.status !== newColStatus;
                if(statusChanged){
                    // === VALIDASI FASE SATU ARAH (Kanban) ===
                    const oldLevel = statusOrder[task.status] ?? 0;
                    const newLevel = statusOrder[newColStatus] ?? 0;
                    const isKepalaDivisi = currentUser && currentUser.role === 'Kepala Divisi';
                    if(newLevel < oldLevel && !isKepalaDivisi){
                        // Visual rollback: kembalikan kartu ke kolom asal
                        evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
                        Swal.fire({
                            icon: 'warning',
                            title: 'Tidak Diizinkan',
                            text: 'Tugas tidak bisa dikembalikan ke fase sebelumnya. Hanya Kepala Divisi yang dapat memundurkan status.',
                            confirmButtonColor: '#22c55e'
                        });
                        return;
                    }

                    task.status = newColStatus;
                    toast('info','Mengupdate status...');
                    const res = await apiCall('update_task_status',{task_id:taskId, status:newColStatus});
                    if(res.success){
                        toast('success',res.message);
                    } else {
                        Swal.fire('Gagal',res.message,'error');
                    }
                    await refreshTasks();
                    return;
                }

                // Same column — just reorder
                saveKanbanOrder();
            }
        });
    });
}

function saveKanbanOrder(){
    const order=[];
    let idx=0;
    document.querySelectorAll('.kanban-items').forEach(col=>{
        col.querySelectorAll('.kanban-card').forEach(card=>{
            const tid=card.dataset.taskId;
            if(tid){
                order.push({task_id:tid, urutan:idx});
                // Update local data too
                const t=tasksData.find(x=>x.task_id===tid);
                if(t) t.urutan=idx;
                idx++;
            }
        });
    });
    if(order.length){
        apiCall('reorder_tasks',{order:JSON.stringify(order)});
    }
}

// ---- Kanban Drag & Drop Handlers ----
function handleKanbanDragStart(e, taskId){
    e.dataTransfer.setData('text/plain', taskId);
    e.dataTransfer.effectAllowed='move';
    setTimeout(()=>e.target.classList.add('dragging'),0);
}

function handleKanbanDragEnd(e){
    e.target.classList.remove('dragging');
    document.querySelectorAll('.kanban-col').forEach(c=>c.classList.remove('drag-over'));
}

function handleKanbanDragOver(e){
    e.preventDefault();
    e.dataTransfer.dropEffect='move';
}

function handleKanbanDragEnter(e){
    e.preventDefault();
    const col=e.target.closest('.kanban-col');
    if(col) col.classList.add('drag-over');
}

function handleKanbanDragLeave(e){
    const col=e.target.closest('.kanban-col');
    if(col && !col.contains(e.relatedTarget)) col.classList.remove('drag-over');
}

async function handleKanbanDrop(e, newStatus){
    e.preventDefault();
    document.querySelectorAll('.kanban-col').forEach(c=>c.classList.remove('drag-over'));
    const taskId=e.dataTransfer.getData('text/plain');
    if(!taskId) return;
    const task=tasksData.find(t=>t.task_id===taskId);
    if(!task) return;
    if(task.status===newStatus) return;
    // === VALIDASI FASE SATU ARAH (Drop) ===
    const statusOrder={'Belum Dimulai':0,'Sedang Diproses':1,'Selesai':2};
    const oldLevel=statusOrder[task.status]??0;
    const newLevel=statusOrder[newStatus]??0;
    const isKepalaDivisi=currentUser&&currentUser.role==='Kepala Divisi';
    if(newLevel<oldLevel&&!isKepalaDivisi){
        Swal.fire({icon:'warning',title:'Tidak Diizinkan',text:'Tugas tidak bisa dikembalikan ke fase sebelumnya. Hanya Kepala Divisi yang dapat memundurkan status.',confirmButtonColor:'#22c55e'});
        filterTasks();
        return;
    }
    task.status=newStatus;
    filterTasks();
    toast('info','Mengupdate status...');
    const res=await apiCall('update_task_status',{task_id:taskId,status:newStatus});
    if(res.success){
        toast('success',res.message);
        refreshTasks();
    } else {
        Swal.fire('Gagal',res.message,'error');
        refreshTasks();
    }
}


// ========== TASK CRUD ==========
function showTaskModal(id){
    document.getElementById('taskForm').reset(); populateAssigneeDropdowns();
    if(id){
        document.getElementById('taskModalTitle').textContent='Edit Tugas';
        document.getElementById('statusGroup').classList.remove('hidden');
        const t=tasksData.find(x=>x.task_id===id); if(!t) return;
        document.getElementById('taskId').value=t.task_id;
        document.getElementById('taskJudul').value=t.judul;
        document.getElementById('taskDeskripsi').value=t.deskripsi||'';
        document.getElementById('taskTipe').value=t.tipe;
        document.getElementById('taskAssignee').value=t.assignee;
        document.getElementById('taskPrioritas').value=t.prioritas;
        try{ document.getElementById('taskBatasWaktu').value=new Date(t.batas_waktu).toISOString().split('T')[0]; }catch(e){}
        document.getElementById('taskStatus').value=t.status;
        // === FILTER OPSI STATUS BERDASARKAN FASE & ROLE ===
        const statusOrder={'Belum Dimulai':0,'Sedang Diproses':1,'Selesai':2};
        const curLevel=statusOrder[t.status]??0;
        const isKepalaDivisi=currentUser&&currentUser.role==='Kepala Divisi';
        const selStatus=document.getElementById('taskStatus');
        Array.from(selStatus.options).forEach(opt=>{
            const optLevel=statusOrder[opt.value]??0;
            if(!isKepalaDivisi && optLevel<curLevel){
                opt.disabled=true;
                opt.style.display='none';
            } else {
                opt.disabled=false;
                opt.style.display='';
            }
        });
    } else {
        document.getElementById('taskModalTitle').textContent='Tambah Tugas Baru';
        document.getElementById('taskId').value='';
        document.getElementById('statusGroup').classList.add('hidden');
        // Reset all status options to enabled
        const selStatus=document.getElementById('taskStatus');
        Array.from(selStatus.options).forEach(opt=>{ opt.disabled=false; opt.style.display=''; });
        // Auto-select current user as assignee
        if(currentUser) document.getElementById('taskAssignee').value=currentUser.nama;
    }
    openModal('taskModal');
}

async function handleTaskSubmit(e){
    e.preventDefault();
    const btn=document.getElementById('btnSaveTask'); btn.disabled=true; btn.innerHTML='<span class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin-custom"></span>';
    const res = await apiCall('save_task', {
        task_id: document.getElementById('taskId').value,
        judul: document.getElementById('taskJudul').value,
        deskripsi: document.getElementById('taskDeskripsi').value,
        tipe: document.getElementById('taskTipe').value,
        assignee: document.getElementById('taskAssignee').value,
        prioritas: document.getElementById('taskPrioritas').value,
        batas_waktu: document.getElementById('taskBatasWaktu').value,
        status: document.getElementById('taskStatus').value||'Belum Dimulai'
    });
    btn.disabled=false; btn.innerHTML='Simpan';
    if(res.success){ closeModal('taskModal'); toast('success',res.message); refreshTasks(); }
    else Swal.fire('Gagal',res.message,'error');
}

async function changeStatus(id,s){
    showLoader(); document.getElementById('statusDropdownMenu').classList.add('hidden');
    const r=await apiCall('update_task_status',{task_id:id,status:s});
    if(r.success){ toast('success',r.message); refreshTasks(); } else { hideLoader(); Swal.fire('Gagal',r.message,'error'); }
}

async function deleteTaskConfirm(id){
    const r=await Swal.fire({title:'Hapus Tugas?',text:'Data tidak bisa dikembalikan!',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'Hapus'});
    if(r.isConfirmed){ showLoader(); const res=await apiCall('delete_task',{task_id:id}); if(res.success){ toast('success',res.message); refreshTasks(); } else { hideLoader(); Swal.fire('Gagal',res.message,'error'); } }
}

async function refreshTasks(){
    const r=await apiCall('get_tasks'); hideLoader();
    if(r.success){ tasksData=r.data; filterTasks(); applyDashboardFilter(); } else toast('error','Gagal memuat tugas');
}

function showDetail(id){
    const t=tasksData.find(x=>x.task_id===id); if(!t) return;
    const pc=t.prioritas==='Tinggi'?'from-red-500 to-red-400 text-white':t.prioritas==='Sedang'?'from-amber-500 to-amber-400 text-black':'from-emerald-500 to-emerald-400 text-white';
    const sc=t.status==='Selesai'?'bg-emerald-500':t.status==='Sedang Diproses'?'bg-blue-500':'bg-slate-500';
    // Duration display
    let durasiDisplay='-';
    let durasiHtml='';
    if(t.status==='Sedang Diproses' && t.waktu_mulai){
        durasiDisplay=`<span class="live-timer text-blue-500 font-bold" data-live-timer="${t.waktu_mulai}">${formatDuration(getElapsedSeconds(t.waktu_mulai))}</span> <span class="text-xs text-blue-400">(berjalan)</span>`;
    } else if(t.durasi_kerja){
        durasiDisplay=`<span class="text-emerald-500 font-bold">${formatDuration(t.durasi_kerja)}</span>`;
    }
    document.getElementById('detailBody').innerHTML=`
        <h5 class="font-bold text-lg mb-3">${t.judul}</h5>
        <div class="flex gap-2 mb-4"><span class="text-xs font-semibold px-3 py-1 rounded-full bg-gradient-to-r ${pc}">${t.prioritas}</span><span class="text-xs font-medium px-3 py-1 rounded-full ${sc} text-white">${t.status}</span></div>
        <table class="w-full text-sm"><tbody>
            <tr class="border-b border-slate-100 dark:border-slate-700"><td class="py-2 text-slate-500 w-28">ID</td><td class="py-2"><code class="text-xs bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded">${t.task_id}</code></td></tr>
            <tr class="border-b border-slate-100 dark:border-slate-700"><td class="py-2 text-slate-500">Deskripsi</td><td class="py-2">${t.deskripsi||'-'}</td></tr>
            <tr class="border-b border-slate-100 dark:border-slate-700"><td class="py-2 text-slate-500">Tipe</td><td class="py-2">${t.tipe}</td></tr>
            <tr class="border-b border-slate-100 dark:border-slate-700"><td class="py-2 text-slate-500">Assignee</td><td class="py-2">${t.assignee}</td></tr>
            <tr class="border-b border-slate-100 dark:border-slate-700"><td class="py-2 text-slate-500">Deadline</td><td class="py-2">${fmtDate(t.batas_waktu)}</td></tr>
            <tr class="border-b border-slate-100 dark:border-slate-700"><td class="py-2 text-slate-500">Dibuat</td><td class="py-2">${t.tgl_dibuat||'-'}</td></tr>
            <tr class="border-b border-slate-100 dark:border-slate-700"><td class="py-2 text-slate-500"><i class="bi bi-play-circle text-blue-500 mr-1"></i>Mulai Dikerjakan</td><td class="py-2">${t.waktu_mulai||'-'}</td></tr>
            <tr class="border-b border-slate-100 dark:border-slate-700"><td class="py-2 text-slate-500"><i class="bi bi-stopwatch text-amber-500 mr-1"></i>Durasi Pengerjaan</td><td class="py-2">${durasiDisplay}</td></tr>
            <tr><td class="py-2 text-slate-500"><i class="bi bi-check-circle text-emerald-500 mr-1"></i>Selesai</td><td class="py-2">${t.tgl_selesai||'-'}</td></tr>
        </tbody></table>`;
    openModal('detailModal');
}
window.editTask=showTaskModal;

// ========== USER / PERSONNEL MANAGEMENT ==========
async function loadUsers(){
    if(!currentUser||!['Kepala Divisi','Kepala Unit'].includes(currentUser.role)) return;
    showLoader();
    const r=await apiCall('get_users'); hideLoader();
    if(r.success){ usersData=r.data||[]; renderUserTable(); populateAssigneeDropdowns(); } else toast('error','Gagal memuat personil');
}

function renderUserTable(){
    const tbody=document.getElementById('userTableBody'); if(!tbody) return;
    tbody.innerHTML=usersData.map(u=>{
        const rc=u.role==='Kepala Divisi'?'bg-red-500':u.role==='Kepala Unit'?'bg-blue-500':'bg-slate-500';
        return `<tr class="border-b border-slate-100 dark:border-slate-700/50 hover:bg-brand-50/50 dark:hover:bg-brand-900/10 transition">
        <td class="px-4 py-3 font-semibold"><i class="bi bi-person-circle mr-2 text-brand-500"></i>${u.nama_lengkap}</td>
        <td class="px-4 py-3 hidden md:table-cell text-slate-500">${u.username}</td>
        <td class="px-4 py-3 hidden lg:table-cell">${u.posisi||'-'}</td>
        <td class="px-4 py-3"><span class="text-xs font-medium px-3 py-1 rounded-full ${rc} text-white">${u.role}</span></td>
        <td class="px-4 py-3 hidden md:table-cell">${u.supervisor_nama||'-'}</td>
        <td class="px-4 py-3"><span class="text-xs font-medium px-3 py-1 rounded-full ${u.status==='Aktif'?'bg-emerald-500':'bg-slate-500'} text-white">${u.status}</span></td>
        <td class="px-4 py-3 text-center whitespace-nowrap"><button class="p-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition mr-1" onclick="editUser('${u.user_id}')"><i class="bi bi-pencil text-sm"></i></button><button class="p-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600 transition" onclick="deleteUserConfirm('${u.user_id}')"><i class="bi bi-trash text-sm"></i></button></td></tr>`;
    }).join('');
}

function toggleSupervisorField(){
    const role=document.getElementById('userRoleSelect').value;
    document.getElementById('supervisorGroup').classList.toggle('hidden', role==='Kepala Divisi');
}

async function showUserModal(id){
    document.getElementById('userForm').reset();
    // Load supervisors for dropdown
    try{
        const r=await apiCall('get_supervisors');
        if(r.success){
            let opts='<option value="">- Tidak Ada -</option>';
            r.data.forEach(s=>{ opts+=`<option value="${s.id}">${s.nama_lengkap} (${s.role})</option>`; });
            document.getElementById('userSupervisor').innerHTML=opts;
        }
    }catch(e){}
    // Kepala Unit: force Staff role
    if(currentUser && currentUser.role==='Kepala Unit'){
        document.getElementById('roleGroup').classList.add('hidden');
        document.getElementById('supervisorGroup').classList.add('hidden');
    } else {
        document.getElementById('roleGroup').classList.remove('hidden');
        document.getElementById('supervisorGroup').classList.remove('hidden');
    }
    if(id){
        document.getElementById('userModalTitle').textContent='Edit Personil';
        document.getElementById('userStatusGroup').classList.remove('hidden');
        document.getElementById('pwdReq').classList.add('hidden');
        document.getElementById('pwdHint').classList.remove('hidden');
        document.getElementById('userPassword').removeAttribute('required');
        const u=usersData.find(x=>x.user_id===id); if(!u) return;
        document.getElementById('userId').value=u.user_id;
        document.getElementById('userUsername').value=u.username;
        document.getElementById('userNama').value=u.nama_lengkap;
        document.getElementById('userEmail').value=u.email||'';
        document.getElementById('userPosisi').value=u.posisi||'';
        document.getElementById('userRoleSelect').value=u.role;
        document.getElementById('userSupervisor').value=u.supervisor_id||'';
        document.getElementById('userStatusSelect').value=u.status;
        toggleSupervisorField();
    } else {
        document.getElementById('userModalTitle').textContent='Tambah Personil';
        document.getElementById('userId').value='';
        document.getElementById('userStatusGroup').classList.add('hidden');
        document.getElementById('pwdReq').classList.remove('hidden');
        document.getElementById('pwdHint').classList.add('hidden');
        document.getElementById('userPassword').setAttribute('required','');
        toggleSupervisorField();
    }
    openModal('userModal');
}

async function handleUserSubmit(e){
    e.preventDefault(); showLoader();
    const res=await apiCall('save_user',{
        user_id:document.getElementById('userId').value, username:document.getElementById('userUsername').value,
        password:document.getElementById('userPassword').value, nama_lengkap:document.getElementById('userNama').value,
        email:document.getElementById('userEmail').value, posisi:document.getElementById('userPosisi').value,
        role:document.getElementById('userRoleSelect').value,
        supervisor_id:document.getElementById('userSupervisor').value,
        status:document.getElementById('userStatusSelect').value||'Aktif'
    });
    hideLoader();
    if(res.success){ closeModal('userModal'); toast('success',res.message); loadUsers(); } else Swal.fire('Gagal',res.message,'error');
}

async function deleteUserConfirm(id){
    const r=await Swal.fire({title:'Hapus Personil?',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'Hapus'});
    if(r.isConfirmed){ showLoader(); const res=await apiCall('delete_user',{user_id:id}); hideLoader(); if(res.success){ toast('success',res.message); loadUsers(); } else Swal.fire('Gagal',res.message,'error'); }
}
window.editUser=showUserModal;

// ========== EXPORT ==========
async function exportData(){
    if(!tasksData.length) return Swal.fire('Info','Tidak ada data','info');
    const res=await apiCall('export_csv');
    if(res.success){
        const blob=new Blob([res.csv],{type:'text/csv;charset=utf-8;'});
        const a=document.createElement('a'); a.href=URL.createObjectURL(blob);
        a.download='TaskFlow_Export_'+new Date().toISOString().split('T')[0]+'.csv';
        document.body.appendChild(a); a.click(); a.remove();
    }
}

// ========== BACKUP ==========
async function handleBackup(){
    const r=await Swal.fire({title:'Backup Data?',text:'Data akan diexport sebagai file SQL.',icon:'question',showCancelButton:true,confirmButtonText:'Backup Sekarang',confirmButtonColor:'#10b981'});
    if(r.isConfirmed){
        showLoader(); const res=await apiCall('backup_data'); hideLoader();
        if(res.success){
            const blob=new Blob([res.sql],{type:'text/sql;charset=utf-8;'});
            const a=document.createElement('a'); a.href=URL.createObjectURL(blob);
            a.download='TaskFlow_Backup_'+new Date().toISOString().split('T')[0]+'.sql';
            document.body.appendChild(a); a.click(); a.remove();
            Swal.fire('Berhasil','File backup berhasil diunduh!','success');
        } else Swal.fire('Gagal',res.message,'error');
    }
}

// ========== GOAL & STRATEGI ==========
function getCurrentPeriode(){
    const now=new Date();
    return now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0');
}

function getPeriodeLabel(p){
    if(!p) return '';
    const [y,m]=p.split('-');
    const months=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return months[parseInt(m)]+' '+y;
}

function setGoalTab(tab){
    activeGoalTab=tab;
    document.querySelectorAll('.goal-tab-btn').forEach(b=>{
        b.classList.toggle('active',b.dataset.gtab===tab);
    });
    renderGoalCard();
}

function renderGoalCard(){
    const el=document.getElementById('goalContent');
    const lbl=document.getElementById('goalPeriodeLabel');
    const periode=getCurrentPeriode();
    if(lbl) lbl.textContent=getPeriodeLabel(periode);

    if(activeGoalTab==='Tim'){
        renderTeamGoals();
        return;
    }

    const filtered=goalsData.filter(g=>g.scope===activeGoalTab);
    if(!filtered.length){
        const scopeLabel=activeGoalTab==='Divisi'?'divisi':activeGoalTab==='Unit'?'unit':'personal';
        el.innerHTML=`<div class="text-center py-6"><i class="bi bi-bullseye text-3xl text-slate-300 dark:text-slate-600"></i><p class="text-slate-400 text-xs mt-2">Belum ada goal ${scopeLabel} untuk periode ini.</p></div>`;
        return;
    }
    let html='';
    filtered.forEach(g=>{
        const canEdit=currentUser&&(currentUser.role==='Kepala Divisi'||(g.scope_owner_id==currentUser.db_id)||(g.created_by_id==currentUser.db_id));
        const scopeIcon=g.scope==='Divisi'?'bi-building':g.scope==='Unit'?'bi-people':'bi-person';
        const scopeColor=g.scope==='Divisi'?'from-indigo-500 to-purple-500':g.scope==='Unit'?'from-blue-500 to-cyan-500':'from-emerald-500 to-teal-500';
        const strategiItems=(g.strategi||'').split('\n').filter(s=>s.trim());
        // Badge jika goal dibuat oleh atasan untuk user ini
        const assignedBadge=g.creator_nama?`<span class="text-[10px] bg-violet-100 dark:bg-violet-500/20 text-violet-600 dark:text-violet-300 px-2 py-0.5 rounded-full ml-1"><i class="bi bi-person-check mr-0.5"></i>Dari ${g.creator_nama}</span>`:'';
        html+=`<div class="glass rounded-xl border border-slate-200/60 dark:border-white/[0.06] p-4 mb-3 hover:shadow-md transition-all">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-7 h-7 rounded-lg bg-gradient-to-br ${scopeColor} flex items-center justify-center text-white text-xs"><i class="bi ${scopeIcon}"></i></span>
                        <h6 class="font-bold text-sm">${g.judul}</h6>${assignedBadge}
                    </div>
                    ${g.deskripsi?`<p class="text-xs text-slate-500 mb-2 ml-9">${g.deskripsi}</p>`:''}
                    ${strategiItems.length?`<div class="ml-9 space-y-1">${strategiItems.map((s,i)=>`<div class="flex items-start gap-2 text-xs"><span class="w-5 h-5 rounded-md bg-brand-50 dark:bg-brand-500/10 text-brand-500 flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5">${i+1}</span><span class="text-slate-600 dark:text-slate-300">${s}</span></div>`).join('')}</div>`:''}
                </div>
                ${canEdit?`<div class="flex gap-1 shrink-0">
                    <button onclick="editGoal('${g.goal_id}')" class="p-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition" title="Edit"><i class="bi bi-pencil text-xs"></i></button>
                    <button onclick="deleteGoalConfirm('${g.goal_id}')" class="p-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600 transition" title="Hapus"><i class="bi bi-trash text-xs"></i></button>
                </div>`:''}
            </div>
            <div class="flex items-center gap-2 mt-3 ml-9 text-[10px] text-slate-400"><i class="bi bi-person-circle"></i>${g.owner_nama} · ${g.owner_role}</div>
        </div>`;
    });
    el.innerHTML=html;
}

function renderTeamGoals(){
    const el=document.getElementById('goalContent');
    // Tampilkan semua goal personal anggota tim (bukan milik sendiri)
    const teamGoals=goalsData.filter(g=>{
        if(g.scope!=='Personal') return false;
        // Kepala Divisi: lihat semua personal kecuali miliknya
        if(currentUser.role==='Kepala Divisi') return g.scope_owner_id!=currentUser.db_id;
        // Kepala Unit: hanya bawahan
        return g.scope_owner_id!=currentUser.db_id;
    });
    if(!teamGoals.length){
        el.innerHTML=`<div class="text-center py-6">
            <i class="bi bi-people text-3xl text-slate-300 dark:text-slate-600"></i>
            <p class="text-slate-400 text-xs mt-2">Belum ada target personal untuk anggota tim periode ini.</p>
            <button onclick="showGoalModal('Personal')" class="mt-3 px-4 py-1.5 text-xs bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition"><i class="bi bi-plus-lg mr-1"></i>Tambah Target Tim</button>
        </div>`;
        return;
    }
    // Kelompokkan per anggota tim
    const grouped={};
    teamGoals.forEach(g=>{
        const key=g.owner_nama;
        if(!grouped[key]) grouped[key]={role:g.owner_role, goals:[]};
        grouped[key].goals.push(g);
    });
    let html='';
    Object.entries(grouped).forEach(([nama, data])=>{
        const roleColor=data.role==='Kepala Unit'?'bg-blue-500':data.role==='Kepala Divisi'?'bg-red-500':'bg-slate-400';
        html+=`<div class="glass rounded-xl border border-slate-200/60 dark:border-white/[0.06] p-4 mb-3">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-brand-500 to-emerald-400 flex items-center justify-center text-white font-bold text-sm">${nama.charAt(0).toUpperCase()}</div>
                <div>
                    <div class="font-semibold text-sm">${nama}</div>
                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-full ${roleColor} text-white">${data.role}</span>
                </div>
                <button onclick="showGoalModal('Personal','${nama}')" class="ml-auto p-1.5 rounded-lg bg-brand-500 text-white hover:bg-brand-600 transition text-xs" title="Tambah target untuk ${nama}"><i class="bi bi-plus-lg"></i></button>
            </div>
            <div class="space-y-2 ml-10">`;
        data.goals.forEach(g=>{
            const canEdit=currentUser&&(currentUser.role==='Kepala Divisi'||(g.created_by_id==currentUser.db_id));
            const strategiItems=(g.strategi||'').split('\n').filter(s=>s.trim());
            const assignedBadge=g.creator_nama?`<span class="text-[10px] bg-violet-100 dark:bg-violet-500/20 text-violet-600 dark:text-violet-300 px-2 py-0.5 rounded-full"><i class="bi bi-person-check mr-0.5"></i>Dari ${g.creator_nama}</span>`:'';
            html+=`<div class="bg-slate-50 dark:bg-navy-900/50 rounded-lg p-3 border border-slate-200/60 dark:border-white/[0.04]">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                        <div class="flex items-center gap-1 flex-wrap mb-1">
                            <span class="font-semibold text-sm">${g.judul}</span>${assignedBadge}
                        </div>
                        ${g.deskripsi?`<p class="text-xs text-slate-500 mb-1">${g.deskripsi}</p>`:''}
                        ${strategiItems.length?`<div class="space-y-0.5">${strategiItems.map((s,i)=>`<div class="flex items-start gap-1.5 text-xs"><span class="w-4 h-4 rounded bg-brand-50 dark:bg-brand-500/10 text-brand-500 flex items-center justify-center text-[9px] font-bold shrink-0 mt-0.5">${i+1}</span><span class="text-slate-600 dark:text-slate-300">${s}</span></div>`).join('')}</div>`:''}
                    </div>
                    ${canEdit?`<div class="flex gap-1 shrink-0">
                        <button onclick="editGoal('${g.goal_id}')" class="p-1 rounded-md bg-amber-500 text-white hover:bg-amber-600 transition" title="Edit"><i class="bi bi-pencil text-xs"></i></button>
                        <button onclick="deleteGoalConfirm('${g.goal_id}')" class="p-1 rounded-md bg-red-500 text-white hover:bg-red-600 transition" title="Hapus"><i class="bi bi-trash text-xs"></i></button>
                    </div>`:''}
                </div>
            </div>`;
        });
        html+='</div></div>';
    });
    el.innerHTML=html;
}

let teamMembersData = []; // Cache daftar anggota tim

async function loadTeamMembers(){
    if(!currentUser||!['Kepala Divisi','Kepala Unit'].includes(currentUser.role)) return;
    try{
        const r=await apiCall('get_team_members');
        if(r.success) teamMembersData=r.data||[];
    }catch(e){}
}

function onGoalScopeChange(){
    const scope=document.getElementById('goalScope').value;
    const isKepa=currentUser&&['Kepala Divisi','Kepala Unit'].includes(currentUser.role);
    const grp=document.getElementById('goalAssignedToGroup');
    if(scope==='Personal'&&isKepa&&teamMembersData.length){
        grp.classList.remove('hidden');
    } else {
        grp.classList.add('hidden');
    }
}

function populateTeamDropdown(selectedId=''){
    const sel=document.getElementById('goalAssignedTo');
    let opts='<option value="">&#128100; Saya Sendiri</option>';
    teamMembersData.forEach(m=>{
        const roleLabel=m.role==='Kepala Divisi'?'Kepala Divisi':m.role==='Kepala Unit'?'Kepala Unit':'Staff';
        opts+=`<option value="${m.id}" ${m.id==selectedId?'selected':''}>${m.nama_lengkap} (${roleLabel})</option>`;
    });
    sel.innerHTML=opts;
}

function showGoalModal(scope, preselectedMemberName){
    document.getElementById('goalForm').reset();
    document.getElementById('goalId').value='';
    document.getElementById('goalModalTitle').textContent='Tambah Target';
    document.getElementById('goalPeriode').value=getCurrentPeriode();
    // Set scope based on active tab or parameter
    const s=scope||(activeGoalTab==='Tim'?'Personal':activeGoalTab);
    document.getElementById('goalScope').value=s;
    // Restrict scope options based on role
    const scopeEl=document.getElementById('goalScope');
    Array.from(scopeEl.options).forEach(o=>{
        if(o.value==='Divisi') o.disabled=currentUser.role!=='Kepala Divisi';
        if(o.value==='Unit') o.disabled=!['Kepala Divisi','Kepala Unit'].includes(currentUser.role);
    });
    // Populate team dropdown & show/hide
    populateTeamDropdown();
    const isKepa=currentUser&&['Kepala Divisi','Kepala Unit'].includes(currentUser.role);
    const grp=document.getElementById('goalAssignedToGroup');
    if(s==='Personal'&&isKepa&&teamMembersData.length){
        grp.classList.remove('hidden');
        // Jika dipanggil dengan nama anggota (dari tab Tim), auto-select
        if(preselectedMemberName){
            const member=teamMembersData.find(m=>m.nama_lengkap===preselectedMemberName);
            if(member) document.getElementById('goalAssignedTo').value=member.id;
        }
    } else {
        grp.classList.add('hidden');
    }
    openModal('goalModal');
}

function editGoal(goalId){
    const g=goalsData.find(x=>x.goal_id===goalId); if(!g) return;
    document.getElementById('goalForm').reset();
    document.getElementById('goalId').value=g.goal_id;
    document.getElementById('goalModalTitle').textContent='Edit Target';
    document.getElementById('goalScope').value=g.scope;
    document.getElementById('goalJudul').value=g.judul;
    document.getElementById('goalDeskripsi').value=g.deskripsi||'';
    document.getElementById('goalStrategi').value=g.strategi||'';
    document.getElementById('goalPeriode').value=g.periode;
    const scopeEl=document.getElementById('goalScope');
    Array.from(scopeEl.options).forEach(o=>{
        if(o.value==='Divisi') o.disabled=currentUser.role!=='Kepala Divisi';
        if(o.value==='Unit') o.disabled=!['Kepala Divisi','Kepala Unit'].includes(currentUser.role);
    });
    // Handle team assignment field
    populateTeamDropdown(g.scope_owner_id);
    const isKepa=currentUser&&['Kepala Divisi','Kepala Unit'].includes(currentUser.role);
    const grp=document.getElementById('goalAssignedToGroup');
    if(g.scope==='Personal'&&isKepa&&teamMembersData.length){
        grp.classList.remove('hidden');
    } else {
        grp.classList.add('hidden');
    }
    openModal('goalModal');
}

async function handleGoalSubmit(e){
    e.preventDefault();
    const btn=document.getElementById('btnSaveGoal'); btn.disabled=true; btn.innerHTML='<span class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin-custom"></span>';
    const scope=document.getElementById('goalScope').value;
    const assignedToId=document.getElementById('goalAssignedTo').value||'';
    const res=await apiCall('save_goal',{
        goal_id:document.getElementById('goalId').value,
        judul:document.getElementById('goalJudul').value,
        deskripsi:document.getElementById('goalDeskripsi').value,
        strategi:document.getElementById('goalStrategi').value,
        scope:scope,
        periode:document.getElementById('goalPeriode').value,
        assigned_to_id:assignedToId
    });
    btn.disabled=false; btn.innerHTML='Simpan';
    if(res.success){ closeModal('goalModal'); toast('success',res.message); refreshGoals(); }
    else Swal.fire('Gagal',res.message,'error');
}

async function deleteGoalConfirm(goalId){
    const r=await Swal.fire({title:'Hapus Goal?',text:'Goal dan strategi akan dihapus!',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'Hapus'});
    if(r.isConfirmed){
        showLoader(); const res=await apiCall('delete_goal',{goal_id:goalId}); hideLoader();
        if(res.success){ toast('success',res.message); refreshGoals(); } else Swal.fire('Gagal',res.message,'error');
    }
}

async function refreshGoals(){
    const r=await apiCall('get_goals',{periode:getCurrentPeriode()});
    if(r.success){ goalsData=r.data||[]; renderGoalCard(); }
}

// Panggil loadTeamMembers setelah data utama dimuat
const _origLoadAllData=loadAllData;
async function loadAllData(){
    showLoader();
    try {
        const res = await apiCall('get_all_data');
        hideLoader();
        if(res.success){
            usersData=res.users||[]; tasksData=res.tasks||[]; goalsData=res.goals||[];
            populateAssigneeDropdowns(); renderUserTable(); filterTasks();
            if(res.dashboard && res.dashboard.recentActivities) renderActivityLog(res.dashboard.recentActivities);
            applyDashboardFilter();
            renderGoalCard();
            // Load team members setelah data utama untuk Kepala
            if(currentUser&&['Kepala Divisi','Kepala Unit'].includes(currentUser.role)){
                loadTeamMembers();
            }
        }
    } catch(e){ hideLoader(); toast('error','Gagal memuat data'); }
}
