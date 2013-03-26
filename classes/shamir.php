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

class Shamir {

	const prime = 257;

	public static function share($secret, $total, $required) {

		if($total >= self::prime || $total <= 0 || $required > $total || $required <= 0) return false;

		$coeffs = array();
		$shares = array();
        for($i = 0; $i < $total; $i++)
            $shares[$i] = sprintf("%02x%02x", $required, $i+1);

		foreach(unpack("C*", $secret) as $byte) {
			$coeffs = self::generateCoeffs($byte, $required);
			for($i = 0; $i < $total; $i++) {
				$enc = sprintf("%02x", self::evalPoly($coeffs, $i + 1));
				$shares[$i] .= $enc.',';
			}
		}

		return $shares;
	}

	public static function recover($tmpshares) {

		$shares = array();
		$secret = '';
		$required = hexdec(substr($tmpshares[0], 0, 2));

		foreach($tmpshares as $share)
			$shares[] = array('x' => hexdec(substr($share, 2, 2)), 'share' => explode(",", trim(substr($share, 4), ",")));

		$bytes = count($shares[0]['share']);
		for($i = 0; $i < $bytes; $i++) {
		 	$e = new EQN();
			foreach($shares as $share) {
			 	$hexfx = $share['share'][$i];
				$e->setPoint(array($share['x'], hexdec($hexfx)));
			}
			$secret .= chr(abs($e->getConstantTerm()));
		}
		return $secret;
	}

	private static function evalPoly($coeffs, $x) {
        $val = 0;
        foreach($coeffs as $c) {
            $val = $x * $val + $c;
        }
        return $val;
	}

	private static function generateCoeffs($constantTerm, $k) {
		$coeffs = array();
		for($i = 1; $i < $k; $i++)
			$coeffs[] = rand(0, self::prime - 1);
		$coeffs[] = $constantTerm;
		return $coeffs;
	}
}

class EQN {

	private $points;

	function __construct() {
		$points = array();
	}

	public function setPoint($p) {
		$this->points[] = array(
								'x' => $p[0],
								'fx' => $p[1]
		);
	}

	public function getConstantTerm() {
		$a0 = 0;

		for($i = 0; $i < count($this->points); $i++) {
			$numerator = 1;
			$denominator = 1;
			for($j = 0; $j < count($this->points); $j++) {
				if($i != $j) {
					$numerator *= (-1)*$this->points[$j]['x'];
					$denominator *= $this->points[$i]['x'] - $this->points[$j]['x'];
				}
			}
			$a0 += ($this->points[$i]['fx']*$numerator)/$denominator;
		}

		return $a0;
	}
}
?>