<?php
require_once 'session.php';

$_SESSION = [];     // forget everything in the session
session_destroy();  // and delete it on the server

header('Location: login.php');
exit;