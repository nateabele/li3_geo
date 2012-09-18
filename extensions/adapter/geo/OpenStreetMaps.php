<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions\adapter\geo;

use li3_geo\data\Geocoder;

class OpenStreetMaps extends Base {

	protected $_service = array(
		'host'    => 'http://nominatim.openstreetmap.org',
		'coords'  => '/search?q={:address}&format=json',
		'address' => '/reverse?lat={:latitude}&lon={:longitude}&format=json'
	);

	protected function _init() {
		parent::_init();

		$this->_parsers += array(
			'coords'  => function($data) {
				$result = json_decode($data, true);
				if (empty($result)) {
					return false;
				}
				$raw = $result[0];
				$bounds = array_map('floatval', $raw['boundingbox']);

				return compact('raw') + array(
					'coordinates' => array(
						'latitude' => floatval($raw['lat']),
						'longitude' => floatval($raw['lon'])
					),
					'license' => $raw['licence'],
					'bounds' => array('box' => array(
						array('latitude' => $bounds[0], 'longitude' => $bounds[2]),
						array('latitude' => $bounds[1], 'longitude' => $bounds[3])
					))
				);
			},
			'address' => function($data) {
				$result = json_decode($data, true);
				if (empty($result)) {
					return false;
				}
				$raw = $result + array('address' => array(), 'licence' => null);
				$addr = $raw['address'];
				$keys = array(
					'title' => 'attraction',
					'number' => 'house_number',
					'street' => 'pedestrian',
					'neighborhood' => 'suburb',
					'city' => 'city',
					'county' => 'county',
					'state' => 'state',
					'province' => 'province',
					'postalCode' => 'postcode',
					'country' => 'country'
				);
				$map = function($key) use ($addr) {
					if (is_array($key)) {
						foreach ($key as $test) {
							if (isset($addr[$test])) {
								$key = $test;
								break;
							}
						}
						if (is_array($key)) {
							return null;
						}
					}
					return isset($addr[$key]) ? Geocoder::normalizePlace($addr[$key]) : null;
				};
				$address = array_filter(array_map($map, $keys));
				$continent = Geocoder::continents($address['country']);
				$address['continent'] = is_array($continent) ? $address['country'] : $continent;

				return compact('raw', 'address') + array(
					'license' => $raw['licence'],
					'coordinates' => array(
						'latitude' => floatval($raw['lat']),
						'longitude' => floatval($raw['lon'])
					)
				);
			}
		);
	}
}

?>