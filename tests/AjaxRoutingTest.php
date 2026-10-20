<?php

namespace CryptoGlance\Tests;

use PHPUnit\Framework\TestCase;

class AjaxRoutingTest extends TestCase {

    public function testKnownPostTargetResolves() {
        $this->assertSame(array('Rigs', 'Restart'), cg_resolve_ajax_target('rigs', 'restart', true));
        $this->assertSame(array('Rigs', 'SwitchPool'), cg_resolve_ajax_target('rigs', 'switch-pool', true));
        $this->assertSame(array('Wallets', 'AddAddress'), cg_resolve_ajax_target('wallets', 'add-address', true));
        $this->assertSame(array('MobileMiner', 'GetUpdate'), cg_resolve_ajax_target('mobile-miner', 'get-update', true));
    }

    public function testGetTargetsUseGetPrefix() {
        $this->assertSame(array('Rigs', 'GetUpdate'), cg_resolve_ajax_target('rigs', 'update', false));
        $this->assertSame(array('Pools', 'GetConfig'), cg_resolve_ajax_target('pools', 'config', false));
    }

    public function testUnknownTypeIsRejected() {
        $this->assertNull(cg_resolve_ajax_target('login', 'login', true));
        $this->assertNull(cg_resolve_ajax_target('../../etc/passwd', 'get', true));
        $this->assertNull(cg_resolve_ajax_target('', 'get', true));
    }

    public function testUnknownActionIsRejected() {
        $this->assertNull(cg_resolve_ajax_target('rigs', 'delete-everything', true));
        $this->assertNull(cg_resolve_ajax_target('rigs', 'write', true));
        $this->assertNull(cg_resolve_ajax_target('wallets', 'restart', true));
    }

    public function testGetCannotReachWriteActions() {
        // A GET must not be able to invoke a state-changing method.
        $this->assertNull(cg_resolve_ajax_target('rigs', 'remove', false));
        $this->assertNull(cg_resolve_ajax_target('rigs', 'create', false));
    }

    public function testMethodMustExistOnClass() {
        $this->assertNull(cg_resolve_ajax_target('pools', 'reset-stats', true));
    }
}
