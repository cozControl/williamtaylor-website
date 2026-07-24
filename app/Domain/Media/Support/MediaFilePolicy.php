<?php

namespace App\Domain\Media\Support;

final class MediaFilePolicy
{
    public const IMAGE_MAX_BYTES = 20 * 1024 * 1024;

    public const VIDEO_MAX_BYTES = 250 * 1024 * 1024;

    public const VIDEO_MAX_DURATION_MS = 300_000;

    public const MAX_BATCH = 10;

    public const IMAGE_FORMATS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    public const VIDEO_FORMATS = ['mp4', 'webm'];
}
