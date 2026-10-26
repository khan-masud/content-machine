<?php

namespace App\Models;

use App\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $post_id
 * @property int|null $telegram_post_request_id
 * @property int|null $created_by_user_id
 * @property string $page_id
 * @property string $state
 * @property CarbonImmutable|null $scheduled_for
 * @property string|null $lease_id
 * @property CarbonImmutable|null $claimed_at
 * @property string|null $facebook_post_id
 * @property string|null $permalink
 * @property string|null $error
 * @property string|null $comment_error
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class FacebookPublication extends Model
{
    use BelongsToWorkspace;

    public const SCHEDULED = 'scheduled';

    public const QUEUED = 'queued';

    public const PUBLISHING = 'publishing';

    public const PUBLISHED = 'published';

    public const FAILED = 'failed';

    /** Facebook may or may not have the post: a person must check the Page. */
    public const UNCERTAIN = 'uncertain';

    public const CANCELLED = 'cancelled';

    /** @var list<string> */
    public const ACTIVE_STATES = [self::SCHEDULED, self::QUEUED, self::PUBLISHING, self::UNCERTAIN];

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'post_id',
        'telegram_post_request_id',
        'created_by_user_id',
        'page_id',
        'state',
        'scheduled_for',
        'lease_id',
        'claimed_at',
        'facebook_post_id',
        'permalink',
        'error',
        'comment_error',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'claimed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<TelegramPostRequest, $this> */
    public function telegramPostRequest(): BelongsTo
    {
        return $this->belongsTo(TelegramPostRequest::class);
    }
}
