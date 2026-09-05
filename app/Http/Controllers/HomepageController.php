<?php

namespace App\Http\Controllers;

use App\Domain\Homepage\Support\HomepageHeroPresenter;
use Illuminate\Contracts\View\View;

final class HomepageController
{
    public function __invoke(HomepageHeroPresenter $presenter): View
    {
        return view('welcome', ['homepageHero' => $presenter->present()]);
    }
}
