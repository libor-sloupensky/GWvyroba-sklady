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
$router->post('/products/create', [ProductsController::class, 'create']);
$router->post('/products/update', [ProductsController::class, 'inlineUpdate']);
$router->get('/products/bom-tree', [ProductsController::class, 'bomTree']);
$router->get('/products/search', [ProductsController::class, 'search']);
$router->post('/products/bom/add', [ProductsController::class, 'bomAdd']);
$router->post('/products/bom/delete', [ProductsController::class, 'bomDelete']);

// BOM
$router->get('/bom', [BomController::class, 'index']);
$router->get('/bom/export', [BomController::class, 'exportCsv']);
$router->post('/bom/import', [BomController::class, 'importCsv']);

// Inventory
$router->get('/inventory', [InventoryController::class, 'index']);
$router->post('/inventory/start', [InventoryController::class, 'start']);
$router->post('/inventory/close', [InventoryController::class, 'close']);
$router->post('/inventory/entry', [InventoryController::class, 'addEntry']);
$router->post('/inventory/delete', [InventoryController::class, 'delete']);
$router->post('/inventory/reopen', [InventoryController::class, 'reopen']);

// Reservations
$router->get('/reservations', [ReservationsController::class, 'index']);
$router->post('/reservations', [ReservationsController::class, 'save']);
$router->post('/reservations/delete', [ReservationsController::class, 'delete']);
$router->get('/reservations/search-products', [ReservationsController::class, 'searchProducts']);

// Production
$router->get('/production/plans', [ProductionController::class, 'plans']);
$router->post('/production/produce', [ProductionController::class, 'produce']);
$router->post('/production/delete', [ProductionController::class, 'deleteRecord']);
$router->post('/production/check', [ProductionController::class, 'check']);

// Analytics
$router->get('/analytics/revenue', [AnalyticsController::class, 'revenue']);
$router->post('/analytics/ai', [AnalyticsController::class, 'ai']);

// Settings
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/series', [SettingsController::class, 'saveSeries']);
$router->post('/settings/series/delete', [SettingsController::class, 'deleteSeries']);
$router->post('/settings/ignore', [SettingsController::class, 'saveIgnore']);
$router->post('/settings/ignore/delete', [SettingsController::class, 'deleteIgnore']);
$router->post('/settings/brand', [SettingsController::class, 'saveBrand']);
$router->post('/settings/brand/delete', [SettingsController::class, 'deleteBrand']);
$router->post('/settings/group', [SettingsController::class, 'saveGroup']);
$router->post('/settings/group/delete', [SettingsController::class, 'deleteGroup']);
$router->post('/settings/unit', [SettingsController::class, 'saveUnit']);
$router->post('/settings/unit/delete', [SettingsController::class, 'deleteUnit']);
$router->post('/settings/global', [SettingsController::class, 'saveGlobal']);
$router->post('/settings/users/save', [SettingsController::class, 'saveUser']);

// Plans page
$router->get('/plany', [PlansController::class, 'index']);

// Admin utilities (secured)
$router->get('/admin/migrate', [AdminController::class, 'migrateForm']);
$router->post('/admin/migrate', [AdminController::class, 'migrateRun']);
$router->get('/admin/seed', [AdminController::class, 'seedForm']);
$router->post('/admin/seed', [AdminController::class, 'seedRun']);

$router->dispatch();
