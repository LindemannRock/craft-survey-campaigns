<?php
/**
 * Survey Campaigns plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\surveycampaigns\helpers;

/**
 * Phone Helper
 *
 * Provides phone number sanitization and validation for Kuwait phone numbers.
 *
 * @author    LindemannRock
 * @package   SurveyCampaigns
 * @since     5.1.0
 */
class PhoneHelper
{
    /**
     * Minimum length for a valid phone number (digits only)
     * Kuwait: 8 digits local + optional country code
     */
    public const MIN_LENGTH = 8;

    /**
     * Maximum length for a valid phone number (digits only)
     * Kuwait: 00965 (5) + 8 digits = 13
     */
    public const MAX_LENGTH = 13;

    /**
     * Kuwait country code
     */
    public const KUWAIT_COUNTRY_CODE = '965';

    /**
     * Sanitize a phone number by removing invalid characters
     *
     * Removes:
     * - Whitespace (spaces, tabs, newlines)
     * - Hidden Unicode characters (zero-width, RTL/LTR marks)
     * - Backslashes
     * - Other non-digit characters except + at the start
     */
    public static function sanitize(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        // Remove whitespace (spaces, tabs, newlines)
        $phone = preg_replace('/\s+/', '', $phone);

        // Remove hidden Unicode characters:
        // - Zero-width spaces (U+200B-U+200D)
        // - Zero-width no-break space / BOM (U+FEFF)
        // - Bidirectional text control (U+202A-U+202E)
        // - Word joiner (U+2060)
        // - Invisible separator (U+2063)
        $phone = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{202A}-\x{202E}\x{2060}\x{2063}]+/u', '', $phone);

        // Remove backslashes
        $phone = str_replace('\\', '', $phone);

        // Preserve + at the start if present
        $hasPlus = str_starts_with($phone, '+');

        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Re-add + if it was at the start
        if ($hasPlus && !empty($phone)) {
            $phone = '+' . $phone;
        }

        return $phone === '' ? null : $phone;
    }

    /**
     * Validate a phone number
     *
     * Checks:
     * - Only contains digits (and optional + at start)
     * - Length is within acceptable range
     * - Doesn't contain letters or special characters
     */
    public static function isValid(?string $phone): bool
    {
        // Empty is valid (phone is optional)
        if ($phone === null || $phone === '') {
            return true;
        }

        // Must only contain digits, with optional + at start
        if (!preg_match('/^\+?[0-9]+$/', $phone)) {
            return false;
        }

        // Check length (digits only, excluding +)
        $digitsOnly = preg_replace('/\D/', '', $phone);
        $length = strlen($digitsOnly);

        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }

    /**
     * Validate a raw phone number (before sanitization)
     *
     * This is useful for checking if a phone number has issues that
     * need to be reported to the user.
     *
     * @return array{valid: bool, error: string|null, sanitized: string|null}
     */
    public static function validate(?string $phone): array
    {
        if ($phone === null || $phone === '') {
            return [
                'valid' => true,
                'error' => null,
                'sanitized' => null,
            ];
        }

        // Check for letters (common error)
        if (preg_match('/[a-zA-Z]/', $phone)) {
            return [
                'valid' => false,
                'error' => 'Phone number contains letters',
                'sanitized' => null,
            ];
        }

        // Sanitize and check
        $sanitized = self::sanitize($phone);

        if ($sanitized === null || $sanitized === '') {
            return [
                'valid' => false,
                'error' => 'Phone number is empty after sanitization',
                'sanitized' => null,
            ];
        }

        // Check length
        $digitsOnly = preg_replace('/\D/', '', $sanitized);
        $length = strlen($digitsOnly);

        if ($length < self::MIN_LENGTH) {
            return [
                'valid' => false,
                'error' => "Phone number too short ({$length} digits, minimum " . self::MIN_LENGTH . ')',
                'sanitized' => $sanitized,
            ];
        }

        if ($length > self::MAX_LENGTH) {
            return [
                'valid' => false,
                'error' => "Phone number too long ({$length} digits, maximum " . self::MAX_LENGTH . ')',
                'sanitized' => $sanitized,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'sanitized' => $sanitized,
        ];
    }

    /**
     * Normalize a Kuwait phone number to standard format (00965XXXXXXXX)
     *
     * Converts various formats:
     * - 965XXXXXXXX -> 00965XXXXXXXX
     * - +965XXXXXXXX -> 00965XXXXXXXX
     * - XXXXXXXX (8 digits) -> 00965XXXXXXXX
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        // Remove + and get digits only
        $digits = preg_replace('/\D/', '', $phone);

        // Already has 00965 prefix
        if (str_starts_with($digits, '00' . self::KUWAIT_COUNTRY_CODE)) {
            return $digits;
        }

        // Has 965 prefix (without 00)
        if (str_starts_with($digits, self::KUWAIT_COUNTRY_CODE)) {
            return '00' . $digits;
        }

        // Just local number (8 digits)
        if (strlen($digits) === 8) {
            return '00' . self::KUWAIT_COUNTRY_CODE . $digits;
        }

        // Return as-is if it doesn't match expected patterns
        return $digits;
    }
}
