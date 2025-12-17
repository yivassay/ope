<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Auth;
use App\Response;

$auth = new Auth($db);

$page = $_GET['page'] ?? 'dashboard';

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
    Response::redirect('/?page=login');
    exit;
}

switch ($page) {
    case 'dashboard':
        (new \App\Controllers\DashboardController($db, $auth))->index();
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

