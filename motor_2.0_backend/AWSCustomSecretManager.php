<?php

use Aws\SecretsManager\SecretsManagerClient;

class AWSCustomSecretManager
{
    public static function setAWSCredentials(): void
    {
        // Loading the .env file
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
        $dotenv->load();

        // Dotenv treats all the values as a string
        if (($_ENV['LOAD_AWS_SECRETS'] ?? 'false') !== 'true') {
            return;
        }

        $client = new SecretsManagerClient(
            [
                'version' => 'latest',
                'region' => $_ENV['AWS_DEFAULT_REGION'],
            ]
        );

        self::setMysqlDatabaseDetails('MOTOR_DB_DETAILS', $client);
        self::setAppKey('MOTOR_APP_KEY', $client);
        // self::setMongoDBDetails('MOTOR_MONGO_DB_DETAILS', $client);
        self::setAwsS3Details('MOTOR_AWS_S3_DETAILS', $client);
        self::setSmtpDetails('MOTOR_SMTP_DETAILS', $client);
    }

    public static function getCredential($secretID, $clientObject = null)
    {
        if (empty($clientObject)) {
            $clientObject = new SecretsManagerClient(
                [
                    'version' => 'latest',
                    'region' => $_ENV['AWS_DEFAULT_REGION'],
                ]
            );
        }

        $result = $clientObject->getSecretValue(['SecretId' => $secretID]);

        return json_decode($result['SecretString']);
    }

    protected static function setMysqlDatabaseDetails($keyName, $client)
    {
        $db_details = self::getCredential($keyName, $client);
        $GLOBALS['AWS_SM_DB_DETAILS'] = [
            'HOST' => $db_details->host ?? '127.0.0.1',
            'PORT' => $db_details->port ?? 3306,
            'DATABASE' => $db_details->database ?? 'laravel',
            'USERNAME' => $db_details->username ?? 'root',
            'PASSWORD' => $db_details->password ?? null,
        ];
    }

    protected static function setAppKey($keyName, $client)
    {
        $app_key = self::getCredential($keyName, $client);
        $GLOBALS['AWS_SM_APP_KEY'] = [
            'KEY' => $app_key->key ?? null,
        ];
    }

    protected static function setMongoDBDetails($keyName, $client)
    {
        $mongo = self::getCredential($keyName, $client);
        $GLOBALS['AWS_SM_MONGO_DETAILS'] = [
            'MONGO_DB_HOST' => $mongo->host ?? null,
            'MONGO_DB_PORT' => $mongo->port ?? null,
            'MONGO_DB_DATABASE' => $mongo->database ?? null,
            'MONGO_DB_USERNAME' => $mongo->username ?? null,
            'MONGO_DB_PASSWORD' => $mongo->password ?? null,
            'MONGO_DB_AUTHENTICATION_DATABASE' => $mongo->authentication_database ?? null,
            'MONGO_DB_RETRY_WRITES' => $mongo->retry_writes ?? null,
            'MONGO_DB_SSL_CONNECTION' => $mongo->ssl_connection ?? null,
        ];
    }

    protected static function setAwsS3Details($keyName, $client)
    {
        $s3_details = self::getCredential($keyName, $client);
        $GLOBALS['AWS_SM_S3_DETAILS'] = [
            'ACCESS_KEY_ID' => $s3_details->key ?? null,
            'SECRET_ACCESS_KEY' => $s3_details->secret ?? null,
            'DEFAULT_REGION' => $s3_details->region ?? null,
            'BUCKET' => $s3_details->bucket ?? null,
            'URL' => $s3_details->url ?? null,
            'ENDPOINT' => $s3_details->endpoint ?? null,
            'ROOT' => $s3_details->root ?? null,
        ];
    }

    protected static function setSmtpDetails($keyName, $client)
    {
        $smtp_details = self::getCredential($keyName, $client);
        $GLOBALS['AWS_SM_SMTP_DETAILS'] = [
            'HOST' => $smtp_details->host ?? 'smtp.mailgun.org',
            'PORT' => $smtp_details->port ?? 587,
            'ENCRYPTION' => $smtp_details->encryption ?? 'tls',
            'USERNAME' => $smtp_details->username ?? null,
            'PASSWORD' => $smtp_details->password ?? null,
        ];
    }

}
