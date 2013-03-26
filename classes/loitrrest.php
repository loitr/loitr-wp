<?php
/*
Copyright 2013 Avatar Software Pvt. Ltd.

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

class loitrREST {
	private $installationStatus;
	private $mobileAccess;

	function __construct($instStatus, $isMobile) {

		$this->mobileAccess = $isMobile;

		if($instStatus < 0) $this->installationStatus = false;
		else $this->installationStatus = true;

		if($this->installationStatus) {
			add_action( 'wp_ajax_nopriv_loitrlogin', array( &$this, 'processCalls' ) );
			add_action( 'wp_ajax_loitrlogin', array( &$this, 'processCalls' ) );
		}
	}

	function processCalls() {
		global $loitrConfig;

		$response = array(
							'status' => 0
		);

		if(isset($_REQUEST['os']))
			$this->processDeviceCalls();

		$op = sanitize($_REQUEST['op']);

		$crypt = new CryptX();
		$crypt->loadKeyFile($loitrConfig['keyFile']);
		$adminEmail = get_option('admin_email');

		switch($op) {
		 	case 'checktoken':
		 		$token = sanitize($_REQUEST['token']);
		 		$tokenDetails = $loitrConfig['dbConnection']->get_row("select * from {$loitrConfig['tables']['tokens']['aliasedto']} where {$loitrConfig['tables']['tokens']['columns']['userid']} <> '' and {$loitrConfig['tables']['tokens']['columns']['token']}='$token'", ARRAY_A);

				if(isset($tokenDetails['userid']) && strlen($tokenDetails['userid']) > 0) {
					$tokenDetails['userid'] = $crypt->decrypt($tokenDetails['userid']);
					$useremail = get_the_author_meta( 'user_email', $tokenDetails['userid'] );
					setSessionValue('thisuseremail', $useremail);
					add_filter( 'authenticate', array(&$this, 'loitrAuth'), 10, 3 );
					$loitrConfig['dbConnection']->query("delete from {$loitrConfig['tables']['tokens']['aliasedto']} where {$loitrConfig['tables']['tokens']['columns']['token']}='$token'");
					$response['status'] = 1;
				}
				$response['session'] = print_r($_SESSION, true);
			break;
			case 'signthis':
				$challenge = sanitize($_REQUEST['challenge']);
				$sign = $crypt->sign($challenge.$adminEmail);
				$crypt->importKeyFromModExp($loitrConfig['loitrPublicKey']['modulus'], $loitrConfig['loitrPublicKey']['exponent']);
				if(!checkRequestSign($crypt, $_REQUEST)) sendErrorResponse($response, 'rejected', $crypt);
				$response['sign'] = $sign;
				$response['admin'] = $adminEmail;
				$response['status'] = 1;
			break;
		}

		sendJSON($response);
		exit();
	}

	function processDeviceCalls() {
		global $loitrConfig;

		$response = array(
							'status' => -1,
							'data' => '',
							'errorcode' => 'illegalrequest'
		);

		$dbc = $loitrConfig['dbConnection'];
		$tables = $loitrConfig['tables'];
		$crypt = new CryptX();
		$crypt->loadKeyFile($loitrConfig['keyFile']);

		$op = getRequestKey('op', true);
		if(!isset($_REQUEST['os']))
			sendErrorResponse($response, 'parametersmissing', $crypt);
		else
			$response['os'] = $_REQUEST['os'];

		if(isset($_REQUEST['nonce']))
			$response['nonce'] = $_REQUEST['nonce'];

		switch($op) {
			case 'activate':
				$qrcode = getRequestKey('qr', true);
				$deviceid = getRequestKey('deviceid', true);
				$token = getRequestKey('token', true);

				if(!$qrcode || !$deviceid || !$token) sendErrorResponse($response, 'incompleteform', $crypt);
				else {
					$mappingForThisUser = $dbc->get_results("select * from {$tables['mappings']['aliasedto']} where {$tables['mappings']['columns']['deviceid']}='$deviceid'", ARRAY_A);
					if(count($mappingForThisUser) > 0) {
						$response['status'] = 0;
						$response['errorcode'] = 'deviceexists';
					} else {
						$loitrResponse = contactLoitr(array(
															'op' => 'verifytoken',
															'token' => $token,
															'deviceid' => $deviceid,
															'qr' => $qrcode
												));

						if($loitrResponse['status'] != -1 && strtolower(trim($loitrResponse['token'])) == strtolower(trim($token))) {
							$crypt->importKeyFromModExp($loitrConfig['loitrPublicKey']['modulus'], $loitrConfig['loitrPublicKey']['exponent']);
							if(!checkRequestSign($crypt, $loitrResponse)) sendErrorResponse($response, 'loitrunavailable', $crypt);

							$activeActivateTokens = $dbc->get_results("select * from {$tables['tokens']['aliasedto']} where token='{$loitrResponse['sessionid']}' and tokentype='ACTIVATE' and expireson > '".time()."'", ARRAY_A);
							if(count($activeActivateTokens) >= 1) {
		 						$sessionInfo = $activeActivateTokens[0];

							 	$crypt->loadKeyFile($loitrConfig['keyFile']);
								$userID = $crypt->decrypt($sessionInfo['userid']);
								$loitrResponse = contactLoitr(array(
																'op' => 'activate',
																'qr' => $qrcode,
																'token' => $token,
																'deviceid' => $deviceid,
																'userid' => $userID,
																'userdata' => '',
																'os' => $_REQUEST['os']
													)
											);
								if($loitrResponse['status'] != -1) {
									$loitrResponse['vectors'] = json_decode(pack("H*", $loitrResponse['vectors']), true);
			
									$newMapping = array(
														$tables['mappings']['columns']['deviceid'] => $loitrResponse['userid'],
														$tables['mappings']['columns']['vector'] => addslashes($loitrResponse['vectors'][0]['vector'])
									);
									$dbc->insert($tables['mappings']['aliasedto'], $newMapping);

									$response['userdata'] = $loitrResponse['userdata'];
									$response['uservector'] = bin2hex(json_encode($loitrResponse['vectors'][1]));
									$response['status'] = 1;
									$response['errorcode'] = 'activatesuccess';
								}
							} else {
								if($loitrResponse['errorcode'] == 'tokenexpired') sendErrorResponse($response, 'tokenexpired', $crypt);
							}
						} else sendErrorResponse($response, $loitrResponse['errorcode'], $crypt);
					}
				}
			break;
			case 'login':
				$qrcode = getRequestKey('qr', true);
				$deviceid = getRequestKey('deviceid', true);
				$token = getRequestKey('token', true);
				$vector = getRequestKey('vector', true);

				if(!$qrcode || !$deviceid || !$token || !$vector) sendErrorResponse($response, 'incompleteform', $crypt);
				else {
					$loitrResponse = contactLoitr(array(
														'op' => 'verifytoken',
														'token' => $token,
														'deviceid' => $deviceid,
														'qr' => $qrcode
											)
									);
					if($loitrResponse['status'] != -1 && strtolower(trim($loitrResponse['token'])) == strtolower(trim($token))) {
						$crypt->importKeyFromModExp($loitrConfig['loitrPublicKey']['modulus'], $loitrConfig['loitrPublicKey']['exponent']);
						if(!checkRequestSign($crypt, $loitrResponse)) sendErrorResponse($response, 'loitrunavailable', $crypt);
		
						$tokenForThisSession = $dbc->get_results("select * from {$tables['tokens']['aliasedto']} where token='{$loitrResponse['sessionid']}' and tokentype='LOGIN' and expireson > '".time()."'", ARRAY_A);
						if(count($tokenForThisSession) == 1) {
							list($loitruserid, $loitrdeviceid) = explode('-', $deviceid);
							$mappingForThisUser = $dbc->get_results("select * from {$tables['mappings']['aliasedto']} where {$tables['mappings']['columns']['deviceid']}='$loitruserid'", ARRAY_A);
							if(count($mappingForThisUser) == 1) {
								$vectorAssoc = $mappingForThisUser[0];
								$pairingData = json_decode(Shamir::recover(array($vectorAssoc['vector'], $vector)), true);

								if($crypt->verifySign($pairingData['data'], $pairingData['sign'])) {
									$pairingData = json_decode($pairingData['data'], true);
									if($pairingData['deviceid'] == $loitruserid) {
									 	$crypt->loadKeyFile($loitrConfig['keyFile']);
										$pairingData['userid'] = $crypt->encrypt($pairingData['userid']);
										$dbc->query("update {$tables['tokens']['aliasedto']} set userid='{$pairingData['userid']}' where token='{$loitrResponse['sessionid']}' and tokentype='LOGIN' and expireson > '".time()."'");
									}

									$response['status'] = 1;
									$response['errorcode'] = 'loginsuccess';
								} else $response['errorcode'] = 'loginfailed';
							} else $response['errorcode'] = 'loginfailed';
						} else $response['errorcode'] = 'qrexpired';
					} else if($loitrResponse['errorcode'] == 'tokenexpired') sendErrorResponse($response, 'qrexpired', $crypt);
				}
			break;
			case 'deactivate':
				$deviceid = getRequestKey('deviceid', true);
				$token = getRequestKey('token', true);
				if(!$deviceid || !$token) sendErrorResponse($response, 'incompleteform', $crypt);
				else {
					$loitrResponse = contactLoitr(array(
														'op' => 'verifytoken',
														'token' => $token,
														'deviceid' => $deviceid
											));

					if($loitrResponse['status'] != -1 && strtolower(trim($loitrResponse['token'])) == strtolower(trim($token))) {
						$crypt->importKeyFromModExp($loitrConfig['loitrPublicKey']['modulus'], $loitrConfig['loitrPublicKey']['exponent']);
						if(!checkRequestSign($crypt, $loitrResponse)) sendErrorResponse($response, 'loitrunavailable', $crypt);

						list($loitruserid, $loitrdeviceid) = explode('-', $deviceid);
						$anyActivatedDevice = $dbc->get_results("select * from {$tables['mappings']['aliasedto']} where {$tables['mappings']['columns']['deviceid']}='$loitruserid'", ARRAY_A);
						if(count($anyActivatedDevice) > 0) {
							$loitrResponse = contactLoitr(array(
															'op' => 'deactivate',
															'token' => $token,
															'deviceid' => $deviceid,
															'os' => $_REQUEST['os']
												));
							if($loitrResponse['status'] > 0)
								$dbc->query("delete from {$tables['mappings']['aliasedto']} where {$tables['mappings']['columns']['deviceid']}='$loitruserid'");

							$response['status'] = 1;
							$response['errorcode'] = 'deactivatesuccess';
						} else $response['errorcode'] = 'deactivatefailed';
					} else if($loitrResponse['errorcode'] == 'tokenexpired') sendErrorResponse($response, 'qrexpired', $crypt);
				}
			break;
		}
		sendJSON($response, $crypt);
		exit();
	}
}
?>