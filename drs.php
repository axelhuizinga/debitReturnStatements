<?php
/**
 * parse uploaded debit return statements 
 */
error_reporting(E_ERROR | E_PARSE | E_NOTICE);

use Genkgo\Camt\Config;
#use SimpleXMLElement;
use Genkgo\Camt\Reader;
use Genkgo\Camt\Camt052\MessageFormat\V02;

require_once('vendor/autoload.php');
require_once('autoload.php');
require_once('functions.php');
/**/
$loader = new Autoloader();
$loader->setNamespacePrefix('')
       ->setBaseDir(__DIR__.DIRECTORY_SEPARATOR)
       ->register();

#edump("FILES:".print_r($_FILES,1));
#edump("FILE:".implode('|',$_GET));
#edump("FILE:".implode('|',$argv));
if(count($_GET)==0 && $argc == 2)
	$_GET['file'] = $argv[1];
$appLog = dirname($argv[0]).'drs.log';
#edump(strlen(file_get_contents($_GET['file'])));
edump(print_r($_GET,1));
$drsGot = array();
#echo(showUser());exit;
### change dir into script directory
chdir(dirname($argv[0]));
echo(getcwd().PHP_EOL);

echo $_GET['file'].PHP_EOL;
$tmp = 'tmp_'.date('ymdHis',time());
if(!mkdir($tmp)){
	die("Konnte Verzeichnis $tmp nicht anlegen!");
};

$file = str_replace(array(' ',"'"), array('_',''), basename($_GET['file']));
$copied = system("COPY ".$_GET['file']." $tmp\\$file");
#chmod($_GET['file'],2+4) or die("chmod failed:".print_r(error_get_last(),1).PHP_EOL);
//1 = execute, 2 = write, 4 = read

#$copied = copy($_GET['file'],"$tmp\\$file");
#$res = $z->open($_GET['file']);
if(strstr($copied,'1 Datei(en) kopiert.'))
{
	#system("dir $tmp/")
	$mzip = preg_match('/zip/',$file, $matches, PREG_OFFSET_CAPTURE);
	if(isset($matches[0]) && substr($file,$matches[0][1],3)==='zip')
	{
		echo('zip file type found :)'.PHP_EOL);
		
		echo substr($file,$matches[0][1],3)."<".$matches[0][1].">->$copied<- copy ".$_GET['file']."to $tmp\\ $file<<".PHP_EOL;
	}
	#echo((preg_match('/zip$/',$file)?'Y':'N').PHP_EOL);
	#echo((preg_match('/zip\\\$/',$file)?'Y':'N').PHP_EOL);
	#echo((preg_match('/zip/',$file)?'Y':'N').PHP_EOL);
	#echo(error_get_last()['message']);
	if(is_array($matches) && isset($matches[0]) && is_array($matches[0]) && substr($file,$matches[0][1],3)==='zip')
	{
		$z = new \ZipArchive();
		clearstatcache();
		$res = $z->open("$tmp\\$file");
		if ($res === TRUE) {
			$z->extractTo($tmp);
			$od = opendir($tmp);
			edump($z->count()."::".count(scandir($tmp)));
			if($z->count() == count(scandir($tmp))-2)
			{
				edump($z->count()." Dateien entpackt");
			}
			$i=0;
			while ($i<$z->count()) {
				$drsGot = array_merge($drsGot, addDRS($z->getFromIndex($i++)));
			}
			$z->close();
		}
		else{
			echo("Unpacking into $tmp ".($res?'OK':$res));
		}
	}
	else{
		echo("Verarbeite Datei $file".PHP_EOL);
		$drsGot = array_merge($drsGot, addDRS(file_get_contents("$tmp\\$file")));
	}
	echo count($drsGot)." Rücklastschriften nach $tmp extrahiert".PHP_EOL;
	if(saveResult($drsGot,$tmp)){
		listAll($drsGot);
		echo "Bereit zum Upload von $tmp/RLast.json".PHP_EOL;
	}
}
else{
	echo "errors:".print_r(error_get_last(),1).PHP_EOL;
	#rmdir($tmp);
	die("Konnte nicht nach $tmp\\$file kopieren");
	#system("rd /s /q $tmp");
}

function saveResult($res, $tmp){
	return file_put_contents("$tmp/RLast.json",json_encode($res));
}
#header('Content-Type: application/json');
#echo json_encode(array('rlData'=>$rla),JSON_FORCE_OBJECT);
#echo json_encode($drsGot);

exit(0);

function listAll($list){
	foreach($list as $l){
		#echo(print_r($l, 1));
		echo("id: $l[id] iban: $l[iban] Betrag: $l[amount] baID: $l[ba_id]".PHP_EOL);
	}
}

function addDRS($xml){

	$config = Config::getDefault();
	#$config->disableXsdValidation();
	##edump($config);###/Camt052/MessageFormat/V02.php
	$messageFormats = $config->getMessageFormats();
	$config->addMessageFormat(new V02());
	foreach ($messageFormats as $messageFormat) {
		if (true /*$messageFormat->getXmlNs() === $xmlNs*/) {
			echo $messageFormat->getName().'::'.$messageFormat->getXmlNs().PHP_EOL;
		}
	}	
	print_r(messageFormats,1);
	#exit(print_r(messageFormats,1));
	if(strpos("<", $xml)!=0){
		echo(strpos("<", $xml).PHP_EOL);
		return [];
	}
	$reader = new Reader($config);
	$dir = '/var/www/vhosts/pitverwaltung.de/files';
	try{
		$message = $reader->readString($xml);
	}
	catch(Exception $e){
		echo('Exception:'.print_r($e,1).PHP_EOL);
		echo('xml:'.substr($xml,0,8).PHP_EOL);
	}
	$s_i = 0;
	$r = 0;
	$t = 0;
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
				#edump(print_r($entry->getTransactionDetails(),1));exit;
				$traDet = $entry->getTransactionDetail();
				if(!$traDet->getReference()->getEndToEndId())
					continue;
				$account = getAccount($traDet->getRelatedParties());#[1]->getAccount();
				#edump($traDet->getRelatedParties()[1]->getAccount()->getIdentification());
				edump($rInfo->getCode().'::'.sprintf("%.2f", $money->getAmount()/100).'::'.$money->isNegative());	
				$iban = ($account==NULL?'':$account->getIdentification());
				edump('getReference:'.$traDet->getReference()->getMandateId().' getAccountServicerReference::'.$entry-> getAccountServicerReference().' getAccount::'.get_class($account).'<');
				edump('getRemittanceInformation:'.$traDet->getRemittanceInformation()->getMessage().' getRelatedPartyAccount::'.$iban.'  getEndToEndId::'.$traDet->getReference()->getEndToEndId());
				$rlData = array(
					'id'=>intval($traDet->getReference()->getMandateId()),
					'ba_id'=>$traDet->getReference()->getEndToEndId(),
					'iban'=>$iban,
					'sepa_code'=>$rInfo->getCode(),
					//'dealId'=>$traDet->getReference()->getMandateId(),
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
	return $rla;
}

function getAccount($relParties)
{
	return array_pop($relParties)->getAccount();
	/*foreach($relParties as $party){
		if(strstr($party->relatedPartyDetails->name, 'SchutzengelWerk')==0)
			continue
	}*/
}

	function delTree($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
		(is_dir("$dir/$file") && !is_link($dir)) ? delTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	} 	
?>