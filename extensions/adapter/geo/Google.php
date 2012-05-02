<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions\adapter\geo;

class Google extends Base {

	protected $_service = array(
		'host'    => 'http://maps.googleapis.com',
		'coords'  => '/maps/api/geocode/json?&address={:address}&sensor=false',
		'address' => '/maps/api/geocode/json?latlng={:latitude},{:longitude}&sensor=false'
	);

	/**
	 * Maps API address component results to address fields in `Location` object results.
	 *
	 * @var array
	 */
	protected $_addressKeyMap = array(
		'number' => 'street_number',
	);

	/**
	 * Configuration used to extract address components from a geocode result.
	 *
	 * @var array
	 */
	protected $_addressFormatMap = array(
		'street_number' => 'short_name',
	);

	protected function _init() {
		parent::_init();

		$this->_parsers += array(
			'coords'  => function($data) {
				return json_decode($data, true);
			},
			'address' => function($data) {
				$data = json_decode($data, true);
				return isset($data['results'][0]) ? $data['results'][0] : null;
			}
		);
	}
}

?>