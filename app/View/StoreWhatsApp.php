<?php

namespace App\View;

/**
 * The store's WhatsApp number: as typed for display, and as digits for tel: and wa.me links.
 */
final readonly class StoreWhatsApp
{
    /**
     * The number product descriptions and seeded CMS pages show. The Blade views write it out
     * themselves, so change them too. CMS > Settings > General's WhatsApp number is not read for now.
     */
    public const CURRENT_NUMBER = '+91 99150 18282';

    /** The number the store launched with, and the setting's default. Product descriptions quote it as plain text. */
    public const ORIGINAL_NUMBER = '+91-814-814-62-62';

    public string $digits;

    public function __construct(public string $display)
    {
        $this->digits = (string) preg_replace('/\D/', '', $display);
    }

    /**
     * Shows the current number wherever saved text quotes the original one, so product
     * descriptions, including ones copied in by a later store import, stay up to date.
     */
    public function inText(string $text): string
    {
        return str_replace(self::ORIGINAL_NUMBER, $this->display, $text);
    }
}
