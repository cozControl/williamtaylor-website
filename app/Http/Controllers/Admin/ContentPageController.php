<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Models\Page;
use Illuminate\Contracts\View\View;

final class ContentPageController
{
    public function index(): View
    {
        return view('admin.content.pages.index');
    }

    public function create(): View
    {
        return view('admin.content.pages.create');
    }

    public function show(Page $page): View
    {
        return view('admin.content.pages.show', ['page' => $page]);
    }

    public function edit(Page $page): View
    {
        abort_if($page->archived_at !== null, 409, 'Archived pages are read-only.');

        return view('admin.content.pages.edit', ['page' => $page]);
    }
}
