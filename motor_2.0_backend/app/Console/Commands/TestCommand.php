<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:kafka';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a test kafka command';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Running Kafka Test Command....");
        $message = new Message(body:['test' => 'message', 'kafkatest' => 'renewbuy']);
        //$producer = Kafka::publishOn('cv-vehicle-new', 'b-2.prod-fyntune.fuetki.c3.kafka.ap-south-1.amazonaws.com:9094')
        $producer = Kafka::publishOn('commercial-vehicle-new', 'b-1.prod-fyntune.fuetki.c3.kafka.ap-south-1.amazonaws.com:9094')
        //$producer = Kafka::publishOn('cv-vehicle-new')
            ->withConfigOptions([
                'security.protocol' => 'SSL',
                //'security.protocol' => 'plaintext',
                //"ssl.ca.location" => "/home/devops/kafka.client.truststore.jks",
                "ssl.keystore.location" => "/home/devops/kafka/kafka.client.keystore.jks",
                "ssl.keystore.password" => "prod-fyntune",
                "ssl.key.password" => "prod-fyntune",
            ])
            ->withMessage($message)
            ->withDebugEnabled();
        try {
            $producer->send();
        } catch (Exception $e) {
            $this->info("Script failed" . $e);
        }
        dd($producer);

        $this->info("###############################################################");
    }
}
