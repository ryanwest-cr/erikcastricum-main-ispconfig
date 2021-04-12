<?php

/*
	Datatypes:
	- INTEGER
	- DOUBLE
	- CURRENCY
	- VARCHAR
	- TEXT
	- DATE
*/



// Name of the list
$liste["name"]     = "mail_blacklist";

// Database table
$liste["table"]    = "mail_access";

// Index index field of the database table
$liste["table_idx"]   = "access_id";

// Search Field Prefix
$liste["search_prefix"]  = "search_";

// Records per page
$liste["records_per_page"]  = "15";

// Script File of the list
$liste["file"]    = "mail_blacklist_list.php";

// Script file of the edit form
$liste["edit_file"]   = "mail_blacklist_edit.php";

// Script File of the delete script
$liste["delete_file"]  = "mail_blacklist_del.php";

// Paging Template
$liste["paging_tpl"]  = "templates/paging.tpl.htm";

// Enable auth
$liste["auth"]    = "yes";


/*****************************************************
* Suchfelder
*****************************************************/

$liste["item"][] = array( 'field'  => "active",
	'datatype' => "VARCHAR",
	'formtype' => "SELECT",
	'op'  => "=",
	'prefix' => "",
	'suffix' => "",
	'width'  => "",
	'value'  => array('y' => $app->lng('yes_txt'), 'n' => $app->lng('no_txt')));



$liste["item"][] = array( 'field'  => "server_id",
	'datatype' => "VARCHAR",
	'formtype' => "SELECT",
	'op'  => "like",
	'prefix' => "%",
	'suffix' => "%",
	'datasource' => array (  'type' => 'SQL',
		'querystring' => 'SELECT server_id,server_name FROM server WHERE {AUTHSQL} AND mirror_server_id = 0 ORDER BY server_name',
		'keyfield'=> 'server_id',
		'valuefield'=> 'server_name'
	),
	'width'  => "",
	'value'  => "");

$liste["item"][] = array( 'field'  => "source",
	'datatype' => "VARCHAR",
	'formtype' => "TEXT",
	'op'  => "like",
	'prefix' => "%",
	'suffix' => "%",
	'datasource' => array (  'type' => 'SQL',
		'querystring' => 'SELECT access_id,source FROM mail_access WHERE {AUTHSQL} ORDER BY source',
		'keyfield'=> 'access_id',
		'valuefield'=> 'source'
	),
	'width'  => "",
	'value'  => "");


if ($app->auth->is_admin()) {
	$type_values = array('recipient' => 'Recipient', 'sender' => 'Sender', 'client' => 'Client');
} else {
	$type_values = array('recipient' => 'Recipient', 'sender' => 'Sender');
}
$liste["item"][] = array( 'field'  => "type",
	'datatype' => "VARCHAR",
	'formtype' => "SELECT",
	'op'  => "=",
	'prefix' => "",
	'suffix' => "",
	'width'  => "",
	'value'  => $type_values);

?>
