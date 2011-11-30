<?php

namespace li3_geo\extensions\data\source\database\adapter;

class MySql extends \lithium\data\source\database\adapter\MySql {

    /**
     * Converts an array of Lat. and Lon. coords to the
     * PointFromText MySQL syntax if column type is 'point'
     */
	public function value($value, array $schema = array()) {
		if(isset($schema['type']) && $schema['type'] == 'point') {
			if(is_array($value)) {
				return "PointFromText('POINT({$value['latitude']} {$value['longitude']})')";
			}
		}
        return parent::value($value, $schema);
    }

    /**
     * Adds detection for 'point' column type
     */
    protected function _column($real) {
        $type = 'point';

        if($real == $type) {
            return compact('type');
        }
        return parent::_column($real);
    }
}


?>