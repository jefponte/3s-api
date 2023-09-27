<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use app3s\controller\MainIndex;
use app3s\util\Sessao;
use App\Http\Controllers\OrdersController;

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

Route::get('/kamban', function () {
    $sessao = new Sessao();
    $firstName = $sessao->getNome();
    $arr = explode(" ", $sessao->getNome());
    if (isset($arr[0])) {
      $firstName = $arr[0];
    }
    $firstName = ucfirst(strtolower($firstName));
    return view('welcome', ['userFirstName' => $firstName, 'divisionSig' =>  $sessao->getUnidade()]);
})->name('kamban');



Route::middleware('auth')->group(function () {
    Route::resources(
        [
            'orders' => OrdersController::class
        ]
    );
});

require_once __DIR__.'/auth.php';
