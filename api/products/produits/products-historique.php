<?php
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
 
$commercant = requireAuth();
$db = getDB();
$id_produit = intval($_REQUEST['_segments'][0] ?? 0);
if (!$id_produit) respond(400, 'ID produit manquant.');

$stmt = $db->prepare("SELECT id FROM produit WHERE id = ? AND id_commercant = ? LIMIT 1");
$stmt->execute([$id_produit, $commercant['id']]);
if (!$stmt->fetch()) respond(404, 'Produit introuvable.');

$page   = max(1, intval($_GET['page']   ?? 1));
$limit  = max(1, min(100, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where  = "WHERE id_produit = ?";
$params = [$id_produit];

if ($search !== '') {
    $where   .= " AND (motif LIKE ? OR raison LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM mouvement_stock $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listParams = array_merge($params, [$limit, $offset]);
$stmt = $db->prepare("
    SELECT * FROM mouvement_stock
    $where
    ORDER BY date DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($listParams);
$mouvements = $stmt->fetchAll();

foreach ($mouvements as &$m) {
    $m['motif_label'] = $m['motif'] == '1' ? 'Entrée stock' : 'Sortie de stock';
}

respond(200, 'OK', [
    'mouvements' => $mouvements,
    'total'      => $total,
    'page'       => $page,
    'limit'      => $limit,
    'pages'      => (int) ceil($total / $limit),
]);