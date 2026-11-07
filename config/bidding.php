<?php

return [
    'api_token_ttl_minutes' => 480,
    'demo_enabled' => env('BIDDING_DEMO_ENABLED', false),
    'display_timezone' => 'America/Argentina/Buenos_Aires',
    'profiles' => [
        'demo' => ['suppliers' => 15, 'tenders' => 20, 'bids_per_tender' => 3],
        'development' => ['suppliers' => 60, 'tenders' => 300, 'bids_per_tender' => 4],
        'stress' => ['suppliers' => 250, 'tenders' => 5000, 'bids_per_tender' => 5],
    ],
];
