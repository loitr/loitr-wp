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

class LoitrLogin {

	private $installationStatus;
	private $mobileAccess;

    /*--------------------------------------------*
     * Constructor
     *--------------------------------------------*/
    /**
     * Initializes the plugin by setting localization, filters, and administration functions.
     */
    function __construct($instStatus, $isMobile) {
    	global $loitrConfig;

		$this->mobileAccess = $isMobile;

		if($instStatus < 0) $this->installationStatus = false;
		else $this->installationStatus = true;

		if($this->installationStatus) {
	 		# Register languages options
	        load_plugin_textdomain( 'loitrlogin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	        # Register site styles and scripts
			add_action( 'init', array( &$this, 'initSetup' ), 1 );
			add_action( 'wp_enqueue_scripts', array( &$this, 'addLoitrStyles' ));
			add_action( 'wp_enqueue_scripts', array( &$this, 'addLoitrScripts' ));
			add_action( 'wp_logout', array( &$this, 'cleanUpSessions' ) );
			add_filter( 'login_message', array( &$this, 'loginBodyMod' ) );
			add_filter( 'authenticate', array( &$this, 'takeMeHome' ), 10, 3 );
		}
	} # end constructor

    /**
     * Registers and enqueues plugin-specific styles.
     */
	function addLoitrStyles() {
		global $loitrConfig;
		wp_register_style( 'loitrlogincss', plugins_url( $loitrConfig['plugindirname'].'/style.css' ) );
        wp_enqueue_style( 'loitrlogincss', plugins_url( $loitrConfig['plugindirname'].'/style.css' ) );
	}

    /**
     * Registers and enqueues plugin-specific scripts.
     */
	function addLoitrScripts() {
		global $loitrConfig;
		wp_register_script( 'loitrloginjs', plugins_url( $loitrConfig['plugindirname'].'/script.js' ), array( 'jquery' ) );
		wp_localize_script( 'loitrloginjs', 'LoitrAJAX', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
		wp_enqueue_script( 'loitrloginjs', plugins_url( $loitrConfig['plugindirname'].'/script.js' ), array( 'jquery' ) );
	}

    /**
     * The function overriding the conventional login process and returning the WP_User object
     */
	function takeMeHome( $user, $username, $password ) {
		$useremail = getSessionValue('thisuseremail');
		if(strlen($useremail) > 0) {
		 	$this->cleanUpSessions();
			$user = $this->getUserInfo( 'email',  $useremail );
			$user = new WP_User($user->ID);
			remove_action('authenticate', 'wp_authenticate_username_password', 20);
			return $user;
		} else return false;
	}

    /**
     * get_user_by is not available in WP versions prior to 2.8
     */
	function getUserInfo($field, $value) {
		global $loitrConfig, $wpdb;
		if(!function_exists('get_user_by')) {
			$keys = array(
							'id' => 'ID',
							'email' => 'user_email',
							'login' => 'user_login'
			);
			$userInfo = $loitrConfig['dbConnection']->get_row("select * from {$wpdb->prefix}users where {$keys[$field]}='$value'");
			$userObject = new stdClass();
			foreach($userInfo as $k=>$v) $userObject->$k = $v;
		} else return get_user_by($field, $value);
		return $userObject;
	}

    /**
     * Start sessions
     */
	function initSetup() {
	    if (!session_id()) {
	        session_start();
	    }
	}
	
    /**
     * And clean up sessions after user logs out
     */
	function cleanUpSessions() {
		unsetSessionValues();
	}

    /**
     * To modify the login form and add the Loitr QR
     */
	function loginBodyMod( $message ) {
		global $loitrConfig;

		setSessionValue('logintoken', md5(rand(0, time()) . time()));
		$loginQRURL = getQRURL(getSessionValue('logintoken'), 'LOGIN', $loitrConfig['qrsettings']['loginqrtimeout'], 0, $loitrConfig['qrsettings']['dimension'], 'L', $loitrConfig['qrsettings']['dotcolor'], $loitrConfig['qrsettings']['backgroundcolor']);
		wp_head();

	    if ( empty($message) ){
	    	if($this->mobileAccess) {
	    	 	$loginQRURL = 'http://loitr.in/qrx'.substr($loginQRURL, strrpos($loginQRURL, '/'));
				return "<div style='text-align:center;margin:10px 0;'>
					<a class='loitrbutton loitrpositivebutton' target='_blank' href='$loginQRURL'>Login with Loitr</a>
				</div>
				<script>
					LoitrLogin.token = '".getSessionValue('logintoken')."';
					LoitrLogin.checkStatus();
				</script>";
			} else {
		        return "<div style='text-align:center;margin:10px 0;'>
					<a class='loitrbutton loitrpositivebutton' onclick='toggle(\"loitrqrbox\")'>Login with Loitr</a>
				</div>
				<div id='loitrqrbox' style='display:none;'>
					<div class='clearfix' style='padding: 10px;'>
						<div class='left' style='width:".($this->qrSettings['dimension'] + 10).";'>
							<img src='$loginQRURL'>
						</div>
						<div>
							<div style='padding:10px 0 0 10px;'>
								<strong>Scan the QR with Loitr to login securely.</strong>
								<br /><br />
								You don't need to type anything.<br /><a target='_blank' href='http://youtu.be/EYwhc8mKr1o'>See the demo</a>.
							</div>
						</div>
					</div>
					<div id='loitrqrboxfooter'>
						Powered by <a href='https://loitr.in'>Loitr</a> &nbsp;&nbsp;
						<a target='_blank' href='https://play.google.com/store/apps/details?id=com.appavatar.loitr'><img style='vertical-align:text-bottom;' src='{$loitrConfig['url']['imagesdir']}android.png'></a>
						<a target='_blank' href='http://itunes.apple.com/app/loitr/'><img style='vertical-align:text-bottom;' src='{$loitrConfig['url']['imagesdir']}ios.png'></a>
					</div>
					<script>
						LoitrLogin.token = '".getSessionValue('logintoken')."';
						LoitrLogin.checkStatus();
					</script>
				</div>";
			}
	    } else {
	        return $message;
	    }
	}
}
?>