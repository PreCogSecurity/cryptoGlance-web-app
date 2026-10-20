<?php

require_once(__DIR__ . '/env.php');

/**
 * Perform an HTTP(S) request and return decoded JSON (or raw body).
 *
 * TLS certificates are verified by default. Verification may only be disabled
 * explicitly via CG_INSECURE_TLS=1 for a trusted local mirror; silently
 * accepting any certificate would expose pool API keys and account data to
 * man-in-the-middle attacks.
 */
function curlCall($url, $params = null, $contentType = 'application/json', $options = array()) {
    $timeout = cg_env_int('CG_HTTP_TIMEOUT', 20, 1, 300);
    $insecureTls = cg_env_bool('CG_INSECURE_TLS', false);

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, min(10, $timeout));
    curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, !$insecureTls);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $insecureTls ? 0 : 2);
    curl_setopt($curl, CURLOPT_SSLVERSION, 4); // TLSv1.2

    if (!is_null($params) && isset($options['key'], $options['sig'])) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: '.$contentType, 'key: '.$options['key'], 'sig: '.$options['sig']));
    } else if (!is_null($params) && !empty($params)) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: '.$contentType));
    } else {
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: '.$contentType));
    }

    // Allow for custom requests
    if (isset($options['custom_request']) && !empty($options['custom_request'])) {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $options['custom_request']);
    }

    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; cryptoGlance ' . CURRENT_VERSION . '; PHP/' . phpversion() . ')');

    $curlExec = curl_exec($curl);
    if ($curlExec === false || curl_errno($curl)) {
        $data = array();
    } else {
        $data = json_decode($curlExec, true);
    }

    curl_close($curl);

    if (empty($data)) {
        // return non-jsonfied data
        return $curlExec;
    }

    return $data;
}

/**
 * Percentage helper that can never divide by zero.
 *
 * @param int|float $part  Numerator.
 * @param int|float $total Denominator; zero yields 0.0.
 * @return float Rounded to two decimal places.
 */
function cg_percent($part, $total) {
    $total = (float) $total;
    if ($total <= 0) {
        return 0.0;
    }
    return round(((float) $part / $total) * 100, 2);
}

/**
 * Reduce a client-supplied AJAX type token to a safe [a-z0-9-] string.
 */
function cg_ajax_normalize_token($token) {
    return preg_replace('/[^a-z0-9-]+/', '', strtolower((string) $token));
}

/**
 * Convert an kebab-case action to StudlyCase method name (no leading verb).
 */
function cg_ajax_studly_action($action) {
    $action = cg_ajax_normalize_token($action);
    if ($action === '') {
        return '';
    }
    return str_replace(' ', '', ucwords(str_replace('-', ' ', $action)));
}

/**
 * Detect a cross-site browser request (CSRF defence) using Fetch Metadata,
 * falling back to comparing the Origin and Host headers.
 */
