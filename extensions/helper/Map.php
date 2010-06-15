<?php

namespace li3_geo\extensions\helper;

class Map extends \lithium\template\Helper {

	protected $_mapsSettings = array(
		'google' => array('version' => '2', 'sensor' => false)
	);

	public function embed(array $options = array()) {
		$defaults = array('service' => 'google', 'load' => true);
		$options += $defaults;
	}

	public function load(array $options = array()) {
		$defaults = array('service' => 'google', 'inline' => false);
		$options += $defaults;
	}
}

?>