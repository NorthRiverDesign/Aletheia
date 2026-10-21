<?php

use Anthropic\Client;
use App\Models\Target;
use App\Models\Vector;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\ClaudeFraudAnalyzer;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * @param  list<Response>  $responses
 * @param  array<int, array<string, mixed>>  $history
 */
function claudeAnalyzer(array $responses, array &$history, int $webSearchMaxUses = 5): ClaudeFraudAnalyzer
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return new ClaudeFraudAnalyzer(
        client: new Client(apiKey: 'test-key', requestOptions: ['transporter' => new Guzzle(['handler' => $stack]), 'maxRetries' => 0]),
        model: 'claude-opus-5-5',
        webSearchMaxUses: $webSearchMaxUses,
    );
}

function claudeMessage(array $content, string $stopReason, array $usage = []): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'id' => 'msg_'.fake()->uuid(),
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => $content,
        'stop_reason' => $stopReason,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 100, 'output_tokens' => 50, ...$usage],
    ]));
}

function recordFindings(array $input): array
{
    return ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'record_findings', 'input' => $input];
}

test('it sends the vector and target to Claude and parses the recorded findings', function () {
    $history = [];
    $analyzer = claudeAnalyzer([
        claudeMessage([recordFindings([
            'assessment' => 'One lead worth checking.',
            'findings' => [
                [
                    'title' => 'Low lead', 'summary' => 'Weak.', 'rationale' => 'Loose fit.', 'risk_score' => 15,
                    'confidence' => 'low', 'entities' => [], 'evidence' => [], 'next_steps' => [],
                ],
                [
                    'title' => 'Vendor shares owner with council aide', 'summary' => 'Same registered agent.',
                    'rationale' => 'Matches the related-party indicator.', 'risk_score' => 140, 'confidence' => 'medium',
                    'entities' => ['Acme Paving LLC', ''],
                    'evidence' => [
                        ['description' => 'Business filing', 'url' => 'https://sos.example.gov/acme'],
                        ['description' => 'Suspicious link', 'url' => 'javascript:alert(1)'],
                    ],
                    'next_steps' => ['Request the statement of economic interest'],
                ],
            ],
        ])], 'tool_use', ['server_tool_use' => ['web_search_requests' => 3, 'web_fetch_requests' => 0]]),
    ], $history);

    $vector = Vector::factory()->create(['title' => 'Relatives owning awarded vendors', 'indicators' => ['Shared surnames']]);
    $target = Target::factory()->create(['name' => 'City of Springfield']);

    $result = $analyzer->analyze($vector, $target);

    expect($result->summary)->toBe('One lead worth checking.')
        ->and($result->model)->toBe('claude-opus-5-5')
        ->and($result->usage)->toBe(['input_tokens' => 100, 'output_tokens' => 50, 'web_searches' => 3])
        ->and($result->findings)->toHaveCount(2);

    $top = $result->findings[0];
    expect($top->title)->toBe('Vendor shares owner with council aide')
        ->and($top->riskScore)->toBe(100)
        ->and($top->entities)->toBe(['Acme Paving LLC'])
        ->and($top->evidence)->toBe([
            ['description' => 'Business filing', 'url' => 'https://sos.example.gov/acme'],
            ['description' => 'Suspicious link', 'url' => null],
        ]);

    expect($history)->toHaveCount(1);
    $request = $history[0]['request'];
    $body = json_decode((string) $request->getBody(), true);

    expect((string) $request->getUri())->toEndWith('/v1/messages?beta=true')
        ->and($request->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01')
        ->and($body['model'])->toBe('claude-opus-5-5')
        ->and($body['fallbacks'])->toBe('default')
        ->and($body['thinking'])->toBe(['type' => 'adaptive'])
        ->and($body['output_config'])->toBe(['effort' => 'high'])
        ->and($body['tools'][0]['name'])->toBe('record_findings')
        ->and($body['tools'][0]['strict'])->toBeTrue()
        ->and($body['tools'][0]['input_schema']['additionalProperties'])->toBeFalse()
        ->and($body['tools'][1])->toMatchArray(['type' => 'web_search_20260209', 'name' => 'web_search', 'max_uses' => 5])
        ->and($body['messages'][0]['role'])->toBe('user')
        ->and($body['messages'][0]['content'])->toContain('Relatives owning awarded vendors', 'City of Springfield', 'Shared surnames');
});

test('it resumes a paused turn by sending the assistant content back', function () {
    $history = [];
    $analyzer = claudeAnalyzer([
        claudeMessage([
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig_abc'],
            ['type' => 'text', 'text' => 'Searching contract awards.'],
            ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'web_search', 'input' => ['query' => 'springfield contracts']],
        ], 'pause_turn'),
        claudeMessage([recordFindings(['assessment' => 'Nothing checks out.', 'findings' => []])], 'tool_use'),
    ], $history);

    $result = $analyzer->analyze(Vector::factory()->create(), Target::factory()->create());

    expect($result->findings)->toBe([])
        ->and($result->usage['input_tokens'])->toBe(200)
        ->and($history)->toHaveCount(2);

    $messages = json_decode((string) $history[1]['request']->getBody(), true)['messages'];

    expect($messages)->toHaveCount(2)
        ->and($messages[1]['role'])->toBe('assistant')
        ->and($messages[1]['content'][0])->toMatchArray(['type' => 'thinking', 'signature' => 'sig_abc'])
        ->and($messages[1]['content'][2])->toMatchArray(['type' => 'server_tool_use', 'id' => 'srvtoolu_1']);
});

test('it asks again when the model answers without calling the tool', function () {
    $history = [];
    $analyzer = claudeAnalyzer([
        claudeMessage([['type' => 'text', 'text' => 'Here is what I found...']], 'end_turn'),
        claudeMessage([recordFindings(['assessment' => 'Done.', 'findings' => []])], 'tool_use'),
    ], $history, webSearchMaxUses: 0);

    $result = $analyzer->analyze(Vector::factory()->create(), Target::factory()->create());

    $first = json_decode((string) $history[0]['request']->getBody(), true);
    $second = json_decode((string) $history[1]['request']->getBody(), true);

    expect($result->summary)->toBe('Done.')
        ->and($first['tools'])->toHaveCount(1)
        ->and($first['messages'][0]['content'])->toContain('Web search is not available')
        ->and($second['messages'])->toHaveCount(3)
        ->and($second['messages'][2]['content'])->toContain('record_findings');
});

test('refusals fail the analysis', function () {
    $history = [];
    $analyzer = claudeAnalyzer([claudeMessage([], 'refusal')], $history);

    $analyzer->analyze(Vector::factory()->create(), Target::factory()->create());
})->throws(AnalysisFailed::class, 'declined');

test('bad credentials fail the analysis with a hint', function () {
    $history = [];
    $analyzer = claudeAnalyzer([
        new Response(401, ['Content-Type' => 'application/json'], json_encode([
            'type' => 'error',
            'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key'],
        ])),
    ], $history);

    $analyzer->analyze(Vector::factory()->create(), Target::factory()->create());
})->throws(AnalysisFailed::class, 'ANTHROPIC_API_KEY');
