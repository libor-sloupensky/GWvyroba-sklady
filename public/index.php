<?php
declare(strict_types=1);
// UTF-8, basic front controller
// Updated with margins analytics endpoint

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
use App\Controller\AdminController;

$sessionLifetime = 60 * 60 * 24 * 7; // 7 dni
ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
ini_set('session.cookie_lifetime', (string)$sessionLifetime);
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// Logování návštěv - max jednou za hodinu
if (isset($_SESSION['user']['email'])) {
    $lastLog = $_SESSION['_last_access_log'] ?? 0;
    if (time() - $lastLog >= 3600) {
        $_SESSION['_last_access_log'] = time();
        $logDir = __DIR__ . '/../data';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(
            $logDir . '/access_log.csv',
            date('Y-m-d H:i:s') . ',' . $_SESSION['user']['email'] . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}

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
$router->post('/import/delete-invoice', [ImportController::class, 'deleteInvoice']);
$router->get('/import/invoice-detail', [ImportController::class, 'getInvoiceDetail']);
$router->get('/report/missing-sku', [ImportController::class, 'reportMissingSku']);
$router->get('/import/auto-run', [ImportController::class, 'autoRun']);

// Produkty
$router->get('/products', [ProductsController::class, 'index']);
$router->get('/products/export', [ProductsController::class, 'exportCsv']);
$router->post('/products/import', [ProductsController::class, 'importCsv']);
$router->post('/products/create', [ProductsController::class, 'create']);
$router->post('/products/update', [ProductsController::class, 'inlineUpdate']);
$router->get('/products/search', [ProductsController::class, 'search']);
$router->get('/products/bom-tree', [ProductsController::class, 'bomTree']);
$router->post('/products/bom/add', [ProductsController::class, 'bomAdd']);
$router->post('/products/bom/delete', [ProductsController::class, 'bomDelete']);

// BOM export/import pro stránku Produkty
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
$router->get('/production/demand-tree', [ProductionController::class, 'demandTree']);
$router->get('/production/movements', [ProductionController::class, 'movements']);
$router->get('/production/filtered-movements', [ProductionController::class, 'filteredMovements']);
$router->post('/production/recent-limit', [ProductionController::class, 'updateRecentLimit']);

// Analytics
$router->get('/analytics/revenue', [AnalyticsController::class, 'revenue']);
$router->post('/analytics/run', [AnalyticsController::class, 'runTemplateV2']);
$router->get('/analytics/contacts', [AnalyticsController::class, 'searchContactsV2']);
$router->get('/analytics/contacts/by-id', [AnalyticsController::class, 'searchContactsByIdsV2']);
$router->get('/analytics/invoice-items', [AnalyticsController::class, 'invoiceItemsV2']);
$router->get('/analytics/favorite/list', [AnalyticsController::class, 'favoriteListV2']);
$router->post('/analytics/favorite', [AnalyticsController::class, 'saveFavoriteV2']);
$router->post('/analytics/favorite/delete', [AnalyticsController::class, 'deleteFavoriteV2']);

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
$router->post('/settings/type', [SettingsController::class, 'saveProductType']);
$router->post('/settings/type/delete', [SettingsController::class, 'deleteProductType']);
$router->post('/settings/global', [SettingsController::class, 'saveGlobal']);
$router->post('/settings/users/save', [SettingsController::class, 'saveUser']);

// Admin utilities (secured)
$router->get('/admin/migrate', [AdminController::class, 'migrateForm']);
$router->post('/admin/migrate', [AdminController::class, 'migrateRun']);
$router->get('/admin/seed', [AdminController::class, 'seedForm']);
$router->post('/admin/seed', [AdminController::class, 'seedRun']);
$router->get('/admin/rebuild-movements', [AdminController::class, 'rebuildMovements']);
$router->get('/admin/history', [AdminController::class, 'history']);

$router->dispatch();
