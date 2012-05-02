<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions\data\behavior\locatable\adapter;

class MongoDb extends \lithium\core\Object {

	public function index($connection, array $meta, array $options = array()) {
		$index = array($base => '2d') + $options['include'];
		unset($options['include']);
		$source = $meta['source'];
		return $connection->connection->{$source}->ensureIndex($index, $options);
	}
}

?>