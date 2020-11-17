<?php
/**
 * parse uploaded debit return statements 
 */
error_reporting(E_ERROR | E_PARSE | E_NOTICE);
$_GET['file'] = readline("Dateiname?");
require_once('drs.php');

?>