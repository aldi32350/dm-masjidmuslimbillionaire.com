// ==============================================================================
// TASKFLOW PRO v3.4 — Teks Siap Salin (EOD) & Bot Telegram (frontend)
// FILE       : fitur.js  (dimuat setelah app.js)
// ==============================================================================

function escHtml(s){ const d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML; }
function todayYmd(){ const d=new Date(); return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }

async function copyText(text){
    try { await navigator.clipboard.writeText(text); return true; }
    catch(e){
        const ta=document.createElement('textarea'); ta.value=text; ta.style.position='fixed'; ta.style.opacity='0';
        document.body.appendChild(ta); ta.select(); let ok=false; try{ ok=document.execCommand('copy'); }catch(_){} ta.remove(); return ok;
    }
}
async function copyField(id){ const ok=await copyText(document.getElementById(id).value); toast(ok?'success':'error', ok?'Disalin!':'Gagal menyalin'); }

// ========== TEKS SIAP SALIN (EOD) ==========
let eodTimer=null, eodLoadedFor=null;

function eodSelectableUsers(){
    if(!currentUser) return [];
    const aktif=(usersData||[]).filter(u=>u.status==='Aktif');
    if(currentUser.role==='Kepala Divisi') return aktif;
    if(currentUser.role==='Kepala Unit') return aktif.filter(u=>String(u.id)===String(currentUser.db_id)||String(u.supervisor_id)===String(currentUser.db_id));
    return aktif.filter(u=>String(u.id)===String(currentUser.db_id));
}

function initEodView(){
    const sel=document.getElementById('eodUser');
    const list=eodSelectableUsers();
    const prev=sel.value||String(currentUser.db_id);
    sel.innerHTML=list.map(u=>`<option value="${u.id}">${escHtml(u.nama_lengkap)}${u.posisi?' — '+escHtml(u.posisi):''}</option>`).join('');
    if(!list.length) sel.innerHTML=`<option value="${currentUser.db_id}">${escHtml(currentUser.nama)}</option>`;
    sel.value=[...sel.options].some(o=>o.value===prev)?prev:String(currentUser.db_id);
    document.getElementById('eodUserWrap').classList.toggle('hidden', list.length<=1);
    const d=document.getElementById('eodDate'); if(!d.value) d.value=todayYmd();
    loadEod(eodLoadedFor!==sel.value);
}

// resetForm=true → isi ulang Unit & Target dari data tersimpan milik personil terpilih
async function loadEod(resetForm){
    const uid=document.getElementById('eodUser').value||currentUser.db_id;
    const payload={ user_db_id:uid, tanggal:document.getElementById('eodDate').value||todayYmd(),
        problem:document.getElementById('eodProblem').value, solusi:document.getElementById('eodSolusi').value,
        include_progress:document.getElementById('eodProgress').checked?'1':'0' };
    if(!resetForm){ payload.unit=document.getElementById('eodUnit').value; payload.target=document.getElementById('eodTarget').value; }
    const r=await apiCall('eod_get',payload);
    if(!r.success){ toast('error',r.message||'Gagal memuat EOD'); return; }
    if(resetForm){
        document.getElementById('eodUnit').value=r.data.saved_unit||'';
        document.getElementById('eodTarget').value=r.data.saved_target||'';
        document.getElementById('eodProblem').value=''; document.getElementById('eodSolusi').value='';
        eodLoadedFor=String(uid);
    }
    document.getElementById('eodText').value=r.data.text;
    document.getElementById('eodStats').textContent=`${r.data.done_count} tugas selesai · ${r.data.progress_count} sedang diproses`;
}

function eodChanged(){ clearTimeout(eodTimer); eodTimer=setTimeout(()=>loadEod(false),400); }

async function copyEod(){
    const ok=await copyText(document.getElementById('eodText').value);
    const b=document.getElementById('btnCopyEod');
    if(ok){ b.innerHTML='<i class="bi bi-check2 mr-1"></i>Tersalin!'; setTimeout(()=>{ b.innerHTML='<i class="bi bi-clipboard mr-1"></i>Salin Teks'; },1800); }
    else toast('error','Gagal menyalin, silakan blok teks lalu Ctrl+C');
}

async function saveEodTemplate(){
    const r=await apiCall('eod_save_template',{ user_db_id:document.getElementById('eodUser').value||currentUser.db_id,
        unit:document.getElementById('eodUnit').value, target:document.getElementById('eodTarget').value });
    toast(r.success?'success':'error', r.message);
}

