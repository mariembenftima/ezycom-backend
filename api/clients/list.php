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

$page   = max(1, (int) ($_GET['page']  ?? 1));
$limit  = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where  = "WHERE c.id_commercant = ?";
$params = [$id_commercant];

if ($search !== '') {
    $where   .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.tel LIKE ? OR c.email LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}

$countStmt = $db->prepare("
    SELECT COUNT(DISTINCT COALESCE(NULLIF(c.tel,''), CONCAT(c.nom,' ',c.prenom)))
    FROM commande c
    $where
");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listParams = array_merge($params, [$limit, $offset]);
$stmt = $db->prepare("
    SELECT
        COALESCE(NULLIF(c.tel,''), CONCAT(c.nom,' ',c.prenom)) AS client_key,
        c.nom,
        c.prenom,
        c.tel,
        c.email,
        c.ville,
        c.gouvernerat,
        COUNT(c.id)                                              AS nb_commandes,
        SUM(CASE WHEN c.etat = 5 THEN 1 ELSE 0 END)             AS nb_livrees,
        SUM(CASE WHEN c.etat = 7 THEN 1 ELSE 0 END)             AS nb_annulees,
        SUM(CASE WHEN c.etat = 5 THEN c.prix ELSE 0 END)        AS total_achats,
        MAX(c.date_add)                                          AS derniere_commande
    FROM commande c
    $where
    GROUP BY client_key
    ORDER BY derniere_commande DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($listParams);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsStmt = $db->prepare("
    SELECT
        COUNT(DISTINCT COALESCE(NULLIF(c.tel,''), CONCAT(c.nom,' ',c.prenom))) AS total_clients,
        SUM(CASE WHEN c.etat = 5 THEN c.prix ELSE 0 END)                       AS chiffre_affaires,
        COUNT(c.id)                                                              AS total_commandes
    FROM commande c
    WHERE c.id_commercant = ?
");
$statsStmt->execute([$id_commercant]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

respond(200, 'OK', [
    'clients' => $clients,
    'stats'   => $stats,
    'total'   => $total,
    'page'    => $page,
    'limit'   => $limit,
    'pages'   => (int) ceil($total / $limit),
]);