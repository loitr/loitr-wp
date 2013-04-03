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

class LoitrLoginSettings {

	private $installationStatus;
	private $mobileAccess;

	function __construct($instStatus, $isMobile) {

		$this->installationStatus = $instStatus;
		$this->mobileAccess = $isMobile;
		# First use the add_action to add onto the WordPress menu.
		add_action('admin_menu', array(&$this, 'addOptionsLink'));

		# Then register settings
		add_action('admin_init', array(&$this, 'setupSettings'));
	}

	function addOptionsLink() {
		add_options_page('Loitr Settings', 'Loitr Settings', 'manage_options', __FILE__, array(&$this, 'optionsPageHTML'));
	}

	function optionsPageHTML() {
		global $loitrConfig;

		global $current_user;

		switch($this->installationStatus) {
			case -1 :
				$settingsPageHTML = "<strong>Oops! The Private Key file for Loitr is not readable.</strong> <br/>
				The plugin expects a key file at <i>{$loitrConfig['keyFile']}</i>.<br />
				Either the activation process failed to create it automatically because of lack of write permissions or the file has been moved.<br />
				<br />
				You can either:<br>
				<ol>
					<li>
						Grant write permissions to the <strong>Loitr Login</strong> plugin folder residing in wp-content/plugins/ and <strong>Deactivate</strong> & <strong>Activate</strong> the plugin again to generate the key files.
					</li>
					<li>
						Or check to see if the file exists but read permissions have been revoked on the folder, or maybe the key file has been moved.
					</li>
				</ol>
				";
			break;
			case -2 :
				$lpk = new CryptX();
				$lpk->loadKeyFile($loitrConfig['keyFile']);
				$modexp = json_encode($lpk->exportKeyModExp());
				$blogname = get_bloginfo( 'name' );
				$blogdescription = get_bloginfo( 'name' );
				$adminEmail = get_option('admin_email');
				$endpointurl = site_url().'/wp-admin/admin-ajax.php?action=loitrlogin';
				$settingsPageHTML = "<br /><strong>We couldn't locate the service identifier for your blog. (at Line 25 in ".$loitrConfig['abspath']."config.php)</strong>
				<ol>
					<li>
						If you haven't sent the following, then copy the contents of the rectangle below and send it to <strong>contact@loitr.in</strong>. Note that the sender should be: <strong>$adminEmail</strong><br />
						<div style='font-family:courier;background:#EEEEEE;padding:15px;border:1px solid #DDDDDD;'>
							<strong>Name</strong><br />
							$blogname<br />
							<br /><br />
							<strong>Description</strong><br />
							$blogdescription<br />
							<br /><br />
							<strong>Endpoint URL</strong><br />
							$endpointurl<br />
							<br /><br />
							<strong>Public Key (Modulus & Exponent)</strong><br />
							".nl2br(print_r($modexp, true))."
						</div>
					</li>
					<li>
						If you have sent it already and haven't yet received your identifier, we are working to verify that you are the rightful owner of your blog. This process takes not more than 2-3 days.
					</li>
					<li>
						If you have received your service identifier, then locate the config.php file in the Loitr plugin folder(typically residing in wp-content/plugins/) and add your service identifier in the <strong>loitrConfig</strong> variable as explained in the config file.
					</li>
				</ol>";
			break;
			case -3 :
				$sqlMappingsTbl = "CREATE TABLE IF NOT EXISTS {$loitrConfig['tables']['mappings']['aliasedto']} ({$loitrConfig['tables']['mappings']['columns']['deviceid']} varchar(250) NOT NULL, {$loitrConfig['tables']['mappings']['columns']['vector']} text NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
				$sqlTokensTbl = "CREATE TABLE IF NOT EXISTS {$loitrConfig['tables']['tokens']['aliasedto']} ({$loitrConfig['tables']['tokens']['columns']['userid']} varchar(250) NOT NULL, {$loitrConfig['tables']['tokens']['columns']['token']} varchar(50) NOT NULL, {$loitrConfig['tables']['tokens']['columns']['tokentype']} varchar(20) NOT NULL, {$loitrConfig['tables']['tokens']['columns']['expireson']} bigint(20) NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
				$settingsPageHTML = "<strong>The required database tables were not found.</strong>
				<br /><br />
				Loitr expects 2 tables, namely <strong>{$loitrConfig['tables']['mappings']['aliasedto']}</strong> & <strong>{$loitrConfig['tables']['tokens']['aliasedto']}</strong> in your database. This has either happened because the activation process didn't have database access rights or maybe the tables got deleted after creation.<br/>
				You can try Deactivate-ing & Activate-ing the plugin again to check to see if the tables get created again.<br /><br />
				If that doesn't work you can try creating the tables yourself by running the following query in the Mysql prompt on the Wordpress database you are using:
				<div style='font-family:courier;background:#EEEEEE;padding:15px;border:1px solid #DDDDDD;'>
					$sqlMappingsTbl
					<br />
					<br />
					$sqlTokensTbl
				</div>
				Please note that if the tables did existed and users were using Loitr on your blog, they are unable to use Loitr right now, and will have to re-activate Loitr by scanning the Loitr activation QR on their Dashboard.";
			break;
			case -4 :
				$settingsPageHTML = "<strong><a href='http://loitr.in'>http://loitr.in</a> is unreachable. It is either firewalled or this system is not connected to the Web.</strong>
				<br /><br />
				This plugin needs to be able to connect to the Loitr website to provide its services. Please check that the connections to the Web are in place and are not being prevented by a firewall.";
			break;
			case 1:
				$settingsPageHTML = "Everything is alright!";
			break;
		}
		echo "<div class='wrap'>
					".screen_icon()."
					<h2>Loitr Settings</h2>
					".settings_fields('loitrlogin_settings')."
					".do_settings_sections( __FILE__ )."
					$settingsPageHTML
					<br /><br />
					<a href='https://loitr.in'>Loitr</a> &middot; contact@loitr.in &middot; <a href='http://loitr.in/blog/post-titled-loitr-wordpress-plugin-for-logins-in-features/9'>Blog post about this plugin</a> &middot; <a href='https://www.facebook.com/loitr'>Loitr on Facebook</a>
				</div>";
	}

	function setupSettings() {
		register_setting('loitrlogin_settings', 'loitrlogin_settings');
	}
}
?>