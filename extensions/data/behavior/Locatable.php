<?php

namespace li3_geo\extensions\data\behavior;

use \lithium\util\String;
use \lithium\data\Connections;
use \UnexpectedValueException;
use \li3_geo\extensions\Geocoder;

/**
 * The `Locatable` class handles all geocoding, coordinate calculation, and formula-generation
 * functionality for performing geocode lookups against various API services, and running
 * location-based queries against relational databases and MongoDB.
 */
class Locatable extends \lithium\core\StaticObject {

	/**
	 * An array of configurations indexed by model class name, for each model to which this class
	 * is bound.
	 *
	 * @var array
	 */
	protected static $_configurations = array();

	/**
	 * Binds geocoding functionality to the model specified.
	 *
	 * @param string $class A fully-namespaced class reference to a model class that `Geocoder`
	 *               should bind to. Assigns configuration for the model, and attaches custom
	 *               behavior to perform geocode lookups and location-based searches.
	 * @param array $config An array of configuration settings defining how `Geocoder` should
	 *              behave with this model. The available settings are as follows:
	 *              - `'service'` _string_: The name of the API service to use when geocoding
	 *                addresses. You can configure custom lookup services, but by default, the
	 *                available options are `'google'` or `'yahoo'`. Attempting to use an undefined
	 *                service will result in an exception.
	 *             
	 */
	public static function bind($class, array $config = array()) {
		$defaults = array(
			'service' => 'google',
			'autoIndex' => true,
			'fields' => array('latitude', 'longitude'),
			'format' => '{:address} {:city}, {:state} {:zip}'
		);
		$config += $defaults;

		if (!Geocoder::services($config['service'])) {
			$message = "The lookup service '{$config['service']}' does not exist.";
			throw new UnexpectedValueException($message);
		}

		if (is_string($config['fields'])) {
			$config['fields'] = array($config['fields'], $config['fields']);
		}
		if ($index = $config['autoIndex']) {
			static::index($class, $config['fields'], is_array($index) ? $index : array());
		}

		$finder = function($self, $params, $chain) use ($class) {
			$params['options'] = Locatable::invokeMethod('_formatParameters', array(
				$class, $params['type'], $params['options']
			));
			return $chain->next($self, $params, $chain);
		};
		$class::finder('near', $finder);
		$class::finder('within', $finder);
		$class::applyFilter('find', $finder);
		return static::$_configurations[$class] = $config;
	}

	/**
	 * Get the geocode latitude/longitude points from given address.
	 * Look in the cache first, otherwise get from web service (i.e. Google or Yahoo!).
	 * 
	 * @param object $record A `Record` or `Document` object containing the address data to be
	 *               geocoded.
	 */
	public static function geocode($record) {
		$class = $record->model();
		$data = array_map('trim', $record->data());
		$address = trim(String::insert(static::$_configurations[$model]['format'], $data));
		return $address ? Geocoder::find($address) : null;
	}

	/**
	 * Calculate the distance between to geographic coordinates using the circle distance formula
	 * 
	 * @param array $a An array representing "Point A" in the distance comparison. Should contain
	 *              two keys: latitude and longitude.
	 * @param array $b An array representing "Point B" in the distance comparison. Same format as
	 *              `$a`.
	 * @param mixed $unit   M=miles, K=kilometers, N=nautical miles, I=inches, F=feet
	 */
	public static function distance(array $a, array $b, $unit = 'M') {
		list($lat1, $lon1) = $a;
		list($lat2, $lon2) = $b;

		$sin = sin(deg2rad($lat1)) * sin(deg2rad($lat2));
		$cos = cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2));
		$distance = 69.09 * rad2deg(acos($sin + $cos));

		$unit = isset(static::$_units[$unit]) ? static::$_units[$unit] : floatval($unit);
		return $distance * $unit;
	}

	public static function index($class, array $keys, array $options = array()) {
		$defaults = array('include' => array(), 'background' => true);
		$options += $defaults;

		$meta = $class::meta();
		$database = Connections::get($meta['connection']);

		list($latitude, $longitude) = $keys;
		$base = static::_baseField($latitude, $longitude);

		if (!$database || !$latitude || !$longitude) {
			return false;
		}

		if (is_a($database, 'lithium\data\source\MongoDb')) {
			$index = array($base => '2d') + $options['include'];
			$collection = $meta['source'];
			unset($options['include']);
			$database->connection->{$collection}->ensureIndex($index, $options);
		}
	}

	protected static function _formatParameters($class, $key, $options) {
		$type = $key;
		$location = array();
		$parent = 'conditions';
		$config = static::$_configurations[$class];
		$field = static::_baseField($config['fields'][0], $config['fields'][1]);
		$options[$parent] = isset($options[$parent]) ? (array) $options[$parent] : array();
		$key = ($key == 'near' || $key == 'within') ? $key : null;

		if (isset($options[0]) && isset($options[1])) {
			$location = array($options[0], $options[1]);
			unset($options[0], $options[1]);
		}
		if ($key && isset($options[$key])) {
			$location = $options[$key];
			unset($options[$key]);
		}
		if (isset($options[$parent][$field])) {
			$location = $options[$parent][$field];
			unset($options[$parent][$field]);
		}
		if (isset($options[$field])) {
			$location = $options[$field];
			unset($options[$field]);
		}

		if (!$location) {
			return $options;
		}

		if (($loc = $location) && !$key && count($location) == 2) {
			if ((is_int($loc[0]) || is_float($loc[0])) && (is_int($loc[1]) || is_float($loc[1]))) {
				$key = 'near';
			} elseif (is_array($loc[0]) && is_array($loc[1])) {
				$key = 'within';
			}
		}

		if ($type == 'count' && !$options[$parent]) {
			$insert =& $options;
		} else {
			$insert =& $options[$parent];
		}

		switch ($key) {
			case 'near':
				$insert[$field]['$' . $key] = array_map('floatval', $location);
			case 'within':
				$insert[$field]['$' . $key]['$box'] = array(
					array_map('floatval', $location[1]),
					array_map('floatval', $location[0])
				);
		}
		return $options;
	}

	protected static function _baseField($latitude, $longitude) {
		if (strpos($latitude, '.')) {
			list($base) = explode('.', $latitude);
		} elseif ($latitude == $longitude) {
			$base = $latitude;
		}
		return $base;
	}

	/**
	 * Generates an SQL-formatted calculation that finds the geographic distance of the latitude and
	 * longitude data of a database record, compared to a fixed point.
	 *
	 * @param float $y The latitude value of the point that the record columns will be compared to.
	 * @param float $x The longitude value of the point that the record columns will be compared to.
	 * @param string $xField An SQL fragment containing the column or calculated value representing
	 *               the longitude value of a record.
	 * @param string $yField An SQL fragment containing the column or calculated value representing
	 *               the latitude value of a record.
	 * @return string Returns the SQL conditions for performing the Great-circle distance formula
	 *                against two database columns, compared to a point defined by `$x` and `$y`.
	 */
	protected static function _distanceQuery($y, $x, $xField, $yField) {
		$sql  = "(3958 * 3.1415926 * SQRT(({$yField} - {$y}) * ({$yField} - {$y}) + ";
		$sql .= "COS({$yField} / 57.29578) * COS({$y} / 57.29578) * ({$xField} - {$x}) * ";
		return $sql . "({$xField} - {$x})) / 180)";
	}
}

?>