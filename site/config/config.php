<?php

ini_set('memory_limit', '256M');

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
                return;
            }
        },
        'page.update:after' => function ($newPage, $oldPage) {
            if ($newPage->template()->name() !== 'adventure') return;
            $newSlug = \Kirby\Toolkit\Str::slug($newPage->title()->value());
            if ($newSlug && $newSlug !== $newPage->slug()) {
                $newPage->changeSlug($newSlug);
            }
        },
        'file.create:after' => function ($file) {
            if ($file->extension() !== 'gpx') return;
            $page = $file->parent();
            if (!$page || $page->template()->name() !== 'adventure') return;

            // Set date from first GPX timestamp
            $content = file_get_contents($file->root());
            if (preg_match('/<time>(\d{4}-\d{2}-\d{2})/', $content, $m)) {
                $page->update(['date' => $m[1]]);
            }

            // Cache aggregated stats in content fields so the list page skips GPX parsing
            $stats = $page->gpxAggregatedStats();
            if ($stats) {
                $page->update([
                    'distance'   => $stats['distance'],
                    'elevation'  => $stats['elevation_gain'],
                    'movingtime' => $stats['moving_time_formatted'],
                    'duration'   => $stats['duration_formatted'],
                    'avgspeed'   => $stats['avg_speed'],
                ]);
            }
        },
        'file.replace:after' => function ($newFile, $oldFile) {
            if ($newFile->extension() !== 'gpx') return;
            $page = $newFile->parent();
            if (!$page || $page->template()->name() !== 'adventure') return;

            $stats = $page->gpxAggregatedStats();
            if ($stats) {
                $page->update([
                    'distance'   => $stats['distance'],
                    'elevation'  => $stats['elevation_gain'],
                    'movingtime' => $stats['moving_time_formatted'],
                    'duration'   => $stats['duration_formatted'],
                    'avgspeed'   => $stats['avg_speed'],
                ]);
            }
        },
        'file.delete:after' => function ($file) {
            if ($file->extension() !== 'gpx') return;
            $page = $file->parent();
            if (!$page || $page->template()->name() !== 'adventure') return;

            $stats = $page->gpxAggregatedStats();
            if ($stats) {
                $page->update([
                    'distance'   => $stats['distance'],
                    'elevation'  => $stats['elevation_gain'],
                    'movingtime' => $stats['moving_time_formatted'],
                    'duration'   => $stats['duration_formatted'],
                    'avgspeed'   => $stats['avg_speed'],
                ]);
            } else {
                $page->update(['distance' => '', 'elevation' => '', 'movingtime' => '', 'duration' => '', 'avgspeed' => '']);
            }
        },
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
    'url' => 'http://jrmacbook.local:8000', // Change this to your production URL

    // Panel settings (admin interface)
    'panel' => [
        'install' => true,
        'slug'    => 'panel',
        'css'     => 'assets/css/panel.css',
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
        // Permalink: /adventures/28-some-slug (slug is decorative, lookup by number)
        [
            'pattern' => 'adventures/(:num)-(:any)',
            'action'  => function (string $num, string $slug) {
                $page = page('adventures')->children()
                    ->filter(fn($p) => (string)$p->num() === $num)
                    ->first();
                return $page ?? page('error');
            }
        ],
        // Permalink without slug: /adventures/28
        [
            'pattern' => 'adventures/(:num)',
            'action'  => function (string $num) {
                $page = page('adventures')->children()
                    ->filter(fn($p) => (string)$p->num() === $num)
                    ->first();
                return $page ?? page('error');
            }
        ],
        // Legacy slug-only URLs for backwards compatibility
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
        ],
        'adventure-helpers' => [
            'type' => 'file'
        ]
    ]
];
