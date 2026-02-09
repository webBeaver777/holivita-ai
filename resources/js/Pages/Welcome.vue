<script setup>
import { Head, Link } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Добро пожаловать" />

    <div
        class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4"
    >
        <div class="max-w-2xl w-full">
            <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12">
                <div class="text-center mb-10">
                    <h1 class="text-4xl font-bold text-gray-800 mb-4">
                        Holivita AI
                    </h1>
                    <p class="text-lg text-gray-600">
                        Добро пожаловать в систему онбординга здоровья
                    </p>
                </div>

                <div class="space-y-4">
                    <Link
                        href="/onboarding"
                        class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-4 px-6 rounded-xl transition-colors duration-200 text-center"
                    >
                        <div class="flex items-center justify-center gap-3">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                />
                            </svg>
                            <span>Начать онбординг</span>
                        </div>
                        <p class="text-indigo-200 text-sm mt-1">
                            Пройдите диалог с AI-ассистентом
                        </p>
                    </Link>

                    <Link
                        href="/summaries"
                        class="block w-full bg-white hover:bg-gray-50 text-gray-700 font-semibold py-4 px-6 rounded-xl transition-colors duration-200 text-center border-2 border-gray-200"
                    >
                        <div class="flex items-center justify-center gap-3">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <span>Просмотр суммаризаций</span>
                        </div>
                        <p class="text-gray-500 text-sm mt-1">
                            История завершённых сессий
                        </p>
                    </Link>
                </div>

                <div class="mt-10 pt-6 border-t border-gray-200">
                    <h2
                        class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4"
                    >
                        API Endpoints
                    </h2>
                    <div class="grid gap-2 text-sm text-gray-600">
                        <div class="flex justify-between">
                            <code class="bg-gray-100 px-2 py-1 rounded"
                                >POST /api/onboarding/validate-user</code
                            >
                            <span>Валидация пользователя</span>
                        </div>
                        <div class="flex justify-between">
                            <code class="bg-gray-100 px-2 py-1 rounded"
                                >POST /api/onboarding/chat</code
                            >
                            <span>Отправка сообщения</span>
                        </div>
                        <div class="flex justify-between">
                            <code class="bg-gray-100 px-2 py-1 rounded"
                                >POST /api/onboarding/cancel</code
                            >
                            <span>Отмена сессии</span>
                        </div>
                        <div class="flex justify-between">
                            <code class="bg-gray-100 px-2 py-1 rounded"
                                >POST /api/onboarding/complete</code
                            >
                            <span>Завершение и суммаризация</span>
                        </div>
                        <div class="flex justify-between">
                            <code class="bg-gray-100 px-2 py-1 rounded"
                                >GET /api/summaries</code
                            >
                            <span>Список суммаризаций</span>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-center text-gray-500 text-sm mt-6">
                Holivita AI &copy; {{ new Date().getFullYear() }}
            </p>
        </div>
    </div>
</template>
