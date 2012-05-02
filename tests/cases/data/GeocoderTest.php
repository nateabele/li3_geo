<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\tests\cases\data;

use li3_geo\data\Geocoder;
use li3_geo\tests\mocks\MockService;

class GeocoderTest extends \lithium\test\Unit {

	public function testGeocodeLookupAndReverse() {
		$this->skipIf(dns_check_record("google.com") === false, "No internet connection.");
		$address = '1600 Pennsylvania Avenue Northwest, Washington, DC';

		foreach (array(/*'google', */'osm') as $service) {
			$location = Geocoder::find($service, compact('address'));
			$expected = array('latitude' => 38, 'longitude' => -77);
			$this->assertEqual($expected, array_map('intval', $location->coordinates()));

			$address = Geocoder::find($service, $location->coordinates());
			$this->assertTrue(is_object($address));
		}
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