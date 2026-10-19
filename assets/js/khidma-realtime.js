/**
 * khidma-realtime.js v4
 * window.KhidmaRT = { role, base, myId, me:{initials}, seed:{notif_id, msg_id, ...} }
 */
(function(){
'use strict';

const cfg  = window.KhidmaRT || {};
const BASE = cfg.base || '';
const ROLE = cfg.role || 'client';
const ME   = cfg.me  || {};
const seed = cfg.seed || {};

let lastNotifId    = seed.notif_id   || 0;
let lastMsgId      = seed.msg_id     || 0;
let lastServiceId  = seed.service_id || 0;
let lastUserId     = seed.user_id    || 0;

let chatWith   = null;
let lastChatId = 0;
let chatTimer  = null;
let initialized = false;  // after first successful poll, toasts fire for new items

/* ── Toast ── */
const Q = []; let busy = false;
function toast(msg, type, link) { Q.push({msg,type,link}); if(!busy) drain(); }
function drain() {
  if (!Q.length) { busy=false; return; }
  busy = true;
  const {msg,type,link} = Q.shift();
  const tc = document.getElementById('tc');
  if (!tc) { setTimeout(drain, 500); return; }
  const el = document.createElement('div');
  el.className = 'toast ' + (type||'ok');
  el.style.cssText = 'cursor:pointer;max-width:320px;word-break:break-word;';
  el.innerHTML = (type==='err' ? '❌ ' : type==='info' ? '🔔 ' : '✅ ') + esc(msg);
  if (link) el.onclick = () => location.href = link;
  tc.appendChild(el);
  requestAnimationFrame(() => el.style.opacity = '1');
  setTimeout(() => {
    el.style.opacity = '0';
    setTimeout(() => { el.remove(); setTimeout(drain, 150); }, 350);
  }, 4500);
}

/* ── Helpers ── */
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function nl2br(s) { return esc(s).replace(/\n/g,'<br>'); }
function badge(sel, n) {
  document.querySelectorAll(sel).forEach(el => {
    el.textContent = n > 99 ? '99+' : n;
    el.style.display = n > 0 ? '' : 'none';
  });
}
function dot(sel, on) {
  document.querySelectorAll(sel).forEach(el => el.style.display = on ? '' : 'none');
}
function ini(nom, prenom) { return ((prenom||'').charAt(0) + (nom||'').charAt(0)).toUpperCase(); }
function fmtTime(dt) {
  if (!dt) return '';
  const d = new Date(dt.replace(' ','T')), now = new Date(), p = n => String(n).padStart(2,'0');
  if (d.toDateString() === now.toDateString()) return p(d.getHours()) + ':' + p(d.getMinutes());
  return p(d.getDate()) + '/' + p(d.getMonth()+1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
}

/* ── Build bubble ── */
function makeBubble(msg, isMe) {
  const wc = ROLE==='technicien' ? 'mbw' : 'bubble-wrap';
  const tc = ROLE==='technicien' ? 'bt'  : 'btime';
  const av = isMe ? (ME.initials||'?') : ini(msg.nom, msg.prenom);

  let avHtml = '';
  if (ROLE === 'technicien') {
    const bg = isMe ? 'var(--primary)' : 'var(--primary-light)';
    const fg = isMe ? '#fff' : 'var(--primary)';
    avHtml = `<div class="mav" style="width:30px;height:30px;font-size:11px;flex-shrink:0;background:${bg};color:${fg}">${esc(av)}</div>`;
  } else {
    avHtml = `<div class="mini-av">${esc(av)}</div>`;
  }

  const el = document.createElement('div');
  el.className = wc + (isMe ? ' me' : '');
  el.dataset.msgid = msg.id_message || '';
  el.innerHTML = `${avHtml}
    <div class="bubble-inner">
      <div class="bubble ${isMe ? 'me' : 'other'}">${nl2br(msg.contenu||'')}</div>
      <div class="${tc}">${fmtTime(msg.data_envoi)}</div>
    </div>`;
  return el;
}

/* ── Chat polling (every 3s when chat open) ── */
function startChat(uid, lastId) {
  chatWith   = uid;
  lastChatId = lastId || 0;
  if (chatTimer) clearInterval(chatTimer);
  chatTimer = setInterval(fetchMsgs, 3000);
  fetchMsgs();
}

async function fetchMsgs() {
  if (!chatWith) return;
  try {
    const r = await fetch(
      `${BASE}api/messages.php?action=fetch&with=${chatWith}&last_id=${lastChatId}`,
      { credentials: 'same-origin', cache: 'no-store' }
    );
    if (!r.ok) return;
    const d = await r.json();
    if (!d.auth) return;
    if (!d.msgs || !d.msgs.length) return;

    const box = document.getElementById('chat-messages');
    if (!box) return;

    box.querySelectorAll('[data-placeholder]').forEach(e => e.remove());
    d.msgs.forEach(m => {
      const isMe = parseInt(m.id_expediteur) === (cfg.myId || 0);
      box.appendChild(makeBubble(m, isMe));
      lastChatId = Math.max(lastChatId, parseInt(m.id_message) || 0);
    });
    box.scrollTop = box.scrollHeight;
    badge('.nav-badge-msg', 0);
    dot('.msg-dot', false);
  } catch(e) {
    console.warn('[KhidmaRT] fetchMsgs error:', e);
  }
}

/* ── Intercept send form ── */
function setupSendForm() {
  const form = document.getElementById('chat-send-form');
  const ta   = document.getElementById('chat-textarea');
  if (!form || !ta) return;

  function resize() { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 120) + 'px'; }
  ta.addEventListener('input', resize);
  ta.addEventListener('keydown', e => { if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); doSend(); } });
  form.addEventListener('submit', e => { e.preventDefault(); doSend(); });

  async function doSend() {
    const dest = form.querySelector('input[name="id_destinataire"]');
    if (!dest || !ta.value.trim()) return;
    const contenu = ta.value.trim(), destId = dest.value;
    ta.value = ''; ta.style.height = 'auto';

    const box = document.getElementById('chat-messages');
    if (box) {
      box.querySelectorAll('[data-placeholder]').forEach(e => e.remove());
      box.appendChild(makeBubble({
        id_message: 'tmp_' + Date.now(),
        id_expediteur: cfg.myId,
        contenu,
        data_envoi: new Date().toISOString().replace('T',' ').slice(0,19),
        nom: '', prenom: ''
      }, true));
      box.scrollTop = box.scrollHeight;
    }

    try {
      const fd = new FormData();
      fd.append('action', 'send');
      fd.append('id_destinataire', destId);
      fd.append('contenu', contenu);
      const r = await fetch(`${BASE}api/messages.php`, { method:'POST', credentials:'same-origin', body:fd });
      const d = await r.json();
      if (d.ok) {
        lastChatId = Math.max(lastChatId, parseInt(d.id_message) || 0);
        lastMsgId  = Math.max(lastMsgId,  parseInt(d.id_message) || 0);
      } else {
        toast("Erreur lors de l'envoi", 'err');
      }
    } catch(e) {
      toast('Erreur réseau', 'err');
      console.warn('[KhidmaRT] send error:', e);
    }
  }
}

