<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | Which model provider cross-checks vectors against targets: "anthropic"
    | (Claude) or "xai" (Grok). Credentials and model settings for each live
    | in config/services.php.
    |
    */

    'provider' => env('AI_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Cooldown
    |--------------------------------------------------------------------------
    |
    | Hours before the same vector can be analyzed against the same target
    | again after a completed run, so identical lookups aren't paid for twice.
    | Set to 0 to disable.
    |
    */

    'cooldown_hours' => (int) env('ANALYSIS_COOLDOWN_HOURS', 168),

    /*
    |--------------------------------------------------------------------------
    | Fact Checks
    |--------------------------------------------------------------------------
    |
    | A second AI pass can re-check a finding's claims and sources and look
    | for errors. Reviewers start one from a finding or an analysis; turn on
    | "auto" to fact-check every new finding as soon as its analysis
    | completes, which roughly doubles the inference spent per analysis.
    |
    */

    'verification' => [
        'auto' => (bool) env('VERIFY_FINDINGS_AUTOMATICALLY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reference Data
    |--------------------------------------------------------------------------
    |
    | Links to public datasets are cached and handed to the model up front so
    | it doesn't spend searches rediscovering them. "prompt_limit" caps how
    | many per-target links go into a prompt and "store_limit" how many are
    | kept per target; "cache_ttl" is in seconds.
    |
    */

    'references' => [
        'prompt_limit' => (int) env('REFERENCE_PROMPT_LIMIT', 20),
        'store_limit' => (int) env('REFERENCE_STORE_LIMIT', 50),
        'cache_ttl' => (int) env('REFERENCE_CACHE_TTL', 3600),
    ],

];
