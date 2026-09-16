<?php

namespace Tests\Support;

final class StorefrontMarkup
{
    /** Remove inert projection copies and script/style text before counting rendered regions. */
    public static function active(string $html): string
    {
        $html = preg_replace('~<template\b[^>]*>.*?</template>~is', '', $html);

        return preg_replace('~(<(?:script|style)\b[^>]*>).*?(</(?:script|style)>)~is', '$1$2', $html);
    }
}
