<?php

return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'test_db'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', '123123'),
        ],
    ],
    'migrations' => 'migrations',

];
