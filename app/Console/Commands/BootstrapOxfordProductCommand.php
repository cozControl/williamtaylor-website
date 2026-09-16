<?php

namespace App\Console\Commands;

use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductOption;
use App\Domain\Catalogue\Actions\CreateProductOptionValue;
use App\Domain\Catalogue\Actions\CreateProductRevision;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Actions\SetDefaultProductVariant;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Support\OxfordProductPresenter;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BootstrapOxfordProductCommand extends Command
{
    protected $signature = 'catalogue:bootstrap-oxford {--user= : Existing administrator email recorded as creator} {--collection= : Existing Collection slug for the shirt Category}';

    protected $description = 'Create the canonical Taylor Oxford Shirt once without overwriting existing product data.';

    public function handle(): int
    {
        if (Product::query()->where('slug', OxfordProductPresenter::SLUG)->exists()) {
            $this->components->info('Oxford Product already exists; no data was changed.');

            return self::SUCCESS;
        }

        $email = trim((string) $this->option('user'));
        $actor = User::query()->where('email', $email)->first();
        if ($email === '' || $actor === null) {
            $this->components->error('Provide an existing administrator with --user=email@example.com.');

            return self::FAILURE;
        }

        $collection = Collection::query()->active()->where('slug', (string) $this->option('collection'))->first();
        if ($collection === null) {
            $this->components->error('Provide an existing Category owner with --collection=collection-slug.');

            return self::FAILURE;
        }
        $created = DB::transaction(function () use ($actor, $collection): Product {
            $product = app(CreateProduct::class)->handle($actor, 'taylor-oxford-shirt', OxfordProductPresenter::SLUG);
            $category = ProductCategory::query()->firstOrCreate(
                ['collection_id' => $collection->id, 'slug' => 'mens-shirts'],
                ['name' => "Men's Shirts", 'description' => 'Tailored and casual shirts.', 'is_visible' => true, 'position' => 10, 'created_by' => $actor->id, 'updated_by' => $actor->id],
            );
            $product->forceFill(['base_price_minor' => 28500000, 'currency' => 'TZS'])->save();
            $product->categories()->attach($category->id, ['is_primary' => true, 'position' => 0]);
            app(CreateProductRevision::class)->handle($actor, $product, 0, [
                'title' => 'The Taylor Oxford Shirt',
                'short_description' => 'Hand-finished camp collar shirt in textured Italian cotton. Crossover drape with a refined boxy silhouette.',
                'description_document' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'The Taylor Oxford Shirt is the cornerstone of the William Taylor collection. Crafted from our signature textured Italian cotton, this piece commands attention through restraint — an unbuttoned camp collar, a slightly oversized boxy cut, and clean crossover detail speak volumes without effort.']]],
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Every stitch is finished by hand in our Dar es Salaam atelier. The result is a shirt that transcends seasons, moving effortlessly from a morning meeting to an evening gathering at the coast.']]],
                ]],
                'materials' => '100% Italian textured cotton. Breathable, structured, and pre-washed for an immediate softness.',
                'fit' => 'Boxy and relaxed. Model is 6\'1" wearing size M.',
                'care' => null,
                'features' => ['Crossover front panel. No buttons, no fuss. A single interior label — gold on charcoal.'],
            ]);
            $product->refresh();

            $colour = app(CreateProductOption::class)->handle($actor, $product, $product->lock_version, 'colour', 'Colour', 0);
            $product->refresh();
            $size = app(CreateProductOption::class)->handle($actor, $product, $product->lock_version, 'size', 'Size', 1);

            $colours = [];
            foreach (['ivory' => 'Ivory', 'noir' => 'Noir'] as $key => $label) {
                $colours[$key] = app(CreateProductOptionValue::class)->handle($actor, $colour, $key, $label, count($colours));
            }
            $sizes = [];
            foreach (['xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL', 'xxl' => 'XXL', '3xl' => '3XL'] as $key => $label) {
                $sizes[$key] = app(CreateProductOptionValue::class)->handle($actor, $size, $key, $label, count($sizes));
            }

            $first = null;
            $position = 0;
            foreach ($colours as $colourKey => $colourValue) {
                foreach ($sizes as $sizeKey => $sizeValue) {
                    $product->refresh();
                    $sku = $position === 0 ? 'WT-SH-001' : 'WT-SH-001-'.strtoupper($colourKey).'-'.strtoupper($sizeKey);
                    $variant = app(CreateProductVariant::class)->handle(
                        $actor,
                        $product,
                        app(ProductStateFingerprint::class)->for($product),
                        [['option_id' => $colour->id, 'value_id' => $colourValue->id], ['option_id' => $size->id, 'value_id' => $sizeValue->id]],
                        $sku,
                        null,
                        $colourValue->label.' / '.$sizeValue->label,
                        $position++,
                    );
                    $first ??= $variant;
                }
            }

            $product->refresh();
            app(SetDefaultProductVariant::class)->handle($actor, $product, $first, app(ProductStateFingerprint::class)->for($product));

            return $product->refresh();
        }, 3);

        $this->components->info("Created {$created->slug} with 2 options and 14 variants. Existing Media Assets were not changed.");

        return self::SUCCESS;
    }
}
