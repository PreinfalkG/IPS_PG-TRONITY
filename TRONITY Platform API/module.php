<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/COMMON.php'; 

class TRONITYPlatformAPI extends IPSModule {

	use TRONITY_COMMON;

	//const API_URL_Authentication = "https://api-eu.TRONITY.io/oauth/authentication";
	//const API_URL_Bulk = "https://api-eu.TRONITY.io/v1/vehicles/%%vehicleId%%/bulk";

	const API_URL_Authentication = "https://api.tronity.tech/authentication";
	const API_URL_LatRecord = "https://api.tronity.tech/tronity/vehicles/%%vehicleId%%/last_record";

	private $logLevel = 3;
	private $logCnt = 0;
	private $enableIPSLogOutput = false;

	private $apiClientId;
	private $apiClientSecret;
	private $apiGrantType;
	private $vehicleId;


	public function __construct($InstanceID) {
	
		parent::__construct($InstanceID);		// Diese Zeile nicht löschen

		$currentStatus = @$this->GetStatus();
		if($currentStatus == 102) {				//Instanz ist aktiv
			$this->logLevel = $this->ReadPropertyInteger("LogLevel");
			$this->apiClientId = $this->ReadPropertyString("tbClientId");
			$this->apiClientSecret = $this->ReadPropertyString("tbClientSecret");		
			$this->apiGrantType = $this->ReadPropertyString("tbGrantType");		
			$this->vehicleId = $this->ReadPropertyString("tbVehicleId");	

			if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, sprintf("Log-Level is %d", $this->logLevel)); }
		} else {
			if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Current Status is '%s'", $currentStatus)); }	
		}
	}


	public function Create() {
		
		parent::Create();				//Never delete this line!

		$logMsg = sprintf("Create Modul '%s [%s]'...", IPS_GetName($this->InstanceID), $this->InstanceID);
		if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, $logMsg); }
		IPS_LogMessage(__CLASS__."_".__FUNCTION__, $logMsg);

		$logMsg = sprintf("KernelRunlevel '%s'", IPS_GetKernelRunlevel());
		if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, $logMsg); }	

		$this->RegisterPropertyBoolean('AutoUpdate', false);
		$this->RegisterPropertyInteger("TimerInterval", 240);		
		$this->RegisterPropertyInteger("LogLevel", 4);

		$this->RegisterPropertyString("tbClientId", "");
		$this->RegisterPropertyString("tbClientSecret", "");
		$this->RegisterPropertyString("tbGrantType", "app");
		$this->RegisterPropertyString("tbVehicleId", "");

		$this->RegisterTimer('TimerAutoUpdate_TRONITY', 0, 'TPA_TimerAutoUpdate_TRONITY($_IPS["TARGET"]);');

		$this->RegisterMessage(0, IPS_KERNELMESSAGE);
	}

	public function Destroy() {
		IPS_LogMessage(__CLASS__."_".__FUNCTION__, sprintf("Destroy Modul '%s' ...", $this->InstanceID));
		parent::Destroy();						//Never delete this line!
	}

	public function ApplyChanges() {
		parent::ApplyChanges();					//Never delete this line!

		$this->logLevel = $this->ReadPropertyInteger("LogLevel");
		if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Log-Level to %d", $this->logLevel)); }
		
		$this->RegisterProfiles();
		$this->RegisterVariables();  
			
		$autoUpdate = $this->ReadPropertyBoolean("AutoUpdate");		
		if($autoUpdate) {
			$timerInterval = $this->ReadPropertyInteger("TimerInterval");
		} else {
			$timerInterval = 0;
		}
		$this->SetUpdateInterval($timerInterval);
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data)	{
		$logMsg = sprintf("TimeStamp: %s | SenderID: %s | Message: %s | Data: %s", $TimeStamp, $SenderID, $Message, json_encode($Data));
		if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, $logMsg); }
		//IPS_LogMessage(__CLASS__."_".__FUNCTION__, $logMsg);
	}
	
	public function SetUpdateInterval(int $timerInterval) {
		if ($timerInterval == 0) {  
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Auto-Update stopped [TimerIntervall = 0]"); }	
		}else if ($timerInterval < 60) { 
			$timerInterval = 60; 
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Auto-Update Timer Intervall to %s sec", $timerInterval)); }	
		} else {
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Auto-Update Timer Intervall to %s sec", $timerInterval)); }
		}
		$this->SetTimerInterval("TimerAutoUpdate_TRONITY", $timerInterval*1000);	
	}


	public function TimerAutoUpdate_TRONITY() {

		if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "TimerAutoUpdate_TRONITY called ..."); }

		if(true) {
			$this->LastRecord("AutoUpdateTimer");
		} else {

			$skipUdateSec = 600;
			$lastUpdate  = time() - round(IPS_GetVariable($this->GetIDForIdent("updateCntError"))["VariableUpdated"]);
			if ($lastUpdate > $skipUdateSec) {

				//$this->UpdateBulk("AutoUpdateTimer");
				$this->LastRecord("AutoUpdateTimer");

			} else {
				SetValue($this->GetIDForIdent("updateCntSkip"), GetValue($this->GetIDForIdent("updateCntSkip")) + 1);
				$logMsg =  sprintf("INFO :: Skip Update for %d sec for Instance '%s' [%s] >> last error %d seconds ago...", $skipUdateSec, $this->InstanceID, IPS_GetName($this->InstanceID),  $lastUpdate);
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, $logMsg); }
			}	
		}					
	}


	public function UpdateBulk_OLD(string $Text) {

		if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Update Bulk ..."); }

			$currentStatus = $this->GetStatus();
			if($currentStatus == 102) {		
			
				$start_Time = microtime(true);

				try {

					//$a = 0;	$b = 0;	$c = $a / $b;  //Test Try-Catch
					//throw new Exception('! Throw TEST Exception !');

					$apiUrl = self::API_URL_Bulk;
					$apiUrl = str_replace("%%vehicleId%%", $this->vehicleId, $apiUrl);
					$api_accessToken = $this->GetApiAccessToken();
	
					if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("HTTP_REQUEST: %s", $apiUrl)); }

					$options = array(
						'http' => array(
						'method'  => 'GET',
						'timeout' => 10,
						'header'=>  "Authorization: Bearer ". $api_accessToken . "\r\n" .
									"Content-Type: application/json\r\n" .
									"Accept: application/json\r\n"
						)
					);

					$data = $this->RequestHttpData($apiUrl, $options);
					$jsonData = json_decode($data);
					
					if(isset($jsonData->odometer)) { 
						SetValue($this->GetIDForIdent("odometer"), $jsonData->odometer);
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'odometer' not found in JSON data"); }
					}

					if(isset($jsonData->level)) { 

						$level = $jsonData->level;
						SetValue($this->GetIDForIdent("level"), $level);

						if(isset($jsonData->range)) { 
							$range = $jsonData->range;
							SetValue($this->GetIDForIdent("range"), $range);

							///
							// CALC Custom Values
							if(true) {
								$calcBattEnergyLeft = 58/100 * $level;
								SetValue($this->GetIDForIdent("calcBattEnergyLeft"), round($calcBattEnergyLeft,1));
							
								$calcConsumption = $calcBattEnergyLeft / $range * 100;
								SetValue($this->GetIDForIdent("calcConsumption"), round($calcConsumption,1));
							
								$calcEstimatedRangeOnFullCharge = $range / $level * 100;
								SetValue($this->GetIDForIdent("calcEstimatedRangeOnFullCharge"), round($calcEstimatedRangeOnFullCharge));
							
								$calcPercentOfWLTP = 100 / 424 * $calcEstimatedRangeOnFullCharge;
								SetValue($this->GetIDForIdent("calcPercentOfWLTP"), round($calcPercentOfWLTP,1));
							
								$calcBattEnergyLeftTEMP = GetValue($this->GetIDForIdent("calcBattEnergyLeft"));
								$calcBattEnergyDiff = $calcBattEnergyLeft - $calcBattEnergyLeftTEMP;
								if($calcBattEnergyDiff > 0) {
									$calcBattChargedTemp = GetValue($this->GetIDForIdent("calcBattCharged"));
									SetValue($this->GetIDForIdent("calcBattCharged"), round($calcBattChargedTemp + abs($calcBattEnergyDiff),1));
								} if($calcBattEnergyDiff < 0) {
									$calcBattDisChargedTemp = GetValue($this->GetIDForIdent("calcBattDisCharged"));
									SetValue($this->GetIDForIdent("calcBattDisCharged"), round($calcBattDisChargedTemp + abs($calcBattEnergyDiff),1));	
								}
							}

						} else {
							if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'range' not found in JSON data"); }
						}

					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'level' not found in JSON data"); }
					}	


					if(isset($jsonData->charging)) { 
						$charging = $jsonData->charging;
						SetValue($this->GetIDForIdent("chargingStatusTxt"), $charging);
				
						$chargingStatus = -99;
						switch($charging) {
							case "Disconnected";
								$chargingStatus = 0;
								break;
							case "NoPower";
								$chargingStatus = 1;
								break;		
							case "Starting";
								$chargingStatus = 2;
								break;										
							case "Charging";
								$chargingStatus = 3;
								break;										
							case "Complete";
								$chargingStatus = 4;
								break;										
							case "Stopped";
								$chargingStatus = 5;
								break;										
							case "Error";
								$chargingStatus = 10;
								break;										
							default:
								$chargingStatus = 11;
							break;
						}
						SetValue($this->GetIDForIdent("chargingStatus"), $chargingStatus);
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'charging' not found in JSON data"); }
					}	

					$chargeRemainingTime = -3600;
					if(isset($jsonData->chargeRemainingTime)) { 
						$chargeRemainingTime = $jsonData->chargeRemainingTime;
						if($chargeRemainingTime >=3600) {
							$chargeRemainingTime = $chargeRemainingTime -3600;
						}
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'chargeRemainingTime' not found in JSON data"); }
					}	
					SetValue($this->GetIDForIdent("chargeRemainingTime"), $chargeRemainingTime);


					if(isset($jsonData->plugged)) { 
						SetValue($this->GetIDForIdent("plugged"), $jsonData->plugged);
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'plugged' not found in JSON data"); }
					}	

					$chargerPower = -1;
					if(isset($jsonData->chargerPower)) { 
						$chargerPower = $jsonData->chargerPower;
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'chargerPower' not found in JSON data"); }
					}
					SetValue($this->GetIDForIdent("chargerPower"), $chargerPower);

					$latitude = 0;
					if(isset($jsonData->latitude)) { 
						$latitude =  round(floatval($jsonData->latitude), 5);
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'latitude' not found in JSON data"); }
					}	
					SetValue($this->GetIDForIdent("latitude"), $latitude);

					$longitude = 0;
					if(isset($jsonData->longitude)) { 
						$longitude = round(floatval($jsonData->longitude), 5);
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'longitude' not found in JSON data"); }
					}	
					SetValue($this->GetIDForIdent("longitude"), $longitude);

					if(($latitude != 0) AND ($longitude != 0)) {
						$coordinates = sprintf("%s,%s", $latitude, $longitude);
						SetValue($this->GetIDForIdent("coordinates"), $coordinates);
					}

					if(isset($jsonData->timestamp)) { 
						SetValue($this->GetIDForIdent("timestamp"), round($jsonData->timestamp/1000));
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'timestamp' not found in JSON data"); }
					}	

					if(isset($jsonData->lastUpdate)) { 
						SetValue($this->GetIDForIdent("lastUpdate"), round($jsonData->lastUpdate/1000));
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'timestamp' not found in JSON data"); }
					}	

					SetValue($this->GetIDForIdent("updateCntOk"), GetValue($this->GetIDForIdent("updateCntOk")) + 1);  
					if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Update IPS Variables DONE"); }

				} catch (Exception $e) {
					$errorMsg = $e->getMessage();
					//$errorMsg = print_r($e, true);
					SetValue($this->GetIDForIdent("updateCntError"), GetValue($this->GetIDForIdent("updateCntError")) + 1);  
					SetValue($this->GetIDForIdent("updateLastError"), $errorMsg);
					if($this->logLevel >= LogLevel::ERROR) { $this->AddLog(__FUNCTION__, sprintf("Exception occurred :: %s", $errorMsg)); }
				}

				$duration = $this->CalcDuration_ms($start_Time);
				SetValue($this->GetIDForIdent("updateLastDuration"), $duration); 

			} else {
				//SetValue($this->GetIDForIdent("instanzInactivCnt"), GetValue($this->GetIDForIdent("instanzInactivCnt")) + 1);
				if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Instanz '%s - [%s]' not activ [Status=%s]", $this->InstanceID, IPS_GetName($this->InstanceID), $currentStatus)); }
			}
			
	}


	public function LastRecord(string $Text) {

		if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Update LastRecord ..."); }

			$currentStatus = $this->GetStatus();
			if($currentStatus == 102) {		
			
				$start_Time = microtime(true);

				try {

					//$a = 0;	$b = 0;	$c = $a / $b;  //Test Try-Catch
					//throw new Exception('! Throw TEST Exception !');

					$apiUrl = self::API_URL_LatRecord;
					$apiUrl = str_replace("%%vehicleId%%", $this->vehicleId, $apiUrl);
					$api_accessToken = $this->GetApiAccessToken();
	
					if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("HTTP_REQUEST: %s", $apiUrl)); }

					$options = array(
						'http' => array(
						'method'  => 'GET',
						'timeout' => 10,
						'header'=>  "Authorization: Bearer ". $api_accessToken . "\r\n" .
									"Content-Type: application/json\r\n" .
									"Accept: application/json\r\n"
						)
					);

					$data = $this->RequestHttpData($apiUrl, $options);
					$jsonData = json_decode($data);
					
					if(isset($jsonData->odometer)) { 
						SetValue($this->GetIDForIdent("odometer"), $jsonData->odometer);
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'odometer' not found in JSON data"); }
					}

					if(isset($jsonData->level)) { 

						$level = $jsonData->level;
						SetValue($this->GetIDForIdent("level"), $level);

						if(isset($jsonData->range)) { 
							$range = $jsonData->range;
							SetValue($this->GetIDForIdent("range"), $range);

							///
							// CALC Custom Values
							if(true) {
								$calcBattEnergyLeft = 58/100 * $level;
								SetValue($this->GetIDForIdent("calcBattEnergyLeft"), round($calcBattEnergyLeft,1));
							
								$calcConsumption = $calcBattEnergyLeft / $range * 100;
								SetValue($this->GetIDForIdent("calcConsumption"), round($calcConsumption,1));
							
								$calcEstimatedRangeOnFullCharge = $range / $level * 100;
								SetValue($this->GetIDForIdent("calcEstimatedRangeOnFullCharge"), round($calcEstimatedRangeOnFullCharge));
							
								$calcPercentOfWLTP = 100 / 424 * $calcEstimatedRangeOnFullCharge;
								SetValue($this->GetIDForIdent("calcPercentOfWLTP"), round($calcPercentOfWLTP,1));
							
								$calcBattEnergyLeftTEMP = GetValue($this->GetIDForIdent("calcBattEnergyLeft"));
								$calcBattEnergyDiff = $calcBattEnergyLeft - $calcBattEnergyLeftTEMP;
								if($calcBattEnergyDiff > 0) {
									$calcBattChargedTemp = GetValue($this->GetIDForIdent("calcBattCharged"));
									SetValue($this->GetIDForIdent("calcBattCharged"), round($calcBattChargedTemp + abs($calcBattEnergyDiff),1));
								} if($calcBattEnergyDiff < 0) {
									$calcBattDisChargedTemp = GetValue($this->GetIDForIdent("calcBattDisCharged"));
									SetValue($this->GetIDForIdent("calcBattDisCharged"), round($calcBattDisChargedTemp + abs($calcBattEnergyDiff),1));	
								}
							}

						} else {
							if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'range' not found in JSON data"); }
						}

					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'level' not found in JSON data"); }
					}	


					if(isset($jsonData->charging)) { 
						$charging = $jsonData->charging;
						SetValue($this->GetIDForIdent("chargingStatusTxt"), $charging);
				
						$chargingStatus = -99;
						switch($charging) {
							case "Disconnected";
								$chargingStatus = 0;
								break;
							case "NoPower";
								$chargingStatus = 1;
								break;		
							case "Starting";
								$chargingStatus = 2;
								break;										
							case "Charging";
								$chargingStatus = 3;
								break;										
							case "Complete";
								$chargingStatus = 4;
								break;										
							case "Stopped";
								$chargingStatus = 5;
								break;										
							case "Error";
								$chargingStatus = 10;
								break;										
							default:
								$chargingStatus = 11;
							break;
						}
						SetValue($this->GetIDForIdent("chargingStatus"), $chargingStatus);
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'charging' not found in JSON data"); }
					}	

					$chargeRemainingTime = -3600;
					if(isset($jsonData->chargeRemainingTime)) { 
						$chargeRemainingTime = $jsonData->chargeRemainingTime;
						if($chargeRemainingTime >=3600) {
							$chargeRemainingTime = $chargeRemainingTime -3600;
						}
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'chargeRemainingTime' not found in JSON data"); }
					}	
					SetValue($this->GetIDForIdent("chargeRemainingTime"), $chargeRemainingTime);


					if(isset($jsonData->plugged)) { 
						SetValue($this->GetIDForIdent("plugged"), $jsonData->plugged);
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'plugged' not found in JSON data"); }
					}	

					$chargerPower = -1;
					if(isset($jsonData->chargerPower)) { 
						$chargerPower = $jsonData->chargerPower;
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'chargerPower' not found in JSON data"); }
					}
					SetValue($this->GetIDForIdent("chargerPower"), $chargerPower);

					$latitude = 0;
					if(isset($jsonData->latitude)) { 
						$latitude =  round(floatval($jsonData->latitude), 5);
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'latitude' not found in JSON data"); }
					}	
					SetValue($this->GetIDForIdent("latitude"), $latitude);

					$longitude = 0;
					if(isset($jsonData->longitude)) { 
						$longitude = round(floatval($jsonData->longitude), 5);
					} else {
						if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Property 'longitude' not found in JSON data"); }
					}	
					SetValue($this->GetIDForIdent("longitude"), $longitude);

					if(($latitude != 0) AND ($longitude != 0)) {
						$coordinates = sprintf("%s,%s", $latitude, $longitude);
						SetValue($this->GetIDForIdent("coordinates"), $coordinates);
					}

					if(isset($jsonData->timestamp)) { 
						SetValue($this->GetIDForIdent("timestamp"), round($jsonData->timestamp/1000));
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'timestamp' not found in JSON data"); }
					}	

					if(isset($jsonData->lastUpdate)) { 
						SetValue($this->GetIDForIdent("lastUpdate"), round($jsonData->lastUpdate/1000));
					} else {
						if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, "Property 'timestamp' not found in JSON data"); }
					}	

					SetValue($this->GetIDForIdent("updateCntOk"), GetValue($this->GetIDForIdent("updateCntOk")) + 1);  
					if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Update IPS Variables DONE"); }

				} catch (Exception $e) {
					$errorMsg = $e->getMessage();
					//$errorMsg = print_r($e, true);
					SetValue($this->GetIDForIdent("updateCntError"), GetValue($this->GetIDForIdent("updateCntError")) + 1);  
					SetValue($this->GetIDForIdent("updateLastError"), $errorMsg);
					if($this->logLevel >= LogLevel::ERROR) { $this->AddLog(__FUNCTION__, sprintf("Exception occurred :: %s", $errorMsg)); }
				}

				$duration = $this->CalcDuration_ms($start_Time);
				SetValue($this->GetIDForIdent("updateLastDuration"), $duration); 

			} else {
				//SetValue($this->GetIDForIdent("instanzInactivCnt"), GetValue($this->GetIDForIdent("instanzInactivCnt")) + 1);
				if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Instanz '%s - [%s]' not activ [Status=%s]", $this->InstanceID, IPS_GetName($this->InstanceID), $currentStatus)); }
			}
			
	}


	public function GetApiAccessToken() {

		$api_accessToken = GetValue($this->GetIDForIdent("api_accessToken"));
		$api_accessToken_expires = GetValueInteger($this->GetIDForIdent("api_accessToken_expires"));
		$api_accessToken_expires = $api_accessToken_expires - 15;

		$now = time();
		if($api_accessToken == "") {
			if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("NEED Updade API 'accessToken' [empty '%s']", $api_accessToken)); }   
			$api_accessToken = $this->UpdateApiAccessToken();
		} else if($now >= $api_accessToken_expires) {
			if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("NEED Updade API 'accessToken' [expires @%s]", date('d.m.Y H:i:s', $api_accessToken_expires))); }   
			$api_accessToken = $this->UpdateApiAccessToken();
		} else {
			if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("API 'accessToken' should be valid > expires @%s", date('d.m.Y H:i:s', $api_accessToken_expires))); }   
		}

		return $api_accessToken;
	}

	public function UpdateApiAccessToken() {

		try {

			$api_access_token = false;
			$api_accessToken_expires = false;

			$apiUrl = self::API_URL_Authentication;
			$data = ["client_id" => $this->apiClientId, "client_secret" => $this->apiClientSecret, "grant_type" => $this->apiGrantType];
			$options = array(
				'http' => array(
					'method'  => 'POST',
					'content' => json_encode( $data ),
					'header'=>  "Content-Type: application/json\r\n" .
								"Accept: application/json\r\n"
					)
				);

			$result = $this->RequestHttpData($apiUrl, $options);	  
			$response = json_decode( $result );
			//$response->id;
			//$response->token_type;

			if(isset($response->access_token)) { 
				$api_access_token =  $response->access_token;
				SetValue($this->GetIDForIdent("api_accessToken"), $api_access_token);
			} else {
				$errorMsg = sprintf("Property 'access_token' not found in JSON Response Data \n on Line: %s | Function: %s | File: %s",  __LINE__, __FUNCTION__, __FILE__);
				throw new Exception($errorMsg, 40);
			}

			if(isset($response->expires_in)) { 
				$api_accessToken_expires = time() + $response->expires_in;
				SetValue($this->GetIDForIdent("api_accessToken_expires"), $api_accessToken_expires);					
			} else {
				$errorMsg = sprintf("Property 'expires_in' not found in JSON Response Data \n on Line: %s | Function: %s | File: %s",  __LINE__, __FUNCTION__, __FILE__);
				throw new Exception($errorMsg, 41);
			}
			
			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("NEW API api_accessToken expires @%s", date('d.m.Y H:i:s',$api_accessToken_expires))); }   

			return $api_access_token;

		} catch (Exception $e) {
			//$errorMsg = $e->getMessage();
			//$errorMsg = print_r($e, true);

			SetValue($this->GetIDForIdent("api_accessToken"), "");
			SetValue($this->GetIDForIdent("api_accessToken_expires"), 0);

			$errorMsg = sprintf("ERROR - Update TRONITY API AccessToken > %s \n on Line: %s | Function: %s | File: %s",  $e->getMessage(), __LINE__, __FUNCTION__, __FILE__);
			if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, $errorMsg); }
			throw new Exception($errorMsg, 10, $e);

		}

	}

	public function GetJsonProperty(string $json, string $propertyName) {

		if(property_exists($json, $propertyName)) {
			return $json->{$propertyName};
		} else {
			return null;
		}

	}


	public function RequestHttpData(string $url, array $options) {

		$result = false;

		$context  = stream_context_create( $options );
		if (($data = @file_get_contents( $url, false, $context )) === false) {

			if(isset($http_response_header)) {
				$errorMsg = sprintf("ERROR - Request TRONITY API '%s' > %s \n on Line: %s | Function: %s | File: %s", $url, print_r($http_response_header, true), __LINE__, __FUNCTION__, __FILE__);
				if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, $errorMsg); }

				$error = error_get_last();
				$errorMsg = print_r($error, true);
				$errorMsg = sprintf("ERROR - Request TRONITY API '%s' > %s \n on Line: %s | Function: %s | File: %s", $url, $errorMsg, __LINE__, __FUNCTION__, __FILE__);
				if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, $errorMsg); }					
				
				throw new Exception($errorMsg, 10);
			} else {
				$error = error_get_last();
				$errorMsg = print_r($error, true);
				$errorMsg = sprintf("ERROR - Request TRONITY API '%s' > %s \n on Line: %s | Function: %s | File: %s", $url, $errorMsg, __LINE__, __FUNCTION__, __FILE__);
				if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, $errorMsg); }
				throw new Exception($errorMsg, 11);
			}

		} else {

			if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("HTTP_RESPONSE_HEADER: %s", print_r($http_response_header, true))); }
			if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("HTTP_RESPONSE_DATA: %s", print_r($data, true))); }					

			if (strpos($http_response_header[0], "200")) {

				$result = $data;

			} else 	if (strpos($http_response_header[0], "201")) { 

				$result = $data;

			} else 	if (strpos($http_response_header[0], "401")) { 

				SetValue($this->GetIDForIdent("api_accessToken"), "");
				SetValue($this->GetIDForIdent("api_accessToken_expires"), 0);		

				$errorMsg = sprintf("HTTP_RESPONSE_HEADER '400': %s", print_r($http_response_header, true));
				$errorMsg = sprintf("ERROR - Request TRONITY API '%s' > %s \n on Line: %s | Function: %s | File: %s", $url, $errorMsg, __LINE__, __FUNCTION__, __FILE__);
				if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, $errorMsg); }
				throw new Exception($errorMsg, 20);

			} else {
				$errorMsg = print_r($http_response_header, true);
				$errorMsg = sprintf("ERROR - Request TRONITY API '%s' > %s \n on Line: %s | Function: %s | File: %s", $url, $http_response_header[0], __LINE__, __FUNCTION__, __FILE__);
				if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, $errorMsg); }
				throw new Exception($errorMsg, 21);				
			}

		}

		return $result;
	}



	public function ResetUpdateVariables(string $Text) {
		if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, 'RESET Update Variables', 0); }
		SetValue($this->GetIDForIdent("updateCntOk"), 0);
		SetValue($this->GetIDForIdent("updateCntSkip"), 0);
		SetValue($this->GetIDForIdent("updateCntError"), 0); 
		SetValue($this->GetIDForIdent("updateLastError"), "-"); 
		SetValue($this->GetIDForIdent("updateLastDuration"), 0); 
	}


	protected function RegisterProfiles() {


		if ( !IPS_VariableProfileExists('EV.level') ) {
			IPS_CreateVariableProfile('EV.level', VARIABLE::TYPE_INTEGER );
			IPS_SetVariableProfileDigits('EV.level', 0 );
			IPS_SetVariableProfileText('EV.level', "", " %" );
			IPS_SetVariableProfileValues('EV.level', 0, 100, 1);
		} 
		if ( !IPS_VariableProfileExists('EV.km') ) {
			IPS_CreateVariableProfile('EV.km', VARIABLE::TYPE_INTEGER );
			IPS_SetVariableProfileDigits('EV.km', 0 );
			IPS_SetVariableProfileText('EV.km', "", " km" );
			//IPS_SetVariableProfileValues('EV.km', 0, 0, 0);
		} 		
		
		if ( !IPS_VariableProfileExists('EV.ChargingStatus') ) {
			IPS_CreateVariableProfile('EV.ChargingStatus', VARIABLE::TYPE_INTEGER );
			IPS_SetVariableProfileText('EV.ChargingStatus', "", "" );
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', -1, "[%d] unknown", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 0, "[%d] Disconnected", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 1, "[%d] NoPower", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 2, "[%d] Starting", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 3, "[%d] Charging", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 4, "[%d] Complete", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 5, "[%d] Stopped", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 6, "[%d] unknown", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 7, "[%d] unknown", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 8, "[%d] unknown", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 9, "[%d] unknown", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 10, "[%d] Error", "", -1);
			IPS_SetVariableProfileAssociation ('EV.ChargingStatus', 11, "[%d] unknown", "", -1);
		}   			


		if ( !IPS_VariableProfileExists('EV.kWh') ) {
			IPS_CreateVariableProfile('EV.kWh', VARIABLE::TYPE_FLOAT );
			IPS_SetVariableProfileDigits('EV.kWh', 1);
			IPS_SetVariableProfileText('EV.kWh', "", " kWh" );
			//IPS_SetVariableProfileValues('EV.kWh', 0, 0, 0);
		} 

		if ( !IPS_VariableProfileExists('EV.kWh_100km') ) {
			IPS_CreateVariableProfile('EV.kWh_100km', VARIABLE::TYPE_FLOAT );
			IPS_SetVariableProfileDigits('EV.kWh_100km', 1);
			IPS_SetVariableProfileText('EV.kWh_100km', "", " kWh/100km" );
			//IPS_SetVariableProfileValues('EV.kWh_100km', 0, 0, 0);
		} 			

		if ( !IPS_VariableProfileExists('EV.Percent') ) {
			IPS_CreateVariableProfile('EV.Percent', VARIABLE::TYPE_FLOAT );
			IPS_SetVariableProfileDigits('EV.Percent', 1);
			IPS_SetVariableProfileText('EV.Percent', "", " %" );
			//IPS_SetVariableProfileValues('EV.Percent', 0, 0, 0);
		} 	


		if ( !IPS_VariableProfileExists('Geo.Latitude.5') ) {
			IPS_CreateVariableProfile('Geo.Latitude.5', VARIABLE::TYPE_FLOAT );
			IPS_SetVariableProfileDigits('Geo.Latitude.5', 5);
			IPS_SetVariableProfileText('Geo.Latitude.5', "", "" );
			IPS_SetVariableProfileValues('Geo.Latitude.5', -90, 90, 0);
		} 	

		if ( !IPS_VariableProfileExists('Geo.Longitude.5') ) {
			IPS_CreateVariableProfile('Geo.Longitude.5', VARIABLE::TYPE_FLOAT );
			IPS_SetVariableProfileDigits('Geo.Longitude.5', 5);
			IPS_SetVariableProfileText('Geo.Longitude.5', "", "" );
			IPS_SetVariableProfileValues('Geo.Longitude.5', -180, 180, 0);
		} 			

		if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, "Profiles registered"); }
	}

	protected function RegisterVariables() {
		
		$archivInstanzID = IPS_GetInstanceListByModuleID("{43192F0B-135B-4CE7-A0A7-1475603F3060}")[0];


		$varId = $this->RegisterVariableInteger("odometer", "Odometer", "EV.km", 100);

		$varId = $this->RegisterVariableInteger("level", "Batterie Ladezustand", "EV.level", 110);
		//AC_SetLoggingStatus($archivInstanzID, $varId, true);

		$varId = $this->RegisterVariableInteger("range", "Geschätzte Reichweite", "EV.km", 120);
		//AC_SetLoggingStatus($archivInstanzID, $varId, true);			

		$varId = $this->RegisterVariableInteger("chargingStatus", "Charging Status", "EV.ChargingStatus", 150);

		$varId = $this->RegisterVariableString("chargingStatusTxt", "Charging Status", "", 151);

		$varId = $this->RegisterVariableInteger("chargeRemainingTime", "Charge Remaining Time", "~UnixTimestampTime", 180);

		$varId = $this->RegisterVariableBoolean("plugged", "Plugged", "", 160);

		$varId = $this->RegisterVariableFloat("chargerPower", "ChargerPower", "", 170);

		$varId = $this->RegisterVariableFloat("latitude", "Latitude", "", 200);

		$varId = $this->RegisterVariableFloat("longitude", "Longitude", "", 210);

		$varId = $this->RegisterVariableString("coordinates", "Koordinaten", "", 212);

		$varId = $this->RegisterVariableInteger("timestamp", "Timestamp", "~UnixTimestamp", 300);

		$varId = $this->RegisterVariableInteger("lastUpdate", "LastUpdate", "~UnixTimestamp", 310);


		$varId = $this->RegisterVariableFloat("calcBattEnergyLeft", "[calc] verbleibende Batteriekapazität", "EV.kWh", 400);
		IPS_SetHidden($varId, true);

		$varId = $this->RegisterVariableFloat("calcConsumption", "[calc] Verbrauch", "EV.kWh_100km", 401);
		IPS_SetHidden($varId, true);	
		
		$varId = $this->RegisterVariableInteger("calcEstimatedRangeOnFullCharge", "[calc] Geschätzte Reichweite bei voller Ladung", "EV.km", 402);
		IPS_SetHidden($varId, true);				

		$varId = $this->RegisterVariableFloat("calcPercentOfWLTP", "[calc] Prozent von WLTP [424km]", "EV.Percent", 403);
		IPS_SetHidden($varId, true);		

		$varId = $this->RegisterVariableFloat("calcBattCharged", "[calc] Batterie geladen", "EV.kWh", 410);
		IPS_SetHidden($varId, true);

		$varId = $this->RegisterVariableFloat("calcBattDisCharged", "[calc] Batterie entladen", "EV.kWh", 411);
		IPS_SetHidden($varId, true);			


		$this->RegisterVariableInteger("updateCntOk", "Update Cnt", "", 900);
		$this->RegisterVariableFloat("updateCntSkip", "Update Cnt Skip", "", 910);	
		$this->RegisterVariableInteger("updateCntError", "Update Cnt Error", "", 920);
		$this->RegisterVariableString("updateLastError", "Update Last Error", "", 930);
		$this->RegisterVariableFloat("updateLastDuration", "Last API Request Duration [ms]", "", 940);	

		$varId = $this->RegisterVariableString("api_accessToken", "API access_token", "", 950);	
		IPS_SetHidden($varId, true);

		$varId = $this->RegisterVariableInteger("api_accessToken_expires", "API access_token expires", "~UnixTimestamp", 950);
		IPS_SetHidden($varId, true);

		IPS_ApplyChanges($archivInstanzID);
		if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, "Variables registered"); }

	}


	protected function AddLog($name, $daten, $format=0) {
		$this->logCnt++;
		$logSender = "[".__CLASS__."] - " . $name;
		if($this->logLevel >= LogLevel::DEBUG) {
			$logSender = sprintf("%02d-T%2d [%s] - %s", $this->logCnt, $_IPS['THREAD'], __CLASS__, $name);
		} 
		$this->SendDebug($logSender, $daten, $format); 	
	
		if($this->enableIPSLogOutput) {
			if($format == 0) {
				IPS_LogMessage($logSender, $daten);	
			} else {
				IPS_LogMessage($logSender, $this->String2Hex($daten));			
			}
		}
	}

}