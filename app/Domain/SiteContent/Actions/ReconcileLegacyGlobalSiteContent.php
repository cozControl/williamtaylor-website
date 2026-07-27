<?php

namespace App\Domain\SiteContent\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use Illuminate\Support\Facades\DB;

final class ReconcileLegacyGlobalSiteContent
{
    public function __construct(
        private EnsureSiteContent $ensure,
        private RecordAuditEvent $audit,
    ) {}

    /** @return list<string> */
    public function handle(): array
    {
        $legacy = SiteContent::query()->where('key', 'global')->whereNull('type')->first();
        if ($legacy === null) {
            return [];
        }

        return DB::transaction(function () use ($legacy): array {
            $legacy = SiteContent::query()->whereKey($legacy->getKey())->lockForUpdate()->firstOrFail();
            $revision = ContentRevision::query()->findOrFail($legacy->current_draft_revision_id);
            $payload = $revision->payload;
            $actor = $legacy->updater()->firstOrFail();
            $created = [];
            $resources = [
                SiteContentTypeRegistry::PRIMARY_NAVIGATION => [
                    'items' => array_map(fn (array $item, int|string $index): array => [
                        'key' => 'legacy-header-'.((int) $index + 1),
                        'label' => $item['label'],
                        'link' => $this->legacyLink($item['url']),
                        'new_tab' => (bool) ($item['new_tab'] ?? false),
                        'visibility' => 'all',
                        'children' => [],
                    ], $payload['menus']['header'] ?? [], array_keys($payload['menus']['header'] ?? [])),
                ],
                SiteContentTypeRegistry::FOOTER_NAVIGATION => [
                    'groups' => $this->legacyFooterGroups($payload),
                ],
                SiteContentTypeRegistry::SITE_PROFILE => $this->legacyProfile($payload),
            ];
            foreach ($resources as $type => $separatedPayload) {
                $resource = $this->ensure->handle($actor, $type);
                if ($resource->revisions()->count() === 1) {
                    app(SaveSiteContentDraft::class)->handle(
                        $actor,
                        $resource,
                        $resource->current_draft_revision_id,
                        $separatedPayload,
                        'Deterministic BE-4G.1 legacy global reconciliation',
                    );
                }
                $created[] = $resource->getKey();
            }
            foreach ($payload['announcements'] ?? [] as $index => $announcement) {
                $resource = $this->ensure->handle($actor, SiteContentTypeRegistry::ANNOUNCEMENT, 'legacy-announcement-'.($index + 1), 'Legacy announcement '.($index + 1));
                if ($resource->revisions()->count() === 1) {
                    app(SaveSiteContentDraft::class)->handle($actor, $resource, $resource->current_draft_revision_id, [
                        'message' => $announcement['message'],
                        'cta_label' => $announcement['cta_label'] ?? '',
                        'cta' => empty($announcement['cta_url']) ? null : $this->legacyLink($announcement['cta_url']),
                        'variant' => 'neutral',
                        'dismissible' => false,
                        'priority' => (int) ($announcement['priority'] ?? 0),
                        'accessibility_label' => '',
                        'effective_from' => $announcement['starts_at'] ?? null,
                        'effective_until' => $announcement['ends_at'] ?? null,
                    ], 'Deterministic BE-4G.1 announcement reconciliation');
                }
                $created[] = $resource->getKey();
            }
            $this->audit->handle('site-content.architecture.reconciled', $legacy, null, ['legacy_revision_id' => $revision->getKey()], [
                'resource_ids' => $created,
                'resource_count' => count($created),
            ], reason: 'BE-4G.1 authorized architecture reconciliation');

            return $created;
        }, 3);
    }

    /** @return array{type: string, value: string} */
    private function legacyLink(string $value): array
    {
        return ['type' => str_starts_with($value, '/') && ! str_starts_with($value, '//') ? 'internal_path' : 'external_url', 'value' => $value];
    }

    /** @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private function legacyFooterGroups(array $payload): array
    {
        $groups = [];
        $approved = ['company', 'customer_care', 'legal'];
        foreach ($approved as $index => $key) {
            $source = $payload['footer']['columns'][$index] ?? [];
            $groups[] = [
                'key' => $key,
                'label' => $source['heading'] ?? str_replace('_', ' ', ucfirst($key)),
                'links' => array_map(fn (array $link, int|string $linkIndex): array => [
                    'key' => "legacy-{$key}-".((int) $linkIndex + 1),
                    'label' => $link['label'],
                    'link' => $this->legacyLink($link['url']),
                    'new_tab' => false,
                ], $source['links'] ?? [], array_keys($source['links'] ?? [])),
            ];
        }

        return $groups;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function legacyProfile(array $payload): array
    {
        return [
            'brand' => [
                'name' => $payload['identity']['site_name'] ?? 'William Taylor',
                'description' => $payload['identity']['tagline'] ?? '',
                'copyright_holder' => $payload['identity']['legal_name'] ?? '',
                'header_logo_id' => null,
                'footer_logo_id' => null,
            ],
            'contact' => [
                'email' => $payload['contact']['email'] ?? '',
                'telephone' => $payload['contact']['phone'] ?? '',
                'whatsapp' => $payload['whatsapp']['number'] ?? '',
                'address' => $payload['contact']['address'] ?? '',
                'business_hours' => $payload['contact']['business_hours'] ?? '',
                'cta_label' => 'Contact us',
            ],
            'social_links' => array_map(fn (array $link): array => [
                'platform' => $link['platform'],
                'url' => $link['url'],
                'label' => ucfirst($link['platform']),
            ], $payload['social_links'] ?? []),
            'footer' => [
                'description' => $payload['footer']['summary'] ?? '',
                'copyright' => $payload['footer']['copyright'] ?? '',
                'newsletter_heading' => '',
                'newsletter_copy' => '',
                'footer_image_id' => null,
            ],
        ];
    }
}
