<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\data;

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
class Geocoder extends \lithium\core\Adaptable {

	/**
	 * Provides a mapping of continents to their respective countries.
	 *
	 * @var array
	 */
	protected static $_continents = array(
		'Africa' => array(
			'Algeria', 'Abyssinia', 'Angola', 'Benin', 'Botswana', 'Burkina Faso', 'Burundi',
			'Cameroon', 'Cape Verde', 'Central African Republic', 'Chad', 'Comoros',
			'Republic of the Congo', 'Democratic Republic of the Congo', 'Côte d\'Ivoire',
			'Djibouti', 'Egypt', 'Equatorial Guinea', 'Eritrea', 'Ethiopia', 'Gabon', 'The Gambia',
			'Ghana', 'Guinea', 'Guinea-Bissau', 'Kenya', 'Lesotho', 'Liberia', 'Libya',
			'Madagascar', 'Malawi', 'Mali', 'Mauritania', 'Mauritius', 'Morocco', 'Mozambique',
			'Namibia', 'Niger', 'Nigeria', 'Rwanda', 'São Tomé and Príncipe', 'Senegal',
			'Seychelles', 'Sierra Leone', 'Somalia', 'South Africa', 'South Sudan', 'Sudan',
			'Swaziland', 'Tanzania', 'Togo', 'Tunisia', 'Uganda', 'Western Sahara', 'Zambia',
			'Zaire', 'Zimbabwe'
		),
		'Asia' => array(
			'Afghanistan', 'Armenia', 'Azerbaijan', 'Bahrain', 'Bangladesh', 'Bhutan', 'Brunei',
			'Cambodia', 'China', 'Taiwan', 'East Timor', 'India', 'Indonesia', 'Iran', 'Iraq',
			'Israel', 'Palestine', 'Japan', 'Jordan', 'Kazakhstan', 'Kuwait', 'Kyrgyzstan',
			'Laos', 'Lebanon', 'Malaysia', 'Maldives', 'Mongolia', 'Myanmar', 'Nepal',
			'North Korea', 'Oman', 'Pakistan', 'Philippines', 'Qatar', 'Russia', 'Saudi Arabia',
			'Singapore', 'South Korea', 'Sri Lanka', 'Syria', 'Tajikistan', 'Thailand', 'Tibet',
			'Turkey', 'Turkmenistan', 'United Arab Emirates', 'Uzbekistan', 'Vietnam', 'Yemen'
		),
		'Europe' => array(
			'Albania', 'Andorra', 'Austria', 'Belarus', 'Belgium', 'Bosnia and Herzegovina',
			'Bulgaria', 'Croatia', 'Cyprus', 'Czech Republic', 'Denmark', 'Estonia', 'Finland',
			'France', 'Georgia', 'Germany', 'Greece', 'Hungary', 'Iceland', 'Republic of Ireland',
			'Italy', 'Latvia', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Republic of Macedonia',
			'Malta', 'Moldova', 'Monaco', 'Montenegro', 'Netherlands', 'Norway', 'Poland',
			'Portugal', 'Romania', 'San Marino', 'Serbia', 'Slovakia', 'Slovenia', 'Spain',
			'Sweden', 'Switzerland', 'Turkey', 'Ukraine', 'United Kingdom', 'Vatican City'
		),
		'North America' => array(
			'Antigua and Barbuda', 'Bahamas', 'Barbados', 'Belize', 'Canada', 'Cayman Islands',
			'Costa Rica', 'Cuba', 'Dominica', 'Dominican Republic', 'El Salvador', 'Greenland',
			'Grenada', 'Guatemala', 'Haiti', 'Honduras', 'Jamaica', 'Mexico', 'Nicaragua', 'Panama',
			'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines',
			'Trinidad and Tobago', 'United States', 'Turks and Caicos', 'American Samoa',
			'Cook Islands', 'French Polynesia', 'Niue', 'Pitcairn Islands', 'Samoa', 'Tokelau',
			'Tonga', 'Tuvalu', 'Wallis and Futuna Islands'
		),
		'South America' => array(
			'Argentina', 'Bolivia', 'Brazil', 'Chile', 'Colombia', 'Ecuador', 'French Guiana',
			'Guyana', 'Paraguay', 'Peru', 'Suriname', 'Uruguay', 'Venezuela'
		),
		'Australia' => array(
			'Australia', 'New Zealand', 'Christmas Island', 'Cocos Islands', 'Fiji'
		)
	);