// ========== HUBUNGKAN TELEGRAM (semua user) ==========
async function showTgLinkModal(regenerate){
    const body=document.getElementById('tgLinkBody');
    openModal('tgLinkModal');
    body.innerHTML='<div class="text-slate-400">Memuat...</div>';
    const r=await apiCall('tg_link_info', regenerate?{regenerate:'1'}:{});
    if(!r.success){ body.innerHTML=`<div class="text-red-500">${escHtml(r.message)}</div>`; return; }
    if(!r.enabled){ body.innerHTML='<p>Bot Telegram belum diaktifkan oleh Kepala Divisi.</p>'; return; }
    const bot=r.bot_username;
    const link=bot?`https://t.me/${encodeURIComponent(bot)}?start=${encodeURIComponent(r.code)}`:'';
    body.innerHTML=`
      ${r.linked?'<div class="rounded-xl bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 p-3"><i class="bi bi-check-circle-fill mr-1"></i>Akun Anda sudah terhubung ke Telegram.</div>':''}
      <p>${r.linked?'Untuk pindah ke akun Telegram lain':'Tekan tombol di bawah'}, lalu tekan <b>Start</b> di Telegram.</p>
      ${link?`<a href="${link}" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-sky-500 hover:bg-sky-600 text-white font-semibold transition"><i class="bi bi-telegram"></i>Buka @${escHtml(bot)}</a>`:''}
      <p class="text-xs text-slate-400">Atau kirim pesan ini ke bot: <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-navy-900 select-all">/start ${escHtml(r.code)}</code></p>
      <div class="flex justify-between gap-2 pt-1">
        <button onclick="showTgLinkModal(true)" class="text-xs text-slate-500 hover:underline">Buat kode baru</button>
        ${r.linked?'<button onclick="unlinkTg()" class="text-xs text-red-500 hover:underline">Putuskan sambungan</button>':''}
      </div>`;
}

async function unlinkTg(dbId){
    const c=await Swal.fire({title:'Putuskan Telegram?',icon:'question',showCancelButton:true,confirmButtonText:'Ya, putuskan',confirmButtonColor:'#ef4444'});
    if(!c.isConfirmed) return;
    const r=await apiCall('tg_unlink', dbId?{user_db_id:dbId}:{});
    toast(r.success?'success':'error', r.message);
    if(dbId) loadTgSettings(); else closeModal('tgLinkModal');
}

// ========== ADMIN BOT TELEGRAM (Kepala Divisi) ==========
async function loadTgSettings(){
    const r=await apiCall('tg_get_settings');
    if(!r.success){ toast('error',r.message); return; }
    const d=r.data;
    document.getElementById('tgToken').value='';
    document.getElementById('tgTokenInfo').innerHTML=d.has_token?`Tersimpan: <code>${escHtml(d.token_masked)}</code>${d.bot_username?' · @'+escHtml(d.bot_username):''} — kosongkan jika tidak ingin mengganti.`:'Belum ada token.';
    document.getElementById('tgGroup').value=d.group_chat_id||'';
    document.getElementById('tgNotifyAssign').checked=d.notify_assign;
    document.getElementById('tgNotifySup').checked=d.notify_done_supervisor;
    document.getElementById('tgNotifyGroup').checked=d.notify_done_group;
    document.getElementById('tgCronPagi').value=`curl -s "${d.cron_pagi}" > /dev/null`;
    document.getElementById('tgCronSore').value=`curl -s "${d.cron_sore}" > /dev/null`;

    const wh=d.webhook, badge=document.getElementById('tgStatusBadge'), info=document.getElementById('tgWebhookInfo');
    const active=wh && wh.url && wh.url===d.webhook_target;
    badge.className='text-xs px-3 py-1 rounded-full '+(active?'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400':'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400');
    badge.innerHTML=active?'<i class="bi bi-circle-fill text-[8px] mr-1"></i>Bot aktif':(d.has_token?'Webhook belum dipasang':'Belum diatur');
    info.innerHTML=!d.has_token?'<span class="text-slate-400">Simpan token dulu.</span>'
        : wh&&wh.error?`<span class="text-red-500">${escHtml(wh.error)}</span>`
        : `<div>Alamat: <code class="break-all">${escHtml(wh&&wh.url||'(belum dipasang)')}</code></div>`
          +(wh&&wh.last_error?`<div class="text-red-500">Error terakhir: ${escHtml(wh.last_error)}</div>`:'')
          +(wh&&wh.url&&!active?`<div class="text-amber-600">Webhook mengarah ke alamat lain. Tekan Pasang Webhook.</div>`:'');

    const linked=d.users.filter(u=>+u.linked).length;
    document.getElementById('tgUserTable').innerHTML=`<tr><td colspan="3" class="pb-2 text-xs text-slate-400">${linked} dari ${d.users.length} personil terhubung</td></tr>`
      + d.users.map(u=>`<tr class="border-t border-slate-100 dark:border-slate-700/60">
        <td class="py-2 pr-2"><div class="font-medium">${escHtml(u.nama_lengkap)}</div><div class="text-[11px] text-slate-400">${escHtml(u.role)}${u.status!=='Aktif'?' · nonaktif':''}</div></td>
        <td class="py-2 text-xs">${+u.linked?'<span class="text-green-600"><i class="bi bi-telegram"></i> Terhubung</span>':'<span class="text-slate-400">Belum</span>'}</td>
        <td class="py-2 text-right">${+u.linked?`<button onclick="unlinkTg(${+u.id})" class="text-[11px] text-red-500 hover:underline">Putus</button>`:''}</td></tr>`).join('');
}

async function saveTgSettings(e){
    e.preventDefault(); showLoader();
    const r=await apiCall('tg_save_settings',{ bot_token:document.getElementById('tgToken').value.trim(),
        group_chat_id:document.getElementById('tgGroup').value.trim(),
        notify_assign:document.getElementById('tgNotifyAssign').checked?'1':'0',
        notify_done_supervisor:document.getElementById('tgNotifySup').checked?'1':'0',
        notify_done_group:document.getElementById('tgNotifyGroup').checked?'1':'0' });
    hideLoader();
    if(r.success){ toast('success',r.message); loadTgSettings(); } else Swal.fire('Gagal',r.message,'error');
}

async function setTgWebhook(){
    showLoader(); const r=await apiCall('tg_set_webhook'); hideLoader();
    if(r.success){ toast('success',r.message); loadTgSettings(); } else Swal.fire('Gagal',r.message,'error');
}

async function testTg(to){
    showLoader(); const r=await apiCall('tg_test',{to}); hideLoader();
    toast(r.success?'success':'error', r.message);
}
