<?php

namespace App\Domain\Media\Enums;

enum AccessibilityClassification: string
{
    case Informative = 'informative';
    case Decorative = 'decorative';
    case Complex = 'complex';
    case TextImage = 'text_image';
}
