<?php

use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Publishing\Actions\ApprovePageRevision;
use App\Domain\Publishing\Actions\PublishApprovedPageRevision;
use App\Domain\Publishing\Actions\RequestPageChanges;
use App\Domain\Publishing\Actions\ScheduleApprovedPageRevision;
use App\Domain\Publishing\Actions\SubmitPageForReview;
use App\Domain\Publishing\Support\PublicationFingerprint;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$password = getenv('BE4F_EVIDENCE_PASSWORD');
if (! is_string($password) || strlen($password) < 12) {
    throw new RuntimeException('BE4F_EVIDENCE_PASSWORD must be at least 12 characters.');
}

app(ProvisionRegisteredAccess::class)->handle();
$cms = User::query()->updateOrCreate(
    ['email' => 'be4f.cms@example.test'],
    ['name' => 'BE-4F CMS Manager', 'password' => Hash::make($password), 'email_verified_at' => now()],
);
$cms->forceFill(['email_verified_at' => now()])->save();
app(ControlledRoleMutation::class)->run(fn () => $cms->syncRoles([RoleRegistry::CMS_MANAGER]));
$ordinary = User::query()->updateOrCreate(
    ['email' => 'be4f.user@example.test'],
    ['name' => 'BE-4F Ordinary User', 'password' => Hash::make($password), 'email_verified_at' => now()],
);
$ordinary->forceFill(['email_verified_at' => now()])->save();

$fingerprint = fn ($page): string => app(PublicationFingerprint::class)->for($page->fresh());
$make = fn (string $title, string $slug) => app(CreatePageDraft::class)->handle($cms, 'standard', $title, $slug, 'en', 'standard_page');

$review = $make('Review candidate', 'be4f-review-candidate');
app(SubmitPageForReview::class)->handle($cms, $review, 'Ready for controlled review evidence.', $fingerprint($review));

$changes = $make('Changes requested candidate', 'be4f-changes-requested');
app(SubmitPageForReview::class)->handle($cms, $changes, 'Submit for review.', $fingerprint($changes));
app(RequestPageChanges::class)->handle($cms, $changes, 'Clarify the editorial introduction.');

$approved = $make('Approved candidate', 'be4f-approved-candidate');
app(SubmitPageForReview::class)->handle($cms, $approved, 'Submit for approval.', $fingerprint($approved));
app(ApprovePageRevision::class)->handle($cms, $approved, 'Approved for designation.', $fingerprint($approved));

$scheduled = $make('Scheduled candidate', 'be4f-scheduled-candidate');
app(SubmitPageForReview::class)->handle($cms, $scheduled, 'Submit for scheduling.', $fingerprint($scheduled));
app(ApprovePageRevision::class)->handle($cms, $scheduled, 'Approved for schedule.', $fingerprint($scheduled));
app(ScheduleApprovedPageRevision::class)->handle($cms, $scheduled, now('UTC')->addDay(), $fingerprint($scheduled));

$published = $make('Designated published page', 'be4f-designated-published');
app(SubmitPageForReview::class)->handle($cms, $published, 'Submit for publication.', $fingerprint($published));
app(ApprovePageRevision::class)->handle($cms, $published, 'Approved for publication.', $fingerprint($published));
app(PublishApprovedPageRevision::class)->handle($cms, $published, $fingerprint($published));

echo json_encode([
    'review_id' => $review->id,
    'approved_id' => $approved->id,
    'scheduled_id' => $scheduled->id,
    'published_id' => $published->id,
], JSON_THROW_ON_ERROR);
