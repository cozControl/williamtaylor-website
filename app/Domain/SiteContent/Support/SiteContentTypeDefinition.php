<?php

namespace App\Domain\SiteContent\Support;

use Closure;

final readonly class SiteContentTypeDefinition
{
    /**
     * @param  array<string, string>  $permissions
     * @param  list<string>  $mediaRoles
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public bool $singleton,
        public array $permissions,
        public bool $selfApprovalAllowed,
        public int $maximumItems,
        public array $mediaRoles,
        private Closure $defaults,
        private Closure $validator,
        private Closure $comparator,
    ) {}

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return ($this->defaults)();
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validate(array $payload): array
    {
        return ($this->validator)($payload);
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     * @return list<array{field: string, change: string}>
     */
    public function compare(array $before, array $after): array
    {
        return ($this->comparator)($before, $after);
    }

    public function permission(string $action): string
    {
        return $this->permissions[$action]
            ?? throw new \InvalidArgumentException("Unsupported {$this->key} action [{$action}].");
    }

    public function policyChecksum(): string
    {
        return hash('sha256', json_encode([
            $this->key,
            $this->singleton,
            $this->permissions,
            $this->selfApprovalAllowed,
            $this->maximumItems,
            $this->mediaRoles,
        ], JSON_THROW_ON_ERROR));
    }
}
