<?php

use App\Models\Target;
use App\Models\Vector;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\ClaudeFraudAnalyzer;
use App\Services\Analysis\FraudAnalyzer;
use App\Services\Analysis\GrokFraudAnalyzer;
use App\Services\Analysis\ReferenceLink;
use App\Services\Analysis\References;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function grokAnalyzer(?string $apiKey = 'xai-test-key', bool $webSearch = true): GrokFraudAnalyzer
{
    return new GrokFraudAnalyzer(
        http: app(HttpFactory::class),
        apiKey: $apiKey,
        model: 'grok-4.3',
        reasoningEffort: 'low',
        maxTurns: 6,
        webSearch: $webSearch,
    );
}

function grokResponse(array $output, string $status = 'completed', array $usage = []): array
{
    return [
        'id' => 'resp_'.fake()->uuid(),
        'object' => 'response',
        'model' => 'grok-4.3',
        'status' => $status,
        'output' => $output,
        'usage' => [
            'input_tokens' => 1000,
            'input_tokens_details' => ['cached_tokens' => 600],
            'output_tokens' => 200,
            'output_tokens_details' => ['reasoning_tokens' => 50],
            'num_server_side_tools_used' => 2,
            'server_side_tool_usage_details' => ['web_search_calls' => 2, 'x_search_calls' => 0],
            'cost_in_usd_ticks' => 25_000_000,
            ...$usage,
        ],
    ];
}

function grokToolCall(array $arguments): array
{
    return ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'record_findings', 'arguments' => json_encode($arguments)];
}

function grokText(string $text): array
{
    return ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]];
}

test('it sends the analysis to the xAI Responses API and parses the recorded findings', function () {
    Http::fake(['api.x.ai/*' => Http::response(grokResponse([
        ['type' => 'web_search_call', 'id' => 'ws_1', 'status' => 'completed'],
        grokToolCall([
            'assessment' => 'One lead.',
            'findings' => [[
                'title' => 'Vendor shares address with council aide', 'summary' => 'Same address.', 'rationale' => 'Fits.',
                'risk_score' => 64, 'confidence' => 'medium', 'entities' => ['Acme LLC'],
                'evidence' => [['description' => 'Registry', 'url' => 'https://sos.example.gov/acme']], 'next_steps' => [],
            ]],
            'reference_sources' => [
                ['title' => 'Springfield contracts portal', 'url' => 'https://contracts.springfield.example.gov', 'description' => 'Awarded contracts'],
                ['title' => 'Bad link', 'url' => 'ftp://files.example.gov', 'description' => ''],
            ],
        ]),
    ]))]);

    $vector = Vector::factory()->create(['title' => 'Relatives owning awarded vendors']);
    $target = Target::factory()->create(['name' => 'City of Springfield']);

    $result = grokAnalyzer()->analyze($vector, $target, new References(
        global: [new ReferenceLink('USAspending.gov', 'https://www.usaspending.gov/search')],
        target: [new ReferenceLink('Springfield checkbook', 'https://checkbook.springfield.example.gov')],
    ));

    expect($result->model)->toBe('grok-4.3')
        ->and($result->summary)->toBe('One lead.')
        ->and($result->findings)->toHaveCount(1)
        ->and($result->findings[0]->riskScore)->toBe(64)
        ->and($result->usage)->toBe(['input_tokens' => 1000, 'cached_input_tokens' => 600, 'output_tokens' => 200, 'web_searches' => 2, 'cost_usd' => 0.0025])
        ->and($result->referenceSources)->toHaveCount(1)
        ->and($result->referenceSources[0]->url)->toBe('https://contracts.springfield.example.gov');

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://api.x.ai/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer xai-test-key')
            && $body['model'] === 'grok-4.3'
            && $body['store'] === false
            && $body['prompt_cache_key'] === 'aletheia-analysis'
            && $body['max_turns'] === 6
            && $body['reasoning'] === ['effort' => 'low']
            && $body['tool_choice'] === 'auto'
            && $body['tools'][0]['type'] === 'function'
            && $body['tools'][0]['name'] === 'record_findings'
            && in_array('reference_sources', $body['tools'][0]['parameters']['required'], true)
            && $body['tools'][1] === ['type' => 'web_search']
            && str_contains($body['instructions'], 'USAspending.gov: https://www.usaspending.gov/search')
            && str_contains($body['input'][0]['content'], 'Springfield checkbook: https://checkbook.springfield.example.gov')
            && str_contains($body['input'][0]['content'], 'City of Springfield');
    });
});

