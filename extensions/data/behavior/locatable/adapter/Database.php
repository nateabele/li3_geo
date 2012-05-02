<?php
/**
 * li3_geo: Geocoding and location utilities for Lithium, the most rad framework for PHP
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_geo\extensions\data\behavior\locatable\adapter;

class Database extends \lithium\core\Object {

	/**
	 * @todo Implement me.
	 */
	public function index($connection, array $meta, array $options = array()) {
	}

	/**
	 * Generates an SQL-formatted calculation that finds the geographic distance of the latitude and
	 * longitude data of a database record, compared to a fixed point.
	 *
	 * @param float $y The latitude value of the point that the record columns will be compared to.
	 * @param float $x The longitude value of the point that the record columns will be compared to.
	 * @param string $xField An SQL fragment containing the column or calculated value representing
	 *               the longitude value of a record.
	 * @param string $yField An SQL fragment containing the column or calculated value representing
	 *               the latitude value of a record.
	 * @return string Returns the SQL conditions for performing the Great-circle distance formula
	 *                against two database columns, compared to a point defined by `$x` and `$y`.
	 */
	protected static function _distanceQuery($y, $x, $xField, $yField) {
		$sql  = "(3958 * 3.1415926 * SQRT(({$yField} - {$y}) * ({$yField} - {$y}) + ";
		$sql .= "COS({$yField} / 57.29578) * COS({$y} / 57.29578) * ({$xField} - {$x}) * ";
		return $sql . "({$xField} - {$x})) / 180)";
	}
}

?>