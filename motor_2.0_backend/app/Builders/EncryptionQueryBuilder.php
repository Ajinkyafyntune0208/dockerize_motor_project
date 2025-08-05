<?php

namespace App\Builders;
use Illuminate\Database\Eloquent\Builder;

include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';


class EncryptionQueryBuilder extends  Builder
{
    public function update(array $values)
    {
        $values = $this->getEncryptedString($values);
        return parent::update($values);
    }

    protected function getEncryptedString(array $values)
    {
        $model = $this->getModel();
        $allCasts = $model->getCasts();

        if (!empty($allCasts)) {
            foreach ($allCasts as  $key => $value) {
                if (
                    array_key_exists($key, $values) &&
                    $value == 'App\Casts\PersonalDataEncryption'
                ) {
                    $values[$key] = encryptData($values[$key]);
                }
            }
        }

        return $values;
    }
}