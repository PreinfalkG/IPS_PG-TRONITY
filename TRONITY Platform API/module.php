<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/COMMON.php'; 

	class TRONITYPlatformAPI extends IPSModule
	{

		use TRONITY_COMMON;

		const API_URL_Authentication = "https://api-eu.TRONITY.io/oauth/authentication";
		const API_URL_Bulk = "https://api-eu.TRONITY.io/v1/vehicles/%%vehicleId%%/bulk";

		private $logLevel = 3;
		private $enableIPSLogOutput = false;
		private $parentRootId;
		private $archivInstanzID;

		private $apiClientId;
		private $apiClientSecret;
		private $apiGrantType;
		private $vehicleId;


		public function __construct($InstanceID) {
		
			parent::__construct($InstanceID);		// Diese Zeile nicht löschen
		
			if(IPS_InstanceExists($InstanceID)) {

				$this->parentRootId = IPS_GetParent($InstanceID);
				$this->archivInstanzID = IPS_GetInstanceListByModuleID("{43192F0B-135B-4CE7-A0A7-1475603F3060}")[0];

				$currentStatus = $this->GetStatus();
				if($currentStatus == 102) {				//Instanz ist aktiv
					$this->logLevel = $this->ReadPropertyInteger("LogLevel");
					$this->apiClientId = $this->ReadPropertyString("tbClientId");
					$this->apiClientSecret = $this->ReadPropertyString("tbClientSecret");		
					$this->apiGrantType = $this->ReadPropertyString("tbGrantType");		
					$this->vehicleId = $this->ReadPropertyString("tbVehicleId");	
	
					if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, sprintf("Log-Level is %d", $this->logLevel), 0); }
				} else {
					if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Current Status is '%s'", $currentStatus), 0); }	
				}

			} else {
				IPS_LogMessage("[" . __CLASS__ . "] - " . __FUNCTION__, sprintf("INFO: Instance '%s' not exists", $InstanceID));
			}
		}



		public function Create()
		{
			//Never delete this line!
			parent::Create();

			IPS_LogMessage("[" . __CLASS__ . "] - " . __FUNCTION__, sprintf("Create Modul '%s' ...", $this->InstanceID));
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Create Modul '%s [%s']...", IPS_GetName($this->InstanceID), $this->InstanceID), 0); }

			$this->RegisterPropertyBoolean('AutoUpdate', false);
			$this->RegisterPropertyInteger("TimerInterval", 240);		
			$this->RegisterPropertyInteger("LogLevel", 4);

			$this->RegisterPropertyString("tbClientId", "");
			$this->RegisterPropertyString("tbClientSecret", "");
			$this->RegisterPropertyString("tbGrantType", "app");
			$this->RegisterPropertyString("tbVehicleId", "");

			$this->RegisterTimer('Timer_AutoUpdate', 0, 'TPA_Timer_AutoUpdate($_IPS["TARGET"]);');


			$runlevel = IPS_GetKernelRunlevel();
			if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, sprintf("KernelRunlevel '%s'", $runlevel), 0); }	
			if ( $runlevel == KR_READY ) {
				//$this->RegisterHook(self::WEB_HOOK);
			} else {
				$this->RegisterMessage(0, IPS_KERNELMESSAGE);
			}


		}

		public function Destroy()
		{
			IPS_LogMessage("[" . __CLASS__ . "] - " . __FUNCTION__, sprintf("Destroy Modul '%s' ...", $this->InstanceID));
			$this->SetUpdateInterval(0);		//Stop Auto-Update Timer
			parent::Destroy();					//Never delete this line!
		}

		public function ApplyChanges()
		{
			parent::ApplyChanges();				//Never delete this line!

			$this->logLevel = $this->ReadPropertyInteger("LogLevel");
			if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Log-Level to %d", $this->logLevel), 0); }
			
			if (IPS_GetKernelRunlevel() != KR_READY) {
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("GetKernelRunlevel is '%s'", IPS_GetKernelRunlevel()), 0); }
				//return;
			}

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

			$logMsg = sprintf("TimeStamp: %s | SenderID: %s | Message: %s | Data: %s", $TimeStamp, $SenderID, $Message, print_r($Data,true));
			IPS_LogMessage("[" . __CLASS__ . "] - " . __FUNCTION__, $logMsg);
			if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, $logMsg, 0); }

			parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
			//if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) 	{
			//		$this->RegisterHook(self::WEB_HOOK);
			//}
		}

		
		   public function SetUpdateInterval(int $timerInterval) {
			if ($timerInterval == 0) {  
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, "Auto-Update stopped [TimerIntervall = 0]", 0); }	
			}else if ($timerInterval < 60) { 
				$timerInterval = 60; 
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Auto-Update Timer Intervall to %s sec", $timerInterval), 0); }	
			} else {
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, sprintf("Set Auto-Update Timer Intervall to %s sec", $timerInterval), 0); }
			}
			$this->SetTimerInterval("Timer_AutoUpdate", $timerInterval*1000);	
		}



		public function Timer_AutoUpdate() {

			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Timer_AutoUpdate called ...", 0); }
			$this->UpdateBulk("AutoUpdateTimer");
						
		}


		public function UpdateBulk(string $Text) {

			if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Update Bulk ...", 0); }

			$skipUdateSec = 600;
			$lastUpdate  = time() - round(IPS_GetVariable($this->GetIDForIdent("updateCntError"))["VariableUpdated"]);
			if ($lastUpdate > $skipUdateSec) {

				$currentStatus = $this->GetStatus();
				if($currentStatus == 102) {		
				
					$start_Time = microtime(true);

					try {

						//$a = 0;	$b = 0;	$c = $a / $b;  //Test Try-Catch
  						//throw new Exception('! Throw TEST Exception !');

						$api_accessToken = GetValue($this->GetIDForIdent("api_accessToken"));
						$api_accessToken_expires = GetValueInteger($this->GetIDForIdent("api_accessToken_expires"));
						$api_accessToken_expires = $api_accessToken_expires - 15;

						$now = time();
						if($api_accessToken == "") {
							if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("NEED Updae API 'accessToken' [empty '%s']", $api_accessToken), 0); }   
							$this->UpdateApiAccessToken();
						} else if($now >= $api_accessToken_expires) {
							if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("NEED Updae API 'accessToken' [expires @%s]", date('d.m.Y H:i:s',$api_accessToken_expires)), 0); }   
							$this->UpdateApiAccessToken();
						} else {
							if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("API 'accessToken' expires @%s", date('d.m.Y H:i:s',$api_accessToken_expires)), 0); }   
						}
		
						$apiUrl = self::API_URL_Bulk;
						$apiUrl = str_replace("%%vehicleId%%", $this->vehicleId, $apiUrl);
		
						if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("HTTP_REQUEST: %s", $apiUrl),0); }

						$options = array(
							'http' => array(
							'method'  => 'GET',
							'header'=>  "Authorization: Bearer ". $api_accessToken . "\r\n" .
										"Content-Type: application/json\r\n" .
										"Accept: application/json\r\n"
							)
						);
						
						$context  = stream_context_create( $options );
						$data = file_get_contents( $apiUrl, false, $context );

						//if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("HTTP_RESPONSE_HEADER: %s", print_r($http_response_header, true)),0); }
						if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("HTTP_RESPONSE_HEADER: %s", print_r($http_response_header[0], true)),0); }
						if($this->logLevel >= LogLevel::COMMUNICATION) { $this->AddLog(__FUNCTION__, sprintf("HTTP_RESPONSE_DATA: %s", print_r($data, true)),0); }

						if (strpos($http_response_header[0], "200")) { 

							if ($data === FALSE) {
								if($this->logLevel >= LogLevel::ERROR) { $this->AddLog(__FUNCTION__, sprintf("NO HTTP_RESPONSE_DATA: %s", print_r($data, true)),0); }
							} else {
								$jsonData = json_decode($data);
								$odometer = $jsonData->odometer;
								$range = $jsonData->range;
								$level = $jsonData->level;
								$charging = $jsonData->charging;
								$chargeRemainingTime = $jsonData->chargeRemainingTime;
								$latitude = $jsonData->latitude;
								$longitude = $jsonData->longitude;
								$timestamp = $jsonData->timestamp;
		
								SetValue($this->GetIDForIdent("level"), $level);
								SetValue($this->GetIDForIdent("range"), $range);
								SetValue($this->GetIDForIdent("chargingStatus"), $charging);
								SetValue($this->GetIDForIdent("timestamp"), round($timestamp/1000));

								SetValue($this->GetIDForIdent("updateCntOk"), GetValue($this->GetIDForIdent("updateCntOk")) + 1);  
								if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, "Update IPS Variables DONE",0); }
							}

						} else 	if (strpos($http_response_header[0], "401")) { 
							SetValue($this->GetIDForIdent("updateCntError"), GetValue($this->GetIDForIdent("updateCntError")) + 1);  
							$errorMsg = sprintf("HTTP_RESPONSE_HEADER Error '400': %s", print_r($http_response_header, true));
							SetValue($this->GetIDForIdent("updateLastError"), $errorMsg);
							if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, $errorMsg, 0); }
							SetValueInteger($this->GetIDForIdent("api_accessToken"), "");
							SetValueInteger($this->GetIDForIdent("api_accessToken_expires"), 0);

						} else {
							SetValue($this->GetIDForIdent("updateCntError"), GetValue($this->GetIDForIdent("updateCntError")) + 1);  
							$errorMsg = sprintf("HTTP_RESPONSE_HEADER != 200: %s", print_r($http_response_header, true));
							SetValue($this->GetIDForIdent("updateLastError"), $errorMsg);
							if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, $errorMsg, 0); }
						}
					} catch (Exception $e) {
						$errorMsg = $e->getMessage();
						SetValue($this->GetIDForIdent("updateCntError"), GetValue($this->GetIDForIdent("updateCntError")) + 1);  
						SetValue($this->GetIDForIdent("updateLastError"), $errorMsg);
						if($this->logLevel >= LogLevel::ERROR) { $this->AddLog(__FUNCTION__, sprintf("Exception occurred: %s", $errorMsg),0); }
					}

					$duration = $this->CalcDuration_ms($start_Time);
					SetValue($this->GetIDForIdent("updateLastDuration"), $duration); 

				} else {
					//SetValue($this->GetIDForIdent("instanzInactivCnt"), GetValue($this->GetIDForIdent("instanzInactivCnt")) + 1);
					if($this->logLevel >= LogLevel::WARN) { $this->AddLog(__FUNCTION__, sprintf("Instanz '%s - [%s]' not activ [Status=%s]", $this->InstanceID, IPS_GetName($this->InstanceID), $currentStatus), 0); }
				}
			} else {
				SetValue($this->GetIDForIdent("updateCntSkip"), GetValue($this->GetIDForIdent("updateCntSkip")) + 1);
				$logMsg =  sprintf("INFO :: Skip Update for %d sec for Instance '%s' [%s] >> last error %d seconds ago...", $skipUdateSec, $this->InstanceID, IPS_GetName($this->InstanceID),  $lastUpdate);
				if($this->logLevel >= LogLevel::INFO) { $this->AddLog(__FUNCTION__, $logMsg, 0); }
				IPS_LogMessage("[" . __CLASS__ . "] - " . __FUNCTION__, $logMsg);
			}
		}

		public function UpdateApiAccessToken() {

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
			  
			  $context  = stream_context_create( $options );
			  $result = file_get_contents( $apiUrl, false, $context );
			  $response = json_decode( $result );
			  
			  //$response->id;
			  //$response->token_type;
			  $api_access_token =  $response->access_token;
			  $api_accessToken_expires = time() + $response->expires_in;

			  SetValue($this->GetIDForIdent("api_accessToken"), $api_access_token);
			  SetValue($this->GetIDForIdent("api_accessToken_expires"), $api_accessToken_expires);

			  if($this->logLevel >= LogLevel::DEBUG) { $this->AddLog(__FUNCTION__, sprintf("NEW API api_accessToken expires @%s", date('d.m.Y H:i:s',$api_accessToken_expires)), 0); }   

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


			if ( !IPS_VariableProfileExists('TRONITY.level') ) {
				IPS_CreateVariableProfile('TRONITY.level', VARIABLE::TYPE_INTEGER );
				IPS_SetVariableProfileDigits('TRONITY.level', 0 );
				IPS_SetVariableProfileText('TRONITY.level', "", " %" );
				IPS_SetVariableProfileValues('TRONITY.level', 0, 100, 1);
			} 
			if ( !IPS_VariableProfileExists('TRONITY.km') ) {
				IPS_CreateVariableProfile('TRONITY.km', VARIABLE::TYPE_INTEGER );
				IPS_SetVariableProfileDigits('TRONITY.km', 0 );
				IPS_SetVariableProfileText('TRONITY.km', "", " km" );
				//IPS_SetVariableProfileValues('GEN24.Prozent', 0, 0, 0);
			} 			
			if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, "Profiles registered", 0); }
		}

		protected function RegisterVariables() {
			
			$varId = $this->RegisterVariableInteger("level", "Batterie Ladezustand", "TRONITY.level", 100);
			AC_SetLoggingStatus($this->archivInstanzID, $varId, true);

			$varId = $this->RegisterVariableInteger("range", "Geschätzte Reichweite", "TRONITY.km", 110);
			AC_SetLoggingStatus($this->archivInstanzID, $varId, true);			


			$varId = $this->RegisterVariableInteger("charging", "Charging", "", 150);
			IPS_SetHidden($varId, true);

			$varId = $this->RegisterVariableString("chargingStatus", "Charging Status", "", 151);
			IPS_SetHidden($varId, true);

			$varId = $this->RegisterVariableInteger("chargeRemainingTime", "Charge Remaining Time", "", 160);
			IPS_SetHidden($varId, true);

			$varId = $this->RegisterVariableInteger("odometer", "Odometer", "TRONITY.km", 200);
			IPS_SetHidden($varId, true);

			$varId = $this->RegisterVariableFloat("latitude", "Latitude", "", 210);
			IPS_SetHidden($varId, true);

			$varId = $this->RegisterVariableFloat("longitude", "Longitude", "", 220);
			IPS_SetHidden($varId, true);

			$varId = $this->RegisterVariableInteger("timestamp", "Timestamp", "~UnixTimestamp", 300);


			$this->RegisterVariableInteger("updateCntOk", "Update Cnt", "", 900);
			$this->RegisterVariableFloat("updateCntSkip", "Update Cnt Skip", "", 910);	
			$this->RegisterVariableInteger("updateCntError", "Update Cnt Error", "", 920);
			$this->RegisterVariableString("updateLastError", "Update Last Error", "", 930);
			$this->RegisterVariableFloat("updateLastDuration", "Last API Request Duration [ms]", "", 940);	

			$varId = $this->RegisterVariableString("api_accessToken", "API access_token", "", 950);	
			IPS_SetHidden($varId, true);

			$varId = $this->RegisterVariableInteger("api_accessToken_expires", "API access_token expires", "~UnixTimestamp", 950);
			IPS_SetHidden($varId, true);

			IPS_ApplyChanges($this->archivInstanzID);
			if($this->logLevel >= LogLevel::TRACE) { $this->AddLog(__FUNCTION__, "Variables registered", 0); }

		}


		protected function AddLog($name, $daten, $format) {
			$this->SendDebug("[" . __CLASS__ . "] - " . $name, $daten, $format); 	
	
			if($this->enableIPSLogOutput) {
				if($format == 0) {
					IPS_LogMessage("[" . __CLASS__ . "] - " . $name, $daten);	
				} else {
					IPS_LogMessage("[" . __CLASS__ . "] - " . $name, $this->String2Hex($daten));			
				}
			}
		}


	}