<?php

namespace App\Http\Controllers;

use App\Domain\PublicProjection\Services\ResolvePublicPage;
use Illuminate\View\View;

final class AboutController
{
    public function __invoke(ResolvePublicPage $resolver): View
    {
        return view('frontend.about', ['publicPage' => $resolver->resolve('about')]);
    }
}
