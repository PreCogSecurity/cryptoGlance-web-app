<?php
require_once('includes/inc.php');

if (empty($_SESSION['login_string'])) {
    header("HTTP/1.0 401 Unauthorized");
    exit();
}

// Reject cross-site browser requests (CSRF) before touching any state.
if (cg_request_is_cross_site()) {
    header("HTTP/1.0 403 Forbidden");
    exit();
}

session_write_close();

$CACHED = true;
if (isset($_GET['cached']) && ($_GET['cached'] == 'false' || $_GET['cached'] == 0)) {
    $CACHED = false;
}
$GLOBALS['cached'] = $CACHED;

$type = (!empty($_GET['type']) ? $_GET['type'] : (isset($_POST['type']) ? $_POST['type'] : ''));
$action = (!empty($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : ''));

if ($type === '' || $action === '') {
    header("HTTP/1.0 400 Bad Request");
    exit();
}

require_once('includes/autoloader.inc.php');

// Only allow-listed type/action pairs resolve; anything else is refused.
// (Previously any autoloaded class could be instantiated and any method
// invoked with attacker-controlled names.)
$target = cg_resolve_ajax_target($type, $action, !empty($_POST));
if ($target === null) {
    header("HTTP/1.0 403 Forbidden");
    exit();
}
list($type, $action) = $target;

$obj = new $type();
$result = $obj->$action();
header('Content-Type: application/json');
echo json_encode($result);
