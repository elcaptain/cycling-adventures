<?php

/**
 * Cycling Adventures Blog Configuration
 *
 * This is the main configuration file for your Kirby site.
 * Adjust these settings as needed for your deployment.
 */
return [
'hooks' => [
        'file.create:before' => function ($file, $upload) {
            // If it's a GPX file, we skip Kirby's internal mime check
            if ($upload->extension() === 'gpx') {
                return; // Allow the upload to proceed
            }
        }
    ],
    'components' => [
        'file::type' => function ($kirby, $extension) {
            if ($extension === 'gpx') return 'document';
            return $kirby->nativeComponent('file::type')($kirby, $extension);
        }
    ],
    // Enable debug mode during development
    // Set to false in production!
    'debug' => true,

    // Basic site settings
    'url' => 'http:/jonas.local:8000', // Change this to your production URL

    // Panel settings (admin interface)
    'panel' => [
        'install' => true,
        'slug' => 'panel'
    ],

    // Content settings
    'content' => [
        'extension' => 'txt'
    ],

    // Image handling
    'thumbs' => [
        'driver' => 'gd',
        'srcsets' => [
            'default' => [
                '400w' => ['width' => 400, 'quality' => 80],
                '800w' => ['width' => 800, 'quality' => 80],
                '1200w' => ['width' => 1200, 'quality' => 80],
                '1600w' => ['width' => 1600, 'quality' => 80]
            ]
        ]
    ],

    // Routes for clean URLs
    'routes' => [
        // Homepage shows adventure list
        [
            'pattern' => '/',
            'action' => function () {
                return page('home');
            }
        ],
        // Adventure detail pages
        [
            'pattern' => 'adventures/(:any)',
            'action' => function ($uid) {
                if ($page = page('adventures/' . $uid)) {
                    return $page;
                }
                return page('error');
            }
        ]
    ],

    // File upload settings - allow insecure for development
    // 'api' => [
    //     'allowInsecure' => true,
    //     'basicAuth' => false
    // ],
    // 'mime' => [
    //     'gpx' => 'application/gpx+xml'
    // ],
    // 'validations' => [
    //     'files' => [
    //         'extension' => [
    //             'gpx'
    //         ]
    //     ]
    // ],
    'components' => [
        'file::version' => function ($kirby, $file, $options) {
            return $file->url();
        },
        // This is the specific hook to bypass validation
        'file::type' => function ($kirby, $extension) {
            if ($extension === 'gpx') {
                return 'document';
            }
            return $kirby->nativeComponent('file::type')($kirby, $extension);
        },
    ],
    'fileTypes' => [
        'gpx' => [
            'mime' => 'application/gpx+xml',
            'type' => 'document',
        ],
    'panel' => [
        'debug' => true
    ]
  ],

    // Languages (currently single language)
    'languages' => false,

    // Cache settings (enable in production for better performance)
    'cache' => [
        'pages' => [
            'active' => false, // Set to true in production
            'ignore' => function ($page) {
                // Don't cache the panel
                return $page->kirby()->user() !== null;
            }
        ]
    ]
];
