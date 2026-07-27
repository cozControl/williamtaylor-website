<?php

namespace App\Domain\SiteContent\Support;

use App\Domain\Identity\Support\PermissionRegistry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SiteContentTypeRegistry
{
    public const PRIMARY_NAVIGATION = 'primary_navigation';

    public const FOOTER_NAVIGATION = 'footer_navigation';

    public const ANNOUNCEMENT = 'announcement';

    public const SITE_PROFILE = 'site_profile';

    /** @return array<string, SiteContentTypeDefinition> */
    public function all(): array
    {
        return [
            self::PRIMARY_NAVIGATION => $this->navigation(self::PRIMARY_NAVIGATION, 'Primary navigation', true),
            self::FOOTER_NAVIGATION => $this->navigation(self::FOOTER_NAVIGATION, 'Footer navigation', false),
            self::ANNOUNCEMENT => $this->announcement(),
            self::SITE_PROFILE => $this->siteProfile(),
        ];
    }

    public function get(string $type): SiteContentTypeDefinition
    {
        return $this->all()[$type] ?? throw new \InvalidArgumentException("Unknown Site Content type [{$type}].");
    }

    public function checksum(): string
    {
        return hash('sha256', implode('|', array_map(
            fn (SiteContentTypeDefinition $definition): string => $definition->policyChecksum(),
            $this->all(),
        )));
    }

    private function navigation(string $key, string $label, bool $nested): SiteContentTypeDefinition
    {
        return new SiteContentTypeDefinition(
            key: $key,
            label: $label,
            description: $nested ? 'Header navigation with one child level.' : 'Storefront-grounded footer link groups.',
            singleton: true,
            permissions: $this->navigationPermissions(),
            selfApprovalAllowed: true,
            maximumItems: $nested ? 12 : 3,
            mediaRoles: [],
            defaults: fn (): array => $nested ? ['items' => []] : ['groups' => [
                ['key' => 'company', 'label' => 'Company', 'links' => []],
                ['key' => 'customer_care', 'label' => 'Customer Care', 'links' => []],
                ['key' => 'legal', 'label' => 'Legal', 'links' => []],
            ]],
            validator: fn (array $payload): array => $nested
                ? $this->validatePrimaryNavigation($payload)
                : $this->validateFooterNavigation($payload),
            comparator: fn (array $before, array $after): array => $this->semanticDiff($before, $after, 'navigation'),
        );
    }

    private function announcement(): SiteContentTypeDefinition
    {
        return new SiteContentTypeDefinition(
            key: self::ANNOUNCEMENT,
            label: 'Announcement',
            description: 'An independently governed plain-text storefront announcement.',
            singleton: false,
            permissions: [
                'view' => PermissionRegistry::ANNOUNCEMENTS_VIEW,
                'create' => PermissionRegistry::ANNOUNCEMENTS_CREATE,
                'edit' => PermissionRegistry::ANNOUNCEMENTS_EDIT,
                'preview' => PermissionRegistry::ANNOUNCEMENTS_PREVIEW,
                'review' => PermissionRegistry::ANNOUNCEMENTS_REVIEW,
                'approve' => PermissionRegistry::ANNOUNCEMENTS_APPROVE,
                'publish' => PermissionRegistry::ANNOUNCEMENTS_PUBLISH,
                'schedule' => PermissionRegistry::ANNOUNCEMENTS_SCHEDULE,
                'unpublish' => PermissionRegistry::ANNOUNCEMENTS_UNPUBLISH,
                'archive' => PermissionRegistry::ANNOUNCEMENTS_ARCHIVE,
                'restore' => PermissionRegistry::ANNOUNCEMENTS_RESTORE,
            ],
            selfApprovalAllowed: true,
            maximumItems: 100,
            mediaRoles: [],
            defaults: fn (): array => [
                'message' => 'New announcement',
                'cta_label' => '',
                'cta' => null,
                'variant' => 'neutral',
                'dismissible' => false,
                'priority' => 0,
                'accessibility_label' => '',
                'effective_from' => null,
                'effective_until' => null,
            ],
            validator: fn (array $payload): array => $this->validateAnnouncement($payload),
            comparator: fn (array $before, array $after): array => $this->semanticDiff($before, $after, 'announcement'),
        );
    }

    private function siteProfile(): SiteContentTypeDefinition
    {
        return new SiteContentTypeDefinition(
            key: self::SITE_PROFILE,
            label: 'Site settings',
            description: 'Typed brand, contact, social, footer, and logo content.',
            singleton: true,
            permissions: [
                'view' => PermissionRegistry::SETTINGS_VIEW,
                'edit' => PermissionRegistry::SETTINGS_MANAGE,
                'preview' => PermissionRegistry::SETTINGS_PREVIEW,
                'review' => PermissionRegistry::SETTINGS_REVIEW,
                'approve' => PermissionRegistry::SETTINGS_APPROVE,
                'publish' => PermissionRegistry::SETTINGS_PUBLISH,
                'schedule' => PermissionRegistry::SETTINGS_SCHEDULE,
                'unpublish' => PermissionRegistry::SETTINGS_UNPUBLISH,
            ],
            selfApprovalAllowed: true,
            maximumItems: 1,
            mediaRoles: ['header_logo', 'footer_logo', 'footer_image'],
            defaults: fn (): array => [
                'brand' => ['name' => 'William Taylor', 'description' => '', 'copyright_holder' => '', 'header_logo_id' => null, 'footer_logo_id' => null],
                'contact' => ['email' => '', 'telephone' => '', 'whatsapp' => '', 'address' => '', 'business_hours' => '', 'cta_label' => 'Contact us'],
                'social_links' => [],
                'footer' => ['description' => '', 'copyright' => '', 'newsletter_heading' => '', 'newsletter_copy' => '', 'footer_image_id' => null],
            ],
            validator: fn (array $payload): array => $this->validateSiteProfile($payload),
            comparator: fn (array $before, array $after): array => $this->semanticDiff($before, $after, 'site profile'),
        );
    }

    /** @return array<string, string> */
    private function navigationPermissions(): array
    {
        return [
            'view' => PermissionRegistry::NAVIGATION_VIEW,
            'edit' => PermissionRegistry::NAVIGATION_EDIT,
            'preview' => PermissionRegistry::NAVIGATION_PREVIEW,
            'review' => PermissionRegistry::NAVIGATION_REVIEW,
            'approve' => PermissionRegistry::NAVIGATION_APPROVE,
            'publish' => PermissionRegistry::NAVIGATION_PUBLISH,
            'schedule' => PermissionRegistry::NAVIGATION_SCHEDULE,
            'unpublish' => PermissionRegistry::NAVIGATION_UNPUBLISH,
        ];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validatePrimaryNavigation(array $payload): array
    {
        $validated = Validator::make($payload, [
            'items' => ['present', 'array', 'max:12'],
            'items.*.key' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'max:64', 'distinct'],
            'items.*.label' => ['required', 'string', 'max:80'],
            'items.*.link' => ['required', 'array'],
            'items.*.link.type' => ['required', 'in:internal_path,external_url'],
            'items.*.link.value' => ['required', 'string', 'max:2048'],
            'items.*.new_tab' => ['required', 'boolean'],
            'items.*.visibility' => ['required', 'in:all,desktop,mobile'],
            'items.*.children' => ['array', 'max:10'],
            'items.*.children.*.key' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'max:64'],
            'items.*.children.*.label' => ['required', 'string', 'max:80'],
            'items.*.children.*.link.type' => ['required', 'in:internal_path,external_url'],
            'items.*.children.*.link.value' => ['required', 'string', 'max:2048'],
            'items.*.children.*.new_tab' => ['required', 'boolean'],
            'items.*.children.*.visibility' => ['required', 'in:all,desktop,mobile'],
        ])->validate();
        $keys = [];
        foreach ($validated['items'] as $item) {
            $this->assertLink($item['link']);
            foreach ([$item, ...$item['children']] as $entry) {
                if (isset($keys[$entry['key']])) {
                    throw ValidationException::withMessages(['items' => 'Navigation item keys must be unique across the tree.']);
                }
                $keys[$entry['key']] = true;
                $this->assertLink($entry['link']);
            }
        }

        return $validated;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validateFooterNavigation(array $payload): array
    {
        $validated = Validator::make($payload, [
            'groups' => ['required', 'array', 'size:3'],
            'groups.*.key' => ['required', 'in:company,customer_care,legal', 'distinct'],
            'groups.*.label' => ['required', 'string', 'max:80'],
            'groups.*.links' => ['array', 'max:10'],
            'groups.*.links.*.key' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'max:64'],
            'groups.*.links.*.label' => ['required', 'string', 'max:80'],
            'groups.*.links.*.link.type' => ['required', 'in:internal_path,external_url'],
            'groups.*.links.*.link.value' => ['required', 'string', 'max:2048'],
            'groups.*.links.*.new_tab' => ['required', 'boolean'],
        ])->validate();
        $keys = [];
        foreach ($validated['groups'] as $group) {
            foreach ($group['links'] as $entry) {
                if (isset($keys[$entry['key']])) {
                    throw ValidationException::withMessages(['groups' => 'Footer link keys must be unique.']);
                }
                $keys[$entry['key']] = true;
                $this->assertLink($entry['link']);
            }
        }

        return $validated;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validateAnnouncement(array $payload): array
    {
        $validated = Validator::make($payload, [
            'message' => ['required', 'string', 'max:240', 'not_regex:/[<>]/'],
            'cta_label' => ['nullable', 'string', 'max:80', 'not_regex:/[<>]/'],
            'cta' => ['nullable', 'array'],
            'cta.type' => ['required_with:cta', 'in:internal_path,external_url'],
            'cta.value' => ['required_with:cta', 'string', 'max:2048'],
            'variant' => ['required', 'in:neutral,accent,urgent'],
            'dismissible' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'between:0,100'],
            'accessibility_label' => ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ])->validate();
        if ($validated['cta'] !== null) {
            $this->assertLink($validated['cta']);
        }

        return $validated;
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validateSiteProfile(array $payload): array
    {
        $validated = Validator::make($payload, [
            'brand.name' => ['required', 'string', 'max:120'],
            'brand.description' => ['nullable', 'string', 'max:300'],
            'brand.copyright_holder' => ['nullable', 'string', 'max:160'],
            'brand.header_logo_id' => ['nullable', 'ulid'],
            'brand.footer_logo_id' => ['nullable', 'ulid'],
            'contact.email' => ['nullable', 'email:rfc', 'max:254'],
            'contact.telephone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+() .-]*$/'],
            'contact.whatsapp' => ['nullable', 'string', 'max:24', 'regex:/^\+?[1-9][0-9]{7,14}$/'],
            'contact.address' => ['nullable', 'string', 'max:500'],
            'contact.business_hours' => ['nullable', 'string', 'max:500'],
            'contact.cta_label' => ['nullable', 'string', 'max:80'],
            'social_links' => ['array', 'max:12'],
            'social_links.*.platform' => ['required', 'in:instagram,facebook,x,linkedin,youtube,tiktok,pinterest'],
            'social_links.*.url' => ['required', 'url:https', 'max:2048'],
            'social_links.*.label' => ['required', 'string', 'max:100'],
            'footer.description' => ['nullable', 'string', 'max:500'],
            'footer.copyright' => ['nullable', 'string', 'max:240'],
            'footer.newsletter_heading' => ['nullable', 'string', 'max:120'],
            'footer.newsletter_copy' => ['nullable', 'string', 'max:300'],
            'footer.footer_image_id' => ['nullable', 'ulid'],
        ])->validate();
        foreach ($validated['social_links'] as $link) {
            if (parse_url($link['url'], PHP_URL_SCHEME) !== 'https') {
                throw ValidationException::withMessages(['social_links' => 'Social links must use HTTPS.']);
            }
        }

        return $validated;
    }

    /** @param array{type: string, value: string} $link */
    private function assertLink(array $link): void
    {
        if ($link['type'] === 'internal_path') {
            if (! str_starts_with($link['value'], '/') || str_starts_with($link['value'], '//')) {
                throw ValidationException::withMessages(['link' => 'Internal links must begin with one slash.']);
            }

            return;
        }
        if (! filter_var($link['value'], FILTER_VALIDATE_URL) || parse_url($link['value'], PHP_URL_SCHEME) !== 'https') {
            throw ValidationException::withMessages(['link' => 'External links must use HTTPS.']);
        }
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     * @return list<array{field: string, change: string}>
     */
    private function semanticDiff(array $before, array $after, string $label): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[] = ['field' => (string) $field, 'change' => ucfirst($label)." {$field} changed"];
            }
        }

        return $changes;
    }
}
