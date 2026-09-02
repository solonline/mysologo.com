<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $image_path
 * @property string $status
 * @property string $audience
 * @property bool $pinned
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_RETAIL = 'retail';

    public const AUDIENCE_CORPORATE = 'corporate';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    /** @var list<string> */
    public const AUDIENCES = [
        self::AUDIENCE_ALL,
        self::AUDIENCE_RETAIL,
        self::AUDIENCE_CORPORATE,
    ];

    protected $fillable = [
        'title',
        'excerpt',
        'body',
        'image_path',
        'status',
        'audience',
        'pinned',
        'published_at',
        'expires_at',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'audience' => self::AUDIENCE_ALL,
        'pinned' => false,
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return asset('storage/'.$this->image_path);
    }

    public function resolvedExcerpt(): string
    {
        if (is_string($this->excerpt) && trim($this->excerpt) !== '') {
            return trim($this->excerpt);
        }

        return self::excerptFrom($this->body);
    }

    public static function excerptFrom(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

        return Str::limit($text, 160);
    }

    /**
     * @param  Builder<News>  $query
     * @return Builder<News>
     */
    public function scopeVisibleTo(Builder $query, Customer $customer): Builder
    {
        $now = now();

        $query->where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '<=', $now)
            ->where(function (Builder $inner) use ($now): void {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            });

        if ($customer->type === 'platform') {
            return $query;
        }

        $audiences = [self::AUDIENCE_ALL];

        if (in_array($customer->type, ['corporate', 'responsible'], true)) {
            $audiences[] = self::AUDIENCE_CORPORATE;
        } elseif ($customer->type === 'retail') {
            $audiences[] = self::AUDIENCE_RETAIL;
        }

        return $query->whereIn('audience', $audiences);
    }
}
