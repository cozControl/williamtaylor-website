<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Media\Models\MediaAsset;
use Illuminate\Contracts\View\View;

final class MediaController
{
    public function index(): View
    {
        return view('admin.media.index');
    }

    public function show(MediaAsset $mediaAsset): View
    {
        return view('admin.media.show', ['mediaAsset' => $mediaAsset]);
    }
}
