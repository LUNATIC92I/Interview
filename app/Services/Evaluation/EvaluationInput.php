<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

/**
 * Everything an evaluator needs about one answer. Contains no personal data about the candidate.
 */
final class EvaluationInput
{
    /**
     * @param list<array{label:string, keywords:list<string>}> $expectedElements
     * @param list<string> $keywords
     * @param list<array{code:string, evaluator:string, weight:int, name:string}> $criteria
     * @param list<int> $skillIds
     */
    public function __construct(
        public readonly string $answer,
        public readonly string $locale,
        public readonly string $questionType,
        public readonly string $questionText,
        public readonly array $expectedElements,
        public readonly array $keywords,
        public readonly array $criteria,
        public readonly array $skillIds = [],
        public readonly int $recommendedSeconds = 180,
        public readonly string $professionName = '',
        public readonly string $levelLabel = '',
    ) {
    }
}
