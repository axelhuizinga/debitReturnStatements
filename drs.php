<?php
/**
 * parse uploaded debit return statements 
 */

use Genkgo\Camt\Config;
use Genkgo\Camt\Reader;
use SimpleXMLElement;

require_once('vendor/autoload.php');
require_once('autoload.php');
require_once('functions.php');
/**/
$loader = new Autoloader();
$loader->setNamespacePrefix('')
       ->setBaseDir(__DIR__.DIRECTORY_SEPARATOR)
       ->register();

#edump("FILES:".print_r($_FILES,1));
edump("FILE:".implode('|',$_GET));
edump("FILE:".implode('|',$argv));
if(count($_GET)==0 && $argc == 2)
	$_GET['file'] = $argv[1];
#edump(strlen(file_get_contents($_GET['file'])));
edump(print_r($_GET,1));
if(file_exists($_GET['file']))
{
	$tmp = dirname($_GET['file']).'/tmp_'.date('YmdHis',time());
	mkdir($tmp);
	$z = zip_open($_GET['file']);
	$z = new \ZipArchive();
	$res = $z->open($_GET['file']);

	if ($res === TRUE) {
		/*while ($file = zip_read($z)) {
			echo  zip_entry_name($file).PHP_EOL;
		}*/
		$z->extractTo($tmp);
		$od = opendir($tmp);
		edump($z->count()."::".count(scandir($tmp)));
		if($z->count() == count(scandir($tmp))-2)
		{
			edump($z->count()." Dateien entpackt");
		}
	}
	$z->close();
	delTree($tmp);
}
exit('OK');

$config = Config::getDefault();
#$config->disableXsdValidation();
##edump($config);
$reader = new Reader($config);
$dir = '/var/www/vhosts/pitverwaltung.de/files';
#$reader = new Genkgo\Camt\Reader(Config::getDefault());
#$message = $reader->readFile($dir.'/camt053.v2.minimal.xml');
#$message = $reader->readFile($dir.'/camt/2014-01-03_C52_DE58740618130100033626_EUR_A00035.xml');
#$message = $reader->readFile($dir.'/camt-1169231300-43060967-2020-09-23.xml');
$message = $reader->readFile($_GET['file']);
unlink($_GET['file']);
#print_r($message);
$s_i = 0;
$r = 0;
$t = 0;
#edump(get_included_files());
edump(strlen(print_r($message,1)));
$statements = $message->getRecords();
$count_statements = count($statements);
#edump($statements);
$rla = array();
foreach ($statements as $statement) {		
	$entries = $statement->getEntries();
	$count_entries = count($entries);
	edump($count_entries);
	#edump($statement);
	foreach($entries as $entry)
	{
		$money = $entry->getAmount();# Money object
		#edump($entry);
		### CHECK IF RECORD IS A RETURN TRANSACTION
		if($rInfo = $entry->getTransactionDetail()->getReturnInformation()){
			$traDet = $entry->getTransactionDetail();
			$account = $traDet->getRelatedParties()[1]->getAccount();
			#edump($traDet->getRelatedParties()[1]->getAccount()->getIdentification());
			edump($rInfo->getCode().'::'.sprintf("%.2f", $money->getAmount()/100).'::'.$money->isNegative());			
			edump('getReference:'.$traDet->getReference()->getMandateId().' getAccountServicerReference::'.$entry-> getAccountServicerReference().' getAccount::'.get_class($account).'<');
			edump('getRemittanceInformation:'.$traDet->getRemittanceInformation()->getMessage().' getRelatedPartyAccount::'.$account->getIdentification().'  getEndToEndId::'.$traDet->getReference()->getEndToEndId());
			$rlData = array(
				'id'=>intval($traDet->getReference()->getMandateId()),
				'baID'=>$traDet->getReference()->getEndToEndId(),
				'iban'=>$account->getIdentification(),
				'sepaCode'=>$rInfo->getCode(),
				'dealId'=>$traDet->getReference()->getMandateId(),
				'amount'=>sprintf("%.2f", $money->getAmount()/100)
			);
			array_push($rla,$rlData);
		}
		else{
			### normal transaction						
		}
	}
	#print_r($entries);
}
	header('Content-Type: application/json');
	#echo json_encode(array('rlData'=>$rla),JSON_FORCE_OBJECT);
	echo json_encode(array('rlData'=>$rla));

	function delTree($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
		(is_dir("$dir/$file") && !is_link($dir)) ? delTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	} 	
?>