<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

/**
 * Language aware text features used by the rule based evaluator.
 * Matching is accent and case insensitive; single word terms match as prefixes ("authentif" matches
 * "authentification" and "authentication"), multi word terms match as phrases.
 */
final class TextAnalyzer
{
    public readonly string $normalized;
    /** @var list<string> */
    public readonly array $tokens;
    public readonly int $wordCount;
    /** @var list<string> */
    public readonly array $sentences;
    public readonly int $paragraphs;

    public function __construct(public readonly string $text)
    {
        $this->normalized = self::normalize($text);
        preg_match_all('/[a-z0-9]+(?:[\'’-][a-z0-9]+)*/u', $this->normalized, $m);
        $this->tokens = $m[0];
        $this->wordCount = count($this->tokens);
        $sentences = preg_split('/(?<=[.!?;])\s+|\n+/u', trim($text)) ?: [];
        $this->sentences = array_values(array_filter(array_map('trim', $sentences), static fn ($s) => mb_strlen($s) > 2));
        $this->paragraphs = max(1, count(array_filter(preg_split('/\n\s*\n|\n(?=\s*(?:[-*•]|\d+[.)]))/u', trim($text)) ?: [])));
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = (string) preg_replace('/\p{Mn}+/u', '', $text);
        } else {
            $text = strtr($text, [
                'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
                'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', 'ñ' => 'n',
            ]);
        }
        $text = str_replace(['œ', 'æ', '’'], ['oe', 'ae', "'"], $text);
        return (string) preg_replace('/\s+/u', ' ', $text);
    }

    /** True when the term is present. */
    public function has(string $term): bool
    {
        $term = trim(self::normalize($term));
        if ($term === '') {
            return false;
        }
        if (str_ends_with($term, '=')) {
            return in_array(substr($term, 0, -1), $this->tokens, true);
        }
        if (str_contains($term, ' ') || str_contains($term, "'")) {
            return (bool) preg_match('/(?<![a-z0-9])' . preg_quote($term, '/') . '/u', $this->normalized);
        }
        foreach ($this->tokens as $token) {
            if (str_starts_with($token, $term)) {
                return true;
            }
        }
        return false;
    }

    /** Number of distinct terms of the list found in the text. */
    public function countTerms(array $terms): int
    {
        $n = 0;
        foreach (array_unique($terms) as $term) {
            if ($this->has((string) $term)) {
                $n++;
            }
        }
        return $n;
    }

    /** Total occurrences of the terms (used for density signals). */
    public function occurrences(array $terms): int
    {
        $total = 0;
        foreach (array_unique($terms) as $term) {
            $term = trim(self::normalize((string) $term));
            if ($term === '') {
                continue;
            }
            if (str_ends_with($term, '=')) {
                $total += count(array_keys($this->tokens, substr($term, 0, -1), true));
                continue;
            }
            if (str_contains($term, ' ') || str_contains($term, "'")) {
                $total += preg_match_all('/(?<![a-z0-9])' . preg_quote($term, '/') . '/u', $this->normalized);
            } else {
                foreach ($this->tokens as $token) {
                    if (str_starts_with($token, $term)) {
                        $total++;
                    }
                }
            }
        }
        return $total;
    }

    public function numberCount(): int
    {
        return preg_match_all('/\d+(?:[.,]\d+)?\s*(?:%|k|m|€|\$|eur|usd|fcfa|xof|jours?|days?|heures?|hours?|semaines?|weeks?|mois|months?|ans?|years?|minutes?)?/u', $this->normalized);
    }

    public function averageSentenceLength(): float
    {
        $n = count($this->sentences);
        return $n === 0 ? 0.0 : $this->wordCount / $n;
    }

    public function hasEnumeration(): bool
    {
        return (bool) preg_match('/(^|\n)\s*(?:[-*•]|\d+[.)])\s+/u', $this->text);
    }

    /** Ratio of the most repeated meaningful token: high values hint at filler or copy-paste. */
    public function repetitionRatio(): float
    {
        $long = array_filter($this->tokens, static fn ($t) => mb_strlen($t) > 4);
        if (count($long) < 10) {
            return 0.0;
        }
        $counts = array_count_values($long);
        return max($counts) / count($long);
    }
}
