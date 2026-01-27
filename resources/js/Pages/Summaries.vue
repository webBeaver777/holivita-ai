<script setup>
/**
 * Страница просмотра суммаризаций онбординга.
 */
import { ref, computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

// === STATE ===
const summaries = ref([]);
const searchUserId = ref('');
const isLoading = ref(false);
const error = ref(null);
const selectedSummary = ref(null);

// Пагинация
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const perPage = ref(10);

// === COMPUTED ===
const hasSummaries = computed(() => summaries.value.length > 0);
const hasNextPage = computed(() => currentPage.value < lastPage.value);
const hasPrevPage = computed(() => currentPage.value > 1);

// === METHODS ===

/**
 * Загрузка суммаризаций.
 */
async function loadSummaries(page = 1) {
    isLoading.value = true;
    error.value = null;

    try {
        const params = {
            per_page: perPage.value,
            page: page,
        };

        if (searchUserId.value) {
            params.user_id = parseInt(searchUserId.value);
        }

        const response = await axios.get('/api/summaries', { params });

        if (response.data.success) {
            summaries.value = response.data.data.data;
            currentPage.value = response.data.meta.current_page;
            lastPage.value = response.data.meta.last_page;
            total.value = response.data.meta.total;
        }
    } catch (err) {
        console.error('Load summaries error:', err);
        error.value = 'Не удалось загрузить суммаризации.';
    } finally {
        isLoading.value = false;
    }
}

/**
 * Поиск по User ID.
 */
function search() {
    currentPage.value = 1;
    loadSummaries(1);
}

/**
 * Сброс поиска.
 */
function resetSearch() {
    searchUserId.value = '';
    currentPage.value = 1;
    loadSummaries(1);
}

/**
 * Следующая страница.
 */
function nextPage() {
    if (hasNextPage.value) {
        loadSummaries(currentPage.value + 1);
    }
}

/**
 * Предыдущая страница.
 */
function prevPage() {
    if (hasPrevPage.value) {
        loadSummaries(currentPage.value - 1);
    }
}

/**
 * Открыть детали суммаризации.
 */
function openDetails(summary) {
    selectedSummary.value = summary;
}

/**
 * Закрыть детали.
 */
function closeDetails() {
    selectedSummary.value = null;
}

/**
 * Форматирование даты.
 */
function formatDate(isoString) {
    if (!isoString) return '-';
    return new Date(isoString).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Форматирование JSON для отображения.
 */
function formatSummaryPreview(summary) {
    if (!summary) return 'Нет данных';

    if (summary.summary_text) {
        return summary.summary_text.substring(0, 100) + '...';
    }

    if (summary.health_goals && summary.health_goals.length > 0) {
        return `Цели: ${summary.health_goals.slice(0, 2).join(', ')}...`;
    }

    return 'Суммаризация доступна';
}

// Загрузка при монтировании
loadSummaries();
</script>

<template>
    <Head title="Суммаризации онбординга" />

    <div class="min-h-screen bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-100 p-4">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center shadow-md">
                            <span class="text-xl">📊</span>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                                Суммаризации
                            </h1>
                            <p class="text-sm text-gray-500">
                                Всего записей: {{ total }}
                            </p>
                        </div>
                    </div>

                    <!-- Поиск -->
                    <div class="flex items-center space-x-2">
                        <input
                            v-model="searchUserId"
                            type="number"
                            min="1"
                            placeholder="User ID"
                            @keyup.enter="search"
                            class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                        />
                        <button
                            @click="search"
                            :disabled="isLoading"
                            class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors text-sm disabled:opacity-50"
                        >
                            Найти
                        </button>
                        <button
                            v-if="searchUserId"
                            @click="resetSearch"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm"
                        >
                            Сброс
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ошибка -->
            <div v-if="error" class="bg-red-50 border border-red-200 text-red-600 rounded-xl p-4 mb-6">
                {{ error }}
            </div>

            <!-- Загрузка -->
            <div v-if="isLoading" class="bg-white rounded-2xl shadow-xl p-12 text-center">
                <div class="flex items-center justify-center space-x-2">
                    <div class="w-3 h-3 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                    <div class="w-3 h-3 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                    <div class="w-3 h-3 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                </div>
                <p class="text-gray-500 mt-4">Загрузка...</p>
            </div>

            <!-- Пустой список -->
            <div v-else-if="!hasSummaries" class="bg-white rounded-2xl shadow-xl p-12 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                    <span class="text-3xl">📭</span>
                </div>
                <p class="text-gray-500">Суммаризации не найдены</p>
            </div>

            <!-- Список суммаризаций -->
            <div v-else class="space-y-4">
                <div
                    v-for="summary in summaries"
                    :key="summary.id"
                    @click="openDetails(summary)"
                    class="bg-white rounded-xl shadow-md p-4 hover:shadow-lg transition-shadow cursor-pointer border border-transparent hover:border-purple-200"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    User ID: {{ summary.user_id }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    {{ formatDate(summary.completed_at) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 line-clamp-2">
                                {{ formatSummaryPreview(summary.summary) }}
                            </p>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Пагинация -->
                <div class="flex items-center justify-between bg-white rounded-xl shadow-md p-4">
                    <button
                        @click="prevPage"
                        :disabled="!hasPrevPage || isLoading"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Назад
                    </button>
                    <span class="text-sm text-gray-500">
                        Страница {{ currentPage }} из {{ lastPage }}
                    </span>
                    <button
                        @click="nextPage"
                        :disabled="!hasNextPage || isLoading"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Вперёд
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модалка с деталями -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="selectedSummary"
                class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
                @click.self="closeDetails"
            >
                <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-purple-500 to-blue-500 text-white p-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold">Суммаризация</h2>
                            <p class="text-sm opacity-80">User ID: {{ selectedSummary.user_id }}</p>
                        </div>
                        <button
                            @click="closeDetails"
                            class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                        <div class="space-y-4">
                            <!-- Дата -->
                            <div class="text-sm text-gray-500">
                                Завершено: {{ formatDate(selectedSummary.completed_at) }}
                            </div>

                            <!-- Summary Text -->
                            <div v-if="selectedSummary.summary?.summary_text" class="bg-purple-50 rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-purple-700 mb-2">Описание</h3>
                                <p class="text-gray-700">{{ selectedSummary.summary.summary_text }}</p>
                            </div>

                            <!-- Health Goals -->
                            <div v-if="selectedSummary.summary?.health_goals?.length" class="bg-green-50 rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-green-700 mb-2">Цели здоровья</h3>
                                <ul class="list-disc list-inside text-gray-700 space-y-1">
                                    <li v-for="goal in selectedSummary.summary.health_goals" :key="goal">{{ goal }}</li>
                                </ul>
                            </div>

                            <!-- Current Health Issues -->
                            <div v-if="selectedSummary.summary?.current_health_issues?.length" class="bg-orange-50 rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-orange-700 mb-2">Текущие проблемы</h3>
                                <ul class="list-disc list-inside text-gray-700 space-y-1">
                                    <li v-for="issue in selectedSummary.summary.current_health_issues" :key="issue">{{ issue }}</li>
                                </ul>
                            </div>

                            <!-- Lifestyle -->
                            <div v-if="selectedSummary.summary?.lifestyle" class="bg-blue-50 rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-blue-700 mb-2">Образ жизни</h3>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div v-if="selectedSummary.summary.lifestyle.sleep">
                                        <span class="text-gray-500">Сон:</span>
                                        <span class="text-gray-700 ml-1">{{ selectedSummary.summary.lifestyle.sleep }}</span>
                                    </div>
                                    <div v-if="selectedSummary.summary.lifestyle.nutrition">
                                        <span class="text-gray-500">Питание:</span>
                                        <span class="text-gray-700 ml-1">{{ selectedSummary.summary.lifestyle.nutrition }}</span>
                                    </div>
                                    <div v-if="selectedSummary.summary.lifestyle.activity">
                                        <span class="text-gray-500">Активность:</span>
                                        <span class="text-gray-700 ml-1">{{ selectedSummary.summary.lifestyle.activity }}</span>
                                    </div>
                                    <div v-if="selectedSummary.summary.lifestyle.stress">
                                        <span class="text-gray-500">Стресс:</span>
                                        <span class="text-gray-700 ml-1">{{ selectedSummary.summary.lifestyle.stress }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Recommendations -->
                            <div v-if="selectedSummary.summary?.recommendations_focus?.length" class="bg-indigo-50 rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-indigo-700 mb-2">Фокус рекомендаций</h3>
                                <ul class="list-disc list-inside text-gray-700 space-y-1">
                                    <li v-for="rec in selectedSummary.summary.recommendations_focus" :key="rec">{{ rec }}</li>
                                </ul>
                            </div>

                            <!-- Raw JSON (if structure is different) -->
                            <div v-if="!selectedSummary.summary?.summary_text && !selectedSummary.summary?.health_goals" class="bg-gray-50 rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-gray-700 mb-2">Данные</h3>
                                <pre class="text-xs text-gray-600 overflow-x-auto whitespace-pre-wrap">{{ JSON.stringify(selectedSummary.summary, null, 2) }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type=number] {
    -moz-appearance: textfield;
}
</style>