test('it asks once more, without web search, when Grok answers in prose', function () {
    Http::fake(['api.x.ai/*' => Http::sequence()
        ->push(grokResponse([grokText('Here is what I found about the vendors.')]))
        ->push(grokResponse([grokToolCall(['assessment' => 'Done.', 'findings' => [], 'reference_sources' => []])]))]);

    $result = grokAnalyzer()->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());

    expect($result->summary)->toBe('Done.')
        ->and($result->usage['input_tokens'])->toBe(2000)
        ->and($result->usage['cost_usd'])->toBe(0.005);

    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);

    $second = $requests[1][0]->data();
    expect($second['tools'])->toHaveCount(1)
        ->and($second)->not->toHaveKey('max_turns')
        ->and($second['input'])->toHaveCount(3)
        ->and($second['input'][1])->toBe(['role' => 'assistant', 'content' => 'Here is what I found about the vendors.'])
        ->and($second['input'][2]['content'])->toContain('record_findings');
});

test('an incomplete response fails the analysis with the reason', function () {
    Http::fake(['api.x.ai/*' => Http::response(grokResponse([], 'incomplete') + ['incomplete_details' => ['reason' => 'max_output_tokens']])]);

    grokAnalyzer()->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());
})->throws(AnalysisFailed::class, 'max_output_tokens');

test('bad credentials fail the analysis with a hint', function (int $status) {
    // The live API answers an invalid key with a 400 and a plain-string error.
    Http::fake(['api.x.ai/*' => Http::response([
        'code' => 'Client specified an invalid argument',
        'error' => 'Incorrect API key provided: xa***st. You can obtain an API key from https://console.x.ai.',
    ], $status)]);

    grokAnalyzer()->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());
})->with([400, 401])->throws(AnalysisFailed::class, 'Check XAI_API_KEY');

test('API errors are surfaced', function () {
    Http::fake(['api.x.ai/*' => Http::response(['error' => ['message' => 'Model not found: grok-9']], 404)]);

    grokAnalyzer()->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());
})->throws(AnalysisFailed::class, 'Model not found: grok-9');

test('a missing API key fails before any request is made', function () {
    Http::fake();

    expect(fn () => grokAnalyzer(apiKey: null)->analyze(Vector::factory()->create(), Target::factory()->create(), References::none()))
        ->toThrow(AnalysisFailed::class, 'XAI_API_KEY is not set.');

    Http::assertNothingSent();
});

test('web search can be turned off', function () {
    Http::fake(['api.x.ai/*' => Http::response(grokResponse([grokToolCall(['assessment' => 'Done.', 'findings' => [], 'reference_sources' => []])]))]);

    grokAnalyzer(webSearch: false)->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());

    Http::assertSent(fn (Request $request) => count($request->data()['tools']) === 1
        && ! array_key_exists('max_turns', $request->data())
        && str_contains($request->data()['input'][0]['content'], 'Web search is not available'));
});

test('the AI_PROVIDER setting picks the analyzer', function () {
    expect(app(FraudAnalyzer::class))->toBeInstanceOf(ClaudeFraudAnalyzer::class);

    config(['analysis.provider' => 'xai']);

    expect(app(FraudAnalyzer::class))->toBeInstanceOf(GrokFraudAnalyzer::class);
});
