<?php

namespace Harvirsidhu\FilamentTimepicker\Support;

/**
 * Forgiving wall-clock time parser. Turns whatever a human types into a
 * canonical 24-hour `H:i` (or `H:i:s`) string, or null when it can't.
 *
 * This is the AUTHORITATIVE normalizer: it runs server-side in the field's
 * dehydration so no typed value is ever stored unnormalized, regardless of
 * what the client-side JS mirror did. The JS in
 * `resources/js/components/smart-time-picker.js` mirrors these rules for
 * instant feedback — keep the two in lockstep.
 *
 * Examples:
 *   "3:30 PM" -> "15:30"   "3p" / "3pm" -> "15:00"   "9" -> "09:00"
 *   "330" -> "03:30"        "1530" -> "15:30"         "0930" -> "09:30"
 *   "15:00" -> "15:00"      "" / "nope" / "25:00" -> null
 */
class TimeParser
{
    public static function parse(?string $value, bool $seconds = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalized = strtolower($value);

        // Pull off a trailing meridiem token: a, p, am, pm, a.m., p.m.
        $meridiem = null;

        if (preg_match('/\s*([ap])\.?m?\.?$/', $normalized, $matches)) {
            $meridiem = $matches[1];
            $normalized = trim(preg_replace('/\s*([ap])\.?m?\.?$/', '', $normalized));
        }

        $hour = null;
        $minute = 0;
        $second = 0;

        if (preg_match('/^(\d{1,2})[:.h](\d{2})(?:[:.h](\d{2}))?$/', $normalized, $matches)) {
            // "h:mm" / "hh:mm" with a colon, dot, or "h" separator (UK/MY "9.30",
            // French "9h30"), optionally with ":ss" / ".ss".
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            $second = isset($matches[3]) ? (int) $matches[3] : 0;
        } elseif (preg_match('/^\d+$/', $normalized)) {
            // Bare digits: 1-2 = hour, 3 = "h mm", 4 = "hh mm".
            $length = strlen($normalized);

            if ($length <= 2) {
                $hour = (int) $normalized;
            } elseif ($length === 3) {
                $hour = (int) substr($normalized, 0, 1);
                $minute = (int) substr($normalized, 1, 2);
            } elseif ($length === 4) {
                $hour = (int) substr($normalized, 0, 2);
                $minute = (int) substr($normalized, 2, 2);
            } else {
                return null;
            }
        } else {
            return null;
        }

        // Apply the meridiem only when the hour is in 12-hour range; a hand
        // typed "15:00 pm" keeps its unambiguous 24-hour hour.
        if ($meridiem !== null && $hour >= 1 && $hour <= 12) {
            if ($meridiem === 'p' && $hour < 12) {
                $hour += 12;
            } elseif ($meridiem === 'a' && $hour === 12) {
                $hour = 0;
            }
        }

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            return null;
        }

        return $seconds
            ? sprintf('%02d:%02d:%02d', $hour, $minute, $second)
            : sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * Format a stored `H:i`/`H:i:s` value for display, e.g. "15:30" -> "3:30 pm".
     * Returns null for blank/invalid input so the field shows an empty box.
     *
     * `$displayFormat` uses PHP date() tokens. The default `g:i a` gives the
     * non-padded, lowercase 12-hour look ("3:30 pm"); pass `g:i A` for
     * uppercase ("3:30 PM") or `h:i a` for a zero-padded hour ("03:30 pm").
     */
    public static function format(?string $value, string $displayFormat = 'g:i a'): ?string
    {
        $canonical = static::parse($value, seconds: true);

        if ($canonical === null) {
            return null;
        }

        [$hour, $minute, $second] = array_map('intval', explode(':', $canonical));

        $timestamp = mktime($hour, $minute, $second, 1, 1, 2000);

        return $timestamp === false ? null : date($displayFormat, $timestamp);
    }
}
