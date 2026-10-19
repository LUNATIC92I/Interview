<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Small dependency free PDF 1.4 writer for reports: A4 pages, Helvetica (WinAnsi encoding, so French
 * accents render correctly), word wrapping, automatic page breaks, coloured bars and page footers.
 */
final class PdfDocument
{
    private const W = 595.28;
    private const H = 841.89;
    private const MARGIN = 50.0;
    private const BOTTOM = 60.0;

    /** @var list<string> page content streams */
    private array $pages = [];
    private string $current = '';
    private float $y = 0;

    /** Helvetica and Helvetica-Bold advance widths (1/1000 em) for ASCII 32..126. */
    private const WIDTHS = [
        'F1' => [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584],
        'F2' => [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584],
    ];

    public function __construct(private readonly string $title, private readonly string $footer = '')
    {
        $this->newPage();
    }

    private function newPage(): void
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }
        $this->current = ' ';
        $this->y = self::H - self::MARGIN;
    }

    /** Convert UTF-8 to the single byte WinAnsi encoding used by the standard fonts. */
    private static function encode(string $text): string
    {
        $text = strtr($text, ["\u{2019}" => "'", "\u{2018}" => "'", "\u{201C}" => '"', "\u{201D}" => '"', "\u{2013}" => '-', "\u{2014}" => '-', "\u{202F}" => ' ', "\u{00A0}" => ' ', "\u{2022}" => '-', "\u{2026}" => '...']);
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        return $out === false ? (string) preg_replace('/[^\x20-\x7E]/', '?', $text) : $out;
    }

    private static function escape(string $encoded): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $encoded);
    }

    private function width(string $encoded, string $font, float $size): float
    {
        $w = 0;
        $table = self::WIDTHS[$font];
        $len = strlen($encoded);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($encoded[$i]);
            $w += ($c >= 32 && $c <= 126) ? $table[$c - 32] : ($c >= 192 && $c <= 221 ? 700 : 556);
        }
        return $w * $size / 1000;
    }

    /** @return list<string> encoded lines */
    private function wrap(string $text, string $font, float $size, float $maxWidth): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [''] as $paragraph) {
            $words = preg_split('/\s+/u', trim($paragraph)) ?: [];
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($this->width(self::encode($candidate), $font, $size) <= $maxWidth || $line === '') {
                    $line = $candidate;
                } else {
                    $lines[] = self::encode($line);
                    $line = $word;
                }
            }
            $lines[] = self::encode($line);
        }
        return $lines;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y - $height < self::BOTTOM) {
            $this->newPage();
        }
    }

    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return sprintf('%.3F %.3F %.3F', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255);
    }

    public function text(string $text, float $size = 10, bool $bold = false, string $color = '172B3A', float $indent = 0, float $spacing = 1.35): self
    {
        $font = $bold ? 'F2' : 'F1';
        $lineHeight = $size * $spacing;
        foreach ($this->wrap($text, $font, $size, self::W - 2 * self::MARGIN - $indent) as $line) {
            $this->ensureSpace($lineHeight);
            $this->y -= $lineHeight;
            $this->current .= sprintf("BT /%s %.1F Tf %s rg %.2F %.2F Td (%s) Tj ET\n", $font, $size, self::rgb($color), self::MARGIN + $indent, $this->y, self::escape($line));
        }
        return $this;
    }

    public function heading(string $text, float $size = 14): self
    {
        $this->ensureSpace($size * 3);
        $this->space(6);
        return $this->text($text, $size, true, '0D8B87');
    }

    public function space(float $height = 8): self
    {
        $this->y -= $height;
        return $this;
    }

    public function rule(string $color = 'E2E8F0'): self
    {
        $this->ensureSpace(10);
        $this->y -= 6;
        $this->current .= sprintf("%s RG 0.8 w %.2F %.2F m %.2F %.2F l S\n", self::rgb($color), self::MARGIN, $this->y, self::W - self::MARGIN, $this->y);
        $this->y -= 6;
        return $this;
    }

    public function bullets(array $items, float $size = 10, string $color = '172B3A'): self
    {
        foreach ($items as $item) {
            $this->text('- ' . $item, $size, false, $color, 8);
        }
        return $this;
    }

    /** Labelled horizontal bar for a 0-100 score (value printed as text, not conveyed by colour only). */
    public function bar(string $label, float $score, string $suffix = '/100'): self
    {
        $this->ensureSpace(26);
        $this->y -= 14;
        $this->current .= sprintf("BT /F1 9.5 Tf %s rg %.2F %.2F Td (%s) Tj ET\n", self::rgb('172B3A'), self::MARGIN, $this->y, self::escape(self::encode($label)));
        $value = self::encode(format_number($score) . ' ' . $suffix);
        $this->current .= sprintf("BT /F2 9.5 Tf %s rg %.2F %.2F Td (%s) Tj ET\n", self::rgb('172B3A'), self::W - self::MARGIN - $this->width($value, 'F2', 9.5), $this->y, self::escape($value));
        $this->y -= 9;
        $full = self::W - 2 * self::MARGIN;
        $color = $score >= 75 ? '198754' : ($score >= 50 ? 'D97706' : 'DC3545');
        $this->current .= sprintf("%s rg %.2F %.2F %.2F 5 re f\n", self::rgb('E5FAF8'), self::MARGIN, $this->y, $full);
        $this->current .= sprintf("%s rg %.2F %.2F %.2F 5 re f\n", self::rgb($color), self::MARGIN, $this->y, $full * max(0, min(100, $score)) / 100);
        $this->y -= 3;
        return $this;
    }

    /** Large score badge used on the first page. */
    public function scoreBox(string $label, float $score, string $band): self
    {
        $this->ensureSpace(80);
        $this->y -= 70;
        $this->current .= sprintf("%s rg %.2F %.2F %.2F 64 re f\n", self::rgb('E5FAF8'), self::MARGIN, $this->y, self::W - 2 * self::MARGIN);
        $this->current .= sprintf("BT /F2 30 Tf %s rg %.2F %.2F Td (%s) Tj ET\n", self::rgb('0D8B87'), self::MARGIN + 16, $this->y + 22, self::escape(self::encode(format_number($score) . '/100')));
        $this->current .= sprintf("BT /F2 11 Tf %s rg %.2F %.2F Td (%s) Tj ET\n", self::rgb('172B3A'), self::MARGIN + 190, $this->y + 36, self::escape(self::encode($label)));
        $this->current .= sprintf("BT /F1 10 Tf %s rg %.2F %.2F Td (%s) Tj ET\n", self::rgb('64748B'), self::MARGIN + 190, $this->y + 20, self::escape(self::encode($band)));
        return $this;
    }

    public function output(): string
    {
        $pages = $this->pages;
        $pages[] = $this->current;
        $count = count($pages);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $fontF1 = 3;
        $fontF2 = 4;
        $objects[$fontF1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontF2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $next = 5;
        foreach ($pages as $i => $content) {
            $footer = self::encode(($this->footer !== '' ? $this->footer . '   ' : '') . ($i + 1) . ' / ' . $count);
            $content .= sprintf("%s RG 0.5 w %.2F 40 m %.2F 40 l S\n", self::rgb('E2E8F0'), self::MARGIN, self::W - self::MARGIN);
            $content .= sprintf("BT /F1 8 Tf %s rg %.2F 28 Td (%s) Tj ET\n", self::rgb('64748B'), self::MARGIN, self::escape($footer));
            $streamId = $next++;
            $pageId = $next++;
            $objects[$streamId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>', self::W, self::H, $fontF1, $fontF2, $streamId);
            $kids[] = $pageId . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $count . ' >>';
        $infoId = $next;
        $objects[$infoId] = '<< /Title (' . self::escape(self::encode($this->title)) . ') /Producer (InterviewPro) /CreationDate (D:' . gmdate('YmdHis') . "Z) >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($objects as $id => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R /Info $infoId 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }
}
