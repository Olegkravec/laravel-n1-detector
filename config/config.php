<?php

return [
    'enabled' => env('QUERY_DETECTOR_ENABLED', null),

    'threshold' => (int) env('QUERY_DETECTOR_THRESHOLD', 1),

    'except' => [
        //Author::class => [
        //    Post::class,
        //    'posts',
        //]
    ],

    'log_channel' => env('QUERY_DETECTOR_LOG_CHANNEL', 'daily'),


    'output' => [
        \OlegKravec\QueryDetector\Outputs\Alert::class,
        \OlegKravec\QueryDetector\Outputs\Log::class,
    ]
];
