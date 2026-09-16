<?php

namespace App\Domain\Payments\Support;

use Illuminate\Validation\ValidationException;

final class TanzanianPhone
{
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s()-]/', '', trim($phone));
        if (preg_match('/^0[67][0-9]{8}$/D', $phone)) {
            $phone = '255'.substr($phone, 1);
        }
        $phone = preg_replace('/^\+/', '', $phone);
        if (! preg_match('/^255[67][0-9]{8}$/D', $phone)) {
            throw ValidationException::withMessages(['payer_phone' => 'Enter a valid Tanzanian mobile number, for example 0712 345 678.']);
        }

        return $phone;
    }
}
