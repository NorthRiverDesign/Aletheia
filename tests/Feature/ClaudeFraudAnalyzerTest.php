<?php

use Anthropic\Client;
use App\Enums\Confidence;
use App\Enums\Verdict;
use App\Models\Finding;
use App\Models\Target;
use App\Models\Vector;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\ClaudeFraudAnalyzer;
use App\Services\Analysis\ReferenceLink;
use App\Services\Analysis\References;
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

    $result = $analyzer->analyze($vector, $target, new References(
        global: [new ReferenceLink('USAspending.gov', 'https://www.usaspending.gov/search')],
        target: [new ReferenceLink('Springfield checkbook', 'https://checkbook.springfield.example.gov', 'Vendor payments')],
    ));

    expect($result->summary)->toBe('One lead worth checking.')
        ->and($result->model)->toBe('claude-opus-5-5')
        ->and($result->usage)->toBe(['input_tokens' => 100, 'cached_input_tokens' => 0, 'output_tokens' => 50, 'web_searches' => 3])
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
        ->and($body['system'][0]['text'])->toContain('USAspending.gov: https://www.usaspending.gov/search')
        ->and($body['system'][0]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($body['messages'][0]['content'])->toContain('Relatives owning awarded vendors', 'City of Springfield', 'Shared surnames')
        ->and($body['messages'][0]['content'])->toContain('Springfield checkbook: https://checkbook.springfield.example.gov (Vendor payments)');
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

    $result = $analyzer->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());

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

    $result = $analyzer->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());

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

    $analyzer->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());
})->throws(AnalysisFailed::class, 'declined');

test('bad credentials fail the analysis with a hint', function () {
    $history = [];
    $analyzer = claudeAnalyzer([
        new Response(401, ['Content-Type' => 'application/json'], json_encode([
            'type' => 'error',
            'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key'],
        ])),
    ], $history);

    $analyzer->analyze(Vector::factory()->create(), Target::factory()->create(), References::none());
})->throws(AnalysisFailed::class, 'ANTHROPIC_API_KEY');

test('it fact-checks a finding with web search and web fetch', function () {
    $history = [];
    $analyzer = claudeAnalyzer([
        claudeMessage([[
            'type' => 'tool_use', 'id' => 'toolu_2', 'name' => 'record_verification', 'input' => [
                'verdict' => 'contradicted',
                'summary' => 'The registry lists a different owner.',
                'claims' => [[
                    'claim' => 'Acme Paving is owned by the council aide', 'status' => 'incorrect',
                    'note' => 'The registry lists an unrelated owner.', 'url' => 'https://sos.example.gov/acme',
                ]],
                'issues' => ['Owner misidentified'],
                'risk_score' => 10,
                'confidence' => 'high',
            ],
        ]], 'tool_use', ['server_tool_use' => ['web_search_requests' => 1, 'web_fetch_requests' => 2]]),
    ], $history);

    $finding = Finding::factory()->create([
        'title' => 'Vendor shares owner with council aide',
        'evidence' => [['description' => 'Business filing', 'url' => 'https://sos.example.gov/acme']],
    ]);

    $result = $analyzer->verify($finding, References::none());

    expect($result->verdict)->toBe(Verdict::Contradicted)
        ->and($result->summary)->toBe('The registry lists a different owner.')
        ->and($result->riskScore)->toBe(10)
        ->and($result->confidence)->toBe(Confidence::High)
        ->and($result->claims[0]['status'])->toBe('incorrect')
        ->and($result->issues)->toBe(['Owner misidentified'])
        ->and($result->usage)->toBe(['input_tokens' => 100, 'cached_input_tokens' => 0, 'output_tokens' => 50, 'web_searches' => 1, 'web_fetches' => 2]);

    $body = json_decode((string) $history[0]['request']->getBody(), true);

    expect($body['tools'][0]['name'])->toBe('record_verification')
        ->and($body['tools'][0]['strict'])->toBeTrue()
        ->and($body['tools'][0]['input_schema']['required'])->toBe(['verdict', 'summary', 'claims', 'issues', 'risk_score', 'confidence'])
        ->and($body['tools'][1])->toMatchArray(['type' => 'web_search_20260209', 'max_uses' => 5])
        ->and($body['tools'][2])->toMatchArray(['type' => 'web_fetch_20260209', 'name' => 'web_fetch', 'max_uses' => 10])
        ->and($body['system'][0]['text'])->toContain('independent fact-checker')
        ->and($body['messages'][0]['content'])->toContain(
            'Lead to fact-check: Vendor shares owner with council aide',
            '1. Business filing — https://sos.example.gov/acme',
            $finding->vector->title,
            $finding->target->name,
        );
});
