<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds the downloadable PDF version of a post interview report in the active interface language.
 */
final class ReportPdf
{
    public static function render(array $r): string
    {
        $m = $r['meta'];
        $pdf = new PdfDocument(t('report.pdf_title') . ' - ' . $m['profession'], 'InterviewPro - ' . t('report.pdf_footer'));
        $pdf->text('InterviewPro', 11, true, '0D8B87')
            ->text(t('report.pdf_title'), 20, true)
            ->space(4)
            ->text($m['profession'] . ' - ' . $m['type'], 12, true, '172B3A')
            ->space(6);
        foreach ([
            t('report.meta.date') => $m['date'],
            t('report.meta.mode') => $m['mode'],
            t('report.meta.level') => $m['level'],
            t('report.meta.language') => $m['language'],
            t('report.meta.difficulty') => $m['difficulty'],
            t('report.meta.duration') => format_duration((int) $r['elapsed']),
            t('report.meta.answered') => $r['answered'] . ' / ' . count($r['answers']),
        ] as $label => $value) {
            $pdf->text($label . ' : ' . $value, 9.5, false, '64748B', 0, 1.25);
        }
        $pdf->scoreBox(t('report.overall_score'), (float) $r['score'], score_label((float) $r['score']));
        $pdf->space(6)->text(t('report.disclaimer'), 8.5, false, '64748B');

        if ($r['criteria']) {
            $pdf->heading(t('report.by_criterion'));
            foreach ($r['criteria'] as $c) {
                $pdf->bar($c['name'], (float) $c['score']);
            }
        }
        if ($r['skills']) {
            $pdf->heading(t('report.by_skill'));
            foreach ($r['skills'] as $s) {
                $pdf->bar($s['name'], (float) $s['score']);
            }
        }
        foreach (['strengths' => 'report.strengths', 'improvements' => 'report.improvements', 'priorities' => 'report.priorities'] as $key => $label) {
            if ($r[$key]) {
                $pdf->heading(t($label), 12)->bullets($r[$key]);
            }
        }

        $pdf->heading(t('report.answers'));
        foreach ($r['answers'] as $a) {
            $pdf->rule();
            $prefix = t('report.question_n', ['n' => $a['position']]) . ($a['is_followup'] ? ' (' . t('interview.followup_label') . ')' : '');
            $pdf->text($prefix . ' - ' . t('question_types.' . ($a['type'] ?: 'hr')), 9, true, '0D8B87')
                ->text($a['question'], 10.5, true);
            $f = $a['feedback'];
            $status = t('answer_status.' . $a['status']);
            $pdf->text($status . ' - ' . format_duration($a['time_spent']) . ($f ? ' - ' . t('report.score_short') . ' ' . format_number($f['score']) . '/100 (' . $f['score_label'] . ')' : ''), 9, false, '64748B');
            if ($a['answer'] !== '') {
                $pdf->space(2)->text(t('report.your_answer'), 9, true)->text($a['answer'], 9.5, false, '172B3A', 6);
            }
            if ($f) {
                if ($f['summary'] !== '') {
                    $pdf->text($f['summary'], 9.5, false, '172B3A', 6);
                }
                if ($f['strengths']) {
                    $pdf->text(t('report.strengths'), 9, true, '198754')->bullets($f['strengths'], 9);
                }
                if ($f['gaps']) {
                    $pdf->text(t('report.gaps'), 9, true, 'D97706')->bullets($f['gaps'], 9);
                }
                if ($f['missing_elements']) {
                    $pdf->text(t('report.missing'), 9, true)->bullets($f['missing_elements'], 9);
                }
                if ($f['next_tip'] !== '') {
                    $pdf->text(t('report.next_tip') . ' : ' . $f['next_tip'], 9, false, '0D8B87');
                }
                $pdf->text($f['evaluator_label'] . ' - ' . t('report.confidence') . ' : ' . $f['confidence_label'], 8, false, '64748B');
            }
        }
        return $pdf->output();
    }
}
