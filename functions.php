<?php
	function edump($m, $stackPos=0)
	{
		global $appLog;
		$date = date("d.m.y H:i:s",time());
		#error_log("syncLog:$appLog");
		if(!is_string($m))
			$m = print_r($m,1);
		#$m = preg_replace("/\r\n|\n/", '', $m);
		$dump = debug_backtrace();
		$file = basename($dump[$stackPos]['file']);
		#$file = $dump[$stackPos]['file'];
		$dumpBuf = "$file::{$dump[$stackPos]['line']}:$date $m";
		if($appLog && file_exists(dirname($appLog)))
			file_put_contents($appLog,"$dumpBuf\n",FILE_APPEND);
			#echo "$dumpBuf\n";
		else
			error_log($dumpBuf);
	}

	function showUser(){
		$processUser = posix_getpwuid(posix_geteuid());
		return $processUser['name'];
	}

	function vardump($ob){
		#error_log('XXX...');
		ob_start();
		var_dump($ob);
		$dumpBuf = ob_get_clean();
		return $dumpBuf;		
	}

	function dumpPos($m, $stackPos=0)
	{
		$dump = debug_backtrace();
		$file = basename($dump[$stackPos]['file']);
		return "$file::{$dump[$stackPos]['line']}: $m";		
	}
?>
