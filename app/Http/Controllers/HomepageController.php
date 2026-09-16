<?php

namespace App\Http\Controllers;

use App\Domain\Homepage\Support\HomepageViewData;
use Illuminate\Contracts\View\View;

final class HomepageController
{
    public function __invoke(HomepageViewData $data): View
    {
        return view('welcome', $data->resolve());
    }
}
