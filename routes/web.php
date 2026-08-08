<?php

use App\Http\Controllers\PostIdeaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PostIdeaController::class, 'create'])->name('post-idea.create');
Route::post('/post-idea', [PostIdeaController::class, 'store'])->name('post-idea.store');
Route::post('/post-idea/buffer', [PostIdeaController::class, 'postToBuffer'])->name('post-idea.buffer');
