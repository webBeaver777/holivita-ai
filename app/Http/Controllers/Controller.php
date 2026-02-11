<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Holivita AI API',
    description: 'API для онбординг-чата с AI-ассистентом, суммаризации и голосового ввода (OpenAI Whisper). Авторизация отключена для MVP — user_id передаётся вручную. Модули: Onboarding (синхронный и асинхронный чат), Summaries (суммаризации сессий), Voice (транскрипция аудио). Формат ответов: успех — {success: true, data: {...}}, ошибка — {success: false, error: "..."}.',
    contact: new OA\Contact(name: 'Holivita AI Team', email: 'support@holivita.ai')
)]
#[OA\Server(url: '/api', description: 'API сервер')]
#[OA\Tag(name: 'Onboarding', description: 'Синхронный онбординг-чат с AI-ассистентом. Позволяет валидировать пользователя, вести диалог, завершать/отменять сессию и получать историю.')]
#[OA\Tag(name: 'Onboarding Async', description: 'Асинхронная обработка онбординга через очередь. Сообщения ставятся в очередь и обрабатываются фоновым воркером.')]
#[OA\Tag(name: 'Summaries', description: 'Суммаризации завершённых сессий онбординга. Содержат структурированные данные о пользователе.')]
#[OA\Tag(name: 'Voice', description: 'Голосовой ввод через OpenAI Whisper API. Поддерживает синхронную и асинхронную транскрипцию аудио в текст.')]
#[OA\Schema(
    schema: 'SuccessResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', nullable: true),
        new OA\Property(property: 'data', type: 'object', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'string', example: 'Описание ошибки'),
    ],
    type: 'object'
)]
abstract class Controller
{
    //
}
