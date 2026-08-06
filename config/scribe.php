<?php

use Knuckles\Scribe\Extracting\Strategies;

return [

    'theme' => 'default',

    'title' => 'HRMS API Documentation',

    'description' => 'Human Resource Management System API documentation. This API provides endpoints for managing employees, attendance, leave requests, recruitment, and more.',

    'base_url' => env('APP_URL', 'http://localhost'),

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
                'versions' => ['v1'],
            ],
            'include' => [],
            'exclude' => [],
            'apply' => [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'response_calls' => [
                    'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
                    'config' => [
                        'app.env' => 'documentation',
                        'app.debug' => false,
                    ],
                    'queryParams' => [],
                    'bodyParams' => [],
                    'fileParams' => [],
                    'cookies' => [],
                ],
            ],
        ],
    ],

    'type' => 'static',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,
        'docs_url' => '/docs',
        'middleware' => [],
    ],

    'try_it_out' => [
        'enabled' => true,
        'base_url' => null,
    ],

    'auth' => [
        'enabled' => true,
        'default' => false,
        'in' => 'bearer',
        'name' => 'token',
        'use_value' => env('SCRIBE_AUTH_KEY'),
        'placeholder' => '{YOUR_AUTH_TOKEN}',
        'extra_info' => 'You can retrieve your token by visiting your dashboard and clicking <b>Generate API token</b>.',
    ],

    'intro_text' => <<<INTRO
This documentation aims to provide all the information you need to work with our API.

<aside>As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).</aside>
INTRO,

    'example_languages' => [
        'bash',
        'javascript',
        'php',
    ],

    'postman' => [
        'enabled' => true,
        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    'openapi' => [
        'enabled' => true,
        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    'groups' => [
        'default' => 'Endpoints',
        'order' => [
            'Authentication',
            'Employees',
            'Departments',
            'Positions',
            'Attendance',
            'Leave Management',
            'Recruitment',
            'Travel Orders',
            'Offboarding',
            'Dashboard',
        ],
    ],

    'logo' => false,

    'faker_seed' => null,

    'strategies' => [
        'metadata' => [
            Strategies\Metadata\GetFromDocBlocks::class,
        ],
        'urlParameters' => [
            Strategies\UrlParameters\GetFromLaravelAPI::class,
            Strategies\UrlParameters\GetFromUrlParamTag::class,
        ],

        'queryParameters' => [
            Strategies\QueryParameters\GetFromFormRequest::class,
            Strategies\QueryParameters\GetFromInlineValidator::class,
            Strategies\QueryParameters\GetFromQueryParamTag::class,
        ],
        'headers' => [
            Strategies\Headers\GetFromHeaderTag::class,
        ],

        'bodyParameters' => [
            Strategies\BodyParameters\GetFromFormRequest::class,
            Strategies\BodyParameters\GetFromInlineValidator::class,
            Strategies\BodyParameters\GetFromBodyParamTag::class,
        ],
        'responses' => [
            Strategies\Responses\UseTransformerTags::class,
            Strategies\Responses\UseApiResourceTags::class,
            Strategies\Responses\UseResponseTag::class,
            Strategies\Responses\UseResponseFileTag::class,
            Strategies\Responses\ResponseCalls::class,
        ],
        'responseFields' => [
            Strategies\ResponseFields\GetFromResponseFieldTag::class,
        ],
    ],

    'fractal' => [
        'serializer' => null,
    ],

    'routeMatcher' => \Knuckles\Scribe\Matching\RouteMatcher::class,

    'database_connections_to_transact' => [config('database.default')],

];
