<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Evaluation\EvaluationInput;
use App\Services\Evaluation\EvaluationUnavailableException;
use App\Services\Evaluation\LlmEvaluator;
use App\Services\Evaluation\RuleBasedEvaluator;
use App\Services\Evaluation\TextAnalyzer;
use Tests\Support\TestCase;

final class EvaluationTest extends TestCase
{
    private function criteria(): array
    {
        return [
            ['code' => 'relevance', 'evaluator' => 'relevance', 'weight' => 25, 'name' => 'Relevance'],
            ['code' => 'structure', 'evaluator' => 'structure', 'weight' => 20, 'name' => 'Structure'],
            ['code' => 'actions', 'evaluator' => 'actions', 'weight' => 25, 'name' => 'Actions'],
            ['code' => 'results', 'evaluator' => 'results', 'weight' => 20, 'name' => 'Results'],
            ['code' => 'reflection', 'evaluator' => 'reflection', 'weight' => 10, 'name' => 'Reflection'],
        ];
    }

    private function input(string $answer, string $locale = 'en'): EvaluationInput
    {
        return new EvaluationInput(
            answer: $answer,
            locale: $locale,
            questionType: 'behavioral',
            questionText: 'Describe a disagreement with a colleague.',
            expectedElements: [
                ['label' => 'Context', 'keywords' => ['disagree', 'conflict', 'desaccord']],
                ['label' => 'Listening', 'keywords' => ['listen', 'ecoute']],
                ['label' => 'Solution', 'keywords' => ['compromise', 'solution', 'compromis']],
                ['label' => 'Outcome', 'keywords' => ['result', 'delivered', 'resultat']],
            ],
            keywords: ['disagree', 'listen', 'compromise'],
            criteria: $this->criteria(),
            skillIds: [1, 2],
            recommendedSeconds: 210,
        );
    }

    private const STRONG = "Situation: a colleague and I disagreed about shipping without acceptance tests. My task was to protect quality without blocking the team. First, I proposed a one to one meeting and I listened to his constraints. Then I suggested a compromise: we tested the three critical journeys first. As a result, we delivered on time with zero blocking incidents and reduced rework by 30 %. I learned to look for the shared goal before defending my position.";

    public function testEmptyAnswerScoresZero(): void
    {
        $r = (new RuleBasedEvaluator())->evaluate($this->input(''));
        $this->assertSame(0, $r->score);
        $this->assertSame('eval.gap.empty', $r->gaps[0]['key']);
    }

    public function testWeightsDefinePoints(): void
    {
        $r = (new RuleBasedEvaluator())->evaluate($this->input(self::STRONG));
        $sum = array_sum(array_column($r->criteria, 'points'));
        $this->assertTrue(abs($sum - $r->score) <= 1, 'score equals the sum of criterion points');
        foreach ($r->criteria as $c) {
            $this->assertTrue($c['points'] <= $c['weight'], 'points never exceed weight');
        }
    }

    public function testStrongStarAnswerBeatsWeakAnswer(): void
    {
        $ev = new RuleBasedEvaluator();
        $strong = $ev->evaluate($this->input(self::STRONG));
        $weak = $ev->evaluate($this->input('We had a problem once and it was fine in the end, the team handled it.'));
        $this->assertGreaterThan(70, $strong->score, 'strong STAR answer');
        $this->assertLessThan(35, $weak->score, 'weak answer');
        $this->assertSame([], $strong->missingElements, 'all expected elements detected');
        $this->assertSame([1, 2], $strong->skillsDemonstrated);
    }

    public function testDeterministic(): void
    {
        $ev = new RuleBasedEvaluator();
        $this->assertSame($ev->evaluate($this->input(self::STRONG))->score, $ev->evaluate($this->input(self::STRONG))->score);
    }

    public function testShortAnswersAreCappedAndLowConfidence(): void
    {
        $r = (new RuleBasedEvaluator())->evaluate($this->input('I listened, found a compromise, result delivered.'));
        $this->assertLessThan(50, $r->score);
        $this->assertSame('low', $r->confidence);
        $this->assertSame('rules', $r->evaluator);
    }

    public function testFrenchAccentInsensitiveMatching(): void
    {
        $t = new TextAnalyzer("J'ai écouté le désaccord et trouvé un compromis. Résultat : livré.");
        $this->assertTrue($t->has('ecoute'));
        $this->assertTrue($t->has('désaccord'));
        $this->assertTrue($t->has("j'ai"));
        $this->assertFalse($t->has('je='), 'exact word marker');
        $this->assertSame(10, $t->wordCount);
    }

    public function testFeedbackIsTranslationKeys(): void
    {
        $r = (new RuleBasedEvaluator())->evaluate($this->input('We had a problem and the team solved it quickly together.'));
        foreach (array_merge($r->strengths, $r->gaps, $r->recommendations) as $item) {
            $this->assertTrue(isset($item['key']) && str_starts_with($item['key'], 'eval.'), 'feedback item is a key');
            $this->assertNotContains('[eval', t($item['key'], $item['params'] ?? []), 'key exists');
        }
    }

    public function testLlmEvaluatorComputesScoreFromWeights(): void
    {
        $transport = static function (string $url, array $headers, string $body, int $timeout): array {
            $sent = json_decode($body, true);
            assert($sent['output_config']['format']['type'] === 'json_schema');
            $payload = ['criteria' => ['relevance' => 1, 'structure' => 0.5, 'actions' => 1, 'results' => 0.5, 'reflection' => 0], 'missing_elements' => [3, 99],
                'strengths' => ['Clear'], 'gaps' => ['No lesson'], 'recommendations' => ['Add a lesson'], 'next_tip' => 'Practise', 'summary' => 'Good.', 'confidence' => 'high'];
            return ['status' => 200, 'body' => json_encode(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => json_encode($payload)]]])];
        };
        $ev = new LlmEvaluator('anthropic', 'key', 'claude-opus-5-5', 5, '', 'medium', $transport);
        $r = $ev->evaluate($this->input(self::STRONG));
        $this->assertSame(25 + 10 + 25 + 10, $r->score);
        $this->assertSame([3], $r->missingElements, 'out of range indexes dropped');
        $this->assertSame('llm', $r->evaluator);
        $this->assertSame('Clear', $r->strengths[0]['text']);
    }

    public function testLlmFailuresNeverInventAnalysis(): void
    {
        $cases = [
            static fn () => ['status' => 500, 'body' => '{}'],
            static fn () => ['status' => 200, 'body' => json_encode(['stop_reason' => 'refusal', 'content' => []])],
            static fn () => ['status' => 200, 'body' => json_encode(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'not json']]])],
            static fn () => ['status' => 200, 'body' => json_encode(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => '{"criteria":{"relevance":1}}']]])],
            static function () { throw new EvaluationUnavailableException('timeout'); },
        ];
        foreach ($cases as $transport) {
            $ev = new LlmEvaluator('anthropic', 'key', 'm', 5, '', 'medium', $transport);
            $this->expectException(EvaluationUnavailableException::class, fn () => $ev->evaluate($this->input(self::STRONG)));
        }
    }
}
