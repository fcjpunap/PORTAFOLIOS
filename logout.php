<?php
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>