	/**
	 * Lists each variation of each place name that may be represented by multiple names. In methods
	 * that convert between names, the first element of each name array will be used as the
	 * canonical name.
	 *
	 * @var array
	 */
	protected static $_alternates = array(
		array('United States', 'United States of America', 'USA'),
		array('Ireland', 'Republic of Ireland')
	);

	/**
	 * A dot-separated path for use by `Libraries::locate()`. Used to look up the correct type of
	 * adapters for this class.
	 *
	 * @var string
	 */
	protected static $_adapters = 'adapter.geo';

	/**
	 * The list of classes which `Geocoder` depends on.
	 *
	 * @var array
	 */
	protected static $_classes = array(
		'service' => 'lithium\net\http\Service'
	);

	/**
	 * Index of geo-data lookup services.
	 *
	 * @var array
	 */
	protected static $_configurations = array(
		'osm' => array('adapter' => 'OpenStreetMaps'),
		'google' => array('adapter' => 'Google')
	);

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
	 * Accepts place names to normalize, when a name has multiple variants. For example, replaces
	 * `'United States of America'` and `'USA'` with `'United States'`.
	 *
	 * @param string $name A place name to normalize.
	 * @return string Returns the place name that has been designated as canonical among a list of
	 *         variants.
	 */
	public static function normalizePlace($name = null) {
		foreach (static::$_alternates as $names) {
			if (in_array($name, $names)) {
				return reset($names);
			}
		}
		return $name;
	}

	/**
	 * Returns a nested array of continents and countries, an array of countries for a particular
	 * continent, or finds the continent name of a country.
	 *
	 * @param string $name Optional. The name of a continent or country.
	 * @return mixed Returns a string or array, depending on the value of `$name`.
	 */
	public static function continents($name = null) {
		if (!$name) {
			return static::$_continents;
		}
		if (isset(static::$_continents[$name])) {
			return static::$_continents[$name];
		}
		foreach (static::$_continents as $continent => $countries) {
			if (in_array($name, $countries)) {
				return $continent;
			}
		}
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
		$keys = array_combine($expectedKeys, $expectedKeys);
		$result = array();

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
	 * Get latitude/longitude points for given address, or get the address information for a set of
	 * coordinates.
	 *
	 * @param string $name The name of the service configuration to use, i.e. `'google'` or `'osm'`.
	 * @param array $location The information associated with the location you want data about.
	 *              Possible keys are:
	 *              - `latitude`/`longitude` _float_: If geo-coding to an address, include the
	 *                latitude and longitude coodinates you wish to search for.
	 *              - `address` _string_: If searching for a location by address, you can specify
	 *                the full address as a string.
	 * @return array Latitude and longitude data, or `false` on failure.
	 */
	public static function find($name, array $location = array()) {
		$defaults = array('latitude' => null, 'longitude' => null, 'address' => null);
		$location += $defaults;

		return static::_filter(__FUNCTION__, compact('name', 'location'), function($self, $params) {
			$name  = $params['name'];
			$location = $params['location'];
			$adapter  = $self::adapter($name);

			list($scheme, $host) = explode('://', $adapter->service('host'), 2);
			$connection = $self::invokeMethod('_instance', array(
				'service', compact('scheme', 'host')
			));
			$service = null;

			switch (true) {
				case ($location['latitude'] && $location['longitude']):
					$service = 'address';
				break;
				case ($location['address']):
					$service = 'coords';
				break;
			}
			if (!$service) {
				return;
			}
			return $self::adapter($name)->query($connection, $service, $location);
		});
	}

	/**
	 * Calculates the distance between to geographic coordinates using the circle distance formula.
	 *
	 * @see li3_geo\data\Geocoder::$_units
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

	/**
	 * Returns a list of available geocoding services
	 *
	 * @param string $service (optional) if included, returns a boolean on
	 *     whether or not the selected service exists
	 * @return array
	 */
	public static function services($service = null) {
		$services = array_keys(static::$_configurations);
		if ($service !== null) {
			return in_array($service, $services);
		}
		return $services;
	}
}

?>