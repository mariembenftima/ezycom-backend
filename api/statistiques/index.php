<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__, 2) . '/helpers/response.php';
require_once dirname(__DIR__, 2) . '/middleware/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];
$db            = getDB();

$periode = max(7, min(90, (int) ($_GET['periode'] ?? 30)));

$caStmt = $db->prepare("
    SELECT
        DATE(date_add) AS jour,
        COUNT(id)      AS nb_commandes,
        SUM(prix)      AS ca
    FROM commande
    WHERE id_commercant = ?
      AND etat = 5
      AND date_add >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY jour
    ORDER BY jour ASC
");
$caStmt->execute([$id_commercant, $periode]);
$ca_jours = $caStmt->fetchAll(PDO::FETCH_ASSOC);

$statutStmt = $db->prepare("
    SELECT etat, COUNT(*) AS nb
    FROM commande
    WHERE id_commercant = ?
      AND date_add >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY etat
");
$statutStmt->execute([$id_commercant, $periode]);
$par_statut = $statutStmt->fetchAll(PDO::FETCH_ASSOC);

$topStmt = $db->prepare("
    SELECT
        p.nom                                     AS produit,
        SUM(d.quantite)                           AS total_vendus,
        SUM(d.quantite * d.prix_unitaire)         AS ca_produit
    FROM detail_cmd d
    JOIN commande c ON c.id = d.id_cmd
    JOIN produit p  ON p.id = d.id_produit
    WHERE c.id_commercant = ?
      AND c.etat = 5
      AND c.date_add >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY d.id_produit
    ORDER BY total_vendus DESC
    LIMIT 5
");
$topStmt->execute([$id_commercant, $periode]);
$top_produits = $topStmt->fetchAll(PDO::FETCH_ASSOC);

$villesStmt = $db->prepare("
    SELECT
        ville,
        COUNT(*) AS nb_commandes
    FROM commande
    WHERE id_commercant = ?
      AND ville IS NOT NULL AND ville != ''
      AND date_add >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY ville
    ORDER BY nb_commandes DESC
    LIMIT 5
");
$villesStmt->execute([$id_commercant, $periode]);
$top_villes = $villesStmt->fetchAll(PDO::FETCH_ASSOC);

$kpiStmt = $db->prepare("
    SELECT
        COUNT(*)                                                                 AS total_commandes,
        SUM(CASE WHEN etat = 5 THEN prix ELSE 0 END)                            AS ca_total,
        SUM(CASE WHEN etat = 5 THEN 1 ELSE 0 END)                               AS livrees,
        SUM(CASE WHEN etat = 7 THEN 1 ELSE 0 END)                               AS annulees,
        COUNT(DISTINCT COALESCE(NULLIF(tel,''), CONCAT(nom,' ',prenom)))         AS nb_clients
    FROM commande
    WHERE id_commercant = ?
      AND date_add >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
");
$kpiStmt->execute([$id_commercant, $periode]);
$kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC);

$kpis['taux_livraison'] = $kpis['total_commandes'] > 0
    ? round(($kpis['livrees'] / $kpis['total_commandes']) * 100, 1)
    : 0;

respond(200, 'OK', [
    'kpis'         => $kpis,
    'ca_jours'     => $ca_jours,
    'par_statut'   => $par_statut,
    'top_produits' => $top_produits,
    'top_villes'   => $top_villes,
    'periode'      => $periode,
]);