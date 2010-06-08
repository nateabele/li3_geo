<?php

namespace li3_geo\extensions;

use \lithium\util\String;
use \lithium\core\Libraries;
use \lithium\core\Environment;
use \UnexpectedValueException;

/**
 * The `Geocoder` class handles all geocoding, coordinate calculation, and formula-generation
 * functionality for performing geocode lookups against various API services, and running
 * location-based queries against relational databases and MongoDB.
 */
class Geocoder extends \lithium\core\StaticObject {

	/**
	 * The list of classes which `Geocoder` depends on.
	 *
	 * @var array
	 */
	protected static $_classes = array(
		'service' => '\lithium\net\http\Service'
	);

	/**
	 * Index of geo-data lookup services.  Each item contains a lookup URL with placeholders,
	 * and a regular expression to parse latitude and longitude values.
	 *
	 * @var array
	 */
	protected static $_services = array();

	/**
	 * Keeps a copy of the request context information to determine information about the current
	 * host.
	 *
	 * @var array
	 */
	protected static $_context = array();

	/**
	 * Index of measurement unit factors relative to miles.  These values can be specified in any
	 * method that accepts a `$unit` parameter.  All `$unit` parameters also accept an arbitrary
	 * float value to use for distance conversions.  Unit values are represented as follows:
	 * M: miles, K: kilometers, N: nautical miles, I: inches, F: feet
	 * 
	 * @var array
	 */
	protected static $_units = array(
		'K' => 1.609344, 'N' => 0.868976242, 'F' => 5280, 'I' => 63360, 'M' => 1
	);

	public static function __init() {
		static::$_services['google'] = array(
			'url' => 'http://maps.google.com/maps/geo?&q={:address}&output=csv&key={:key}',
			'parser' => '/200,[^,]+,(?P<latitude>[^,]+),(?P<longitude>[^,\s]+)/',
		);
		static::$_services['yahoo'] = array(
			'url' => 'http://api.local.yahoo.com/MapsService/V1/geocode' .
			          '?appid={:key}&location={:address}',
			'parser' => '/<Latitude>(?P<latitude>.*)<\/Latitude>' .
			            '<Longitude>(?P<longitude>.*)<\/Longitude>/U',
		);
	}

	/**
	 * Gets or sets the configuration for a geocoding service.
	 *
	 * @param string $name The service name.
	 * @param array $config An array of configuration that defines the service.
	 * @return void
	 */
	public static function services($name = null, array $config = array()) {
		if (!$name) {
			return static::$_services;
		}
		if ($config) {
			static::$_services[$name] = $config;
		}
		return isset(static::$_services[$name]) ? static::$_services[$name] : null;
	}

	public static function context($context) {
		if (is_array($context)) {
			return static::$_context = array_merge($context, static::$_context);
		}
		return isset(static::$_context[$context]) ? static::$_context[$context] : null;
	}

	/**
	 * Get latitude/longitude points for given address from web service (i.e. Google / Yahoo!).
	 * 
	 * @param string $address The address to geocode.
	 * @return array Latitude and longitude data, or `false` on failure.
	 */
	public static function find($service, $address) {
		$params = compact('service', 'address');
		$_classes = static::$_classes;

		return static::_filter(__FUNCTION__, $params, function($self, $params) use ($_classes) {
			$service = $params['service'];
			$address = $params['address'];

			if (!$config = Geocoder::services($service)) {
				$message = "The lookup service '{$service}' does not exist.";
				throw new UnexpectedValueException($message);
			}

			$key = null;
			$host = Geocoder::context('host');
			$address = rawurlencode($address);
			$appConfig = Libraries::get('li3_geo');

			if (isset($appConfig['keys'][$service][$host])) {
				$key = $appConfig['keys'][$service][$host];
			}
			$url = parse_url(String::insert($config['url'], compact('key', 'address')));

			$connection = new $_classes['service'](array(
				'protocol' => $url['scheme'],
				'host' => $url['host']
			));

			if (!$result = $connection->get("{$url['path']}?{$url['query']}")) {
				return;
			}

			switch (true) {
				case is_string($config['parser']) && preg_match($config['parser'], $result, $match):
					return array(
						'latitude' => floatval($match['latitude']),
						'longitude' => floatval($match['longitude'])
					);
				case is_callable($parser = $config['parser']):
					return $parser($result);
			}
		});
	}
}

?>