<?php

namespace App\Services\Analysis;

/**
 * A link to a public dataset, portal, or record system.
 */
final readonly class ReferenceLink
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $description = null,
    ) {}

    /**
     * Build a link from model output or cached data, discarding anything malformed.
     */
    public static function fromArray(mixed $input): ?self
    {
        if (! is_array($input)) {
            return null;
        }

        $title = is_string($input['title'] ?? null) ? mb_substr(trim($input['title']), 0, 255) : '';
        $url = self::webUrl($input['url'] ?? null);
        $description = is_string($input['description'] ?? null) ? trim($input['description']) : '';

        if ($title === '' || $url === null || mb_strlen($url) > 500) {
            return null;
        }

        return new self($title, $url, $description === '' ? null : mb_substr($description, 0, 1000));
    }

    /**
     * Only keep web links, so model output can never smuggle in other schemes.
     */
    public static function webUrl(mixed $value): ?string
    {
        $url = is_string($value) ? trim($value) : '';

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /**
     * A comparison key so the same dataset isn't stored twice under cosmetic URL differences.
     */
    public static function key(string $url): string
    {
        $parts = parse_url(trim($url)) ?: [];
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return mb_substr($host.$path.$query, 0, 500);
    }

    /**
     * @return array{title: string, url: string, description: string|null}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'url' => $this->url, 'description' => $this->description];
    }
}
