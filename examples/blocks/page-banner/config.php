<?php

declare(strict_types=1);

return [
    'category' => 'Examples',
    'title' => 'Page banner',
    'icon' => 'fa fa-window-maximize',
    'namespace' => 'Vihzhuo\\Examples\\Blocks\\PageBanner',
    'wrapper' => 'section',
    'cache' => true,
    'cache_lifetime' => 60,
    'settings' => [
        'eyebrow' => [
            'type' => 'text',
            'label' => 'Eyebrow',
            'value' => 'Example extension block',
            'placeholder' => 'Short introductory text',
        ],
        'heading' => [
            'type' => 'text',
            'label' => 'Heading',
            'value' => 'A configurable page banner',
        ],
        'message' => [
            'type' => 'text',
            'label' => 'Message',
            'value' => 'This block is rendered by PHP whenever a setting changes.',
        ],
        'tone' => [
            'type' => 'select',
            'label' => 'Tone',
            'options' => [
                ['value' => 'primary', 'label' => 'Primary'],
                ['value' => 'success', 'label' => 'Success'],
                ['value' => 'warning', 'label' => 'Warning'],
                ['value' => 'dark', 'label' => 'Dark'],
            ],
            'value' => 'primary',
        ],
        'show_context' => [
            'type' => 'yes_no',
            'label' => 'Show page context',
            'value' => '1',
        ],
        'link_label' => [
            'type' => 'text',
            'label' => 'Link label',
            'value' => 'Read the documentation',
        ],
        'link_url' => [
            'type' => 'text',
            'label' => 'Link URL',
            'value' => '/docs',
            'placeholder' => '/docs or https://example.com/docs',
        ],
    ],
];
