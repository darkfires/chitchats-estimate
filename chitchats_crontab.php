#!/usr/bin/env php
<?php
/*
 * Chit Chats Estimate PHP Class
 * Copyright (C) 2025 Len White <lwhite@nrw.ca>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class Estimate {
	public $Cookies;
	public $CSRF_Token;
	public $ClientID;

	// ClientID is the Chit Chats account ID
	public function __construct($ClientID) {
		$this->CSRF_Token = "";
		$this->Cookies = "";
		$this->ClientID = $ClientID;

		if (extension_loaded('tidy') == false) {
			echo "You need to enable the PHP extension tidy for this class to work.\n";
			return false;
		}

		if (extension_loaded('curl') == false) {
			echo "You need to enable the PHP extension curl for this class to work.\n";
			return false;
		}

		return true;
	}

	// recursive HTML element with attribute search
	public function recurseSearch($obj, $target, $attrib, $returnAttrib = 'value') {
		if (isset($obj->child))
			foreach ($obj->child as $cid => $cval) {
				// match target and attribute, return target value
				if ($obj->name == $target && (isset($obj->attribute) && $obj->attribute == $attrib)) {
					return $cval->{$returnAttrib};
				}

				if (isset($cval->child)) {
					$retval = $this->recurseSearch($cval, $target, $attrib);

					if ($retval != false)
						return $retval;
				}
			}

		return false;
	}

	// query Chit Chats server for estimate
	public function getRates($estArray) {
		$estArray["commit"] = "Calculate Rate";

		$httpArray	= http_build_query($estArray);

		// send html form in POST with ClientID
		$ch = curl_init("https://chitchats.com/clients/{$this->ClientID}/estimate");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $httpArray);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded',
			"x-csrf-token: {$this->CSRF_Token}",
			"cookie: {$this->Cookies}",
			'Content-Length: ' . strlen($httpArray))
		);

		$rates = array();

		// we get back the results from chitchats.
		$result = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		// HTTP returns 200 if successful
		if ($httpCode != 200) {
			$msg = "";

			if ($httpCode == 302) {
				$msg = ": Session expired, need new cookies";
			}

			throw new Exception("HTTP code returned {$httpCode}{$msg}");
			return false;
		}

		// Check if there was an error with our query
		if (strpos($result, 'mb-3 text-danger')) {
			$trimResult = substr($result, strpos($result, '<div class="mb-3 text-danger'));
			$trimResult = substr($trimResult, strpos($trimResult, '</i>')+4);
			$trimResult = substr($trimResult, 0, strpos($trimResult, '</span'));
			throw new Exception("Query failed: {$trimResult}");
			return false;
		}

		// Check for other messages such as no rates
		if (strpos($result, 'card p-3 mt-3')) {
			$trimResult = substr($result, strpos($result, "<div class=\"card p-3 mt-3"));
			$trimResult = substr($trimResult, 0, strpos($trimResult, '</div'));
			throw new Exception("Query failed: ". trim(strip_tags($trimResult)));
			return false;
		}

		$trimResult = substr($result, strpos($result, 'class="js-postage-rates-scroll'));
		$trimResult = substr($trimResult, strpos($trimResult, '<tbody'), strpos($trimResult, '</table'));

		$tidy = tidy_parse_string($trimResult);

		if ($tidy == false) {
			throw new Exception("Tidy parser failed to parse result");
			return false;
		}

		$tbody = 0;

		foreach ($tidy->Body()->child[0] as $xid => $hval) {
			if ($xid == "name" && $hval == "tbody") {
				$tbody = 1;
			}

			if ($tbody && $xid == "child") {
				foreach ($hval as $cid => $cval) {
					if ($cval->name == "tr") {
						if (isset($cval->attribute["data-description"])) {
							foreach ($cval->attribute as $attrname => $attrval) {
								if (!strstr($attrname, 'data'))
									continue;

								$rates[$cval->attribute["data-description"]][str_replace("data-", "", $attrname)] = $attrval;
							}

							$retval = $this->recurseSearch($cval, "span", array("class" => "d-block text-muted"));

							if ($retval != false)
								$rates[$cval->attribute["data-description"]]["shipping-time"] = $retval;
						}
					}
				}
			}
		}

		return $rates;
	}
}

try {
	// Estimate ( Chit Chats Client ID )
	$estimate = new Estimate(XXXXXX);

	$rates = $estimate->getRates(array(
		"estimate_view_model[package_preset]"	=> "custom",
		"estimate_view_model[region_id]"	=> "1",
		"estimate_view_model[country_code]"	=> "US",
		"estimate_view_model[postal_code]"	=> "11228",
		"estimate_view_model[street]"		=> "123 ANYWHERE ST",
		"estimate_view_model[city]"		=> "Brooklyn",
		"estimate_view_model[province_code]"	=> "NY",
		"estimate_view_model[package_type]"	=> "parcel",	// thick_envelope
		"estimate_view_model[size_x_amount]"	=> "30",	// length
		"estimate_view_model[size_y_amount]"	=> "30",	// width
		"estimate_view_model[size_z_amount]"	=> "10",	// height
		"estimate_view_model[size_unit]"	=> "cm",
		"estimate_view_model[weight_amount]"	=> "0.3",
		"estimate_view_model[weight_unit]"	=> "kg"
		));

	echo print_r($rates, 1);
} catch (Exception $e) {
	echo "Exception error: {$e->getMessage()}\n";
}
