<?php
/**
 * Simple TOTP implementation (RFC 6238)
 */
class TOTP {
    /**
     * Generate a random Base32 secret
     */
    public static function generateSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Calculate the code for a given secret and timestamp
     */
    public static function getCode($secret, $time = null) {
        if ($time === null) $time = time();
        
        // Time slot (30 seconds)
        $timeSlot = floor($time / 30);
        
        $secretKey = self::base32Decode($secret);
        
        // Pack time into 8-byte binary string
        $timeBin = pack('N*', 0) . pack('N*', $timeSlot);
        
        // HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBin, $secretKey, true);
        
        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0xf;
        $otp = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a code with a window for time drift
     */
    public static function verifyCode($secret, $code, $window = 1) {
        $currentTime = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (self::getCode($secret, $currentTime + ($i * 30)) === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate otpauth URL for QR codes
     */
    public static function getQrCodeUrl($label, $secret, $issuer = 'SivisDrive') {
        $encodedLabel = rawurlencode($label);
        $encodedIssuer = rawurlencode($issuer);
        return "otpauth://totp/{$encodedIssuer}:{$encodedLabel}?secret={$secret}&issuer={$encodedIssuer}";
    }

    /**
     * Simple Base32 decoding
     */
    private static function base32Decode($base32) {
        $base32 = strtoupper($base32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $decoded = '';
        $buffer = 0;
        $bufferSize = 0;
        
        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $value = strpos($alphabet, $char);
            if ($value === false) continue;
            
            $buffer = ($buffer << 5) | $value;
            $bufferSize += 5;
            
            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $decoded .= chr(($buffer >> $bufferSize) & 0xff);
            }
        }
        return $decoded;
    }
}
