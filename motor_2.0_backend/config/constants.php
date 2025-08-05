<?php 
if (!defined('STAGE_NAMES')) {
    define('STAGE_NAMES', [
    'LEAD_GENERATION'                       => 'Lead Generation',
    'QUOTE'                                 => 'Quote - Buy Now',
    'PROPOSAL_DRAFTED'                      => 'Proposal Drafted',
    'PROPOSAL_ACCEPTED'                     => 'Proposal Accepted',
    'PENDING_FROM_IC'                       => 'Pending from IC',
    'INSPECTION_APPROVED'                   => 'Inspection Approved',
    'INSPECTION_PENDING'                    => 'Inspection Pending',
    'INSPECTION_ACCEPTED'                   => 'Inspection Accepted',
    'INSPECTION_REJECTED'                   => 'Inspection Rejected',
    'PAYMENT_INITIATED'                     => 'Payment Initiated',
    'PAYMENT_FAILED'                        => 'Payment Failed',
    'PAYMENT_SUCCESS'                       => 'Payment Success',
    'PAYMENT_PENDING'                       => 'Payment Pending',
    'PAYMENT_RECEIVED'                      => 'Payment Received',
    'POLICY_PDF_GENERATED'                  => 'Policy PDF Generated',
    'POLICY_ISSUED_BUT_PDF_NOT_GENERATED'   => 'Policy Issued, but pdf not generated',
    'POLICY_ISSUED'                         => 'Policy Issued',
    'POLICY_CANCELLED'                      => 'Policy Cancelled'
]);}

if (!defined('STAGE_CODE')) {
    define('STAGE_CODE',  [
    'LEAD_GENERATION'                     => 101,
    'QUOTE'                               => 201,
    'PROPOSAL_DRAFTED'                    => 301,
    'PROPOSAL_ACCEPTED'                   => 302,
    'INSPECTION_PENDING'                  => 401,
    'INSPECTION_ACCEPTED'                 => 402,
    'INSPECTION_REJECTED'                 => 403,
    'PAYMENT_INITIATED'                   => 501,
    'PAYMENT_SUCCESS'                     => 502,
    'PAYMENT_RECEIVED'                    => 503,
    'PAYMENT_FAILED'                      => 504,
    'PAYMENT_PENDING'                     => 505,
    'POLICY_ISSUED_BUT_PDF_NOT_GENERATED' => 601,
    'POLICY_ISSUED'                       => 602,
    'POLICY_PDF_GENERATED'                => 603,
    'POLICY_CANCELLED'                    => 701
]); }

