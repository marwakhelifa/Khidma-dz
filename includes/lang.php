<?php
// ============================================================
// includes/lang.php — Shared language helper for ALL pages
// ============================================================

function get_lang(): string {
    // Ensure session is available so we can persist user language
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $allowed = ['fr', 'ar'];

    // Explicit override via ?lang=
    if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed)) {
        $l = $_GET['lang'];
        $_SESSION['user_lang'] = $l;
        setcookie('khidma_lang', $l, time() + 60*60*24*365, '/');
        // If user is logged in, persist choice in DB when possible
        if (!empty($_SESSION['user_id']) && isset($GLOBALS['pdo'])) {
            try { $GLOBALS['pdo']->prepare("UPDATE utilisateur SET langue=? WHERE id_user=?")->execute([$l, $_SESSION['user_id']]); } catch (Exception $e) {}
        }
        return $l;
    }

    // Session-stored language (set at login or via previous actions)
    if (!empty($_SESSION['user_lang']) && in_array($_SESSION['user_lang'], $allowed)) {
        return $_SESSION['user_lang'];
    }

    // If user is logged in but session language is missing, try DB (helps intermittent cases)
    if (!empty($_SESSION['user_id']) && isset($GLOBALS['pdo'])) {
        try {
            $stmt = $GLOBALS['pdo']->prepare("SELECT langue FROM utilisateur WHERE id_user=? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();
            if ($row && in_array($row['langue'], $allowed)) {
                $_SESSION['user_lang'] = $row['langue'];
                setcookie('khidma_lang', $row['langue'], time() + 60*60*24*365, '/');
                return $row['langue'];
            }
        } catch (Exception $e) {}
    }

    // Fallback to cookie
    if (!empty($_COOKIE['khidma_lang']) && in_array($_COOKIE['khidma_lang'], $allowed)) {
        $_SESSION['user_lang'] = $_COOKIE['khidma_lang'];
        return $_COOKIE['khidma_lang'];
    }

    // Default
    return 'fr';
}

function t(string $key, string $lang = 'fr'): string {
    static $cache = null;
    if ($cache === null) $cache = _all_tr();
    return $cache[$lang][$key] ?? $cache['fr'][$key] ?? $key;
}

