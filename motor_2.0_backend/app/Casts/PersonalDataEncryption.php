<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';

class PersonalDataEncryption implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        return decryptData($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        //encrypt only for new inserted record
        //existing record will be updated using custom query builder

        if(!$model->exists) {
            $value = encryptData($value);
            return $value;
        }

        if (is_array($value)) {
            return [
                $key => $value
            ];
        }

        return $value;
    }
}