return [
    'motorConstant' => [
        'vehicleCategory' => 'uploads/vehicleCategory/',
        'vehicleModels'   => 'uploads/vehicleModels/',
        'logos'    => 'uploads/logos/',
        'PAYMENT_SUCCESS_CALLBACK_URL' => env('APP_FRONTEND_URL') . '/payment-success',
        'PAYMENT_FAILURE_CALLBACK_URL' => env('APP_FRONTEND_URL') . '/payment-failure',
        'CV_PROPOSAL_PDF_URL' => 'policyDocs/Cv/',
        'CAR_PROPOSAL_PDF_URL' => 'policyDocs/Car/',
        'BIKE_PROPOSAL_PDF_URL' => 'policyDocs/Bike/',
        'TOKEN_VALIDATE_URL' => null,
        'IS_POS_ENABLED' => null,
        'IS_EMPLOYEE_ENABLED' => null,
        'IS_USER_ENABLED' => null,
        'BROKER_USER_CREATION_API' => null,
        'BROKER_LIC_CODE' => null,
        'SMS_ENABLED' => 'N',
        'frontend_url' => null,
        'CC_EMAIL' => null,
        'EMAIL_ENABLED' => 'N',
        'SMS_FOLDER' => null, //'unilight', compare-policy
        'CV_FRONTEND_URL' => null,
        'BIKE_FRONTEND_URL' => null,
        'CAR_FRONTEND_URL' => null,
        "CV_PAYMENT_SUCCESS_CALLBACK_URL" => null,
        'CV_PAYMENT_FAILURE_CALLBACK_URL' => null,
        'BIKE_PAYMENT_SUCCESS_CALLBACK_URL' => null,
        'BIKE_PAYMENT_FAILURE_CALLBACK_URL' => null,
        'CAR_PAYMENT_SUCCESS_CALLBACK_URL' => null,
        'CAR_PAYMENT_FAILURE_CALLBACK_URL' => null,
        'REGISTRATION_DETAILS_SERVICE_TYPE' => null,
        'GRAMCOVER_DATA_PUSH_ENABLED' => null,
        'WEB_SERVICE_LOG_SHARE' => null,
        'WEB_SERVICE_LOG_SHARE_EMAIL' => null,
        'AGENT_MAPPING_BROKER' => null,
        'unCertifiedPosAgents' => null,
        'block_ic' => null,
        'block_employee' => null,
        'corporate_domain_validation_api' => null,
        'IS_USER_ENABLED' => null,
        'USER_CREATION_MAIL' => null,
        'PINC_API_PASSWORD' => null,
        'PINC_API_USERNAME' => null,
        'BREAKIN_CHECK_URL' => null,
        'CAR_BREAKIN_PAYMENT_URL' => null,
        'GCV_PROPOSAL_PDF_URL' => null,
        'KAFKA_DATA_PUSH_ENABLED' => null,
        'IS_APD_ENABLED' => null
    ],

    'posTesting' =>
    [
        'IS_POS_TESTING_MODE_ENABLE' => null,
        'IS_POS_TESTING_MODE_ENABLE' => null
    ],

    'brokerConstant' => [
        'name' => null,
        'website' => null,
        'logo' => null,
        'tollfree_number' => null,
        'help_email' => null,
        'support_email' => null,
        'address' => null,
        'code' => null,
        'bajaj' => [
            "2w_campaign_name" => null,
            "4w_campaign_name" => null,
            "lead_source" => null
        ],
    ],
    'mmv' => [
        'MMV_API_URL_LIST'  => null,
        'RTO_UAT_API_URL_LIST' => 'https://mmvuat.fynity.in/admin/mmv/get_rto_url_list',
        'RTO_PROD_API_URL_LIST' => null
    ],

    'sms' => [
        "URL" => "",
        "USERNAME" => "",
        "MSG_TOKEN" => "",
        "SENDER_ID" => ""
    ],

    'motor' => [
        'EMBEDDED_LINK_WHATSAPP_SCHEDULAR_TIME' => null,
        'EMBEDDED_LINK_GENERATION_SCHEDULAR_TIME' => null,
        'EMBEDDED_SCRUB_DATA_GENERATION_SCHEDULAR_TIME' => null,
        'EMBEDDED_SCRUB_DATA_GENERATION_SCHEDULAR_START_TIME' => null,
        'EMBEDDED_SCRUB_DATA_GENERATION_SCHEDULAR_END_TIME' => null,
        'UPDATE_STATE_CITY_IN_EMBEDDED_LINK' => null,
        'agentJourney' => null,
        'USE_CONTROLLER_FOR_API_CALL' => null,
        'IS_WIMWISURE_GODIGIT_ENABLED' => null,

        'bajaj_allianz' => [
            'BAJAJ_ALLIANZ_CV_JSON_USER_ID' => null,
            'IS_POS_TESTING_MODE_ENABLE_BAJAJ' => null,
            'BAJAJ_ALLIANZ_CV_JSON_POS_USERNAME' => null,
            'BAJAJ_ALLIANZ_CV_JSON_POS_PASSWORD' => null,
            'BAJAJ_ALLIANZ_CV_USERNAME' => null,
            'BAJAJ_ALLIANZ_CV_PASSWORD' => null,
            'BAJAJ_ALLIANZ_CHECK_PG_TRANS_STATUS' => null,
            'AUTH_PASS_BAJAJ_ALLIANZ_BIKE' => null,
            'AUTH_NAME_BAJAJ_ALLIANZ_BIKE' => null
        ],

        'oriental' => [
            'QUOTE_URL' => null,
            'ORIENTAL_PDF_URL' => null
        ]
    ],

    'LSQ' => [
        'IS_LSQ_ENABLED' => null,
        'ACTIVITY_CREATE' => null,
        'ACCESS_KEY' => null,
        'SECRET_KEY' => null,
        'origin' => null,
        'LEAD_CAPTURE' => null,
        'LEAD_UPDATE' => null,
        'LEAD_RETRIEVE' => null,
        'OPPORTUNITY_CAPTURE' => null,
        'OPPORTUNITY_UPDATE' => null,
        'OPPORTUNITY_RETRIEVE' => null
    ],

    'brokerConstant' => [
        'tollfree_number' => null,
        'support_email' => null
    ],

    'frontend_url' => null,

    'fastlane' => [
        'username' => null,
        'password' => null
    ],

    'mmv' => [
        'MMV_UAT_API_URL_LIST' => null,
        'MMV_PROD_API_URL_LIST' => null,
        'RTO_UAT_API_URL_LIST' => null,
        'RTO_PROD_API_URL_LIST' => null
    ],

    'finsall' => [
        'FINSALL_LOGGEDINUNIQUEIDENTIFIERID' => null,
        'FINSALL_LOGGEDINUSERID' => null,
        'FINSALL_SERVICE_URL' => null,
        'FINSALL_AUTHENTICATION_TOKEN' => null,
        'FINSALL_AUTHENTICATION_USERNAME' => null,
        'FINSALL_CLIENTID' => null,
        'FINSALL_CLIENTKEY' => null,
        'FINSALL_VERSION' => null,
        'FINSALL_ROLES' => null
    ],

    'IS_OLA_BROKER' => null,
    'enhance_journey_short_term_3_months' => null,
    'enhance_journey_short_term_6_months' => null,
    'X-TENANT-KEY' => null,
    'X-TENANT-NAME' => null,

    'wimwisure' => [
        'API_KEY_GODIGIT' => null,
        'API_KEY_RELIANCE' => null,
        'API_KEY_SHRIRAM' => null
    ],

    'cv' => [
        'iffco' => [
            'IFFCO_TOKIO_PCV_PARTNER_BRANCH' => null,
            'IFFCO_TOKIO_PCV_PARTNER_CODE' => null,
            'END_POINT_URL_IFFCO_TOKIO_MOTOR_PDF' => null,
            'IFFCO_TOKIO_PCV_PARTNER_PASSWORD' => null,
            'IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM' => null,
            'IFFCO_TOKIO_PCV_PARTNER_PASSWORD_SHORT_TERM' => null,
            'IFFCO_TOKIO_PCV_QUOTE_URL_SHORT_TERM' => null,
            'IFFCO_TOKIO_PCV_POLICY_PDF_SHORT_TERM' => null
        ]
    ],

    'IcConstants' => [
        'acko' => [
            'ACKO_WEB_SERVICE_AUTH' => null,
            'ACKO_WEB_SERVICE_X_CUSTOMER_SESSION_ID' => null,
            'ACKO_PAYMENT_WEB_SERIVCE_URL' => null,
            'ACKO_PROPOSAL_STATUS_URL' => null,
            'ACKO_QUOTE_WEB_SERVICE_URL' => null

        ],
        'godigit' => [
            'GODIGIT_WEB_USER_ID' => null,
            'GODIGIT_PASSWORD' => null,
            'GODIGIT_BREAKIN_STATUS' => null,
            'GODIGIT_WIMWISURE_BREAKIN_STATUS' => null,
            'GODIGIT_WIMWISURE_BREAKIN_AUTHORIZATION' => null,
            'GODIGIT_PAYMENT_GATEWAY_REDIRECTIONAL' => null,
            'GODIGIT_PG_AUTHORIZATION' => null,
            'GODIGIT_POLICY_PDF' => null,
            'GODIGIT_BIKE_PAYMENT_GATEWAY_REDIRECTIONAL' => null,
            'GODIGIT_BIKE_PG_AUTHORIZATION' => null,
            'GODIGIT_BIKE_PAYMENT_APD_URL' => null,
            'GODIGIT_BIKE_POLICY_PDF' => null
        ],

        'edelweiss' => [
            'EDELWEISS_TOKEN_PASSWORD' => null,
            'EDELWEISS_BIKE_X_API_KEY' => null,
            'EDELWEISS_X_API_KEY' => null,
            'BIKE_MERCHANT_ID' => null,
            'BIKE_MERCHANT_ID' => null,
            'BIKE_USER_ID' => null,
            'MOTOR_CHECKSUM_KEY' => null,
            'END_POINT_URL_EDELWEISS_PAYMENT_GATEWAY' => null,
            'END_POINT_URL_EDELWEISS_TOKEN_GENERATION' => null,
            'EDELWEISS_TOKEN_USER_NAME' => null,
            'END_POINT_URL_EDELWEISS_BIKE_POLICY_GENERATION' => null,
            'MOTOR_BPID' => null,
            'END_POINT_URL_EDELWEISS_BIKE_PAYMENT_REQUEST' => null,
            'END_POINT_URL_EDELWEISS_BIKE_PDF_SERVICE' => null,
            'EDELWEISS_TOKEN_USER_NAME' => null
        ],

        'iffco_tokio' => [
            'IS_BREAKIN_INSPECTION_DATE_CHANGES_AVAILABLE' => null
        ],

        'raheja' => [
            'WEB_USER_ID_RAHEJA_BIKE' => null,
            'PASSWORD_RAHEJA_BIKE' => null,
            'PASSWORD_RAHEJA_MOTOR' => null,
            'WEB_USER_ID_RAHEJA_MOTOR' => null
        ],
        'sbi' => [
            'SBI_X_IBM_CLIENT_ID_PDF_BIKE' => null,
            'SBI_X_IBM_CLIENT_SECRET_PDF_BIKE' => null,
            'SBI_X_IBM_CLIENT_ID_BIKE' => null,
            'SBI_X_IBM_CLIENT_SECRET_BIKE' => null,
            'SBI_X_IBM_CLIENT_ID_PDF' => null,
            'SBI_X_IBM_CLIENT_SECRET_PDF' => null,
            'SBI_X_IBM_CLIENT_SECRET' => null,
            'SBI_X_IBM_CLIENT_ID' => null
        ],

        'hdfc_ergo' => [
            'IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR' => null,
            'HDFC_ERGO_CV_REQUEST_TYPE' => null,
            'HDFC_ERGO_CV_SOURCE' => null,
            'HDFC_ERGO_CV_CHANNEL_ID' => null,
            'HDFC_ERGO_CV_CREDENTIAL' => null,
            'IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR' => null,
            'v2.useCommonPincodeMaster' => null,
            'HDFC_ERGO_COMPREHENSIVE_AGENT_CODE' => null,
            'HDFC_ERGO_MOTOR_INSPECTION_STATUS' => null,
            'HDFC_ERGO_MOTOR_GET_BREAKIN_PROPOSAL_DATA_PREMIUM' => null,
            'HDFC_ERGO_MOTOR_BREAKIN_FINAL_PROPOSAL' => null,
            'HDFC_ERGO_V2_MOTOR_AGENT_CODE' => null,
            'HDFC_ERGO_V2_MOTOR_GET_BREAKIN_DETAILS_URL' => null,
            'HDFC_ERGO_V2_MOTOR_MERCHANT_KEY' => null,
            'HDFC_ERGO_V2_MOTOR_SECRET_TOKEN' => null,
            'TRANSACTION_NO_SERIES_HDFC_ERGO_CV_JSON_MOTOR2' => null,
            'SubscriptionID_HDFC_ERGO_GIC_CV' => null,
            'PAYMENT_CHECKSUM_LINK_HDFC_ERGO_GIC_MOTOR' => null,
            'PAYMENT_GATEWAY_LINK_HDFC_ERGO_GIC_MOTOR' => null,
            'HDFC_ERGO_MOTOR_PAYMENT_URL' => null,
            'HDFC_ERGO_MOTOR_PAYMENT_URL_METHOD' => null,
            'HDFC_ERGO_MOTOR_TP_PAYMENT_URL' => null,
            'HDFC_ERGO_MOTOR_GIC_PROPOSAL' => null,
            'HDFC_ERGO_GIC_MOTOR_POLICY_DOCUMENT_DOWNLOAD' => null,
            'HDFC_ERGO_V2_BIKE_MERCHANT_KEY' => null,
            'HDFC_ERGO_V2_BIKE_SECRET_TOKEN' => null,
            'TRANSACTION_NO_SERIES_HDFC_ERGO_GIC_MOTOR' => null,
            'HDFC_ERGO_V2_BIKE_AGENT_CODE' => null,
            'HDFC_ERGO_V2_BIKE_POLICY_GENERATION_URL' => null,
            'IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_BIKE' => null
        ],

        'kotak' => [
            'KOTAK_' . /* strtoupper($section). */  '_USERID' => null,
            'KOTAK_' . /* strtoupper($section). */  '_PASSWORD' => null,
            'END_POINT_URL_TOKEN_KOTAK_BIKE' => null
        ],

        'future_generali' => [
            'END_POINT_URL_NEW_INSPECTION' => null,
            'VENDOR_CODE_FUTURE_GENERALI' => null,
            'BRANCH_CODE_FUTURE_GENERALI' => null,
            'END_POINT_URL_FUTURE_GENERALI' => null,
            'FUTURE_GENERALI_MOTOR_CHECK_TRN_STATUS' => null,
            'BROKER_USER_FOR_NEW_INSPECTION' => null,
            'APP_KEY_FG_LIVE_CHECK' => null
        ],

        'icici_lombard' => [
            'ICICI_LOMBARD_DEAL_ID' => null,
            'ICICI_LOMBARD_DEAL_ID_GCV_TP' => null,
            'ICICI_LOMBARD_USERNAME' => null,
            'ICICI_LOMBARD_PASSWORD' => null,
            'ICICI_LOMBARD_CLIENT_ID' => null,
            'ICICI_LOMBARD_CLIENT_SECRET' => null,
            'ICICI_LOMBARD_TOKEN_GENERATION_URL_MOTOR' => null,
            'CV_IDV_END_POINT_URL' => null,
            'CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR' => null,
            'PROPOSAL_DEAL_ID_ICICI_LOMBARD_BIKE' => null,
            'ICICI_LOMBARD_RTO_URL' => null,
            'ICICI_LOMBARD_MMV_URL' => null,
            'ICICI_LOMBARD_TOKEN_GENERATION_URL' => null,
            'ICICI_LOMBARD_PAYMENT_APPLICATION_ID' => null,
            'ICICI_LOMBARD_PAYMENT_USERNAME' => null,
            'ICICI_LOMBARD_PAYMENT_TOKEN_URL' => null,
            'ICICI_LOMBARD_PAYMENT_URL' => null,
            'ICICI_LOMBARD_GCV_PRODUCT_CODE_TP' => null,
            'ICICI_LOMBARD_DEAL_ID_GCV_BREAKIN' => null,
            'ICICI_LOMBARD_GCV_PRODUCT_CODE' => null,
            'ICICI_LOMBARD_DEAL_ID_GCV' => null,
            'ICICI_LOMBARD_DEAL_ID_TP' => null,
            'ICICI_LOMBARD_PCV_TP_PRODUCT_CODE' => null,
            'ICICI_LOMBARD_DEAL_ID_BREAKIN' => null,
            'ICICI_LOMBARD_PCV_PRODUCT_CODE' => null,
            'ICICI_LOMBARD_DEAL_ID_SHORT_TERM_3_BREAKIN' => null,
            'ICICI_LOMBARD_DEAL_ID_SHORT_TERM_6_BREAKIN' => null,
            'ICICI_LOMBARD_DEAL_ID' => null,
            'ICICI_LOMBARD_MISC_TP_PRODUCT_CODE' => null,
            'ICICI_LOMBARD_MISC_DEAL_ID' => null,
            'TRANSACTION_ENQUIRY_END_POINT_URL_ICICI_LOMBARD_MOTOR'  => null,
            'ICICI_LOMBARD_IRDA_LICENCE_NUMBER' => null,
            'ICICI_LOMBARD_GENERATE_POLICY' => null,
            'CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_TP' => null,
            'CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE_OD' => null,
            'CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_BIKE' => null,
            'ICICI_LOMBARD_GENERATE_POLICY_PDF' => null,
            'ICICI_LOMBARD_TOKEN_GENERATION_URL_BIKE' => null,
            'PRODUCT_CODE_ICICI_LOMBARD_BIKE' => null,
            'ICICI_LOMBARD_CLIENT_SECRET_BIKE' => null,
            'ICICI_LOMBARD_PASSWORD_BIKE' => null,
            'ICICI_LOMBARD_CLIENT_ID_BIKE' => null,
            'ICICI_LOMBARD_PAYMENT_PASSWORD' => null
        ],

        'reliance' => [
            'USERID_RELIANCE' => null,
            'AUTH_TOKEN_RELIANCE' => null,
            'POLICY_DWLD_LINK_RELIANCE' => null,
            'END_POINT_URL_RELIANCE_MOTOR_PROPOSAL' => null,
            'PAYMENT_GATEWAY_LINK_RELIANCE' => null,
            'IS_WIMWISURE_RELIANCE_ENABLED' => null,
            'RELIANCE_MOTOR_LEAD_AGENCYREFNO' => null,
            'RELIANCE_MOTOR_LEAD_AGENCY_CODE' => null,
            'RELIANCE_MOTOR_LEAD_USERID' => null,
            'RELIANCE_MOTOR_LEAD_USERPASSWORD' => null,
            'END_POINT_URL_RELIANCE_MOTOR_LEAD' => null,
            'RELIANCE_MOTOR_FETCH_LEAD_DETAILS_URL' => null,
            'RELIANCE_CV_OCP_APIM_SUBSCRIPTION_KEY_FOR_FETCH_DETAILS' => null
        ],

        'finsall' => [
            'END_POINT_URL_RELIANCE_FINSALL_PAYMENT_CHECK' => null
        ],

        'tata_aig_v2.IS_TATA_AIG_V2_CAR_ENABLED' => null,
        'GRAMCOVER_REMOTE_TOKEN_VERIFY_URL' => null,

        'shriram' => [
            'SHRIRAM_USERNAME' => null,
            'SHRIRAM_BREAKIN_CHECK_URL' => null,
            'SHRIRAM_PASSWORD' => null
        ],

        'tata_aig_v2' => [
            'TATA_AIG_V2_XAPI_KEY' => null,
            'TATA_AIG_V2_END_POINT_URL_VERIFY_INSPECTION' => null,
            'TATA_AIG_V2_END_POINT_URL_VERIFY_INSPECTION' => null
        ],

        'tata_aig' => [
            'END_POINT_PAYMENT_BEFORE_URL' => null,
            'END_POINT_PAYMENT_BEFORE_URL_METHOD' => null,
            'SRC' => null,
            'TOKEN' => null,
            'END_POINT_URL_POLICY_NO_GENERATION' => null,
            'cv' => [
                'END_POINT_URL_POLICY_GENERATION' => null
            ]
        ],

        'universal_sompo' => [
            'AUTH_APPCODE_SOMPO_MOTOR' => null,
            'AUTH_CODE_SOMPO_MOTOR' => null,
            'BREAKIN_STATUS_CHECK_END_POINT_URL_UNIVERSAL_SOMPO_CAR' => null,
            'UNIVERSAL_SOMPO_PAYMENT_END_POINT_URL' => null,
            'AUTH_CODE_SOMPO_CV' => null
        ],

        'live_chek' => [
            'LIVE_CHEK_APP_ID' => null,
            'LIVE_CHEK_COMPANY_ID' => null,
            'LIVE_CHEK_BRANCH_ID' => null,
            'LIVE_CHEK_APP_USER_ID' => null,
            'LIVE_CHEK_END_POINT_URL' => null,
            'LIVE_CHEK_APP_KEY' => null
        ],

        'liberty_videocon' => [
            'PAYMENT_GATEWAY_LINK_LIBERTY_VIDEOCON' => null,
            'TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR' => null,
            'LIBERTY_VIDEOCON_PAYMENT_SOURCE' => null,
            'CAR_EMAIL' => null,
            'OTP' => null,
            'CREATE_POLICY_LIBERTY_VIDEOCON_MOTOR' => null,
            'BROKER_IDENTIFIER' => null,
            'END_POINT_URL_LIBERTY_VIDEOCON_PREMIUM_CALCULATION' => null
        ],

        'oriental' => [
            'cv' => [
                'CV_PAYMENT_IDENTIFIER' => null,
                'CV_PAYMENT_MERCHANT_ID' => null,
                'CV_PAYMENT_USER_ID' => null,
                'CV_PAYMENT_CHECKSUM_KEY' => null
            ],

            'ORIENTAL_USER_NAME_POLICY_PDF' => null,
            'ORIENTAL_PASSWROD_POLICY_PDF' => null
        ],

        'universal_sompo' => [
            'universal_sompo' => null,
            'END_POINT_URL_UNIVERSAL_SOMPO_GET_POLICY_STATUS' => null
        ],

        'royal_sundaram' => [
            'END_POINT_URL_ROYAL_SUNDARAM_FETCH_POLICY_DETAILS' => null
        ],

        'cholla_madalam' => [
            'PAYMENT_GATEWAY_ID_CHOLLA_MANDALAM' => null,
            'BROKER_NAME' => null,
            'PAYMENT_MERCHANT_ID_CHOLLA_MANDALAM' => null,
            'STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM' => null,
            'PAYMENT_SECURITY_ID_CHOLLA_MANDALAM' => null,
            'QUERY_API_MERCHANT_ID_CHOLLA_MANDALAM' => null,
            'PAYMENT_CHECKSUM_CHOLLA_MANDALAM' => null,
            'QUERY_API_URL_CHOLLA_MANDALAM' => null
        ]
    ],
];