<?php

namespace CryptoGlance\Tests;

use PHPUnit\Framework\TestCase;

class TestableConfigPools extends \Config_Pools {

    public function __construct() {
        // Skip parent: no config file access needed for these tests.
    }

    public function addPool(array $pool) {
        return $this->add($pool);
    }

    public function objectCount() {
        return count($this->_objs);
    }
}

class TestableRigs extends \Rigs {

    public function __construct() {
        // Skip parent: simulate a fresh install with no rigs configured.
    }
}

class TestableWallets extends \Wallets {

    public function __construct(array $wallets) {
        // Skip parent and inject wallet config directly.
        $this->_objs = $wallets;
    }
}

class HardeningTest extends TestCase {

    public function testPoolTypeAllowListRejectsUnknownClass() {
        $pools = new TestableConfigPools();

        $this->assertFalse($pools->addPool(array('type' => 'not-a-real-pool')));
        $this->assertFalse($pools->addPool(array('type' => '../config/abstract')));
        $this->assertSame(0, $pools->objectCount());
    }

    public function testPoolTypeAllowListAcceptsShippedPool() {
        $pools = new TestableConfigPools();

        $this->assertNull($pools->addPool(array('type' => 'slush', 'apikey' => 'x')));
        $this->assertSame(1, $pools->objectCount());
    }

    public function testRigActionsFailGracefullyWithoutConfiguredRigs() {
        $rigs = new TestableRigs();

        $this->assertSame('No rigs are configured yet.', $rigs->removePool());
        $this->assertSame('No rigs are configured yet.', $rigs->restart());
        $this->assertSame(array(), $rigs->getUpdate());
    }

    public function testWalletUpdateSkipsUnsupportedCurrency() {
        $wallets = new TestableWallets(array(
            array('label' => 'Old', 'currency' => 'vertcoin', 'fiat' => 'USD', 'addresses' => array()),
        ));

        $this->assertSame(array(), $wallets->getUpdate());
    }
}
