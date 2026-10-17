<?php

namespace App\Support;

/**
 * The temporary password given to a new or reset account: six digits (e.g. 482915). Easy to read
 * out, text and type on a phone; the person must replace it with their own password the first
 * time they sign in, so it only has to survive that first sign-in.
 */
class TemporaryPassword
{
    public static function make(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
