<?php
session_start();
include 'includes/db.php';
require_once 'includes/lang.php';
require_once 'includes/services.php';
$lang = get_lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';
$all_svcs = all_services();
$wilayas = ['1-Adrar','2-Chlef','3-Laghouat','4-Oum El Bouaghi','5-Batna','6-Béjaïa','7-Biskra','8-Béchar','9-Blida','10-Bouira','11-Tamanrasset','12-Tébessa','13-Tlemcen','14-Tiaret','15-Tizi Ouzou','16-Alger','17-Djelfa','18-Jijel','19-Sétif','20-Saïda','21-Skikda','22-Sidi Bel Abbès','23-Annaba','24-Guelma','25-Constantine','26-Médéa','27-Mostaganem',"28-M'Sila",'29-Mascara','30-Ouargla','31-Oran','32-El Bayadh','33-Illizi','34-Bordj Bou Arréridj','35-Boumerdès','36-El Tarf','37-Tindouf','38-Tissemsilt','39-El Oued','40-Khenchela','41-Souk Ahras','42-Tipaza','43-Mila','44-Aïn Defla','45-Naâma','46-Aïn Témouchent','47-Ghardaïa','48-Relizane','49-Timimoun','50-Bordj Badji Mokhtar','51-Ouled Djellal','52-Béni Abbès','53-In Salah','54-In Guezzam','55-Touggourt','56-Djanet',"57-El M'Ghair",'58-El Meniaa','59-Aflou','60-El Abiodh Sidi Cheikh','61-El Aricha','62-El Kantara','63-Barika','64-Bou Saâda','65-Bir El Ater','66-Ksar El Boukhari','67-Ksar Chellala','68-Aïn Oussera','69-Messaâd'];
$wilayas_ar = ['أدرار','الشلف','الأغواط','أم البواقي','باتنة','بجاية','بسكرة','بشار','البليدة','البويرة','تمنراست','تبسة','تلمسان','تيارت','تيزي وزو','الجزائر','الجلفة','جيجل','سطيف','سعيدة','سكيكدة','سيدي بلعباس','عنابة','قالمة','قسنطينة','المدية','مستغانم','المسيلة','معسكر','ورقلة','وهران','البيض','إليزي','برج بوعريريج','بومرداس','الطارف','تندوف','تيسمسيلت','الوادي','خنشلة','سوق أهراس','تيبازة','ميلة','عين الدفلى','النعامة','عين تيموشنت','غرداية','غليزان','تيميمون','برج باجي مختار','أولاد جلال','بني عباس','عين صالح','عين قزام','تقرت','جانت','المغير','المنيعة','أفلو','الأبيض سيدي الشيخ','الأريشة','القنطرة','بريكة','بوسعادة','بئر العاتر','قصر البخاري','قصر الشلالة','عين وسارة','مسعد'];
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
<title><?=t('reg_tech_title',$lang)?> — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--v:#06b6d4;--vd:#0891b2;--vl:#ecfeff;--vm:#67e8f9;--c:#7c3aed;--bg:#f0fdfe;--s:#fff;--t:#0c1f24;--t2:#164e63;--mu:#64748b;--br:#cffafe;}
html{scroll-behavior:smooth;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 16px;position:relative;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 60% 50% at 80% 10%,rgba(124,58,237,.11) 0%,transparent 65%),radial-gradient(ellipse 50% 45% at 10% 90%,rgba(6,182,212,.08) 0%,transparent 60%);pointer-events:none;}
.lang-btn{position:fixed;top:18px;right:18px;padding:7px 15px;background:var(--vl);border:1.5px solid var(--vm);border-radius:10px;color:var(--v);font-weight:700;font-size:13px;text-decoration:none;transition:all .2s;z-index:99;font-family:'Plus Jakarta Sans',sans-serif;}
[dir=rtl] .lang-btn{right:auto;left:18px;}
.lang-btn:hover{background:var(--v);color:#fff;}
.card{background:var(--s);border:1.5px solid var(--br);border-radius:24px;padding:38px 34px;width:100%;max-width:520px;position:relative;z-index:1;box-shadow:0 8px 48px rgba(124,58,237,.09),0 2px 8px rgba(0,0,0,.04);}
.card::before{content:'';position:absolute;top:0;left:28px;right:28px;height:3px;background:linear-gradient(90deg,var(--v),var(--c));border-radius:0 0 4px 4px;}.logo{display:flex;align-items:center;gap:11px;margin-bottom:26px;justify-content:center;}
.logo-icon{width:44px;height:44px;flex-shrink:0;}
.logo-name{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--t);}
.logo-name span{color:var(--v);}
h1{font-family:'Syne',sans-serif;font-size:21px;font-weight:800;text-align:center;color:var(--t);margin-bottom:5px;letter-spacing:-.5px;}
.sub{font-size:13px;color:var(--mu);text-align:center;margin-bottom:20px;line-height:1.65;}
/* Section labels */
.sec-label{font-size:10.5px;font-weight:700;color:var(--v);text-transform:uppercase;letter-spacing:1.5px;margin:20px 0 12px;padding-bottom:8px;border-bottom:1.5px solid var(--br);display:flex;align-items:center;gap:7px;}
.sec-label::before{content:'';width:14px;height:2px;background:linear-gradient(90deg,var(--v),var(--c));border-radius:2px;flex-shrink:0;}
[dir=rtl] .sec-label::before{display:none;}
[dir=rtl] .sec-label::after{content:'';width:14px;height:2px;background:linear-gradient(270deg,var(--v),var(--c));border-radius:2px;flex-shrink:0;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fg{margin-bottom:13px;}
.fg label{display:block;font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px;}
.fg input,.fg select,.fg textarea{width:100%;background:var(--bg);border:1.5px solid var(--br);border-radius:11px;padding:11px 14px;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--t);outline:none;transition:all .2s;appearance:none;-webkit-appearance:none;}
.fg select{background-image:url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L6 7L11 1' stroke='%238b83a8' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 13px center;}
[dir=rtl] .fg select{background-position:left 13px center;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--v);background:var(--s);box-shadow:0 0 0 4px rgba(124,58,237,.09);}
.fg input[type=file]{padding:8px 14px;cursor:pointer;font-size:13px;}
.fg textarea{resize:vertical;}
/* File upload styled */
.file-zone{width:100%;background:var(--vl);border:1.5px dashed var(--vm);border-radius:11px;padding:12px 14px;font-size:13px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--v);cursor:pointer;transition:all .2s;}
.file-zone:hover{background:var(--v);color:#fff;border-color:var(--v);}
.btn-main{width:100%;background:var(--v);color:#fff;border:none;border-radius:13px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .22s;margin-top:6px;box-shadow:0 4px 16px rgba(6,182,212,.3);}
.btn-main:hover{background:var(--vd);transform:translateY(-1px);box-shadow:0 8px 24px rgba(6,182,212,.35);}
.bottom-links{margin-top:18px;text-align:center;font-size:13px;color:var(--mu);}
.bottom-links a{color:var(--v);font-weight:600;text-decoration:none;}
.back{display:flex;align-items:center;justify-content:center;gap:5px;font-size:13px;color:var(--mu);text-decoration:none;margin-bottom:18px;transition:color .2s;}
.back:hover{color:var(--v);}
/* Info badge */
.info-badge{background:var(--vl);border:1px solid var(--vm);border-radius:10px;padding:10px 14px;font-size:12.5px;color:var(--v);margin-bottom:14px;display:flex;align-items:flex-start;gap:8px;line-height:1.6;}
::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-thumb{background:var(--vm);border-radius:3px;}
@media(max-width:480px){.row{grid-template-columns:1fr;}.card{padding:28px 20px;}}

.strength{height:4px;border-radius:2px;background:var(--br);margin-top:8px;overflow:hidden;}
.strength-bar{height:100%;border-radius:2px;transition:all .3s;width:0%;}
.strength-label{font-size:11px;color:var(--mu);margin-top:4px;height:14px;}
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
      <defs><linearGradient id="lg" x1="0" y1="0" x2="44" y2="44" gradientUnits="userSpaceOnUse"><stop stop-color="#06b6d4"/><stop offset="1" stop-color="#7c3aed"/></linearGradient></defs>
    </svg>
    <div class="logo-name">Khidma <span>DZ</span></div>
  </div>
  <h1><?=t('reg_tech_title',$lang)?></h1>
  <p class="sub"><?=t('reg_tech_sub',$lang)?></p>
  <div class="info-badge">ℹ️ <?=$lang==='ar'?'سيتم مراجعة ملفك من قبل الإدارة قبل تفعيل حسابك.':'Votre profil sera examiné par notre équipe avant activation.'?></div>

  <form method="POST" action="actions/handle_register_tech.php" enctype="multipart/form-data">

    <div class="sec-label"><?=$lang==='ar'?'المعلومات الشخصية':'Informations personnelles'?></div>
    <div class="row">
      <div class="fg"><label><?=t('reg_nom',$lang)?></label><input type="text" name="nom" required></div>
      <div class="fg"><label><?=t('reg_prenom',$lang)?></label><input type="text" name="prenom" required></div>
    </div>
    <div class="fg"><label><?=t('reg_email',$lang)?></label><input type="email" name="email" placeholder="vous@example.com" required></div>
    <div class="fg"><label><?=t('reg_phone',$lang)?></label><input type="tel" name="telephone" placeholder="+213..."></div>
    <div class="fg">
      <label><?=t('reg_city',$lang)?></label>
      <select name="ville" required>
        <option value=""><?=$lang==='ar'?'— الولاية —':'— Choisir une Wilaya —'?></option>
        <?php foreach($wilayas as $i=>$w):$num=$i+1;$lbl=$lang==='ar'?$num.' - '.($wilayas_ar[$i]??$w):$num.' - '.$w;?><option value="<?=htmlspecialchars($w)?>"><?=htmlspecialchars($lbl)?></option><?php endforeach;?>
      </select>
    </div>

    <div class="sec-label"><?=$lang==='ar'?'المعلومات المهنية':'Informations professionnelles'?></div>
    <div class="fg">
      <label><?=t('reg_specialty',$lang)?></label>
      <?=services_select('specialite', $lang, '', t('reg_specialty_ph',$lang))?>
    </div>
    <div class="row">
      <div class="fg"><label><?=t('reg_experience',$lang)?></label><input type="number" name="experience" min="0" max="50" placeholder="<?=$lang==='ar'?'سنوات':'Années'?>"></div>
    </div>

    <div class="sec-label"><?=$lang==='ar'?'الوثائق':'Documents '?></div>
    <div class="fg">
      <label><?=t('reg_cv',$lang)?></label>
      <input type="file" name="cv" accept="application/pdf,.pdf" class="file-zone">
    </div>
    <div class="fg">
      <label><?=t('reg_diplome',$lang)?></label>
      <input type="file" name="diplome" accept="application/pdf,.pdf" class="file-zone">
    </div>

    <div class="sec-label"><?=$lang==='ar'?'كلمة المرور':'Sécurité'?></div>
    <div class="row">
      <div class="fg">
        <label><?=t('reg_password',$lang)?></label>
        <input type="password" name="mot_de_passe" id="pw" required placeholder="••••••••" oninput="checkStrength(this.value)">
        <div class="strength"><div class="strength-bar" id="str-bar"></div></div>
        <div class="strength-label" id="str-label"><?=$lang==='ar'?'8 أحرف على الأقل':'8 caractères minimum'?></div>
      </div>
      <div class="fg">
        <label><?=t('reg_password_conf',$lang)?></label>
        <input type="password" name="confirm_password" id="pw2" required placeholder="••••••••" oninput="checkMatch()">
      </div>
    </div>

    <button type="submit" class="btn-main" id="submit-btn">🔧 <?=t('reg_btn_tech',$lang)?></button>
  </form>
  <div class="bottom-links"><?=t('reg_already_tech',$lang)?> <a href="login.php"><?=t('reg_login_link',$lang)?></a></div>
</div>
<script>
const labels={weak:'<?=$lang==="ar"?"ضعيفة":"Faible"?>',ok:'<?=$lang==="ar"?"متوسطة":"Moyen"?>',strong:'<?=$lang==="ar"?"قوية":"Fort"?>',very:'<?=$lang==="ar"?"قوية جداً":"Très fort"?>'};
function checkStrength(v){
  const bar=document.getElementById('str-bar'),lbl=document.getElementById('str-label');
  let s=0;
  if(v.length>=8)s++;if(v.length>=12)s++;if(/[A-Z]/.test(v)&&/[a-z]/.test(v))s++;if(/\d/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const pct=[0,25,50,75,100][Math.min(s,4)];
  const col=['#e5e7eb','#ef4444','#f59e0b','#22c55e','#7c3aed'][Math.min(s,4)];
  bar.style.width=pct+'%';bar.style.background=col;
  lbl.textContent=[' ',labels.weak,labels.ok,labels.strong,labels.very][Math.min(s,4)];
  lbl.style.color=col;
  checkMatch();
}
function checkMatch(){
  const p1=document.getElementById('pw').value,p2=document.getElementById('pw2').value;
  const btn=document.getElementById('submit-btn');
  if(btn) btn.disabled=p1!==p2||p1.length<8;
  if(p2&&p1!==p2){
    document.getElementById('pw2').style.borderColor='#ef4444';
  } else {
    document.getElementById('pw2').style.borderColor='';
  }
}
</script>
<script src="js/khidma-pwa.js" defer></script>
</body>
</html>