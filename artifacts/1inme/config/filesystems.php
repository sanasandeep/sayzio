<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Admin Asset Vault Disk
    |--------------------------------------------------------------------------
    |
    | Which disk the admin asset vault writes to. Defaults to the local
    | "admin_assets" disk; set ADMIN_ASSETS_DISK=s3 in your env to point the
    | vault at S3 (requires AWS_* credentials below).
    |
    */
    'admin_assets_disk' => env('ADMIN_ASSETS_DISK', 'admin_assets'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    /*
    |--------------------------------------------------------------------------
    | User-Content Storage Driver
    |--------------------------------------------------------------------------
    |
    | When USER_CONTENT_DISK=s3 the user-facing disks (`public`, `user_files`,
    | `admin_assets`) are backed by S3 instead of the local filesystem, so
    | uploads are durable across environment resets and public files can be
    | served via CloudFront. The disk *names* stay the same so the hundreds of
    | `Storage::disk('public'|'user_files'|'admin_assets')` call sites and the
    | `disk` value stamped on records keep resolving — only the backing driver
    | changes. Code that must choose a signed URL vs. a local file path checks
    | the disk's *driver* (config('filesystems.disks.<name>.driver') === 's3'),
    | never the disk name, because S3 disks have no on-disk ->path().
    |
    */

    'disks' => (function () {
        $useS3 = env('USER_CONTENT_DISK', 'local') === 's3';

        // NOTE: no `visibility` key on the S3 disks. The `1in.me` bucket has
        // ACLs disabled (Object Ownership = bucket-owner-enforced), so any
        // PutObject that carries an x-amz-acl header is rejected with
        // AccessControlListNotSupported. Object access is governed entirely by
        // the bucket policy + CloudFront (public reads) and pre-signed
        // temporary URLs (private reads) — never by per-object ACLs.
        $s3 = [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ];

        return [

            'local' => [
                'driver' => 'local',
                'root' => storage_path('app/private'),
                // serve=false: do NOT let Laravel auto-register its built-in
                // `storage/{path}` (storage.local) route. It points at the
                // private disk and would shadow our own /storage/{path}
                // fallback that bridges legacy public-disk URLs to CloudFront.
                'serve' => false,
                'throw' => false,
                'report' => false,
            ],

            'public' => $useS3
                ? $s3
                : [
                    'driver' => 'local',
                    'root' => storage_path('app/public'),
                    'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
                    'visibility' => 'public',
                    'throw' => false,
                    'report' => false,
                ],

            'user_files' => $useS3
                ? $s3
                : [
                    'driver' => 'local',
                    'root' => storage_path('app/user-files'),
                    'visibility' => 'private',
                    'throw' => false,
                    'report' => false,
                ],

            'admin_assets' => $useS3
                ? $s3
                : [
                    'driver' => 'local',
                    'root' => storage_path('app/admin-assets'),
                    'visibility' => 'private',
                    'throw' => false,
                    'report' => false,
                ],

            's3' => $s3,

        ];
    })(),

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
