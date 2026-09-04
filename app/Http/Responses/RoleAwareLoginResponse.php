<?php

namespace App\Http\Responses;

use App\Domain\Identity\Support\PostAuthenticationDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse;

final class RoleAwareLoginResponse implements LoginResponse
{
    public function __construct(
        private readonly PostAuthenticationDestination $destination,
    ) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect()->to($this->destination->for($request->user()));
    }
}
