<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions\adapter\geo;

use li3_geo\data\Geocoder;

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

		$parse = function($data) {
			$data = json_decode($data, true);
			if (!isset($data['results'][0])) {
				return false;
			}
			$result = $raw = $data['results'][0];

			if (isset($raw['geometry']['bounds'])) {
				$bounds = $raw['geometry']['bounds'];
				$result += array(
					'bounds' => array('box' => array(
						array(
							'latitude' => $bounds['southwest']['lat'],
							'longitude' => $bounds['southwest']['lng']
						),
						array(
							'latitude' => $bounds['northeast']['lat'],
							'longitude' => $bounds['northeast']['lng']
						)
					))
				);
			}

			if (isset($raw['geometry']['location'])) {
				$result += array(
					'coordinates' => array(
						'latitude' => $raw['geometry']['location']['lat'],
						'longitude' => $raw['geometry']['location']['lng']
					)
				);
			}

			$keys = array(
				'title' => 'point_of_interest',
				'number' => 'street_number',
				'street' => 'route',
				'neighborhood' => array(
					'neighborhood',
					'sublocality'
				),
				'city' => 'locality',
				'county' => 'administrative_area_level_2',
				'state' => 'administrative_area_level_1',
				'province' => 'administrative_area_level_1',
				'postalCode' => 'postal_code',
				'country' => 'country'
			);
			if (isset($raw['address_components'])) {
				$addr = $raw['address_components'];
				$map = function($key) use ($addr) {
					$value = null;
					if (is_array($key)) {
						foreach ($key as $test) {
							foreach ($addr as $component) {
								if (in_array($test, $component['types'])) {
									$value = $component['long_name'];
									break 2;
								}
							}
						}
					} else {
						foreach ($addr as $component) {
							if (in_array($key, $component['types'])) {
								$value = $component['long_name'];
								break;
							}
						}
					}
					if ($value === null) {
						return null;
					}
					return Geocoder::normalizePlace($value);
				};
				$address = array_filter(array_map($map, $keys));
				$continent = Geocoder::continents($address['country']);
				$address['continent'] = is_array($continent) ? $address['country'] : $continent;
				$result += compact('address');
			}

			return $result;
		};

		$this->_parsers += array(
			'coords'  => $parse,
			'address' => $parse
		);
	}
}

?>