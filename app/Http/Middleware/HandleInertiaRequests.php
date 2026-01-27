<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Middleware для обработки Inertia запросов.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Корневой шаблон.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Определить версию ассетов.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Общие данные для всех Inertia страниц.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
