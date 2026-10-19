<?php
http_response_code(404);
require_once 'includes/lang.php';
$lang = get_lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';
$logged = isset($_SESSION['user_id']);
$role   = $_SESSION['user_role'] ?? null;
$back   = match($role) {
    'client'     => 'dashboard-client.php',
    'technicien' => 'dashboard-tech.php',
    'admin'      => 'dashboard-admin.php',
    default      => 'index.php'
};
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>404 — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--v:#7c3aed;--vd:#5b21b6;--vl:#ede9fe;--vm:#c4b5fd;--c:#06b6d4;--bg:#faf9fe;--t:#0f0c1d;--mu:#8b83a8;--br:#e8e4f8;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 24px;position:relative;overflow:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 70% 60% at 50% 0%,rgba(124,58,237,.11) 0%,transparent 60%),radial-gradient(ellipse 50% 45% at 10% 100%,rgba(6,182,212,.08) 0%,transparent 60%);pointer-events:none;}
.container{text-align:center;position:relative;z-index:1;max-width:520px;}
.num{font-family:'Syne',sans-serif;font-size:clamp(100px,20vw,160px);font-weight:900;letter-spacing:-8px;line-height:1;background:linear-gradient(135deg,var(--v),var(--c));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:8px;animation:float 3s ease-in-out infinite;}
@keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-12px);}}
.icon{font-size:48px;margin-bottom:20px;}
h1{font-family:'Syne',sans-serif;font-size:clamp(20px,4vw,28px);font-weight:800;color:var(--t);margin-bottom:12px;letter-spacing:-.5px;}
.sub{font-size:15px;color:var(--mu);line-height:1.7;margin-bottom:36px;}
.btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
.btn{padding:12px 24px;border-radius:13px;font-size:14px;font-weight:700;text-decoration:none;font-family:'Plus Jakarta Sans',sans-serif;transition:all .22s;display:inline-flex;align-items:center;gap:7px;}
.btn-v{background:var(--v);color:#fff;box-shadow:0 4px 16px rgba(124,58,237,.3);}
.btn-v:hover{background:var(--vd);transform:translateY(-1px);box-shadow:0 8px 24px rgba(124,58,237,.38);}
.btn-g{background:var(--bg);color:var(--t);border:1.5px solid var(--br);}
.btn-g:hover{border-color:var(--v);color:var(--v);}
.orb{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;opacity:.5;}
.orb1{width:300px;height:300px;background:rgba(124,58,237,.15);top:-100px;right:-100px;}
.orb2{width:250px;height:250px;background:rgba(6,182,212,.12);bottom:-80px;left:-80px;}
</style>
</head>
<body>
<div class="orb orb1"></div>
<div class="orb orb2"></div>
<div class="container">
  <div class="icon">🔧</div>
  <div class="num">404</div>
  <h1><?=$lang==='ar'?'هذه الصفحة غير موجودة':"Cette page n'existe pas"?></h1>
  <p class="sub"><?=$lang==='ar'?'ربما تم نقل هذه الصفحة أو حذفها. لا تقلق، يمكنك العودة إلى المكان الصحيح.':'La page que vous cherchez a peut-être été déplacée ou supprimée. Pas de panique !'?></p>
  <div class="btns">
    <?php
      if ($logged) {
        $primary_label = ($lang==='ar') ? '← لوحة التحكم' : '← Mon tableau de bord';
      } else {
        $primary_label = ($lang==='ar') ? '🏠 الصفحة الرئيسية' : "🏠 Retour à l'accueil";
      }
    ?>
    <a href="<?=$back?>" class="btn btn-v"><?=$primary_label?></a>
    <a href="index.php" class="btn btn-g">🏠 <?=$lang==='ar'?'الموقع الرئيسي':'Site principal'?></a>
  </div>
</div>
</body>
</html>