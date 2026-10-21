<?php

namespace App\Services\Analysis;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaMessageParam;
use Anthropic\Beta\Messages\BetaOutputConfig\Effort;
use Anthropic\Beta\Messages\BetaTool;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Beta\Messages\BetaWebFetchTool20260209;
use Anthropic\Beta\Messages\BetaWebSearchTool20260209;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\AuthenticationException;
use App\Models\Finding;
use App\Models\Target;
use App\Models\Vector;

/**
 * Runs analyses and fact checks on Claude, optionally letting it search the web for public records.
 */
class ClaudeFraudAnalyzer implements FindingVerifier, FraudAnalyzer
{
    /**
     * Server-side tool loops can pause; this bounds how often a paused turn is resumed.
     */
    private const MAX_REQUESTS = 4;

    /**
     * Caps how much of one fetched page or PDF reaches the model during a fact check.
     */
    private const WEB_FETCH_MAX_CONTENT_TOKENS = 20000;

    public function __construct(
        private readonly Client $client,
        private readonly string $model,
        private readonly string $effort = 'high',
        private readonly int $maxTokens = 16000,
        private readonly int $webSearchMaxUses = 5,
        private readonly int $webFetchMaxUses = 10,
        private readonly bool $fallbacks = true,
    ) {}

    public function analyze(Vector $vector, Target $target, References $references): AnalysisResult
    {
        $webSearch = $this->webSearchMaxUses > 0;

        [$input, $model, $usage] = $this->callTool(
            system: AnalysisPrompt::system($references->global),
            prompt: AnalysisPrompt::user($vector, $target, $references->target, $webSearch),
            tool: BetaTool::with(
                inputSchema: AnalysisPrompt::findingsSchema(),
                name: AnalysisPrompt::TOOL_NAME,
                description: 'Record the overall assessment and the scored leads for manual review. Call this once, at the end.',
                strict: true,
            ),
            webFetch: false,
            declined: 'The model declined to analyze this pairing.',
            output: 'any findings',
        );

        return AnalysisResult::fromToolInput($input, $model, $usage);
    }

    public function verify(Finding $finding, References $references): VerificationResult
    {
        $webAccess = $this->webSearchMaxUses > 0 || $this->webFetchMaxUses > 0;

        [$input, $model, $usage] = $this->callTool(
            system: VerificationPrompt::system($references->global),
            prompt: VerificationPrompt::user($finding, $references->target, $webAccess),
            tool: BetaTool::with(
                inputSchema: VerificationPrompt::verificationSchema(),
                name: VerificationPrompt::TOOL_NAME,
                description: 'Record the verdict, the claims checked, and the errors found in the lead. Call this once, at the end.',
                strict: true,
            ),
            webFetch: true,
            declined: 'The model declined to fact-check this finding.',
            output: 'a verdict',
        );

        return VerificationResult::fromToolInput($input, $model, $usage);
    }

    /**
     * Run the conversation until the model calls the given tool, and return its input, the model, and usage.
     *
     * @return array{0: array<mixed>, 1: string, 2: array<string, int>}
     */
    private function callTool(string $system, string $prompt, BetaTool $tool, bool $webFetch, string $declined, string $output): array
    {
        $webFetch = $webFetch && $this->webFetchMaxUses > 0;
        $messages = [BetaMessageParam::with(content: $prompt, role: 'user')];
        $usage = ['input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0];

        for ($request = 1; $request <= self::MAX_REQUESTS; $request++) {
            $response = $this->send($system, $messages, $tool, $webFetch);

            $usage['input_tokens'] += $response->usage->inputTokens
                + ($response->usage->cacheReadInputTokens ?? 0)
                + ($response->usage->cacheCreationInputTokens ?? 0);
            $usage['cached_input_tokens'] += $response->usage->cacheReadInputTokens ?? 0;
            $usage['output_tokens'] += $response->usage->outputTokens;
            $usage['web_searches'] += $response->usage->serverToolUse->webSearchRequests ?? 0;

            if ($webFetch) {
                $usage['web_fetches'] = ($usage['web_fetches'] ?? 0) + ($response->usage->serverToolUse->webFetchRequests ?? 0);
            }

            if ($response->stopReason === 'refusal') {
                throw new AnalysisFailed($declined);
            }

            foreach ($response->content as $block) {
                if ($block instanceof BetaToolUseBlock && $block->name === $tool->name) {
                    return [$block->input, $response->model, $usage];
                }
            }

            if ($response->stopReason === 'max_tokens') {
                throw new AnalysisFailed("The model ran out of output tokens before recording {$output}. Raise ANTHROPIC_MAX_TOKENS.");
            }

            // Resume a paused server-side tool loop as-is; otherwise ask once more for the tool call.
            $messages[] = BetaMessageParam::fromArray(['role' => 'assistant', 'content' => $response->content]);

            if ($response->stopReason !== 'pause_turn') {
                $messages[] = BetaMessageParam::with(content: 'Record your results now by calling the '.$tool->name.' tool.', role: 'user');
            }
        }

        throw new AnalysisFailed("The model did not record {$output}.");
    }

    /**
     * @param  list<BetaMessageParam>  $messages
     */
    private function send(string $system, array $messages, BetaTool $tool, bool $webFetch): BetaMessage
    {
        $tools = [$tool];

        if ($this->webSearchMaxUses > 0) {
            $tools[] = BetaWebSearchTool20260209::with(maxUses: $this->webSearchMaxUses);
        }

        if ($webFetch) {
            $tools[] = BetaWebFetchTool20260209::with(maxContentTokens: self::WEB_FETCH_MAX_CONTENT_TOKENS, maxUses: $this->webFetchMaxUses);
        }

        try {
            return $this->client->beta->messages->create(
                maxTokens: $this->maxTokens,
                messages: $messages,
                model: $this->model,
                fallbacks: $this->fallbacks ? 'default' : null,
                outputConfig: ['effort' => Effort::tryFrom($this->effort) ?? Effort::HIGH],
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                thinking: ['type' => 'adaptive'],
                toolChoice: ['type' => 'auto'],
                tools: $tools,
                betas: $this->fallbacks ? ['server-side-fallback-2026-07-01'] : null,
            );
        } catch (AuthenticationException $e) {
            throw new AnalysisFailed('Anthropic rejected the credentials. Check ANTHROPIC_API_KEY.', previous: $e);
        } catch (APIException $e) {
            throw new AnalysisFailed('The Anthropic API request failed: '.$e->getMessage(), previous: $e);
        }
    }
}
