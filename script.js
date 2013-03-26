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

function toggle(id) {
	var el = document.getElementById(id);
	if(el.style.display == 'none') el.style.display = '';
	else el.style.display = 'none';
}

var LoitrLogin = new function() {
	this.token = 0;
	this.checkevery = 4000;
	this.timerid = null;
	this.loginStatus = false;
	this.checkStatus = function() {
		if(this.loginStatus == false) {
			this.timerid = setTimeout(function() { LoitrLogin.checkStatus(); }, this.checkevery);
			jQuery.post(
				LoitrAJAX.ajaxurl,
				{
					action : 'loitrlogin',
					op : 'checktoken',
					token : this.token
				},
				function( response ) {
					responseArr = jQuery.parseJSON(response);
					if(responseArr.status == 1) document.location = 'wp-login.php'; //Reload the page, user information has been received
				}
			);
		}
	};
	this.loginSuccess = function() { this.loginStatus = true; }
	this.cancelCheck = function() {
		clearTimeout(this.timerid);
	}
}