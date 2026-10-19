<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Accessible inline SVG charts rendered server side (no JavaScript dependency).
 * Every chart is paired with a data table in the views for screen readers.
 */
final class Charts
{
    /**
     * Score evolution line chart (0 to 100).
     * @param list<array{label:string, value:float}> $points
     */
    public static function line(array $points, string $title): string
    {
        $w = 640;
        $h = 220;
        $pad = ['l' => 36, 'r' => 16, 't' => 16, 'b' => 30];
        $n = count($points);
        if ($n === 0) {
            return '';
        }
        $plotW = $w - $pad['l'] - $pad['r'];
        $plotH = $h - $pad['t'] - $pad['b'];
        $x = static fn (int $i) => $pad['l'] + ($n === 1 ? $plotW / 2 : $plotW * $i / ($n - 1));
        $y = static fn (float $v) => $pad['t'] + $plotH * (1 - max(0, min(100, $v)) / 100);

        $svg = '<svg class="chart chart-line" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '" preserveAspectRatio="xMidYMid meet">';
        foreach ([0, 25, 50, 75, 100] as $tick) {
            $ty = round($y($tick), 1);
            $svg .= '<line class="grid" x1="' . $pad['l'] . '" x2="' . ($w - $pad['r']) . '" y1="' . $ty . '" y2="' . $ty . '"/>';
            $svg .= '<text class="axis" x="' . ($pad['l'] - 8) . '" y="' . ($ty + 4) . '" text-anchor="end">' . $tick . '</text>';
        }
        $path = '';
        $area = '';
        foreach ($points as $i => $p) {
            $px = round($x($i), 1);
            $py = round($y((float) $p['value']), 1);
            $path .= ($i === 0 ? 'M' : 'L') . $px . ' ' . $py . ' ';
        }
        $area = $path . 'L' . round($x($n - 1), 1) . ' ' . ($pad['t'] + $plotH) . ' L' . round($x(0), 1) . ' ' . ($pad['t'] + $plotH) . ' Z';
        $svg .= '<path class="area" d="' . $area . '"/>';
        $svg .= '<path class="line" pathLength="1" d="' . trim($path) . '"/>';
        foreach ($points as $i => $p) {
            $px = round($x($i), 1);
            $py = round($y((float) $p['value']), 1);
            $svg .= '<circle class="dot" cx="' . $px . '" cy="' . $py . '" r="4"><title>' . e($p['label'] . ' : ' . round((float) $p['value'])) . '</title></circle>';
            if ($n <= 8 || $i % (int) ceil($n / 6) === 0 || $i === $n - 1) {
                $svg .= '<text class="axis" x="' . $px . '" y="' . ($h - 8) . '" text-anchor="middle">' . e($p['short'] ?? $p['label']) . '</text>';
            }
        }
        return $svg . '</svg>';
    }
}
