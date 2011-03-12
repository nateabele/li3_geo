<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */


use lithium\action\Dispatcher;
use li3_geo\extensions\Geocoder;

Dispatcher::applyFilter('run', function($self, $params, $chain) {
	Geocoder::context(array(
		'host' => $params['request']->env('HTTP_HOST')
	));
	return $chain->next($self, $params, $chain);
});

?>