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

global $loitrConfig;
global $wpdb;
$loitrConfig = array(
				'version' => '1.0',

				# ADD YOUR SERVICE IDENTIFIER HERE, eg., 'serviceid' => '3987',
				# This ID will be provided to you by contact@appavatar.com after verifying that you own this blog.
				'serviceid' => '',

				# The REST endpoint where this plugin connects to contact with Loitr.
				# This is a constant. Don't change.
				'loitrAPI' => 'https://loitr.in/api2.0/service/', //Constant

				# Path to the Key File. The key file is generated automatically on activating the plugin.
				# If you move it to a different folder for security reasons, make sure you set permissions and update the path here.
				'keyFile' => dirname(__FILE__).'/loitrPrivate.key',

				# These are aliases into the tables. Don't change them unless you change the table names.
				'tables' => array( 
								'tokens' => array(
												'aliasedto' => $wpdb->prefix.'loitr_tokens',
												'columns' => array(
																'userid' => 'userid',
																'token' => 'token',
																'tokentype' => 'tokentype',
																'expireson' => 'expireson'
												)
								),
								'mappings' => array(
												'aliasedto' => $wpdb->prefix.'loitr_mappings',
												'columns' => array(
																'vector' => 'vector',
																'deviceid' => 'deviceid'
												)
								)
				),

				# QR Settings. Controls the appearance of the QRs. Default settings work fine.
				# If you have to change the colors, make sure the dark and light spots have considerable contrast to help in scanning.
				'qrsettings' => array(
									'dotcolor' => '21759B',
									'backgroundcolor' => 'FFFFFF',
									'dimension' => 180, # in pixels
									'loginqrtimeout' => 120, # The Login QR becomes defunct after 120 seconds of issuance
									'activateqrtimeout' => 900 # The Activate QR becomes defunct after 900 seconds of issuance
				),

				# The database object.
				'dbConnection' => $wpdb,

				# Loitr's Public Key to verify signatures of all communication with Loitr.
				# This is a constant. Don't change.
				'loitrPublicKey' => array(
											'modulus' => '8f0d549e031fb09048bb3f135a5204f9010c53769482adbda8e9012bc032ea7b89046ec8366ead13077bdfe5605221847e75463d86cd5a582e0cd6d500719c51',
											'exponent' => '010001'
				),

				'abspath' => dirname(__FILE__).'/',
				'session_tag' => 'loitr_'.get_bloginfo( 'name' ),
				'url' => array(
								'blog' => site_url().'/',
								'imagesdir' => site_url().substr(dirname(__FILE__), strpos(dirname(__FILE__), '/wp-content/plugins')).'/images/'
				),
				'path' => array(
								'plugindir' => dirname(__FILE__).'/',
								'imagesdir' => dirname(__FILE__).'/images/',
								'classesdir' => dirname(__FILE__).'/classes/'
				)
);
ini_set('include_path', $loitrConfig['path']['classesdir']);
?>
