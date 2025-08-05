<?php

use App\Helpers\IcHelpers\HdfcErgoHelper;
use App\Helpers\IcHelpers\TataAigHelper;
use Carbon\Carbon;
use Ixudra\Curl\Facades\Curl;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use App\Models\WebserviceRequestResponseDataOptionList;
use App\Models\QuoteServiceRequestResponse;
use App\Models\WebServiceRequestResponse;
use App\Models\PosServiceRequestResponse;

if (!function_exists('getWsData')) {
	function getWsData($cUrl = '', $cInputs = array(), $companyAlias = '', $additionalData = array())
	{
		if (in_array($companyAlias, [
			'hdfc_ergo',
			'tata_aig_v2',
			'tata_aig'
		])) {

			if ($companyAlias == 'hdfc_ergo') {
				$token = HdfcErgoHelper::checkForToken($additionalData);
			} else {
				$token = TataAigHelper::checkForToken($additionalData, $cInputs);
			}

			if (!empty($token)) {
				return [
					'webservice_id' => null,
					'table' => null,
					'response' => $token
				];
			}
		}

		$global_timeout = (int) config("WEBSERVICE_TIMEOUT", 45);
		if(!empty(config($companyAlias . ".WEBSERVICE_TIMEOUT"))){
			$global_timeout = (int) config($companyAlias . ".WEBSERVICE_TIMEOUT", 45);
		}
		$global_with_timeout = (int) config("WEBSERVICE_WITH_TIMEOUT", 45);
		if(!empty(config($companyAlias . ".WEBSERVICE_WITH_TIMEOUT"))) {
			$global_with_timeout = (int) config($companyAlias . ".WEBSERVICE_WITH_TIMEOUT", 45);
		}
		if (app()->environment('local')) {
			$global_timeout = $global_with_timeout = app('ApiTimeoutHelper')->getIcApiTimeout($companyAlias, strtolower($additionalData['transaction_type'] ?? 'proposal'), $cUrl);
		}
		switch ($companyAlias) {

			case 'acko':
				$cRequest = !empty($cInputs) ? json_encode($cInputs) : '';
                $additionalData['headers'] = [
                    'Content-type' => 'application/json',
                    'Authorization' => Config::get('constants.IcConstants.acko.ACKO_WEB_SERVICE_AUTH'),
                    'X-CUSTOMER-SESSION-ID' => Config::get('constants.IcConstants.acko.ACKO_WEB_SERVICE_X_CUSTOMER_SESSION_ID'),
                    'Accept' => 'application/json'
                ];

                $curl = Curl::to($cUrl)->withHeaders($additionalData['headers']);

				if ($cRequest != '') {
					$curl = $curl->withData($cRequest);
				}
				break;

			case 'icici_lombard':
			$type_array1 = ['premiumCalculation','proposalService','policyGeneration','policyPdfGeneration','brekinInspectionStatus','premiumRecalculation','idvService','Break-in Id Generation','Submit POS Certificate','Create IM Broker Child','Fetch Policy Details','Renewal Proposal Service','addonsCoverService'];
			$type_array2 = ['paymentTokenGeneration','transactionIdForPG'];

				if (config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CUSTOM_TOKEN_REQUIRED_FOR_PAYMENT') == 'Y') {
					array_splice($type_array2, array_search('paymentTokenGeneration', $type_array2), 1);
					array_splice($type_array2, array_search('transactionIdForPG', $type_array2), 1);
				}

                if ($additionalData['type'] == 'tokenGeneration' || $additionalData['type'] == 'tokenGenerationAddons')
                {
                    $cRequest = $cInputs;
                    $additionalData['headers'] = [
                        'Content-type' => 'application/x-www-form-urlencoded'
                    ];

                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest);
                }
                elseif(in_array($additionalData['type'], $type_array1))
                {
                    $cRequest = json_encode($cInputs, JSON_UNESCAPED_SLASHES);
                    $additionalData['headers'] = [
                        'Content-type' => 'application/json',
                        'Authorization' => 'Bearer '.$additionalData['token'],
                        'Accept' => 'application/json'
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest);

                    if(isset($additionalData['pos_details']))
                    {

                       $pos_details = $additionalData['pos_details'];

                        $additionalData['headers'] = [
                            'IRDALicenceNumber' => $pos_details['IRDALicenceNumber'],
                            'CertificateNumber' => $pos_details['CertificateNumber'],
                            'PanCardNo' => $pos_details['PanCardNo'],
                            'AadhaarNo' => $pos_details['AadhaarNo'],
                            'ProductCode' => $pos_details['ProductCode']
                        ];

                        $curl->withHeaders($additionalData['headers']);
                    }
                }
                elseif(in_array($additionalData['type'], $type_array2))
                {
                    $cRequest = json_encode($cInputs, JSON_UNESCAPED_SLASHES);
                    $additionalData['headers'] = [
                        'Content-type' => 'application/json',
                        'Authorization' => 'Basic '.base64_encode(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_USERNAME').':'.config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_PASSWORD')),
                        'Accept' =>  'application/json'
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest);
                } elseif($additionalData['type'] == 'checkBreakinStatus') {
					$cRequest = json_encode($cInputs);
                    $additionalData['headers'] = [
                        'Authorization' => 'Bearer ' . $additionalData['token'],
                        'Content-Type' =>  'application/json'
                    ];
					$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cRequest);
				} else {
					$cRequest = json_encode($cInputs, JSON_UNESCAPED_SLASHES);
                    $curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
				}
				break;

			case 'shriram':
				$cRequest = ($additionalData['requestType'] == 'xml') ? $cInputs : json_encode($cInputs);
				$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cRequest);
				break;

				case 'godigit':
					$cRequest = json_encode($cInputs, true);

				if (($additionalData['type'] ?? '') == 'renewal') {
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
				} else if ($additionalData['method'] == 'PG Redirectional' || $additionalData['method'] == 'Policy PDF') {

                    $additionalData['headers'] = [
                        'Content-type' => 'application/json',
                        'Authorization' => $additionalData['authorization'],
                        'Accept' => 'application/json'
                    ];
                    $curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);

				} else if (isset($additionalData['authorization']) || ($additionalData['method'] == 'oneapi PG Redirectional') || ($additionalData['method'] == 'oneapi Policy PDF')) {
                    $additionalData['headers'] = [
                        'Content-type' => 'application/json',
                        // 'Authorization' => $additionalData['authorization'],
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer " . $additionalData['authorization'],
						'integrationId' => $additionalData['integrationId']
                    ];
                    $curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
				} else {
					$webUserId = $additionalData['webUserId'] ?? config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
                    $password  = $additionalData['password'] ?? config('constants.IcConstants.godigit.GODIGIT_PASSWORD');
                    $additionalData['headers'] = [
                        'webUserId' => $webUserId,
                        'password'  => $password,
                        'Content-type'  => 'application/json',
                        'Authorization' => 'Basic '.base64_encode("$webUserId:$password"),
                        'Accept' => 'application/json'
                    ];
					if (config("IC.GODIGIT.V2.CAR.ENABLE") == "Y") {
						unset($additionalData['headers']['Authorization']);
						unset($additionalData['headers']['webUserId']);
						unset($additionalData['headers']['password']);
					} // as per Ic curl they are not passing those values in header

					$curl = Curl::to($cUrl)
                        ->withHeaders($additionalData['headers'])
						->withData($cRequest);
					// $additionalData['headers'] = [
					// 	"webUserId" => config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID'),
					// 	"password" => config('constants.IcConstants.godigit.GODIGIT_PASSWORD')
					// ];
				}
				break;
			case 'reliance':

				if (in_array(($additionalData['method'] ?? ''), ['Lead Creation', 'Lead Fetch Details'])) {
					$cRequest = $cInputs;
				} else {
					$cRequest = ArrayToXML::convert($cInputs,$additionalData['root_tag']);
					$cRequest = preg_replace("/<\\?xml .*\\?>/i", '', $cRequest);
				}
				$reliance_headers = [
					'Content-type' => 'text/xml',
					// 'Content-Length' => strlen($cRequest)
				];

				if (in_array(($additionalData['method'] ?? ''), ['Lead Fetch Details'])) {
					unset($reliance_headers['Content-type']);
				}

				if (empty($additionalData['headers'])) {
					$additionalData['headers']= $reliance_headers;
				} else {
					$additionalData['headers'] = array_merge($additionalData['headers'], $reliance_headers);
				}

				$curl = Curl::to($cUrl)
	              ->withHeaders($additionalData['headers'])
				  ->withHeader('Content-Length', strlen(is_array($cRequest) ? json_encode($cRequest) : $cRequest))
	              ->withData($cRequest)
				  ->withTimeout(0)
				  ->withConnectTimeout($global_timeout);
      		break;

			case 'hdfc_ergo':
				if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y')
				{
					$cRequest = json_encode($cInputs, JSON_UNESCAPED_SLASHES);

					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest)
						->withTimeout(0)
						->withConnectTimeout($global_timeout);
				}
				else
				{
					if(isset($additionalData['root_tag']))
					{
						if($additionalData['root_tag'] == 'PDF')
						{
							$cRequest = $cInputs;
						}
						elseif($additionalData['root_tag'] == 'tem:objposp')
						{
							$cRequest = ArrayToXML::convert($cInputs);
							$cRequest = str_replace("#replace",$cRequest,$additionalData['container']);
							$cRequest = preg_replace('/<\\?xml .*\\?>/i', '', $cRequest);
						}
						else
						{
							$cRequest = $cInputs[0] . '=' . urlencode((string) ArrayToXML::convert($cInputs[1]));
							$cRequest = str_replace("<root>", '', $cRequest);
							$cRequest = str_replace("</root>", '', $cRequest);
							$cRequest = str_replace('<?xml version="1.0"?>', '', $cRequest);
						}
					}
					else
					{
						$cRequest = $cInputs;
					}
					//
					if(isset($additionalData['root_tag']) && $additionalData['root_tag'] == 'PDF')
					{
                        $additionalData['headers'] = [
                            'Content-type' => 'application/x-www-form-urlencoded'
                        ];
						$curl = Curl::to($cUrl)
										->withHeaders($additionalData['headers'])
										->withData($cRequest);
					}else if(isset($additionalData['root_tag']) && $additionalData['root_tag'] == 'tem:objposp')
					{
                        $additionalData['headers'] = [
                            'Content-type' => 'text/xml;charset=UTF-8',
                            'SOAPAction' => "\"http://tempuri.org/". $additionalData['soap_action'] . '"'
                        ];

						$curl = Curl::to($cUrl)
										->withHeaders($additionalData['headers'])
										->withData($cRequest);
					}
					elseif(isset($additionalData['type']) && $additionalData['type'] == 'gettoken'){
						$cRequest = '';
                        $additionalData['headers'] = [
                            'Content-type' => 'application/json',
                            'PRODUCT_CODE' => $additionalData['PRODUCT_CODE'],
                            'SOURCE' => $additionalData['SOURCE'],
                            'CHANNEL_ID' => $additionalData['CHANNEL_ID'],
                            'TRANSACTIONID' => $additionalData['TRANSACTIONID'],
                            'CREDENTIAL' => $additionalData['CREDENTIAL']
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);

						}elseif(in_array($additionalData['type'], array('IDVCalculation', 'PremiumCalculation','checksum_generation','PdfGeneration','getrenewal','ProposalCalculation', 'CheckInspection'))){
						$cRequest = json_encode($cInputs, JSON_UNESCAPED_SLASHES);
						
						if ($additionalData['type'] == 'CheckInspection') {
							$additionalData['headers'] = [
								'Content-type' => 'application/json',
							];
						} else {
							$additionalData['headers'] = [
								'Content-type' => 'application/json',
								'PRODUCT_CODE' => $additionalData['PRODUCT_CODE'],
								'SOURCE' => $additionalData['SOURCE'],
								'CHANNEL_ID' => $additionalData['CHANNEL_ID'],
								'TRANSACTIONID' => $additionalData['TRANSACTIONID'],
								'CREDENTIAL' => $additionalData['CREDENTIAL'],
								'TOKEN' => $additionalData['TOKEN']
							];
						}
                        
						$curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
							->withData($cRequest);

					}else{				
						$curl = Curl::to($cUrl)
								->withHeader('Content-Length',strlen($cRequest))
								->withData($cRequest);
					}
                                        if(isset($additionalData))
                                        {
                                            $additionalData['headers'] = $additionalData;
                                        }

				}

				break;

			case 'liberty_videocon':
                if (isset($additionalData['root_tag'])) {
                	// $cRequest = ArrayToXml::convert($cInputs);
	                /*
	                $cRequest = preg_replace('/<\\?xml .*\\?>/i', '',  $cRequest);
	                */

	                $jsonString = json_encode([$cInputs]);
	                $cRequest = str_replace("#replace",  $jsonString, $additionalData['container']);
                    $additionalData['headers'] = [
                        'Content-Length' 	=> strlen($cRequest),
						'Content-Type' 		=> $additionalData['content_type'],
                    ];
					$curl = Curl::to($cUrl)
					->withTimeout(100)
					->withConnectTimeout(100)
					->withHeaders($additionalData['headers'])
					->withData($cRequest);
                }
                else
                {
					$cRequest = !empty($cInputs) ? json_encode($cInputs) : '';
					if (isset($additionalData['headers']['Authorization'])) {
						$additionalData['headers'] = [
							'Content-Type' => 'Application/json',
							'Authorization' => $additionalData['headers']['Authorization']
						];
					} else {
						$additionalData['headers'] = [
							'Content-Type' => 'Application/json'
						];
					}
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
				}
				break;

            case 'future_generali' :

				if(isset($additionalData['method']) && in_array($additionalData['method'] , ['Renewal Fetch Policy Details', 'Renewal Create Policy Details']))
				{
					$cRequest = json_encode($cInputs);
	                $curl = Curl::to($cUrl)
					    ->withHeaders($additionalData['headers'])
			            ->withData($cRequest);
				}
                else if(isset($additionalData['method']) && $additionalData['method'] == 'Recon_Fetch_Trns')
	            {

	               $str = $cUrl . '?' . http_build_query($cInputs);
                   $additionalData['headers'] = [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                   ];
	               $curl = Curl::to($str)
	                       ->withHeaders($additionalData['headers'])
						   ->withTimeout(0)
						   ->withConnectTimeout($global_timeout);
	            }
                elseif ($additionalData['soap_action'] == 'GetPDF')
                {
                    $cRequest = ArrayToXml::convert($cInputs);
                    //$cRequest = htmlentities($cRequest);
                    $cRequest = preg_replace('/<\\?xml .*\\?>/i', '',  $cRequest);
                    $cRequest = str_replace("#replace",  $cRequest, $additionalData['container']);
                    $cRequest = str_replace("root",  'tem:GetPDF', $cRequest);
                    $additionalData['headers'] = [
                        'Content-Type' => 'text/xml',
                        'Content-Length' => strlen($cRequest)
                    ];
                    $curl = Curl::to($cUrl)
                            //->withHeader('SOAPAction:'.'http://tempuri.org/IService/' . $additionalData['soap_action'])
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest)
							->withTimeout(0)
							->withConnectTimeout($global_timeout);
                }elseif($additionalData['soap_action'] == 'MotorRenewal' || $additionalData['soap_action'] == 'Pos_MispMaster' )
				{
					$cRequest = ArrayToXml::convert($cInputs);
                    // $cRequest = htmlentities($cRequest);
                    $cRequest = preg_replace('/<\\?xml .*\\?>/i', '',  $cRequest);
                    $cRequest = str_replace("#replace",  $cRequest, $additionalData['container']);
					$cRequest = str_replace("root",  'Root', $cRequest);
                    $additionalData['headers'] = [
                        'SOAPAction' => 'http://tempuri.org/IService/' . $additionalData['soap_action'],
                        'Content-Type' => 'text/xml',
                        'Content-Length' => strlen($cRequest)
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest)
							->withTimeout(0)
							->withConnectTimeout($global_timeout);

				}else if(isset($additionalData['method']) && ($additionalData['method'] == 'fg_Recorn_service'))
				{
					$cRequest = (string)$cInputs;
                    $curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
                            ->withHeader('Content-Length:'.strlen($cRequest))
                            ->withData($cRequest)
							->withTimeout(0);
				}else{
                    $cRequest = ArrayToXml::convert($cInputs);
                    $cRequest = htmlentities($cRequest);
                    $cRequest = preg_replace('/<\\?xml .*\\?>/i', '',  $cRequest);
                    $cRequest = str_replace("#replace",  $cRequest, $additionalData['container']);
                    $cRequest = str_replace("root",  'Root', $cRequest);

                    $additionalData['headers'] = [
                        'SOAPAction' => 'http://tempuri.org/IService/' . $additionalData['soap_action'],
                        'Content-Type' => 'text/xml',
                        'Content-Length' => strlen($cRequest)
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest)
							->withTimeout(0)
							->withConnectTimeout($global_timeout);
                }
            	break;

            case 'inspection_request':
		            $cRequest = json_encode($cInputs);
                    $additionalData['headers'] =[
                        'Content-Type' => 'application/json',
                        'app-key' =>  config('constants.IcConstants.future_generali.APP_KEY_FG_LIVE_CHECK') .''
                    ];

	                $curl = Curl::to($cUrl)
			            ->withHeaders($additionalData['headers'])
			            ->withData($cRequest);
	            break;

			case 'tata_aig' :

				if (($additionalData['type'] ?? '') == 'renewal' && ($additionalData['method'] ?? '') != 'token generation') {
					$cRequest = json_encode($cInputs);

					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
				} else {
					$cRequest = urldecode(http_build_query($cInputs));

					// $curl = Curl::to($cUrl)
					// ->withHeader('Content-Type: application/x-www-form-urlencoded')
					// 	->withData($cRequest);

					$oldCurl = curl_init();
					curl_setopt_array($oldCurl, array(
						CURLOPT_URL => $cUrl,
						CURLOPT_PROXY => config('constants.http_proxy'),
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => '',
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => $global_timeout, //timeout
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => 'POST',
						CURLOPT_POSTFIELDS => $cRequest,
						CURLOPT_HTTPHEADER => array(
							'Content-Type: application/x-www-form-urlencoded'
						),
					));
					$tataCurlResponse = curl_exec($oldCurl);
					curl_close($oldCurl);
				}
				
				break;
				

            case 'bharti_axa' :
                $cRequest = ArrayToXml::convert($cInputs);
                $cRequest = preg_replace('/<\\?xml .*\\?>/i', '',  $cRequest);
                $cRequest = str_replace("#replace",  $cRequest, $additionalData['container']);
                if ($additionalData['root_tag'] == 'getIDV') {
                    $cRequest = str_replace("root",  'getIDV', $cRequest);
                }elseif($additionalData['root_tag'] == 'Session') {
                    $cRequest = str_replace("root",  'Session', $cRequest);
                }

                $additionalData['headers'] = [
                    'Content-Type' => 'text/xml',
                    'Content-Length' => strlen($cRequest)
                ];
                $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headeres'])
                            ->withData($cRequest);

            break;

			case 'royal_sundaram':
                            if(isset($additionalData['method']) && $additionalData['method'] == 'Fetch Policy Details')
                            {
                                $curl = Curl::to($cUrl);

                            }

				else if (isset($additionalData['root_tag'])) {
					$cRequest = ArrayToXml::convert($cInputs, $additionalData['root_tag']);
					$cRequest = preg_replace('/<\\?xml .*\\?>/i', '', $cRequest);
                    $additionalData['headers'] = [
                        'Content-Type' => 'application/xml'
                    ];
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withHeader('Content-Length',strlen($cRequest))
						->withData($cRequest);
				} else {
					if ($additionalData['method'] == 'Policy PDF') {
						$cRequest = json_encode($cInputs);
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json'
                        ];
						$curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
							->withHeader('Content-Length',strlen($cRequest))
							->withData($cRequest);
					} elseif ($additionalData['method'] == 'Generate Base64 Pdf') {
						$cRequest = json_encode($cInputs);
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json'
                        ];
						$curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}
					else {
						$cRequest = urlencode(http_build_query($cInputs));
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/xml'
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}
				}
				break;

            case 'cholla_mandalam':
                if ($additionalData['type'] == 'token') {
                    $cRequest = urldecode(http_build_query($cInputs));
                    $token = $additionalData['Authorization'];

                    $additionalData['headers'] = [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Authorization' => 'Basic ' . $token
                    ];
                    $curl = Curl::to($cUrl)
                        ->withHeaders($additionalData['headers'])
                        ->withData($cInputs);

                } else if ($additionalData['type'] == 'get_request') {
                    $cRequest = '';
                    $tocken = $additionalData['Authorization'];
                    $additionalData['headers'] = [
                        'Authorization' => 'Bearer '.$tocken
                    ];
                    $curl = Curl::to($cUrl)->withHeaders($additionalData['headers']);

                } else if($additionalData['type'] == 'Query API'){
                    $cRequest = urldecode(http_build_query($cInputs));
                    $curl = Curl::to($cUrl)->withData($cInputs);
				} else if($additionalData['type'] == 'Breakin Token'){
					$cRequest = '';
					$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cInputs);
				} else {
                    $cRequest = json_encode($cInputs);
                    $tocken = $additionalData['Authorization'];
                    $additionalData['headers'] = [
                        'Authorization' => 'Bearer '.$tocken
                    ];

                    $curl = Curl::to($cUrl)
                        ->withHeaders($additionalData['headers'])
                        ->withData($cInputs);
                }

                break;

			case 'kotak':
				$cRequest = json_encode($cInputs);
				if($additionalData['method'] == 'Private Car Proposal Service' || $additionalData['method'] == 'Proposal Payment')
				{
					$curl = curl_init();
                        curl_setopt_array($curl, array(
                          CURLOPT_URL => $cUrl,
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_PROXY => config('constants.http_proxy'),
                          CURLOPT_ENCODING => '',
                          CURLOPT_MAXREDIRS => 10,
                          CURLOPT_TIMEOUT => 0,
                          CURLOPT_FOLLOWLOCATION => true,
                          CURLOPT_CUSTOMREQUEST => 'POST',
                          CURLOPT_POSTFIELDS =>$cRequest,
                          CURLOPT_HTTPHEADER => array(
                            'vTokenCode: '.$additionalData['headers']['vTokenCode'],
                            'Content-Type: application/json'
                          ),
                        ));

                        $proposal_service_response = curl_exec($curl);
                        curl_close($curl);
				}
	            if (!isset($additionalData['request_method'])) {
	                if (isset($additionalData['token'])) {
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json',
                            'vTokenCode' => $additionalData['token'],
                        ];
	                	$curl = Curl::to($cUrl)
	                	->withHeaders($additionalData['headers'])
						->withHeader('Content-Length',strlen($cRequest))
	                	->withData($cRequest);

	                } elseif (isset($additionalData['Key'])) {
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json',
                            'vRanKey' => $additionalData['Key']
                        ];
	                	$curl = Curl::to($cUrl)
	                	->withHeaders($additionalData['headers'])
						->withHeader('Content-Length',strlen($cRequest))
	                	->withData($cRequest);
	                }
	            } else {
					if (($additionalData['method'] ?? '')  == 'KOTAK POS REGISTRATION') {
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json'
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest)
							->withTimeout(0)
							->withConnectTimeout($global_timeout);
					}elseif(($additionalData['method'] ?? '')  == 'Payment check'){
						$cRequest = $cInputs;
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                            'Accept' => 'application/json'
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cInputs);
					}else{
                        $additionalData['headers'] = [
                            'vTokenCode' => $additionalData['TokenCode'],
                            'Content-Type' => 'application/json'
                        ];
	                	$curl = Curl::to($cUrl)
		                	->withHeaders($additionalData['headers'])
		                	->withData($cRequest);
					}
	            }

	            break;
				case 'magma':
					if ($additionalData['type'] == 'tokenGeneration') {
						$cRequest = $cInputs;
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                            'User-Agent' =>  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'
                        ];
						$curl = Curl::to($cUrl)
								->withHeaders($additionalData['headers'])
								->withData($cRequest);
					}elseif($additionalData['type'] == 'premiumCalculation' || $additionalData['type'] == 'IIBVerification' || $additionalData['type'] == 'ProposalGeneration' || $additionalData['type'] == 'proposalStatus' || $additionalData['type'] == 'policyGeneration' || $additionalData['type'] == 'policyPdfGeneration' || $additionalData['type'] == 'GetPaymentURL' || $additionalData['type'] == 'POS Submit'){
						$cRequest = json_encode($cInputs);
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json',
                            'User-Agent' =>  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
                            'Authorization' => 'Bearer '.$additionalData['token']
                        ];
						$curl = Curl::to($cUrl)
								->withHeaders($additionalData['headers'])
								->withData($cRequest)
								->withTimeout(0)
								->withConnectTimeout($global_timeout);
					}
					break;

				case 'iffco_tokio':
					if(isset($additionalData['root_tag'])) {
						$cRequest = ArrayToXML::convert($cInputs,$additionalData['root_tag']);
						$cRequest = str_replace('#replace',$cRequest,$additionalData['container']);
						$cRequest = preg_replace("/<\\?xml .*\\?>/i", "", $cRequest);
                        $additionalData['headers'] = [
                            'SOAPAction'=> 'http://tempuri.org/IService/',
                            'Content-Length' => strlen($cRequest),
                            'Content-Type' => 'text/xml; charset=utf-8'
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}
					if(isset($additionalData['method']) && $additionalData['method'] == 'PDF Generation'){
						$cRequest = json_encode($cInputs, true);
						$u = $additionalData['username'];
						$p = $additionalData['password'];
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json',
                            'Authorization' =>  'Basic '.base64_encode("$u:$p"),
                            'Accept' => 'application/json',
                            'Content-Length' => strlen($cRequest)
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}
				break;
				case 'bajaj_allianz':
					if(isset($additionalData['method']) && ($additionalData['method'] == 'Generate Policy Motor' || $additionalData['method'] == 'Recon_PG_Status')){
						$cRequest = json_encode($cInputs);
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json',
                            'Content-Length' => strlen($cRequest)
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					} else {
						$cRequest = ($additionalData['requestType'] == 'xml') ? $cInputs : json_encode($cInputs);
						if(!empty($additionalData['container'])) {
							$cRequest = preg_replace('/<\\?xml .*\\?>/i', '', $cInputs);
							$cRequest = str_replace("#replace", $cRequest, $additionalData['container']);
						}
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}

				break;

			case 'sbi':
				if (!empty($cInputs)) {
					if (isset($additionalData['rootTag'])) {

					}elseif ($additionalData['method'] == 'PDF Service') {
						$str = json_encode($cInputs,JSON_UNESCAPED_SLASHES);
						$cRequest = $str;
                        $additionalData['headers'] = [
                            'Content-type' => 'application/json',
                            'X-IBM-Client-Id' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF'),
                            'X-IBM-Client-Secret' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF'),
							'Content-Length' => strlen($cRequest)
                        ];
						$curl = Curl::to($cUrl);
							// ->withHeaders($additionalData['headers']);

							if (isset($additionalData['authorization'])) {
								$additionalData['headers']['Authorization'] = 'Bearer '.$additionalData['authorization'];
							}
                            $curl = $curl->withHeaders($additionalData['headers'])
							// $curl = $curl->withHeader('Content-Length: '.strlen($str))
							->withData($str);
					} else {
						$str = json_encode($cInputs);
						$cRequest = $str;
                        // $additionalData['headers'] = [
                        //     'Content-type' => 'application/json',
                        //     'X-IBM-Client-Id' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID'),
                        //     'X-IBM-Client-Secret' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET')
                        // ];
						$curl = Curl::to($cUrl);
							// ->withHeaders($additionalData['headers']);

							$additionalData['headers'] = [
								'Content-Type' => 'application/json',
								'X-IBM-Client-Id' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID'),
								'X-IBM-Client-Secret' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET'),
								// 'Content-Length' => strlen($cRequest),
								'Accept'  => 'application/json'
							];
						if (isset($additionalData['authorization'])) {
							// $curl = $curl->withHeader('Authorization: Bearer '.$additionalData['authorization']);
							$additionalData['headers']['Authorization'] = 'Bearer '.$additionalData['authorization'];
						}
                        $curl = $curl->withHeaders($additionalData['headers']);
						$curl = $curl->withHeader('Content-Length: '.strlen($str))
						->withData($str);
					}
				} else {
					if ($additionalData['method'] != 'Generate PDF TOKEN') {
                        $additionalData['headers'] = [
                            'Content-type' => 'application/json',
                            'X-IBM-Client-Id' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID'),
                            'X-IBM-Client-Secret' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET'),
							'Accept'  => 'application/json'
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers']);

					} else {
                        $additionalData['headers'] = [
                            'Content-type' => 'application/json',
                            'X-IBM-Client-Id' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_ID_PDF'),
                            'X-IBM-Client-Secret' => config('constants.IcConstants.sbi.SBI_X_IBM_CLIENT_SECRET_PDF'),
                        ];
						$curl = Curl::to($cUrl)->withHeaders($additionalData['headers']);
					}
				}
				break;

                case 'universal_sompo':
                    if($additionalData['method'] == 'Breakin Creation' || $additionalData['method'] == 'Check Inspection')
                    {
                        $cRequest = json_encode($cInputs);
                        $additionalData['headers'] = [
                            'Content-type' => 'application/json',
                            'Content-Length' => strlen($cRequest)
                        ];
                        $curl = Curl::to($cUrl)
                                ->withHeaders( $additionalData['headers'])
                                ->withData($cRequest);
                    }
                    elseif($additionalData['method'] == 'get payment status')
                    {
                        $curl = Curl::to($cUrl);
                    }
                    else
                    {
											if(config('IC.UNIVERSAL_SOMPO.V2.CAR.ENABLED') == 'Y')
											{
												$cRequest = (preg_replace('/<\\?xml .*\\?>/i', '', $cInputs));
											}
											else
											{
												$cRequest = htmlentities(preg_replace('/<\\?xml .*\\?>/i', '', $cInputs));
											}

                    $cRequest = str_replace("#replace", $cRequest, $additionalData['container']);
                    $additionalData['headers'] = [
                        'Content-type' => 'text/xml',
                        'Accept' => 'text/xml',
                        'Content-Length' => strlen($cRequest),
                        'SOAPAction'=> 'http://tempuri.org/IService1/'. $additionalData['soap_action']
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])->withData($cRequest);
                    }
                    break;

				case 'united_india':

					if($additionalData['method'] == 'Payment Token Generation') {
						$cRequest = $cInputs;
						$curl = Curl::to($cUrl)->withHeaders($additionalData['headers'])->withData($cRequest);
					}
					else if($additionalData['method'] == 'Check Payment Status')
					{
						$cRequest = json_encode($cInputs);
						$curl = Curl::to($cUrl)->withHeaders($additionalData['headers'])->withData($cRequest);
					}else{
						$cRequest = ArrayToXml::convert($cInputs);
						$cRequest = preg_replace('/<\\?xml .*\\?>/i', '',  $cRequest);
						$cRequest = str_replace("#replace",  $cRequest, $additionalData['container']);
                        $additionalData['headers'] = [
                            'SOAPAction'		=> 'http://tempuri.org/IService/',
                            'Content-Type' 		=> 'text/xml; charset=utf-8',
                            'SOAPAction' 		=> "http://ws.uiic.com/UGenericMotorOnlineService/". $additionalData['soap_action'] . "Request"
                        ];
						$curl = Curl::to($cUrl)->withTimeout(100)->withConnectTimeout(100)->withHeader('Content-Length',strlen($cRequest))->withHeaders($additionalData['headers'])->withData($cRequest);

					}
				break;

                case 'nic':
                    $cRequest = json_encode($cInputs);

                    $curl = Curl::to($cUrl)
                    ->withHeaders($additionalData['headers'])
                    ->withData($cRequest);
                break;

				case 'raheja':
					if ($additionalData['request_method'] == 'post'){
						$cRequest = json_encode($cInputs);
                        $additionalData['headers'] = [
                            'Content-Type'	=> 'application/json',
                            'Content-Length' => strlen($cRequest)
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}else{
						$cRequest = json_encode($cInputs);
                        $additionalData['headers'] = [
                            'Content-Type'	=> 'application/json',
                            'Content-Length' => strlen($cRequest)
                        ];
						$curl = Curl::to($cUrl)

							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}
					if (isset($additionalData['authorization']))
					{
						$curl->withHeader('Authorization', $additionalData['authorization']);
					}
					else
					{
						$webUserId = config('constants.IcConstants.raheja.WEB_USER_ID_RAHEJA_MOTOR');
						$password  = config('constants.IcConstants.raheja.PASSWORD_RAHEJA_MOTOR');
                        $additionalData['headers'] = [
                            'Content-Type'    => 'application/json',
                            'Authorization' => 'Basic ' . base64_encode("$webUserId:$password"),
                            'Accept' => 'application/json'
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}
				break;
				case 'edelweiss':
				if ($additionalData['method'] == 'Token genration') {
					$webUserId = config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_USER_NAME');
					$password  = config('constants.IcConstants.edelweiss.EDELWEISS_TOKEN_PASSWORD');
					$cRequest = $cInputs;
                    $additionalData['headers'] = [
                        'Content-Type'    => 'application/x-www-form-urlencoded',
                        'Authorization' => 'Basic ' . base64_encode("$webUserId:$password")
                    ];
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
						$additionalData['headers'] = [
							'webUserId' => $webUserId,
							'password'  => $password,
							'Content-type'  => 'application/x-www-form-urlencoded',
							'Authorization' => 'Basic '.base64_encode("$webUserId:$password")
						];
				}elseif($additionalData['method'] == 'Premium Calculation'|| 'Proposal Service' ||'Policy Generation'|| 'Payment Request' || 'Pdf Service' ){
					$cRequest = json_encode($cInputs);
					$apikey = config('constants.IcConstants.edelweiss.EDELWEISS_X_API_KEY');
					if(strtolower($additionalData['section'] ?? '') == 'bike') {
						$apikey = config('constants.IcConstants.edelweiss.EDELWEISS_BIKE_X_API_KEY');
					}
                    $additionalData['headers'] = [
                        'x-api-key' => $apikey,
                        'Content-type' => 'application/json',
                        'Accept'  => 'application/json',
                        'Authorization' => 'Bearer '.$additionalData['authorization'],
                        'Content-Length' => strlen($cRequest)
                    ];
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
				}
				break;
			case 'new_india':
				if (isset($additionalData['root_tag'])) {
					$cRequest = ArrayToXML::convert($cInputs,$additionalData['root_tag']);
	                $cRequest = preg_replace('/<\\?xml .*\\?>/i', '',  $cRequest);
	                $cRequest = str_replace("#replace",  $cRequest, $additionalData['container']);
					$webUserId = $additionalData['authorization'][0];
					$password =  $additionalData['authorization'][1];
                    $additionalData['headers'] = [
                        'Content-type' => 'application/soap+xml',
                        'Authorization' => 'Basic '.base64_encode("$webUserId:$password")
                    ];
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withHeader('Content-Length',strlen($cRequest))
						->withData($cRequest);
				}else{
					$cRequest = $cInputs;
					$cRequest = is_array($cRequest) ? json_encode($cRequest) : $cRequest;
					$webUserId = $additionalData['authorization'][0];
					$password =  $additionalData['authorization'][1];
                    $additionalData['headers'] = [
                        'Content-type' => 'application/json',
                        'Authorization' => 'Basic '.base64_encode("$webUserId:$password"),
                        'Accept' => 'application/json'
                        // 'Content-Length' => strlen($cRequest)
                    ];
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
					// $additionalData['headers'] = [
					// 	'Content-Type' => 'application/json',
					// 	'Authorization' => 'Basic '. base64_encode("$webUserId:$password"),
					// 	'Accept' => 'application/json'
					// ];
				}
				break;
					case 'oriental':
						if (isset($additionalData['type']) && $additionalData['type'] == 'Query API') {
							$cRequest = urldecode(http_build_query($cInputs));
							$curl = Curl::to($cUrl)->withData($cInputs);
						} else {
							$cRequest = ($additionalData['requestType'] == 'xml') ? $cInputs : json_encode($cInputs);
							$curl = Curl::to($cUrl)
								->withHeaders($additionalData['headers'])
								->withData($cRequest)
								->withTimeout(0)
								->withConnectTimeout($global_timeout);
						}
						
						break;

            case 'tata_aig_v2':

                if(isset($additionalData['type']) && $additionalData['type'] == 'token'){
                    $cRequest = urldecode(http_build_query($cInputs));

                    $curl = Curl::to($cUrl)
                        ->withData($cInputs);
                }
                else{
                    $cRequest = json_encode($cInputs);

                    $curl = Curl::to($cUrl)
                    ->withHeaders($additionalData['headers'])
                    ->withData($cRequest);
                }
            break;
			case 'live_chek':

                $cRequest = $additionalData['method'] === 'CheckInspectionStatus' ? '' : json_encode($cInputs);
                $curl = Curl::to($cUrl)->withHeaders($additionalData['headers']);

                if ($additionalData['method'] !== 'CheckInspectionStatus') {
                    $curl = $curl->withData($cRequest);
                }

            break;

            case 'wimwisure':
            // dd(json_encode($cInputs),$additionalData['headers']);

                $cRequest = $additionalData['method'] === 'Check-Inspection-Status' ? '' : json_encode($cInputs);
                $additionalData['headers']['Content-Type'] = 'Application/json';
                $curl = Curl::to($cUrl)
        // ->withHeader('Content-Type: Application/json')
					->withHeaders($additionalData['headers'])
					->withTimeout(0)
					->withConnectTimeout($global_timeout);

							if ($additionalData['method'] !== 'Check-Inspection-Status') {
									$curl = $curl->withData($cRequest);
							}

							break;
			case 'paytm':
				$companyAlias = $additionalData['company_alias'] ?? $companyAlias;
				$cRequest = $cInputs;

				if($additionalData['method'] == 'Transaction Status') {
					$cRequest = json_encode($cInputs);
				}
				
				$curl = Curl::to($cUrl)
				->withHeaders($additionalData['headers'])
				->withData($cRequest);
				break;

			case 'billdesk':
				if ($additionalData['method'] == 'Order Id Creation') {
					$cRequest = $cInputs;
					$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cInputs);
				} else if ($additionalData['method'] == 'Check Order Id Status') {
					$cRequest = $cInputs;
					$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cInputs);
				}
				break;

		}

		$startTime = new DateTime(date('Y-m-d H:i:s'));

		if (!empty(config('constants.http_proxy')) && $companyAlias != 'tata_aig')
                {
                    $curl = $curl->withProxy(config('constants.http_proxy'));
		}
		$responseObject = new stdClass();
		$response_status_code = NULL;
		// if($companyAlias == 'tata_aig' && ($additionalData['type'] ?? '') != 'renewal')
		if($companyAlias == 'tata_aig' && ( ($additionalData['type'] ?? '') != 'renewal' || ($additionalData['method'] ?? '') == 'token generation' ))
		{
			$curlResponse = $tataCurlResponse;
		}
		else if($companyAlias == 'kotak' && $additionalData['method'] == 'Private Car Proposal Service')
		{
			$curlResponse = $proposal_service_response;
		}
		else{
			$curl->returnResponseObject();
			$curl->withResponseHeaders();
			$curl->withTimeout($global_with_timeout);
			$curl->withConnectTimeout($global_timeout);
			if (isset($additionalData['requestMethod']) && $additionalData['requestMethod'] != "") {
				if (strtolower($additionalData['requestMethod']) == 'post') {
					$responseObject = $curl->post();
					$response_status_code = $responseObject->status;
					$curlResponse = $responseObject->content;
				} elseif (strtolower($additionalData['requestMethod']) == 'get') {
					$responseObject = $curl->get();
					$response_status_code = $responseObject->status;
					$curlResponse = $responseObject->content;
				} elseif (strtolower($additionalData['requestMethod']) == 'put') {
					$responseObject = $curl->put();
					$response_status_code = $responseObject->status;
					$curlResponse = $responseObject->content;
				} else {
					$curlResponse = json_encode($curl);
				}
			} else {
				$responseObject = $curl->get();
				$response_status_code = $responseObject->status;
				$curlResponse = $responseObject->content;
			}
		}
		if($companyAlias == 'hdfc_ergo' && isset($additionalData['root_tag']) && $additionalData['root_tag'] == 'PDF')
		{
			$cRequest = json_encode($cRequest);
		}
	    $endTime = new DateTime(date('Y-m-d H:i:s'));

	    $responseTime = $startTime->diff($endTime);

		$old_method_name = $companyAlias == 'icici_lombard' ? $additionalData['type'] : $additionalData['method'];

	    $wsLogdata = [
            'enquiry_id'     => $additionalData['enquiryId'],
            'product'       => (isset($additionalData['productName']))?$additionalData['productName']:'',
            'section'       => $additionalData['section'],
            // 'method_name'   => $companyAlias == 'icici_lombard' ? $additionalData['type'] : $additionalData['method'],
            'method_name'   => getGenericMethodName($old_method_name, $additionalData['transaction_type'] ?? ''),
            'company'       => $companyAlias,
            'method'        => $additionalData['requestMethod'] ?? 'get',
            'transaction_type'    => $additionalData['transaction_type'] ?? '',
            'request'       => $cRequest ?? '',
            'response'      => $curlResponse,
            'endpoint_url'  => $cUrl,
            'ip_address'    => request()->ip(),
            'start_time'    => $startTime->format('Y-m-d H:i:s'),
            'end_time'      => $endTime->format('Y-m-d H:i:s'),
            // 'response_time'	=> $responseTime->format('%H:%i:%s'),
            'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
            'created_at'    => Carbon::now(),
            'headers'       => isset($additionalData['headers']) ? json_encode($additionalData['headers']) : null,
        ];

        if (isset($additionalData['type']) && $additionalData['type'] == 'policyPdfGeneration') {
            if($companyAlias == 'icici_lombard'){
                $wsLogdata['response'] = null;//base64_encode($curlResponse);
            }
            else
            {
                $wsLogdata['response'] = null;
            }
        }
		if(in_array($companyAlias,['icici_lombard','future_generali']) && isset($additionalData['transaction_type']) && in_array($additionalData['transaction_type'],['POS REGISTRATION']))
		{
			unset($wsLogdata['enquiry_id']);
			$wsLogdata['agent_id'] = $additionalData['enquiryId'];
			$wsLogdata['section'] = 'POS REGISTRATION';
			$table = "pos_service_request_response_data";
			$data = PosServiceRequestResponse::create($wsLogdata);
			$webservice_id = $data->id;
		}
		elseif(isset($additionalData['transaction_type']) && strtolower($additionalData['transaction_type']) == 'quote')
		{
            $wsLogdata['policy_id'] = $additionalData['policy_id'] ?? request()->policyId ?? NULL;
			$wsLogdata['checksum'] = $additionalData['checksum'] ?? NULL;
			$table = "quote_webservice_request_response_data";
			$data = QuoteServiceRequestResponse::create($wsLogdata);
			$webservice_id = $data->id;
			insertIntoQuoteVisibilityLogs($data, 'CAR', request()->policyId);
		}
		else{
			$table = "webservice_request_response_data";
			$data = WebServiceRequestResponse::create($wsLogdata);
			$webservice_id = $data->id;
		}
		app('ApiTimeoutHelper')->monitorIcApi($responseObject, $wsLogdata, $webservice_id);
		#DB::table('webservice_request_response_data')->insert($wsLogdata);
		WebserviceRequestResponseDataOptionList::firstOrCreate([
			'company' => $companyAlias,
			'section' => $additionalData['section'],
			'method_name' => ($companyAlias == 'icici_lombard'  || $companyAlias == 'magma') ? $additionalData['type'] : $additionalData['method'],
		]);

		if (in_array($companyAlias, [
			'hdfc_ergo',
			'tata_aig_v2',
			'tata_aig'
		])) {
			if ($companyAlias == 'hdfc_ergo') {
				HdfcErgoHelper::storeToken(
					$additionalData,
					$curlResponse
				);
			} else {
				TataAigHelper::storeToken(
					$additionalData,
					$cInputs,
					$curlResponse
				);
			}
		}

		return [
			'webservice_id' => $webservice_id,
			'table' => $table,
			'response' => $curlResponse,
			'status_code' => $response_status_code
		];
	}
}
