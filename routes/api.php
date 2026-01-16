<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminVideoReferenceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\SharedCollectionController;
use App\Http\Controllers\VideoCollectionController;
use App\Http\Controllers\VideoCollectionItemController;
use App\Http\Controllers\VideoReferenceController;
use Illuminate\Support\Facades\Route;

// Публичные роуты (без аутентификации)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/email-verification/send-code', [EmailVerificationController::class, 'sendCode']);
Route::post('/email-verification/verify-code', [EmailVerificationController::class, 'verifyCode']);

// Публичные роуты для просмотра контента
Route::apiResource('video-references', VideoReferenceController::class)->only(['index', 'show']);
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
Route::get('tags', [TagController::class, 'index']);
Route::get('tutorials', [TutorialController::class, 'index']);

// Публичные роуты для расшаренных коллекций
Route::get('shared/collections/{token}/videos', [SharedCollectionController::class, 'getVideos']);

// Защищенные роуты (требуют аутентификации)
Route::middleware('auth:api')->group(function () {
    // Аутентификация
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Защищенные роуты для контента (требуют подтверждения email)
    Route::middleware('email.verified')->group(function () {
        // Лайки
        Route::post('/video-references/{id}/like', [LikeController::class, 'toggleLike']);
        Route::get('/video-references/{id}/like', [LikeController::class, 'checkLike']);
        Route::get('/likes', [LikeController::class, 'getUserLikes']);

        // Профиль
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);

        // Каталоги
        Route::apiResource('collections', VideoCollectionController::class);
        
        // Видео в каталогах
        Route::get('/collections/{collectionId}/videos', [VideoCollectionItemController::class, 'index']);
        Route::post('/collections/{collectionId}/videos', [VideoCollectionItemController::class, 'store']);
        Route::delete('/collections/{collectionId}/videos/{videoId}', [VideoCollectionItemController::class, 'destroy']);
        Route::get('/video-references/{videoId}/saved', [VideoCollectionItemController::class, 'checkSaved']);
    });
});

// Админские роуты (требуют аутентификации и роли admin)
Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {
    // Информация о текущем админе
    Route::get('/me', [AdminAuthController::class, 'me']);

    // CRUD для video-references (только для админов)
    Route::apiResource('video-references', AdminVideoReferenceController::class);

    // CRUD для categories (только для админов)
    Route::apiResource('categories', AdminCategoryController::class);
});
