<?php

namespace App\Support;

/**
 * Verifies password hashes migrated from WordPress so existing customers can log in.
 *
 * Supports:
 *   $P$...      classic WordPress (phpass, portable MD5)
 *   $wp$2y$...  WordPress 6.8+ (bcrypt over HMAC-SHA384)
 *   $2y$...     plain bcrypt (handled by Laravel already, included for completeness)
 *
 * Place at app/Support/WordPressPassword.php
 */
class WordPressPassword
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function isWordPressHash(?string $hash): bool
    {
        return is_string($hash) && (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$') || str_starts_with($hash, '$wp$'));
    }

    public static function check(string $password, string $hash): bool
    {
        if (str_starts_with($hash, '$wp$')) {
            $prehashed = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
            return password_verify($prehashed, substr($hash, 3));
        }
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return hash_equals($hash, self::phpass($password, $hash));
        }
        return password_verify($password, $hash);
    }

    private static function phpass(string $password, string $setting): string
    {
        $fail = str_starts_with($setting, '*0') ? '*1' : '*0';
        $countLog2 = strpos(self::ITOA64, $setting[3] ?? '');
        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return $fail;
        }
        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return $fail;
        }
        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        return substr($setting, 0, 12) . self::encode64($hash, 16);
    }

    private static function encode64(string $input, int $count): string
    {
        $itoa = self::ITOA64;
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= $itoa[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= $itoa[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= $itoa[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= $itoa[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
