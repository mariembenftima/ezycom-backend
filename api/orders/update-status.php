<?php
// api/orders/update-status.php
// PATCH /api/orders/{id}/status
// Body JSON : { "etat": 1, "motif": "raison optionnelle" }
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__, 2) . '/helpers/response.php';
require_once dirname(__DIR__, 2) . '/middleware/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];
$db            = getDB();

$id = (int) ($_REQUEST['_segments'][0] ?? 0);
if (!$id) respond(400, 'ID commande manquant.');

// Vérifier appartenance
$stmt = $db->prepare("SELECT id, etat FROM commande WHERE id = ? AND id_commercant = ? LIMIT 1");
$stmt->execute([$id, $id_commercant]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$commande) respond(404, 'Commande introuvable.');

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$etat  = isset($body['etat']) ? (int) $body['etat'] : null;
$motif = trim($body['motif'] ?? '');

if ($etat === null) respond(400, 'Le champ etat est obligatoire.');

// Etats valides : 0=En attente, 1=Confirmée, 2=Dispatchée, 5=Livrée, 7=Annulée
$etats_valides = [0, 1, 2, 5, 7];
if (!in_array($etat, $etats_valides)) respond(400, 'Statut invalide.');

// Labels pour historique
$labels = [
    0 => 'En attente de validation',
    1 => 'Commande confirmée par le marchand',
    2 => 'Commande dispatchée',
    5 => 'Commande livrée',
    7 => 'Commande annulée',
];

// Colonnes date à mettre à jour selon statut
$date_cols = [
    1 => 'date_validation',
    2 => 'date_dispatch',
    5 => 'date_liv',
    7 => 'date_annulation',
];

$db->beginTransaction();
try {
    // Mettre à jour statut
    $extra_sql = '';
    if (isset($date_cols[$etat])) {
        $col = $date_cols[$etat];
        $extra_sql = ", {$col} = NOW()";
    }
    $db->prepare("UPDATE commande SET etat = ? {$extra_sql} WHERE id = ? AND id_commercant = ?")
       ->execute([$etat, $id, $id_commercant]);

    // Ajouter dans l'historique
    $label_final = $motif ?: ($labels[$etat] ?? 'Statut mis à jour');
    $db->prepare("INSERT INTO historique_commande (id_cmd, date, etat, motif) VALUES (?, NOW(), ?, ?)")
       ->execute([$id, $etat, $label_final]);

    $db->commit();
    respond(200, 'Statut mis à jour avec succès.', ['etat' => $etat]);
} catch (Exception $e) {
    $db->rollBack();
    respond(500, 'Erreur: ' . $e->getMessage());
}