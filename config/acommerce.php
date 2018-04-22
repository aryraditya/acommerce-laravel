<?php

return [
    'production'    => true,
    'username'      => env('ACOM_USERNAME'),
    'api_key'       => env('ACOM_APIKEY'),

    'cache'         => [
        'duration'  => 60 * 60 * 2, //2 hours
    ],
];