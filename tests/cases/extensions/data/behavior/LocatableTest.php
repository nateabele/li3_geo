<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\tests\cases\extensions\data\behavior;

use Closure;
use li3_geo\data\Geocoder;
use lithium\data\entity\Document;
use li3_geo\tests\mocks\MockService;
use li3_geo\tests\mocks\MockLocatableModel;
use li3_geo\extensions\data\behavior\Locatable;

class LocatableTest extends \lithium\test\Unit {

	protected static $_addresses = array();

	public static function find($service, $address) {
		static::$_addresses[] = $address;
		return array(84.13, 11.38);
	}

	public static function services($service) {
		return $service != 'bar';
	}

	public function setUp() {
		Locatable::config(array('classes' => array('geocoder' => __CLASS__)));
		Locatable::reset();
		MockLocatableModel::$finders = array();
	}

	public function testModelBindingWithServiceCall() {
		MockLocatableModel::$finders = array();

		Locatable::bind('li3_geo\tests\mocks\MockLocatableModel', array(
			'autoIndex' => false,
			'service' => 'foo'
		));
		$this->assertEqual(array('near', 'within'), array_keys(MockLocatableModel::$finders));
		$this->assertTrue(MockLocatableModel::$finders['near'] instanceof Closure);
		$this->assertTrue(MockLocatableModel::$finders['within'] instanceof Closure);

		$doc = new Document(array(
			'model' => 'li3_geo\tests\mocks\MockLocatableModel',
			'data' => array(
				'address' => '1600 Pennsylvania Ave.',
				'city' => 'Washington',
				'state' => 'DC',
				'zip' => '20071'
			)
		));

		static::$_addresses = array();
		$this->assertEqual(array(84.13, 11.38), Locatable::geocode($doc));
		$this->assertEqual('1600 Pennsylvania Ave. Washington, DC 20071', end(static::$_addresses));

		Locatable::reset();
		$this->assertNull(Locatable::geocode($doc));
	}

	public function testConfig() {
		$result = Locatable::config();
		$this->assertEqual(array('classes' => array('geocoder' => __CLASS__)), $result);

		$override = array('classes' => array('geocoder' => 'Foo'));
		$this->assertEqual($override, Locatable::config($override));
	}

	public function testBindWithInvalidService() {
		$this->expectException("The lookup service `bar` does not exist.");
		Locatable::bind('li3_geo\tests\mocks\MockLocatableModel', array(
			'autoIndex' => false,
			'service' => 'bar'
		));
	}

	/**
	 * @todo Finish implementing indexing support.
	 */
	public function testBindWithAutoIndex() {
		Locatable::bind('li3_geo\tests\mocks\MockLocatableModel', array(
			'service' => 'foo',
			'fields' => array('location.latitude', 'location.longitude')
		));
	}

	public function testBindFilter() {
		$model = 'li3_geo\tests\mocks\MockLocatableModel';
		$coords = array(84.13, 11.38);

		Locatable::bind($model, array(
			'autoIndex' => false,
			'fields' => array('location.latitude', 'location.longitude')
		));
		$result = MockLocatableModel::find('near', $coords);
		$conditions = array('location' => array('$near' => $coords));

		$this->assertEqual('near', $result['type']);
		$this->assertEqual(compact('conditions'), $result['options']);

		$result = MockLocatableModel::find('within', array($coords, $coords));
		$conditions = array('location' => array('$within' => array(
			'$box' => array($coords, $coords)
		)));

		$this->assertEqual('within', $result['type']);
		$this->assertEqual(compact('conditions'), $result['options']);
	}

	public function testFindersWithCountQueries() {
		$model = 'li3_geo\tests\mocks\MockLocatableModel';
		$location = array(84.13, 11.38);
		
		MockLocatableModel::finder('count', function($self, $params) {
			$options = array_diff_key($params['options'], array(
				'conditions' => 1, 'fields' => 1, 'order' => 1, 'limit' => 1, 'page' => 1
			));
			if ($options && !isset($params['options']['conditions'])) {
				return array('conditions' => $options);
			}
			return $params['options'];
		});
		
		Locatable::bind($model, array(
			'autoIndex' => false,
			'fields' => array('location.latitude', 'location.longitude')
		));
		$conditions = compact('location');
		$result = MockLocatableModel::find('count', compact('conditions'));
		$this->assertEqual(compact('conditions'), $result);
	}

	public function testSkipPreExistingConditions() {
		$model = 'li3_geo\tests\mocks\MockLocatableModel';
		$coords = array(84.13, 11.38);

		Locatable::bind($model, array(
			'autoIndex' => false,
			'fields' => array('location.latitude', 'location.longitude')
		));

		$conditions = array('location' => array('$within' => array(
			'$box' => array($coords, $coords)
		)));
		$result = MockLocatableModel::find('within', compact('conditions'));
		$this->assertEqual(compact('conditions'), $result['options']);
	}

	public function testShortHandFormats() {
		$model = 'li3_geo\tests\mocks\MockLocatableModel';
		$coords = array(84.13, 11.38);
		$coords2 = array_map(function($point) { return $point + 5; }, $coords);

		Locatable::bind($model, array(
			'autoIndex' => false,
			'fields' => array('location.latitude', 'location.longitude')
		));
		$result = $model::find('near', $coords);
		$conditions = array('location' => array('$near' => $coords));
		$this->assertEqual(compact('conditions'), $result['options']);

		$result = $model::find('within', array($coords, $coords2));
		$conditions = array('location' => array(
			'$within' => array('$box' => array($coords2, $coords))
		));
		$this->assertEqual(compact('conditions'), $result['options']);
	}
}

?>