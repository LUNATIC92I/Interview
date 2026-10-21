<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PdfDocument;
use Tests\Support\TestCase;

final class PdfTest extends TestCase
{
    public function testGeneratesValidMultiPagePdf(): void
    {
        $pdf = new PdfDocument('Rapport d\'entretien', 'Pied de page');
        $pdf->heading('Évaluation générale')->scoreBox('Score global', 78, 'Bon');
        for ($i = 0; $i < 60; $i++) {
            $pdf->text("Paragraphe $i : réponse détaillée avec des caractères accentués (é, è, à, ç) et des parenthèses.");
            $pdf->bar('Critère ' . $i, $i % 100);
        }
        $out = $pdf->output();
        $this->assertTrue(str_starts_with($out, '%PDF-1.4'));
        $this->assertTrue(str_ends_with(rtrim($out), '%%EOF'));
        $this->assertGreaterThan(2, substr_count($out, '/Type /Page '), 'several pages');
        // Cross reference offsets must point to the objects.
        preg_match('/startxref\n(\d+)/', $out, $m);
        $this->assertSame('xref', substr($out, (int) $m[1], 4));
        preg_match_all('/^(\d{10}) 00000 n $/m', $out, $offsets);
        foreach ($offsets[1] as $i => $offset) {
            $this->assertSame(($i + 1) . ' 0 obj', substr($out, (int) $offset, strlen(($i + 1) . ' 0 obj')));
        }
        // WinAnsi encoding of accented characters and escaped parentheses.
        $this->assertContains("\xE9", $out);
        $this->assertContains('\\(', $out);
    }
}
