<?php
session_start();
require_once 'includes/lang.php';
$lang = get_lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';

$wilayas = ['Adrar','Chlef','Laghouat','Oum El Bouaghi','Batna','Béjaïa','Biskra','Béchar','Blida','Bouira','Tamanrasset','Tébessa','Tlemcen','Tiaret','Tizi Ouzou','Alger','Djelfa','Jijel','Sétif','Saïda','Skikda','Sidi Bel Abbès','Annaba','Guelma','Constantine','Médéa','Mostaganem',"M'Sila",'Mascara','Ouargla','Oran','El Bayadh','Illizi','Bordj Bou Arréridj','Boumerdès','El Tarf','Tindouf','Tissemsilt','El Oued','Khenchela','Souk Ahras','Tipaza','Mila','Aïn Defla','Naâma','Aïn Témouchent','Ghardaïa','Relizane','Timimoun','Bordj Badji Mokhtar','Ouled Djellal','Béni Abbès','In Salah','In Guezzam','Touggourt','Djanet',"El M'Ghair",'El Meniaa','Aflou','El Abiodh Sidi Cheikh','El Aricha','El Kantara','Barika','Bou Saâda','Bir El Ater','Ksar El Boukhari','Ksar Chellala','Aïn Oussera','Messaâd'];
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
<title><?=t('reg_client_title',$lang)?> — Khidma DZ</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{--v:#7c3aed;--vd:#5b21b6;--vl:#ede9fe;--vm:#c4b5fd;--c:#06b6d4;--bg:#faf9fe;--s:#fff;--t:#0f0c1d;--t2:#3d3655;--mu:#8b83a8;--br:#e8e4f8;}
html{scroll-behavior:smooth;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 16px;position:relative;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 60% 50% at 80% 10%,rgba(124,58,237,.11) 0%,transparent 65%),radial-gradient(ellipse 50% 45% at 10% 90%,rgba(6,182,212,.08) 0%,transparent 60%);pointer-events:none;}
.lang-btn{position:fixed;top:18px;right:18px;padding:7px 15px;background:var(--vl);border:1.5px solid var(--vm);border-radius:10px;color:var(--v);font-weight:700;font-size:13px;text-decoration:none;transition:all .2s;z-index:99;font-family:'Plus Jakarta Sans',sans-serif;}
[dir=rtl] .lang-btn{right:auto;left:18px;}
.lang-btn:hover{background:var(--v);color:#fff;}
.card{background:var(--s);border:1.5px solid var(--br);border-radius:24px;padding:38px 34px;width:100%;max-width:500px;position:relative;z-index:1;box-shadow:0 8px 48px rgba(124,58,237,.09),0 2px 8px rgba(0,0,0,.04);}
.card::before{content:'';position:absolute;top:0;left:28px;right:28px;height:3px;background:linear-gradient(90deg,var(--v),var(--c));border-radius:0 0 4px 4px;}
.logo{display:flex;align-items:center;gap:11px;margin-bottom:26px;justify-content:center;}
.logo-icon{width:44px;height:44px;flex-shrink:0;}
.logo-name{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--t);}
.logo-name span{color:var(--v);}
h1{font-family:'Syne',sans-serif;font-size:21px;font-weight:800;text-align:center;color:var(--t);margin-bottom:5px;letter-spacing:-.5px;}
.sub{font-size:13px;color:var(--mu);text-align:center;margin-bottom:22px;line-height:1.65;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fg{margin-bottom:13px;}
.fg label{display:block;font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px;}
.fg input,.fg select{width:100%;background:var(--bg);border:1.5px solid var(--br);border-radius:11px;padding:11px 14px;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--t);outline:none;transition:all .2s;appearance:none;-webkit-appearance:none;}
.fg select{background-image:url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L6 7L11 1' stroke='%238b83a8' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 13px center;}
[dir=rtl] .fg select{background-position:left 13px center;}
.fg input:focus,.fg select:focus{border-color:var(--v);background:var(--s);box-shadow:0 0 0 4px rgba(124,58,237,.09);}
.btn-main{width:100%;background:var(--v);color:#fff;border:none;border-radius:13px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;transition:all .22s;margin-top:4px;box-shadow:0 4px 16px rgba(124,58,237,.3);}
.btn-main:hover{background:var(--vd);transform:translateY(-1px);box-shadow:0 8px 24px rgba(124,58,237,.38);}
.bottom-links{margin-top:18px;text-align:center;font-size:13px;color:var(--mu);}
.bottom-links a{color:var(--v);font-weight:600;text-decoration:none;}
.back{display:flex;align-items:center;justify-content:center;gap:5px;font-size:13px;color:var(--mu);text-decoration:none;margin-bottom:18px;transition:color .2s;}
.back:hover{color:var(--v);}
::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-thumb{background:var(--vm);border-radius:3px;}
@media(max-width:480px){.row{grid-template-columns:1fr;}}

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
      <defs><linearGradient id="lg" x1="0" y1="0" x2="44" y2="44" gradientUnits="userSpaceOnUse"><stop stop-color="#7c3aed"/><stop offset="1" stop-color="#06b6d4"/></linearGradient></defs>
    </svg>
    <div class="logo-name">Khidma <span>DZ</span></div>
  </div>
  <h1><?=t('reg_client_title',$lang)?></h1>
  <p class="sub"><?=t('reg_client_sub',$lang)?></p>
  <form method="POST" action="actions/handle_register_client.php">
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
    <div class="row">
      <div class="fg">
        <label><?=t('reg_password',$lang)?></label>
        <input type="password" name="mot_de_passe" id="pw" required placeholder="••••••••" oninput="checkStrength(this.value)">
        <div class="strength"><div class="strength-bar" id="str-bar"></div></div>
        <div class="strength-label" id="str-label"><?=$lang==='ar'?'8 أحرف على الأقل':'8 caractères minimum'?></div>
      </div>
      <div class="fg">
        <label><?=t('reg_password_conf',$lang)?></label>
        <input type="password" name="mot_de_passe_confirm" id="pw2" required placeholder="••••••••" oninput="checkMatch()">
      </div>
    </div>
    <button type="submit" class="btn-main" id="submit-btn"><?=t('reg_btn_client',$lang)?></button>
  </form>
  <div class="bottom-links"><?=t('reg_already',$lang)?> <a href="login.php"><?=t('reg_login_link',$lang)?></a></div>
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