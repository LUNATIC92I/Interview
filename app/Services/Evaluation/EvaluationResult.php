<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

final class EvaluationResult
{
    /**
     * @param list<array{code:string, evaluator:string, weight:int, ratio:float, points:float}> $criteria
     * @param list<array{key?:string, params?:array, text?:string}> $strengths
     * @param list<array{key?:string, params?:array, text?:string}> $gaps
     * @param list<int> $skillsDemonstrated
     * @param list<int> $missingElements indexes in the question's expected elements
     * @param list<array{key?:string, params?:array, text?:string}> $recommendations
     * @param array{key?:string, text?:string} $nextTip
     */
    public function __construct(
        public readonly int $score,
        public readonly array $criteria,
        public readonly array $strengths,
        public readonly array $gaps,
        public readonly array $skillsDemonstrated,
        public readonly array $missingElements,
        public readonly array $recommendations,
        public readonly array $nextTip,
        public readonly string $evaluator,
        public readonly string $confidence,
        public readonly ?string $modelName = null,
        public readonly ?string $llmFeedback = null,
    ) {
    }
}
