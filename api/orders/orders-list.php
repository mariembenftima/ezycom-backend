<?php
// api/orders/list.php
// GET /api/orders?page=1&limit=20&etat=0&search=nom
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
$etat   = $_GET['etat']   ?? null;   // null = tous
$search = trim($_GET['search'] ?? '');

$where  = "WHERE c.id_commercant = ?";
$params = [$id_commercant];

if ($etat !== null && $etat !== '') {
    $where   .= " AND c.etat = ?";
    $params[] = (int) $etat;
}
if ($search !== '') {
    $where   .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.tel LIKE ? OR c.code_barre LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}

// Total
$countStmt = $db->prepare("SELECT COUNT(*) FROM commande c $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

// Liste
$listParams = array_merge($params, [$limit, $offset]);
$stmt = $db->prepare("
    SELECT c.id, c.code_barre, c.nom, c.prenom, c.tel, c.email,
           c.ville, c.gouvernerat, c.adresse,
           c.prix, c.frais, c.etat, c.paye,
           c.date_add, c.date_validation, c.date_liv, c.date_annulation,
           c.code_tracking, c.transporteur, c.msg,
           c.fragile, c.ouvrir, c.echange,
           COUNT(d.id) AS nb_articles
    FROM commande c
    LEFT JOIN detail_cmd d ON d.id_cmd = c.id
    $where
    GROUP BY c.id
    ORDER BY c.date_add DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($listParams);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats par statut (pour badges dans l'app)
$statsStmt = $db->prepare("
    SELECT etat, COUNT(*) as nb
    FROM commande
    WHERE id_commercant = ?
    GROUP BY etat
");
$statsStmt->execute([$id_commercant]);
$statsRows = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
$stats = ['total' => $total, 'en_attente' => 0, 'confirmee' => 0, 'dispatchee' => 0, 'livree' => 0, 'annulee' => 0];
foreach ($statsRows as $row) {
    switch ((int)$row['etat']) {
        case 0: $stats['en_attente']  += (int)$row['nb']; break;
        case 1: $stats['confirmee']   += (int)$row['nb']; break;
        case 2: $stats['dispatchee']  += (int)$row['nb']; break;
        case 5: $stats['livree']      += (int)$row['nb']; break;
        case 7: $stats['annulee']     += (int)$row['nb']; break;
    }
}

respond(200, 'OK', [
    'commandes' => $commandes,
    'stats'     => $stats,
    'total'     => $total,
    'page'      => $page,
    'limit'     => $limit,
    'pages'     => (int) ceil($total / $limit),
]);