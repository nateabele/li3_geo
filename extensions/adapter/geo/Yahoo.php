<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions\adapter\geo;

/**
 * The Yahoo! PlaceFinder API is not currently supported.
 */
class Yahoo extends Base {

	protected $_service = array(
		'host'   => 'http://where.yahooapis.com',
		'coords' => '/geocode?appid={:key}&location={:address}'
	);

	protected function _init() {
		parent::_init();

		$this->_parsers += array(
			'coords'  => function($data) {
			},
			'address' => function($data) {
			}
		);
	}
}

?>