<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use App\Core\Logger;

/**
 * Optional evaluator backed by an external language model, called server side only.
 *
 * - The API key is read from the server environment and never sent to the browser.
 * - The model returns one ratio per rubric criterion; the score is computed here with the
 *   documented weights, so scoring stays consistent with the rule based engine.
 * - Any failure (timeout, HTTP error, refusal, malformed output) raises EvaluationUnavailableException:
 *   the caller then falls back to the rule based evaluator and says so. No analysis is ever invented.
 */
final class LlmEvaluator implements EvaluatorInterface
{
    /** @var callable(string $url, array $headers, string $body, int $timeout): array{status:int, body:string} */
    private $transport;

    public function __construct(
        private readonly string $provider,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeout = 45,
        private readonly string $baseUrl = '',
        private readonly string $effort = 'medium',
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? [self::class, 'curlTransport'];
    }

    public function name(): string
    {
        return 'llm';
    }

    public function evaluate(EvaluationInput $input): EvaluationResult
    {
        if (trim($input->answer) === '') {
            throw new EvaluationUnavailableException('Empty answer is scored by the rule engine');
        }
        $data = $this->provider === 'anthropic' ? $this->callAnthropic($input) : $this->callOpenAiCompatible($input);
        return $this->toResult($data, $input);
    }

    private function systemPrompt(string $locale): string
    {
        $language = $locale === 'fr' ? 'French' : 'English';
        return <<<TXT
You assess written answers given during a job interview practice session. Your feedback helps the candidate improve; it is indicative and not a hiring decision.
Judge only the content of the answer against the question, the expected elements and the rubric criteria provided.
Never infer or comment on personality, origin, gender, age, religion, health or any other personal characteristic, and do not claim to detect truthfulness or confidence.
When a good answer depends on context or on the recruiter's judgement, say so instead of presenting one answer as objectively correct.
Write every text field in {$language}, in a professional and encouraging tone, with concrete advice.
Return only the JSON object described by the schema.
TXT;
    }

    private function userPrompt(EvaluationInput $input): string
    {
        $elements = [];
        foreach ($input->expectedElements as $i => $el) {
            $elements[] = $i . ': ' . $el['label'];
        }
        $criteria = [];
        foreach ($input->criteria as $c) {
            $criteria[] = sprintf('%s (%s, weight %d)', $c['code'], $c['name'] ?? $c['evaluator'], $c['weight']);
        }
        return json_encode([
            'profession' => $input->professionName,
            'experience_level' => $input->levelLabel,
            'question_type' => $input->questionType,
            'question' => $input->questionText,
            'expected_elements' => $elements,
            'rubric_criteria' => $criteria,
            'candidate_answer' => mb_substr($input->answer, 0, 8000),
            'instructions' => 'For each rubric criterion give a ratio between 0 and 1. List the indexes of expected elements that are missing. Give 1 to 4 strengths, 1 to 4 gaps, 2 to 4 recommendations, one tip for the next attempt, a two sentence summary and your confidence (low, medium or high).',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function schema(array $criteriaCodes): array
    {
        $criteriaProps = [];
        foreach ($criteriaCodes as $code) {
            $criteriaProps[$code] = ['type' => 'number'];
        }
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['criteria', 'missing_elements', 'strengths', 'gaps', 'recommendations', 'next_tip', 'summary', 'confidence'],
            'properties' => [
                'criteria' => ['type' => 'object', 'additionalProperties' => false, 'required' => $criteriaCodes, 'properties' => $criteriaProps],
                'missing_elements' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'strengths' => $strings,
                'gaps' => $strings,
                'recommendations' => $strings,
                'next_tip' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
            ],
        ];
    }

    private function callAnthropic(EvaluationInput $input): array
    {
        $body = [
            'model' => $this->model,
            'max_tokens' => 16000,
            'system' => $this->systemPrompt($input->locale),
            'messages' => [['role' => 'user', 'content' => $this->userPrompt($input)]],
            'output_config' => [
                'effort' => $this->effort,
                'format' => ['type' => 'json_schema', 'schema' => $this->schema(array_column($input->criteria, 'code'))],
            ],
            // Server side fallback when a safety classifier declines the request.
            'fallbacks' => 'default',
        ];
        $response = $this->send(
            rtrim($this->baseUrl !== '' ? $this->baseUrl : 'https://api.anthropic.com', '/') . '/v1/messages',
            [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'anthropic-beta: server-side-fallback-2026-07-01',
            ],
            $body
        );
        if (($response['stop_reason'] ?? '') === 'refusal') {
            throw new EvaluationUnavailableException('Model declined the request');
        }
        if (($response['stop_reason'] ?? '') === 'max_tokens') {
            throw new EvaluationUnavailableException('Model output truncated');
        }
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return $this->decode((string) $block['text']);
            }
        }
        throw new EvaluationUnavailableException('No text block in model response');
    }

