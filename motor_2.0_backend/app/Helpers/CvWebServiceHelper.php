<?php

use App\Helpers\IcHelpers\HdfcErgoHelper;
use App\Helpers\IcHelpers\TataAigHelper;
use App\Models\LsqServiceLogs;
use App\Models\WebserviceRequestResponseDataOptionList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Ixudra\Curl\Facades\Curl;
use Illuminate\Support\Facades\DB;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\QuoteServiceRequestResponse;
use App\Models\WebServiceRequestResponse;

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
			case 'magma':
				if ($additionalData['type'] == 'tokenGeneration') {
					$cRequest = $cInputs;
					$additionalData['headers']['User-Agent'] = 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0';
					$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
				} elseif ($additionalData['type'] == 'premiumCalculation' || $additionalData['type'] == 'IIBVerification' || $additionalData['type'] == 'ProposalGeneration' || $additionalData['type'] == 'proposalStatus' || $additionalData['type'] == 'policyGeneration' || $additionalData['type'] == 'policyPdfGeneration' || $additionalData['type'] == 'GetPaymentURL') {
					$cRequest = json_encode($cInputs, JSON_UNESCAPED_SLASHES);
					$additionalData['headers']['User-Agent'] = 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0';
					$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest)
							->withTimeout(0)
							->withConnectTimeout($global_timeout);
				}
				break;

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
                if ($additionalData['type'] == 'tokenGeneration') {
                    $cRequest = $cInputs;
                    $additionalData['headers'] = [
                        'Content-type' => 'application/x-www-form-urlencoded'
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest)
							->withTimeout(0)
							->withConnectTimeout($global_timeout);
                }elseif($additionalData['type'] == 'IdvGeneration' || $additionalData['type'] == 'premiumCalculation' || $additionalData['type'] == 'proposalService' || $additionalData['type'] == 'premiumReCalculation' || $additionalData['type'] == 'proposalService' || $additionalData['type'] == 'policyGeneration' || $additionalData['type'] == 'policyPdfGeneration' || $additionalData['type'] == 'brekinInspectionStatus'){
                    $cRequest = json_encode($cInputs);
                    $additionalData['headers'] = [
                        'Content-type' => 'application/json',
                        'Authorization' => 'Bearer '.$additionalData['token'],
                        'Accept' =>  'application/json'
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest)
							->withTimeout(0)
							->withConnectTimeout($global_timeout);
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

                      /*  $curl->withHeader('IRDALicenceNumber: '.$pos_details['IRDALicenceNumber']);
                       $curl->withHeader('CertificateNumber: '.$pos_details['CertificateNumber']);
                       $curl->withHeader('PanCardNo: '.$pos_details['PanCardNo']);
                       $curl->withHeader('AadhaarNo: '.$pos_details['AadhaarNo']);
                       $curl->withHeader('ProductCode: '.$pos_details['ProductCode']); */
                    }
                }elseif($additionalData['type'] == 'paymentTokenGeneration' || $additionalData['type'] == 'transactionIdForPG' || $additionalData['type'] == 'customTokenForPayment'){
                    $cRequest = json_encode($cInputs);
					if(isset($additionalData['headers']))
					{
						$curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest);
					}else{
                        $additionalData['headers'] = [
                            'Content-type' => 'application/json',
                            'Authorization' => 'Basic '.base64_encode(config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_USERNAME').':'.config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PAYMENT_PASSWORD')),
                            'Accept' => 'application/json'
                        ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withData($cRequest);
					}
                }
				elseif($additionalData['type'] == 'checkBreakinStatus') {
					$cRequest = json_encode($cInputs);
                    $additionalData['headers'] = [
                        'Authorization' => 'Bearer ' . $additionalData['token'],
                        'Content-Type' => 'application/json'
                    ];
					$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cRequest);
				}
				break;

            case 'future_generali' :

	                if(isset($additionalData['method']) && $additionalData['method'] == 'Recon_Fetch_Trns')
		            {

		               $str = $cUrl . '?' . http_build_query($cInputs);
                       $additionalData['headers'] = [
                                'Content-Type' => 'application/x-www-form-urlencoded'
                       ];
		               $curl = Curl::to($str)
		                       ->withHeaders($additionalData['headers']);
		            }
	                elseif ($additionalData['soap_action'] == 'GetPDF')
	                {
	                    $cRequest = ArrayToXml::convert($cInputs);
	                    //$cRequest = htmlentities($cRequest);
	                    $cRequest = preg_replace('/<\\?xml .*\\?>/i', '',  $cRequest);
	                    $cRequest = str_replace("#replace",  $cRequest, $additionalData['container']);
	                    $cRequest = str_replace("root",  'tem:GetPDF', $cRequest);
                        $additionalData['headers'] = [
                            'Content-Type' =>  'text/xml',
                            'Content-Length' => strlen($cRequest)
                        ];
	                    $curl = Curl::to($cUrl)
	                            //->withHeader('SOAPAction:'.'http://tempuri.org/IService/' . $additionalData['soap_action'])
	                            ->withHeaders($additionalData['headers'])
	                            ->withData($cRequest);
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
	                            ->withHeaders($additionalData['headers'] )
	                            ->withData($cRequest);
	                }
	            	break;

	        case 'universal_sompo':
                    if($additionalData['method'] == 'Breakin Creation' || $additionalData['method'] == 'Check Inspection')
                    {
                        $cRequest = json_encode($cInputs);
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json',
                            'Content-Length' => strlen($cRequest)
                        ];
                        $curl = Curl::to($cUrl)
                                ->withHeaders($additionalData['headers'] )
                                ->withData($cRequest);
                    }
                    elseif($additionalData['method'] == 'get payment status')
                    {
                        $curl = Curl::to($cUrl);
                        $cRequest = $cUrl;
                    }
                    else
                    {
					$cRequest = htmlentities(preg_replace('/<\\?xml .*\\?>/i', '', $cInputs));
					if (config('IC.UNIVERSAL_SOMPO.V2.MISCD.ENABLED') == 'Y') {
						$cRequest = (preg_replace('/<\\?xml .*\\?>/i', '', $cInputs));
					}
					$cRequest = str_replace("#replace", $cRequest, $additionalData['container']);
					$additionalData['headers'] = [
                        'SOAPAction'=> 'http://tempuri.org/IService1/'. $additionalData['soap_action'],
                        'Content-Type' => 'text/xml',
                        'Content-Length' => strlen($cRequest),
                        'Accept' => 'text/xml'
                    ];
                    $curl = Curl::to($cUrl)->withHeaders($additionalData['headers'])->withData($cRequest);
                    }
                    break;


	        case 'inspection_request':
		            $cRequest = json_encode($cInputs);
                    $additionalData['headers'] = [
                        'Content-Type' => 'application/json',
                        'app-key' => config('constants.IcConstants.future_generali.APP_KEY_FG_LIVE_CHECK') .''
                    ];
	                $curl = Curl::to($cUrl)
			            ->withHeaders($additionalData['headers'])
			            ->withData($cRequest);
	            break;

			case 'shriram':
				$cRequest = ($additionalData['requestType'] == 'xml') ? $cInputs : json_encode($cInputs, JSON_UNESCAPED_SLASHES);
				$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cRequest);
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
					if (isset($additionalData['contentType']) && $additionalData['contentType'] == 'json')
					{
						$cRequest = json_encode($cInputs, JSON_UNESCAPED_SLASHES);
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json'
                        ];
						$curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
							->withData($cRequest)
							->withTimeout(0)
							->withConnectTimeout($global_timeout);
					}
					else
					{
						$cRequest = ($additionalData['requestType'] == 'xml') ? $cInputs : json_encode($cInputs);
						if(!empty($additionalData['container'])) {
							$cRequest = preg_replace('/<\\?xml .*\\?>/i', '', $cInputs);
							$cRequest = str_replace("#replace", $cRequest, $additionalData['container']);
						}
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
					}
				}
				break;

			case 'godigit':
				$cRequest = json_encode($cInputs, true);
				if($additionalData['method'] == 'PG Redirection' || $additionalData['method'] == 'Policy PDF' || $additionalData['method'] == 'RE HIT PDF' || $additionalData['method'] == 'Check Wimwisure Breakin Status')
				{
                    $additionalData['headers'] =[
                        'Content-Type' => 'application/json',
                        'Authorization' => $additionalData['authorization'],
                        'Accept' => 'application/json'
                    ];
                    $curl = Curl::to($cUrl)
                    ->withHeaders($additionalData['headers'])
                    ->withData($cRequest);

			}else{
				if (isset($additionalData['authorization'])) {
                    $additionalData['headers'] =[
                        'Authorization' => "Bearer " . $additionalData['authorization'],
						'integrationId' => $additionalData['integrationId'],
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ];
					$curl = Curl::to($cUrl)
						// ->withContentType('application/json')
						// ->withHeader('Authorization: ' . 'Bearer ' . "$additionalData[authorization]")
						// ->withHeader('Accept: application/json')
						// ->withHeader('integrationId:'."$additionalData[integrationId]")
                        ->withHeaders($additionalData['headers'])
						->withData($cRequest);
				}
				else
				{
                    $webUserId = $additionalData['webUserId'] ?? config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
                    $password  = $additionalData['password'] ?? config('constants.IcConstants.godigit.GODIGIT_PASSWORD');
                    $additionalData['headers'] = [
                        'webUserId' => $webUserId,
                        'password'  => $password,
                        'Content-type'  => 'application/json',
                        'Authorization' => 'Basic '.base64_encode("$webUserId:$password"),
                        'Accept' => 'application/json'
                    ];
					if (config("IC.GODIGIT.V2.CV.ENABLE") == "Y") {
						unset($additionalData['headers']['Authorization']);
						unset($additionalData['headers']['webUserId']);
						unset($additionalData['headers']['password']);
					} // as per Ic curl they are not passing those values in header
                    $curl = Curl::to($cUrl)
                                ->withHeaders($additionalData['headers'])
                                ->withData($cRequest);
				}
			}
				break;

			case 'reliance':
				$passProxy = false;
				if (in_array($additionalData['method'], ['Lead Creation', 'Search Lead', 'Lead Updation']))
				{
					$cRequest = $cInputs;
					$passProxy = true;
				}
				elseif ($additionalData['method'] == 'Lead Fetch Details')
				{
					$cRequest = json_encode($cInputs);
					$passProxy = true;
				}
				else
				{
					$cRequest = ArrayToXML::convert($cInputs,$additionalData['root_tag']);
					$cRequest = preg_replace("/<\\?xml .*\\?>/i", '', $cRequest);
				}

				$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withHeader('Content-Length:'.strlen($cRequest))
					->withData($cRequest)
					->withTimeout(0)
					->withConnectTimeout($global_timeout);

					if(config('constants.motorConstant.SMS_FOLDER') == 'ola' && config('OLA_SPECIFIC_PROXY') != "" && $passProxy )
					{
						$curl = $curl->withProxy( config('OLA_SPECIFIC_PROXY') );
					}
      		    break;
			case 'hdfc_ergo':
				if (config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_REQUEST_TYPE') == 'JSON') {
					$cRequest = !empty($cInputs) ? json_encode($cInputs, JSON_UNESCAPED_SLASHES) : '';
                    $additionalData['headers'] = [
                        'Content-type' => 'application/json',
                        'SOURCE' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_SOURCE'),
                        'CHANNEL_ID' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CHANNEL_ID'),
                        'PRODUCT_CODE' => $additionalData['product_code'],
                        'TransactionID' => $additionalData['transaction_id'],
                        'Accept' => 'application/json'
                    ];
					$curl = Curl::to($cUrl)
						// ->withHeaders($additionalData['headers'])
						->withTimeout(0)
						->withConnectTimeout($global_timeout);

					if ($additionalData['method'] == 'Token Generation') {
                        $additionalData['headers']['CREDENTIAL'] = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_CV_CREDENTIAL');
						$curl->withHeaders($additionalData['headers']);
					} else {
                        $additionalData['headers']['Token'] = $additionalData['token'];
						$curl->withHeaders($additionalData['headers']);
					}

					if ($cRequest != '') {
						$curl = $curl->withData($cRequest);
					}
				} else {
					if(isset($additionalData['root_tag']))
					{
						if($additionalData['root_tag'] == 'PDF')
						{
							$cRequest = $cInputs;
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
					if(isset($additionalData['root_tag']) && in_array($additionalData['root_tag'], ['PDF','PCVPremiumCalc']))
					{
                        $additionalData['headers']['Content-type'] = 'application/x-www-form-urlencoded';
						$curl = Curl::to($cUrl)
										->withHeaders($additionalData['headers'])
										->withData($cRequest);
					} else {
                        $additionalData['headers']['Content-type'] = 'application/x-www-form-urlencoded';
						$curl = Curl::to($cUrl)
							    ->withHeaders($additionalData['headers'])
								->withHeader('Content-Length',strlen($cRequest))
								->withData($cRequest);
					}
				}
			    break;
			case 'iffco_tokio':
				$cRequest = (isset($additionalData['requestType']) && $additionalData['requestType'] == 'xml') ? $cInputs : json_encode($cInputs);
				$curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cRequest);
				break;
            case 'fastlane':
                $cRequest = '';
                $additionalData['headers'] =[
                    'Content-type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($additionalData['username'] . ':' . $additionalData['password'])
                ];
                $curl = Curl::to($cUrl)
                        ->withHeaders($additionalData['headers']);
                break;
            case 'sbi' :
                if($additionalData['method'] == 'Token Generation'){
                    $cRequest = '';
                    $additionalData['headers'] =[
                        'Content-type' => 'application/json',
                        'X-IBM-Client-Id' => $additionalData['client_id'],
                        'X-IBM-Client-Secret' => $additionalData['client_secret']
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers']);

                }elseif($additionalData['method'] == 'Premium Calculation' || $additionalData['method'] == 'Policy Issurance' || $additionalData['method'] == 'Pdf Genration' || $additionalData['method'] == 'Bank Fetch Service'){
					if($additionalData['method'] == 'Pdf Genration')
					{
						$cRequest = json_encode($cInputs,JSON_UNESCAPED_SLASHES);
					}else{
						$cRequest = json_encode($cInputs);
					}
                    $additionalData['headers'] =[
                        'Content-type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-IBM-Client-Id' => $additionalData['client_id'],
                        'X-IBM-Client-Secret' => $additionalData['client_secret'],
                        'Authorization' => 'Bearer '.$additionalData['authorization']
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withHeader('Content-Length: '.strlen($cRequest))
                            ->withData($cRequest);
                }/* elseif($additionalData['method'] == 'Pdf Genration') {
                    $cRequest = '';
                    $curl = Curl::to($cUrl)
                            ->withHeader('Content-type: application/json')
                            ->withHeader('X-IBM-Client-Id: '.$additionalData['client_id'])
                            ->withHeader('X-IBM-Client-Secret: '.$additionalData['client_secret']);
                } */
                break;

				case 'oriental':
					if (isset($additionalData['type']) && $additionalData['type'] == 'Query API') {
						$cRequest = urldecode(http_build_query($cInputs));
						$curl = Curl::to($cUrl)->withData($cInputs);
					} else {
						$cRequest = $cInputs;
					    $curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest)
						->withTimeout(0)
						->withConnectTimeout($global_timeout);
					}
					break;
			case 'liberty_videocon':
				if (isset($additionalData['root_tag'])) {
					$jsonString = json_encode([$cInputs]);
					$cRequest = str_replace("#replace",  $jsonString, $additionalData['container']);
                    $additionalData['headers'] =[
                        // 'SOAPAction'		=> 'http://tempuri.org/IService/',
						'Content-Length' 	=> strlen($cRequest),
						'Content-Type' 		=> $additionalData['content_type'],
                    ];
					$curl = Curl::to($cUrl)
					->withTimeout(100)
					->withConnectTimeout(100)
					->withHeaders($additionalData['headers'])
					->withData($cRequest);
				} elseif(isset($additionalData['headers']['Authorization'])){
					$cRequest = !empty($cInputs) ? json_encode($cInputs) : '';
					$additionalData['headers'] = [
							'Content-Type' => 'Application/json',
							'Authorization' => $additionalData['headers']['Authorization']
						];
						$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);

				}
				else
				{
					$cRequest = !empty($cInputs) ? json_encode($cInputs) : '';
                    $additionalData['headers'] =[
						'Content-Type' 		=> 'Application/json'
                    ];
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
				}
				break;

			case 'tata_aig' :
				if($additionalData['method'] == 'checkProposalPaymentStatus')
				{
					$cRequest = !empty($cInputs) ? json_encode($cInputs) : '';

					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withData($cRequest);
				}
				else
				{
				$curlRequest = http_build_query($cInputs);

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
				CURLOPT_TIMEOUT => $global_timeout,//timeout
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => $curlRequest,
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/x-www-form-urlencoded'
				),
				));
				$tataCurlResponse = curl_exec($oldCurl);
				curl_close($oldCurl);
				}
				break;
            case 'royal_sundaram':
                if(is_array($cInputs))
                {
                    $cRequest = json_encode($cInputs);
                    $additionalData['headers'] = [
                        'Content-Type' => 'application/json'
                    ];
                    $curl = Curl::to($cUrl)
                            ->withHeaders($additionalData['headers'])
                            ->withHeader('Content-Length: '.strlen($cRequest))
                            ->withData($cRequest);
				} elseif (($additionalData['type'] ?? '') == 'transactionHistoryByQuote') {
					$cRequest = json_encode($cInputs, true);
                    $additionalData['headers'] = [
                        'Content-Type' => 'application/json'
                    ];
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

                $cRequest = $additionalData['method'] === 'Check-Inspection-Status' ? '' : json_encode($cInputs);
                $additionalData['headers']['Content-Type'] = 'application/json';
                $curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers'])
						->withTimeout(0)
						->withConnectTimeout($global_timeout);

                if ($additionalData['method'] !== 'Check-Inspection-Status') {
                    $curl = $curl->withData($cRequest);
                }

                break;

            case 'finsall':

				if(($additionalData['method'] ?? '') == 'Payment Response Decryption')
				{
					$cRequest = $cInputs;// json_encode($cInputs);
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers']);
					$curl = $curl->withData($cRequest);
					$cRequest = json_encode($cInputs);
				}
				else
				{
					$cRequest = json_encode($cInputs);
					$curl = Curl::to($cUrl)
						->withHeaders($additionalData['headers']);
					$curl = $curl->withData($cRequest);
				}

                break;

			case 'LSQ':
				$cRequest = !empty($cInputs) ? json_encode($cInputs, JSON_UNESCAPED_SLASHES) : '';

                $curl = Curl::to($cUrl)
					->withHeaders($additionalData['headers'])
					->withData($cRequest)
					->withTimeout(0)
					->withConnectTimeout($global_timeout);
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
			case 'cholla_mandalam':
					if ($additionalData['type'] == 'token') {
						$cRequest = urldecode(http_build_query($cInputs));
						$token = $additionalData['Authorization'];
                        $additionalData['headers'] = [
                            'Content-Type' =>'application/x-www-form-urlencoded',
                            'Authorization' => 'Basic ' . $token
                        ];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cInputs);
					} else if ($additionalData['type'] == 'get_request') {
						$cRequest = '';
						$tocken = $additionalData['Authorization'];
                        $additionalData['headers'] = [
							'Authorization' => 'Bearer '.$tocken,
						];
						$curl = Curl::to($cUrl)->withHeaders($additionalData['headers']);

					} else if($additionalData['type'] == 'Query API'){
						$cRequest = urldecode(http_build_query($cInputs));
						$curl = Curl::to($cUrl)->withData($cInputs);
					}else {
						$cRequest = json_encode($cInputs);
						$tocken = $additionalData['Authorization'];
                        $additionalData['headers'] = [
                            'Content-Type' => 'application/json',
							'Authorization' => 'Bearer '.$tocken,
						];
						$curl = Curl::to($cUrl)
							->withHeaders($additionalData['headers'])
							->withData($cRequest);
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
                    $additionalData['headers'] = [
                        'Content-Type'      => $additionalData['content_type'],
                        'WWW-Authenticate'  => $additionalData['WWW-Authenticate']
                    ];
                    $curl = Curl::to($cUrl)
                                ->withHeaders($additionalData['headers'])
                                ->withData($cRequest);
                break;

				case 'onepay':
				    $cRequest = json_encode($cInputs);
                    $curl = Curl::to($cUrl)->withHeaders($additionalData['headers']);
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
				case 'new_india':
					if (isset($additionalData['root_tag'])) 
					{
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
					}
					else
					{
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

		if (!empty(config('constants.http_proxy')) && ($companyAlias != 'tata_aig' || (isset($additionalData['method']) && $additionalData['method'] == 'checkProposalPaymentStatus')))
                {
                    $curl = $curl->withProxy(config('constants.http_proxy'));
		}

		$responseObject = new stdClass();
		$response_status_code = NULL;
		if($companyAlias == 'tata_aig' && (isset($additionalData['method']) && $additionalData['method'] != 'checkProposalPaymentStatus'))
		{
			$curlResponse = $tataCurlResponse;
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
					$curlResponse = $companyAlias == 'wimwisure' ? (array) $responseObject : $responseObject->content;
					// $curlResponse = ($companyAlias == 'wimwisure') ? $curl->returnResponseArray()->post() : $curl->post();
				} elseif (strtolower($additionalData['requestMethod']) == 'get') {
					$responseObject = $curl->get();
					$response_status_code = $responseObject->status;
					$curlResponse = $companyAlias == 'wimwisure' ? (array) $responseObject : $responseObject->content;
					// $curlResponse = ($companyAlias == 'wimwisure') ? $curl->returnResponseArray()->get() : $curl->get();
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
		if($companyAlias == 'tata_aig_v2')
		{
			$companyAlias = 'tata_aig';
		}

            if($companyAlias == 'fastlane')
            {
                $wsLogdata = [
                    'enquiry_id'        => $additionalData['enquiryId'],
                    'transaction_type'  => $additionalData['transaction_type'],
                    'request'           => $additionalData['reg_no'],
                    'response'          => $curlResponse,
                    'endpoint_url'      => $cUrl,
                    'ip_address'        => request()->ip(),
                    'response_time'	=> $responseTime->format('%H:%i:%s'),
                    'created_at'        => Carbon::now()
                ];
                DB::table('fastlane_request_response')->insert($wsLogdata);
            }
			elseif ($companyAlias == 'LSQ')
			{
				LsqServiceLogs::create([
					'enquiry_id'        => $additionalData['enquiryId'] ?? NULL,
                    'transaction_type'  => $additionalData['transaction_type'] ?? NULL,
                    'method'			=> $additionalData['requestMethod'] ?? 'get',
					'method_name'		=> $additionalData['method'],
                    'request'           => $cRequest,
                    'response'          => $curlResponse,
                    'endpoint_url'      => $cUrl,
                    'ip_address'        => request()->ip(),
                    'start_time'        => $startTime->format('Y-m-d H:i:s'),
                    'end_time'			=> $endTime->format('Y-m-d H:i:s'),
                    'response_time'		=> $responseTime->format('%H:%i:%s'),
					'headers'			=> json_encode($additionalData['headers'], JSON_UNESCAPED_SLASHES),
                    'created_at'        => Carbon::now()
				]);
			}
            else
            {
				$old_method_name = $companyAlias == 'icici_lombard' ? $additionalData['type'] : $additionalData['method'];

                $wsLogdata = [
                    'enquiry_id'     => $additionalData['enquiryId'],
                    'product'       => (isset($additionalData['productName']))?$additionalData['productName']:'',
                    'section'       => $additionalData['section'],
                    // 'method_name'   => ($companyAlias == 'icici_lombard') ? $additionalData['type'] : $additionalData['method'],
                    'method_name'   => getGenericMethodName($old_method_name, $additionalData['transaction_type'] ?? ''),
                    'company'       => $companyAlias,
                    'method'        => $additionalData['requestMethod'] ?? 'get',
                    'transaction_type'    => $additionalData['transaction_type'] ?? '',
                    'request'       => $cRequest,
                    'response'      => $companyAlias == 'wimwisure'? json_encode($curlResponse) : $curlResponse,
                    'endpoint_url'  => $cUrl,
                    'ip_address'    => request()->ip(),
                    'start_time'    => $startTime->format('Y-m-d H:i:s'),
                    'end_time'      => $endTime->format('Y-m-d H:i:s'),
                    // 'response_time'	=> $responseTime->format('%H:%i:%s'),
                    'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
                    'created_at'    => Carbon::now(),
                    'headers'       => isset($additionalData['headers']) ? json_encode($additionalData['headers'], JSON_UNESCAPED_SLASHES) : null,
                ];
                if (isset($additionalData['type']) && $additionalData['type'] == 'policyPdfGeneration' && $companyAlias != 'hdfc_ergo')
                {
                    $wsLogdata['response'] = base64_encode($curlResponse);
                }
				if(isset($additionalData['transaction_type']) && strtolower($additionalData['transaction_type']) == 'quote')
				{
					$wsLogdata['policy_id'] = $additionalData['policy_id'] ?? request()->policyId ?? NULL;
					$wsLogdata['checksum'] = $additionalData['checksum'] ?? NULL;
					$table = "quote_webservice_request_response_data";
					$data = QuoteServiceRequestResponse::create($wsLogdata);
					$webservice_id = $data->id ?? "";
					insertIntoQuoteVisibilityLogs($data, 'CV', request()->policyId);
				}
				else{
					$table = "webservice_request_response_data";
					$data = WebServiceRequestResponse::create($wsLogdata);
					$webservice_id = $data->id ?? "";
				}
				app('ApiTimeoutHelper')->monitorIcApi($responseObject, $wsLogdata, $webservice_id);
                #DB::table('webservice_request_response_data')->insert($wsLogdata);
				WebserviceRequestResponseDataOptionList::firstOrCreate([
                    'company' => $companyAlias,
                    'section' => $additionalData['section'],
                    'method_name' => ($companyAlias == 'icici_lombard') ? $additionalData['type'] : $additionalData['method'],
				]);
            }

			if (in_array($companyAlias, [
				'hdfc_ergo',
				'tata_aig',
				'tata_aig_v2'
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
				'webservice_id' => $webservice_id ?? null,
				'table' => $table ?? null,
				'response' => $curlResponse,
				'status_code' => $response_status_code
			];
	}
}
