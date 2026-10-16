<?php

namespace App\Support\Blog;

use Illuminate\Support\Carbon;

/**
 * A marketing blog post. Posts have no database row — the catalog is the
 * static list in {@see BlogPosts}, and each post's body is its own hand-coded
 * Inertia page under `resources/js/pages/blog/posts/{slug}.tsx`, matching how
 * the landing page itself is a static React page rather than DB-driven
 * content.
 */
final readonly class BlogPost
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $excerpt,
        public string $metaDescription,
        public Carbon $publishedAt,
        public ?string $heroImage = null,
    ) {}

    /** @return array{slug: string, title: string, excerpt: string, publishedAt: string, publishedAtLabel: string, heroImage: ?string} */
    public function toSummaryArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'publishedAt' => $this->publishedAt->toIso8601String(),
            'publishedAtLabel' => $this->publishedAtLabel(),
            'heroImage' => $this->heroImage,
        ];
    }

    /** @return array{slug: string, title: string, metaDescription: string, publishedAt: string, publishedAtLabel: string, heroImage: ?string} */
    public function toMetaArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'metaDescription' => $this->metaDescription,
            'publishedAt' => $this->publishedAt->toIso8601String(),
            'publishedAtLabel' => $this->publishedAtLabel(),
            'heroImage' => $this->heroImage,
        ];
    }

    private function publishedAtLabel(): string
    {
        return $this->publishedAt->translatedFormat('d \d\e F \d\e Y');
    }
}
