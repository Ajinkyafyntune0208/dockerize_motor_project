<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class DashboardDataPushModel extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'dashboard_transactions';

    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);

        if (env('LOAD_AWS_SECRETS') !== true) {
            \Illuminate\Support\Facades\Config::set('database.connections.dashboard-mongodb', [
                'driver' => 'mongodb',
                'host' => config('MONGO_DB_HOST', 'localhost'),
                'port' => (int) config('MONGO_DB_PORT', 27017),
                'database' => config('MONGO_DB_DATABASE', 'forge'),
                'username' => config('MONGO_DB_USERNAME', 'forge'),
                'password' => config('MONGO_DB_PASSWORD'),
                'options' => [
                    "database" => config("MONGO_DB_AUTHENTICATION_DATABASE", config('MONGO_DB_DATABASE', 'forge')),
                    "retryWrites" => (boolean) config("MONGO_DB_RETRY_WRITES", true),
                    "ssl" => (boolean) config("MONGO_DB_SSL_CONNECTION", false),
                ],
            ]);
            if (!empty(config("MONGO_DB_CA_FILE_PATH"))) {
                \Illuminate\Support\Facades\Config::set('database.connections.dashboard-mongodb.options.tlsCAFile', config("MONGO_DB_CA_FILE_PATH"));
            }
        }
    }

    public function getConnectionName()
    {
        return "dashboard-mongodb";
    }
}
