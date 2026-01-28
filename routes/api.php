<?php

declare(strict_types=1);

use App\Http\Controllers\Api\OnboardingAsyncController;
use App\Http\Controllers\Api\OnboardingChatController;
use App\Http\Controllers\Api\SummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API маршруты для приложения.
| Авторизация отключена для MVP - используется ручной ввод user_id.
|
*/

Route::prefix('onboarding')->group(function () {
    // Валидация user_id перед началом онбординга
    Route::post('/validate-user', [OnboardingChatController::class, 'validateUser'])
        ->name('onboarding.validate-user');

    // Синхронная отправка сообщения в чат
    Route::post('/chat', [OnboardingChatController::class, 'chat'])
        ->name('onboarding.chat');

    // Асинхронные эндпоинты (через очередь)
    Route::prefix('async')->group(function () {
        // Отправка сообщения в очередь
        Route::post('/chat', [OnboardingAsyncController::class, 'chat'])
            ->name('onboarding.async.chat');

        // Проверка статуса обработки
        Route::get('/status', [OnboardingAsyncController::class, 'status'])
            ->name('onboarding.async.status');
    });

    // Завершение онбординга и суммаризация
    Route::post('/complete', [OnboardingChatController::class, 'complete'])
        ->name('onboarding.complete');

    // Отмена сессии онбординга
    Route::post('/cancel', [OnboardingChatController::class, 'cancel'])
        ->name('onboarding.cancel');

    // Получение истории чата
    Route::get('/history', [OnboardingChatController::class, 'history'])
        ->name('onboarding.history');
});

// Суммаризации
Route::prefix('summaries')->group(function () {
    Route::get('/', [SummaryController::class, 'index'])
        ->name('summaries.index');

    Route::get('/{sessionId}', [SummaryController::class, 'show'])
        ->name('summaries.show');
});
