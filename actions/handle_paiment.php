<?php
// ═══════════════════════════════════════════════════════════════
//  actions/handle_paiment.php
//  Handles: set_price | client_confirm | tech_confirm
// ═══════════════════════════════════════════════════════════════
session_start();
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/lang.php';

$lang  = get_lang();
$is_ar = $lang === 'ar';

// ── Auth ──────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'client';
$action    = $_POST['action']    ?? '';
$id_service= intval($_POST['id_service'] ?? 0);

if (!$id_service) {
    header('Location: ../dashboard-client.php'); exit;
}

function flash($msg, $type = 'ok') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function redirect($id) {
    header("Location: ../paiment.php?id_service=$id"); exit;
}

function calc_ht_ttc(PDO $pdo, int $id_service, array $svc = []): array {
    $calc = $pdo->prepare("SELECT COALESCE(SUM(p.prix_unitaire * sp.quantite),0) FROM service_prestation sp JOIN prestation p ON p.id_prestation=sp.id_prestation WHERE sp.id_service=?");
    $calc->execute([$id_service]);
    $montant_ht = floatval($calc->fetchColumn());
    if (!$montant_ht) {
        $montant_ht = floatval($svc['prix_final'] ?? $svc['prix_estime'] ?? 0);
    }

    $tva_rate = 0.19;
    $apply_tva = facture_uses_tva($montant_ht);
    $tva_amount = $apply_tva ? round($montant_ht * $tva_rate, 2) : 0;
    $montant_ttc = round($montant_ht + $tva_amount, 2);

    return [$montant_ht, $tva_rate, $tva_amount, $montant_ttc];
}

// ── Fetch service ─────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.*, c.id_client, c.id_user AS client_uid,
           t.id_technicien, t.id_user AS tech_uid,
           uc.nom AS cn, uc.prenom AS cp, uc.email AS cemail,
           ut.nom AS tn, ut.prenom AS tp,
           p.id_paiment, p.statut_paiment
    FROM service s
    JOIN client c      ON c.id_client     = s.id_client
    JOIN utilisateur uc ON uc.id_user     = c.id_user
    JOIN technicien t  ON t.id_technicien = s.id_technicien
    JOIN utilisateur ut ON ut.id_user     = t.id_user
    LEFT JOIN paiment p ON p.id_service   = s.id_service
    WHERE s.id_service = ?
");
$stmt->execute([$id_service]);
$svc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$svc) { header('Location: ../dashboard-client.php'); exit; }

// ── Access control ────────────────────────────
$is_tech   = ($user_role === 'technicien' && $svc['tech_uid']   == $user_id);
$is_client = ($user_role === 'client'     && $svc['client_uid'] == $user_id);
$is_admin  = ($user_role === 'admin');

if (!$is_tech && !$is_client && !$is_admin) {
    flash($is_ar ? 'غير مصرح.' : 'Accès refusé.', 'err');
    redirect($id_service);
}

if (($svc['statut_service'] ?? '') === 'annulé') {
    flash($is_ar ? 'الخدمة ملغاة ولا يمكن إنشاء دفعة أو فاتورة.' : 'Service annulé: aucun paiement ou facture ne peut être généré.', 'err');
    redirect($id_service);
}

