<?php
/**
 * Minimal environment configuration loader.
 *
 * Loads KEY=VALUE pairs from the .env file at the project root (if present)
 * into the process environment without overriding variables that are already
 * set by the SAPI, orchestrator, or shell. No third-party dependency.
 *
 * Real environment variables always win over .env contents, so the same file
 * works for local development and for Docker/Kubernetes deployments where
 * secrets are injected out-of-band.
 */

if (!function_exists('cg_load_env')) {

    /**
     * Parse a dotenv-style file and export entries via putenv()/$_ENV.
     * Existing environment variables are never overwritten.
     *
     * @param string $path Absolute path to the .env file.
     * @return int Number of variables imported.
     */
    function cg_load_env($path) {
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            return 0;
        }

        $imported = 0;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return 0;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            // Support "export KEY=value" as well as "KEY=value".
            if (strncmp($line, 'export ', 7) === 0) {
                $line = trim(substr($line, 7));
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $name = trim(substr($line, 0, $eq));
            if ($name === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }
            if (getenv($name) !== false) {
                continue; // Real environment wins.
            }
            $value = trim(substr($line, $eq + 1));
            // Strip matching single/double quotes.
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                $value = substr($value, 1, -1);
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $imported++;
        }

        return $imported;
    }

    /**
     * Read an environment variable with a fallback default.
     *
     * @param string $name    Variable name.
     * @param mixed  $default Returned when the variable is unset.
     * @return mixed
     */
    function cg_env($name, $default = null) {
        $value = getenv($name);
        return ($value === false) ? $default : $value;
    }

    /**
     * Read a boolean environment variable. "1", "true", "yes", "on" are true.
     *
     * @param string $name    Variable name.
     * @param bool   $default Returned when the variable is unset.
     * @return bool
     */
    function cg_env_bool($name, $default = false) {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }
        $value = strtolower(trim($value));
        return in_array($value, array('1', 'true', 'yes', 'on'), true);
    }

    /**
     * Read a bounded integer environment variable (e.g. timeouts).
     *
     * @param string $name    Variable name.
     * @param int    $default Returned when unset or out of range.
     * @param int    $min     Inclusive lower bound.
     * @param int    $max     Inclusive upper bound.
     * @return int
     */
    function cg_env_int($name, $default, $min, $max) {
        $value = getenv($name);
        if ($value === false || !is_numeric($value)) {
            return $default;
        }
        $value = (int) $value;
        if ($value < $min || $value > $max) {
            return $default;
        }
        return $value;
    }

    // Load the project-root .env once at include time.
    cg_load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
}
