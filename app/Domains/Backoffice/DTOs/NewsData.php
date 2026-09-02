<?php

namespace App\Domains\Backoffice\DTOs;

use App\Domains\Backoffice\Requests\StoreNewsRequest;
use App\Domains\Backoffice\Requests\UpdateNewsRequest;
use App\Models\News;
use App\Support\NewsHtmlSanitizer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;

readonly class NewsData
{
    public function __construct(
        public string $title,
        public ?string $excerpt,
        public string $body,
        public string $status,
        public string $audience,
        public bool $pinned,
        public ?CarbonInterface $publishedAt,
        public ?CarbonInterface $expiresAt,
        public ?UploadedFile $image,
        public bool $removeImage,
        public ?int $createdBy,
    ) {}

    public static function fromRequest(StoreNewsRequest|UpdateNewsRequest $request, ?int $createdBy = null): self
    {
        $validated = $request->validated();
        $body = app(NewsHtmlSanitizer::class)->sanitize((string) $validated['body']);
        $excerpt = trim((string) ($validated['excerpt'] ?? ''));

        if ($excerpt === '') {
            $excerpt = News::excerptFrom($body);
        }

        $status = (string) $validated['status'];
        $publishedAt = ! empty($validated['published_at'])
            ? Carbon::parse($validated['published_at'])
            : null;

        if ($status === News::STATUS_PUBLISHED && $publishedAt === null) {
            $publishedAt = now();
        }

        $expiresAt = ! empty($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : null;

        return new self(
            title: (string) $validated['title'],
            excerpt: $excerpt !== '' ? $excerpt : null,
            body: $body,
            status: $status,
            audience: (string) $validated['audience'],
            pinned: $request->boolean('pinned'),
            publishedAt: $publishedAt,
            expiresAt: $expiresAt,
            image: $request->file('image'),
            removeImage: $request->boolean('remove_image'),
            createdBy: $createdBy,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        $attributes = [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'status' => $this->status,
            'audience' => $this->audience,
            'pinned' => $this->pinned,
            'published_at' => $this->publishedAt,
            'expires_at' => $this->expiresAt,
        ];

        if ($this->createdBy !== null) {
            $attributes['created_by'] = $this->createdBy;
        }

        return $attributes;
    }
}
