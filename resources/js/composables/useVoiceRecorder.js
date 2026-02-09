/**
 * Composable для записи голоса и транскрипции.
 * Следует принципам SOLID - единая ответственность за голосовой ввод.
 */
import { ref, computed, onUnmounted } from "vue";
import axios from "axios";

// Константы
const MAX_RECORDING_DURATION = 60; // 1 минута
const RECORDING_INTERVAL_MS = 100;
const TRANSCRIPTION_TIMEOUT_MS = 60000;
const POLL_INTERVAL_MS = 500;
const MAX_POLL_ATTEMPTS = 120; // 60 секунд при интервале 500ms

/**
 * Определить поддерживаемый MIME-тип для записи.
 */
function getSupportedMimeType() {
    const types = [
        "audio/webm;codecs=opus",
        "audio/webm",
        "audio/mp4",
        "audio/ogg",
    ];

    for (const type of types) {
        if (MediaRecorder.isTypeSupported(type)) {
            return type;
        }
    }

    return "audio/webm";
}

/**
 * Получить расширение файла по MIME-типу.
 */
function getFileExtension(mimeType) {
    if (mimeType.includes("webm")) return "webm";
    if (mimeType.includes("mp4")) return "mp4";
    if (mimeType.includes("ogg")) return "ogg";
    return "webm";
}

/**
 * Composable для голосового ввода.
 */
