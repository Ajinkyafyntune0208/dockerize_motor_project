<?php

return [
    /*
     | Your kafka brokers url.
     */
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),

    /*
     | Kafka consumers belonging to the same consumer group share a group id.
     | The consumers in a group then divides the topic partitions as fairly amongst themselves as possible by
     | establishing that each partition is only consumed by a single consumer from the group.
     | This config defines the consumer group id you want to use for your project.
     */
    'consumer_group_id' => env('KAFKA_CONSUMER_GROUP_ID', 'group'),

    /*
     | After the consumer receives its assignment from the coordinator,
     | it must determine the initial position for each assigned partition.
     | When the group is first created, before any messages have been consumed, the position is set according to a configurable
     | offset reset policy (auto.offset.reset). Typically, consumption starts either at the earliest offset or the latest offset.
     | You can choose between "latest", "earliest" or "none".
     */
    'offset_reset' => env('KAFKA_OFFSET_RESET', 'latest'),

    /*
     | If you set enable.auto.commit (which is the default), then the consumer will automatically commit offsets periodically at the
     | interval set by auto.commit.interval.ms.
     */
    'auto_commit' => env('KAFKA_AUTO_COMMIT', true),

    'sleep_on_error' => env('KAFKA_ERROR_SLEEP', 5),

    'partition' => env('KAFKA_PARTITION', 0),

    /*
     | Kafka supports 4 compression codecs: none , gzip , lz4 and snappy
     */
    'compression' => 'gzip',//env('KAFKA_COMPRESSION_TYPE', 'snappy'),

    /*
     | Choose if debug is enabled or not.
     */
    'debug' => env('KAFKA_DEBUG', false),

    /*
     | Repository for batching messages together
     | Implement BatchRepositoryInterface to save batches in different storage
     */
    'batch_repository' => env('KAFKA_BATCH_REPOSITORY', \Junges\Kafka\BatchRepositories\InMemoryBatchRepository::class),

    // Premium details mapping for RB kafka dataPush
    'premiumDetails' => [
        "imt23" => "imt_23",
        "emeCover" => "eme_cover",
        "antitheftDiscount" => "anti_theft",
        "consumables" => "consumable",
        "netPremium" => "net_premium",
        "tyreSecure" => "tyre_secure",
        "tppdDiscount" => "tppd_discount",
        "defaultPaidDriver" => "ll_paid_driver",
        "llPaidCleanerPremium" => "ll_paid_cleaner",
        "llPaidConductorPremium" => "ll_paid_conductor",
        "llPaidDriverPremium" => "ll_paid_driver",
        "underwritingLoadingAmount" => "loading_amount",
        "totalLoadingAmount" => "loading_amount",
        "ncbProtection" => "ncb_protection",
        "icVehicleDiscount" => "other_discount",
        "accidentShield" => "accident_shield",
        "keyReplace" => "key_replacement",
        "basicOdPremium" => "basic_od_premium",
        "basicTpPremium" => "basic_tp_premium",
        "engineProtector" => "engine_protector",
        "finalOdPremium" => "final_od_premium",
        "finalTpPremium" => "final_tp_premium",
        "voluntaryExcess" => "voluntary_excess",
        "motorLpgCngKitValue" => "bifuel_od_premium",
        "cngLpgTp" => "bifuel_tp_premium",
        "returnToInvoice" => "return_to_invoice",
        "emergencyMedicalExpenses" => "eme_cover",
        "zeroDepreciation" => "zero_depreciation",
        "conveyanceBenefit" => "conveyance_benefit",
        "serviceTaxAmount" => "service_tax_amount",
        "finalPayableAmount" => "final_payable_amount",
        "deductionOfNcb" => "ncb_discount_premium",
        "motorAdditionalPaidDriver" => "pa_additional_driver",
        "roadSideAssistance" => "road_side_assistance",
        "passengerAssistCover" => "passenger_assist_cover",
        "geogExtensionODPremium" => "geo_extension_odpremium",
        "geogExtensionTPPremium" => "geo_extension_tppremium",
        "compulsoryPaOwnDriver" => "compulsory_pa_own_driver",
        "multiYearCpa" => "compulsory_pa_own_driver",
        "motorElectricAccessoriesValue" => "electric_accessories_value",
        "coverUnnamedPassengerValue" => "unnamed_passenger_pa_cover",
        "lopb" => "loss_of_personal_belongings",
        "motorNonElectricAccessoriesValue" => "non_electric_accessories_value",
    ]
];
