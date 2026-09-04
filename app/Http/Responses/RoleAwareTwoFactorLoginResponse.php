<?php

namespace App\Http\Responses;

use App\Domain\Identity\Support\PostAuthenticationDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

final class RoleAwareTwoFactorLoginResponse implements TwoFactorLoginResponse
{
    public function __construct(
        private readonly PostAuthenticationDestination $destination,
    ) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->to($this->destination->for($request->user()));
    }
}
