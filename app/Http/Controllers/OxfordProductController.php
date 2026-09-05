<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\Support\OxfordProductPresenter;
use Illuminate\Contracts\View\View;

final class OxfordProductController
{
    public function __invoke(OxfordProductPresenter $presenter): View
    {
        return view('frontend.products.taylor-oxford-shirt', ['oxfordProduct' => $presenter->resolve()]);
    }
}
