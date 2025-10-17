<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\UnsubscribeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/produk_dan_layanan', function () {
    return view('produk_dan_layanan');
});


Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [LoginController::class, 'login'])->name('login.process');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');


// Contoh halaman yang dilindungi login
Route::middleware('checklogin')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    });

    Route::get('/broadcast', [BroadcastController::class, 'index'])->name('broadcast.form');
    Route::post('/broadcast/import', [BroadcastController::class, 'importExcel'])->name('broadcast.import');
    Route::post('/broadcast/send', [BroadcastController::class, 'send'])->name('broadcast.send');
    Route::get('/broadcast/send-stream', [BroadcastController::class, 'sendStream'])->name('broadcast.send.stream'); // BARU
    Route::get('/broadcast/logs', [BroadcastController::class, 'logs'])->name('broadcast.broadcast_logs');

    Route::get('/unsubscribe/logs', [UnsubscribeController::class, 'unsubscribe_logs'])->name('unsubscribe.unsubscribe_logs');
    // Route baru untuk template
    Route::post('/broadcast/set-template', [BroadcastController::class, 'setTemplate'])->name('broadcast.set.template');
    Route::get('/broadcast/preview/{id}', [BroadcastController::class, 'preview'])->name('broadcast.preview');
});

Route::get('/unsubscribe/{id}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
Route::post('/unsubscribe/{id}', [UnsubscribeController::class, 'confirm'])->name('unsubscribe.confirm');
