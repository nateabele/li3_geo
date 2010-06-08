<?php

use lithium\action\Dispatcher;
use li3_geo\extensions\Geocoder;

Dispatcher::applyFilter('run', function($self, $params, $chain) {
	Geocoder::context(array(
		'host' => $params['request']->env('HTTP_HOST')
	));
	return $chain->next($self, $params, $chain);
});

?>