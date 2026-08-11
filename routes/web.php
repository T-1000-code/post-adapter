<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BufferConnectionController;
use App\Http\Controllers\PostIdeaController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [PostIdeaController::class, 'create'])->name('post-idea.create');
    Route::post('/post-idea', [PostIdeaController::class, 'store'])->name('post-idea.store');
    Route::post('/post-idea/buffer', [PostIdeaController::class, 'postToBuffer'])->name('post-idea.buffer');

    Route::get('/connect-x', [BufferConnectionController::class, 'show'])->name('buffer.show');
    Route::post('/connect-x/token', [BufferConnectionController::class, 'saveToken'])->name('buffer.save-token');
    Route::post('/connect-x/refresh', [BufferConnectionController::class, 'refresh'])->name('buffer.refresh');
    Route::post('/connect-x/disconnect', [BufferConnectionController::class, 'disconnect'])->name('buffer.disconnect');
});
