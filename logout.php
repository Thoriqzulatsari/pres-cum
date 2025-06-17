<?php
// Include config file
require_once "config.php";
 
// Unset all of the session variables
$_SESSION = array();
 
// Destroy the session
session_destroy();
 
// Redirect to login page
redirect("login.php");
exit;
?>