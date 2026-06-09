<?php

declare(strict_types=1);

/**
 * Filesystem Configuration
 */
return [
    /**
     * Default Disk
     *
     * This option controls the default filesystem disk that is used when you
     * use the storage driver. The default disk is usually configured to
     * use the local driver but you may change this to any other disk.
     */
    'default' => env('FILESYSTEM_DISK', 'local'),

    /**
     * Filesystem Disks
     *
     * Here you may configure as many filesystem "disks" as you wish, and you
     * may even configure multiple disks of the same driver. Defaults have
     * been setup for each driver as an example of the required options.
     */
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'permissions' => 0755,
        ],

        'public' => [
            'driver' => 'local',
            'root' => public_path('storage'),
            'url' => env('APP_URL') . '/storage',
            'permissions' => 0755,
        ],

    ],
];
