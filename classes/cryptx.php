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

include_once('Crypt/RSA.php');

class CryptX {
	private $privateKey;
	private $publicKey;
	private $rsa;

	function CryptX($pubKey = '') {
		$this->rsa = new Crypt_RSA();
		$this->rsa->setHash('sha1');
		$this->rsa->setMGFHash('sha1');
		$this->rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		$this->rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
		if($pubKey != '') $this->publicKey = $pubKey;
	}

	function loadPrivateKey($priKey) {
		$this->privateKey = $priKey;
	}

	function loadPublicKey($pubKey) {
		$keyDetails = json_decode($pubKey, true);
		if($keyDetails != null) $this->importKeyFromModExp($keyDetails['modulus'], $keyDetails['exponent']);
		else $this->publicKey = $pubKey;
	}

	function loadKeyFile($keyFile) {
		$handle = fopen($keyFile, "r");
		if(!$handle) trigger_error("Failed reading private key file. Read permissions are required.");
		$this->privateKey = fread($handle, filesize($keyFile));
		fclose($handle);

		$tmprsa = new Crypt_RSA();
		$components = $this->rsa->_parseKey($this->privateKey, CRYPT_RSA_PRIVATE_FORMAT_PKCS1);
		$tmprsa->modulus = $components['modulus'];
		$tmprsa->exponent = $components['publicExponent'];
		$tmprsa->publicExponent = $components['publicExponent'];
		$tmprsa->k = strlen($tmprsa->modulus->toBytes());
		$this->publicKey = $tmprsa->getPublicKey(CRYPT_RSA_PUBLIC_FORMAT_PKCS1);
	}

	function importKeyFromModExp($modulus, $exponent) {
		$tmprsa = new Crypt_RSA();
		$tmprsa->modulus = new Math_BigInteger($modulus, 16);
		$tmprsa->publicExponent = $tmprsa->exponent = new Math_BigInteger($exponent, 16);
		$tmprsa->k = strlen($tmprsa->modulus->toBytes());
		$this->publicKey = $tmprsa->getPublicKey(CRYPT_RSA_PUBLIC_FORMAT_PKCS1);
	}

	function exportKeyModExp() {
		$components = $this->rsa->_parseKey($this->publicKey, CRYPT_RSA_PUBLIC_FORMAT_PKCS1);
		return array(
						'modulus' => $components['modulus']->toHex(),
						'exponent' => $components['publicExponent']->toHex()
		);
	}

	function generateKeyPair($keyFile = '', $keyBits = 512) {
		$tmp = $this->rsa->createKey($keyBits);
		$this->publicKey = $tmp['publickey'];
		$this->privateKey = $tmp['privatekey'];
		if($keyFile != '') {
			$fp = fopen($keyFile, 'x');
			if(!$fp) trigger_error("Failed writing to private key file. Write permissions are required in the Loitr plugin directory.");
			fwrite($fp, $this->privateKey);
			fclose($fp);
			chmod($keyFile, 0400);
		}
	}

	function sign($message) {
	 	$this->rsa->loadKey($this->privateKey, CRYPT_RSA_PRIVATE_FORMAT_PKCS1);
		return bin2hex($this->rsa->sign($message));
	}

		function sign2($message) {
		 	$this->rsa->loadKey($this->privateKey, CRYPT_RSA_PRIVATE_FORMAT_PKCS1);
			return bin2hex($this->rsa->encrypt(xpack("H*", hash('sha1', $message))));
		}

	function verifySign($message, $sign) {
		$this->rsa->loadKey($this->publicKey, CRYPT_RSA_PRIVATE_FORMAT_PKCS1);
		return $this->rsa->verify($message, xpack("H*" , strtolower($sign)));
	}

		function verifySign2($message, $sign) {
			$this->rsa->loadKey($this->publicKey, CRYPT_RSA_PRIVATE_FORMAT_PKCS1);
			if(bin2hex($this->rsa->decrypt(xpack("H*", strtolower($sign)))) == strtolower(hash('sha1', $message))) return true;
			else return false;
		}

	function encrypt($message) {
		$this->rsa->loadKey($this->publicKey, CRYPT_RSA_PRIVATE_FORMAT_PKCS1);
		return bin2hex($this->rsa->encrypt($message));
	}

	function decrypt($message) {
		$this->rsa->loadKey($this->privateKey, CRYPT_RSA_PRIVATE_FORMAT_PKCS1);
		return $this->rsa->decrypt(xpack("H*" , strtolower($message)));
	}

	function getPublicKey() {
		return $this->publicKey;
	}

	function getPrivateKey() {
		return $this->privateKey;
	}

	function showKeys() {
		print_r($this->publicKey);
		print_r($this->privateKey);
	}
}
?>