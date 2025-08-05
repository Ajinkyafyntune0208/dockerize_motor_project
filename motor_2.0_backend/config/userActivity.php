<?php

return [
    'apis' =>
    [
        'api/save',
        'api/ckyc-verifications',

        'api/submit',
        'api/car/submit',
        'api/bike/submit',

        'api/make-payment',
        'api/car/make-payment',
        'api/bike/make-payment',

        'api/saveAddonData',
        'api/saveQuoteRequestData',
        'api/saveQuoteData',
        'api/updateQuoteRequestData',
        
        'api/updateUserJourney',

        'api/car/premiumCalculation/{company_alias}',
        'api/premiumCalculation/{company_alias}',
        'api/bike/premiumCalculation/{company_alias}',

        'api/createDuplicateJourney',
    ]
];