/* ── Inject new notif into open notifications tab ── */
function injectNotif(n) {
  const list = document.getElementById('notif-list');
  if (!list) return;
  const el = document.createElement('div');
  el.className = 'notif-item unread';
  el.innerHTML = `
    <div class="notif-icon">🔔</div>
    <div style="flex:1;min-width:0">
      <div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:2px">${esc(n.titre)}</div>
      <div style="font-size:12.5px;color:var(--muted)">${esc(n.contenu||'')}</div>
      <div style="font-size:11px;color:var(--muted);margin-top:4px">${fmtTime(n.date_notif)}</div>
    </div>`;
  list.prepend(el);
}

/* ── Main notification poll (every 5s) ── */
async function pollNotifs() {
  try {
    const p = new URLSearchParams({
      last_notif:   lastNotifId,
      last_msg:     lastMsgId,
      last_service: lastServiceId,
      last_user:    lastUserId,
    });
    const r = await fetch(`${BASE}api/poll.php?` + p, { credentials:'same-origin', cache:'no-store' });
    if (!r.ok) { console.warn('[KhidmaRT] poll HTTP', r.status); return; }

    const d = await r.json();
    if (!d.auth) { console.warn('[KhidmaRT] poll: not authenticated'); return; }

    /* ── Badges ── */
    badge('.nav-badge-notif', d.notif_count);
    dot('.notif-dot', d.notif_count > 0);

    if (!chatWith) {
      badge('.nav-badge-msg', d.msg_count);
      dot('.msg-dot', d.msg_count > 0);
    }

    if (d.demandes_count !== undefined) badge('.nav-badge-demandes', d.demandes_count);
    if (d.pending_verif  !== undefined) badge('.nav-badge-verif',    d.pending_verif);

    /* ── New notifications → toast + inject ── */
    if (initialized && d.new_notifs && d.new_notifs.length) {
      d.new_notifs.forEach(n => {
        toast(n.titre + (n.contenu ? ' — ' + String(n.contenu).substring(0,60) : ''), 'info', n.lien || null);
        injectNotif(n);
      });
    }
    if (d.new_notifs && d.new_notifs.length)
      lastNotifId = Math.max(lastNotifId, ...d.new_notifs.map(n => parseInt(n.id_notif)||0));

    /* ── New messages → toast (if not in that chat) ── */
    if (initialized && d.new_msgs && d.new_msgs.length) {
      d.new_msgs.forEach(m => {
        if (parseInt(m.id_expediteur) === chatWith) return; // already shown by chat poll
        const from = (m.prenom||'') + ' ' + (m.nom||'');
        toast('💬 ' + from + ' : ' + String(m.contenu||'').substring(0,60), 'info',
          `${BASE}dashboard-${ROLE}.php?tab=messages&with=${m.id_expediteur}`);
      });
      badge('.nav-badge-msg', d.msg_count);
      dot('.msg-dot', d.msg_count > 0);
    }
    if (d.new_msgs && d.new_msgs.length)
      lastMsgId = Math.max(lastMsgId, ...d.new_msgs.map(m => parseInt(m.id_message)||0));

    /* ── Tech: new demandes → toast ── */
    if (initialized && d.new_demandes && d.new_demandes.length) {
      d.new_demandes.forEach(dm =>
        toast('📋 Nouvelle demande de ' + (dm.prenom||'') + ' ' + (dm.nom||'') +
          (dm.date_service ? ' pour le ' + dm.date_service : ''), 'info',
          `${BASE}dashboard-tech.php?tab=demandes`)
      );
      lastServiceId = Math.max(lastServiceId, ...d.new_demandes.map(dm => parseInt(dm.id_service)||0));
    }

    /* ── Admin: new users → toast ── */
    if (initialized && d.new_users && d.new_users.length) {
      d.new_users.forEach(u =>
        toast((u.role==='technicien' ? '🔧 Nouveau technicien' : '👤 Nouveau client') +
          ' : ' + (u.prenom||'') + ' ' + (u.nom||''), 'info',
          `${BASE}dashboard-admin.php?tab=utilisateurs`)
      );
      lastUserId = Math.max(lastUserId, ...d.new_users.map(u => parseInt(u.id_user)||0));
    }

    initialized = true; // from this point on, toasts fire for new items

  } catch(e) {
    console.warn('[KhidmaRT] pollNotifs error:', e);
  }
}

/* ── Boot ── */
function boot() {
  const params  = new URLSearchParams(location.search);
  const tab     = params.get('tab');
  const withUid = parseInt(params.get('with') || '0');

  // Start chat polling if messages tab is open
  if (tab === 'messages' && withUid) {
    const box = document.getElementById('chat-messages');
    if (box) {
      const ids = Array.from(box.querySelectorAll('[data-msgid]'))
        .map(el => parseInt(el.dataset.msgid || 0)).filter(n => n > 0);
      lastChatId = ids.length ? Math.max(...ids) : lastMsgId;
    }
    startChat(withUid, lastChatId);
  }

  setupSendForm();

  // Poll immediately then every 5s
  pollNotifs();
  setInterval(pollNotifs, 5000);

  // Auto-dismiss server-rendered toast
  const t = document.getElementById('php-toast');
  if (t) setTimeout(() => t.style.opacity = '0', 4000);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();

})();