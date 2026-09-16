<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Homepage\Support\HomepageViewData;
use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class SiteContentController extends Controller
{
    public function navigation(EnsureSiteContent $ensure): View
    {
        $actor = $this->actor();
        $primary = $ensure->handle($actor, SiteContentTypeRegistry::PRIMARY_NAVIGATION);
        $footer = $ensure->handle($actor, SiteContentTypeRegistry::FOOTER_NAVIGATION);

        return view('admin.site-content.navigation-index', compact('primary', 'footer'));
    }

    public function navigationShow(SiteContent $siteContentResource): View
    {
        $this->assertNavigation($siteContentResource);

        return view('admin.site-content.resource', ['resource' => $siteContentResource, 'mode' => 'show']);
    }

    public function navigationEdit(SiteContent $siteContentResource): View
    {
        $this->assertNavigation($siteContentResource);
        Gate::authorize(app(SiteContentTypeRegistry::class)->get($siteContentResource->type)->permission('edit'));

        return view('admin.site-content.resource', ['resource' => $siteContentResource, 'mode' => 'edit']);
    }

    public function announcements(Request $request): View
    {
        $query = SiteContent::query()->where('type', SiteContentTypeRegistry::ANNOUNCEMENT)->with('publicationState')->orderByDesc('updated_at');
        if ($search = trim((string) $request->query('search'))) {
            $query->where('title', 'like', "%{$search}%");
        }
        if ($request->query('lifecycle') === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif ($request->query('lifecycle') === 'active') {
            $query->whereNull('archived_at');
        }
        if ($request->query('schedule') === 'scheduled') {
            $query->whereHas('publicationState', fn ($state) => $state->where('candidate_state', 'scheduled'));
        }

        return view('admin.site-content.announcements-index', ['announcements' => $query->paginate(20)->withQueryString()]);
    }

    public function announcementCreate(): View
    {
        return view('admin.site-content.announcement-create');
    }

    public function announcementStore(Request $request, EnsureSiteContent $ensure): RedirectResponse
    {
        Gate::authorize('announcements.create');
        $validated = $request->validate(['title' => ['required', 'string', 'max:160']]);
        $resource = $ensure->handle($this->actor(), SiteContentTypeRegistry::ANNOUNCEMENT, null, $validated['title']);

        return redirect()->route('admin.content.announcements.edit', $resource);
    }

    public function announcementShow(SiteContent $siteContentResource): View
    {
        $this->assertType($siteContentResource, SiteContentTypeRegistry::ANNOUNCEMENT);

        return view('admin.site-content.resource', ['resource' => $siteContentResource, 'mode' => 'show']);
    }

    public function announcementEdit(SiteContent $siteContentResource): View
    {
        $this->assertType($siteContentResource, SiteContentTypeRegistry::ANNOUNCEMENT);
        Gate::authorize('announcements.edit');

        return view('admin.site-content.resource', ['resource' => $siteContentResource, 'mode' => 'edit']);
    }

    public function settings(EnsureSiteContent $ensure): View
    {
        $resource = $ensure->handle($this->actor(), SiteContentTypeRegistry::SITE_PROFILE);

        return view('admin.site-content.resource', compact('resource') + ['mode' => 'settings']);
    }

    public function preview(SiteContent $siteContentResource, ContentRevision $revision): Response
    {
        Gate::authorize(app(SiteContentTypeRegistry::class)->get($siteContentResource->type)->permission('preview'));
        abort_unless($revision->resource_type === SiteContent::class && $revision->resource_id === $siteContentResource->getKey(), 404);

        return response(view('admin.site-content.preview', [
            'siteContent' => $siteContentResource,
            'revision' => $revision,
            'renderUrl' => \URL::temporarySignedRoute('preview.site-content.render', now()->addMinutes(15), [$siteContentResource, $revision]),
        ]))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function renderPreview(SiteContent $siteContentResource, ContentRevision $revision, ResolvePublicSiteChrome $chrome): Response
    {
        Gate::authorize(app(SiteContentTypeRegistry::class)->get($siteContentResource->type)->permission('preview'));
        abort_unless($revision->resource_type === SiteContent::class && $revision->resource_id === $siteContentResource->getKey(), 404);
        $chrome->preview($siteContentResource, $revision);

        return response(view('welcome', app(HomepageViewData::class)->resolve()))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function assertNavigation(SiteContent $resource): void
    {
        abort_unless(in_array($resource->type, [SiteContentTypeRegistry::PRIMARY_NAVIGATION, SiteContentTypeRegistry::FOOTER_NAVIGATION], true), 404);
        Gate::authorize('navigation.view');
    }

    private function assertType(SiteContent $resource, string $type): void
    {
        abort_unless($resource->type === $type, 404);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
