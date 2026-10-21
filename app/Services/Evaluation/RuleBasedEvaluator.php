<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

/**
 * Deterministic, documented scoring engine.
 *
 * Each rubric criterion names an "evaluator" method below that returns a ratio between 0 and 1.
 * Points = weight x ratio, and the answer score is the sum of points (0 to 100).
 * The engine only looks at observable properties of the written answer: coverage of the
 * expected elements defined for the question, structure, concrete actions, evidence of results,
 * reasoning markers and length. It never infers personality, origin, gender, age, religion
 * or any other personal characteristic, and it does not claim to detect truthfulness.
 *
 * Feedback is stored as translation keys so a report can be read in French or English.
 */
final class RuleBasedEvaluator implements EvaluatorInterface
{
    public const VERSION = 'rules-1.0';

    public function name(): string
    {
        return 'rules';
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        $text = new TextAnalyzer($input->answer);
        $loc = $input->locale;

        $elements = $this->elementCoverage($text, $input->expectedElements);
        $context = [
            'text' => $text,
            'locale' => $loc,
            'input' => $input,
            'elements' => $elements,
            'targetWords' => $this->targetWords($input->recommendedSeconds),
        ];

        $criteria = [];
        $total = 0.0;
        $lengthFactor = $this->lengthFactor($text->wordCount);
        foreach ($input->criteria as $criterion) {
            $method = 'criterion' . str_replace('_', '', ucwords($criterion['evaluator'], '_'));
            $ratio = method_exists($this, $method) ? $this->$method($context) : 0.0;
            $ratio = round(max(0.0, min(1.0, $ratio * $lengthFactor)), 3);
            $points = round($criterion['weight'] * $ratio, 1);
            $total += $points;
            $criteria[] = [
                'code' => $criterion['code'],
                'evaluator' => $criterion['evaluator'],
                'weight' => (int) $criterion['weight'],
                'ratio' => $ratio,
                'points' => $points,
            ];
        }
        $score = (int) round(min(100, $total));

        return new EvaluationResult(
            score: $score,
            criteria: $criteria,
            strengths: $this->strengths($criteria, $text, $elements),
            gaps: $this->gaps($criteria, $text),
            skillsDemonstrated: $score >= 60 ? $input->skillIds : array_slice($input->skillIds, 0, $score >= 45 ? 1 : 0),
            missingElements: $elements['missing'],
            recommendations: $this->recommendations($criteria, $text, $input),
            nextTip: $this->nextTip($criteria),
            evaluator: $this->name(),
            confidence: $text->wordCount < 40 ? 'low' : 'medium',
            modelName: self::VERSION,
        );
    }

    /** Expected number of written words for the recommended speaking time, bounded. */
    private function targetWords(int $seconds): int
    {
        return (int) max(60, min(260, round($seconds / 2.2)));
    }

    /** Very short answers cannot reach high scores, whatever keywords they contain. */
    private function lengthFactor(int $words): float
    {
        return match (true) {
            $words === 0 => 0.0,
            $words < 8 => 0.15,
            $words < 20 => 0.45,
            $words < 35 => 0.75,
            $words < 50 => 0.9,
            default => 1.0,
        };
    }

    /**
     * @param list<array{label:string, keywords:list<string>}> $expected
     * @return array{ratio: float, covered: list<int>, missing: list<int>}
     */
    private function elementCoverage(TextAnalyzer $text, array $expected): array
    {
        if ($expected === []) {
            return ['ratio' => 0.5, 'covered' => [], 'missing' => []];
        }
        $covered = $missing = [];
        foreach ($expected as $i => $element) {
            $keywords = $element['keywords'] ?? [];
            $hit = $keywords !== [] && $text->countTerms($keywords) > 0;
            $hit ? $covered[] = $i : $missing[] = $i;
        }
        return ['ratio' => count($covered) / count($expected), 'covered' => $covered, 'missing' => $missing];
    }

