<?php

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