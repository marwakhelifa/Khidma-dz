<?php
session_start();
require_once 'includes/lang.php';
$lang = get_lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';
$success   = $_SESSION['login_success']  ?? null; unset($_SESSION['login_success']);
$error     = $_SESSION['login_error']     ?? null; unset($_SESSION['login_error']);
$suspended = $_SESSION['login_suspended'] ?? false; unset($_SESSION['login_suspended']);
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#7c3aed">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Khidma DZ">
<link rel="apple-touch-icon" href="icons/icon-152.png">
<title><?=t('login_title',$lang)?> — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--v:#7c3aed;--vd:#5b21b6;--vl:#ede9fe;--vm:#c4b5fd;--c:#06b6d4;--bg:#faf9fe;--s:#fff;--t:#0f0c1d;--t2:#3d3655;--mu:#8b83a8;--br:#e8e4f8;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;position:relative;overflow:hidden;}
/* Background orbs */
body::before{content:'';position:fixed;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(124,58,237,.13) 0%,transparent 70%);top:-150px;right:-150px;pointer-events:none;}
body::after{content:'';position:fixed;width:380px;height:380px;border-radius:50%;background:radial-gradient(circle,rgba(6,182,212,.09) 0%,transparent 70%);bottom:-100px;left:-100px;pointer-events:none;}
.lang-btn{position:fixed;top:18px;right:18px;padding:7px 15px;background:var(--vl);border:1.5px solid var(--vm);border-radius:10px;color:var(--v);font-weight:700;font-size:13px;text-decoration:none;transition:all .2s;z-index:99;font-family:'Plus Jakarta Sans',sans-serif;}
[dir=rtl] .lang-btn{right:auto;left:18px;}
.lang-btn:hover{background:var(--v);color:#fff;}
.card{background:var(--s);border:1.5px solid var(--br);border-radius:24px;padding:44px 40px;width:100%;max-width:420px;box-shadow:0 8px 48px rgba(124,58,237,.10),0 2px 8px rgba(0,0,0,.04);position:relative;z-index:1;}
/* Logo */
.logo{display:flex;align-items:center;gap:11px;margin-bottom:32px;justify-content:center;}
.logo-icon{width:44px;height:44px;flex-shrink:0;}
.logo-name{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--t);}
.logo-name span{color:var(--v);}
/* Top bar gradient */
.card::before{content:'';position:absolute;top:0;left:28px;right:28px;height:3px;background:linear-gradient(90deg,var(--v),var(--c));border-radius:0 0 4px 4px;}
h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;text-align:center;color:var(--t);margin-bottom:6px;letter-spacing:-.5px;}
.sub{font-size:13.5px;color:var(--mu);text-align:center;margin-bottom:28px;line-height:1.65;}
.fg{margin-bottom:16px;}
.fg label{display:block;font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;}
.fg input{width:100%;background:var(--bg);border:1.5px solid var(--br);border-radius:12px;padding:12px 15px;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--t);outline:none;transition:all .2s;}
.fg input:focus{border-color:var(--v);background:var(--s);box-shadow:0 0 0 4px rgba(124,58,237,.09);}
.btn-main{width:100%;background:var(--v);color:#fff;border:none;border-radius:13px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .22s;margin-top:4px;box-shadow:0 4px 16px rgba(124,58,237,.3);}
.btn-main:hover{background:var(--vd);transform:translateY(-1px);box-shadow:0 8px 24px rgba(124,58,237,.38);}
.divider{display:flex;align-items:center;gap:12px;margin:22px 0;color:var(--mu);font-size:12px;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--br);}
.links-row{display:flex;gap:10px;}
.btn-out{flex:1;text-align:center;padding:10px 14px;border-radius:11px;border:1.5px solid var(--br);color:var(--t2);font-size:13px;font-weight:600;text-decoration:none;transition:all .2s;font-family:'Plus Jakarta Sans',sans-serif;}
.btn-out:hover{border-color:var(--v);color:var(--v);background:var(--vl);}
.btn-out.cyan:hover{border-color:var(--c);color:var(--c);background:#ecfeff;}
.back{display:flex;align-items:center;justify-content:center;gap:5px;font-size:13px;color:var(--mu);text-decoration:none;margin-bottom:22px;transition:color .2s;}
.back:hover{color:var(--v);}
</style>
</head>
<body>
<a href="?lang=<?=t('lang_target',$lang)?>" class="lang-btn"><?=t('lang_label',$lang)?></a>
<div class="card">
  <a href="index.php" class="back">← <?=t('back_home',$lang)?></a>
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
  <h1><?=t('login_title',$lang)?></h1>
  <?php if($success): ?>
  <div style="background:#dcfce7;border:1.5px solid #86efac;color:#15803d;padding:11px 14px;border-radius:11px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:8px">✅ <?=htmlspecialchars($success)?></div>
  <?php endif; ?>
  <?php if($error): ?>
  <div style="background:#fef2f2;border:1.5px solid #fca5a5;color:#dc2626;padding:12px 14px;border-radius:11px;font-size:13px;font-weight:600;margin-bottom:16px;display:flex;align-items:flex-start;gap:9px">
    <span style="flex-shrink:0;font-size:16px"><?=$suspended?'🔒':'⚠️'?></span>
    <span><?=htmlspecialchars($error)?></span>
  </div>
  <?php endif; ?>
  <p class="sub"><?=t('login_subtitle',$lang)?></p>
  <?php $old_email = htmlspecialchars($_GET['email'] ?? ''); ?>
  <form method="POST" action="actions/handle_login.php">
    <div class="fg">
      <label><?=t('login_email',$lang)?></label>
      <input type="email" name="email" placeholder="vous@example.com" required autocomplete="email"
        value="<?=$old_email?>"
        style="<?=$error&&!$suspended?'border-color:#fca5a5;':''?>">
    </div>
    <div class="fg">
      <label><?=t('login_password',$lang)?></label>
      <input type="password" name="mot_de_passe" placeholder="••••••••" required autocomplete="current-password"
        style="<?=$error&&!$suspended?'border-color:#fca5a5;':''?>">
    </div>
    <button type="submit" class="btn-main"><?=t('login_btn',$lang)?></button>
    <div style="text-align:center;margin-top:12px"><a href="forgot-password.php" style="font-size:12.5px;color:var(--mu);text-decoration:none;transition:color .2s" onmouseover="this.style.color='var(--v)'" onmouseout="this.style.color='var(--mu)'"><?=$lang==='ar'?'نسيت كلمة المرور؟':'Mot de passe oublié ?'?></a></div>
  </form>
  <div class="divider"><?=$lang==='ar'?'ليس لديك حساب؟':'Pas encore de compte ?'?></div>
  <div class="links-row">
    <a href="register-client.php" class="btn-out">👤 <?=t('login_as_client',$lang)?></a>
    <a href="register-tech.php" class="btn-out cyan">🔧 <?=t('login_as_tech',$lang)?></a>
  </div>
</div>
<script src="js/khidma-pwa.js" defer></script>
</body>
</html>