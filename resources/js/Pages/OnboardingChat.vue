<script setup>
/**
 * Компонент чата онбординга HOLI.
 * Заботливый AI-ассистент для знакомства с пользователем.
 * MVP версия с ручным вводом user_id.
 */
import { ref, computed, nextTick } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import { useVoiceRecorder } from "@/composables/useVoiceRecorder";

// === STATE ===

// Форма входа
const userIdInput = ref("");
const userId = ref(null);
const userIdError = ref("");
const isValidating = ref(false);
const activeSessionId = ref(null);
const isCancelling = ref(false);

// Чат
const messages = ref([]);
const userInput = ref("");
const isLoading = ref(false);
const isCompleted = ref(false);
const sessionId = ref(null);
const error = ref(null);
const messagesContainer = ref(null);

// Голосовой ввод
const {
    isRecording,
    isTranscribing,
    error: voiceError,
    formattedDuration: formattedRecordingDuration,
    isVoiceSupported,
    startRecording,
    stopRecording,
    transcribe,
    clearError: clearVoiceError,
    cleanup: cleanupVoice,
} = useVoiceRecorder();

// === COMPUTED ===

const canSend = computed(() => {
    return (
        userInput.value.trim().length > 0 &&
        !isLoading.value &&
        !isCompleted.value &&
        !isRecording.value &&
        !isTranscribing.value
    );
});

const canRecord = computed(() => {
    return !isLoading.value && !isCompleted.value && !isTranscribing.value;
});

const hasMessages = computed(() => messages.value.length > 0);

// === METHODS ===

/**
 * Прокрутка к последнему сообщению.
 */
async function scrollToBottom() {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight;
    }
}

/**
 * Валидация User ID через API.
 */
async function validateUserId() {
    if (!userIdInput.value) return;

    isValidating.value = true;
    userIdError.value = "";
    activeSessionId.value = null;

    try {
        const response = await axios.post("/api/onboarding/validate-user", {
            user_id: parseInt(userIdInput.value),
        });

        if (response.data.success) {
            userId.value = parseInt(userIdInput.value);
            await startOnboarding();
        }
    } catch (err) {
        if (
            err.response?.status === 409 &&
            err.response?.data?.active_session_id
        ) {
            activeSessionId.value = err.response.data.active_session_id;
            userIdError.value =
                err.response.data.message ||
                "У вас уже есть активная сессия онбординга.";
        } else if (err.response?.data?.message) {
            userIdError.value = err.response.data.message;
        } else if (err.response?.status === 422) {
            userIdError.value = "Введите корректный User ID (число).";
        } else {
            userIdError.value = "Ошибка проверки User ID. Попробуйте ещё раз.";
        }
    } finally {
        isValidating.value = false;
    }
}

/**
 * Отмена активной сессии.
 */
async function cancelActiveSession() {
    if (!activeSessionId.value || !userIdInput.value) return;

    isCancelling.value = true;

    try {
        await axios.post("/api/onboarding/cancel", {
            user_id: parseInt(userIdInput.value),
            session_id: activeSessionId.value,
        });

        activeSessionId.value = null;
        userIdError.value = "";
        await validateUserId();
    } catch (err) {
        userIdError.value =
            err.response?.data?.error || "Не удалось отменить сессию.";
    } finally {
        isCancelling.value = false;
    }
}

/**
 * Продолжить существующую сессию.
 */
async function continueExistingSession() {
    if (!activeSessionId.value || !userIdInput.value) return;

    userId.value = parseInt(userIdInput.value);
    sessionId.value = activeSessionId.value;
    activeSessionId.value = null;
    userIdError.value = "";

    isLoading.value = true;
    try {
        const response = await axios.get("/api/onboarding/history", {
            params: {
                user_id: userId.value,
            },
        });

        if (response.data.success && response.data.data.messages) {
            messages.value = response.data.data.messages.map((msg) => ({
                role: msg.role,
                content: msg.content,
            }));
            isCompleted.value = response.data.data.is_completed;
        }
    } catch (err) {
        console.error("Load history error:", err);
        await startOnboarding();
    } finally {
        isLoading.value = false;
        await scrollToBottom();
    }
}

