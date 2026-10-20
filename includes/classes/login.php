<?php

require_once('filehandler.php');

class Login {

    /** Failed attempts allowed within the lockout window. */
    const MAX_FAILURES = 5;

    /** Lockout window in seconds after too many failed attempts. */
    const LOCKOUT_SECONDS = 900;

    protected $_fh = null;

    public function __construct() {
        $this->_fh = new FileHandler('configs/account.json');
    }

    /**
     * Authenticate (or register on first use).
     *
     * @param string $username
     * @param string $password Plaintext, as typed by the user.
     * @return bool|string True on success, false on bad credentials, or the
     *                     string 'throttled' when the account is locked out.
     */
    public function login($username, $password) {
        $login = json_decode($this->_fh->read(), true);

        if (empty($login)) {
            return $this->register($username, $password);
        }

        if ($this->isThrottled()) {
            return 'throttled';
        }

        $stored = isset($login['password']) ? (string) $login['password'] : '';
        $usernameOk = strcasecmp((string) $username, isset($login['username']) ? (string) $login['username'] : "\0") === 0;
        $passwordOk = $this->verifyPassword((string) $password, $stored);

        if ($usernameOk && $passwordOk) {
            $this->clearFailures();
            $this->upgradeHashIfNeeded($login, (string) $password);
            return true;
        }

        $this->recordFailure();
        return false;
    }

    /**
     * Verify a plaintext password against the stored hash. Accepts bcrypt
     * (password_hash) and, for upgrades only, legacy SHA-512 hex digests.
     */
    private function verifyPassword($password, $stored) {
        if ($stored === '') {
            return false;
        }
        if (password_verify($password, $stored)) {
            return true;
        }
        // Legacy storage: unsalted SHA-512 hex. Still constant-time compared,
        // and replaced with bcrypt on the next successful login.
        if (preg_match('/^[0-9a-f]{128}$/i', $stored)
            && hash_equals($stored, hash('sha512', $password))) {
            return true;
        }
        return false;
    }

    /**
     * Re-store legacy SHA-512 digests as bcrypt after a successful login.
     */
    private function upgradeHashIfNeeded(array $login, $password) {
        if (empty($login['password']) || !password_needs_rehash($login['password'], PASSWORD_DEFAULT)) {
            return; // Already a current password_hash() digest.
        }
        $login['password'] = password_hash($password, PASSWORD_DEFAULT);
        $this->_fh->write(json_encode($login));
    }

    private function register($username, $password) {
        if (!empty($username) && !empty($password)) {
            $data = array(
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            );

            $this->_fh->write(json_encode($data));
            return true;
        }

        return false;
    }

    /*
     * Brute-force throttling, tracked per session so an attacker sharing the
     * deployment cannot try unlimited passwords against the single account.
     */

    private function isThrottled() {
        if (empty($_SESSION['cg_login_failures'])) {
            return false;
        }
        if ((int) $_SESSION['cg_login_failures'] < self::MAX_FAILURES) {
            return false;
        }
        $lastFailure = isset($_SESSION['cg_login_last_failure']) ? (int) $_SESSION['cg_login_last_failure'] : 0;
        if (time() - $lastFailure < self::LOCKOUT_SECONDS) {
            return true;
        }
        $this->clearFailures(); // Lockout window expired.
        return false;
    }

    private function recordFailure() {
        $_SESSION['cg_login_failures'] = (isset($_SESSION['cg_login_failures']) ? (int) $_SESSION['cg_login_failures'] : 0) + 1;
        $_SESSION['cg_login_last_failure'] = time();
    }

    private function clearFailures() {
        unset($_SESSION['cg_login_failures'], $_SESSION['cg_login_last_failure']);
    }

    public function firstLogin() {
        return $this->_fh->fileExists();
    }

}