function cg_request_is_cross_site() {
    $secFetchSite = isset($_SERVER['HTTP_SEC_FETCH_SITE']) ? $_SERVER['HTTP_SEC_FETCH_SITE'] : '';
    if ($secFetchSite !== '') {
        return !in_array($secFetchSite, array('same-origin', 'none'), true);
    }

    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $origin = parse_url($_SERVER['HTTP_ORIGIN']);
        $originHost = isset($origin['host']) ? strtolower($origin['host']) : '';
        $originPort = isset($origin['port']) ? (int) $origin['port'] : null;

        $request = parse_url('http://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
        $requestHost = isset($request['host']) ? strtolower($request['host']) : '';
        $requestPort = isset($request['port']) ? (int) $request['port'] : null;

        if ($originHost === '' || $requestHost === '') {
            return true; // Cannot establish same-origin; be strict.
        }
        if ($originHost !== $requestHost) {
            return true;
        }
        if ($originPort !== null && $requestPort !== null && $originPort !== $requestPort) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve an AJAX type/action pair to a [class, method] target.
 *
 * Returns null unless the type is on the allow-list, the method exists on the
 * target class, and (for GET requests) the method is a read-only get* method.
 * This replaces the previous behaviour of instantiating any autoloaded class
 * and invoking any caller-chosen method.
 *
 * @param string $type   Client-supplied panel type (e.g. "rigs").
 * @param string $action Client-supplied action (e.g. "switch-pool").
 * @param bool   $isPost True when the request carries a POST body.
 * @return array|null array($class, $method) or null when rejected.
 */
function cg_resolve_ajax_target($type, $action, $isPost) {
    // Explicit per-type action allow-lists, matching the calls the bundled
    // frontend actually makes. Internal helpers (write, add, ...) and any
    // future accidental public method stay unreachable over AJAX.
    $map = array(
        'rigs' => array(
            'class' => 'Rigs',
            'post' => array(
                'restart', 'switch-pool', 'add-pool', 'prioritize-pool',
                'edit-pool', 'remove-pool', 'change-pool-status',
                'change-device-status', 'update-devices', 'reset-stats',
                'create', 'update', 'remove',
            ),
            'get' => array('pools', 'devices', 'settings', 'overview', 'update'),
        ),
        'pools' => array(
            'class' => 'Pools',
            'post' => array('create', 'update', 'remove'),
            'get' => array('update', 'config'),
        ),
        'wallets' => array(
            'class' => 'Wallets',
            'post' => array(
                'add-address', 'remove-address', 'edit-address',
                'create', 'update', 'remove',
            ),
            'get' => array('update', 'currencies', 'fiat'),
        ),
        'mobile-miner' => array(
            'class' => 'MobileMiner',
            'post' => array('get-update'),
            'get' => array('update'),
        ),
    );

    $typeToken = cg_ajax_normalize_token($type);
    if (!isset($map[$typeToken])) {
        return null;
    }
    $entry = $map[$typeToken];
    $class = $entry['class'];

    $actionToken = cg_ajax_normalize_token($action);
    $allowed = $isPost ? $entry['post'] : $entry['get'];
    if ($actionToken === '' || !in_array($actionToken, $allowed, true)) {
        return null;
    }

    $method = cg_ajax_studly_action($actionToken);
    if (!$isPost) {
        $method = 'Get' . $method;
    }

    // The method must really exist on the target class (defence in depth on
    // top of the allow-list, and it keeps the map honest as APIs evolve).
    if (!class_exists($class) || !method_exists($class, $method)) {
        return null;
    }
    if (!$isPost && strpos($method, 'Get') !== 0) {
        return null; // GET may never reach a state-changing method.
    }

    return array($class, $method);
}

function formatHashrate($hashrate, $precision = 2) { // h expected
    // Math Stuffs
    $units = array('H', 'KH', 'MH', 'GH', 'TH', 'PH', 'EH', 'ZH', 'YH');

    $pow = min(floor(($hashrate ? log($hashrate) : 0) / log(1000)), count($units) - 1);
    $hashrate /= pow(1000, $pow);
    $hashrate = round($hashrate, $precision) . ' ' . $units[$pow] . '/s';

    return $hashrate;
}
function formatCapacity($hashrate, $precision = 2) { // h expected
    return str_replace('H/s', 'B', formatHashrate($hashrate, $precision));
}

function formatTimeElapsed($elapsed) { // NOTE: This does not support weeks. Only days/months.
    if (!isset($elapsed)) {
        return null;
    }

    $elapsed = intval($elapsed);

    $from = new DateTime("@0");
    $to = new DateTime("@$elapsed");
    $difference = $from->diff($to);

    if ($difference->y) {
        $format = '%yY %mM %dD';
    } else if ($difference->m) {
        $format = '%mM %dD %hH';
    } else if ($difference->d) {
        $format = '%aD %hH %iM';
    } else if ($difference->h) {
        $format = '%hH %iM %sS';
    } else if ($difference->i) {
        $format = '%hH %iM %sS';
    } else {
        $format = '%hH %iM %sS'; // includes zero elapsed time
    }

    return $difference->format($format);
}
