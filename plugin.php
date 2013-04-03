<?php
/*
Plugin Name: Loitr Login
Plugin URI: http://loitr.in/
Description: Loitr would enable users of your blog to sign in without having to type their login credentials. Loitr doesn't need to know any of your users' login credentials or passwords.
Version: 1.0
Author: Appavatar
Author URI: http://appavatar.com/
Author Email: contact@loitr.in
License: Apache 2.0
 
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

include_once dirname( __FILE__ ).'/config.php';
include_once dirname( __FILE__ ).'/classes/cryptx.php';
include_once dirname( __FILE__ ).'/classes/shamir.php';
include_once dirname( __FILE__ ).'/classes/loitrlogin.php';
include_once dirname( __FILE__ ).'/classes/loitrloginhelpers.php';
include_once dirname( __FILE__ ).'/classes/loitrloginsettings.php';
include_once dirname( __FILE__ ).'/classes/loitrdashboardwidget.php';
include_once dirname( __FILE__ ).'/classes/loitrrest.php';
include_once dirname( __FILE__ ).'/functions.php';
include_once dirname( __FILE__ ).'/activateplugin.php';

# To run activation methods everytime this plugin is activated from the Dashboard
register_activation_hook( __FILE__, 'activatePlugin' );

$installationStatus = LoitrLoginHelpers::checkSetup();
$mobileAccess = LoitrLoginHelpers::fromMobile();
new LoitrDashboardWidget($installationStatus, $mobileAccess);	# The place in the Dashboard where messages & Activate QR is displayed.
new LoitrLoginSettings($installationStatus, $mobileAccess);		# The menu in the Settings section and the content for the pane it opens
new LoitrLogin($installationStatus, $mobileAccess);				# The actual module with the authentication functions
new LoitrREST($installationStatus, $mobileAccess);				# For AJAX
?>