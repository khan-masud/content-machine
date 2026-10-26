<?php

namespace App\Support\Posts;

/**
 * Read-only view over config/post_categories.php. Every caller that needs to
 * know which categories exist, how to label them, or how to recognise one in
 * a Telegram command goes through here instead of reading the config array.
 */
final class PostCategories
{
    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::all());
    }

    /**
     * Canonical key for a stored or submitted value, or null when it is not a
     * known category.
     */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $key = strtolower(trim($value));

        return self::exists($key) ? $key : null;
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }

    public static function tone(string $key): string
    {
        return self::all()[$key]['tone'] ?? '';
    }

    /**
     * Default photo-card look for a category, before workspace overrides.
     *
     * @return array{theme: string, accent: string}|null
     */
    public static function style(string $key): ?array
    {
        $category = self::all()[$key] ?? null;

        return $category === null ? null : ['theme' => $category['theme'], 'accent' => $category['accent']];
    }

    /**
     * Category key for a word typed by a person (key or alias, any case).
     */
    public static function fromAlias(string $word): ?string
    {
        $word = mb_strtolower(trim($word));

        if ($word === '') {
            return null;
        }

        foreach (self::all() as $key => $category) {
            if ($word === $key || in_array($word, array_map('mb_strtolower', $category['aliases']), true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Split a leading category word off a command argument:
     * "sarcasm https://example.com" => ['sarcasm', 'https://example.com'].
     * Text that does not start with a category comes back unchanged.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function splitLeading(?string $text): array
    {
        if ($text === null) {
            return [null, null];
        }

        $text = trim($text);
        $parts = preg_split('/\s+/u', $text, 2) ?: [];
        $category = isset($parts[0]) ? self::fromAlias($parts[0]) : null;

        if ($category === null) {
            return [null, $text];
        }

        return [$category, trim($parts[1] ?? '')];
    }

    /**
     * @return list<array{key: string, label: string, label_bn: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $key => $category) {
            $options[] = [
                'key' => $key,
                'label' => $category['label'],
                'label_bn' => $category['label_bn'],
            ];
        }

        return $options;
    }

    /**
     * @return array<string, array{label: string, label_bn: string, aliases: list<string>, tone: string, theme: string, accent: string}>
     */
    private static function all(): array
    {
        /** @var array<string, array{label: string, label_bn: string, aliases: list<string>, tone: string, theme: string, accent: string}> $categories */
        $categories = config('post_categories.categories', []);

        return $categories;
    }
}