/**
 * Начало онбординга - получение приветствия от HOLI.
 */
async function startOnboarding() {
    isLoading.value = true;
    error.value = null;

    try {
        const response = await axios.post("/api/onboarding/chat", {
            user_id: userId.value,
            session_id: null,
            message: null,
            conversation_history: [],
        });

        if (response.data.success) {
            const data = response.data.data;
            sessionId.value = data.session_id;

            messages.value.push({
                role: "assistant",
                content: data.message,
            });
        }
    } catch (err) {
        console.error("Start onboarding error:", err);
        error.value =
            "Не удалось начать онбординг. Попробуйте обновить страницу.";
    } finally {
        isLoading.value = false;
        await scrollToBottom();
    }
}

/**
 * Отправка сообщения пользователя.
 */
async function sendMessage() {
    if (!canSend.value) return;

    const userMessage = userInput.value.trim();
    userInput.value = "";
    error.value = null;

    messages.value.push({
        role: "user",
        content: userMessage,
    });

    await scrollToBottom();
    isLoading.value = true;

    try {
        const conversationHistory = messages.value.map((msg) => ({
            role: msg.role,
            content: msg.content,
        }));

        const response = await axios.post("/api/onboarding/chat", {
            user_id: userId.value,
            session_id: sessionId.value,
            message: userMessage,
            conversation_history: conversationHistory,
        });

        if (response.data.success) {
            const data = response.data.data;

            messages.value.push({
                role: "assistant",
                content: data.message,
            });

            if (data.completed) {
                await completeOnboarding();
            }
        }
    } catch (err) {
        console.error("Chat error:", err);
        error.value =
            err.response?.data?.error ||
            "Произошла ошибка. Попробуйте ещё раз.";
        messages.value.pop();
    } finally {
        isLoading.value = false;
        await scrollToBottom();
    }
}

/**
 * Завершение онбординга и получение суммаризации.
 */
async function completeOnboarding() {
    try {
        const response = await axios.post("/api/onboarding/complete", {
            user_id: userId.value,
            session_id: sessionId.value,
        });

        if (response.data.success) {
            isCompleted.value = true;

            messages.value.push({
                role: "assistant",
                content:
                    "Отлично! Я узнал тебя получше. Теперь смогу давать более персональные рекомендации. Добро пожаловать в Holivita!",
            });
        }
    } catch (err) {
        console.error("Complete error:", err);
    }

    await scrollToBottom();
}

/**
 * Обработка Enter.
 */
