<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Minimal assertion base class (no external dependency so the suite runs on any PHP host).
 */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUpClass(): void
    {
    }

    protected function assertTrue(mixed $value, string $message = 'Expected true'): void
    {
        $this->assertions++;
        if ($value !== true) {
            throw new AssertionFailed($message);
        }
    }

    protected function assertFalse(mixed $value, string $message = 'Expected false'): void
    {
        $this->assertTrue($value === false, $message);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed(($message ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new AssertionFailed(($message ? $message . ': ' : '') . 'missing "' . $needle . '"');
        }
    }

    protected function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            throw new AssertionFailed(($message ? $message . ': ' : '') . 'unexpected "' . $needle . '"');
        }
    }

    protected function assertGreaterThan(float|int $limit, float|int $actual, string $message = ''): void
    {
        $this->assertTrue($actual > $limit, ($message ? $message . ': ' : '') . "expected > $limit, got $actual");
    }

    protected function assertLessThan(float|int $limit, float|int $actual, string $message = ''): void
    {
        $this->assertTrue($actual < $limit, ($message ? $message . ': ' : '') . "expected < $limit, got $actual");
    }

    protected function expectException(string $class, callable $fn, string $message = ''): void
    {
        $this->assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $class) {
                return;
            }
            throw new AssertionFailed("Expected $class, got " . $e::class . ': ' . $e->getMessage());
        }
        throw new AssertionFailed($message ?: "Expected exception $class");
    }
}
