<?php

namespace App\Domain\Publication\Enums;

enum RolloutMode: string
{
    case Static = 'static';
    case Shadow = 'shadow';
    case Enabled = 'enabled';
    case EmergencyDisabled = 'emergency_disabled';
}
