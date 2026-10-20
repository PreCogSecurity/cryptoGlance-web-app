<?php

namespace CryptoGlance\Tests;

use PHPUnit\Framework\TestCase;

class TestableConfigRigs extends \Config_Rigs {

    public function __construct() {
        // Skip parent: no config file access needed for validation tests.
    }

    public function validate($dataType, array $data) {
        return $this->postValidate($dataType, $data);
    }
}

class ConfigRigsValidationTest extends TestCase {

    private function validator() {
        return new TestableConfigRigs();
    }

    public function testDetailsRequireAddressAndPort() {
        $this->assertIsString($this->validator()->validate('details', array('port' => 4028)));
        $this->assertIsString($this->validator()->validate('details', array('ip_address' => '10.0.0.5')));
        $this->assertTrue($this->validator()->validate('details', array('ip_address' => '10.0.0.5', 'port' => 4028)));
    }

    public function testThresholdsRequireWarningBelowDanger() {
        $data = array(
            'temps' => array('enabled' => 'on', 'warning' => 90, 'danger' => 80),
            'hwErrors' => array('enabled' => 0),
        );
        $this->assertIsString($this->validator()->validate('thresholds', $data));

        $data['temps'] = array('enabled' => 'on', 'warning' => 70, 'danger' => 80);
        $this->assertTrue($this->validator()->validate('thresholds', $data));
    }

    public function testThresholdsRequireHardwareErrorValues() {
        $data = array(
            'temps' => array('enabled' => 0),
            'hwErrors' => array('enabled' => 'on', 'type' => 'percent', 'warning' => array('percent' => ''), 'danger' => array('percent' => '')),
        );
        $this->assertIsString($this->validator()->validate('thresholds', $data));
    }

    public function testPoolRequiresUrlUserAndPassword() {
        $this->assertIsString($this->validator()->validate('pools', array('user' => 'u', 'password' => 'p')));
        $this->assertIsString($this->validator()->validate('pools', array('url' => 'stratum+tcp://pool:3333', 'password' => 'p')));
        $this->assertTrue($this->validator()->validate('pools', array(
            'url' => 'stratum+tcp://pool:3333',
            'user' => 'worker',
            'password' => 'x',
        )));
    }
}
