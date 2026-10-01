<?php

declare(strict_types=1);

namespace JOOservices\CrawlerXNews\Adapters\Concerns;

/**
 * Maps the shared VietNamNet newsapi payload (used by vietnamnet.vn and
 * ictnews.vietnamnet.vn) into article fields.
 */
trait VietnamnetApiMapper
{
    /**
     * @param  array<mixed, mixed>  $payload
     * @return array{title: ?string, intro: ?string, content_html: ?string, content_text: ?string, published_at: ?string, updated_at: ?string, canonical_url: ?string, image: ?string, language: ?string, authors: list<string>, categories: list<string>, tags: list<string>}
     */
    protected function mapVietnamnetPayload(array $payload): array
    {
        $model = is_array($payload['data'] ?? null) && is_array($payload['data']['model'] ?? null)
            ? $payload['data']['model']
            : [];

        $contentHtml = $this->nullableString($model['content'] ?? null);
        $contentText = null;
        if ($contentHtml !== null) {
            $contentText = $this->normalizeText(strip_tags($contentHtml));
        }

        $authors = [];
        foreach ((array) ($model['nickNames'] ?? []) as $nick) {
            if (is_array($nick)) {
                $name = $this->nullableString($nick['name'] ?? null);
                if ($name !== null) {
                    $authors[] = $name;
                }
            }
        }

        $categories = [];
        $category = is_array($model['category'] ?? null) ? $model['category'] : [];
        $categoryName = $this->nullableString($category['name'] ?? null);
        if ($categoryName !== null) {
            $categories[] = $categoryName;
        }

        $tags = [];
        foreach ((array) ($model['tagObjects'] ?? []) as $tag) {
            if (is_array($tag)) {
                $name = $this->nullableString($tag['title'] ?? null);
                if ($name !== null) {
                    $tags[] = $name;
                }
            }
        }

        $canonicalUrl = $this->nullableString($model['detailUrl'] ?? null);
        if ($canonicalUrl !== null && ! str_starts_with($canonicalUrl, 'http')) {
            $canonicalUrl = $this->absoluteUrl($canonicalUrl, 'https://vietnamnet.vn');
        }

        return [
            'title' => $this->nullableString($model['title'] ?? null),
            'intro' => $this->nullableString($model['descriptionPlainText'] ?? $model['description'] ?? null),
            'content_html' => $contentHtml,
            'content_text' => $contentText,
            'published_at' => $this->nullableString($model['publishDate'] ?? null),
            'updated_at' => $this->nullableString($model['updatedDate'] ?? null),
            'canonical_url' => $canonicalUrl,
            'image' => $this->normalizeVietnamnetImage($model['avatarUrl'] ?? null),
            'language' => 'vi',
            'authors' => array_values(array_unique($authors)),
            'categories' => array_values(array_unique($categories)),
            'tags' => array_values(array_unique($tags)),
        ];
    }

    private function normalizeVietnamnetImage(mixed $value): ?string
    {
        $image = $this->nullableString($value);
        if ($image === null) {
            return null;
        }

        if (str_starts_with($image, '$VNN_CDN_KEY#')) {
            $image = 'https://static-images.vnncdn.net' . substr($image, strlen('$VNN_CDN_KEY#'));
        }

        return $image;
    }
}
