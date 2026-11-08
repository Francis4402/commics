<?php

use App\Http\Controllers\ComicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ComicController::class, 'index'])->name('comic.index');
Route::get('/comic/{slug?}', [ComicController::class, 'show'])->name('comic.show');
Route::get('/read/{comicSlug?}/{chapterSlug?}', [ComicController::class, 'read'])->name('comic.read');
Route::get('/proxy/image', [ComicController::class, 'proxyImage'])->name('comic.proxy');
