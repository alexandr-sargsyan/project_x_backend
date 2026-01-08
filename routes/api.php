<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\VideoReferenceController;
use Illuminate\Support\Facades\Route;

Route::apiResource('video-references', VideoReferenceController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('tags', TagController::class);

