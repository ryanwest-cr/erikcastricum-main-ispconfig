<?php

require 'soap_config.php';


$client = new SoapClient(null, array('location' => $soap_location,
		'uri'      => $soap_uri,
		'trace' => 1,
		'exceptions' => 1));


try {
	if($session_id = $client->login($username, $password)) {
		echo 'Logged successfull. Session ID:'.$session_id.'<br />';
	}

	//* Set the function parameters. 
	//* 'data' are given for example MUST be edited with appropriate DS record
	$client_id = 1;
	$params = array(
		'server_id' => 1,
		'zone' => 7,
		'name' => 'nameserver',
		'type' => 'ds',
		'data' => '13456 13 2 0EXD84534054012XFN7880EDFR23Z56Y34GRC64KOY704DFTEV87AE A34ZDC45',
		'aux' => '0',
		'ttl' => '3600',
		'active' => 'y',
		'stamp' => 'CURRENT_TIMESTAMP',
		'serial' => '1',
	);

	$id = $client->dns_ds_add($session_id, $client_id, $params);

	echo "ID: ".$id."<br>";

	if($client->logout($session_id)) {
		echo 'Logged out.<br />';
	}


} catch (SoapFault $e) {
	echo $client->__getLastResponse();
	die('SOAP Error: '.$e->getMessage());
}

?>
