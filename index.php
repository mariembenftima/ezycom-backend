<?php
require_once 'config/cors.php';
require_once 'config/database.php';
require_once 'helpers/response.php';

$method = $_SERVER['REQUEST_METHOD'];

$uri = strtok($_SERVER['REQUEST_URI'], '?');
preg_match('#(/api/.*)$#', $uri, $m);
$path = rtrim($m[1] ?? '', '/');

$routes = [
    'POST   /api/auth/register'          => 'api/auth/register.php',
    'POST   /api/auth/login'             => 'api/auth/login.php',
    'POST   /api/auth/logout'            => 'api/auth/logout.php',
    'POST   /api/auth/forgot-password'   => 'api/auth/forgot-password.php',
    'POST   /api/auth/verify-reset-code' => 'api/auth/verify-reset-code.php',
    'POST   /api/auth/reset-password'    => 'api/auth/reset-password.php',

    'GET    /api/categories'             => 'api/categories/categories-list.php',
    'POST   /api/categories'             => 'api/categories/categories-create.php',
    'PUT    /api/categories/{id}'        => 'api/categories/categories-update.php',
    'DELETE /api/categories/{id}'        => 'api/categories/categories-delete.php',

    'GET    /api/products'               => 'api/products/produits/products-list.php',
    'POST   /api/products'               => 'api/products/produits/products-create.php',

    'GET    /api/products/attributs'     => 'api/products/variantes/list-attributs.php',

    'GET    /api/products/{id}/historique'       => 'api/products/produits/products-historique.php',
    'POST   /api/products/{id}/image'            => 'api/products/images/upload-image.php',
    'DELETE /api/products/{id}/image'            => 'api/products/images/delete-image.php',
    'GET    /api/products/{id}/variations'       => 'api/products/variantes/list-variations.php',
    'POST   /api/products/{id}/variation'        => 'api/products/variantes/create-variation.php',
    'DELETE /api/products/{id}/variation/{vid}'  => 'api/products/variantes/delete-variation.php',

    'GET    /api/products/{id}'          => 'api/products/produits/products-get.php',
    'PUT    /api/products/{id}'          => 'api/products/produits/products-update.php',
    'POST   /api/products/{id}'          => 'api/products/produits/products-update.php',
    'DELETE /api/products/{id}'          => 'api/products/produits/products-delete.php',

    'POST   /api/stock/sortie'           => 'api/stock/stock-out.php',
    'POST   /api/stock/entree'           => 'api/stock/stock-in.php',

    'GET    /api/orders'                 => 'api/orders/orders-list.php',
    'POST   /api/orders'                 => 'api/orders/orders-create.php',
    'PUT    /api/orders/{id}/status'     => 'api/orders/update-status.php',
    'GET    /api/orders/{id}'            => 'api/orders/orders-get.php',

    'GET    /api/clients'                => 'api/clients/list.php',

    'GET    /api/statistiques'           => 'api/statistiques/index.php',
    'GET    /api/parametres'             => 'api/parametres/get.php',
    'PUT    /api/parametres'             => 'api/parametres/update.php',

    'GET    /api/villes'                 => 'api/villes/list.php',
    'GET    /api/packs'                  => 'api/packs/list.php',
];

$matched = false;
foreach ($routes as $route => $file) {
    [$routeMethod, $routePath] = explode(' ', trim($route), 2);
    $routeMethod = trim($routeMethod);
    $routePath   = trim($routePath);
    $pattern = '#^' . preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath) . '$#';
    if ($method === $routeMethod && preg_match($pattern, $path, $matches)) {
        $_REQUEST['_segments'] = array_slice($matches, 1);
        file_exists($file) ? require_once $file : respond(501, 'Not yet implemented');
        $matched = true;
        break;
    }
}
if (!$matched) respond(404, 'Route not found');