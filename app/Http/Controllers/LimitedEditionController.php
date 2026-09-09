<?php

namespace App\Http\Controllers;

use App\Domain\Campaign\Support\LimitedEditionCampaignPresenter;
use Illuminate\Contracts\View\View;

final class LimitedEditionController
{
    public function __invoke(LimitedEditionCampaignPresenter $presenter): View
    {
        return view('frontend.limited-edition', ['limitedEditionCampaigns' => $presenter->all()]);
    }
}
