<?php
declare(strict_types=1);

namespace App\Services;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $maxIdx = strlen(self::BASE32_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $maxIdx)];
        }
        return $secret;
    }

    public function getProvisioningUri(string $secret, string $accountEmail, string $issuer = 'ReqMaster'): string
    {
        $label = rawurlencode($issuer . ':' . $accountEmail);
        $issuerParam = rawurlencode($issuer);
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuerParam}&algorithm=SHA1&digits=" . self::DIGITS . "&period=" . self::PERIOD;
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $cleanCode = preg_replace('/\D/', '', trim($code));
        if (strlen($cleanCode) !== self::DIGITS) {
            return false;
        }

        $timeSlice = (int)floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $calculated = $this->getCode($secret, $timeSlice + $i);
            if (hash_equals($calculated, $cleanCode)) {
                return true;
            }
        }
        return false;
    }

    public function getCode(string $secret, ?int $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = (int)floor(time() / self::PERIOD);
        }

        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);

        $value = unpack('N', $hashPart)[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = 10 ** self::DIGITS;

        return str_pad((string)($value % $modulo), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function generateRecoveryCodes(int $count = 6): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        }
        return $codes;
    }

    private function base32Decode(string $secret): string
    {
        if (empty($secret)) {
            return '';
        }
        $secret = strtoupper($secret);
        $secret = preg_replace('/[^A-Z2-7]/', '', $secret);
        $binaryString = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos(self::BASE32_ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($binaryString, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }
        return $output;
    }
}
