<?php
/**
 * parse uploaded debit return statements 
 */
error_reporting(E_ERROR | E_PARSE | E_NOTICE);
while($_GET['file'] = readline("Dateiname?")){
	require('drs.php');
}
#require_once('drs.php');

?>