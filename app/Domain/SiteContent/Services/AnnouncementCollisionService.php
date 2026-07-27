<?php

namespace App\Domain\SiteContent\Services;

use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class AnnouncementCollisionService
{
    public function assertAvailable(SiteContent $announcement, CarbonInterface $startsAt, ?CarbonInterface $endsAt = null): void
    {
        if ($announcement->type !== SiteContentTypeRegistry::ANNOUNCEMENT) {
            return;
        }
        $start = CarbonImmutable::instance($startsAt)->utc();
        $end = $endsAt ? CarbonImmutable::instance($endsAt)->utc() : null;
        $others = SiteContent::query()->where('type', SiteContentTypeRegistry::ANNOUNCEMENT)->whereKeyNot($announcement->getKey())->whereNull('archived_at')->with(['publicationState.candidateRevision', 'publicationState.currentPublicRevision'])->get();
        foreach ($others as $other) {
            $state = $other->publicationState;
            if ($state === null) {
                continue;
            }
            $otherStart = null;
            $otherEnd = null;
            if ($state->candidate_state?->value === 'scheduled' && $state->scheduled_for !== null) {
                $otherStart = $state->scheduled_for->utc();
                $otherEnd = $this->payloadEnd($state->candidateRevision->payload ?? []);
            } elseif ($state->current_public_revision_id !== null) {
                $payload = $state->currentPublicRevision->payload ?? [];
                $otherStart = $this->payloadStart($payload) ?? CarbonImmutable::create(1970, 1, 1, 0, 0, 0, 'UTC');
                $otherEnd = $this->payloadEnd($payload);
            }
            if ($otherStart !== null && $this->overlaps($start, $end, $otherStart, $otherEnd)) {
                $period = $otherStart->timezone('Africa/Dar_es_Salaam')->toDateTimeString().' to '.($otherEnd?->timezone('Africa/Dar_es_Salaam')->toDateTimeString() ?? 'open-ended');
                throw ValidationException::withMessages(['schedule' => "Announcement conflicts with [{$other->title}] effective {$period}. Cancel, unpublish, or adjust its time."]);
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function payloadStart(array $payload): ?CarbonImmutable
    {
        $value = $payload['effective_from'] ?? null;

        return $value ? CarbonImmutable::parse($value)->utc() : null;
    }

    /** @param array<string, mixed> $payload */
    private function payloadEnd(array $payload): ?CarbonImmutable
    {
        $value = $payload['effective_until'] ?? null;

        return $value ? CarbonImmutable::parse($value)->utc() : null;
    }

    private function overlaps(CarbonInterface $aStart, ?CarbonInterface $aEnd, CarbonInterface $bStart, ?CarbonInterface $bEnd): bool
    {
        return ($aEnd === null || $bStart->lt($aEnd)) && ($bEnd === null || $aStart->lt($bEnd));
    }
}
