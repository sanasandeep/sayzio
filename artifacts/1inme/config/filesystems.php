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

    'default' => env('FILESYSTEM_DISK', 's3'),

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
    | User-Content Storage Driver — S3-only, no local fallback
    |--------------------------------------------------------------------------
    |
    | User-facing disks (`public`, `user_files`, `admin_assets`) are ALWAYS
    | backed by S3 — there is no local-disk mode for user content anymore.
    | This is deliberate: user files must be durable, CDN-servable, and never
    | silently written to ephemeral local disk. The disk *names* stay the
    | same so the hundreds of `Storage::disk('public'|'user_files'|
    | 'admin_assets')` call sites and the `disk` value stamped on records
    | keep resolving — only the backing driver is S3. Code that must choose a
    | signed URL vs. a local file path checks the disk's *driver*
    | (config('filesystems.disks.<name>.driver') === 's3'), never the disk
    | name, because S3 disks have no on-disk ->path().
    |
    | If AWS_* credentials are missing/misconfigured, the S3 disk array below
    | still resolves (Laravel builds it lazily), but any actual write/read
    | fails loudly with an S3 exception — UserFile::createFromUpload() also
    | pre-flight checks credentials and throws a clear RuntimeException
    | before ever attempting a write, so misconfiguration is obvious instead
    | of degrading to local storage.
    |
    */

    'disks' => (function () {
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
                // This "local" disk is used only for non-user-content
                // framework needs (e.g. transient temp files) — it is never
                // the backing store for public/user_files/admin_assets.
                'serve' => false,
                'throw' => false,
                'report' => false,
            ],

            'public' => $s3,

            'user_files' => $s3,

            'admin_assets' => $s3,

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
