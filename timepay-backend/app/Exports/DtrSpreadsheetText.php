<?php

namespace App\Exports;

/**
 * Protect text controlled by employees or company administrators from being
 * interpreted as a spreadsheet formula.
 */
class DtrSpreadsheetText
{
    public static function safe(string $value): string
    {
        if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
