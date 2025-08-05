<?php

namespace App\Helpers\Mongo;

use MongoDB\Client;

class MongoDBConnection
{
    protected $connectionSetting;

    public function __construct()
    {
        $this->connectionSetting = [
            'host' => config('MONGO_DB_HOST', 'localhost'),
            'port' => config('MONGO_DB_PORT', 27017),
            'database' => config('MONGO_DB_DATABASE', 'forge'),
            'username' => config('MONGO_DB_USERNAME', 'forge'),
            'password' => config('MONGO_DB_PASSWORD'),
            'authSource' => config('MONGO_DB_AUTHENTICATION_DATABASE', config('MONGO_DB_DATABASE', 'forge')),
            'retryWrites' => config('MONGO_DB_RETRY_WRITES', true),
            'ssl' => config('MONGO_DB_SSL_CONNECTION', false),
            'caFile' => config('MONGO_DB_CA_FILE_PATH', null),
        ];

        $this->connectionSetting['ssl'] = filter_var($this->connectionSetting['ssl'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        $this->connectionSetting['retryWrites'] = filter_var($this->connectionSetting['retryWrites'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';

        if (empty($this->connectionSetting['caFile'])) {
            $this->connectionSetting['caFile'] = null;
        }
    }

    protected function getConnectionString(): string
    {
        $user = $this->connectionSetting['username'];
        $pass = $this->connectionSetting['password'];
        $host = $this->connectionSetting['host'];
        $port = $this->connectionSetting['port'];
        $db   = $this->connectionSetting['database'];

        $query = [
            'retryWrites' => $this->connectionSetting['retryWrites'],
            'ssl' => $this->connectionSetting['ssl'],
            'authSource' => $this->connectionSetting['authSource'],
            'tlsCAFile' => $this->connectionSetting['caFile'],
        ];

        $queryString = http_build_query($query);

        return "mongodb://{$user}:{$pass}@{$host}:{$port}/{$db}?{$queryString}";
    }

    protected function getConnectionOptions(): array
    {
        $options = [];

        if ($this->connectionSetting['ssl'] == 'true' && !empty($this->connectionSetting['caFile'])) {
            $options['tlsCAFile'] = $this->connectionSetting['caFile'];
        }

        return $options;
    }

    public function connect()
    {
        // Create a new MongoDB client instance
        // and select the database using the connection string and options
        $client = new Client(
            $this->getConnectionString(),
            $this->getConnectionOptions()
        );
        return $client->selectDatabase($this->connectionSetting['database']);
    }
}
