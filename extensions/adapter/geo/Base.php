<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions\adapter\geo;

use UnexpectedValueException;
use lithium\util\String;

abstract class Base extends \lithium\core\Object {

	protected $_classes = array(
		'location' => 'li3_geo\data\Location'
	);

	protected $_service = array();

	protected $_parsers = array();

	public function service($name) {
		return isset($this->_service[$name]) ? $this->_service[$name] : null;
	}

	public function query($connection, $service, array $options) {
		if (!$path = $this->service($service)) {
			throw new UnexpectedValueException("Service configuration `{$name}` not defined.");
		}
		$url = String::insert($path, array_map('rawurlencode', $options));

		if (!$result = $connection->get($url)) {
			return;
		}
		if (!$config = $this->_decode($service, $result)) {
			return;
		}
		return $this->_instance('location', $config);
	}

	protected function _decode($service, $data) {
		if (!isset($this->_parsers[$service])) {
			throw new UnexpectedValueException("Service configuration `{$name}` not defined.");
		}
		return $this->_parsers[$service]($data);
	}
}

?>