function handleKeydown(event) {
    if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

/**
 * Сброс и начало заново.
 */
function resetChat() {
    userId.value = null;
    userIdInput.value = "";
    messages.value = [];
    sessionId.value = null;
    isCompleted.value = false;
    error.value = null;
    activeSessionId.value = null;
    userIdError.value = "";
    cleanupVoice();
}

// === VOICE INPUT ===

/**
 * Переключение записи голоса.
 */
async function toggleVoiceRecording() {
    if (isRecording.value) {
        await stopAndTranscribe();
    } else {
        await startRecording();
    }
}

/**
 * Остановить запись и транскрибировать.
 */
async function stopAndTranscribe() {
    const audioData = await stopRecording();
    if (!audioData) return;

    const result = await transcribe(audioData, userId.value, sessionId.value);
    if (result?.text) {
        userInput.value = result.text;
        await nextTick();
        await sendMessage();
    }
}
</script>

<template>
    <Head title="Знакомство с HOLI" />

    <div
        class="min-h-screen bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-100 p-4"
    >
        <div class="max-w-2xl mx-auto">
            <!-- === ФОРМА ВВОДА USER ID === -->
            <div v-if="!userId" class="bg-white rounded-2xl shadow-xl p-8 mt-8">
                <!-- Логотип и заголовок -->
                <div class="text-center mb-8">
                    <div
                        class="w-20 h-20 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full mx-auto mb-4 flex items-center justify-center shadow-lg"
                    >
                        <span class="text-white text-3xl">🌿</span>
                    </div>
                    <h1
                        class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent mb-2"
                    >
                        Добро пожаловать в HOLI
                    </h1>
                    <p class="text-gray-500">
                        Ваш заботливый AI-помощник в мире здоровья
                    </p>
                </div>

                <!-- Форма -->
                <div class="max-w-sm mx-auto">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Введите ваш User ID
                    </label>
                    <input
                        v-model="userIdInput"
                        type="number"
                        min="1"
                        placeholder="Например: 1"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-lg transition-all"
                        :disabled="isValidating"
                        @keyup.enter="validateUserId"
                    />

                    <!-- Ошибка валидации -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div
                            v-if="userIdError"
                            class="mt-3 p-3 rounded-xl"
                            :class="
                                activeSessionId
                                    ? 'bg-amber-50 border border-amber-200'
                                    : 'bg-red-50 border border-red-200'
                            "
                        >
                            <div class="flex items-start">
                                <svg
                                    v-if="!activeSessionId"
                                    class="w-5 h-5 text-red-500 mr-2 flex-shrink-0 mt-0.5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="w-5 h-5 text-amber-500 mr-2 flex-shrink-0 mt-0.5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <div class="flex-1">
                                    <p
                                        class="text-sm"
                                        :class="
                                            activeSessionId
                                                ? 'text-amber-700'
                                                : 'text-red-600'
                                        "
                                    >
                                        {{ userIdError }}
                                    </p>
                                    <!-- Кнопки для активной сессии -->
                                    <div
                                        v-if="activeSessionId"
                                        class="mt-3 flex flex-wrap gap-2"
                                    >
                                        <button
                                            class="px-4 py-2 bg-purple-500 text-white text-sm rounded-lg hover:bg-purple-600 transition-colors disabled:opacity-50"
                                            :disabled="isCancelling"
                                            @click="continueExistingSession"
                                        >
                                            Продолжить сессию
                                        </button>
                                        <button
                                            class="px-4 py-2 bg-white text-red-600 text-sm rounded-lg border border-red-300 hover:bg-red-50 transition-colors disabled:opacity-50"
                                            :disabled="isCancelling"
                                            @click="cancelActiveSession"
                                        >
                                            <span
                                                v-if="isCancelling"
                                                class="flex items-center"
                                            >
                                                <svg
                                                    class="animate-spin -ml-1 mr-2 h-4 w-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle
                                                        class="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        stroke-width="4"
                                                    />
                                                    <path
                                                        class="opacity-75"
                                                        fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                                    />
                                                </svg>
                                                Отмена...
                                            </span>
                                            <span v-else
                                                >Отменить и начать заново</span
                                            >
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <!-- Кнопка -->
                    <button
                        class="w-full mt-6 px-6 py-3 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl font-medium hover:from-purple-600 hover:to-blue-600 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-md hover:shadow-lg"
                        :disabled="!userIdInput || isValidating"
                        @click="validateUserId"
                    >
                        <span
                            v-if="isValidating"
                            class="flex items-center justify-center"
                        >
                            <svg
                                class="animate-spin -ml-1 mr-2 h-5 w-5 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            Проверка...
                        </span>
                        <span v-else>Начать онбординг</span>
                    </button>

                    <p class="text-center text-xs text-gray-400 mt-4">
                        User ID выдаётся при регистрации в системе
                    </p>
                </div>
            </div>

            <!-- === ЧАТ === -->
            <div v-else class="flex flex-col h-[calc(100vh-2rem)]">
                <!-- Header -->
                <header
                    class="bg-white rounded-t-2xl shadow-lg p-4 border-b border-gray-100 flex-shrink-0"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div
                                class="w-12 h-12 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center shadow-md"
                            >
                                <span class="text-xl">🌿</span>
                            </div>
                            <div>
                                <h1
                                    class="text-xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent"
                                >
                                    HOLI
                                </h1>
                                <p class="text-xs text-gray-500">
                                    Ваш заботливый помощник
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span
                                class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-full"
                            >
                                ID: {{ userId }}
                            </span>
                            <button
                                v-if="isCompleted"
                                class="text-xs text-purple-600 hover:text-purple-800 transition-colors"
                                @click="resetChat"
                            >
                                Начать заново
                            </button>
                        </div>
                    </div>
                </header>

                <!-- Messages Area -->
                <div
                    ref="messagesContainer"
                    class="flex-1 overflow-y-auto bg-white/70 backdrop-blur-sm p-4 space-y-4"
                >
                    <!-- Сообщения -->
                    <TransitionGroup name="message">
                        <div
                            v-for="(message, index) in messages"
                            :key="index"
                            :class="[
                                'flex',
                                message.role === 'user'
                                    ? 'justify-end'
                                    : 'justify-start',
                            ]"
                        >
                            <div
                                :class="[
                                    'max-w-[80%] rounded-2xl px-4 py-3 shadow-sm',
                                    message.role === 'user'
                                        ? 'bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-br-md'
                                        : 'bg-white text-gray-700 rounded-bl-md border border-gray-100',
                                ]"
                            >
                                <p
                                    class="text-sm leading-relaxed whitespace-pre-wrap"
                                >
                                    {{ message.content }}
                                </p>
                            </div>
                        </div>
                    </TransitionGroup>

                    <!-- Индикатор загрузки -->
                    <div v-if="isLoading" class="flex justify-start">
                        <div
                            class="bg-white text-gray-500 rounded-2xl rounded-bl-md px-4 py-3 shadow-sm border border-gray-100"
                        >
                            <div class="flex items-center space-x-2">
                                <div class="flex space-x-1">
                                    <span
                                        class="w-2 h-2 bg-purple-400 rounded-full animate-bounce"
                                        style="animation-delay: 0ms"
                                    />
                                    <span
                                        class="w-2 h-2 bg-purple-400 rounded-full animate-bounce"
                                        style="animation-delay: 150ms"
                                    />
                                    <span
                                        class="w-2 h-2 bg-purple-400 rounded-full animate-bounce"
                                        style="animation-delay: 300ms"
                                    />
                                </div>
                                <span class="text-xs">HOLI печатает...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Ошибка -->
                    <div v-if="error" class="flex justify-center">
                        <div
                            class="bg-red-50 text-red-600 rounded-xl px-4 py-2 text-sm border border-red-200"
                        >
                            {{ error }}
                        </div>
                    </div>
                </div>

                <!-- Индикатор завершения -->
                <div
                    v-if="isCompleted"
                    class="bg-white/70 px-4 py-3 flex-shrink-0"
                >
                    <div
                        class="flex items-center justify-center bg-green-50 text-green-600 rounded-xl px-4 py-2 text-sm border border-green-200"
                    >
                        <svg
                            class="w-4 h-4 mr-2"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Знакомство завершено! Добро пожаловать в Holivita.
                    </div>
                </div>

                <!-- Input Area -->
                <div class="bg-white rounded-b-2xl shadow-lg p-3 flex-shrink-0">
                    <!-- Ошибка голосового ввода -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div
                            v-if="voiceError"
                            class="mb-3 p-2 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-between"
                        >
                            <div class="flex items-center">
                                <svg
                                    class="w-4 h-4 text-amber-500 mr-2 flex-shrink-0"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <span class="text-xs text-amber-700">{{
                                    voiceError
                                }}</span>
                            </div>
                            <button
                                class="text-amber-500 hover:text-amber-700 ml-2"
                                @click="clearVoiceError"
                            >
                                <svg
                                    class="w-4 h-4"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </div>
                    </Transition>

                    <!-- Индикатор записи -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="isRecording"
                            class="mb-3 p-3 rounded-xl bg-red-50 border border-red-200"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="relative">
                                        <div
                                            class="w-3 h-3 bg-red-500 rounded-full animate-pulse"
                                        />
                                        <div
                                            class="absolute inset-0 w-3 h-3 bg-red-400 rounded-full animate-ping"
                                        />
                                    </div>
                                    <span
                                        class="text-sm text-red-700 font-medium"
                                        >Запись...</span
                                    >
                                    <span
                                        class="text-sm text-red-500 font-mono"
                                        >{{ formattedRecordingDuration }}</span
                                    >
                                </div>
                                <span class="text-xs text-red-400"
                                    >Нажмите для завершения</span
                                >
                            </div>
                        </div>
                    </Transition>

                    <!-- Индикатор транскрипции -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="isTranscribing"
                            class="mb-3 p-3 rounded-xl bg-purple-50 border border-purple-200"
                        >
                            <div class="flex items-center space-x-3">
                                <svg
                                    class="animate-spin w-4 h-4 text-purple-500"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    />
                                </svg>
                                <span class="text-sm text-purple-700"
                                    >Распознавание речи...</span
                                >
                            </div>
                        </div>
                    </Transition>

                    <div class="flex items-end space-x-2">
                        <textarea
                            v-model="userInput"
                            :disabled="
                                isLoading ||
                                isCompleted ||
                                isRecording ||
                                isTranscribing
                            "
                            :placeholder="
                                isCompleted
                                    ? 'Знакомство завершено'
                                    : isRecording
                                      ? 'Идёт запись...'
                                      : 'Напиши сообщение...'
                            "
                            rows="1"
                            class="flex-1 resize-none border border-gray-200 bg-gray-50 rounded-xl px-4 py-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent disabled:opacity-50 transition-all"
                            style="max-height: 120px; min-height: 48px"
                            @keydown="handleKeydown"
                        />

                        <!-- Кнопка голосового ввода -->
                        <button
                            v-if="isVoiceSupported"
                            :disabled="!canRecord"
                            :class="[
                                'flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center transition-all duration-200 hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed',
                                isRecording
                                    ? 'bg-red-500 text-white hover:bg-red-600 animate-pulse'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:scale-105 active:scale-95',
                            ]"
                            :title="
                                isRecording
                                    ? 'Остановить запись'
                                    : 'Голосовой ввод'
                            "
                            @click="toggleVoiceRecording"
                        >
                            <!-- Иконка микрофона -->
                            <svg
                                v-if="!isRecording"
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
                                />
                            </svg>
                            <!-- Иконка стоп -->
                            <svg
                                v-else
                                class="w-5 h-5"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <rect
                                    x="6"
                                    y="6"
                                    width="12"
                                    height="12"
                                    rx="2"
                                />
                            </svg>
                        </button>

                        <!-- Кнопка отправки -->
                        <button
                            :disabled="!canSend"
                            class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-r from-purple-500 to-blue-500 text-white flex items-center justify-center transition-all duration-200 hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed hover:scale-105 active:scale-95"
                            @click="sendMessage"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                                />
                            </svg>
                        </button>
                    </div>

                    <p class="text-center text-xs text-gray-400 mt-2">
                        HOLI не ставит диагнозы и не назначает лечение
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Анимация появления сообщений */
.message-enter-active {
    transition: all 0.3s ease-out;
}

.message-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

/* Кастомный скроллбар */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: rgba(139, 92, 246, 0.3);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(139, 92, 246, 0.5);
}

/* Авторесайз textarea */
textarea {
    field-sizing: content;
}

/* Убираем стрелки у input[type=number] */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}
</style>
