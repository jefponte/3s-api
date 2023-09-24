<?php


namespace App\Enums;

use Spatie\Enum\Laravel\Enum;

/**
 * @method static self customer()
 * @method static self provider()
 * @method static self administrator()
 * @method static self disabled()
 */
class UserRole extends Enum
{
    protected static function values(): array
    {
        return [
            'customer' => 'customer',
            'provider' => 'provider',
            'administrator' => 'administrator',
            'disabled' => 'disabled',
        ];
    }
}
