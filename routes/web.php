<?php

use App\Http\Controllers\OrdersController;
use app3s\controller\MainIndex;
use app3s\util\Sessao;
use App\Http\Controllers\KanbanPanelController;
use App\Http\Controllers\TablePanelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    $main = new MainIndex();

    return $main->main();
})->name('root');

Route::post('/', function () {
    $main = new MainIndex();

    return $main->main();
})->name('root-post');

Route::get('/welcome', function () {
    $sessao = new Sessao();
    $firstName = $sessao->getNome();
    $arr = explode(' ', $sessao->getNome());
    if (isset($arr[0])) {
        $firstName = $arr[0];
    }
    $firstName = ucfirst(strtolower($firstName));

    return view('welcome', ['userFirstName' => $firstName, 'divisionSig' => $sessao->getUnidade()]);
})->name('welcome');

Route::middleware('auth')->group(function () {
    Route::get('kanban-panel', [OrdersController::class, 'kanban'])->name('kanban-panel');
    Route::get('table-panel', [OrdersController::class, 'panelTable'])->name('table-panel');
    Route::resources(
        [
            'orders' => OrdersController::class,
        ]
    );
});

require_once __DIR__.'/auth.php';