function _all_tr(): array { return [
'fr' => [
    'site_name'=>'Khidma DZ','back_home'=>"← Retour à l'accueil",
    'lang_label'=>'عربي','lang_target'=>'ar',
    // Login
    'login_title'=>'Connexion',
    'login_subtitle'=>'Connectez-vous en tant que <strong>client</strong>, <strong>technicien</strong> ou <strong>administrateur</strong>.',
    'login_email'=>'Adresse e-mail','login_password'=>'Mot de passe','login_btn'=>'Se connecter',
    'login_no_account'=>'Pas encore de compte ?','login_as_client'=>'Je suis client','login_as_tech'=>'Je suis technicien',
    // Register client
    'reg_client_title'=>'Inscription client',
    'reg_client_sub'=>'Créez un compte pour réserver des services techniques en quelques clics.',
    'reg_nom'=>'Nom','reg_prenom'=>'Prénom','reg_email'=>'Adresse e-mail',
    'reg_phone'=>'Numéro de téléphone','reg_city'=>'Ville / Wilaya',
    'reg_password'=>'Mot de passe','reg_password_conf'=>'Confirmer le mot de passe',
    'reg_btn_client'=>'Créer mon compte','reg_already'=>'Déjà un compte ?','reg_login_link'=>'Se connecter',
    'reg_detect_loc'=>'Détecter ma position',
    // Register tech
    'reg_tech_title'=>'Inscription technicien',
    'reg_tech_sub'=>'Créez votre profil pour apparaître dans les recherches clients.',
    'reg_specialty'=>'Spécialité','reg_specialty_ph'=>'Choisir une spécialité',
    'reg_experience'=>'Expérience (années)','reg_tarif'=>'Tarif indicatif (DA)',
    'reg_bio'=>'Description / Bio','reg_bio_ph'=>'Décrivez vos compétences et services...',
    'reg_cv'=>'CV (PDF)','reg_diplome'=>'Diplôme (PDF)',
    'reg_btn_tech'=>'Soumettre ma candidature','reg_already_tech'=>'Déjà inscrit ?',
    // Homepage nav
    'home_nav_home'=>'Accueil','home_nav_services'=>'Services',
    'home_nav_how'=>"Comment ça marche",'home_nav_who'=>'Pour qui ?',
    'home_nav_contact'=>'Contact','home_nav_login'=>'Connexion',
    // Homepage hero
    'home_hero_h1'=>'Khidma DZ',
    'home_hero_sub'=>"La plateforme qui connecte les <strong>clients</strong> avec des <strong>prestataires techniques qualifiés</strong> (plomberie, électricité, menuiserie, peinture, réparation d'électroménager, et plus).",
    'home_hero_sub2'=>'Recherche, réservation, paiement et évaluation en ligne, en toute transparence.',
    'home_btn_client'=>'Je suis un client','home_btn_tech'=>'Je suis un technicien',
    'home_hero_note'=>'Interface disponible en <strong>français</strong> et <strong>arabe</strong>.',
    'home_search_title'=>"Besoin d'un service technique ?",
    'home_search_type'=>'Type de service','home_search_city'=>'Ville / Localisation',
    'home_search_city_ph'=>'Alger, Oran, Constantine...','home_search_btn'=>'Rechercher un technicien',
    // Homepage sections
    'home_about_h2'=>'Objectif de Khidma DZ',
    'home_about_p1'=>"Khidma DZ est une plateforme numérique centralisée qui facilite la mise en relation entre les utilisateurs ayant un besoin de <strong>services techniques de proximité</strong> et des <strong>prestataires locaux qualifiés et vérifiés</strong>.",
    'home_about_p2'=>"L'application vise à structurer le processus de <strong>recherche</strong>, de <strong>réservation</strong>, de <strong>paiement</strong> et d'<strong>évaluation</strong> des prestations artisanales.",
    'home_features_h2'=>'Fonctionnalités clés','home_services_h2'=>'Services proposés',
    'home_how_h2'=>"Comment ça marche ?",
    'home_step1_h'=>'Recherche','home_step1_p'=>'Le client recherche un technicien par type de service, localisation, tarif et note.',
    'home_step2_h'=>'Réservation','home_step2_p'=>'Il réserve un créneau et décrit son besoin. Le technicien accepte ou refuse.',
    'home_step3_h'=>'Intervention','home_step3_p'=>"Le technicien se déplace selon l'adresse et l'horaire convenus.",
    'home_step4_h'=>'Paiement & évaluation','home_step4_p'=>'Le client paie et laisse une note et un avis.',
    'home_who_h2'=>'Pour qui ?','home_clients_h'=>'Clients','home_techs_h'=>'Prestataires (Techniciens)','home_admin_h'=>'Administrateurs',
    'home_contact_h2'=>'Contact & Support','home_contact_sub'=>"Pour toute question, suggestion ou demande d'information.",
    'home_contact_name'=>'Nom et prénom','home_contact_email'=>'Adresse e-mail',
    'home_contact_msg'=>'Votre message','home_contact_msg_ph'=>'Décrivez votre demande...',
    'home_contact_btn'=>'Envoyer (démo)','home_footer'=>'© %d Khidma DZ. Tous droits réservés.',
    // Services
    'svc_plomberie'=>'Plomberie','svc_elec'=>'Électricité','svc_menu'=>'Menuiserie',
    'svc_peinture'=>'Peinture','svc_clim'=>'Climatisation','svc_electro'=>'Électroménager',
    'svc_carrelage'=>'Carrelage','svc_maconnerie'=>'Maçonnerie','svc_serrurerie'=>'Serrurerie','svc_autre'=>'Autre',
],
'ar' => [
    'site_name'=>'خدمة DZ','back_home'=>'العودة للرئيسية →',
    'lang_label'=>'FR','lang_target'=>'fr',
    // Login
    'login_title'=>'تسجيل الدخول',
    'login_subtitle'=>'سجّل دخولك كـ <strong>عميل</strong> أو <strong>تقني</strong> أو <strong>مدير</strong>.',
    'login_email'=>'البريد الإلكتروني','login_password'=>'كلمة المرور','login_btn'=>'تسجيل الدخول',
    'login_no_account'=>'ليس لديك حساب؟','login_as_client'=>'أنا عميل','login_as_tech'=>'أنا تقني',
    // Register client
    'reg_client_title'=>'إنشاء حساب عميل',
    'reg_client_sub'=>'أنشئ حسابك لحجز الخدمات التقنية بسهولة.',
    'reg_nom'=>'اللقب','reg_prenom'=>'الاسم','reg_email'=>'البريد الإلكتروني',
    'reg_phone'=>'رقم الهاتف','reg_city'=>'المدينة / الولاية',
    'reg_password'=>'كلمة المرور','reg_password_conf'=>'تأكيد كلمة المرور',
    'reg_btn_client'=>'إنشاء حسابي','reg_already'=>'لديك حساب بالفعل؟','reg_login_link'=>'تسجيل الدخول',
    'reg_detect_loc'=>'تحديد موقعي',
    // Register tech
    'reg_tech_title'=>'تسجيل تقني',
    'reg_tech_sub'=>'أنشئ ملفك المهني لتظهر في نتائج بحث العملاء.',
    'reg_specialty'=>'التخصص','reg_specialty_ph'=>'اختر تخصصاً',
    'reg_experience'=>'سنوات الخبرة','reg_tarif'=>'الأجر بالساعة (دج)',
    'reg_bio'=>'نبذة / وصف','reg_bio_ph'=>'صف كفاءاتك وخدماتك...',
    'reg_cv'=>'السيرة الذاتية (PDF)','reg_diplome'=>'الشهادة / الدبلوم (PDF)',
    'reg_btn_tech'=>'إرسال طلبي','reg_already_tech'=>'مسجّل بالفعل؟',
    // Homepage nav
    'home_nav_home'=>'الرئيسية','home_nav_services'=>'الخدمات',
    'home_nav_how'=>'كيف يعمل','home_nav_who'=>'لمن؟',
    'home_nav_contact'=>'اتصل بنا','home_nav_login'=>'دخول',
    // Homepage hero
    'home_hero_h1'=>'خدمة DZ',
    'home_hero_sub'=>'المنصة التي تربط <strong>العملاء</strong> بـ<strong>تقنيين محليين مؤهلين</strong> (سباكة، كهرباء، نجارة، دهان، إصلاح أجهزة كهربائية والمزيد).',
    'home_hero_sub2'=>'بحث، حجز، دفع وتقييم عبر الإنترنت، بكل شفافية.',
    'home_btn_client'=>'أنا عميل','home_btn_tech'=>'أنا تقني',
    'home_hero_note'=>'الواجهة متاحة بـ<strong>الفرنسية</strong> و<strong>العربية</strong>.',
    'home_search_title'=>'تحتاج إلى خدمة تقنية؟',
    'home_search_type'=>'نوع الخدمة','home_search_city'=>'المدينة / الموقع',
    'home_search_city_ph'=>'الجزائر، وهران، قسنطينة...','home_search_btn'=>'ابحث عن تقني',
    // Homepage sections
    'home_about_h2'=>'هدف خدمة DZ',
    'home_about_p1'=>'خدمة DZ منصة رقمية مركزية تسهّل التواصل بين أصحاب الحاجة لـ<strong>خدمات تقنية قريبة</strong> و<strong>مزودي خدمات محليين مؤهلين</strong>.',
    'home_about_p2'=>'تهدف التطبيقة إلى تنظيم عملية <strong>البحث</strong> و<strong>الحجز</strong> و<strong>الدفع</strong> و<strong>التقييم</strong> للخدمات الحرفية.',
    'home_features_h2'=>'الميزات الرئيسية','home_services_h2'=>'الخدمات المتاحة',
    'home_how_h2'=>'كيف يعمل؟',
    'home_step1_h'=>'البحث','home_step1_p'=>'يبحث العميل عن تقني حسب نوع الخدمة والموقع والسعر والتقييم.',
    'home_step2_h'=>'الحجز','home_step2_p'=>'يحجز موعداً ويصف احتياجه، ويوافق التقني أو يرفض.',
    'home_step3_h'=>'التدخل','home_step3_p'=>'يتنقل التقني إلى العميل حسب العنوان والوقت المتفق عليه.',
    'home_step4_h'=>'الدفع والتقييم','home_step4_p'=>'يدفع العميل ويترك تقييماً ورأيه.',
    'home_who_h2'=>'لمن؟','home_clients_h'=>'العملاء','home_techs_h'=>'مزودو الخدمات (التقنيون)','home_admin_h'=>'المديرون',
    'home_contact_h2'=>'اتصل بنا','home_contact_sub'=>'لأي سؤال أو اقتراح أو طلب معلومات.',
    'home_contact_name'=>'الاسم واللقب','home_contact_email'=>'البريد الإلكتروني',
    'home_contact_msg'=>'رسالتك','home_contact_msg_ph'=>'اشرح طلبك...',
    'home_contact_btn'=>'إرسال (تجريبي)','home_footer'=>'© %d خدمة DZ. جميع الحقوق محفوظة.',
    // Services
    'svc_plomberie'=>'سباكة','svc_elec'=>'كهرباء','svc_menu'=>'نجارة',
    'svc_peinture'=>'دهان','svc_clim'=>'تكييف','svc_electro'=>'أجهزة كهربائية',
    'svc_carrelage'=>'بلاط','svc_maconnerie'=>'بناء','svc_serrurerie'=>'أقفال','svc_autre'=>'أخرى',
],
]; }