<?php

namespace App\Domain\Media\Enums;

enum MediaAssetState: string
{
    case PendingUpload = 'pending_upload';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Archived = 'archived';
}