export function useVoiceRecorder() {
    // State
    const isRecording = ref(false);
    const isTranscribing = ref(false);
    const error = ref(null);
    const recordingDuration = ref(0);

    // Internal state
    let mediaRecorder = null;
    let audioChunks = [];
    let recordingTimer = null;
    let currentStream = null;

    // Computed
    const isVoiceSupported = computed(() => {
        return (
            typeof navigator !== "undefined" &&
            navigator.mediaDevices &&
            typeof navigator.mediaDevices.getUserMedia === "function"
        );
    });

    const formattedDuration = computed(() => {
        const minutes = Math.floor(recordingDuration.value / 60);
        const seconds = recordingDuration.value % 60;
        return `${minutes}:${seconds.toString().padStart(2, "0")}`;
    });

    const isProcessing = computed(() => {
        return isRecording.value || isTranscribing.value;
    });

    /**
     * Начать запись голоса.
     */
    async function startRecording() {
        if (!isVoiceSupported.value) {
            error.value = "Голосовой ввод не поддерживается в этом браузере.";
            return false;
        }

        error.value = null;

        try {
            currentStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 44100,
                },
            });

            audioChunks = [];
            recordingDuration.value = 0;

            const mimeType = getSupportedMimeType();
            mediaRecorder = new MediaRecorder(currentStream, { mimeType });

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            };

            mediaRecorder.onerror = (event) => {
                console.error("MediaRecorder error:", event);
                error.value = "Ошибка записи. Попробуйте ещё раз.";
                stopRecording(true);
            };

            mediaRecorder.start(RECORDING_INTERVAL_MS);
            isRecording.value = true;

            // Таймер длительности
            recordingTimer = setInterval(() => {
                recordingDuration.value++;
                if (recordingDuration.value >= MAX_RECORDING_DURATION) {
                    stopRecording();
                }
            }, 1000);

            return true;
        } catch (err) {
            console.error("Microphone access error:", err);
            handleMicrophoneError(err);
            return false;
        }
    }

    /**
     * Остановить запись голоса.
     */
    function stopRecording(cancel = false) {
        return new Promise((resolve) => {
            // Очищаем таймер
            if (recordingTimer) {
                clearInterval(recordingTimer);
                recordingTimer = null;
            }

            // Если отменяем - очищаем chunks
            if (cancel) {
                audioChunks = [];
            }

            // Останавливаем запись
            if (mediaRecorder && mediaRecorder.state !== "inactive") {
                mediaRecorder.onstop = () => {
                    cleanupStream();
                    resolve(cancel ? null : getAudioBlob());
                };
                mediaRecorder.stop();
            } else {
                cleanupStream();
                resolve(null);
            }

            isRecording.value = false;
            recordingDuration.value = 0;
        });
    }

    /**
     * Получить blob из записанных chunks.
     */
    function getAudioBlob() {
        if (audioChunks.length === 0) return null;

        const mimeType = mediaRecorder?.mimeType || "audio/webm";
        return {
            blob: new Blob(audioChunks, { type: mimeType }),
            mimeType,
            extension: getFileExtension(mimeType),
        };
    }

    /**
     * Транскрибировать аудио асинхронно через очередь.
     */
    async function transcribe(
        audioData,
        userId,
        sessionId = null,
        provider = null,
    ) {
        if (!audioData?.blob) return null;

        isTranscribing.value = true;
        error.value = null;

        try {
            // Отправляем аудио в очередь
            const formData = new FormData();
            formData.append(
                "audio",
                audioData.blob,
                `recording.${audioData.extension}`,
            );
            formData.append("user_id", userId);
            formData.append("language", "ru");

            if (sessionId) {
                formData.append("session_id", sessionId);
            }

            if (provider) {
                formData.append("provider", provider);
            }

            const submitResponse = await axios.post(
                "/api/voice/async/transcribe",
                formData,
                {
                    headers: { "Content-Type": "multipart/form-data" },
                    timeout: 30000,
                },
            );

            if (!submitResponse.data.success) {
                error.value =
                    submitResponse.data.error ||
                    "Не удалось отправить аудио на распознавание.";
                return null;
            }

            const transcriptionId = submitResponse.data.data.transcription_id;

            // Опрашиваем статус до завершения
            const result = await pollTranscriptionStatus(
                transcriptionId,
                userId,
            );
            return result;
        } catch (err) {
            console.error("Transcription error:", err);
            handleTranscriptionError(err);
            return null;
        } finally {
            isTranscribing.value = false;
            audioChunks = [];
        }
    }

    /**
     * Опрашивать статус транскрипции до завершения.
     */
    async function pollTranscriptionStatus(transcriptionId, userId) {
        let attempts = 0;

        while (attempts < MAX_POLL_ATTEMPTS) {
            try {
                const response = await axios.get("/api/voice/async/status", {
                    params: {
                        transcription_id: transcriptionId,
                        user_id: userId,
                    },
                });

                const data = response.data.data;

                if (data.completed) {
                    if (data.status === "completed") {
                        const text = (data.text || "").trim();
                        if (text) {
                            return {
                                text,
                                provider: data.provider,
                            };
                        }
                        error.value =
                            data.message ||
                            "Не удалось распознать речь. Попробуйте ещё раз.";
                    } else if (data.status === "failed") {
                        error.value =
                            data.error || "Ошибка распознавания речи.";
                    }
                    return null;
                }

                // Ждём перед следующим запросом
                await new Promise((resolve) =>
                    setTimeout(resolve, POLL_INTERVAL_MS),
                );
                attempts++;
            } catch (err) {
                console.error("Poll status error:", err);
                if (attempts >= MAX_POLL_ATTEMPTS - 1) {
                    error.value = "Превышено время ожидания распознавания.";
                    return null;
                }
                await new Promise((resolve) =>
                    setTimeout(resolve, POLL_INTERVAL_MS),
                );
                attempts++;
            }
        }

        error.value = "Превышено время ожидания распознавания.";
        return null;
    }

    /**
     * Обработка ошибок доступа к микрофону.
     */
    function handleMicrophoneError(err) {
        if (
            err.name === "NotAllowedError" ||
            err.name === "PermissionDeniedError"
        ) {
            error.value =
                "Доступ к микрофону запрещён. Разрешите доступ в настройках браузера.";
        } else if (err.name === "NotFoundError") {
            error.value =
                "Микрофон не найден. Подключите микрофон и попробуйте снова.";
        } else {
            error.value = "Не удалось получить доступ к микрофону.";
        }
    }

    /**
     * Обработка ошибок транскрипции.
     */
    function handleTranscriptionError(err) {
        if (err.response?.status === 503) {
            error.value =
                "Сервис распознавания речи недоступен. Проверьте настройки провайдера.";
        } else if (err.response?.status === 422) {
            const message =
                err.response?.data?.errors?.audio?.[0] ||
                err.response?.data?.message;
            error.value = message || "Неверный формат аудио.";
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else if (err.code === "ECONNABORTED") {
            error.value = "Превышено время ожидания. Попробуйте ещё раз.";
        } else {
            error.value = "Ошибка распознавания речи. Попробуйте ещё раз.";
        }
    }

    /**
     * Очистка потока микрофона.
     */
    function cleanupStream() {
        if (currentStream) {
            currentStream.getTracks().forEach((track) => track.stop());
            currentStream = null;
        }
    }

    /**
     * Очистить ошибку.
     */
    function clearError() {
        error.value = null;
    }

    /**
     * Полная очистка ресурсов.
     */
    function cleanup() {
        stopRecording(true);
    }

    // Автоочистка при размонтировании
    onUnmounted(cleanup);

    return {
        // State
        isRecording,
        isTranscribing,
        isProcessing,
        error,
        recordingDuration,
        formattedDuration,
        isVoiceSupported,

        // Methods
        startRecording,
        stopRecording,
        transcribe,
        clearError,
        cleanup,
    };
}
