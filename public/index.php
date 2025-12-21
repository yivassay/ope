<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Auth;
use App\Response;

$auth = new Auth($db);

$page = $_GET['page'] ?? 'dashboard';

// Language switch (works for all pages)
if (isset($_GET['lang'])) {
    $lang = (string)$_GET['lang'];
    if (in_array($lang, ['uz', 'ru'], true)) {
        $_SESSION['locale'] = $lang;
    }
    // redirect back without lang param
    $params = $_GET;
    unset($params['lang']);
    $qs = http_build_query($params);
    Response::redirect($qs ? ('?' . $qs) : '?page=dashboard');
    exit;
}

// Public pages
if ($page === 'login') {
    (new \App\Controllers\AuthController($db, $auth))->login();
    exit;
}
if ($page === 'logout') {
    (new \App\Controllers\AuthController($db, $auth))->logout();
    exit;
}

// Protected
if (!$auth->check()) {
    Response::redirect('?page=login');
    exit;
}

// Role-based access (manager should not access dashboard/settings)
if (($auth->role() ?? '') === 'callcenter_manager' && in_array($page, ['dashboard', 'settings'], true)) {
    http_response_code(403);
    echo "403";
    exit;
}

switch ($page) {
    case 'dashboard':
        (new \App\Controllers\DashboardController($db, $auth))->index();
        break;
    case 'bugungi':
        (new \App\Controllers\BugungiController($db, $auth))->index();
        break;
    case 'smartomato':
        (new \App\Controllers\SmartomatoController($db, $auth))->index();
        break;
    case 'operator_sales':
        (new \App\Controllers\OperatorSalesController($db, $auth))->index();
        break;
    case 'telegram_bot':
        (new \App\Controllers\TelegramBotController($db, $auth))->index();
        break;
    case 'others':
        (new \App\Controllers\OthersController($db, $auth))->index();
        break;
    case 'taxi':
        (new \App\Controllers\TaxiController($db, $auth))->index();
        break;
    case 'errors':
        (new \App\Controllers\ErrorsController($db, $auth))->index();
        break;
    case 'profile':
        (new \App\Controllers\ProfileController($db, $auth))->index();
        break;
    case 'settings':
        (new \App\Controllers\SettingsController($db, $auth))->index();
        break;
    case 'operators':
        (new \App\Controllers\OperatorsController($db, $auth))->index();
        break;
    default:
        http_response_code(404);
        echo "404";
}