    private function keywordRatio(array $c): float
    {
        $keywords = $c['input']->keywords;
        if ($keywords === []) {
            return $c['elements']['ratio'];
        }
        $found = $c['text']->countTerms($keywords);
        return min(1.0, $found / max(2, min(6, (int) ceil(count($keywords) * 0.5))));
    }

    private function markerRatio(array $c, string $group, int $wanted): float
    {
        return min(1.0, $c['text']->countTerms(Lexicon::get($group, $c['locale'])) / max(1, $wanted));
    }

    // Criteria evaluators. Each returns a ratio in [0, 1].

    private function criterionRelevance(array $c): float
    {
        return 0.65 * $c['elements']['ratio'] + 0.35 * $this->keywordRatio($c);
    }

    private function criterionAccuracy(array $c): float
    {
        // Technical exactness: coverage of the expected technical elements, keywords as secondary signal.
        $ratio = 0.8 * $c['elements']['ratio'] + 0.2 * $this->keywordRatio($c);
        return $ratio;
    }

    private function criterionStructure(array $c): float
    {
        /** @var TextAnalyzer $t */
        $t = $c['text'];
        $score = 0.0;
        $sentences = count($t->sentences);
        $score += $sentences >= 4 ? 0.3 : ($sentences >= 2 ? 0.15 : 0.0);
        $score += min(0.3, 0.1 * $t->countTerms(Lexicon::get('connectors', $c['locale'])));
        $avg = $t->averageSentenceLength();
        $score += ($avg >= 8 && $avg <= 32) ? 0.2 : 0.05;
        $score += ($t->paragraphs > 1 || $t->hasEnumeration()) ? 0.1 : 0.0;
        if ($c['input']->questionType === 'behavioral') {
            $score += min(0.1, 0.025 * $t->countTerms(Lexicon::get('star', $c['locale'])));
        } else {
            $score += 0.1 * min(1.0, $t->wordCount / max(1, $c['targetWords']));
        }
        return $score;
    }

    private function criterionActions(array $c): float
    {
        $t = $c['text'];
        $actions = $t->countTerms(Lexicon::get('actions', $c['locale']));
        $personal = $t->occurrences(Lexicon::get('personal', $c['locale']));
        $collective = $t->occurrences(Lexicon::get('collective', $c['locale']));
        $ratio = min(1.0, $actions / 3) * 0.7;
        $ratio += $personal > 0 ? min(0.3, 0.3 * $personal / max(1, $personal + $collective) + 0.05 * min(2, $personal)) : 0.0;
        return $ratio;
    }

    private function criterionResults(array $c): float
    {
        $t = $c['text'];
        $markers = $t->countTerms(Lexicon::get('results', $c['locale']));
        $numbers = $t->numberCount();
        return min(1.0, 0.25 * $markers + 0.25 * min(2, $numbers));
    }

    private function criterionReflection(array $c): float
    {
        return $this->markerRatio($c, 'reflection', 2);
    }

    private function criterionReasoning(array $c): float
    {
        $t = $c['text'];
        $ratio = 0.7 * $this->markerRatio($c, 'reasoning', 4);
        $ratio += 0.3 * $c['elements']['ratio'];
        return $ratio;
    }

    private function criterionMethod(array $c): float
    {
        $t = $c['text'];
        $ratio = 0.6 * $this->markerRatio($c, 'sequence', 3);
        $ratio += $t->hasEnumeration() || $t->paragraphs > 2 ? 0.2 : 0.0;
        $ratio += 0.2 * $c['elements']['ratio'];
        return $ratio;
    }

