<?php

namespace App\Providers;

use App\Domain\Catalogue\Support\StorefrontShopNavigationPresenter;
use App\Domain\Content\Contracts\RichTextSanitizer;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Snippe\SnippePaymentGateway;
use App\Domain\Payments\Support\CommerceLifecycleMutation;
use App\Domain\PublicProjection\Data\PublicContactView;
use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use App\Domain\Publishing\Contracts\PublicationPolicy;
use App\Domain\Publishing\Support\CodeOwnedPublicationPolicy;
use App\Infrastructure\Content\SymfonyRichTextSanitizer;
use App\Infrastructure\Media\Cloudinary\CloudinaryMediaProvider;
use App\Infrastructure\Media\Testing\DeterministicMediaProvider;
use App\Models\User;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, SnippePaymentGateway::class);
        $this->app->singleton(CommerceLifecycleMutation::class);
        $this->app->singleton(ControlledRoleMutation::class);
        $this->app->singleton(RichTextSanitizer::class, SymfonyRichTextSanitizer::class);
        $this->app->singleton(PublicationPolicy::class, CodeOwnedPublicationPolicy::class);
        $this->app->singleton(ResolvePublicSiteChrome::class);
        $this->app->bind(MediaProvider::class, function () {
            if (config('media.provider') === 'deterministic') {
                if (app()->environment('production')) {
                    throw new \LogicException('The deterministic media provider is prohibited in production.');
                }

                return app(DeterministicMediaProvider::class);
            }

            return app(CloudinaryMediaProvider::class);
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configurePublicSiteContent();
    }

    private function configurePublicSiteContent(): void
    {
        View::composer(['frontend.*', 'layouts.frontend'], function ($view): void {
            $chrome = app(ResolvePublicSiteChrome::class)->resolve();
            $view->with('publicSiteChrome', $chrome);
            $view->with('storefrontContact', new PublicContactView($chrome->profile));
            $view->with('shopNavigation', app(StorefrontShopNavigationPresenter::class)->present());
        });
    }

    private function configureAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            if (! PermissionRegistry::contains($ability) || ! $user->hasRole(RoleRegistry::SUPER_ADMINISTRATOR)) {
                return null;
            }

            Log::channel(config('logging.default'))->notice('Super Administrator permission bypass used.', [
                'user_id' => $user->getKey(),
                'ability' => $ability,
            ]);

            return true;
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
