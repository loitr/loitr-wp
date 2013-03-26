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

function sanitize($inputstr) {
	$inputstr = urldecode($inputstr);
    $inputstr = trim(preg_replace('#<script.*?</script>#', ' ', $inputstr));
    $inputstr = preg_replace('#<style.*?</style>#', ' ', $inputstr);
    $inputstr = preg_replace('#<[^>]*?>#', ' ', $inputstr);
    $inputstr = strip_tags(trim($inputstr));
    $inputstr = htmlentities($inputstr, ENT_QUOTES, "UTF-8");
	if(get_magic_quotes_gpc())  // prevents duplicate backslashes
		$inputstr = stripslashes($inputstr);
	return $inputstr;
}

function setSessionValue($key, $value) {
	global $loitrConfig;

	$_SESSION[$loitrConfig['session_tag'].$key] = $value;
}

function getSessionValue($key) {
	global $loitrConfig;

	return isset($_SESSION[$loitrConfig['session_tag'].$key]) ? $_SESSION[$loitrConfig['session_tag'].$key] : false;
}

function unsetSessionValue($key) {
	global $loitrConfig;

	unset($_SESSION[$loitrConfig['session_tag'].$key]);
}

function unsetSessionValues() {
	global $loitrConfig;

	foreach($_SESSION as $k=>$v) {
		if( substr($k, 0, strlen($loitrConfig['session_tag'])) == $loitrConfig['session_tag'] )
			unset($_SESSION[$k]);
	}
}


function getRequestKey($str, $mandatory = false, $defaultValue = '') {
	if($mandatory) return isset($_REQUEST[$str]) ? sanitize($_REQUEST[$str]) : false;
	else return isset($_REQUEST[$str]) ? sanitize($_REQUEST[$str]) : $defaultValue;
}

function showLoitrLoginBox($qrtoken, $qrtype, $qrexpiresin, $numUses, $pxsize, $errlvl, $userid='') {

	$qrtype = strtoupper($qrtype);

	$url = getQRURL($qrtoken, $qrtype, $qrexpiresin, $numUses, $pxsize, $errlvl, '000000', 'FFFFFF', $userid);
	if($url) {
		$urlcode = "<img style='border:1px solid #000000;' src='$url'>";
	}
	switch($qrtype) {
		case 'LOGIN':
			$rootdivid = 'loitr_login';
			$addBtnTxt = 'Login with Loitr';
		break;
		case 'ACTIVATE':
			$rootdivid = 'loitr_token';
			$addBtnTxt = 'Add Loitr';
		break;
		case 'SIGNUP':
			$rootdivid = 'loitr_signup';
			$addBtnTxt = 'Signup with Loitr';
		break;
	}
	return <<<HTML
<a onclick="loitr.toggle('{$rootdivid}qr');" id='{$rootdivid}' style='padding:5px 7px;color:#12336b;background:#FFFFFF;font-size:14px;font-weight:bold;'>{$addBtnTxt}</a>
<div id='{$rootdivid}qr' style="display:none;position:absolute;top:;left:;padding-left:12px;background:url('qrbg.png') left center no-repeat transparent;">
	$urlcode
</div>
<script>
		var loitr = {
			positionQRBox : function() {
				document.getElementById('{$rootdivid}qr').style.top = document.getElementById('{$rootdivid}').offsetTop + document.getElementById('{$rootdivid}').offsetHeight/2 - document.getElementById('{$rootdivid}qr').offsetHeight/2 + 'px';
				document.getElementById('{$rootdivid}qr').style.left = document.getElementById('{$rootdivid}').offsetLeft + document.getElementById('{$rootdivid}').offsetWidth + 'px';
			},

			toggle : function(id) {
				var el = document.getElementById(id);
				if(el.style.display == 'none') el.style.display = '';
				else el.style.display = 'none';
				this.positionQRBox();
			}
		};
		window.onload = loitr.positionQRBox();
		window.onresize = loitr.positionQRBox();
</script>
HTML;
}

function getUserForToken($token) {
	global $loitrConfig;
	$tables = $loitrConfig['tables'];
	$dbc = $loitrConfig['dbConnection'];
	purgeExpired();

	$userID = $dbc->get_var("select {$tables['tokens']['columns']['userid']} from {$tables['tokens']['aliasedto']} where {$tables['tokens']['columns']['token']}='$token' and {$tables['tokens']['columns']['userid']} <> 0 and {$tables['tokens']['columns']['expireson']} > '".time()."';");
	if($userID != 0) {
		$dbc->query("delete from {$tables['tokens']['aliasedto']} where {$tables['tokens']['columns']['token']}='{$_SESSION['token']}'");
		$userInfo = $dbc->get_results("select * from {$tables['users']['aliasedto']} where {$tables['users']['columns']['userid']}='$userID'", ARRAY_A);
		return $userInfo[0];
	} else return false;
}

