<?php

namespace App\View;

/**
 * The store's WhatsApp number from CMS > Settings > General: as typed for display,
 * and as digits for tel: and wa.me links.
 */
final readonly class StoreWhatsApp
{
    /** The number the store launched with, and the setting's default. Product descriptions quote it as plain text. */
    public const ORIGINAL_NUMBER = '+91-814-814-62-62';

    public string $digits;

    public function __construct(public string $display)
    {
        $this->digits = (string) preg_replace('/\D/', '', $display);
    }

    /**
     * Shows the current number wherever saved text quotes the original one, so product
     * descriptions, including ones copied in by a later store import, follow the setting.
     */
    public function inText(string $text): string
    {
        return str_replace(self::ORIGINAL_NUMBER, $this->display, $text);
    }
}
