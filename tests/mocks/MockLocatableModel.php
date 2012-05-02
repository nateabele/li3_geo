<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\tests\mocks;

use lithium\data\source\MongoDb;
use li3_geo\extensions\Geocoder;
use li3_geo\tests\mocks\MockService;
use lithium\util\collection\Filters;

class MockLocatableModel extends \lithium\core\StaticObject {

	public static $finders = array();

	public static function finder($name, $options = null) {
		static::$finders[$name] = $options;
	}

	public static function connection() {
		return new MongoDb(array('autoConnect' => false));
	}

	public static function schema() {
		return array();
	}

	public static function find($type, $options) {
		if (!isset(static::$finders[$type])) {
			return;
		}
		$class = __CLASS__;
		$method = __FUNCTION__;
		$data = array(static::$finders[$type], function($self, $params) {
			return $params;
		});
		return Filters::run($class, compact('type', 'options'), compact('data', 'class', 'method'));
	}
}

?>