<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

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
	 * Returns an array of geo coordinates from an EXIF data structure.
	 *
	 * This method expects an array of EXIF data containing the following keys:
	 *
	 * @see http://php.net/manual/en/function.exif-read-data.php PHP Manual: exif_read_data()
	 * @param array $data An array containing an EXIF data structure; usually the return value of
	 *              `exif_read_data()`.
	 * @return array Returns an array containing `'latitude'` and `'longitude'` keys which define
	 *         the coordinates of the image data, specified as float values.
	 */
	public static function exifCoords(array $data) {
		$dataAvailable = (
			isset($data['GPSLatitudeRef']) && isset($data['GPSLatitude']) ||
			isset($data['GPSLongitudeRef']) && isset($data['GPSLongitude'])
		);
		$result = array();

		if (!$dataAvailable) {
			return array();
		}

		foreach (array('latitude', 'longitude') as $key) {
			$source = 'GPS' . ucfirst($key);
			list($degrees, $minutes) = $data[$source];
			$result[$key] = static::degreesToDecimal($degrees, $minutes);

			if (in_array(strtoupper($data[$source . 'Ref']), array('S', 'W'))) {
				$result[$key] *= -1;
			}
		}
		return $result;
	}

	/**
	 * Converts a degrees/minutes pair to a decimal coordinate value.
	 *
	 * @param mixed $degrees Number of degrees as a whole number from 0 to 180, as an integer or
	 *              string.
	 * @param mixed $minutes The "minutes", or sub-degree offset of the coordinate value.
	 * @return float Returns the coordinate offset as a decimal value.
	 */
	public static function degreesToDecimal($degrees, $minutes) {
		foreach (compact('degrees', 'minutes') as $key => $value) {
			if (is_string($value) && strpos($value, '/')) {
				list($num, $divisor) = explode('/', $value);
				$value = intval($num) / intval($divisor);
			}
			${$key} = is_float($value) ? $value : floatval($value);
		}
		$minutes = round($minutes * (166 + 2 / 3));
		return floatval("{$degrees}.{$minutes}");
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
				'host' => $url['host'],
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