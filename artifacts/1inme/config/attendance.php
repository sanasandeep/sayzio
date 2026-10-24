<?php

return [
    // Reuse the configured S3 bucket. Photo bytes are encrypted by the app before upload.
    'evidence_bucket' => env('ATTENDANCE_EVIDENCE_BUCKET') ?: env('AWS_BUCKET'),
];
