<?php
declare(strict_types=1);
// UTF-8, basic front controller

require __DIR__ . '/../src/bootstrap.php';

use App\Support\Router;
use App\Controller\AuthController;
use App\Controller\HomeController;
use App\Controller\ImportController;
use App\Controller\ProductsController;
use App\Controller\BomController;
use App\Controller\InventoryController;
use App\Controller\ReservationsController;
use App\Controller\ProductionController;
use App\Controller\AnalyticsController;
use App\Controller\SettingsController;
use App\Controller\PlansController;
use App\Controller\AdminController;

session_start();

$router = new Router();

// Auth
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'loginSubmit']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/auth/google', [AuthController::class, 'googleStart']);
$router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);

// Home
$router->get('/', [HomeController::class, 'index']);

// Import
$router->get('/import', [ImportController::class, 'form']);
$router->post('/import/pohoda', [ImportController::class, 'importPohoda']);
$router->post('/import/delete-last', [ImportController::class, 'deleteLastBatch']);
$router->get('/report/missing-sku', [ImportController::class, 'reportMissingSku']);

// Produkty
$router->get('/products', [ProductsController::class, 'index']);
$router->get('/products/export', [ProductsController::class, 'exportCsv']);
$router->post('/products/import', [ProductsController::class, 'importCsv']);

// BOM
$router->get('/bom', [BomController::class, 'index']);
$router->get('/bom/export', [BomController::class, 'exportCsv']);
$router->post('/bom/import', [BomController::class, 'importCsv']);

// Inventory
$router->get('/inventory', [InventoryController::class, 'index']);
$router->post('/inventory/move', [InventoryController::class, 'addMove']);

// Reservations
$router->get('/reservations', [ReservationsController::class, 'index']);
$router->post('/reservations', [ReservationsController::class, 'save']);
$router->post('/reservations/delete', [ReservationsController::class, 'delete']);

// Production
$router->get('/production/plans', [ProductionController::class, 'plans']);
$router->post('/production/produce', [ProductionController::class, 'produce']);
$router->post('/production/delete', [ProductionController::class, 'deleteRecord']);

// Analytics
$router->get('/analytics/revenue', [AnalyticsController::class, 'revenue']);

// Settings
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/series', [SettingsController::class, 'saveSeries']);
$router->post('/settings/ignore', [SettingsController::class, 'saveIgnore']);
$router->post('/settings/global', [SettingsController::class, 'saveGlobal']);

// Plans page
$router->get('/plany', [PlansController::class, 'index']);

// Admin utilities (secured)
$router->get('/admin/migrate', [AdminController::class, 'migrateForm']);
$router->post('/admin/migrate', [AdminController::class, 'migrateRun']);

$router->dispatch();