// ════════════════════════════════════════════════
//  ACTION 1 — Tech sets final price
// ════════════════════════════════════════════════
if ($action === 'set_price') {
    if (!$is_tech && !$is_admin) {
        flash($is_ar ? 'فقط التقني يمكنه تحديد السعر.' : 'Seul le technicien peut définir le prix.', 'err');
        redirect($id_service);
    }

    list($prix_ht, $prix_tva_rate, $prix_tva, $prix_ttc) = calc_ht_ttc($pdo, $id_service, $svc);

    if ($prix_ht <= 0) {
        flash($is_ar ? 'يجب أن يكون المبلغ أكبر من 0.' : 'Le montant doit être supérieur à 0.', 'err');
        redirect($id_service);
    }

    try {
        $pdo->beginTransaction();

        // Update service prix_final with HT
        $pdo->prepare("UPDATE service SET prix_final=? WHERE id_service=?")->execute([$prix_ht, $id_service]);

        // Only generate facture for services >= 5000 DA
        $requires_facture = facture_uses_tva($prix_ht);
        if ($requires_facture) {
            $fchk = $pdo->prepare("SELECT id_facture FROM facture WHERE id_service=? ORDER BY id_facture DESC LIMIT 1");
            $fchk->execute([$id_service]);
            if (!$fchk->fetchColumn()) {
                $pdo->prepare("INSERT INTO facture (id_service,date_facture,montant_total,statut_facture) VALUES (?,CURDATE(),?,'émise')")->execute([$id_service,$prix_ttc]);
            } else {
                $pdo->prepare("UPDATE facture SET montant_total=? WHERE id_service=?")->execute([$prix_ttc,$id_service]);
            }
        } else {
            $pdo->prepare("DELETE FROM facture WHERE id_service=?")->execute([$id_service]);
            $invoice_file = dirname(__DIR__) . '/factures/facture_' . $id_service . '.html';
            if (is_file($invoice_file)) { @unlink($invoice_file); }
        }

        // Create payment record
        if (!$svc['id_paiment']) {
            $pdo->prepare("INSERT INTO paiment (id_service,mode_paiment,montant,statut_paiment,date_paiment) VALUES (?,'espèces',?,'en_attente',NOW())")->execute([$id_service,$prix_ttc]);
        } else {
            $pdo->prepare("UPDATE paiment SET montant=?,statut_paiment='en_attente' WHERE id_paiment=?")->execute([$prix_ttc,$svc['id_paiment']]);
        }

        // Notify client
        notifier($pdo,$svc['client_uid'],
            $is_ar ? '💰 طلب تأكيد الدفع' : '💰 Confirmation de paiement requise',
            $is_ar
                ? "التقني {$svc['tp']} {$svc['tn']} حدد مبلغ الخدمة: ".number_format($prix_ht,0,',',' ')." DA (HT) + ".number_format($prix_tva,0,',',' ')." TVA = ".number_format($prix_ttc,0,',',' ')." DA (TTC)."
                : "Le technicien {$svc['tp']} {$svc['tn']} a défini le montant : ".number_format($prix_ht,0,',',' ')." DA (HT) + ".number_format($prix_tva,0,',',' ')." TVA = ".number_format($prix_ttc,0,',',' ')." DA (TTC).",
            "paiment.php?id_service=$id_service"
        );

        log_action($pdo,$user_id,'set_price',"Service #$id_service — HT: $prix_ht DA, TTC: $prix_ttc DA");
        $pdo->commit();

        flash(
            $is_ar
                ? "✓ تم تأكيد المبلغ: ".number_format($prix_ttc,0,',',' ')." DA (TTC). تم إرسال إشعار للعميل."
                : "✓ Montant confirmé : ".number_format($prix_ttc,0,',',' ')." DA (TTC). Le client a été notifié.",
            'ok'
        );

    } catch (Exception $e) {
        $pdo->rollBack();
        flash($is_ar ? 'خطأ في الخادم.' : 'Erreur serveur.', 'err');
    }

    redirect($id_service);
}

