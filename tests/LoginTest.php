<?php

namespace CryptoGlance\Tests;

use PHPUnit\Framework\TestCase;

class LoginTest extends TestCase {

    private $accountFile;

    protected function setUp(): void {
        $_SESSION = array();
        $this->accountFile = DATA_PATH . 'configs' . DIRECTORY_SEPARATOR . 'account.json';
        if (is_file($this->accountFile)) {
            unlink($this->accountFile);
        }
    }

    protected function tearDown(): void {
        if (is_file($this->accountFile)) {
            unlink($this->accountFile);
        }
        $_SESSION = array();
    }

    public function testFirstLoginRegistersAccountWithPasswordHash() {
        $login = new \Login();

        $this->assertTrue($login->login('admin', 'correct horse battery staple'));

        $stored = json_decode(file_get_contents($this->accountFile), true);
        $this->assertSame('admin', $stored['username']);
        $this->assertNotSame('correct horse battery staple', $stored['password']);
        $this->assertTrue(password_verify('correct horse battery staple', $stored['password']));
    }

    public function testLoginRejectsWrongPassword() {
        $login = new \Login();
        $login->login('admin', 'secret-password');

        $this->assertFalse($login->login('admin', 'wrong-password'));
    }

    public function testLoginAcceptsCorrectPassword() {
        $login = new \Login();
        $login->login('admin', 'secret-password');

        $this->assertTrue($login->login('admin', 'secret-password'));
    }

    public function testLegacySha512HashIsAcceptedAndUpgraded() {
        if (!is_dir(dirname($this->accountFile))) {
            mkdir(dirname($this->accountFile), 0775, true);
        }
        file_put_contents($this->accountFile, json_encode(array(
            'username' => 'legacy',
            'password' => hash('sha512', 'old-password'),
        )));

        $login = new \Login();
        $this->assertTrue($login->login('legacy', 'old-password'));

        $stored = json_decode(file_get_contents($this->accountFile), true);
        $this->assertTrue(password_verify('old-password', $stored['password']));
    }

    public function testRepeatedFailuresAreThrottled() {
        $login = new \Login();
        $login->login('admin', 'secret-password');

        for ($i = 0; $i < \Login::MAX_FAILURES; $i++) {
            $this->assertFalse($login->login('admin', 'bad-password'));
        }

        $this->assertSame('throttled', $login->login('admin', 'secret-password'));
    }
}
