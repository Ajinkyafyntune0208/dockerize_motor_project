<?php

function encryptData($data)
{
    if (!empty($data)) {
       $data = encryptPiData($data);
    }
    return $data;
}


function decryptData($encryptedData)
{
    if(!empty($encryptedData)) {
        $encryptedData = decryptPiData($encryptedData);
    }
    return $encryptedData;
}

function encryptPiData($data)
{
    if (!empty($data)){
        $data = is_array($data) ? json_encode($data) : $data;
        list($key, $iv) = array_values(getKeys());
        $tag = null;
        $cipherText = openssl_encrypt(
                $data,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
        return base64_encode($iv . $tag . $cipherText);
    }
    return $data;
}

function decryptPiData($encryptedData)
{
    if(!empty($encryptedData) && !is_array($encryptedData)) {
        try {
            $data = base64_decode($encryptedData);
            list($key, $iv) = array_values(getKeys());
            $tag = substr($data, 12, 16);
            $cipherText = substr($data, 28);

            $decryptedData = openssl_decrypt(
                $cipherText,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($decryptedData === false) {
                return $encryptedData; // Decryption failed
            }
            return $decryptedData;
        } catch (Exception $e) {
            return $encryptedData; // Decryption failed
        }
    }

    return $encryptedData;
}

function getKeys()
{
    return [
        'key' => base64_decode('5IKLB7Mh5zb4/J2F3vKifkrdDlzl8uqRKtvHHjeXygw='),//key
        'iv' => base64_decode('RBr5xS8U4WSNhE+b'),//iv
    ];
}