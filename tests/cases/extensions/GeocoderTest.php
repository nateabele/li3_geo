<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\tests\cases\extensions;

use li3_geo\extensions\Geocoder;
use li3_geo\tests\mocks\MockService;

class GeocoderTest extends \lithium\test\Unit {

	public $calls = array();

	public function setUp() {
		Geocoder::__init();
	}

	public function testInvalidService() {
		$this->expectException("The lookup service `foo` does not exist.");
		Geocoder::coords('foo', '1600 Pennsylvania Ave. Washington DC');
	}

	public function testGeocodeLookupAndReverse() {
		$this->skipIf(dns_check_record("google.com") === false, "No internet connection.");
		$addr = '1600 Pennsylvania Avenue Northwest, Washington, DC';

		foreach (array('google', 'osm') as $service) {
			$location = Geocoder::coords($service, $addr);
			$expected = array('latitude' => 38, 'longitude' => -77);
			$this->assertEqual($expected, array_map('intval', $location));

			$address = Geocoder::address($service, $location['latitude'], $location['longitude']);
			$this->assertTrue(is_array($address));
		}
	}

	public function testCreateService() {
		Geocoder::reset();
		Geocoder::services('foo', array('host' => 'http://localhost', 'parser' => null));
		$expected = array('osm', 'google', 'yahoo', 'foo');
		$this->assertEqual($expected, array_keys(Geocoder::services()));
	}

	public function testExifConversion() {
		$exif = array(
			'GPSLatitudeRef' => 'N',
			'GPSLatitude' => array('40/1', '4586/100', '0/1'),
			'GPSLongitudeRef' => 'W',
			'GPSLongitude' => array('73/1', '5841/100', '0/1')
		);
		$data = Geocoder::exifCoords($exif);
		$expected = array('latitude' => 40.7643, 'longitude' => -73.9735);
		$this->assertEqual($expected, $data);

		$this->assertEqual(array(), Geocoder::exifCoords(array()));
	}

	public function testContext() {
		Geocoder::context(array('host' => 'http://foo/'));
		$this->assertEqual('http://foo/', Geocoder::context('host'));

		Geocoder::__init();
		$this->assertNull(Geocoder::context('host'));
	}

	public function testCustomService() {
		$service = new MockService();
		$service->binding =& $this;

		Geocoder::config(array('classes' => array('service' => &$service)));

		Geocoder::services('foo', array(
			'host' => 'http://foo',
			'coords' => '/bar/{:address}?key={:key}',
			'parser' => array('coords' => function($data) {
				return array_map('floatval', explode(', ', $data));
			})
		));

		$this->assertEqual(array(), Geocoder::coords('foo', 'A location'));
		$this->assertEqual('/bar/A%20location?key=', end($this->calls));

		Geocoder::context(array(
			'host' => 'http://foo',
			'keys' => array('foo' => array('http://foo' => 'theKey123'))
		));

		$this->assertEqual(array(84.13, 11.38), Geocoder::coords('foo', "A location"));
		$this->assertEqual('/bar/A%20location?key=theKey123', end($this->calls));

		Geocoder::services('foo', array(
			'host' => 'http://foo',
			'coords' => '/bar/{:address}?key={:key}',
			'parser' => array('coords' => function($data) {
				return array_map('floatval', explode(', ', $data));
			})
		));
	}

	public function testDistanceCalculation() {
		$a = array(40.625316, -74.025972);
		$b = array(40.755473, -73.980855);

		$distance = Geocoder::distance($a, $b);
		$this->assertEqual(9.298, round($distance, 3));

		$distance = Geocoder::distance($a, $b, 'F');
		$this->assertEqual(49093, round($distance));

		$b = array('latitude' => 44.7525199, 'longitude' => -93.244557);
		$distance = Geocoder::distance($a, $b, 'K');
		$this->assertEqual(1632, round($distance));
	}
}

?>