    private function criterionCommunication(array $c): float
    {
        $t = $c['text'];
        $target = $c['targetWords'];
        $lengthRatio = $t->wordCount / max(1, $target);
        $length = match (true) {
            $lengthRatio < 0.3 => 0.2,
            $lengthRatio < 0.6 => 0.6,
            $lengthRatio <= 2.2 => 1.0,
            $lengthRatio <= 3.5 => 0.7,
            default => 0.4,
        };
        $avg = $t->averageSentenceLength();
        $readability = ($avg >= 6 && $avg <= 30) ? 1.0 : ($avg <= 45 ? 0.6 : 0.3);
        $penalty = min(0.3, 0.1 * $t->countTerms(Lexicon::get('filler', $c['locale'])) + ($t->repetitionRatio() > 0.15 ? 0.15 : 0));
        return 0.55 * $length + 0.45 * $readability - $penalty;
    }

    private function criterionLeadership(array $c): float
    {
        return 0.75 * $this->markerRatio($c, 'leadership', 5) + 0.25 * $c['elements']['ratio'];
    }

    private function criterionEthics(array $c): float
    {
        return 0.6 * $this->markerRatio($c, 'ethics', 4) + 0.4 * $c['elements']['ratio'];
    }

    private function criterionMotivationFit(array $c): float
    {
        return 0.6 * $this->markerRatio($c, 'motivation', 4) + 0.4 * $c['elements']['ratio'];
    }

    private function criterionNegotiation(array $c): float
    {
        return 0.6 * $this->markerRatio($c, 'negotiation', 4) + 0.4 * $c['elements']['ratio'];
    }

    // Feedback builders (translation keys with parameters).

    private function strengths(array $criteria, TextAnalyzer $text, array $elements): array
    {
        $out = [];
        foreach ($criteria as $cr) {
            if ($cr['ratio'] >= 0.7) {
                $out[] = ['key' => 'eval.strength.' . $cr['evaluator']];
            }
        }
        if (count($elements['covered']) >= 3) {
            $out[] = ['key' => 'eval.strength.elements', 'params' => ['count' => count($elements['covered'])]];
        }
        if ($text->numberCount() >= 2 && !in_array('eval.strength.results', array_column($out, 'key'), true)) {
            $out[] = ['key' => 'eval.strength.figures'];
        }
        return array_slice($out, 0, 5);
    }

    private function gaps(array $criteria, TextAnalyzer $text): array
    {
        $out = [];
        if ($text->wordCount === 0) {
            return [['key' => 'eval.gap.empty']];
        }
        if ($text->wordCount < 35) {
            $out[] = ['key' => 'eval.gap.too_short', 'params' => ['words' => $text->wordCount]];
        }
        foreach ($criteria as $cr) {
            if ($cr['ratio'] < 0.45) {
                $out[] = ['key' => 'eval.gap.' . $cr['evaluator']];
            }
        }
        return array_slice($out, 0, 5);
    }

    private function recommendations(array $criteria, TextAnalyzer $text, EvaluationInput $input): array
    {
        $sorted = $criteria;
        usort($sorted, static fn ($a, $b) => ($a['ratio'] * 100 - $b['ratio'] * 100) <=> 0 ?: $b['weight'] <=> $a['weight']);
        $out = [];
        foreach (array_slice($sorted, 0, 3) as $cr) {
            if ($cr['ratio'] < 0.85) {
                $out[] = ['key' => 'eval.reco.' . $cr['evaluator']];
            }
        }
        if ($input->questionType === 'behavioral' && $text->countTerms(Lexicon::get('results', $input->locale)) === 0) {
            $out[] = ['key' => 'eval.reco.star'];
        }
        if ($out === []) {
            $out[] = ['key' => 'eval.reco.keep_going'];
        }
        return array_values(array_unique($out, SORT_REGULAR));
    }

    private function nextTip(array $criteria): array
    {
        if ($criteria === []) {
            return ['key' => 'eval.tip.generic'];
        }
        $lowest = $criteria[0];
        foreach ($criteria as $cr) {
            if ($cr['ratio'] * $cr['weight'] - $cr['weight'] < $lowest['ratio'] * $lowest['weight'] - $lowest['weight']) {
                $lowest = $cr;
            }
        }
        return ['key' => 'eval.tip.' . $lowest['evaluator']];
    }
}
