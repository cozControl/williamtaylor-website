<?php

namespace App\Domain\Publishing\Enums;

enum CandidateState: string
{
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
}
