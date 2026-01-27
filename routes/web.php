<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

// Онбординг чат (MVP версия без auth - используется ручной ввод user_id)
Route::get('/onboarding', function () {
    return Inertia::render('OnboardingChat');
})->name('onboarding');

// Просмотр суммаризаций
Route::get('/summaries', function () {
    return Inertia::render('Summaries');
})->name('summaries');
