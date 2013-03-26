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

class LoitrDashboardWidget {
	private $installationStatus;
	private $mobileAccess;

	function __construct($instStatus, $isMobile) {

		$this->installationStatus = $instStatus;
		$this->mobileAccess = $isMobile;

		// Hint: For Multisite Network Admin Dashboard use wp_network_dashboard_setup instead of wp_dashboard_setup.
		add_action('wp_dashboard_setup', array(&$this, 'widgetHook') );
	}

	// Create the function to output the contents of our Dashboard Widget
	function widgetHTML() {
		global $user_level;
		global $current_user;
		global $loitrConfig;

		$adminEmail = get_option('admin_email');
		$blogName = get_bloginfo('name');
		$widgetHTML = '';
		$activateQRURL = site_url().'/wp-content/plugins/loitr/images/pendingconf.png';

		if($this->installationStatus < 0) {
			if($user_level < 8) {
				$widgetHTML = "<strong>$blogName</strong> is about to get Loitr.<br /><br />
					You will be able to login to $blogName with your phone instead of usernames and passwords. And Loitr is only more secure.<br />
					See a video of how that can happen <a href='http://www.youtube.com'>here</a>.<br />
					<br />
					Keep watching this space for your Loitr Activation QR.<br />";
			} else {
				switch($this->installationStatus) {
					case -1 :
						$widgetHTML = "<strong>Oops! The Private Key file for Loitr is not readable.</strong> <br/>
						The plugin expects a key file at <i>{$loitrConfig['keyFile']}</i>.<br />
						Either the activation process failed to create it automatically because of lack of write permissions or the file has been moved.<br />
						<br />
						You can either:<br>
						<ol>
							<li>
								Grant write permissions to the <strong>Loitr Login</strong> plugin folder at wp-content/plugins/loitr and <strong>Deactivate</strong> & <strong>Activate</strong> the plugin again to generate the key files.
							</li>
							<li>
								Or check to see if the file exists but read permissions have been revoked on the folder, or maybe the key file has been moved.
							</li>
						</ol>
						Check out the full installation <a target='_blank' href='http://youtu.be/0_1sa6soy5U'>demo video here</a>.";
					break;
					case -2 :
						$widgetHTML = "<strong>We couldn't locate the service identifier for your blog.</strong>
						<ol>
							<li>
								Visit the Loitr Settings section in Settings and copy the Public Key & Endpoint path as displayed there and send it to contact@appavatar.com requesting your service identifier.
							</li>
							<li>
								If you have sent it already and haven't yet received your identifier, we are working to verify that you are the rightful owner of your blog. This process takes not more than 2-3 days.
							</li>
							<li>
								If you have received your service identifier, then locate the config.php file in the Loitr plugin folder(typically residing at wp-content/plugins/loitr) and add your service identifier in the <strong>loitrConfig</strong> variable as explained in the config file.
							</li>
						</ol>
						Check out the full installation <a target='_blank' href='http://youtu.be/0_1sa6soy5U'>demo video here</a>.";
					break;
					case -3 :
						$widgetHTML = "<strong>The required database tables were not found.</strong>
						<br /><br />
						Loitr expects 2 tables, namely <strong>{$loitrConfig['tables']['mappings']['aliasedto']}</strong> & <strong>{$loitrConfig['tables']['tokens']['aliasedto']}</strong> in your database. This has either happened because the activation process didn't have database access rights or maybe the tables got deleted after creation.<br/>
						You can try Deactivate-ing & Activate-ing the plugin again to check to see if the tables get created again.<br /><br />
						Please note that if the tables did existed and users were using Loitr on your blog, they are unable to use Loitr right now, and will have to re-activate Loitr by scanning the Loitr activation QR on their Dashboard.<br /><br />
						Check out the full installation <a target='_blank' href='http://youtu.be/0_1sa6soy5U'>demo video here</a>.";
					break;
					case -4 :
						$widgetHTML = "<strong><a href='http://loitr.in'>http://loitr.in</a> is unreachable. It is either firewalled or this system is not connected to the Web.</strong>
						<br /><br />
						This plugin needs to be able to connect to the Loitr website to provide its services. Please check that the connections to the Web are in place and are not being prevented by a firewall.
						<br /><br />
						Check out the full installation <a target='_blank' href='http://youtu.be/0_1sa6soy5U'>demo video here</a>.";
					break;
				}
			}
		} else {
		 	//$wp_user = new WP_User(2);
			setSessionValue('activatetoken', md5(rand(0, time()) . time()));
			$activateQRURL = getQRURL(getSessionValue('activatetoken'), 'ACTIVATE', $loitrConfig['qrsettings']['activateqrtimeout'], 0, $loitrConfig['qrsettings']['dimension'], 'L', $loitrConfig['qrsettings']['dotcolor'], $loitrConfig['qrsettings']['backgroundcolor'], $current_user->ID);
		}
		$widgetHTML = "<strong>Scan this QR with Loitr on your phone to activate $blogName login on your phone.</strong><br /><br />Once $blogName is activated in your Loitr, you will be able to login to $blogName without typing anything and by simply scanning the QR on the $blogName login page. See <a target='_blank' href='http://youtu.be/EYwhc8mKr1o'>a demo here</a>.<br />Your usernames and passwords are neither used by Loitr nor transferred.<br /><br />Loitr is available for download on all Android & iPhones.";
		echo "<table style='width:100%;'><tr><td valign='top'><img src='$activateQRURL'></td><td valign='top'>$widgetHTML</td></tr></table><a href='https://loitr.in'>Loitr</a> &middot; contact@loitr.in &middot; <a href='https://www.facebook.com/loitr'>Loitr on Facebook</a>";
	}

	// Create the function use in the action hook
	function widgetHook() {
		// Globalize the metaboxes array, this holds all the widgets for wp-admin
		global $wp_meta_boxes;

		wp_add_dashboard_widget('loitrlogin_dash_widget', 'Activate Loitr', array(&$this, 'widgetHTML'));	

		// Get the regular dashboard widgets array 
		// (which has our new widget already but at the end)
		$normal_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];

		// Backup and delete our new dashboard widget from the end of the array
		$loitr_widget_backup = array('loitrlogin_dash_widget' => $normal_dashboard['loitrlogin_dash_widget']);
		unset($normal_dashboard['loitrlogin_dash_widget']);
		// Merge the two arrays together so our widget is at the beginning

		$sorted_dashboard = array_merge($loitr_widget_backup, $normal_dashboard);
		// Save the sorted array back into the original metaboxes 

		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}
}
?>