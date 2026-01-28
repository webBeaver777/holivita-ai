<div align="center">

# Holivita AI

**AI-Powered Health Onboarding System**

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4.0-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)

[Возможности](#-возможности) •
[Быстрый старт](#-быстрый-старт) •
[API](#-api-документация) •
[Архитектура](#-архитектура) •
[Тестирование](#-тестирование)

</div>

---

## О проекте

**Holivita AI** — это интеллектуальная система онбординга для сбора информации о здоровье пользователей через диалог с AI-ассистентом. Система проводит персонализированное интервью, собирает данные о целях здоровья, образе жизни и формирует структурированную суммаризацию для дальнейших рекомендаций.

### Ключевые особенности

- **Диалоговый AI-интерфейс** — естественное общение с ассистентом HOLI
- **Умная суммаризация** — автоматическое извлечение ключевой информации
- **Асинхронная обработка** — поддержка очередей для высоких нагрузок
- **Управление сессиями** — продолжение, отмена и автоистечение сессий
- **Современный стек** — Laravel 12, Vue 3, Inertia.js, Tailwind CSS 4

---

## Возможности

| Функция | Описание |
|---------|----------|
| **Онбординг чат** | Интерактивный диалог с AI для сбора данных о здоровье |
| **Синхронный режим** | Мгновенные ответы для быстрого взаимодействия |
| **Асинхронный режим** | Обработка через очереди для масштабирования |
| **Суммаризация** | Автоматическое создание структурированного профиля |
| **История диалогов** | Сохранение и просмотр всех сообщений |
| **Управление сессиями** | Отмена, продолжение, автоистечение неактивных сессий |

---

## Быстрый старт

### Требования

- **PHP** 8.2+
- **Composer** 2.x
- **Node.js** 18+ и npm/pnpm
- **MySQL** 8.0+ или **SQLite**
- **AnythingLLM** (или совместимый AI-провайдер)

### Установка

#### 1. Клонирование репозитория

```bash
git clone https://github.com/your-username/holivita-ai.git
cd holivita-ai
```

#### 2. Автоматическая установка

```bash
composer setup
```

Эта команда выполнит:
- Установку PHP-зависимостей
- Копирование `.env.example` → `.env`
- Генерацию ключа приложения
- Миграцию базы данных
- Установку npm-зависимостей
- Сборку фронтенда

#### 3. Ручная установка (альтернатива)

```bash
# Установка зависимостей
composer install
npm install

# Настройка окружения
cp .env.example .env
php artisan key:generate

# База данных
php artisan migrate

# Сборка фронтенда
npm run build
```

### Настройка окружения

Отредактируйте файл `.env`:

```env
# База данных
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=holivita_ai
DB_USERNAME=root
DB_PASSWORD=your_password

# Очереди (рекомендуется database или redis)
QUEUE_CONNECTION=database

# AnythingLLM
ANYTHINGLLM_API_URL=http://localhost:3001
ANYTHINGLLM_API_KEY=your_api_key
ANYTHINGLLM_WORKSPACE=holi-onboarding
ANYTHINGLLM_SUMMARY_WORKSPACE=holi-summarization

# Настройки онбординга (опционально)
ONBOARDING_WELCOME_PROMPT="Начни онбординг. Поприветствуй пользователя."
ONBOARDING_QUEUE=onboarding
ONBOARDING_SESSION_EXPIRY_HOURS=24
```

### Запуск

#### Режим разработки (рекомендуется)

```bash
composer dev
```

Запустит одновременно:
- **Laravel Server** — `http://localhost:8000`
- **Queue Worker** — обработка фоновых задач
- **Pail** — real-time логи
- **Vite** — hot-reload для фронтенда

#### Продакшн

```bash
# Сборка фронтенда
npm run build

# Запуск сервера
php artisan serve

# В отдельном терминале — воркер очередей
php artisan queue:work --queue=onboarding,default
```

---

## API Документация

### Базовый URL

```
http://localhost:8000/api
```

### Онбординг

#### Валидация пользователя

Проверка возможности начать онбординг.

```http
POST /api/onboarding/validate-user
Content-Type: application/json

{
    "user_id": 123
}
```

<details>
<summary>Ответы</summary>

**200 OK** — Можно начать
```json
{
    "success": true,
    "message": "User ID валиден",
    "data": {
        "user_id": 123
    }
}
```

**409 Conflict** — Активная сессия существует
```json
{
    "success": false,
    "message": "У вас уже есть активная сессия онбординга.",
    "active_session_id": "uuid-session-id"
}
```

</details>

---

#### Отправка сообщения (синхронно)

```http
POST /api/onboarding/chat
Content-Type: application/json

{
    "user_id": 123,
    "message": "Привет, меня зовут Анна"
}
```

> **Примечание:** Пустое `message` или `null` начинает новую сессию с приветствием AI.

<details>
<summary>Ответ</summary>

```json
{
    "success": true,
    "data": {
        "message": "Привет, Анна! Рад познакомиться...",
        "completed": false,
        "session_id": "uuid-session-id"
    }
}
```

</details>

---

#### Отправка сообщения (асинхронно)

Для высоких нагрузок — сообщение отправляется в очередь.

```http
POST /api/onboarding/async/chat
Content-Type: application/json

{
    "user_id": 123,
    "message": "Расскажи о своих целях"
}
```

<details>
<summary>Ответ</summary>

```json
{
    "success": true,
    "data": {
        "session_id": "uuid-session-id",
        "status": "pending"
    }
}
```

</details>

---

#### Проверка статуса (для async)

```http
GET /api/onboarding/async/status?user_id=123&session_id=uuid
```

<details>
<summary>Ответы</summary>

**В обработке**
```json
{
    "success": true,
    "data": {
        "session_id": "uuid",
        "status": "processing",
        "message": null,
        "completed": false
    }
}
```

**Готово**
```json
{
    "success": true,
    "data": {
        "session_id": "uuid",
        "status": "completed",
        "message": "Ответ AI...",
        "completed": true
    }
}
```

</details>

---

#### Завершение онбординга

Создаёт суммаризацию на основе диалога.

```http
POST /api/onboarding/complete
Content-Type: application/json

{
    "user_id": 123,
    "session_id": "uuid-session-id"
}
```

<details>
<summary>Ответ</summary>

```json
{
    "success": true,
    "data": {
        "summary": {
            "health_goals": ["Улучшить сон", "Снизить стресс"],
            "current_health_issues": ["Бессонница"],
            "lifestyle": {
                "sleep": "5-6 часов",
                "activity": "Низкая",
                "nutrition": "Нерегулярное питание"
            },
            "recommendations_focus": ["Режим сна", "Медитация"]
        },
        "session_id": "uuid-session-id"
    }
}
```

</details>

---

#### Отмена сессии

```http
POST /api/onboarding/cancel
Content-Type: application/json

{
    "user_id": 123,
    "session_id": "uuid-session-id"  // опционально
}
```

---

#### История диалога

```http
GET /api/onboarding/history?user_id=123
```

---

### Суммаризации

#### Список суммаризаций

```http
GET /api/summaries?user_id=123&per_page=10
```

#### Получение суммаризации

```http
GET /api/summaries/{session_id}
```

---

## Архитектура

### Структура проекта

```
app/
├── Contracts/              # Интерфейсы
│   ├── AI/
│   │   └── AIClientInterface.php
│   └── Onboarding/
│       └── OnboardingServiceInterface.php
├── DTOs/                   # Data Transfer Objects
│   ├── AI/
│   │   ├── ChatRequestDTO.php
│   │   ├── ChatResponseDTO.php
│   │   └── ...
│   └── Onboarding/
│       └── OnboardingConfig.php
├── Enums/                  # Перечисления
│   ├── MessageRole.php
│   ├── MessageStatus.php
│   └── OnboardingStatus.php
├── Http/
│   ├── Controllers/Api/
│   │   ├── OnboardingChatController.php
│   │   ├── OnboardingAsyncController.php
│   │   └── SummaryController.php
│   └── Requests/           # Form Requests
├── Jobs/                   # Queue Jobs
│   ├── Concerns/
│   │   └── ProcessesOnboardingMessages.php
│   └── Onboarding/
│       ├── ProcessOnboardingStartJob.php
│       └── ProcessOnboardingMessageJob.php
├── Models/
│   ├── OnboardingSession.php
│   └── OnboardingMessage.php
└── Services/
    ├── AI/
    │   └── AnythingLLMClient.php
    └── Onboarding/
        └── OnboardingService.php
```

### Диаграмма потока

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Frontend  │────▶│   Controller     │────▶│    Service      │
│   (Vue.js)  │     │   (Laravel)      │     │  (Onboarding)   │
└─────────────┘     └──────────────────┘     └─────────────────┘
                                                      │
                    ┌──────────────────┐              │
                    │    Queue Job     │◀─────────────┤ async
                    │  (Background)    │              │
                    └──────────────────┘              │
                            │                         │
                            ▼                         ▼
                    ┌──────────────────┐     ┌─────────────────┐
                    │   AI Client      │────▶│   AnythingLLM   │
                    │  (Interface)     │     │   (External)    │
                    └──────────────────┘     └─────────────────┘
```

### Принципы проектирования

- **SOLID** — Dependency Injection, Interface Segregation
- **DRY** — Базовые классы и трейты для переиспользования
- **KISS** — Простая и понятная архитектура

---

## Тестирование

### Запуск тестов

```bash
# Все тесты
composer test

# Или напрямую
php artisan test

# С покрытием
php artisan test --coverage

# Конкретный тест
php artisan test --filter=OnboardingServiceTest
```

### Структура тестов

```
tests/
├── Feature/
│   ├── Api/
│   │   ├── OnboardingChatControllerTest.php
│   │   ├── OnboardingAsyncControllerTest.php
│   │   └── SummaryControllerTest.php
│   ├── Jobs/
│   │   └── ProcessOnboardingJobsTest.php
│   └── Services/
│       └── OnboardingServiceTest.php
└── Unit/
    ├── Enums/
    │   └── MessageStatusTest.php
    └── Models/
        ├── OnboardingMessageTest.php
        └── OnboardingSessionTest.php
```

---

## Полезные команды

| Команда | Описание |
|---------|----------|
| `composer dev` | Запуск всего стека для разработки |
| `composer test` | Запуск тестов |
| `composer setup` | Полная установка проекта |
| `php artisan queue:work` | Запуск обработчика очередей |
| `php artisan pail` | Real-time просмотр логов |
| `npm run dev` | Vite dev server с hot-reload |
| `npm run build` | Сборка фронтенда для продакшн |

---

## Переменные окружения

| Переменная | Описание | По умолчанию |
|------------|----------|--------------|
| `ANYTHINGLLM_API_URL` | URL AnythingLLM API | `http://localhost:3001` |
| `ANYTHINGLLM_API_KEY` | API ключ | — |
| `ANYTHINGLLM_WORKSPACE` | Воркспейс для чата | `holi-onboarding` |
| `ANYTHINGLLM_SUMMARY_WORKSPACE` | Воркспейс для суммаризации | `holi-summarization` |
| `ONBOARDING_QUEUE` | Название очереди | `onboarding` |
| `ONBOARDING_JOB_TRIES` | Попытки выполнения джоба | `3` |
| `ONBOARDING_JOB_BACKOFF` | Задержка между попытками (сек) | `10` |
| `ONBOARDING_SESSION_EXPIRY_HOURS` | Время жизни сессии (часы) | `24` |

---

## Технологии

<table>
<tr>
<td align="center" width="100">
<img src="https://laravel.com/img/logomark.min.svg" width="48" height="48" alt="Laravel" />
<br><b>Laravel 12</b>
</td>
<td align="center" width="100">
<img src="https://vuejs.org/images/logo.png" width="48" height="48" alt="Vue.js" />
<br><b>Vue.js 3</b>
</td>
<td align="center" width="100">
<img src="https://vitejs.dev/logo.svg" width="48" height="48" alt="Vite" />
<br><b>Vite 7</b>
</td>
<td align="center" width="100">
<img src="https://www.php.net/images/logos/php-logo.svg" width="48" height="48" alt="PHP" />
<br><b>PHP 8.2</b>
</td>
<td align="center" width="100">
<img src="https://tailwindcss.com/_next/static/media/tailwindcss-mark.3c5441fc7a190e907c2c.svg" width="48" height="48" alt="Tailwind" />
<br><b>Tailwind 4</b>
</td>
</tr>
</table>

---

## Лицензия

Этот проект распространяется под лицензией [MIT](LICENSE).

---

<div align="center">

**[⬆ Вернуться наверх](#holivita-ai)**

Made with ❤️ by Holivita Team

</div>
