<?php

namespace li3_geo\tests\cases\extensions\data\source\database\adapter;

use li3_geo\extensions\data\source\database\adapter\MySql;

class MySqlTest extends \lithium\test\Unit {

    public $db = null;

    public function setUp() {
        $this->db = new MySql();

    }

    /**
     * Tests that an array of Latitude and Longitude coords
     * is converted to the PointFromText MySQL syntax
     */
    public function testValueByIntrospect() {
        $latitude  = 51.4;
        $longitude = -3.1;
        $type = 'point';

        $expected = "PointFromText('POINT({$latitude} {$longitude})')";

        $result = $this->db->value(
            compact('latitude', 'longitude'),
            compact('type')
        );

        $this->assertEqual($expected, $result);
    }

    /**
     * Tests that the 'point' column type is detected
     */
    public function testColumnAbstraction() {
        $type = 'point';

        $result = $this->db->invokeMethod('_column', array('point'));

        $this->assertIdentical(compact('type'), $result);
    }
}

?>