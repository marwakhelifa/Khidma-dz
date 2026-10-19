<?php
session_start();
require_once 'includes/lang.php';
$lang = get_lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';

$sent  = $_SESSION['reset_sent'] ?? false;
$error = $_SESSION['reset_error'] ?? null;
unset($_SESSION['reset_sent'], $_SESSION['reset_error']);
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$lang==='ar'?'نسيت كلمة المرور':'Mot de passe oublié'?> — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--v:#7c3aed;--vd:#5b21b6;--vl:#ede9fe;--vm:#c4b5fd;--c:#06b6d4;--bg:#faf9fe;--s:#fff;--t:#0f0c1d;--mu:#8b83a8;--br:#e8e4f8;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 16px;position:relative;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 60% 50% at 80% 10%,rgba(124,58,237,.11) 0%,transparent 65%),radial-gradient(ellipse 50% 45% at 10% 90%,rgba(6,182,212,.08) 0%,transparent 60%);pointer-events:none;}
.lang-btn{position:fixed;top:18px;right:18px;padding:7px 15px;background:var(--vl);border:1.5px solid var(--vm);border-radius:10px;color:var(--v);font-weight:700;font-size:13px;text-decoration:none;transition:all .2s;z-index:99;}
[dir=rtl] .lang-btn{right:auto;left:18px;}
.lang-btn:hover{background:var(--v);color:#fff;}
.card{background:var(--s);border:1.5px solid var(--br);border-radius:24px;padding:44px 40px;width:100%;max-width:420px;position:relative;z-index:1;box-shadow:0 8px 48px rgba(124,58,237,.09);}
.card::before{content:'';position:absolute;top:0;left:28px;right:28px;height:3px;background:linear-gradient(90deg,var(--v),var(--c));border-radius:0 0 4px 4px;}
.logo{display:flex;align-items:center;gap:11px;margin-bottom:28px;justify-content:center;}
.logo-icon{width:44px;height:44px;flex-shrink:0;}
.logo-name{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--t);}
.logo-name span{color:var(--v);}
h1{font-family:'Syne',sans-serif;font-size:21px;font-weight:800;text-align:center;color:var(--t);margin-bottom:6px;letter-spacing:-.5px;}
.sub{font-size:13px;color:var(--mu);text-align:center;margin-bottom:26px;line-height:1.7;}
.alert{padding:12px 16px;border-radius:12px;font-size:13px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
.alert-ok{background:#dcfce7;border:1.5px solid #86efac;color:#15803d;}
.alert-err{background:#fee2e2;border:1.5px solid #fca5a5;color:#dc2626;}
.fg{margin-bottom:16px;}
.fg label{display:block;font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;}
.fg input{width:100%;background:var(--bg);border:1.5px solid var(--br);border-radius:11px;padding:12px 14px;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--t);outline:none;transition:all .2s;}
.fg input:focus{border-color:var(--v);background:var(--s);box-shadow:0 0 0 4px rgba(124,58,237,.09);}
.btn-main{width:100%;background:var(--v);color:#fff;border:none;border-radius:13px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .22s;box-shadow:0 4px 16px rgba(124,58,237,.3);}
.btn-main:hover{background:var(--vd);transform:translateY(-1px);box-shadow:0 8px 24px rgba(124,58,237,.38);}
.back{display:flex;align-items:center;justify-content:center;gap:5px;font-size:13px;color:var(--mu);text-decoration:none;margin-top:20px;transition:color .2s;}
.back:hover{color:var(--v);}
.info-box{background:var(--vl);border:1.5px solid var(--vm);border-radius:14px;padding:16px 18px;margin-bottom:22px;font-size:13px;color:var(--v);line-height:1.7;}
.info-box strong{display:block;margin-bottom:4px;font-size:13.5px;}
.token-icon{font-size:48px;text-align:center;margin-bottom:16px;}
</style>
</head>
<body>
<a href="?lang=<?=t('lang_target',$lang)?>" class="lang-btn"><?=t('lang_label',$lang)?></a>
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

  <?php if($sent): ?>
  <div class="token-icon">🔐</div>
  <h1><?=$lang==='ar'?'تحقق من بريدك':'Vérifiez votre boîte mail'?></h1>
  <p class="sub"><?=$lang==='ar'?'إذا كان الحساب موجوداً، سيصلك رابط لإعادة تعيين كلمة المرور.':'Si un compte existe avec cet email, vous recevrez un lien de réinitialisation.'?></p>
  <div class="info-box">
    <strong><?=$lang==='ar'?'ملاحظة مهمة:':'Note importante :'?></strong>
    <?=$lang==='ar'?'الرابط صالح لمدة ساعة واحدة فقط. تحقق من مجلد الرسائل غير المرغوب فيها.':'Le lien est valable 1 heure. Vérifiez aussi votre dossier spam.'?>
  </div>
  <?php else: ?>
  <h1><?=$lang==='ar'?'نسيت كلمة المرور':'Mot de passe oublié'?></h1>
  <p class="sub"><?=$lang==='ar'?'أدخل بريدك الإلكتروني وسنرسل لك رابطاً لإعادة التعيين.':'Entrez votre email et nous vous enverrons un lien de réinitialisation.'?></p>
  <?php if($error): ?>
  <div class="alert alert-err">⚠ <?=htmlspecialchars($error)?></div>
  <?php endif; ?>
  <form method="POST" action="actions/handle_forgot.php">
    <div class="fg">
      <label><?=$lang==='ar'?'البريد الإلكتروني':'Adresse email'?></label>
      <input type="email" name="email" placeholder="vous@example.com" required autocomplete="email">
    </div>
    <button type="submit" class="btn-main">🔑 <?=$lang==='ar'?'إرسال رابط الاسترداد':'Envoyer le lien'?></button>
  </form>
  <?php endif; ?>
  <a href="login.php" class="back">← <?=$lang==='ar'?'العودة إلى تسجيل الدخول':'Retour à la connexion'?></a>
</div>
</body>
</html>