<?php

namespace Database\Seeders;

use App\Models\FrontendConstant;
use Illuminate\Database\Seeder;

class AddFrontendConstants extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [];
        switch(config('constants.motorConstant.SMS_FOLDER')){
            case 'ola':
            $data = [ 
                [
                    'key' => 'frontend_constsants.logo',
                    'datatype' => 'text',
                    'value' => 'logo',
                ],
                [
                    'key' => 'frontend_constsants.BrokerName',
                    'datatype' => 'text',
                    'value' => 'Ola Financial Services Private Limited',
                ],
                [
                    'key' => 'frontend_constsants.BrokerCategory',
                    'datatype' => 'text',
                    'value' => 'Corporate Agent',
                ],
                [
                    'key' => 'frontend_constsants.EmailFunction',
                    'datatype' => 'text',
                    'value' => 'insurance@olacabs.com',
                ],
                [
                    'key' => 'frontend_constsants.Contact',
                    'datatype' => 'text',
                    'value' => '08037101822',
                ],
                [
                    'key' => 'frontend_constsants.cinNO',
                    'datatype' => 'text',
                    'value' => 'U67200MH2003PTC141621',
                ],
                [
                    'key' => 'frontend_constsants.IRDAI',
                    'datatype' => 'text',
                    'value' => 'CA0682',
                ],
                [
                    'key' => 'frontend_constsants.BrokerLogoUrl',
                    'datatype' => 'text',
                    'value' => 'ola.png',
                ],
                [
                    'key' => 'frontend_constsants.ContentFn',
                    'datatype' => 'text',
                    'value' => 'In case of any challenges, please contact us at insurance@olacabs.com or call us at our number +91-7829-41-1222',
                ],
                [
                    'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                    'datatype' => 'text',
                    'value' => 'false',
                ],
                // NomineeMandatory
                [
                    'key' => 'frontend_constsants.NomineeMandatory',
                    'datatype' => 'text',
                    'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                ],
                // ovd_ckyc
                [
                    'key' => 'frontend_constsants.ovd_ckyc',
                    'datatype' => 'text',
                    'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                ],
                // panMandatoryIC
                [
                    'key' => 'frontend_constsants.panMandatoryIC',
                    'datatype' => 'text',
                    'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                ],
                // icWithColorMaster
                [
                    'key' => 'frontend_constsants.icWithColorMaster',
                    'datatype' => 'text',
                    'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                ],
                // ICVehicleColorMandatory
                [
                    'key' => 'frontend_constsants.ICVehicleColorMandatory',
                    'datatype' => 'text',
                    'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                ],
                // redirection_ckyc
                [
                    'key' => 'frontend_constsants.redirection_ckyc',
                    'datatype' => 'text',
                    'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                ],
                // postSubmit
                [
                    'key' => 'frontend_constsants.postSubmit',
                    'datatype' => 'text',
                    'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                ],
            ];
            break;
            case 'Fyntune':
            $data = [
                 //Fyntune
                [
                    'key' => 'frontend_constsants.logo',
                    'datatype' => 'text',
                    'value' => 'fyntune',
                ],
                [
                    'key' => 'frontend_constsants.BrokerName',
                    'datatype' => 'text',
                    'value' => '',
                ],
                [
                    'key' => 'frontend_constsants.BrokerCategory',
                    'datatype' => 'text',
                    'value' => 'Composite Broker',
                ],
                [
                    'key' => 'frontend_constsants.EmailFunction',
                    'datatype' => 'text',
                    'value' => 'help@fyntune.com',
                ],
                [
                    'key' => 'frontend_constsants.Contact',
                    'datatype' => 'text',
                    'value' => '1800120000065',
                ],
                [
                    'key' => 'frontend_constsants.cinNO',
                    'datatype' => 'text',
                    'value' => 'U67200MH2003PTC141621',
                ],
                [
                    'key' => 'frontend_constsants.IRDAI',
                    'datatype' => 'text',
                    'value' => 'CA0682',
                ],
                [
                    'key' => 'frontend_constsants.BrokerLogoUrl',
                    'datatype' => 'text',
                    'value' => 'FYNTUNE.png',
                ],
                [
                    'key' => 'frontend_constsants.ContentFn',
                    'datatype' => 'text',
                    'value' => 'In case of any challenges, please contact us at help.com or call us at our number 9711615784',
                ],
                [
                    'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                    'datatype' => 'text',
                    'value' => 'false',
                ],
                // NomineeMandatory
                [
                    'key' => 'frontend_constsants.NomineeMandatory',
                    'datatype' => 'text',
                    'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                ],
                // ovd_ckyc
                [
                    'key' => 'frontend_constsants.ovd_ckyc',
                    'datatype' => 'text',
                    'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                ],
                // panMandatoryIC
                [
                    'key' => 'frontend_constsants.panMandatoryIC',
                    'datatype' => 'text',
                    'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                ],
                // icWithColorMaster
                [
                    'key' => 'frontend_constsants.icWithColorMaster',
                    'datatype' => 'text',
                    'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                ],
                // ICVehicleColorMandatory
                [
                    'key' => 'frontend_constsants.ICVehicleColorMandatory',
                    'datatype' => 'text',
                    'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                ],
                // redirection_ckyc
                [
                    'key' => 'frontend_constsants.redirection_ckyc',
                    'datatype' => 'text',
                    'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                ],
                // postSubmit
                [
                    'key' => 'frontend_constsants.postSubmit',
                    'datatype' => 'text',
                    'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                ],
            ];    
            break;
            case 'policy-era':
            $data = [
                [
                    'key' => 'frontend_constsants.logo',
                    'datatype' => 'text',
                    'value' => 'policyera',
                ],
                [
                    'key' => 'frontend_constsants.BrokerName',
                    'datatype' => 'text',
                    'value' => 'Policy Era Insurance Broking LLP.',
                ],
                [
                    'key' => 'frontend_constsants.BrokerCategory',
                    'datatype' => 'text',
                    'value' => 'Direct Broker',
                ],
                [
                    'key' => 'frontend_constsants.EmailFunction',
                    'datatype' => 'text',
                    'value' => 'support@policyera.com',
                ],
                [
                    'key' => 'frontend_constsants.Contact',
                    'datatype' => 'text',
                    'value' => '7039839239',
                ],
                [
                    'key' => 'frontend_constsants.cinNO',
                    'datatype' => 'text',
                    'value' => 'AAX-7485',
                ],
                [
                    'key' => 'frontend_constsants.IRDAI',
                    'datatype' => 'text',
                    'value' => 'DB 897/2021',
                ],
                [
                    'key' => 'frontend_constsants.BrokerLogoUrl',
                    'datatype' => 'text',
                    'value' => 'policy-era.png',
                ],
                [
                    'key' => 'frontend_constsants.ContentFn',
                    'datatype' => 'text',
                    'value' => 'In case of any further requirements, please contact us at support@policyera.com',
                ],
                [
                    'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                    'datatype' => 'text',
                    'value' => 'true',
                ],
                // NomineeMandatory
                [
                    'key' => 'frontend_constsants.NomineeMandatory',
                    'datatype' => 'text',
                    'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                ],
                // ovd_ckyc
                [
                    'key' => 'frontend_constsants.ovd_ckyc',
                    'datatype' => 'text',
                    'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                ],
                // panMandatoryIC
                [
                    'key' => 'frontend_constsants.panMandatoryIC',
                    'datatype' => 'text',
                    'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                ],
                // icWithColorMaster
                [
                    'key' => 'frontend_constsants.icWithColorMaster',
                    'datatype' => 'text',
                    'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                ],
                // ICVehicleColorMandatory
                [
                    'key' => 'frontend_constsants.ICVehicleColorMandatory',
                    'datatype' => 'text',
                    'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                ],
                // redirection_ckyc
                [
                    'key' => 'frontend_constsants.redirection_ckyc',
                    'datatype' => 'text',
                    'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                ],
                // postSubmit
                [
                    'key' => 'frontend_constsants.postSubmit',
                    'datatype' => 'text',
                    'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                ],
            ];
            break;
            case 'abibl':
            $data = [
                [
                    'key' => 'frontend_constsants.logo',
                    'datatype' => 'text',
                    'value' => 'abibl',
                ],
                [
                    'key' => 'frontend_constsants.BrokerName',
                    'datatype' => 'text',
                    'value' => 'Aditya Birla Insurance Broker Limited',
                ],
                [
                    'key' => 'frontend_constsants.BrokerCategory',
                    'datatype' => 'text',
                    'value' => 'Composite Broker',
                ],
                [
                    'key' => 'frontend_constsants.EmailFunction',
                    'datatype' => 'text',
                    'value' => 'clientfeedback.abibl@adityabirlacapital.com',
                ],
                [
                    'key' => 'frontend_constsants.Contact',
                    'datatype' => 'text',
                    'value' => '18002707000',
                ],
                [
                    'key' => 'frontend_constsants.cinNO',
                    'datatype' => 'text',
                    'value' => 'U67200MH2003PTC141621',
                ],
                [
                    'key' => 'frontend_constsants.IRDAI',
                    'datatype' => 'text',
                    'value' => 'CA0682',
                ],
                [
                    'key' => 'frontend_constsants.BrokerLogoUrl',
                    'datatype' => 'text',
                    'value' => 'abiblPdf.jpeg',
                ],
                [
                    'key' => 'frontend_constsants.ContentFn',
                    'datatype' => 'text',
                    'value' => 'In case of any challenges, please contact us at Support@abibl.com or call us at our number 1800 270 7000',
                ],
                [
                    'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                    'datatype' => 'text',
                    'value' => 'true',
                ],
                // NomineeMandatory
                [
                    'key' => 'frontend_constsants.NomineeMandatory',
                    'datatype' => 'text',
                    'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                ],
                // ovd_ckyc
                [
                    'key' => 'frontend_constsants.ovd_ckyc',
                    'datatype' => 'text',
                    'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                ],
                // panMandatoryIC
                [
                    'key' => 'frontend_constsants.panMandatoryIC',
                    'datatype' => 'text',
                    'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                ],
                // icWithColorMaster
                [
                    'key' => 'frontend_constsants.icWithColorMaster',
                    'datatype' => 'text',
                    'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                ],
                // ICVehicleColorMandatory
                [
                    'key' => 'frontend_constsants.ICVehicleColorMandatory',
                    'datatype' => 'text',
                    'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                ],
                // redirection_ckyc
                [
                    'key' => 'frontend_constsants.redirection_ckyc',
                    'datatype' => 'text',
                    'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                ],
                // postSubmit
                [
                    'key' => 'frontend_constsants.postSubmit',
                    'datatype' => 'text',
                    'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                ],
            ];
            break;
            case 'gramcover':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'gc',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'GramCover Insurance Brokers Private Limited',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Composite Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'info@gramcover.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '+91 9311672463',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U66000DL2016PTC292037',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => 'CB 691/17',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'gc.png',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any challenges, please contact us at info@gramcover.com or call us at our number +91 9311672463',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'true',
                    ],
                    // NomineeMandatory
                    [
                        'key' => 'frontend_constsants.NomineeMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'sriyah':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'sriyah',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'Sriyah Insurance Brokers Pvt. Ltd',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Direct Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'care@nammacover.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '+1800 203 0504',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U66010KA2003PTC031462',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => '203',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'sriyah.png',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any challenges, please contact us at care@nammacover.com or call us at our number 1800 203 0504',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'false',
                    ],
                    // NomineeMandatory
                    [
                        'key' => 'frontend_constsants.NomineeMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'renewbuy':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'rb',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'D2C Insurance Broking Pvt. Ltd.',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Direct Broker (Life & General)',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'customersupport@renewbuy.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '18004197852',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U66030DL2013PTC249265',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => 'DB 571/14',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'rb.png',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any challenges, please contact us at customersupport@renewbuy.com or call us at our number 18004197852',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'true',
                    ],
                    // NomineeMandatory
                    [
                        'key' => 'frontend_constsants.NomineeMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ]
                ];
            break;
            case 'tmibasl':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'tata',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'Tata Motors Insurance Broking And Advisory Services Limited.',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Composite Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'support@tmibasl.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '18002090060',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U50300MH1997PLC149349',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => '375',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'tata.gif',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any further requirements, please contact us at support@tmibasl.com',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'false',
                    ],
                    [
                        'key' => 'frontend_constsants.NomineeMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'spa':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'insuringall',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'SPA Insurance Broking Services Ltd.',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Direct Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'care@insuringall.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '+91-11-45675555',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U67120MH1995PLC088462',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => 'DB053/03',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'insuringall.jpeg',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any further requirements, please contact us at care@insuringall.com or call us at our number +91-11-45675555',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'true',
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'uib':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'uib',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'UIB Insurance Brokers (India) Pvt. Ltd.',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Composite Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'services@uibindia.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '+91 79820 39210',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U66030MH2009PTC195794',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => '410',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'uib.png',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any further requirements, please contact us at services@uibindia.com',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'false',
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'sridhar':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'sridhar',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'Sridhar Insurance Brokers (P) Ltd.',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Direct Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'motor@sibinsure.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '1800-102-6099',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U67120CH2002PTC025491',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => '212',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'sridhar.png',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any further requirements, please contact us at motor@sibinsure.com',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'false',
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'kmd':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'kmd',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'false',
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'hero':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'hero_care',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'Hero Insurance Broking India Private Limited',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Composite Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'support.herocare@heroinsurance.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '911140578489',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U66010DL2007PTC165059',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => '649',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'hero_care.png',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any further requirements, please contact us at support@heroibil.com',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'true',
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'paytm':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'paytm',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'Paytm Insurance pvt. ltd.',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Direct Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'care@paytminsurance.co.in',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '+918826390016',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U66000DL2019PTC355671',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => '700',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'paytm.svg',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => '',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'false',
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ],
                ];
            break;
            case 'bajaj':
                $data = [
                    [
                        'key' => 'frontend_constsants.logo',
                        'datatype' => 'text',
                        'value' => 'bajaj',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerName',
                        'datatype' => 'text',
                        'value' => 'Bajaj Capital Insurance Broking Limited',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerCategory',
                        'datatype' => 'text',
                        'value' => 'Direct Broker',
                    ],
                    [
                        'key' => 'frontend_constsants.EmailFunction',
                        'datatype' => 'text',
                        'value' => 'care@bajajacapital.com',
                    ],
                    [
                        'key' => 'frontend_constsants.Contact',
                        'datatype' => 'text',
                        'value' => '1800 212 123123',
                    ],
                    [
                        'key' => 'frontend_constsants.cinNO',
                        'datatype' => 'text',
                        'value' => 'U67200DL2002PLC117625',
                    ],
                    [
                        'key' => 'frontend_constsants.IRDAI',
                        'datatype' => 'text',
                        'value' => 'CB 042/02',
                    ],
                    [
                        'key' => 'frontend_constsants.BrokerLogoUrl',
                        'datatype' => 'text',
                        'value' => 'bajaj.png',
                    ],
                    [
                        'key' => 'frontend_constsants.ContentFn',
                        'datatype' => 'text',
                        'value' => 'In case of any further requirements, please contact us at care@bajajacapital.com or call us at our number 1800 212 123123',
                    ],
                    [
                        'key' => 'frontend_constsants.CategoryMismatchOnProposal',
                        'datatype' => 'text',
                        'value' => 'true',
                    ],
                    // NomineeMandatory
                    [
                        'key' => 'frontend_constsants.NomineeMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['royal_sundaram', 'edelweiss', 'kotak']),
                    ],
                    // ovd_ckyc
                    [
                        'key' => 'frontend_constsants.ovd_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['icici_lombard', 'bajaj_allianz', 'iffco_tokio']),
                    ],
                    // panMandatoryIC
                    [
                        'key' => 'frontend_constsants.panMandatoryIC',
                        'datatype' => 'text',
                        'value' => json_encode(['edelweiss', 'reliance', 'royal_sundaram', 'united_india', 'oriental', 'magma', 'tata_aig', 'shriram']),
                    ],
                    // icWithColorMaster
                    [
                        'key' => 'frontend_constsants.icWithColorMaster',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india']),
                    ],
                    // ICVehicleColorMandatory
                    [
                        'key' => 'frontend_constsants.ICVehicleColorMandatory',
                        'datatype' => 'text',
                        'value' => json_encode(['sbi', 'universal_sompo', 'new_india', 'oriental']),
                    ],
                    // redirection_ckyc
                    [
                        'key' => 'frontend_constsants.redirection_ckyc',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'reliance', 'hdfc_ergo', 'cholla_mandalam', 'royal_sundaram', 'universal_sompo', 'liberty_videocon', 'future_generali', 'edelweiss']),
                    ],
                    // postSubmit
                    [
                        'key' => 'frontend_constsants.postSubmit',
                        'datatype' => 'text',
                        'value' => json_encode(['godigit', 'tata_aig', 'bajaj_allianz', 'kotak', 'raheja', 'new_india', 'shriram', 'oriental']),
                    ]
                ];
            break;
        }

        foreach ($data as $item) {
            FrontendConstant::insert([
                'key' => $item['key'],
                'datatype' => $item['datatype'],
                'value' => $item['value'],
            ]);
        }
    }
}
