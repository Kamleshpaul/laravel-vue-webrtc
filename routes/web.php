<?php

use App\Http\Controllers\CallController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::group(['middleware' => ['auth:sanctum', 'verified']], function () {

    Route::get('/dashboard', function () {
        $users = User::all();
        return Inertia\Inertia::render('Dashboard', compact('users'));
    })->name('dashboard');

    Route::post('start-call', [CallController::class, 'startCall'])->name('start.call');
    Route::post('answer-call', [CallController::class, 'AnswerCall'])->name('answer.call');
    Route::post('handshake', [CallController::class, 'handshake'])->name('handshake');
});
