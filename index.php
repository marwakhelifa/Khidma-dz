<?php
session_start();
require_once 'includes/lang.php';
require_once 'includes/services.php';
$lang = get_lang();
$dir  = $lang === 'ar' ? 'rtl' : 'ltr';
$svcs = all_services();
?>
<!DOCTYPE html>
<html lang="<?=$lang?>" dir="<?=$dir?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#7c3aed">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Khidma DZ">
<link rel="apple-touch-icon" href="icons/icon-152.png">
<title>Khidma DZ — <?=$lang==='ar'?'خدمات تقنية في الجزائر':'Services techniques en Algérie'?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{font-family:'Plus Jakarta Sans',sans-serif;background:#faf9fe;color:#0f0c1d;overflow-x:hidden;line-height:1.6;}
a{text-decoration:none;color:inherit;}
::selection{background:#7c3aed22;color:#7c3aed;}
:root{
  --v:#7c3aed;--vd:#5b21b6;--vl:#ede9fe;--vm:#c4b5fd;
  --c:#06b6d4;--cd:#0891b2;--cl:#ecfeff;
  --bg:#faf9fe;--s:#fff;--t:#0f0c1d;--t2:#3d3655;--mu:#8b83a8;--br:#e8e4f8;
}
::-webkit-scrollbar{width:5px;}
::-webkit-scrollbar-thumb{background:var(--vm);border-radius:3px;}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-weight:600;transition:all .22s;text-decoration:none;}
.btn-v{background:var(--v);color:#fff;border-radius:12px;padding:13px 26px;font-size:15px;box-shadow:0 4px 20px rgba(124,58,237,.4);}
.btn-v:hover{background:var(--vd);transform:translateY(-2px);box-shadow:0 8px 28px rgba(124,58,237,.45);}
.btn-c{background:linear-gradient(135deg,var(--c),var(--cd));color:#fff;border-radius:12px;padding:13px 26px;font-size:15px;box-shadow:0 4px 20px rgba(6,182,212,.35);}
.btn-c:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(6,182,212,.45);}
.btn-ghost-w{background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.35);border-radius:12px;padding:12px 26px;font-size:15px;backdrop-filter:blur(8px);}
.btn-ghost-w:hover{background:rgba(255,255,255,.22);border-color:rgba(255,255,255,.6);}
.btn-white{background:#fff;color:var(--v);border-radius:12px;padding:13px 28px;font-size:15px;font-weight:700;box-shadow:0 4px 20px rgba(0,0,0,.18);}
.btn-white:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(0,0,0,.24);}
.btn-outline-w{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.45);border-radius:12px;padding:12px 28px;font-size:15px;font-weight:600;}
.btn-outline-w:hover{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.8);}
.lang-toggle{background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.35);color:#fff;border-radius:10px;padding:7px 15px;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;text-decoration:none;backdrop-filter:blur(6px);}
.lang-toggle:hover{background:rgba(255,255,255,.28);}
.lang-toggle-dark{background:var(--vl);border:1.5px solid var(--vm);color:var(--v);}
.lang-toggle-dark:hover{background:var(--v);color:#fff;}
.btn-g{background:#fff;color:var(--t2);border:1.5px solid var(--br);border-radius:12px;padding:9px 20px;font-size:14px;}
.btn-g:hover{border-color:var(--v);color:var(--v);}
.btn-v-sm{background:var(--v);color:#fff;border-radius:10px;padding:9px 20px;font-size:13px;box-shadow:0 3px 12px rgba(124,58,237,.35);}
.btn-v-sm:hover{background:var(--vd);transform:translateY(-1px);}
.btn-c-sm{background:linear-gradient(135deg,var(--c),var(--cd));color:#fff;border-radius:10px;padding:9px 20px;font-size:13px;}
.btn-c-sm:hover{transform:translateY(-1px);}

/* ── Nav ── */
.nav{position:fixed;top:0;left:0;right:0;z-index:999;height:68px;display:flex;align-items:center;transition:all .35s;}
.nav.over-hero{background:transparent;}
.nav.scrolled{background:rgba(250,249,254,.93);backdrop-filter:blur(20px);border-bottom:1px solid var(--br);box-shadow:0 4px 24px rgba(124,58,237,.06);}
.nav-inner{max-width:1160px;margin:0 auto;padding:0 28px;width:100%;display:flex;align-items:center;justify-content:space-between;}
/* Logo */
.logo{display:flex;align-items:center;gap:11px;cursor:pointer;}
.logo-icon{width:46px;height:46px;flex-shrink:0;}
.logo-txt{display:flex;flex-direction:column;line-height:1;}
.logo-main{font-family:'Syne',sans-serif;font-weight:800;font-size:20px;letter-spacing:-.5px;}
.logo-main span{color:var(--v);}
.nav.over-hero .logo-main{color:#fff;}
.nav.over-hero .logo-main span{color:var(--vm);}
.logo-sub{font-size:10px;font-weight:500;letter-spacing:1.8px;text-transform:uppercase;margin-top:2px;color:var(--mu);}
.nav.over-hero .logo-sub{color:rgba(255,255,255,.55);}
.nav-links{display:flex;align-items:center;gap:4px;}
.nav-links a{font-size:14px;font-weight:500;padding:8px 14px;border-radius:10px;transition:all .2s;}
.nav.over-hero .nav-links a{color:rgba(255,255,255,.8);}
.nav.over-hero .nav-links a:hover{background:rgba(255,255,255,.15);color:#fff;}
.nav.scrolled .nav-links a{color:var(--t2);}
.nav.scrolled .nav-links a:hover{background:var(--vl);color:var(--v);}
.nav-r{display:flex;align-items:center;gap:10px;}

/* ── HERO ── */
.hero{position:relative;min-height:100vh;display:flex;flex-direction:column;justify-content:center;overflow:hidden;}
.hero-photo{position:absolute;inset:0;
  background:
    radial-gradient(ellipse 80% 60% at 15% 50%, rgba(109,40,217,.55) 0%, transparent 60%),
    radial-gradient(ellipse 60% 70% at 85% 20%, rgba(6,182,212,.35) 0%, transparent 55%),
    radial-gradient(ellipse 50% 50% at 60% 90%, rgba(124,58,237,.25) 0%, transparent 50%),
    linear-gradient(135deg, #07041a 0%, #0d0826 40%, #0a0f2e 70%, #060418 100%);
}
.hero-photo::after{
  content:'';position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(124,58,237,.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(124,58,237,.06) 1px, transparent 1px);
  background-size:64px 64px;
  mask-image:radial-gradient(ellipse 90% 80% at 50% 50%, #000 20%, transparent 100%);
}
.hero-violet-glow{position:absolute;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(124,58,237,.35) 0%,transparent 70%);top:-100px;left:-200px;pointer-events:none;animation:glow-drift 8s ease-in-out infinite;}
@keyframes glow-drift{0%,100%{transform:translate(0,0);}50%{transform:translate(40px,30px);}}
.hero-cyan-glow{position:absolute;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(6,182,212,.22) 0%,transparent 70%);bottom:-80px;right:20%;pointer-events:none;animation:glow-drift2 10s ease-in-out infinite;}
@keyframes glow-drift2{0%,100%{transform:translate(0,0);}50%{transform:translate(-30px,-20px);}}
.hero-content{position:relative;z-index:2;max-width:1160px;margin:0 auto;padding:0 28px;width:100%;padding-top:68px;}
.hero-chip{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:40px;padding:6px 18px;font-size:12px;font-weight:700;color:rgba(255,255,255,.9);text-transform:uppercase;letter-spacing:1px;margin-bottom:26px;backdrop-filter:blur(8px);}
.chip-pulse{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,var(--v),var(--c));animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1;}50%{transform:scale(1.5);opacity:.5;}}
.hero-h1{font-family:'Syne',sans-serif;font-size:clamp(42px,7vw,76px);font-weight:800;color:#fff;line-height:1.02;letter-spacing:-2.5px;margin-bottom:22px;max-width:700px;}
.hero-h1 .grad{background:linear-gradient(90deg,var(--vm),var(--c));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-p{font-size:17px;color:rgba(255,255,255,.75);line-height:1.75;max-width:520px;margin-bottom:38px;}
.hero-btns{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:52px;}
.hero-stats{display:flex;gap:36px;flex-wrap:wrap;}
.hstat{display:flex;flex-direction:column;}
.hstat-n{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;line-height:1;letter-spacing:-1px;}
.hstat-l{font-size:12px;color:rgba(255,255,255,.5);margin-top:3px;font-weight:500;}
.hstat-sep{width:1px;background:rgba(255,255,255,.15);height:40px;align-self:center;}

/* ── Ticker ── */
.ticker-wrap{background:linear-gradient(135deg,var(--v),var(--vd));padding:13px 0;overflow:hidden;position:relative;}
.ticker-wrap::before,.ticker-wrap::after{content:'';position:absolute;top:0;bottom:0;width:60px;z-index:2;}
.ticker-wrap::before{left:0;background:linear-gradient(90deg,var(--v),transparent);}
.ticker-wrap::after{right:0;background:linear-gradient(-90deg,var(--vd),transparent);}
.ticker{display:flex;animation:tick 30s linear infinite;}
.ticker-item{display:flex;align-items:center;gap:10px;white-space:nowrap;padding:0 28px;font-size:13px;font-weight:600;color:rgba(255,255,255,.8);}
.ticker-sep{color:rgba(255,255,255,.3);}
@keyframes tick{0%{transform:translateX(0);}100%{transform:translateX(-50%);}}

/* ── Sections ── */
.section{padding:96px 0;}
.wrap{max-width:1160px;margin:0 auto;padding:0 28px;}
.eyebrow{font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--v);display:flex;align-items:center;gap:8px;margin-bottom:14px;}
.eyebrow::before{content:'';width:22px;height:2px;background:linear-gradient(90deg,var(--v),var(--c));border-radius:2px;}
[dir=rtl] .eyebrow::before{display:none;}
[dir=rtl] .eyebrow::after{content:'';width:22px;height:2px;background:linear-gradient(270deg,var(--v),var(--c));border-radius:2px;}
.eyebrow.center{justify-content:center;}
.eyebrow.center::before,.eyebrow.center::after{display:block!important;}
.section-title{font-family:'Syne',sans-serif;font-weight:800;font-size:clamp(28px,4vw,42px);letter-spacing:-1.2px;line-height:1.1;margin-bottom:14px;color:var(--t);}
.section-title .grad{background:linear-gradient(135deg,var(--v),var(--c));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.section-sub{font-size:16px;color:var(--mu);max-width:560px;line-height:1.75;}
.sh{margin-bottom:52px;}
.sh.center{text-align:center;}
.sh.center .section-sub{margin:0 auto;}

/* ── Services ── */
.svc-section{background:var(--bg);}
.svc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:12px;}
.svc-item{background:#fff;border:1.5px solid var(--br);border-radius:16px;padding:20px 12px;text-align:center;cursor:pointer;transition:all .25s;display:block;position:relative;overflow:hidden;}
.svc-item::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,var(--vl),var(--cl));opacity:0;transition:opacity .25s;}
.svc-item:hover{border-color:var(--v);transform:translateY(-4px);box-shadow:0 12px 32px rgba(124,58,237,.13);}
.svc-item:hover::before{opacity:1;}
.svc-em{font-size:30px;margin-bottom:9px;display:block;position:relative;z-index:1;transition:transform .25s;}
.svc-item:hover .svc-em{transform:scale(1.15) rotate(-4deg);}
.svc-nm{font-size:12px;font-weight:600;color:var(--t2);line-height:1.3;position:relative;z-index:1;}
.svc-item:hover .svc-nm{color:var(--v);}

/* ── Steps ── */
.how-section{background:#fff;}
.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:24px;position:relative;}
.steps::before{content:'';position:absolute;top:38px;left:12%;right:12%;height:2px;background:linear-gradient(90deg,var(--v),var(--c));opacity:.15;border-radius:2px;pointer-events:none;}
.step{background:var(--bg);border:1.5px solid var(--br);border-radius:22px;padding:30px 22px;text-align:center;transition:all .3s;position:relative;}
.step:hover{border-color:var(--v);box-shadow:0 16px 40px rgba(124,58,237,.10);transform:translateY(-5px);}
.step-n{width:54px;height:54px;border-radius:16px;background:linear-gradient(135deg,var(--v),var(--vd));color:#fff;font-family:'Syne',sans-serif;font-weight:800;font-size:22px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;box-shadow:0 6px 20px rgba(124,58,237,.35);}
.step-h{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:var(--t);margin-bottom:8px;}
.step-p{font-size:13px;color:var(--mu);line-height:1.65;}

/* ── Roles ── */
.roles-section{background:var(--bg);}
.roles-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;}
.role{border-radius:28px;padding:44px;position:relative;overflow:hidden;}
.role-client{background:linear-gradient(160deg,#f5f3ff,#fff 55%);border:1.5px solid var(--vm);}
.role-tech{background:linear-gradient(160deg,#ecfeff,#fff 55%);border:1.5px solid rgba(6,182,212,.4);}
.role-bg{position:absolute;right:-20px;bottom:-20px;font-size:140px;opacity:.055;line-height:1;pointer-events:none;transition:transform .4s;}
[dir=rtl] .role-bg{right:auto;left:-20px;}
.role:hover .role-bg{transform:scale(1.08) rotate(4deg);}
.role-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 15px;border-radius:40px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.9px;margin-bottom:18px;}
.role-client .role-badge{background:var(--vl);color:var(--v);border:1px solid var(--vm);}
.role-tech .role-badge{background:var(--cl);color:var(--cd);border:1px solid rgba(6,182,212,.4);}
.role h3{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;margin-bottom:8px;letter-spacing:-.5px;}
.role-client h3{color:var(--v);}
.role-tech h3{color:var(--cd);}
.role-sub{font-size:14px;color:var(--mu);margin-bottom:24px;line-height:1.7;}
.role-list{list-style:none;display:flex;flex-direction:column;gap:10px;margin-bottom:30px;}
.role-list li{display:flex;align-items:flex-start;gap:10px;font-size:14px;color:var(--t2);line-height:1.5;}
.rcheck{width:20px;height:20px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0;margin-top:1px;font-weight:800;}
.role-client .rcheck{background:var(--vl);color:var(--v);}
.role-tech .rcheck{background:var(--cl);color:var(--cd);}

/* ── CTA ── */
.cta-section{padding:0 0 96px;}
.cta-box{background:linear-gradient(135deg,var(--v) 0%,var(--vd) 50%,#4c1d95 100%);border-radius:28px;padding:72px 60px;text-align:center;position:relative;overflow:hidden;margin:0 28px;}
.cta-box::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px);background-size:28px 28px;}
.cta-box-inner{position:relative;z-index:1;}
.cta-box h2{font-family:'Syne',sans-serif;font-size:clamp(24px,4vw,40px);font-weight:800;color:#fff;letter-spacing:-1px;margin-bottom:14px;}
.cta-box p{font-size:16px;color:rgba(255,255,255,.68);max-width:480px;margin:0 auto 36px;line-height:1.75;}
.cta-btns{display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;}

/* ── Contact ── */
.contact-section{background:#fff;}
.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start;}
.contact-form h3,.contact-details h3{font-family:'Syne',sans-serif;font-size:24px;font-weight:800;margin-bottom:8px;letter-spacing:-.5px;}
.contact-form p,.contact-details p{font-size:14px;color:var(--mu);margin-bottom:28px;line-height:1.7;}
.fld{margin-bottom:15px;}
.fld label{display:block;font-size:11px;font-weight:700;color:var(--mu);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;}
.fld input,.fld textarea{width:100%;background:var(--bg);border:1.5px solid var(--br);border-radius:12px;padding:12px 16px;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--t);outline:none;transition:all .2s;}
.fld input:focus,.fld textarea:focus{border-color:var(--v);background:#fff;box-shadow:0 0 0 4px rgba(124,58,237,.09);}
.fld textarea{resize:vertical;min-height:120px;}
.info-row{display:flex;align-items:center;gap:16px;background:var(--bg);border:1.5px solid var(--br);border-radius:16px;padding:16px 20px;margin-bottom:12px;transition:all .2s;}
.info-row:hover{border-color:var(--v);background:var(--vl);}
.info-ico{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--vl),var(--cl));display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0;}
.info-lbl{font-size:11px;color:var(--mu);margin-bottom:2px;}
.info-val{font-size:14px;font-weight:600;color:var(--t2);}

/* ── Footer ── */
.footer{background:var(--t);padding:56px 0 32px;}
.footer-top{display:flex;align-items:flex-start;justify-content:space-between;gap:40px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,.07);flex-wrap:wrap;}
.ft-brand{}
.ft-logo{display:flex;align-items:center;gap:12px;margin-bottom:13px;}
.ft-logo-icon{width:40px;height:40px;}
.ft-name{font-family:'Syne',sans-serif;font-weight:800;font-size:18px;color:#fff;}
.ft-name span{color:#a78bfa;}
.ft-tag{font-size:13px;color:rgba(255,255,255,.36);max-width:230px;line-height:1.65;}
.ft-links h4{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.8px;color:rgba(255,255,255,.28);margin-bottom:14px;}
.ft-links a{display:block;font-size:14px;color:rgba(255,255,255,.5);margin-bottom:10px;transition:color .2s;}
.ft-links a:hover{color:#fff;}
.footer-bottom{display:flex;align-items:center;justify-content:space-between;padding-top:28px;flex-wrap:wrap;gap:12px;}
.ft-copy{font-size:12px;color:rgba(255,255,255,.22);}
.ft-lang{padding:6px 14px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);font-size:12px;font-weight:700;color:rgba(255,255,255,.45);transition:all .2s;text-decoration:none;}
.ft-lang:hover{color:#fff;border-color:var(--vm);}

/* ── Reveal animations ── */
.rev{opacity:0;transform:translateY(28px);transition:opacity .7s cubic-bezier(.16,1,.3,1),transform .7s cubic-bezier(.16,1,.3,1);}
.rev.in{opacity:1;transform:none;}
.rev-l{opacity:0;transform:translateX(-28px);transition:opacity .7s cubic-bezier(.16,1,.3,1),transform .7s cubic-bezier(.16,1,.3,1);}
.rev-l.in{opacity:1;transform:none;}
.rev-r{opacity:0;transform:translateX(28px);transition:opacity .7s cubic-bezier(.16,1,.3,1),transform .7s cubic-bezier(.16,1,.3,1);}
.rev-r.in{opacity:1;transform:none;}
.d1{transition-delay:.08s;}.d2{transition-delay:.16s;}.d3{transition-delay:.24s;}.d4{transition-delay:.32s;}

/* ── RTL ── */
[dir=rtl] .nav-inner,[dir=rtl] .nav-r,[dir=rtl] .hero-btns,[dir=rtl] .hero-stats,[dir=rtl] .roles-grid,[dir=rtl] .footer-top,[dir=rtl] .footer-bottom,[dir=rtl] .cta-btns{flex-direction:row-reverse;}
[dir=rtl] .hero-content{direction:rtl;}
[dir=rtl] .role-list li{flex-direction:row-reverse;}
[dir=rtl] .info-row{flex-direction:row-reverse;}
[dir=rtl] .steps::before{background:linear-gradient(270deg,var(--v),var(--c));}

/* ── Responsive ── */
@media(max-width:960px){
  .hero-h1{font-size:clamp(38px,8vw,60px);}
  .steps{grid-template-columns:1fr 1fr;}
  .steps::before{display:none;}
  .roles-grid{grid-template-columns:1fr;}
  .contact-grid{grid-template-columns:1fr;}
  .cta-box{padding:44px 28px;margin:0 16px;}
  .hero-stats{gap:24px;}
}
@media(max-width:640px){
  .nav-links{display:none;}
  .section{padding:48px 0;}
  .hero{min-height:100dvh;}
  .hero-h1{font-size:clamp(28px,9vw,44px);letter-spacing:-1.5px;}
  .hero-p{font-size:15px;margin-bottom:28px;}
  .hero-btns{gap:10px;flex-direction:column;align-items:stretch;}
  .hero-btns .btn{justify-content:center;width:100%;padding:14px 20px;}
  .hero-stats{gap:18px;}
  .hstat-n{font-size:22px;}
  .hstat-sep{display:none;}
  .steps{grid-template-columns:1fr;}
  .step{padding:22px 18px;}
  .svc-grid{grid-template-columns:repeat(3,1fr);gap:8px;}
  .svc-item{padding:14px 8px;}
  .svc-em{font-size:24px;}
  .svc-nm{font-size:11px;}
  .role{padding:28px 22px;}
  .role h3{font-size:20px;}
  .cta-box{padding:36px 20px;margin:0 12px;border-radius:20px;}
  .cta-box h2{font-size:22px;}
  .cta-btns{flex-direction:column;align-items:stretch;}
  .cta-btns .btn{justify-content:center;}
  .hero-content{padding:0 16px;padding-top:80px;}
  .wrap{padding:0 16px;}
  .nav-inner{padding:0 16px;}
  .footer-top{gap:24px;}
  .ft-links{display:none;}
  .contact-grid{gap:32px;}
  .hero-chip{font-size:10px;padding:5px 14px;}
}
@media(max-width:380px){
  .hero-h1{font-size:clamp(24px,8vw,36px);}
  .svc-grid{grid-template-columns:repeat(2,1fr);}
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav over-hero" id="mainNav">
  <div class="nav-inner">
    <a href="#" class="logo">
      <!-- Animated handyman SVG logo -->
      <svg class="logo-icon" viewBox="0 0 46 46" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="g1" x1="0" y1="0" x2="46" y2="46" gradientUnits="userSpaceOnUse">
            <stop stop-color="#7c3aed"/><stop offset="1" stop-color="#06b6d4"/>
          </linearGradient>
          <linearGradient id="g2" x1="0" y1="0" x2="46" y2="46" gradientUnits="userSpaceOnUse">
            <stop stop-color="#5b21b6"/><stop offset="1" stop-color="#0891b2"/>
          </linearGradient>
        </defs>
        <!-- Rounded square bg -->
        <rect width="46" height="46" rx="13" fill="url(#g1)"/>
        <!-- Hard hat shape -->
        <ellipse cx="23" cy="19" rx="11" ry="7" fill="white" opacity=".95"/>
        <rect x="12" y="22" width="22" height="3" rx="1.5" fill="white" opacity=".95"/>
        <!-- Brim -->
        <rect x="10" y="24" width="26" height="2.5" rx="1.25" fill="white" opacity=".75"/>
        <!-- Wrench body -->
        <rect x="20" y="27" width="6" height="11" rx="3" fill="white" opacity=".9"/>
        <!-- Wrench head top -->
        <ellipse cx="23" cy="27" rx="5" ry="3.5" fill="white" opacity=".9"/>
        <!-- Wrench grip lines -->
        <line x1="20" y1="30" x2="26" y2="30" stroke="url(#g2)" stroke-width="1" opacity=".5"/>
        <line x1="20" y1="33" x2="26" y2="33" stroke="url(#g2)" stroke-width="1" opacity=".5"/>
        <!-- Sparkle top right -->
        <circle cx="36" cy="10" r="2" fill="white" opacity=".6">
          <animate attributeName="opacity" values=".6;1;.6" dur="2s" repeatCount="indefinite"/>
          <animate attributeName="r" values="2;2.8;2" dur="2s" repeatCount="indefinite"/>
        </circle>
        <!-- Sparkle bottom left -->
        <circle cx="10" cy="36" r="1.5" fill="white" opacity=".4">
          <animate attributeName="opacity" values=".4;.9;.4" dur="2.5s" repeatCount="indefinite" begin=".6s"/>
        </circle>
      </svg>
      <div class="logo-txt">
        <div class="logo-main">Khidma <span>DZ</span></div>
        <div class="logo-sub"><?=$lang==='ar'?'خدمات تقنية':'Services techniques'?></div>
      </div>
    </a>
    <div class="nav-links">
      <a href="#services"><?=$lang==='ar'?'الخدمات':'Services'?></a>
      <a href="#how"><?=$lang==='ar'?'كيف يعمل':'Comment ça marche'?></a>
      <a href="#roles"><?=$lang==='ar'?'لمن؟':'Pour qui ?'?></a>
      <a href="#contact">Contact</a>
    </div>
    <div class="nav-r">
      <a href="?lang=<?=$lang==='ar'?'fr':'ar'?>" class="btn btn-v lang-toggle" style="color:#000;"><?=$lang==='ar'?'FR':'عربي'?></a>
      <a href="login.php" class="btn btn-g" style="display:none" id="loginBtn"><?=$lang==='ar'?'دخول':'Connexion'?></a>
      <a href="register-client.php" class="btn btn-v" style="display:none;font-size:13px;padding:9px 18px" id="startBtn"><?=$lang==='ar'?'ابدأ مجانًا':'Commencer'?></a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="hero">
  <div class="hero-photo"></div>
  <div class="hero-violet-glow"></div>
  <div class="hero-cyan-glow"></div>
  <div class="hero-content">
    <div class="hero-chip rev"><span class="chip-pulse"></span><?=$lang==='ar'?'منصة الخدمات التقنية — الجزائر':'Plateforme technique — Algérie'?></div>
    <h1 class="hero-h1 rev d1">
      <?php if($lang==='ar'): ?>
      تقنيون<br><span class="grad">موثوقون،</span><br>في كل الجزائر
      <?php else: ?>
      Techniciens<br><span class="grad">qualifiés,</span><br>partout en Algérie
      <?php endif; ?>
    </h1>
    <p class="hero-p rev d2"><?=$lang==='ar'?'اربط نفسك بتقنيين محليين موثوقين في السباكة، الكهرباء، النجارة، الدهان وأكثر. حجز سريع، تقييمات حقيقية، دفع آمن.':'Connectez-vous à des prestataires locaux qualifiés — plomberie, électricité, menuiserie, peinture et plus. Réservation rapide, avis vérifiés, paiement sécurisé.'?></p>
    <div class="hero-btns rev d3">
      <a href="register-client.php" class="btn btn-v">🔍 <?=$lang==='ar'?'أبحث عن تقني':'Je cherche un technicien'?></a>
      <a href="register-tech.php" class="btn btn-ghost-w">🔧 <?=$lang==='ar'?'أنا تقني':'Je suis technicien'?></a>
    </div>
    <div class="hero-stats rev d4">
      <div class="hstat"><div class="hstat-n" data-count="500">0</div><div class="hstat-l"><?=$lang==='ar'?'تقنيون':'Techniciens'?></div></div>
      <div class="hstat-sep"></div>
      <div class="hstat"><div class="hstat-n">69</div><div class="hstat-l"><?=$lang==='ar'?'ولاية':'Wilayas'?></div></div>
      <div class="hstat-sep"></div>
      <div class="hstat"><div class="hstat-n">4.8★</div><div class="hstat-l"><?=$lang==='ar'?'تقييم':'Note moy.'?></div></div>
    </div>
  </div>
</section>

<!-- TICKER -->
<div class="ticker-wrap">
  <div class="ticker" id="ticker">
    <?php for($i=0;$i<2;$i++): foreach($svcs as $s): ?>
    <div class="ticker-item"><?=$s['emoji']?> <?=htmlspecialchars($lang==='ar'?$s['ar']:$s['fr'])?> <span class="ticker-sep"> · </span></div>
    <?php endforeach; endfor; ?>
  </div>
</div>

<!-- SERVICES -->
<section id="services" class="section svc-section">
  <div class="wrap">
    <div class="sh center">
      <div class="eyebrow center"><?=$lang==='ar'?'خدماتنا':'Nos services'?></div>
      <h2 class="section-title rev"><?=$lang==='ar'?'كل ما تحتاجه <span class="grad">في مكان واحد</span>':'Tout ce dont vous avez besoin <span class="grad">en un seul endroit</span>'?></h2>
      <p class="section-sub rev d1"><?=$lang==='ar'?'تقنيون مؤهلون في كل المجالات، متاحون للتدخل في أسرع وقت ممكن.':'Des prestataires qualifiés dans tous les domaines, disponibles pour intervenir rapidement.'?></p>
    </div>
    <div class="svc-grid">
      <?php foreach($svcs as $k=>$s): ?>
      <a href="register-client.php" class="svc-item rev">
        <span class="svc-em"><?=$s['emoji']?></span>
        <div class="svc-nm"><?=htmlspecialchars($lang==='ar'?$s['ar']:$s['fr'])?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how" class="section how-section">
  <div class="wrap">
    <div class="sh center">
      <div class="eyebrow center"><?=$lang==='ar'?'كيف يعمل':'Fonctionnement'?></div>
      <h2 class="section-title rev"><?=$lang==='ar'?'بسيط في <span class="grad">4 خطوات</span>':'Simple en <span class="grad">4 étapes</span>'?></h2>
    </div>
    <div class="steps">
      <?php
      $steps=$lang==='ar'?[
        ['h'=>'أنشئ حسابك','p'=>'سجّل مجانًا كعميل أو تقني في أقل من دقيقتين.'],
        ['h'=>'ابحث أو استقبل','p'=>'العملاء يجدون التقنيين — التقنيون يستقبلون الطلبات.'],
        ['h'=>'احجز وتواصل','p'=>'اتفق على الموعد والسعر مباشرة عبر المنصة.'],
        ['h'=>'ادفع وقيّم','p'=>'بعد الانتهاء، سدّد الفاتورة وشارك تقييمك.'],
      ]:[
        ['h'=>'Créez votre compte','p'=>'Inscrivez-vous gratuitement en tant que client ou technicien en moins de 2 min.'],
        ['h'=>'Cherchez ou recevez','p'=>'Les clients trouvent des techniciens — les techniciens reçoivent des demandes.'],
        ['h'=>'Réservez & communiquez','p'=>'Convenez de la date et du tarif directement sur la plateforme.'],
        ['h'=>'Payez & évaluez','p'=>'Une fois terminé, réglez et laissez un avis pour la communauté.'],
      ];
      foreach($steps as $i=>$s): ?>
      <div class="step rev d<?=$i+1?>">
        <div class="step-n"><?=$i+1?></div>
        <div class="step-h"><?=$s['h']?></div>
        <p class="step-p"><?=$s['p']?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ROLES -->
<section id="roles" class="section roles-section">
  <div class="wrap">
    <div class="sh center">
      <div class="eyebrow center"><?=$lang==='ar'?'المنصة لكما معًا':'La plateforme pour vous deux'?></div>
      <h2 class="section-title rev"><?=$lang==='ar'?'عملاء وتقنيون — <span class="grad">شركاء حقيقيون</span>':'Clients et techniciens — <span class="grad">de vrais partenaires</span>'?></h2>
      <p class="section-sub rev d1"><?=$lang==='ar'?'التقنيون ليسوا موظفين — هم شركاؤنا الذين يُحركون المنصة.':'Les techniciens ne travaillent pas pour nous — ils sont nos partenaires qui font vivre la plateforme.'?></p>
    </div>
    <div class="roles-grid">
      <div class="role role-client rev-l">
        <div class="role-badge">👤 <?=$lang==='ar'?'للعملاء':'Pour les clients'?></div>
        <h3><?=$lang==='ar'?'تجد من يساعدك بسرعة':'Trouvez de l\'aide rapidement'?></h3>
        <p class="role-sub"><?=$lang==='ar'?'بدون تنقل أو مكالمات — كل شيء من هاتفك.':'Sans déplacement ni appels — tout depuis votre téléphone.'?></p>
        <ul class="role-list">
          <?php foreach($lang==='ar'?['بحث بالخدمة، الولاية والسعر','ملفات التقنيين مع تقييمات حقيقية','حجز وتتبع الطلب لحظة بلحظة','تواصل مباشر مع التقني','دفع آمن وفاتورة إلكترونية','فتح نزاع في حال وجود مشكلة']:['Recherche par service, wilaya et tarif','Profils détaillés avec avis vérifiés','Réservation et suivi en temps réel','Messagerie directe avec le technicien','Paiement sécurisé et facture électronique','Ouverture de litige si nécessaire'] as $f): ?>
          <li><span class="rcheck">✓</span><?=$f?></li>
          <?php endforeach; ?>
        </ul>
        <a href="register-client.php" class="btn btn-v-sm"><?=$lang==='ar'?'إنشاء حساب عميل':'Créer mon compte client'?></a>
        <div class="role-bg">🏠</div>
      </div>
      <div class="role role-tech rev-r">
        <div class="role-badge">🔧 <?=$lang==='ar'?'للتقنيين':'Pour les techniciens'?></div>
        <h3><?=$lang==='ar'?'طوّر نشاطك معنا':'Développez votre activité'?></h3>
        <p class="role-sub"><?=$lang==='ar'?'نحن شركاؤك — تعمل باستقلالية كاملة ونُسهم في نموك.':'Nous sommes vos partenaires — travaillez en autonomie, nous contribuons à votre croissance.'?></p>
        <ul class="role-list">
          <?php foreach($lang==='ar'?['ملف مهني وظهور أمام آلاف العملاء','طلبات جديدة بإشعارات فورية','إدارة مواعيدك وتوافرك بنفسك','لوحة إيرادات لمتابعة أرباحك','تقييمات العملاء تقوي سمعتك','دعم الإدارة في حال النزاعات']:['Profil visible par des milliers de clients','Nouvelles demandes avec notifications instantanées','Gestion autonome de vos disponibilités','Tableau de bord de suivi des revenus','Avis clients pour renforcer votre réputation','Support de l\'équipe en cas de litige'] as $f): ?>
          <li><span class="rcheck">✓</span><?=$f?></li>
          <?php endforeach; ?>
        </ul>
        <a href="register-tech.php" class="btn btn-c-sm"><?=$lang==='ar'?'أنضم كتقني':'Rejoindre comme technicien'?></a>
        <div class="role-bg">🔧</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div>
    <div class="cta-box rev">
      <div class="cta-box-inner">
        <h2><?=$lang==='ar'?'جاهز للبدء؟ سجّل الآن مجانًا':'Prêt à commencer ? Inscrivez-vous gratuitement'?></h2>
        <p><?=$lang==='ar'?'انضم إلى مئات التقنيين والعملاء الذين يثقون في خدمة DZ كل يوم.':'Rejoignez des centaines de techniciens et clients qui font confiance à Khidma DZ chaque jour.'?></p>
        <div class="cta-btns">
          <a href="register-client.php" class="btn btn-white">🔍 <?=$lang==='ar'?'أبحث عن تقني':'Je cherche un technicien'?></a>
          <a href="register-tech.php" class="btn btn-outline-w">🔧 <?=$lang==='ar'?'أنضم كتقني':'Rejoindre comme technicien'?></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section id="contact" class="section contact-section">
  <div class="wrap">
    <div class="sh">
      <div class="eyebrow"><?=$lang==='ar'?'تواصل معنا':'Contact'?></div>
      <h2 class="section-title rev"><?=$lang==='ar'?'هل لديك <span class="grad">سؤال؟</span>':'Une <span class="grad">question ?</span>'?></h2>
    </div>
    <div class="contact-grid">
      <div class="contact-form rev-l">
        <h3><?=$lang==='ar'?'اكتب لنا':'Écrivez-nous'?></h3>
        <p><?=$lang==='ar'?'فريقنا يرد في أسرع وقت ممكن.':'Notre équipe vous répond dans les plus brefs délais.'?></p>
        <form method="POST" action="actions/handle_contact.php">
          <div class="fld"><label><?=$lang==='ar'?'الاسم':'Nom'?></label><input name="nom" type="text" required placeholder="<?=$lang==='ar'?'اسمك الكامل':'Votre nom complet'?>"></div>
          <div class="fld"><label>Email</label><input name="email" type="email" required placeholder="vous@example.com"></div>
          <div class="fld"><label><?=$lang==='ar'?'الرسالة':'Message'?></label><textarea name="message" required placeholder="<?=$lang==='ar'?'اكتب رسالتك...':'Votre message…'?>"></textarea></div>
          <button type="submit" class="btn btn-v" style="padding:13px 28px;font-size:15px"><?=$lang==='ar'?'إرسال':'Envoyer'?></button>
        </form>
      </div>
      <div class="contact-details rev-r">
        <h3><?=$lang==='ar'?'تواصل مباشر':'Contactez-nous'?></h3>
        <p><?=$lang==='ar'?'متاحون للإجابة على أسئلتك ومساعدتك في التسجيل.':'Disponibles pour répondre à vos questions et vous aider à vous inscrire.'?></p>
        <div class="info-row"><div class="info-ico">✉️</div><div><div class="info-lbl">Email</div><div class="info-val">contact@khidmadz.dz</div></div></div>
        <div class="info-row"><div class="info-ico">📍</div><div><div class="info-lbl"><?=$lang==='ar'?'التغطية':'Couverture'?></div><div class="info-val"><?=$lang==='ar'?'الجزائر — 69 ولاية':'Algérie — 69 wilayas'?></div></div></div>
        <div class="info-row"><div class="info-ico">🕒</div><div><div class="info-lbl"><?=$lang==='ar'?'ساعات الدعم':'Support'?></div><div class="info-val"><?=$lang==='ar'?'7 أيام / 8ص–8م':'7j/7 · 8h–20h'?></div></div></div>
        <div style="margin-top:22px;display:flex;gap:10px;flex-wrap:wrap">
          <a href="register-client.php" class="btn btn-v-sm"><?=$lang==='ar'?'سجّل كعميل':'Inscription client'?></a>
          <a href="register-tech.php" class="btn btn-c-sm"><?=$lang==='ar'?'سجّل كتقني':'Inscription technicien'?></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="wrap">
    <div class="footer-top">
      <div class="ft-brand">
        <div class="ft-logo">
          <svg class="ft-logo-icon" viewBox="0 0 46 46" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="46" height="46" rx="13" fill="url(#fg)"/>
            <ellipse cx="23" cy="19" rx="11" ry="7" fill="white" opacity=".9"/>
            <rect x="12" y="22" width="22" height="3" rx="1.5" fill="white" opacity=".9"/>
            <rect x="10" y="24" width="26" height="2.5" rx="1.25" fill="white" opacity=".7"/>
            <rect x="20" y="27" width="6" height="11" rx="3" fill="white" opacity=".85"/>
            <ellipse cx="23" cy="27" rx="5" ry="3.5" fill="white" opacity=".85"/>
            <defs><linearGradient id="fg" x1="0" y1="0" x2="46" y2="46" gradientUnits="userSpaceOnUse"><stop stop-color="#7c3aed"/><stop offset="1" stop-color="#06b6d4"/></linearGradient></defs>
          </svg>
          <div class="ft-name">Khidma <span>DZ</span></div>
        </div>
        <div class="ft-tag"><?=$lang==='ar'?'منصة الخدمات التقنية في الجزائر — تقنيون موثوقون في متناول يدك.':'Plateforme de services techniques en Algérie — des techniciens de confiance à portée de main.'?></div>
      </div>
      <div class="ft-links">
        <h4><?=$lang==='ar'?'المنصة':'Plateforme'?></h4>
        <a href="#services"><?=$lang==='ar'?'الخدمات':'Services'?></a>
        <a href="#how"><?=$lang==='ar'?'كيف يعمل':'Comment ça marche'?></a>
        <a href="#roles"><?=$lang==='ar'?'لمن؟':'Pour qui ?'?></a>
        <a href="#contact">Contact</a>
      </div>
      <div class="ft-links">
        <h4><?=$lang==='ar'?'الحساب':'Compte'?></h4>
        <a href="login.php"><?=$lang==='ar'?'تسجيل الدخول':'Connexion'?></a>
        <a href="register-client.php"><?=$lang==='ar'?'سجّل كعميل':'Inscription client'?></a>
        <a href="register-tech.php"><?=$lang==='ar'?'سجّل كتقني':'Inscription technicien'?></a>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="ft-copy">© <?=date('Y')?> Khidma DZ — <?=$lang==='ar'?'جميع الحقوق محفوظة':'Tous droits réservés'?></div>
      <a href="?lang=<?=$lang==='ar'?'fr':'ar'?>" class="ft-lang"><?=$lang==='ar'?'Français':'عربي'?></a>
    </div>
  </div>
</footer>

<script>
// Nav state
const nav=document.getElementById('mainNav');
const loginBtn=document.getElementById('loginBtn');
const startBtn=document.getElementById('startBtn');
window.addEventListener('scroll',()=>{
  const scrolled=window.scrollY>80;
  nav.classList.toggle('scrolled',scrolled);
  nav.classList.toggle('over-hero',!scrolled);
  if(loginBtn){loginBtn.style.display=scrolled?'inline-flex':'none';}
  if(startBtn){startBtn.style.display=scrolled?'inline-flex':'none';}
},{passive:true});

// Ticker seamless
(function(){const t=document.getElementById('ticker');if(t)t.innerHTML+=t.innerHTML;})();

// Counter
function countUp(el,target){
  let c=0;const step=target/70;const suffix=el.dataset.suffix||'';
  const iv=setInterval(()=>{c=Math.min(c+step,target);el.textContent=Math.floor(c)+(target>=100?'+':'')+suffix;if(c>=target)clearInterval(iv);},16);
}
const cObs=new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting&&e.target.dataset.count){countUp(e.target,parseInt(e.target.dataset.count));cObs.unobserve(e.target);}});
},{threshold:.6});
document.querySelectorAll('[data-count]').forEach(el=>cObs.observe(el));

// Scroll reveal
const rObs=new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting)e.target.classList.add('in');});
},{threshold:.1});
document.querySelectorAll('.rev,.rev-l,.rev-r').forEach(el=>rObs.observe(el));
</script>
<script src="js/khidma-pwa.js" defer></script>
</body>
</html>