    private function callOpenAiCompatible(EvaluationInput $input): array
    {
        if ($this->baseUrl === '') {
            throw new EvaluationUnavailableException('AI_BASE_URL is required for openai_compatible');
        }
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt($input->locale) . "\nJSON schema:\n" . json_encode($this->schema(array_column($input->criteria, 'code')))],
                ['role' => 'user', 'content' => $this->userPrompt($input)],
            ],
            'response_format' => ['type' => 'json_object'],
        ];
        $response = $this->send(rtrim($this->baseUrl, '/') . '/chat/completions', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ], $body);
        $text = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($text)) {
            throw new EvaluationUnavailableException('Unexpected response shape');
        }
        return $this->decode($text);
    }

    private function send(string $url, array $headers, array $body): array
    {
        $result = ($this->transport)($url, $headers, (string) json_encode($body, JSON_UNESCAPED_UNICODE), $this->timeout);
        if ($result['status'] < 200 || $result['status'] >= 300) {
            Logger::error('LLM evaluation HTTP error', ['status' => $result['status'], 'provider' => $this->provider]);
            throw new EvaluationUnavailableException('HTTP ' . $result['status']);
        }
        $decoded = json_decode($result['body'], true);
        if (!is_array($decoded)) {
            throw new EvaluationUnavailableException('Invalid JSON from provider');
        }
        return $decoded;
    }

    private function decode(string $text): array
    {
        $text = trim($text);
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $text = $m[0];
        }
        $data = json_decode($text, true);
        if (!is_array($data) || !isset($data['criteria']) || !is_array($data['criteria'])) {
            throw new EvaluationUnavailableException('Malformed evaluation payload');
        }
        return $data;
    }

    private function toResult(array $data, EvaluationInput $input): EvaluationResult
    {
        $criteria = [];
        $total = 0.0;
        foreach ($input->criteria as $c) {
            if (!isset($data['criteria'][$c['code']]) || !is_numeric($data['criteria'][$c['code']])) {
                throw new EvaluationUnavailableException('Missing criterion ' . $c['code']);
            }
            $ratio = round(max(0.0, min(1.0, (float) $data['criteria'][$c['code']])), 3);
            $points = round($c['weight'] * $ratio, 1);
            $total += $points;
            $criteria[] = ['code' => $c['code'], 'evaluator' => $c['evaluator'], 'weight' => (int) $c['weight'], 'ratio' => $ratio, 'points' => $points];
        }
        $texts = static fn ($list) => array_values(array_map(
            static fn ($s) => ['text' => mb_substr((string) $s, 0, 600)],
            array_slice(array_filter(is_array($list) ? $list : [], 'is_string'), 0, 5)
        ));
        $missing = array_values(array_filter(
            array_map('intval', is_array($data['missing_elements'] ?? null) ? $data['missing_elements'] : []),
            static fn ($i) => $i >= 0 && $i < count($input->expectedElements)
        ));
        $score = (int) round(min(100, $total));
        $confidence = in_array($data['confidence'] ?? '', ['low', 'medium', 'high'], true) ? $data['confidence'] : 'medium';
        return new EvaluationResult(
            score: $score,
            criteria: $criteria,
            strengths: $texts($data['strengths'] ?? []),
            gaps: $texts($data['gaps'] ?? []),
            skillsDemonstrated: $score >= 60 ? $input->skillIds : [],
            missingElements: $missing,
            recommendations: $texts($data['recommendations'] ?? []),
            nextTip: ['text' => mb_substr((string) ($data['next_tip'] ?? ''), 0, 600)],
            evaluator: 'llm',
            confidence: $confidence,
            modelName: $this->model,
            llmFeedback: mb_substr((string) ($data['summary'] ?? ''), 0, 2000),
        );
    }

    /** @return array{status:int, body:string} */
    public static function curlTransport(string $url, array $headers, string $body, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            throw new EvaluationUnavailableException('ext-curl is not installed');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new EvaluationUnavailableException('Transport error: ' . $error);
        }
        return ['status' => $status, 'body' => (string) $response];
    }
}
