<?php

namespace App\Http\Controllers;

use App\Domain\Campaign\Support\PreOrderCampaignPresenter;
use Illuminate\Contracts\View\View;

final class PreOrderController
{
    public function __invoke(PreOrderCampaignPresenter $presenter): View
    {
        return view('frontend.pre-order', ['preOrderCampaigns' => $presenter->all()]);
    }
}
