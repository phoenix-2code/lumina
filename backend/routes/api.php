<?php

use App\Http\Controllers\BibleController;
use Illuminate\Support\Facades\Route;

Route::get('/bible/chapter', [BibleController::class, 'getChapter']);
Route::get('/bible/versions', [BibleController::class, 'getVersions']);