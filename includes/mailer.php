<?php
// ═══════════════════════════════════════════════════════
//  mailer.php — Khidma DZ email sender via PHPMailer
//  Requires PHPMailer in includes/PHPMailer/
// ═══════════════════════════════════════════════════════

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

// ── Config (voir includes/config.php) ─────────────────
require_once __DIR__ . '/config.php';

// ── Core send function ────────────────────────────────
function send_email(string $to_email, string $to_name, string $subject, string $html_body): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = email_wrap($subject, $html_body);
        $mail->AltBody = strip_tags($html_body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ── Email HTML wrapper (branded template) ────────────
function email_wrap(string $title, string $content): string {
    return '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>'.$title.'</title></head>
<body style="margin:0;padding:0;background:#f0f0f8;font-family:\'Helvetica Neue\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f8;padding:40px 20px">
  <tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0" style="max-width:580px;width:100%">

      <!-- HEADER -->
      <tr><td style="background:linear-gradient(135deg,#7c3aed,#06b6d4);border-radius:16px 16px 0 0;padding:32px 40px;text-align:center">
        <div style="display:inline-block;background:rgba(255,255,255,.15);border-radius:12px;padding:10px 18px">
          <span style="font-size:22px;font-weight:900;color:#fff;letter-spacing:-0.5px">Khidma <span style="color:#c4b5fd">DZ</span></span>
        </div>
      </td></tr>

      <!-- BODY -->
      <tr><td style="background:#ffffff;padding:36px 40px;border-radius:0 0 16px 16px;box-shadow:0 4px 24px rgba(0,0,0,.08)">
        '.$content.'
        <hr style="border:none;border-top:1px solid #e8e4f8;margin:28px 0">
        <p style="font-size:12px;color:#9ca3af;text-align:center;margin:0;line-height:1.6">
          Khidma DZ · Plateforme de services techniques en Algérie<br>
          Si vous n\'avez pas demandé cet email, ignorez-le.
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>';
}

// ══════════════════════════════════════════════════════
//  SPECIFIC EMAIL FUNCTIONS
// ══════════════════════════════════════════════════════

// ── 1. Password reset ─────────────────────────────────
function send_reset_email(string $email, string $nom, string $prenom, string $token): bool {
    $link = SITE_URL . '/reset-password.php?token=' . urlencode($token);
    $html = '
        <h2 style="font-size:22px;font-weight:800;color:#0f0c1d;margin:0 0 8px">Réinitialisation du mot de passe</h2>
        <p style="font-size:15px;color:#3d3655;margin:0 0 24px">Bonjour <strong>'.$prenom.' '.$nom.'</strong>,</p>
        <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 28px">
            Vous avez demandé à réinitialiser votre mot de passe sur Khidma DZ.<br>
            Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.
        </p>
        <div style="text-align:center;margin:0 0 28px">
            <a href="'.$link.'" style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;padding:14px 36px;border-radius:12px;font-size:15px;font-weight:700;letter-spacing:-.3px">
                🔑 Réinitialiser mon mot de passe
            </a>
        </div>
        <p style="font-size:12px;color:#9ca3af;text-align:center;margin:0 0 8px">Ce lien expire dans <strong>1 heure</strong>.</p>
        <p style="font-size:11px;color:#d1d5db;text-align:center;word-break:break-all;margin:0">'.$link.'</p>';

    return send_email($email, $prenom.' '.$nom, '🔑 Réinitialisation de votre mot de passe — Khidma DZ', $html);
}

// ── 2. Welcome email (client) ─────────────────────────
function send_welcome_client(string $email, string $nom, string $prenom): bool {
    $html = '
        <h2 style="font-size:22px;font-weight:800;color:#0f0c1d;margin:0 0 8px">Bienvenue sur Khidma DZ 🎉</h2>
        <p style="font-size:15px;color:#3d3655;margin:0 0 20px">Bonjour <strong>'.$prenom.' '.$nom.'</strong>,</p>
        <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 24px">
            Votre compte client a été créé avec succès. Vous pouvez dès maintenant rechercher des techniciens qualifiés près de chez vous et réserver vos interventions en quelques clics.
        </p>
        <div style="text-align:center;margin:0 0 24px">
            <a href="'.SITE_URL.'/dashboard-client.php" style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;padding:14px 36px;border-radius:12px;font-size:15px;font-weight:700">
                Accéder à mon espace
            </a>
        </div>';
    return send_email($email, $prenom.' '.$nom, '🎉 Bienvenue sur Khidma DZ !', $html);
}

// ── 3. Welcome email (technicien) ────────────────────
function send_welcome_tech(string $email, string $nom, string $prenom): bool {
    $html = '
        <h2 style="font-size:22px;font-weight:800;color:#0f0c1d;margin:0 0 8px">Inscription technicien reçue ✅</h2>
        <p style="font-size:15px;color:#3d3655;margin:0 0 20px">Bonjour <strong>'.$prenom.' '.$nom.'</strong>,</p>
        <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 24px">
            Votre demande d\'inscription en tant que technicien a bien été reçue. Notre équipe va examiner votre profil et vous recevrez un email de confirmation une fois votre compte validé.
        </p>
        <div style="background:#f3f0ff;border:1.5px solid #c4b5fd;border-radius:12px;padding:16px 20px;margin:0 0 24px">
            <p style="font-size:13px;color:#7c3aed;margin:0;font-weight:600">⏳ Délai de validation : 24 à 48 heures</p>
        </div>';
    return send_email($email, $prenom.' '.$nom, '✅ Inscription technicien — En attente de validation', $html);
}

// ── 4. Tech account approved ──────────────────────────
function send_tech_approved(string $email, string $nom, string $prenom): bool {
    $html = '
        <h2 style="font-size:22px;font-weight:800;color:#0f0c1d;margin:0 0 8px">Votre compte a été approuvé 🎉</h2>
        <p style="font-size:15px;color:#3d3655;margin:0 0 20px">Bonjour <strong>'.$prenom.' '.$nom.'</strong>,</p>
        <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 24px">
            Bonne nouvelle ! Votre profil technicien sur Khidma DZ a été vérifié et approuvé. Vous pouvez maintenant recevoir des demandes de clients et commencer à travailler.
        </p>
        <div style="text-align:center;margin:0 0 24px">
            <a href="'.SITE_URL.'/dashboard-tech.php" style="display:inline-block;background:#06b6d4;color:#fff;text-decoration:none;padding:14px 36px;border-radius:12px;font-size:15px;font-weight:700">
                Accéder à mon tableau de bord
            </a>
        </div>';
    return send_email($email, $prenom.' '.$nom, '🎉 Compte approuvé — Khidma DZ', $html);
}

// ── 5. New reservation (tech gets notified) ───────────
function send_new_reservation(string $email, string $tech_nom, string $tech_prenom, string $client_prenom, string $date, string $description): bool {
    $html = '
        <h2 style="font-size:22px;font-weight:800;color:#0f0c1d;margin:0 0 8px">Nouvelle demande de réservation 📋</h2>
        <p style="font-size:15px;color:#3d3655;margin:0 0 20px">Bonjour <strong>'.$tech_prenom.' '.$tech_nom.'</strong>,</p>
        <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 20px">
            <strong>'.$client_prenom.'</strong> a fait une demande d\'intervention pour le <strong>'.date('d/m/Y', strtotime($date)).'</strong>.
        </p>
        <div style="background:#f0fdfe;border:1.5px solid #67e8f9;border-radius:12px;padding:16px 20px;margin:0 0 24px">
            <p style="font-size:13px;color:#0891b2;margin:0 0 6px;font-weight:700">Description :</p>
            <p style="font-size:13px;color:#164e63;margin:0;line-height:1.6">'.htmlspecialchars($description).'</p>
        </div>
        <div style="text-align:center;margin:0 0 24px">
            <a href="'.SITE_URL.'/dashboard-tech.php?tab=demandes" style="display:inline-block;background:#06b6d4;color:#fff;text-decoration:none;padding:14px 36px;border-radius:12px;font-size:15px;font-weight:700">
                Voir la demande
            </a>
        </div>';
    return send_email($email, $tech_prenom.' '.$tech_nom, '📋 Nouvelle demande — Khidma DZ', $html);
}

// ── 6. Reservation confirmed (client gets notified) ───
function send_reservation_confirmed(string $email, string $client_nom, string $client_prenom, string $tech_prenom, string $date): bool {
    $html = '
        <h2 style="font-size:22px;font-weight:800;color:#0f0c1d;margin:0 0 8px">Réservation confirmée ✅</h2>
        <p style="font-size:15px;color:#3d3655;margin:0 0 20px">Bonjour <strong>'.$client_prenom.' '.$client_nom.'</strong>,</p>
        <p style="font-size:14px;color:#6b7280;line-height:1.7;margin:0 0 24px">
            Votre demande a été <strong>acceptée</strong> par <strong>'.$tech_prenom.'</strong> pour le <strong>'.date('d/m/Y', strtotime($date)).'</strong>. Vous pouvez le contacter via la messagerie.
        </p>
        <div style="text-align:center;margin:0 0 24px">
            <a href="'.SITE_URL.'/dashboard-client.php?tab=reservations" style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;padding:14px 36px;border-radius:12px;font-size:15px;font-weight:700">
                Voir ma réservation
            </a>
        </div>';
    return send_email($email, $client_prenom.' '.$client_nom, '✅ Réservation confirmée — Khidma DZ', $html);
}