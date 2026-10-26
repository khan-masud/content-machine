<?php

/*
|--------------------------------------------------------------------------
| Photo cards
|--------------------------------------------------------------------------
|
| Photo cards are rendered by resources/photocards/render.py with Pillow +
| libraqm, which shapes Bangla correctly in ~35 MB of RAM (no browser).
| The server needs `python3` and Pillow built with raqm, e.g. on Ubuntu:
| `apt install python3-pil libraqm0`.
|
*/

return [
    'python' => env('PHOTO_CARD_PYTHON', 'python3'),

    'script' => resource_path('photocards/render.py'),

    'timeout' => (int) env('PHOTO_CARD_TIMEOUT', 30),

    // 4:5 portrait, the largest feed format on Facebook and Instagram.
    'width' => 1080,
    'height' => 1350,

    'font_directory' => resource_path('fonts/photocards'),

    // `weight` selects an instance of a variable font; null for static files.
    'fonts' => [
        'hind-siliguri' => [
            'label' => 'Hind Siliguri',
            'regular' => ['file' => 'HindSiliguri-Medium.ttf', 'weight' => null],
            'bold' => ['file' => 'HindSiliguri-Bold.ttf', 'weight' => null],
        ],
        'anek-bangla' => [
            'label' => 'Anek Bangla',
            'regular' => ['file' => 'AnekBangla.ttf', 'weight' => 500],
            'bold' => ['file' => 'AnekBangla.ttf', 'weight' => 750],
        ],
    ],

    'themes' => [
        'light' => ['background' => '#FFFFFF', 'text' => '#111111', 'muted' => '#4B5563'],
        'dark' => ['background' => '#0B1220', 'text' => '#FFFFFF', 'muted' => '#CBD5E1'],
    ],

    'brand_defaults' => [
        'primary_color' => '#0E7490',
        'font' => 'hind-siliguri',
        'image_label' => 'প্রতীকী চিত্র',
    ],
];
