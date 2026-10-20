<?php

namespace CryptoGlance\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Test double: skips the network fetch performed by the real constructor and
 * lets a test seed the protected miner state directly.
 */
class TestableCgminer extends \Miners_Cgminer {

    public function __construct() {
        // Intentionally does not call parent::__construct() (no socket access).
    }

    public function seedSummary(array $summary) {
        $this->_summary = $summary;
    }

    public function seedDevices(array $devs, array $devStatus) {
        $this->_devs = $devs;
        $this->_devStatus = $devStatus;
    }

    public function seedSettings(array $settings) {
        $this->_settings = array_merge($this->_settings, $settings);
    }
}

class CgminerSummaryTest extends TestCase {

    private function miner() {
        $miner = new TestableCgminer();
        $miner->seedSettings(array(
            'algorithm' => 'scrypt',
            'hwErrors' => array('enabled' => 0),
            'temps' => array('enabled' => 0),
        ));

        return $miner;
    }

    public function testSummaryWithNoSharesDoesNotDivideByZero() {
        $miner = $this->miner();
        $miner->seedSummary(array(
            'Accepted' => 0,
            'Rejected' => 0,
            'Stale' => 0,
            'Hardware Errors' => 0,
            'MHS av' => 0,
            'Found Blocks' => 0,
            'Work Utility' => 0,
            'Best Share' => 0,
        ));

        $summary = $miner->summary();

        $this->assertSame(0.0, $summary['accepted']['percent']);
        $this->assertSame(0.0, $summary['rejected']['percent']);
        $this->assertSame(0.0, $summary['stale']['percent']);
        $this->assertSame(0.0, $summary['hw_errors']['percent']);
    }

    public function testSummaryComputesSharePercentages() {
        $miner = $this->miner();
        $miner->seedSummary(array(
            'Accepted' => 8,
            'Rejected' => 2,
            'Stale' => 0,
            'Hardware Errors' => 0,
            'MHS av' => 1000,
            'Found Blocks' => 1,
            'Work Utility' => 12.5,
            'Best Share' => 42,
        ));

        $summary = $miner->summary();

        $this->assertSame(80.0, $summary['accepted']['percent']);
        $this->assertSame(20.0, $summary['rejected']['percent']);
        $this->assertSame(1000, $summary['hashrate_avg']);
        $this->assertSame('12.5/m', $summary['work_utility']);
    }

    public function testSummaryFallsBackToGhsWhenMhsMissing() {
        $miner = $this->miner();
        $miner->seedSummary(array(
            'Accepted' => 1,
            'Rejected' => 0,
            'Stale' => 0,
            'Hardware Errors' => 0,
            'GHS av' => 2,
        ));

        $summary = $miner->summary();

        $this->assertSame(2000, $summary['hashrate_avg']);
    }

    public function testDevicesWithNoSharesDoesNotDivideByZero() {
        $miner = $this->miner();
        $miner->seedDevices(
            array(
                array(
                    'GPU' => 0,
                    'Enabled' => 'Y',
                    'Status' => 'Alive',
                    'MHS av' => 0,
                    'MHS 5s' => 0,
                    'MHS 20s' => 0,
                    'Intensity' => 0,
                    'Temperature' => 0,
                    'Fan Speed' => 0,
                    'Fan Percent' => 0,
                    'GPU Clock' => 0,
                    'Memory Clock' => 0,
                    'GPU Voltage' => 0,
                    'Powertune' => 0,
                    'Accepted' => 0,
                    'Rejected' => 0,
                    'Hardware Errors' => 0,
                    'Utility' => 0,
                ),
            ),
            array(0 => array('colour' => 'green', 'icon' => 'cpu-processor'))
        );

        $devices = $miner->devices();

        $this->assertCount(1, $devices);
        $this->assertSame(0.0, $devices[0]['accepted']['percent']);
        $this->assertSame(0.0, $devices[0]['rejected']['percent']);
    }
}
