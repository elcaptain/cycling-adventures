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
            static $patching = false;
            if ($patching || $newPage->template()->name() !== 'adventure') return;

            // Auto-slug from title
            $newSlug = \Kirby\Toolkit\Str::slug($newPage->title()->value());
            if ($newSlug && $newSlug !== $newPage->slug()) {
                $newPage->changeSlug($newSlug);
            }

            // Sync date from first GPX trackpoint if it doesn't match what's stored.
            // This runs after every Panel save, so the GPX date always wins over
            // the Panel form's default (today) without requiring a separate upload step.
            $gpxFile = $newPage->gpxfile()->toFile();
            if ($gpxFile) {
                $raw = file_get_contents($gpxFile->root());
                if (preg_match('/<time>(\d{4}-\d{2}-\d{2})/', $raw, $m) && $newPage->date()->value() !== $m[1]) {
                    $patching = true;
                    $newPage->update(['date' => $m[1]]);
                    $patching = false;
                }
            }
        },
        'file.create:after' => function ($file) {
            if ($file->extension() !== 'gpx') return;
            $page = $file->parent();
            if (!$page || $page->template()->name() !== 'adventure') return;

            $updates = [];

            // Date from first GPX timestamp
            $content = file_get_contents($file->root());
            if (preg_match('/<time>(\d{4}-\d{2}-\d{2})/', $content, $m)) {
                $updates['date'] = $m[1];
            }

            // Cache aggregated stats so the list page skips GPX parsing
            $stats = $page->gpxAggregatedStats();
            if ($stats) {
                $updates['distance']   = $stats['distance'];
                $updates['elevation']  = $stats['elevation_gain'];
                $updates['movingtime'] = $stats['moving_time_formatted'];
                $updates['duration']   = $stats['duration_formatted'];
                $updates['avgspeed']   = $stats['avg_speed'];
            }

            if (!empty($updates)) {
                $newPage = $page->update($updates);
                // Also patch the Panel's changes version so a subsequent Save
                // doesn't overwrite our values with the stale form state
                $changes = $newPage->version('changes');
                if ($changes->exists()) {
                    $changes->update($updates);
                }
            }
        },
        'file.replace:after' => function ($newFile, $oldFile) {
            if ($newFile->extension() !== 'gpx') return;
            $page = $newFile->parent();
            if (!$page || $page->template()->name() !== 'adventure') return;

            $stats = $newFile->parent()->gpxAggregatedStats();
            if ($stats) {
                $updates = [
                    'distance'   => $stats['distance'],
                    'elevation'  => $stats['elevation_gain'],
                    'movingtime' => $stats['moving_time_formatted'],
                    'duration'   => $stats['duration_formatted'],
                    'avgspeed'   => $stats['avg_speed'],
                ];
                $newPage = $page->update($updates);
                $changes = $newPage->version('changes');
                if ($changes->exists()) {
                    $changes->update($updates);
                }
            }
        },
        'file.delete:after' => function ($file) {
            if ($file->extension() !== 'gpx') return;
            $page = $file->parent();
            if (!$page || $page->template()->name() !== 'adventure') return;

            $stats = $page->gpxAggregatedStats();
            $updates = $stats ? [
                'distance'   => $stats['distance'],
                'elevation'  => $stats['elevation_gain'],
                'movingtime' => $stats['moving_time_formatted'],
                'duration'   => $stats['duration_formatted'],
                'avgspeed'   => $stats['avg_speed'],
            ] : ['distance' => '', 'elevation' => '', 'movingtime' => '', 'duration' => '', 'avgspeed' => ''];

            $newPage = $page->update($updates);
            $changes = $newPage->version('changes');
            if ($changes->exists()) {
                $changes->update($updates);
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
                // fall through to Kirby's router (handles drafts + preview tokens)
                return $page ?? $this->next();
            }
        ],
        // Permalink without slug: /adventures/28
        [
            'pattern' => 'adventures/(:num)',
            'action'  => function (string $num) {
                $page = page('adventures')->children()
                    ->filter(fn($p) => (string)$p->num() === $num)
                    ->first();
                // fall through to Kirby's router (handles drafts + preview tokens)
                return $page ?? $this->next();
            }
        ],
        // Legacy slug-only URLs for backwards compatibility
        [
            'pattern' => 'adventures/(:any)',
            'action' => function ($uid) {
                if ($page = page('adventures/' . $uid)) {
                    return $page;
                }
                // page() skips drafts; let Kirby's router resolve them so
                // Panel previews (?_token=…&_version=…) work
                $this->next();
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
