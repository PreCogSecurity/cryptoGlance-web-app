<?php

namespace CryptoGlance\Tests;

use PHPUnit\Framework\TestCase;

class FunctionsTest extends TestCase {

    public function testFormatHashrateScalesUnits() {
        $this->assertSame('1 KH/s', formatHashrate(1000));
        $this->assertSame('1.5 MH/s', formatHashrate(1500000));
        $this->assertSame('2 GH/s', formatHashrate(2000000000));
    }

    public function testFormatHashrateHandlesZero() {
        $this->assertSame('0 H/s', formatHashrate(0));
    }

    public function testFormatCapacityUsesByteSuffix() {
        $this->assertSame('1 KB', formatCapacity(1000));
    }

    public function testFormatTimeElapsedZeroDoesNotWarn() {
        // Regression: zero elapsed time used to leave $format undefined.
        $this->assertSame('0H 0M 0S', formatTimeElapsed(0));
    }

    public function testFormatTimeElapsedMinutesAndHours() {
        $this->assertSame('0H 1M 5S', formatTimeElapsed(65));
        $this->assertSame('1H 1M 5S', formatTimeElapsed(3665));
    }

    public function testFormatTimeElapsedDays() {
        $this->assertSame('1D 0H 0M', formatTimeElapsed(86400));
    }

    public function testPercentNeverDividesByZero() {
        $this->assertSame(0.0, cg_percent(0, 0));
        $this->assertSame(0.0, cg_percent(5, 0));
        $this->assertSame(25.0, cg_percent(1, 4));
        $this->assertSame(33.33, cg_percent(1, 3));
    }

    public function testAjaxTokenNormalisationStripsUnsafeCharacters() {
        $this->assertSame('rigs', cg_ajax_normalize_token('Rigs'));
        $this->assertSame('mobile-miner', cg_ajax_normalize_token('Mobile-Miner'));
        $this->assertSame('etcpasswd', cg_ajax_normalize_token('../../etc/passwd'));
        $this->assertSame('', cg_ajax_normalize_token(''));
    }

    public function testAjaxStudlyAction() {
        $this->assertSame('SwitchPool', cg_ajax_studly_action('switch-pool'));
        $this->assertSame('GetUpdate', cg_ajax_studly_action('get-update'));
    }

    public function testCrossSiteDetectionUsesFetchMetadata() {
        unset($_SERVER['HTTP_ORIGIN']);
        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
        $this->assertFalse(cg_request_is_cross_site());

        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
        $this->assertTrue(cg_request_is_cross_site());

        unset($_SERVER['HTTP_SEC_FETCH_SITE']);
    }

    public function testCrossSiteDetectionFallsBackToOrigin() {
        unset($_SERVER['HTTP_SEC_FETCH_SITE']);
        $_SERVER['HTTP_HOST'] = 'miner.local:8080';
        $_SERVER['HTTP_ORIGIN'] = 'http://miner.local:8080';
        $this->assertFalse(cg_request_is_cross_site());

        $_SERVER['HTTP_ORIGIN'] = 'http://evil.example';
        $this->assertTrue(cg_request_is_cross_site());

        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_HOST']);
    }
}
