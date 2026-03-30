<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$commercant = requireAuth();
$db = getDB();

$page       = max(1, intval($_GET['page']      ?? 1));
$limit      = max(1, min(100, intval($_GET['limit'] ?? 10)));
$offset     = ($page - 1) * $limit;
$search     = trim($_GET['search']     ?? '');
$id_cat     = intval($_GET['id_cat']    ?? 0);
$id_sous    = intval($_GET['id_sous_cat'] ?? 0);

$where  = "WHERE p.id_commercant = ?";
$params = [$commercant['id']];

if ($search !== '') {
    $where   .= " AND (p.nom LIKE ? OR p.ref LIKE ? OR p.marque LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($id_cat) {
    $where   .= " AND p.id_cat = ?";
    $params[] = $id_cat;
}
if ($id_sous) {
    $where   .= " AND p.id_sous_cat = ?";
    $params[] = $id_sous;
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM produit p $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listParams   = array_merge($params, [$limit, $offset]);
$stmt = $db->prepare("
    SELECT p.*,
           c.nom  AS categorie_nom,
           sc.nom AS sous_categorie_nom
    FROM produit p
    LEFT JOIN categorie c  ON c.id  = p.id_cat
    LEFT JOIN categorie sc ON sc.id = p.id_sous_cat
    $where
    ORDER BY p.date_add DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($listParams);
$produits = $stmt->fetchAll();

respond(200, 'OK', [
    'produits' => $produits,
    'total'    => $total,
    'page'     => $page,
    'limit'    => $limit,
    'pages'    => (int) ceil($total / $limit),
]);