// ════════════════════════════════════════════════
//  ACTION 2 — Client confirms payment
// ════════════════════════════════════════════════
if ($action === 'client_confirm') {
    if (!$is_client && !$is_admin) {
        flash($is_ar ? 'فقط العميل يمكنه التأكيد.' : 'Seul le client peut confirmer.', 'err');
        redirect($id_service);
    }

    $id_paiment   = intval($_POST['id_paiment'] ?? 0);
    $mode_paiment = in_array($_POST['mode_paiment'] ?? '', ['espèces','edahabia','cib','virement'])
                    ? $_POST['mode_paiment']
                    : 'espèces';

    if (!$id_paiment) {
        flash($is_ar ? 'لا يوجد سجل دفع.' : 'Aucun enregistrement de paiement trouvé.', 'err');
        redirect($id_service);
    }

    try {
        $pdo->beginTransaction();

        // Update payment: client confirmed, waiting for tech
        $pdo->prepare("
            UPDATE paiment
            SET statut_paiment = 'en_attente_tech', mode_paiment = ?
            WHERE id_paiment = ?
        ")->execute([$mode_paiment, $id_paiment]);

        // Create TVA immediately on client confirmation so monthly dashboards can show it right away
        $pdo->exec("CREATE TABLE IF NOT EXISTS tva (\n            id_tva BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n            id_service BIGINT UNSIGNED DEFAULT NULL,\n            id_paiment BIGINT UNSIGNED DEFAULT NULL,\n            id_technicien BIGINT UNSIGNED DEFAULT NULL,\n            montant_service DOUBLE DEFAULT 0,\n            taux_tva DOUBLE DEFAULT 0,\n            montant_tva DOUBLE DEFAULT 0,\n            statut VARCHAR(32) DEFAULT 'due',\n            date_tva DATETIME DEFAULT NULL,\n            date_paiement DATETIME DEFAULT NULL,\n            UNIQUE KEY unique_service_tva (id_service)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        list($montant_ht, $tva_rate, $tva_amount, $montant_ttc) = calc_ht_ttc($pdo, $id_service, $svc);
        if ($tva_amount > 0) {
            $pdo->prepare("INSERT INTO tva (id_service, id_paiment, id_technicien, montant_service, taux_tva, montant_tva, statut, date_tva)\n                VALUES (?, ?, ?, ?, ?, ?, 'due', NOW())\n                ON DUPLICATE KEY UPDATE montant_service=VALUES(montant_service), montant_tva=VALUES(montant_tva), statut='due'\n            ")->execute([
                $id_service,
                $id_paiment,
                $svc['id_technicien'],
                $montant_ht,
                $tva_rate * 100,
                $tva_amount
            ]);
        }

        // Notify tech
        notifier(
            $pdo,
            $svc['tech_uid'],
            $is_ar ? '✅ العميل أكّد الدفع' : '✅ Le client a confirmé le paiement',
            $is_ar
                ? "العميل {$svc['cp']} {$svc['cn']} أكد الدفع نقداً. يرجى تأكيد الاستلام."
                : "Le client {$svc['cp']} {$svc['cn']} a confirmé le paiement en espèces. Veuillez confirmer la réception.",
            "paiment.php?id_service=$id_service"
        );

        log_action($pdo, $user_id, 'client_confirm_payment', "Service #$id_service — Mode: $mode_paiment");

        $pdo->commit();

        flash(
            $is_ar
                ? '✓ تم تأكيد دفعتك. في انتظار تأكيد التقني.'
                : '✓ Paiement confirmé. En attente de confirmation du technicien.',
            'ok'
        );

    } catch (Exception $e) {
        $pdo->rollBack();
        flash($is_ar ? 'خطأ في الخادم.' : 'Erreur serveur.', 'err');
    }

    redirect($id_service);
}

// ════════════════════════════════════════════════
//  ACTION 3 — Tech confirms cash received → generate invoice
// ════════════════════════════════════════════════
if ($action === 'tech_confirm') {
    if (!$is_tech && !$is_admin) {
        flash($is_ar ? 'فقط التقني يمكنه تأكيد الاستلام.' : 'Seul le technicien peut confirmer la réception.', 'err');
        redirect($id_service);
    }

    $id_paiment = intval($_POST['id_paiment'] ?? 0);
    if (!$id_paiment) {
        flash($is_ar ? 'لا يوجد سجل دفع.' : 'Aucun enregistrement trouvé.', 'err');
        redirect($id_service);
    }

    try {
        $pdo->beginTransaction();

        // Fetch payment amount
        $p = $pdo->prepare("SELECT montant FROM paiment WHERE id_paiment=?");
        $p->execute([$id_paiment]);
        $paiment_data = $p->fetch(PDO::FETCH_ASSOC);
        $montant_ttc_db = floatval($paiment_data['montant'] ?? 0);
        list($montant_ht, $tva_rate, $tva_amount, $montant_ttc) = calc_ht_ttc($pdo, $id_service, $svc);

        // Mark payment as paid
        $pdo->prepare("
            UPDATE paiment
            SET statut_paiment = 'payé', date_paiment = NOW()
            WHERE id_paiment = ?
        ")->execute([$id_paiment]);

        // Mark service as terminé
            $pdo->prepare("
                UPDATE service
                SET statut_service = 'terminé', prix_final = ?
                WHERE id_service = ?
            ")->execute([$montant_ht, $id_service]);

        // Generate invoice only when the amount reaches the VAT threshold
        // Fetch all data needed for generate_invoice_html($service, $client, $tech, $facture)
        $svc_full = $pdo->prepare("SELECT * FROM service WHERE id_service=?");
        $svc_full->execute([$id_service]);
        $svc_arr = $svc_full->fetch(PDO::FETCH_ASSOC);

        $cli_arr = $pdo->prepare("SELECT u.* FROM utilisateur u JOIN client c ON c.id_user=u.id_user WHERE c.id_client=?");
        $cli_arr->execute([$svc['id_client']]);
        $cli_arr = $cli_arr->fetch(PDO::FETCH_ASSOC);

        $tech_arr = $pdo->prepare("SELECT u.*,t.specialité FROM utilisateur u JOIN technicien t ON t.id_user=u.id_user WHERE t.id_technicien=?");
        $tech_arr->execute([$svc['id_technicien']]);
        $tech_arr = $tech_arr->fetch(PDO::FETCH_ASSOC);

        $invoice_dir = dirname(__DIR__) . '/factures/';

        $id_facture = null;
        if (facture_uses_tva($montant_ht)) {
            $facture_arr = [
                'id_facture'    => 0, // will update after insert
                'date_facture'  => date('Y-m-d H:i:s'),
                'montant_total' => $montant_ttc,
            ];

            $invoice_html = generate_invoice_html($svc_arr, $cli_arr, $tech_arr, $facture_arr);
            $invoice_path = 'factures/facture_' . $id_service . '.html';

            // Save invoice file
            if (!is_dir($invoice_dir)) mkdir($invoice_dir, 0755, true);
            file_put_contents($invoice_dir . 'facture_' . $id_service . '.html', $invoice_html);

            // Check if invoice record exists
            $fcheck = $pdo->prepare("SELECT id_facture FROM facture WHERE id_service=? ORDER BY id_facture DESC LIMIT 1");
            $fcheck->execute([$id_service]);
            $existing_facture = $fcheck->fetchColumn();

            if (!$existing_facture) {
                $pdo->prepare("
                    INSERT INTO facture (id_service, date_facture, montant_total, fichier_facture)
                    VALUES (?, NOW(), ?, ?)
                ")->execute([$id_service, $montant_ttc, $invoice_path]);
                $id_facture = $pdo->lastInsertId();
            } else {
                $pdo->prepare("
                    UPDATE facture SET montant_total=?, fichier_facture=?, date_facture=NOW()
                    WHERE id_service=?
                ")->execute([$montant_ttc, $invoice_path, $id_service]);
                $id_facture = $existing_facture;
            }

            // Notify client — payment complete + invoice ready
            notifier(
                $pdo,
                $svc['client_uid'],
                $is_ar ? '🧾 فاتورتك جاهزة!' : '🧾 Votre facture est prête !',
                $is_ar
                    ? "تم تأكيد دفع خدمة {$svc['tp']} {$svc['tn']}. فاتورتك جاهزة للتحميل."
                    : "Le paiement de l'intervention de {$svc['tp']} {$svc['tn']} est confirmé. Votre facture est disponible.",
                "facture.php?id=$id_facture"
            );
        } else {
            $pdo->prepare("DELETE FROM facture WHERE id_service=?")->execute([$id_service]);
            $invoice_file = $invoice_dir . 'facture_' . $id_service . '.html';
            if (is_file($invoice_file)) { @unlink($invoice_file); }
            notifier(
                $pdo,
                $svc['client_uid'],
                $is_ar ? '✅ تم تأكيد الدفع' : '✅ Paiement confirmé',
                $is_ar
                    ? "تم تأكيد دفع خدمة {$svc['tp']} {$svc['tn']}. المبلغ أقل من 5000 دج، لذلك لا توجد فاتورة."
                    : "Le paiement de l'intervention de {$svc['tp']} {$svc['tn']} est confirmé. Le montant est inférieur à 5000 DA, donc aucune facture n'est générée."
            );
        }

        // ── Commission 8 % (canonical base: TTC)
        // Use Total TTC as the canonical base for commission calculation so UI, migration
        // and runtime logic are consistent. Store montant_service as HT (existing
        // column semantic) but compute commission from TTC.
        $taux_commission = 8.00;
            $montant_commission = round($montant_ttc * $taux_commission / 100, 2 );
        $pdo->prepare("
            INSERT INTO commission
                (id_service, id_paiment, id_technicien, montant_service, taux_commission, montant_commission, statut, date_commission)
            VALUES (?, ?, ?, ?, ?, ?, 'due', NOW())
            ON DUPLICATE KEY UPDATE
                montant_service=VALUES(montant_service),
                montant_commission=VALUES(montant_commission),
                statut='due'
        ")->execute([
            $id_service,
            $id_paiment,
            $svc['id_technicien'],
            $montant_ht,
            $taux_commission,
            $montant_commission
        ]);
        // ── TVA record (19%) — technician must remit TVA to admin if applicable
        // Create tva table if it doesn't exist (safe to run repeatedly)
        $pdo->exec("CREATE TABLE IF NOT EXISTS tva (
            id_tva BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_service BIGINT UNSIGNED DEFAULT NULL,
            id_paiment BIGINT UNSIGNED DEFAULT NULL,
            id_technicien BIGINT UNSIGNED DEFAULT NULL,
            montant_service DOUBLE DEFAULT 0,
            taux_tva DOUBLE DEFAULT 0,
            montant_tva DOUBLE DEFAULT 0,
            statut VARCHAR(32) DEFAULT 'due',
            date_tva DATETIME DEFAULT NULL,
            date_paiement DATETIME DEFAULT NULL,
            UNIQUE KEY unique_service_tva (id_service)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $tva_rate = 0.19;
        $montant_tva = round($montant_ht * $tva_rate, 2);
        if ($montant_tva > 0) {
            $pdo->prepare("INSERT INTO tva (id_service, id_paiment, id_technicien, montant_service, taux_tva, montant_tva, statut, date_tva)
                VALUES (?, ?, ?, ?, ?, ?, 'due', NOW())
                ON DUPLICATE KEY UPDATE montant_service=VALUES(montant_service), montant_tva=VALUES(montant_tva), statut='due'
            ")->execute([
                $id_service,
                $id_paiment,
                $svc['id_technicien'],
                $montant_ht,
                $tva_rate * 100,
                $montant_tva
            ]);
        }
        // ────────────────────────────────────────────────────────

        log_action($pdo, $user_id, 'tech_confirm_payment', "Service #$id_service — ".($id_facture ? "Facture #$id_facture — " : "Sans facture — ")."Montant HT: $montant_ht DA / TTC: $montant_ttc DA — Commission: $montant_commission DA");

        $pdo->commit();

        flash(
            $is_ar
                ? ($id_facture ? '✓ تم تأكيد الدفع بنجاح! تم إنشاء الفاتورة تلقائياً.' : '✓ تم تأكيد الدفع بنجاح! المبلغ أقل من 5000 دج، لذلك لا توجد فاتورة.')
                : ($id_facture ? '✓ Paiement confirmé ! La facture a été générée automatiquement.' : '✓ Paiement confirmé ! Montant inférieur à 5000 DA, aucune facture n’est générée.'),
            'ok'
        );

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('handle_paiment tech_confirm error: ' . $e->getMessage());
        flash($is_ar ? 'خطأ في الخادم: ' . $e->getMessage() : 'Erreur serveur: ' . $e->getMessage(), 'err');
    }

    redirect($id_service);
}

// ── Unknown action ────────────────────────────
flash($is_ar ? 'إجراء غير معروف.' : 'Action inconnue.', 'err');
redirect($id_service);