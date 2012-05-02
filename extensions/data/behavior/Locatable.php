<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions\data\behavior;

use lithium\util\Set;
use lithium\util\String;
use UnexpectedValueException;
use lithium\data\source\MongoDb;

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
	 * Class dependencies.
	 *
	 * @var array
	 */
	protected static $_classes = array(
		'geocoder' => 'li3_geo\data\Geocoder'
	);

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
		return array('classes' => static::$_classes);
	}

	/**
	 * Resets the configuration for this class.
	 *
	 * @return void
	 */
	public static function reset() {
		static::$_configurations = array();
	}

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
			'format' => '{:address} {:city}, {:state} {:zip}',
		);
		$config += $defaults;
		$geocoder = static::$_classes['geocoder'];

		if (!$geocoder::services($config['service'])) {
			$message = "The lookup service `{$config['service']}` does not exist.";
			throw new UnexpectedValueException($message);
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
	 * @param object $entity A `Record` or `Document` object containing the address data to be
	 *               geocoded.
	 */
	public static function geocode($entity) {
		if (!isset(static::$_configurations[$class = $entity->model()])) {
			return null;
		}
		$config = static::$_configurations[$class];
		$geocoder = static::$_classes['geocoder'];
		$data = Set::flatten($entity->data());
		$address = trim(String::insert($config['format'], $data));
		return $address ? $geocoder::find($config['service'], $address) : null;
	}

	/**
	 * @todo Delegate to correct database adapter.
	 */
	public static function index($class, array $keys, array $options = array()) {
		$defaults = array('include' => array(), 'background' => true);
		$options += $defaults;

		$conn = $class::connection();
		list($latitude, $longitude) = $keys;
		$base = static::_baseField($latitude, $longitude);

		if (!$conn || !$latitude || !$longitude) {
			return false;
		}
	}

	/**
	 * @todo Move me to the `MongoDb` adapter.
	 */
	protected static function _formatParameters($class, $key, $options) {
		$parent = 'conditions';
		$config = static::$_configurations[$class];
		$field = static::_baseField($config['fields'][0], $config['fields'][1]);
		$options[$parent] = isset($options[$parent]) ? (array) $options[$parent] : array();
		$key = ($key == 'near' || $key == 'within') ? $key : null;

		if (!$key || isset($options['conditions'][$field]['$' . $key])) {
			return $options;
		}
		list($location, $options, $key) = static::_fixOptions($options, $field, $key);

		if (!$location) {
			return $options;
		}
		$insert =& $options[$parent];

		switch ($key) {
			case 'near':
				$insert[$field]['$' . $key] = array_map('floatval', $location);
			break;
			case 'within':
				$insert[$field]['$' . $key]['$box'] = array(
					array_map('floatval', $location[1]),
					array_map('floatval', $location[0])
				);
			break;
		}
		return $options;
	}

	protected static function _fixOptions($options, $field, $key) {
		$parent = 'conditions';
		$location = array();

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
		return array($location, $options, $key);
	}

	protected static function _baseField($latitude, $longitude) {
		$base = null;

		if (strpos($latitude, '.')) {
			list($base) = explode('.', $latitude);
		} elseif ($latitude == $longitude) {
			$base = $latitude;
		}
		return $base;
	}
}

?>