function purgeExpired() {
	global $loitrConfig;
	$tables = $loitrConfig['tables'];
	$dbc = $loitrConfig['dbConnection'];
	$dbc->query("delete from {$tables['tokens']['aliasedto']} where {$tables['tokens']['columns']['expireson']} <= '".time()."';");
}

function sendJSON($d, $cryptObject = false) {
 	if($cryptObject)
		$d = addRequestSign($d, $cryptObject);
	$outputStr = json_encode($d);

	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");
	echo $outputStr;
}

function sendErrorResponse($responseArray, $errorcode, $cryptObject, $status = -1, $exit = 1) {
	$responseArray['errorcode'] = $errorcode;
	$responseArray['status'] = $status;
	sendJSON($responseArray, $cryptObject);
	if($exit == 1) exit();
}

function checkRequestSign($cryptObject, $optParams = array()) {
	$params = array();
	if(count($optParams) == 0) $optParams = $_REQUEST;
	foreach($optParams as $k=>$v) {
		$params[$k] = $v;
	}
	if(isset($params['sign'])) {
		$sign = $params['sign'];
		unset($params['sign']);
	} else return false;
	ksort($params);
	$str = '';
	foreach($params as $k=>$v) $str .= rawurlencode($k).'='.rawurlencode($v).'&';
	$str = trim($str, "&");
	return isset($params['os']) && strtolower($params['os']) == 'android' ? $cryptObject->verifySign2($str, $sign) : $cryptObject->verifySign($str, $sign);
}

function addRequestSign($params, $cryptObject) {
	$str = trim(signStr($params), "&");
	$params['sign'] = isset($params['os']) && strtolower($params['os']) == 'android' ? $cryptObject->sign2($str) : $cryptObject->sign($str);
	return $params;
}

function signStr($inputDict) {
	$str = '';

	ksort($inputDict);
	foreach($inputDict as $key=>$value) {
		if(is_array($value)) $str .= signStr($value);
		else $str .= rawurlencode($key).'='.rawurlencode($value).'&';
	}
	return $str;
}

function xpack($format, $val) {
	if(preg_match_all("/[h]/", strtolower($format), $out)) {
		if(preg_match_all("/[^0-9a-fA-F]/", $val, $out) > 0) {
			$val = preg_replace("/[^0-9a-fA-F]/i", '', $val);
		}
	}
	return pack($format, $val);
}

function getQRURL($sessionID, $type, $expiresIn, $numUses, $size, $errCoLvl, $dotColor, $bgColor, $userid = '') {
	global $loitrConfig;

	$params = array(
					'op' => 'getimgurl',
					'sessionID' => $sessionID,
					'type' => $type,
					'expiresIn' => $expiresIn,
					'numUses' => $numUses,
					'size' => $size,
					'errCoLvl' => $errCoLvl,
					'dotColor' => $dotColor,
					'bgColor' => $bgColor
	);

	$response = contactLoitr($params);

	if($userid != '') {
		$crypt = new CryptX();
		$crypt->loadKeyFile($loitrConfig['keyFile']);
		$userid = $crypt->encrypt($userid);
	}

	if($response['status'] != -1 && $response['errorcode'] == 'success') {
		$tables = $loitrConfig['tables'];
		$dbc = $loitrConfig['dbConnection'];
		$newToken = array(
							$tables['tokens']['columns']['userid'] => $userid,
							$tables['tokens']['columns']['token'] => $sessionID,
							$tables['tokens']['columns']['tokentype'] => $type,
							$tables['tokens']['columns']['expireson'] => $expiresIn + time()
		);
		$dbc->insert($tables['tokens']['aliasedto'], $newToken);
		return $response['data'];
	}
	else return false;
}

function contactLoitr($params) {
	global $loitrConfig;
	$crypt = new CryptX();
	$params['serviceID'] = $loitrConfig['serviceid'];
	$crypt->loadKeyFile($loitrConfig['keyFile']);
	$params = addRequestSign($params, $crypt);
	$url = $loitrConfig['loitrAPI'].'?';
	foreach($params as $k=>$v) $url .= $k.'='.$v.'&';
	$url = trim($url, "&");
	$response = wp_remote_get($url);
	if($response['response']['code'] == '200' && json_decode($response['body'], true)) return json_decode($response['body'], true);
	else return false;
}

?>