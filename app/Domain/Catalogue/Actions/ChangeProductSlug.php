<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ChangeProductSlug
{
    public function __construct(
        private ProductStateFingerprint $states,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, string $expected, string $slug): Product
    {
        $slug = Str::slug($slug);
        if (mb_strlen($slug) < 3 || mb_strlen($slug) > 160) {
            throw new InvalidArgumentException('Product slug must normalize to between 3 and 160 characters.');
        }

        return DB::transaction(function () use ($actor, $product, $expected, $slug): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($locked))) {
                throw new StaleCatalogueState;
            }
            if ($locked->archived_at !== null) {
                throw new InvalidArgumentException('Archived Products are read-only.');
            }
            if ($locked->slug === $slug) {
                return $locked;
            }
            if (Product::query()->where('slug', $slug)->whereKeyNot($locked->id)->exists()) {
                throw new InvalidArgumentException('Product slug is already in use.');
            }

            $before = $locked->slug;
            try {
                $locked->forceFill([
                    'slug' => $slug,
                    'lock_version' => $locked->lock_version + 1,
                ])->save();
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    throw new InvalidArgumentException('Product slug is already in use.', previous: $exception);
                }
                throw $exception;
            }

            $this->audit->handle(
                'product.slug.changed',
                $locked,
                $actor,
                ['slug' => $before],
                ['slug' => $slug],
            );

            return $locked;
        }, 3);
    }
}
