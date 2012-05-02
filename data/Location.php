<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\data;

use lithium\util\String;

/**
 * The `Location` class represents all attributes of a physical geographic location, including
 * coordinates, address, and administrative and geographic boundary information.
 */
class Location extends \lithium\core\Object {

	protected $_coordinates = array();

	protected $_address = array();

	protected $_bounds = array();

	protected $_license;

	protected $_raw = array();

	protected $_autoConfig = array('coordinates', 'address', 'bounds', 'license', 'raw');

	public function coordinates($key = null) {
		if ($key) {
			return isset($this->_coordinates[$key]) ? $this->_coordinates[$key] : null;
		}
		return $this->_coordinates ?: null;
	}

	public function bounds($key = null) {
		if (!$key) {
			return isset($this->_bounds['box']) ? $this->_bounds['box'] : reset($this->_bounds);
		}
		return isset($this->_bounds[$key]) ? $this->_bounds[$key] : null;
	}

	public function address($extract = null) {
		if (!$extract) {
			return $this->_address;
		}
		if (is_string($extract)) {
			if (strpos($extract, '{:')) {
				return preg_replace('/\{:\w+\}/', '', String::insert($extract, $this->_address));
			}
			return isset($this->_address[$extract]) ? $this->_address[$extract] : null;
		}
		if (is_array($extract)) {
			$address = $this->_address;

			return array_filter(array_map(
				function($key) use ($address) {
					return isset($address[$key]) ? $address[$key] : null;
				},
				array_combine($extract, $extract)
			));
		}
	}

	public function license() {
		return $this->_license;
	}

	public function raw() {
		return $this->_raw;
	}
}

?>
