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

class LoitrLoginHelpers {

    /**
     * Display errors to users
     */
	static function oopsie($message, $errno = E_USER_ERROR) {
		$errorStr = "<div style='font-family:tahoma;width:80%;margin:100px auto;border:1px solid #7f1c1c;color:#333333;'><div style='padding:10px;font-size:16px;font-weight:bold;background:#f2d1d1;border-bottom:1px solid #7f1c1c;'>{$message['title']}</div><div style='font-size:14px;font-weight:normal;padding:10px;background:#FFFFFF;'>{$message['body']}</div><div style='padding:20px;font-size:11px;font-family:courier;background:#EEEEEE;'>{$message['additional']}</div></div>";
		if(isset($_GET['action']) && $_GET['action'] == 'error_scrape') {
			echo $errorStr;
			exit;
		} else {
			trigger_error($errorStr, $errno);
		}
	}

    /**
     * Check if everything is done perfectly
     */
	static function checkSetup() {
		global $loitrConfig;

		if ( !file_exists($loitrConfig['keyFile']) || !fopen($loitrConfig['keyFile'], "r") )
			return -1;

		if ( trim($loitrConfig['serviceid']) == ''  || $loitrConfig['serviceid'] === false )
			return -2;

		if ( $loitrConfig['dbConnection']->query("select count(*) from {$loitrConfig['tables']['tokens']['aliasedto']}") === false ||
			$loitrConfig['dbConnection']->query("select count(*) from {$loitrConfig['tables']['mappings']['aliasedto']}") === false )
			return -3;

		# Wait what? Everything's good? Lets do it then.
		return 1;
	}

	static function fromMobile() {
		if(preg_match('/\b(iphone|ipod|android)\b/i', $_SERVER['HTTP_USER_AGENT'], $matches) > 0)
			return true;
		else
			return false;
	}
}
?>