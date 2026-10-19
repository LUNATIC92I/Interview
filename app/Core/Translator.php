<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralised translations. Interface strings live in lang/{locale}.php; administrators may override
 * individual keys from the back office (table translation_overrides).
 * A missing key falls back to the other language, then to an explicit marker, never to an empty string.
 */
final class Translator
{
    private static string $locale = 'fr';
    /** @var array<string, array<string, string>> */
    private static array $lines = [];
    private static bool $overridesLoaded = false;

    public static function setLocale(string $locale): void
    {
        self::$locale = in_array($locale, self::locales(), true) ? $locale : (string) Config::get('app.locale', 'fr');
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** @return list<string> */
    public static function locales(): array
    {
        return Config::get('app.locales', ['fr', 'en']);
    }

    public static function other(string $locale): string
    {
        return $locale === 'fr' ? 'en' : 'fr';
    }

    /** @return array<string, string> flattened dotted keys */
    public static function lines(string $locale): array
    {
        if (!isset(self::$lines[$locale])) {
            $file = BASE_PATH . '/lang/' . $locale . '.php';
            self::$lines[$locale] = is_file($file) ? self::flatten(require $file) : [];
        }
        self::loadOverrides();
        return self::$lines[$locale];
    }

    private static function loadOverrides(): void
    {
        if (self::$overridesLoaded) {
            return;
        }
        self::$overridesLoaded = true;
        try {
            foreach (Database::fetchAll('SELECT locale, translation_key, value FROM translation_overrides') as $row) {
                if (!isset(self::$lines[$row['locale']])) {
                    $file = BASE_PATH . '/lang/' . $row['locale'] . '.php';
                    self::$lines[$row['locale']] = is_file($file) ? self::flatten(require $file) : [];
                }
                self::$lines[$row['locale']][$row['translation_key']] = $row['value'];
            }
        } catch (\Throwable) {
            // Database not available or not migrated yet: file translations still work.
        }
    }

    public static function reset(): void
    {
        self::$lines = [];
        self::$overridesLoaded = false;
    }

    public static function has(string $key, ?string $locale = null): bool
    {
        return isset(self::lines($locale ?? self::$locale)[$key]);
    }

    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::$locale;
        $lines = self::lines($locale);
        $line = $lines[$key] ?? self::lines(self::other($locale))[$key] ?? null;
        if ($line === null) {
            return '[' . $key . ']';
        }
        foreach ($replace as $name => $value) {
            $line = str_replace(':' . $name, (string) $value, $line);
        }
        return $line;
    }

    /** @return array<string, string> */
    public static function flatten(array $array, string $prefix = ''): array
    {
        $out = [];
        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $out += self::flatten($value, $full);
            } else {
                $out[$full] = (string) $value;
            }
        }
        return $out;
    }
}
