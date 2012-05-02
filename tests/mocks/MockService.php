<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\tests\mocks;

class MockService extends \lithium\core\Object {

	public $binding;

	/**
	 * Service implementation method so that this class can be used as a mock.
	 *
	 * @param string $url 
	 * @return void
	 */
	public function get($url) {
		if ($this->binding) {
			$this->binding->calls[] = $url;
		}
		return preg_match('/theKey123/', $url) ? "84.13, 11.38" : null;
	}
}

?>