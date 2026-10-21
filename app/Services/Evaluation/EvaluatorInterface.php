<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

interface EvaluatorInterface
{
    public function name(): string;

    /** @throws EvaluationUnavailableException when the evaluator cannot produce a genuine result */
    public function evaluate(EvaluationInput $input): EvaluationResult;
}
