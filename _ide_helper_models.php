<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * Модель сообщения онбординга.
 *
 * @property int $id
 * @property string $session_id
 * @property MessageRole $role
 * @property string $content
 * @property MessageStatus $status
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property-read \App\Models\OnboardingSession $session
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage assistant()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage inProgress()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage processing()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage user()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingMessage whereStatus($value)
 */
	class OnboardingMessage extends \Eloquent {}
}

namespace App\Models{
/**
 * Модель сессии онбординга.
 *
 * @property string $id
 * @property int $user_id
 * @property OnboardingStatus $status
 * @property array|null $summary_json
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static Builder|OnboardingSession forUser(int $userId)
 * @method static Builder|OnboardingSession inProgress()
 * @method static Builder|OnboardingSession completed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnboardingMessage> $messages
 * @property-read int|null $messages_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession cancelled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession expired()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession stale(int $hours = 24)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession whereSummaryJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnboardingSession whereUserId($value)
 */
	class OnboardingSession extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

