<?php

use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Orders\Actions\CreateDemoOrder;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

abort_unless(app()->environment('testing') && config('demo.enabled'), 403);
$password = getenv('DEMO1C_PASSWORD');
if (! is_string($password) || strlen($password) < 12) {
    throw new RuntimeException('DEMO1C_PASSWORD is required.');
}

app(ProvisionRegisteredAccess::class)->handle();
$editor = User::create(['name' => 'DEMO-1C Page Editor', 'email' => 'demo1c.editor@example.test', 'password' => Hash::make($password)]);
$approver = User::create(['name' => 'DEMO-1C Approver', 'email' => 'demo1c.approver@example.test', 'password' => Hash::make($password)]);
foreach ([$editor, $approver] as $actor) {
    $actor->markEmailAsVerified();
}
app(ControlledRoleMutation::class)->run(function () use ($editor, $approver): void {
    $editor->assignRole(RoleRegistry::CMS_MANAGER);
    $approver->assignRole(RoleRegistry::SUPER_ADMINISTRATOR);
});

$page = app(CreatePageDraft::class)->handle($editor, 'standard', 'About', 'about', 'en', 'about');
PagePublicationState::create(['page_id' => $page->id, 'current_public_revision_id' => $page->current_draft_revision_id, 'state_version' => 1, 'last_transition_at' => now()]);
$media = MediaAsset::create([
    'provider' => 'deterministic', 'provider_asset_id' => 'demo1c-ready', 'provider_public_id' => 'demo1c/ready',
    'provider_version' => '1', 'resource_type' => 'image', 'delivery_type' => 'upload', 'format' => 'jpg',
    'mime_type' => 'image/jpeg', 'original_filename' => 'demo1c-about.jpg', 'internal_title' => 'DEMO-1C About image',
    'default_alt_text' => 'A William Taylor tailored look', 'width' => 1200, 'height' => 800, 'bytes' => 1000,
    'accessibility_classification' => 'informative', 'is_decorative' => false, 'state' => MediaAssetState::Ready,
    'confirmed_at' => now(), 'uploaded_by' => $editor->id,
]);
$order = app(CreateDemoOrder::class)->handle($approver, [
    'idempotency_key' => 'demo1c-order', 'fixture_key' => 'demo1c-order', 'customer_name' => 'DEMO-1C Customer',
    'customer_email' => 'customer@example.test', 'customer_telephone' => '+255 700 000 000',
    'delivery_address' => 'Demo Avenue, Dar es Salaam', 'delivery_instructions' => 'Browser fixture',
    'customer_note' => null, 'internal_note' => null,
    'items' => [['product_name' => 'Demo Jacket', 'variant_name' => 'Navy / M', 'sku' => 'DEMO-1C', 'quantity' => 1, 'unit_amount_minor' => 25000000]],
]);

echo json_encode([
    'editor_email' => $editor->email, 'approver_email' => $approver->email, 'page_id' => $page->id,
    'initial_revision_id' => $page->current_draft_revision_id, 'media_id' => $media->id, 'order_number' => $order->order_number,
], JSON_THROW_ON_ERROR);
