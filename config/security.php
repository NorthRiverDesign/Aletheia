<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request Throttling
    |--------------------------------------------------------------------------
    |
    | Limits applied to every web request, counted per signed-in member or,
    | for guests, per IP address. Reads and writes have separate budgets, and
    | sign-ups, password reset emails and catalog suggestions have tighter
    | ones because bots abuse them. Admins are exempt from the suggestion
    | limit. Set any value to 0 to disable that limit.
    |
    */

    'throttle' => [
        'reads_per_minute' => (int) env('THROTTLE_READS_PER_MINUTE', 300),
        'writes_per_minute' => (int) env('THROTTLE_WRITES_PER_MINUTE', 60),
        'registrations_per_hour' => (int) env('THROTTLE_REGISTRATIONS_PER_HOUR', 5),
        'password_resets_per_hour' => (int) env('THROTTLE_PASSWORD_RESETS_PER_HOUR', 10),
        'suggestions_per_hour' => (int) env('THROTTLE_SUGGESTIONS_PER_HOUR', 20),
    ],

];
