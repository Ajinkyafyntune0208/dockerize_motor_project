<?php

namespace Database\Seeders;

use App\Models\PasswordPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddDataInPassordPolicyTable extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PasswordPolicy::insert([
            [
                'label' => 'Minimum password length',
                'key' => 'password.minLength',
                'value' => '8',
            ],
            [
                'label' => 'Uppercase letter',
                'key' => 'password.upperCaseLetter',
                'value' => 'Y',
            ],
            [
                'label' => 'Lowercase letter',
                'key' => 'password.lowerCaseLetter',
                'value' => 'Y',
            ],
            [
                'label' => 'At least one number',
                'key' => 'password.atLeastOneNumber',
                'value' => 'Y',
            ],
            [
                'label' => 'At least one symbol',
                'key' => 'password.atLeastOneSymbol',
                'value' => 'Y',
            ],
            [
                'label' => 'Password expire in days',
                'key' => 'password.passExpireInDays',
                'value' => '60',
            ]
        ]);
    }
}
