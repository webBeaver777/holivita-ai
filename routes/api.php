<?php

declare(strict_types=1);

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

    // Отправка сообщения в чат
    Route::post('/chat', [OnboardingChatController::class, 'chat'])
        ->name('onboarding.chat');

    // Завершение онбординга и суммаризация
    Route::post('/complete', [OnboardingChatController::class, 'complete'])
        ->name('onboarding.complete');

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
