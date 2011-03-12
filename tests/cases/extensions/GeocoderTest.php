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

	public function testInvalidService() {
		$this->expectException("The lookup service 'foo' does not exist.");
		Geocoder::find('foo', '1600 Pennsylvania Ave. Washington DC');
	}

	public function testGeocodeLookup() {
		$location = Geocoder::find('google', '1600 Pennsylvania Avenue Northwest, Washington, DC');
		$expected = array('latitude' => 38.8976463, 'longitude' => -77.036562);
		$this->assertEqual($expected, $location);
	}

	public function testCreateService() {
		Geocoder::__init();
		Geocoder::services('foo', array('url' => 'http://localhost', 'parser' => null));
		$this->assertEqual(array('google', 'yahoo', 'foo'), array_keys(Geocoder::services()));
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
	}
}

?>