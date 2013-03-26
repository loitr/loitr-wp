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

error_reporting(0);

function activatePlugin() {
	global $loitrConfig;

	$sqlMappingsTbl = "CREATE TABLE IF NOT EXISTS {$loitrConfig['tables']['mappings']['aliasedto']} ({$loitrConfig['tables']['mappings']['columns']['deviceid']} varchar(250) NOT NULL, {$loitrConfig['tables']['mappings']['columns']['vector']} text NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
	$sqlTokensTbl = "CREATE TABLE IF NOT EXISTS {$loitrConfig['tables']['tokens']['aliasedto']} ({$loitrConfig['tables']['tokens']['columns']['userid']} varchar(250) NOT NULL, {$loitrConfig['tables']['tokens']['columns']['token']} varchar(50) NOT NULL, {$loitrConfig['tables']['tokens']['columns']['tokentype']} varchar(20) NOT NULL, {$loitrConfig['tables']['tokens']['columns']['expireson']} bigint(20) NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
	if(!$loitrConfig['dbConnection']->query($sqlMappingsTbl)) LoitrLoginHelpers::oopsie(array('title' => "Loitr was unable to create the 'Mappings' table.", 'body' => 'Which means either the database is not setup correctly or Wordpress does not have the proper access yet. The query is listed below if you want to run it yourself', 'additional' => $sqlMappingsTbl));
	if(!$loitrConfig['dbConnection']->query($sqlTokensTbl)) LoitrLoginHelpers::oopsie(array('title' => "Loitr was unable to create the 'Tokens' table.", 'body' => 'Which means either the database is not setup correctly or Wordpress does not have the proper access yet. The query is listed below if you want to run it yourself', 'additional' => $sqlTokensTbl));

	try {
		$c = new CryptX();
		$c->generateKeyPair($loitrConfig['keyFile']);
		//$modexp = $c->exportKeyModExp();
	} catch(Exception $e) {
		LoitrLoginHelpers::oopsie($e);
	}
}
?>