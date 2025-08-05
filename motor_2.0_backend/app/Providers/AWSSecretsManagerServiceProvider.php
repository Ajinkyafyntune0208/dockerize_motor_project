<?php
namespace App\Providers;

use Aws\SecretsManager\SecretsManagerClient;
use Illuminate\Support\ServiceProvider;

class AWSSecretsManagerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (env('LOAD_AWS_SECRETS') === true) {

            $client = new SecretsManagerClient([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
            ]);

            $details = self::getCredential(env("SECRET_MANAGER_NAME"), $client);

            self::setMysqlDatabaseDetails($details);
            self::setAppKey($details);
            self::setMongoDBDetails($details);
            self::setAwsS3Details($details);
            self::setSmtpDetails($details);
        }
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

    protected static function setMysqlDatabaseDetails($details)
    {
        config([
            'database.connections.mysql.host' => $details->motor_db_host,
            'database.connections.mysql.port' => $details->motor_db_port,
            'database.connections.mysql.database' => $details->motor_db_database,
            'database.connections.mysql.username' => $details->motor_db_username,
            'database.connections.mysql.password' => $details->motor_db_password,
        ]);
    }

    protected static function setAppKey($details)
    {
        config([
            'app.key' => $details->motor_app_key ?? env('APP_KEY'),
        ]);
    }

    protected static function setMongoDBDetails($details)
    {
        config([
            'database.connections.dashboard-mongodb.driver' => 'mongodb',
            'database.connections.dashboard-mongodb.host' => $details->mongo_common_db_host ?? null,
            'database.connections.dashboard-mongodb.port' => (int) $details->mongo_common_db_port ?? null,
            'database.connections.dashboard-mongodb.database' => $details->mongo_common_db_database ?? null,
            'database.connections.dashboard-mongodb.username' => $details->mongo_motor_db_username ?? null,
            'database.connections.dashboard-mongodb.password' => $details->mongo_motor_db_password ?? null,
            'database.connections.dashboard-mongodb.options.database' => $details->mongo_common_db_authentication_database ?? null,
            'database.connections.dashboard-mongodb.options.retryWrites' => (boolean) ($details->mongo_common_db_retry_writes ?? null),
            'database.connections.dashboard-mongodb.options.ssl' => (boolean) ($details->mongo_common_db_ssl_connection ?? null),
        ]);
        if (!empty($details->mongo_common_db_ca_file_path)) {
            config([
                'database.connections.dashboard-mongodb.options.tlsCAFile' => $details->mongo_common_db_ca_file_path,
            ]);
        }
    }

    protected static function setAwsS3Details($details)
    {
        config([
            'filesystems.disks.s3.key' => $details->s3_common_key ?? null,
            'filesystems.disks.s3.secret' => $details->s3_common_secret ?? null,
            # `AWS_DEFAULT_REGION` should be mentioned in the .env file only. So commenting the below code.
            // 'filesystems.disks.s3.region' => $details->s3_common_region ?? null,
            'filesystems.disks.s3.bucket' => $details->s3_common_bucket ?? null,
            'filesystems.disks.s3.url' => $details->s3_common_url ?? null,
            'filesystems.disks.s3.endpoint' => $details->s3_common_endpoint ?? null,
            'filesystems.disks.s3.root' => $details->motor_s3_root ?? null,
        ]);
    }

    protected static function setSmtpDetails($details)
    {
        config([
            'mailers.smtp.host' => $details->smtp_common_host ?? 'smtp.mailgun.org',
            'mailers.smtp.port' => $details->smtp_common_port ?? 587,
            'mailers.smtp.encryption' => $details->smtp_common_encryption ?? 'tls',
            'mailers.smtp.username' => $details->smtp_common_username ?? null,
            'mailers.smtp.password' => $details->smtp_common_password ?? null,
        ]);
    }
}
