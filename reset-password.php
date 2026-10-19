<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/lang.php';
$lang = get_lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';

$token = trim($_GET['token'] ?? '');
$error = $_SESSION['reset_pw_error'] ?? null;
unset($_SESSION['reset_pw_error']);

$valid = false;
$user  = null;

if ($token) {
    $row = $pdo->prepare("SELECT rt.*, u.nom, u.prenom, u.email FROM reset_token rt JOIN utilisateur u ON u.id_user = rt.id_user WHERE rt.token = ? AND rt.used = 0 AND rt.expires_at > NOW()");
    $row->execute([$token]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        $valid = true;
        $user  = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$lang==='ar'?'إعادة تعيين كلمة المرور':'Réinitialisation du mot de passe'?> — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--v:#7c3aed;--vd:#5b21b6;--vl:#ede9fe;--vm:#c4b5fd;--c:#06b6d4;--bg:#faf9fe;--s:#fff;--t:#0f0c1d;--mu:#8b83a8;--br:#e8e4f8;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 16px;position:relative;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 60% 50% at 80% 10%,rgba(124,58,237,.11) 0%,transparent 65%),radial-gradient(ellipse 50% 45% at 10% 90%,rgba(6,182,212,.08) 0%,transparent 60%);pointer-events:none;}
.card{background:var(--s);border:1.5px solid var(--br);border-radius:24px;padding:44px 40px;width:100%;max-width:420px;position:relative;z-index:1;box-shadow:0 8px 48px rgba(124,58,237,.09);}
.card::before{content:'';position:absolute;top:0;left:28px;right:28px;height:3px;background:linear-gradient(90deg,var(--v),var(--c));border-radius:0 0 4px 4px;}
.logo{display:flex;align-items:center;gap:11px;margin-bottom:28px;justify-content:center;}
.logo-icon{width:44px;height:44px;flex-shrink:0;}
.logo-name{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--t);}
.logo-name span{color:var(--v);}
h1{font-family:'Syne',sans-serif;font-size:21px;font-weight:800;text-align:center;color:var(--t);margin-bottom:6px;letter-spacing:-.5px;}
.sub{font-size:13px;color:var(--mu);text-align:center;margin-bottom:24px;line-height:1.7;}
.alert{padding:12px 16px;border-radius:12px;font-size:13px;font-weight:600;margin-bottom:18px;display:flex;align-items:center;gap:9px;}
.alert-err{background:#fee2e2;border:1.5px solid #fca5a5;color:#dc2626;}
.alert-warn{background:#fef3c7;border:1.5px solid #fde68a;color:#d97706;}
.fg{margin-bottom:16px;}
.fg label{display:block;font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;}
.fg input{width:100%;background:var(--bg);border:1.5px solid var(--br);border-radius:11px;padding:12px 14px;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--t);outline:none;transition:all .2s;}
.fg input:focus{border-color:var(--v);background:var(--s);box-shadow:0 0 0 4px rgba(124,58,237,.09);}
.strength{height:4px;border-radius:2px;background:var(--br);margin-top:8px;overflow:hidden;}
.strength-bar{height:100%;border-radius:2px;transition:all .3s;width:0%;}
.strength-label{font-size:11px;color:var(--mu);margin-top:4px;}
.btn-main{width:100%;background:var(--v);color:#fff;border:none;border-radius:13px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .22s;box-shadow:0 4px 16px rgba(124,58,237,.3);}
.btn-main:hover{background:var(--vd);transform:translateY(-1px);}
.btn-main:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.back{display:flex;align-items:center;justify-content:center;gap:5px;font-size:13px;color:var(--mu);text-decoration:none;margin-top:18px;transition:color .2s;}
.back:hover{color:var(--v);}
.expired-icon{font-size:52px;text-align:center;margin-bottom:16px;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <svg class="logo-icon" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="44" height="44" rx="12" fill="url(#lg)"/>
      <ellipse cx="22" cy="18" rx="10" ry="6.5" fill="white" opacity=".95"/>
      <rect x="12" y="21" width="20" height="2.8" rx="1.4" fill="white" opacity=".95"/>
      <rect x="10" y="23" width="24" height="2.3" rx="1.15" fill="white" opacity=".7"/>
      <rect x="19" y="26" width="6" height="10" rx="3" fill="white" opacity=".9"/>
      <ellipse cx="22" cy="26" rx="5" ry="3.2" fill="white" opacity=".9"/>
      <defs><linearGradient id="lg" x1="0" y1="0" x2="44" y2="44" gradientUnits="userSpaceOnUse"><stop stop-color="#7c3aed"/><stop offset="1" stop-color="#06b6d4"/></linearGradient></defs>
    </svg>
    <div class="logo-name">Khidma <span>DZ</span></div>
  </div>

  <?php if(!$token || !$valid): ?>
    <div class="expired-icon">⏰</div>
    <h1><?=$lang==='ar'?'الرابط منتهي الصلاحية':'Lien invalide ou expiré'?></h1>
    <p class="sub"><?=$lang==='ar'?'هذا الرابط منتهي الصلاحية أو تم استخدامه. اطلب رابطاً جديداً.':'Ce lien a expiré ou déjà été utilisé. Demandez-en un nouveau.'?></p>
    <div class="alert alert-warn">⚠ <?=$lang==='ar'?'الروابط صالحة لساعة واحدة فقط.':'Les liens sont valables 1 heure seulement.'?></div>
    <a href="forgot-password.php" class="btn-main" style="display:flex;align-items:center;justify-content:center;text-decoration:none">
      🔑 <?=$lang==='ar'?'طلب رابط جديد':'Demander un nouveau lien'?>
    </a>
  <?php else: ?>
    <h1><?=$lang==='ar'?'كلمة مرور جديدة':'Nouveau mot de passe'?></h1>
    <p class="sub"><?=$lang==='ar'?'مرحباً ':'Bonjour '?><strong><?=htmlspecialchars($user['prenom'])?></strong><?=$lang==='ar'?'، أنشئ كلمة مرور جديدة.':'، choisissez un nouveau mot de passe sécurisé.'?></p>
    <?php if($error): ?>
    <div class="alert alert-err">⚠ <?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <form method="POST" action="actions/handle_reset_password.php" id="reset-form">
      <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
      <div class="fg">
        <label><?=$lang==='ar'?'كلمة المرور الجديدة':'Nouveau mot de passe'?></label>
        <input type="password" name="password" id="pw" placeholder="••••••••" required minlength="8" oninput="checkStrength(this.value)">
        <div class="strength"><div class="strength-bar" id="str-bar"></div></div>
        <div class="strength-label" id="str-label"><?=$lang==='ar'?'8 أحرف على الأقل':'8 caractères minimum'?></div>
      </div>
      <div class="fg">
        <label><?=$lang==='ar'?'تأكيد كلمة المرور':'Confirmer le mot de passe'?></label>
        <input type="password" name="password_confirm" id="pw2" placeholder="••••••••" required oninput="checkMatch()">
      </div>
      <button type="submit" class="btn-main" id="submit-btn">🔒 <?=$lang==='ar'?'حفظ كلمة المرور الجديدة':'Enregistrer le nouveau mot de passe'?></button>
    </form>
  <?php endif; ?>
  <a href="login.php" class="back">← <?=$lang==='ar'?'العودة إلى تسجيل الدخول':'Retour à la connexion'?></a>
</div>
<script>
const labels = {weak:'<?=$lang==="ar"?"ضعيفة":"Faible"?>',ok:'<?=$lang==="ar"?"متوسطة":"Moyen"?>',strong:'<?=$lang==="ar"?"قوية":"Fort"?>',very:'<?=$lang==="ar"?"قوية جداً":"Très fort"?>'};
function checkStrength(v){
  const bar=document.getElementById('str-bar'),lbl=document.getElementById('str-label');
  let s=0;
  if(v.length>=8)s++;if(v.length>=12)s++;if(/[A-Z]/.test(v)&&/[a-z]/.test(v))s++;if(/\d/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const pct=[0,25,50,75,100][Math.min(s,4)];
  const col=['#e5e7eb','#ef4444','#f59e0b','#22c55e','#7c3aed'][Math.min(s,4)];
  bar.style.width=pct+'%';bar.style.background=col;
  lbl.textContent=[' ',labels.weak,labels.ok,labels.strong,labels.very][Math.min(s,4)];
  lbl.style.color=col;
}
function checkMatch(){
  const p1=document.getElementById('pw').value,p2=document.getElementById('pw2').value;
  document.getElementById('submit-btn').disabled=p1!==p2||p1.length<8;
}
</script>
</body>
</html>