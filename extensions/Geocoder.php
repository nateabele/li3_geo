<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions;

use lithium\util\String;
use lithium\core\Libraries;
use lithium\core\Environment;
use UnexpectedValueException;
use lithium\core\ConfigException;

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
		'service' => 'lithium\net\http\Service'
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
		'K' => 1.609344,
		'N' => 0.868976242,
		'F' => 5280,
		'I' => 63360,
		'M' => 1
	);

	/**
	 * Initializes the default values for services and configuration.
	 *
	 * @return void
	 */
	public static function __init() {
		static::reset();
	}

	/**
	 * Resets service and configuration data to their defaults.
	 *
	 * @return void
	 */
	public static function reset() {
		static::$_context = Libraries::get('li3_geo');
		static::$_services = array();
		$at = 'latitude';
		$on = 'longitude';

		static::$_services += array(
			'osm' => array(
				'host'    => 'http://nominatim.openstreetmap.org',
				'coords'  => '/search?q={:address}&format=json',
				'address' => '/reverse?lat={:latitude}&lon={:longitude}&format=json',
				'parser'  => array(
					'coords' => function($data) {
						$data = json_decode($data, true);

						return array(
							'latitude' => floatval($data[0]['lat']),
							'longitude' => floatval($data[0]['lon'])
						);
					},
					'address' => function($data) {
						$data = json_decode($data, true);
						return $data['address'];
					}
				)
			),
			'google' => array(
				'host'    => 'http://maps.googleapis.com',
				'coords'  => '/maps/geo?&q={:address}&output=csv',
				'address' => '/maps/api/geocode/json?latlng={:latitude},{:longitude}&sensor=false',
				'parser'  => array(
					'coords'  => "/200,[^,]+,(?P<{$at}>[^,]+),(?P<{$on}>[^,\s]+)/",
					'address' => function($data) {
						$data = json_decode($data, true);
						return isset($data['results'][0]) ? $data['results'][0] : null;
					}
				),
			),
			'yahoo' => array(
				'host'   => 'http://where.yahooapis.com',
				'coords' => '/geocode?appid={:key}&location={:address}',
				'parser' => array(
					'coords' => "/<{$at}>(?P<{$at}>.*)<\/{$at}><{$on}>(?P<{$on}>.*)<\/{$on}>/U"
				)
			)
		);
	}

	/**
	 * Allows the class to be configured with custom dependencies.
	 *
	 * @param array $config An array containing a `'classes'` key.
	 * @return void
	 */
	public static function config(array $config = array()) {
		if (isset($config['classes'])) {
			static::$_classes = array_merge(static::$_classes, $config['classes']);
		}
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
	 * @link http://php.net/manual/en/function.exif-read-data.php PHP Manual: `exif_read_data()`
	 * @param array $data An array containing an EXIF data structure; usually the return value of
	 *              `exif_read_data()`.
	 * @return array Returns an array containing `'latitude'` and `'longitude'` keys which define
	 *         the coordinates of the image data, specified as float values.
	 */
	public static function exifCoords(array $data) {
		$expectedKeys = array('GPSLatitudeRef', 'GPSLatitude', 'GPSLongitudeRef', 'GPSLongitude');
		$result = array();
		$keys = array_combine($expectedKeys, $expectedKeys);

		if (array_intersect_key($keys, $data) != $keys) {
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
	 * @param string $service The name of the service to use, i.e. `'google'` or `'osm'`.
	 * @param string $address The address to geocode.
	 * @return array Latitude and longitude data, or `false` on failure.
	 */
	public static function coords($service, $address) {
		$params = compact('service', 'address');

		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			$address = rawurlencode($params['address']);
			$service = $params['service'];
			return $self::run('coords', $service, compact('address'));
		});
	}

	/**
	 * Get address information for the given set of coordinates.
	 *
	 * @param string $service The name of the service to use, i.e. `'google'` or `'osm'`.
	 * @param float $latitude Coordinate latitude.
	 * @param float $longitude Coordinate longitude.
	 * @return array Address information.
	 */
	public static function address($service, $latitude, $longitude) {
		$params = compact('service', 'latitude', 'longitude');

		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			$service = $params['service'];
			unset($params['service']);
			return $self::run('address', $service, $params);
		});
	}

	public static function run($type, $service, array $data) {
		$host = static::context('host');
		$key  = null;

		if (!$config = static::services($service)) {
			throw new UnexpectedValueException("The lookup service `{$service}` does not exist.");
		}
		if (!isset($config[$type]) || !isset($config['parser'][$type])) {
			$msg = "The lookup service `{$service}` is not configured for `{$type}` operations.";
			throw new ConfigException($msg);
		}
		if (($ctxKey = static::context('keys')) && isset($ctxKey[$service][$host])) {
			$key = $ctxKey[$service][$host];
		}
		$data += compact('key');

		if (!$result = static::_connection($service)->get(String::insert($config[$type], $data))) {
			return;
		}
		return static::_parse($config['parser'][$type], $result);
	}

	/**
	 * Gets a connection object instance configured for a given geocoder service.
	 *
	 * @param string The service name to get the connection for, i.e. `'google'` or `'osm'`.
	 * @return object Returns an instance of the class configured in `$_classes['service']`.
	 */
	protected static function _connection($service) {
		$config = static::services($service);
		list($scheme, $host) = explode(':', $config['host']);
		$host = trim($host, '/');
		return static::_instance('service', compact('scheme', 'host'));
	}

	/**
	 * Passes raw geocoder service data through a configured parser to convert it to a standard
	 * format.
	 *
	 * @param mixed $parser Either a callback, or a regular expression.
	 * @param string $data Raw data returned from the geocoder service.
	 * @return mixed Returns parsed, structured data from the geocoder service parser.
	 */
	protected static function _parse($parser, $data) {
		if (is_callable($parser)) {
			return $parser($data);
		}
		if (is_string($parser) && preg_match($parser, $data, $match)) {
			return array_diff_key(array_map('floatval', $match), range(0, 10));
		}
	}

	/**
	 * Calculates the distance between to geographic coordinates using the circle distance formula.
	 *
	 * @see li3_geo\extensions\Geocoder::$_units
	 * @param array $a An array representing "Point A" in the distance comparison. Should contain
	 *              two keys: latitude and longitude.
	 * @param array $b An array representing "Point B" in the distance comparison. Same format as
	 *              `$a`.
	 * @param mixed $unit Either a numeric multiplier, where `1` represents a mile, Or a string key
	 *              representing an available unit conversion. See the `$_units` property for
	 *              possible values.
	 */
	public static function distance(array $a, array $b, $unit = 'M') {
		list($lat1, $lon1) = array_values($a);
		list($lat2, $lon2) = array_values($b);

		$unit = isset(static::$_units[$unit]) ? static::$_units[$unit] : floatval($unit);
		$sin = sin(deg2rad($lat1)) * sin(deg2rad($lat2));
		$cos = cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2));

		return 69.09 * rad2deg(acos($sin + $cos)) * $unit;
	}
}

?>