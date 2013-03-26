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

include_once('config.php');
include_once('classes/cryptx.php');
include_once('functions.php');
include_once('SSS.php');

$dbc = $loitrConfig['dbConnection'];
$tables = $loitrConfig['tables'];

$crypt = new CryptX();
$crypt->loadKeyFile($loitrConfig['keyFile']);

$op = getRequestKey('op', true);

$response = array(
					'status' => -1,
					'data' => '',
					'errorcode' => 'illegalrequest'
);

if(!isset($_REQUEST['os']))
	sendErrorResponse($response, 'parametersmissing', $crypt);
else
	$response['os'] = $_REQUEST['os'];

if(isset($_REQUEST['nonce']))
	$response['nonce'] = $_REQUEST['nonce'];

switch($op) {
	case 'healthcheck':
		$response['status'] = 1;
		$response['errorcode'] = 'checkdone';
		foreach($_REQUEST as $k=>$v)
			$response['hc_'.$k] = $v;
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
						$vectorAssoc = $$mappingForThisUser[0];
						$pairingData = json_decode(Shamir::recover(array($vectorAssoc['vector'], $vector)), true);

						if($crypt->verifySign($pairingData['data'], $pairingData['sign'])) {
							$pairingData = json_decode($pairingData['data'], true);

							if($pairingData['deviceid'] == $loitruserid)
								$dbc->query("update {$tables['tokens']['aliasedto']} set userid='{$pairingData['userid']}' where token='{$loitrResponse['sessionid']}' and tokentype='LOGIN' and expireson > '".time()."'");

							$response['status'] = 1;
							$response['errorcode'] = 'loginsuccess';
						} else $response['errorcode'] = 'loginfailed';
					} else $response['errorcode'] = 'loginfailed';
				} else $response['errorcode'] = 'qrexpired';
			} else if($loitrResponse['errorcode'] == 'tokenexpired') sendErrorResponse($response, 'qrexpired', $crypt);
		}
	break;
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

						$userID = $sessionInfo['userid'];
						$userInfo = $dbc->get_row("select * from {$tables['users']['aliasedto']} where {$tables['users']['columns']['userid']}='$userID'", ARRAY_A);
						$loitrResponse = contactLoitr(array(
														'op' => 'activate',
														'qr' => $qrcode,
														'token' => $token,
														'deviceid' => $deviceid,
														'userid' => $userID,
														'userdata' => bin2hex(json_encode(array('name' => $userInfo['fname']))),
														'os' => $_REQUEST['os']
											)
									);
						if($loitrResponse['status'] != -1) {
							$loitrResponse['vectors'] = json_decode(pack("H*", $loitrResponse['vectors']), true);
	
							$newMapping = array(
												$tables['mappings']['columns']['deviceid'] => $loitrResponse['userid'],
												$tables['mappings']['columns']['vector'] => addslashes($loitrResponse['vectors'][0]['vector'])
							);
							$dbc->insertRow($tables['mappings']['aliasedto'], $newMapping);
	
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
?>