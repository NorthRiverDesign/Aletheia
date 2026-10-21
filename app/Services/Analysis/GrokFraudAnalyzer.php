<?php

namespace App\Services\Analysis;

use App\Models\Target;
use App\Models\Vector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs analyses on Grok through xAI's Responses API, optionally with its server-side web search.
 *
 * Requests are stateless (`store: false`): nothing is kept on xAI's side, and a
 * follow-up replays the conversation instead of referencing a stored response.
 */
class GrokFraudAnalyzer implements FraudAnalyzer
{
    /**
     * Shared by every analysis so requests reach the server holding the cached system prompt.
     */
    private const PROMPT_CACHE_KEY = 'aletheia-analysis';

    /**
     * Ticks per US dollar in `usage.cost_in_usd_ticks`.
     */
    private const TICKS_PER_USD = 10_000_000_000;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.x.ai/v1',
        private readonly ?string $reasoningEffort = null,
        private readonly int $maxOutputTokens = 16000,
        private readonly int $maxTurns = 8,
        private readonly bool $webSearch = true,
        private readonly int $timeout = 400,
    ) {}

    public function analyze(Vector $vector, Target $target, References $references): AnalysisResult
    {
        if (blank($this->apiKey)) {
            throw new AnalysisFailed('XAI_API_KEY is not set.');
        }

        $instructions = AnalysisPrompt::system($references->global);
        $input = [['role' => 'user', 'content' => AnalysisPrompt::user($vector, $target, $references->target, $this->webSearch)]];
        $usage = ['input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0, 'cost_usd' => 0.0];

        // The second request only happens when the model answers in prose instead of calling the tool.
        foreach ([$this->webSearch, false] as $webSearch) {
            $data = $this->send($instructions, $input, $webSearch);
            $usage = $this->addUsage($usage, $data);
            $model = is_string($data['model'] ?? null) ? $data['model'] : $this->model;

            $arguments = $this->toolArguments($data);

            if ($arguments !== null) {
                return AnalysisResult::fromToolInput($arguments, $model, $usage);
            }

            if (($data['status'] ?? null) === 'incomplete') {
                $reason = data_get($data, 'incomplete_details.reason');

                throw new AnalysisFailed('Grok stopped before recording findings'.(is_string($reason) ? " ({$reason})" : '').'. Raise XAI_MAX_OUTPUT_TOKENS or XAI_MAX_TURNS.');
            }

            $text = $this->outputText($data);

            if ($text !== '') {
                $input[] = ['role' => 'assistant', 'content' => $text];
            }

            $input[] = ['role' => 'user', 'content' => 'Based on your research so far, record your results now by calling the '.AnalysisPrompt::TOOL_NAME.' tool.'];
        }

        throw new AnalysisFailed('Grok did not record any findings.');
    }

    /**
     * @param  list<array{role: string, content: string}>  $input
     * @return array<mixed>
     */
    private function send(string $instructions, array $input, bool $webSearch): array
    {
        $tools = [[
            'type' => 'function',
            'name' => AnalysisPrompt::TOOL_NAME,
            'description' => 'Record the overall assessment, the scored leads for manual review, and new reference sources. Call this once, at the end.',
            'parameters' => AnalysisPrompt::findingsSchema(),
        ]];

        if ($webSearch) {
            $tools[] = ['type' => 'web_search'];
        }

        $payload = array_filter([
            'model' => $this->model,
            'instructions' => $instructions,
            'input' => $input,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'parallel_tool_calls' => false,
            'max_output_tokens' => $this->maxOutputTokens,
            'max_turns' => $webSearch && $this->maxTurns > 0 ? $this->maxTurns : null,
            'reasoning' => filled($this->reasoningEffort) ? ['effort' => $this->reasoningEffort] : null,
            'prompt_cache_key' => self::PROMPT_CACHE_KEY,
            'store' => false,
        ], fn (mixed $value): bool => $value !== null);

        try {
            $response = $this->http
                ->baseUrl($this->baseUrl)
                ->withToken((string) $this->apiKey)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout($this->timeout)
                ->retry(2, 2000, fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())), throw: false)
                ->post('/responses', $payload);
        } catch (ConnectionException $e) {
            throw new AnalysisFailed('Could not reach the xAI API: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $message = $this->errorMessage($response);

            // xAI answers a bad key with a 400 rather than a 401.
            if (in_array($response->status(), [401, 403], true) || Str::contains($message, 'API key', ignoreCase: true)) {
                throw new AnalysisFailed('xAI rejected the credentials. Check XAI_API_KEY. ('.$message.')');
            }

            throw new AnalysisFailed('The xAI API request failed ('.$response->status().'): '.$message);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new AnalysisFailed('The xAI API returned an unreadable response.');
        }

        return $data;
    }

    /**
     * Arguments of the first record_findings call, if the model made one.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>|null
     */
    private function toolArguments(array $data): ?array
    {
        foreach ($this->outputItems($data) as $item) {
            if (($item['type'] ?? null) !== 'function_call' || ($item['name'] ?? null) !== AnalysisPrompt::TOOL_NAME) {
                continue;
            }

            $arguments = $item['arguments'] ?? null;
            $arguments = is_string($arguments) ? json_decode($arguments, true) : $arguments;

            if (! is_array($arguments)) {
                throw new AnalysisFailed('Grok called '.AnalysisPrompt::TOOL_NAME.' with malformed arguments.');
            }

            return $arguments;
        }

        return null;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function outputText(array $data): string
    {
        $text = [];

        foreach ($this->outputItems($data) as $item) {
            if (($item['type'] ?? null) !== 'message' || ! is_array($item['content'] ?? null)) {
                continue;
            }

            foreach ($item['content'] as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'output_text' && is_string($part['text'] ?? null)) {
                    $text[] = $part['text'];
                }
            }
        }

        return trim(implode("\n\n", $text));
    }

    /**
     * @param  array<mixed>  $data
     * @return list<array<mixed>>
     */
    private function outputItems(array $data): array
    {
        $output = is_array($data['output'] ?? null) ? $data['output'] : [];

        return array_values(array_filter($output, is_array(...)));
    }

    /**
     * Accumulate usage, accepting both the documented Responses field names and the Chat Completions ones.
     *
     * @param  array{input_tokens: int, cached_input_tokens: int, output_tokens: int, web_searches: int, cost_usd: float}  $usage
     * @param  array<mixed>  $data
     * @return array{input_tokens: int, cached_input_tokens: int, output_tokens: int, web_searches: int, cost_usd: float}
     */
    private function addUsage(array $usage, array $data): array
    {
        $int = fn (string ...$paths): int => (int) collect($paths)
            ->map(fn (string $path): mixed => data_get($data, $path))
            ->first(fn (mixed $value): bool => is_numeric($value), 0);

        $usage['input_tokens'] += $int('usage.input_tokens', 'usage.prompt_tokens');
        $usage['cached_input_tokens'] += $int('usage.input_tokens_details.cached_tokens', 'usage.prompt_tokens_details.cached_tokens');
        $usage['output_tokens'] += $int('usage.output_tokens', 'usage.completion_tokens');
        $usage['web_searches'] += $int('usage.server_side_tool_usage_details.web_search_calls');
        $usage['cost_usd'] = round($usage['cost_usd'] + $int('usage.cost_in_usd_ticks') / self::TICKS_PER_USD, 6);

        return $usage;
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message') ?? $response->json('error') ?? $response->json('message');

        return Str::limit(is_string($message) ? $message : $response->body(), 500);
    }
}
