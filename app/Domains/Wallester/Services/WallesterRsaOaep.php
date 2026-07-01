<?php

namespace App\Domains\Wallester\Services;

use RuntimeException;

class WallesterRsaOaep
{
    private const HASH_ALGORITHM = 'sha256';

    public static function decrypt(string $ciphertext, string $privateKeyPem, string $label): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if ($privateKey === false) {
            throw new RuntimeException('Invalid Wallester card RSA private key.');
        }

        $details = openssl_pkey_get_details($privateKey);

        if ($details === false) {
            throw new RuntimeException('Unable to read Wallester card RSA private key details.');
        }

        $keyLength = (int) (($details['bits'] + 7) / 8);
        $encoding = '';

        if (! openssl_private_decrypt($ciphertext, $encoding, $privateKey, OPENSSL_NO_PADDING)) {
            throw new RuntimeException('Unable to perform RSA decryption for Wallester payload.');
        }

        if (strlen($encoding) > $keyLength) {
            throw new RuntimeException('Invalid RSA ciphertext length for Wallester payload.');
        }

        if (strlen($encoding) < $keyLength) {
            $encoding = str_pad($encoding, $keyLength, "\x00", STR_PAD_LEFT);
        }

        return self::decode($encoding, $label);
    }

    public static function encrypt(string $message, string $publicKeyPem, string $label): string
    {
        $publicKey = openssl_pkey_get_public($publicKeyPem);

        if ($publicKey === false) {
            throw new RuntimeException('Invalid Wallester card RSA public key.');
        }

        $details = openssl_pkey_get_details($publicKey);

        if ($details === false) {
            throw new RuntimeException('Unable to read Wallester card RSA public key details.');
        }

        $keyLength = (int) (($details['bits'] + 7) / 8);
        $encoding = self::encode($message, $label, $keyLength);
        $ciphertext = '';

        if (! openssl_public_encrypt($encoding, $ciphertext, $publicKey, OPENSSL_NO_PADDING)) {
            throw new RuntimeException('Unable to perform RSA encryption for Wallester payload.');
        }

        return $ciphertext;
    }

    private static function encode(string $message, string $label, int $keyLength): string
    {
        $hashLength = self::hashLength();
        $labelHash = hash(self::HASH_ALGORITHM, $label, true);
        $paddingLength = $keyLength - strlen($message) - (2 * $hashLength) - 2;

        if ($paddingLength < 0) {
            throw new RuntimeException('Message too long for Wallester OAEP encoding.');
        }

        $dataBlock = $labelHash.str_repeat("\x00", $paddingLength)."\x01".$message;
        $seed = random_bytes($hashLength);
        $dataBlockMask = self::mask($seed, $keyLength - $hashLength - 1);
        $maskedDataBlock = self::xorStrings($dataBlock, $dataBlockMask);
        $seedMask = self::mask($maskedDataBlock, $hashLength);
        $maskedSeed = self::xorStrings($seed, $seedMask);

        return "\x00".$maskedSeed.$maskedDataBlock;
    }

    private static function decode(string $encoding, string $label): string
    {
        $hashLength = self::hashLength();
        $keyLength = strlen($encoding);

        if ($keyLength < (2 * $hashLength) + 2) {
            throw new RuntimeException('Invalid Wallester OAEP encoding length.');
        }

        if ($encoding[0] !== "\x00") {
            throw new RuntimeException('Invalid Wallester OAEP leading byte.');
        }

        $maskedSeed = substr($encoding, 1, $hashLength);
        $maskedDataBlock = substr($encoding, 1 + $hashLength);
        $seedMask = self::mask($maskedDataBlock, $hashLength);
        $seed = self::xorStrings($maskedSeed, $seedMask);
        $dataBlockMask = self::mask($seed, $keyLength - $hashLength - 1);
        $dataBlock = self::xorStrings($maskedDataBlock, $dataBlockMask);
        $labelHash = hash(self::HASH_ALGORITHM, $label, true);

        if (! hash_equals($labelHash, substr($dataBlock, 0, $hashLength))) {
            throw new RuntimeException('Invalid Wallester OAEP label hash.');
        }

        $messageWithSeparator = substr($dataBlock, $hashLength);
        $separatorPosition = strpos($messageWithSeparator, "\x01");

        if ($separatorPosition === false) {
            throw new RuntimeException('Invalid Wallester OAEP message separator.');
        }

        $padding = substr($messageWithSeparator, 0, $separatorPosition);

        if ($padding !== str_repeat("\x00", strlen($padding))) {
            throw new RuntimeException('Invalid Wallester OAEP padding.');
        }

        return substr($messageWithSeparator, $separatorPosition + 1);
    }

    private static function mask(string $seed, int $length): string
    {
        $output = '';
        $counter = 0;

        while (strlen($output) < $length) {
            $output .= hash(self::HASH_ALGORITHM, $seed.pack('N', $counter), true);
            $counter++;
        }

        return substr($output, 0, $length);
    }

    private static function xorStrings(string $left, string $right): string
    {
        $result = '';

        for ($index = 0; $index < strlen($left); $index++) {
            $result .= $left[$index] ^ $right[$index];
        }

        return $result;
    }

    private static function hashLength(): int
    {
        return strlen(hash(self::HASH_ALGORITHM, '', true));
    }
}
