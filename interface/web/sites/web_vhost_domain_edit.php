<?php
/*
Copyright (c) 2007 - 2009, Till Brehm, projektfarm Gmbh
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/


/******************************************
* Begin Form configuration
******************************************/

$tform_def_file = "form/web_vhost_domain.tform.php";

/******************************************
* End Form configuration
******************************************/

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

//* Check permissions for module
$app->auth->check_module_permissions('sites');

// Loading classes
$app->uses('tpl,tform,tform_actions,tools_sites');
$app->load('tform_actions');

class page_action extends tform_actions {
	var $_vhostdomain_type = 'domain';

	//* Returna a "3/2/1" path hash from a numeric id '123'
	function id_hash($id, $levels) {
		$hash = "" . $id % 10 ;
		$id /= 10 ;
		$levels -- ;
		while ( $levels > 0 ) {
			$hash .= "/" . $id % 10 ;
			$id /= 10 ;
			$levels-- ;
		}
		return $hash;
	}

	function onLoad() {
		$show_type = 'domain';
		if(isset($_GET['type']) && $_GET['type'] == 'subdomain') {
			$show_type = 'subdomain';
		} elseif(isset($_GET['type']) && $_GET['type'] == 'aliasdomain') {
			$show_type = 'aliasdomain';
		} elseif(!isset($_GET['type']) && isset($_SESSION['s']['var']['vhostdomain_type']) && $_SESSION['s']['var']['vhostdomain_type'] == 'subdomain') {
			$show_type = 'subdomain';
		} elseif(!isset($_GET['type']) && isset($_SESSION['s']['var']['vhostdomain_type']) && $_SESSION['s']['var']['vhostdomain_type'] == 'aliasdomain') {
			$show_type = 'aliasdomain';
		}

		$_SESSION['s']['var']['vhostdomain_type'] = $show_type;
		$this->_vhostdomain_type = $show_type;
		
		parent::onLoad();
	}

	function onShowNew() {
		global $app, $conf;

		// we will check only users, not admins
		if($_SESSION["s"]["user"]["typ"] == 'user') {
			if($this->_vhostdomain_type == 'domain') {
				if(!$app->tform->checkClientLimit('limit_web_domain', "type = 'vhost'")) {
					$app->error($app->tform->wordbook["limit_web_domain_txt"]);
				}
				if(!$app->tform->checkResellerLimit('limit_web_domain', "type = 'vhost'")) {
					$app->error('Reseller: '.$app->tform->wordbook["limit_web_domain_txt"]);
				}
			} elseif($this->_vhostdomain_type == 'subdomain') {
				if(!$app->tform->checkClientLimit('limit_web_subdomain', "(type = 'subdomain' OR type = 'vhostsubdomain')")) {
					$app->error($app->tform->wordbook["limit_web_subdomain_txt"]);
				}
				if(!$app->tform->checkResellerLimit('limit_web_subdomain', "(type = 'subdomain' OR type = 'vhostsubdomain')")) {
					$app->error('Reseller: '.$app->tform->wordbook["limit_web_subdomain_txt"]);
				}
			} elseif($this->_vhostdomain_type == 'aliasdomain') {
				if(!$app->tform->checkClientLimit('limit_web_aliasdomain', "(type = 'alias' OR type = 'vhostalias')")) {
					$app->error($app->tform->wordbook["limit_web_aliasdomain_txt"]);
				}
				if(!$app->tform->checkResellerLimit('limit_web_aliasdomain', "(type = 'alias' OR type = 'vhostalias')")) {
					$app->error('Reseller: '.$app->tform->wordbook["limit_web_aliasdomain_txt"]);
				}
			}
			// Get the limits of the client
			$client_group_id = $_SESSION["s"]["user"]["default_group"];
			$client = $app->db->queryOneRecord("SELECT client.web_servers FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			$web_servers = explode(',', $client['web_servers']);
			$server_id = $web_servers[0];
			$app->tpl->setVar("server_id_value", $server_id);
			unset($web_servers);
		} else {
			$settings = $app->getconf->get_global_config('sites');
			$server_id = intval($settings['default_webserver']);
			$app->tform->formDef['tabs']['domain']['fields']['server_id']['default'] = $server_id;
		}
		if(!$server_id){
			$default_web_server = $app->db->queryOneRecord("SELECT server_id FROM server WHERE web_server = ? ORDER BY server_id LIMIT 0,1", 1);
			$server_id = $default_web_server['server_id'];
		}
		$web_config = $app->getconf->get_server_config($server_id, 'web');
		$app->tform->formDef['tabs']['domain']['fields']['php']['default'] = $web_config['php_handler'];
		$app->tform->formDef['tabs']['domain']['readonly'] = false;

		$app->tpl->setVar('vhostdomain_type', $this->_vhostdomain_type);
		parent::onShowNew();
	}

	function onShowEnd() {
		global $app, $conf;

		$app->uses('ini_parser,getconf');
		$settings = $app->getconf->get_global_config('domains');

		$read_limits = array('limit_cgi', 'limit_ssi', 'limit_perl', 'limit_ruby', 'limit_python', 'force_suexec', 'limit_hterror', 'limit_wildcard', 'limit_ssl');

		if($this->_vhostdomain_type != 'domain') $parent_domain = $app->db->queryOneRecord("select * FROM web_domain WHERE domain_id = ".$app->functions->intval(@$this->dataRecord["parent_domain_id"]));

		//* Client: If the logged in user is not admin and has no sub clients (no reseller)
		if($_SESSION["s"]["user"]["typ"] != 'admin' && !$app->auth->has_clients($_SESSION['s']['user']['userid'])) {

			// Get the limits of the client
			$client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);
			if($this->_vhostdomain_type == 'domain') {
				$client = $app->db->queryOneRecord("SELECT client.limit_web_domain, client.web_servers, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			} elseif($this->_vhostdomain_type == 'subdomain') {
				$client = $app->db->queryOneRecord("SELECT client.limit_web_subdomain, client.web_servers, client.default_webserver, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			} elseif($this->_vhostdomain_type == 'aliasdomain') {
				$client = $app->db->queryOneRecord("SELECT client.limit_web_aliasdomain, client.web_servers, client.default_webserver, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			}

			$client['web_servers_ids'] = explode(',', $client['web_servers']);
			$only_one_server = count($client['web_servers_ids']) === 1;
			$app->tpl->setVar('only_one_server', $only_one_server);

			//* Get global web config
			foreach ($client['web_servers_ids'] as $web_server_id) {
				$web_config[$web_server_id] = $app->getconf->get_server_config($web_server_id, 'web');
			}

			$sql = "SELECT server_id, server_name FROM server WHERE server_id IN (" . $client['web_servers'] . ");";
			$web_servers = $app->db->queryAllRecords($sql);

			$options_web_servers = "";

			foreach ($web_servers as $web_server) {
				$options_web_servers .= "<option value='$web_server[server_id]'>$web_server[server_name]</option>";
			}

			$app->tpl->setVar("server_id", $options_web_servers);
			unset($options_web_servers);

			if($this->id > 0) {
				if(!isset($this->dataRecord["server_id"])){
					$tmp = $app->db->queryOneRecord("SELECT server_id FROM web_domain WHERE domain_id = ".$app->functions->intval($this->id));
					$this->dataRecord["server_id"] = $tmp["server_id"];
					unset($tmp);
				}
				$server_id = intval(@$this->dataRecord["server_id"]);
			} else {
				$server_id = (isset($web_servers[0])) ? intval($web_servers[0]) : 0;
			}
			
			if($app->functions->intval($this->dataRecord["server_id"]) > 0) {
				// check if server is in client's servers or add it.
				$chk_sid = explode(',', $client['web_servers']);
				if(in_array($this->dataRecord["server_id"], explode(',', $client['web_servers'])) == false) {
					if($client['web_servers'] != '') $client['web_servers'] .= ',';
					$client['web_servers'] .= $app->functions->intval($this->dataRecord["server_id"]);
				}
			}
			
			//* Fill the IPv4 select field with the IP addresses that are allowed for this client
			$sql = "SELECT ip_address FROM server_ip WHERE server_id IN (" . $client['web_servers'] . ") AND ip_type = 'IPv4' AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id'].")";
			$ips = $app->db->queryAllRecords($sql);
			$ip_select = ($web_config[$server_id]['enable_ip_wildcard'] == 'y')?"<option value='*'>*</option>":"";
			//if(!in_array($this->dataRecord["ip_address"], $ips)) $ip_select .= "<option value='".$this->dataRecord["ip_address"]."' SELECTED>".$this->dataRecord["ip_address"]."</option>\r\n";
			//$ip_select = "";
			if(is_array($ips)) {
				foreach( $ips as $ip) {
					$selected = ($ip["ip_address"] == $this->dataRecord["ip_address"])?'SELECTED':'';
					$ip_select .= "<option value='$ip[ip_address]' $selected>$ip[ip_address]</option>\r\n";
				}
			}
			$app->tpl->setVar("ip_address", $ip_select);
			unset($tmp);
			unset($ips);

			//* Fill the IPv6 select field with the IP addresses that are allowed for this client
			$sql = "SELECT ip_address FROM server_ip WHERE server_id IN (" . $client['web_servers'] . ") AND ip_type = 'IPv6' AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id'].")";
			$ips = $app->db->queryAllRecords($sql);
			$ip_select = "<option value=''></option>";
			//$ip_select = "";
			if(is_array($ips)) {
				foreach( $ips as $ip) {
					$selected = ($ip["ip_address"] == $this->dataRecord["ipv6_address"])?'SELECTED':'';
					$ip_select .= "<option value='$ip[ip_address]' $selected>$ip[ip_address]</option>\r\n";
				}
			}
			$app->tpl->setVar("ipv6_address", $ip_select);
			unset($tmp);
			unset($ips);

			//PHP Version Selection (FastCGI)
			$server_type = 'apache';
			if(!empty($web_config[$server_id]['server_type'])) $server_type = $web_config[$server_id]['server_type'];
			if($server_type == 'nginx' && $this->dataRecord['php'] == 'fast-cgi') $this->dataRecord['php'] = 'php-fpm';

			if($this->_vhostdomain_type == 'domain') {
				if($this->dataRecord['php'] == 'php-fpm'){
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = ".($this->id > 0 ? $app->functions->intval($this->dataRecord['server_id']) : $app->functions->intval($client['default_webserver']))." AND (client_id = 0 OR client_id=".$app->functions->intval($_SESSION['s']['user']['client_id']).")");
				}
				if($this->dataRecord['php'] == 'fast-cgi'){
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = ".($this->id > 0 ? $app->functions->intval($this->dataRecord['server_id']) : $app->functions->intval($client['default_webserver']))." AND (client_id = 0 OR client_id=".$app->functions->intval($_SESSION['s']['user']['client_id']).")");
				}
			} else {
				if($this->dataRecord['php'] == 'php-fpm'){
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = ".$app->functions->intval($parent_domain['server_id'])." AND (client_id = 0 OR client_id=".$app->functions->intval($_SESSION['s']['user']['client_id']).")");
				}
				if($this->dataRecord['php'] == 'fast-cgi'){
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = ".$app->functions->intval($parent_domain['server_id'])." AND (client_id = 0 OR client_id=".$app->functions->intval($_SESSION['s']['user']['client_id']).")");
				}
			}
			$php_select = "<option value=''>Default</option>";
			if(is_array($php_records) && !empty($php_records)) {
				foreach( $php_records as $php_record) {
					if($this->dataRecord['php'] == 'php-fpm'){
						$php_version = $php_record['name'].':'.$php_record['php_fpm_init_script'].':'.$php_record['php_fpm_ini_dir'].':'.$php_record['php_fpm_pool_dir'];
					} else {
						$php_version = $php_record['name'].':'.$php_record['php_fastcgi_binary'].':'.$php_record['php_fastcgi_ini_dir'];
					}
					$selected = ($php_version == $this->dataRecord["fastcgi_php_version"])?'SELECTED':'';
					$php_select .= "<option value='$php_version' $selected>".$php_record['name']."</option>\r\n";
				}
			}
			$app->tpl->setVar("fastcgi_php_version", $php_select);
			unset($php_records);

			// add limits to template to be able to hide settings
			foreach($read_limits as $limit) $app->tpl->setVar($limit, $client[$limit]);


			//* Reseller: If the logged in user is not admin and has sub clients (is a reseller)
		} elseif ($_SESSION["s"]["user"]["typ"] != 'admin' && $app->auth->has_clients($_SESSION['s']['user']['userid'])) {

			// Get the limits of the client
			$client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);

			if($this->_vhostdomain_type == 'domain') {
				$client = $app->db->queryOneRecord("SELECT client.client_id, client.limit_web_domain, client.web_servers, client.default_webserver, client.contact_name, CONCAT(IF(client.company_name != '', CONCAT(client.company_name, ' :: '), ''), client.contact_name, ' (', client.username, IF(client.customer_no != '', CONCAT(', ', client.customer_no), ''), ')') as contactname, sys_group.name, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
				$app->tpl->setVar('only_one_server', $only_one_server);
			} elseif($this->_vhostdomain_type == 'subdomain') {
				$client = $app->db->queryOneRecord("SELECT client.client_id, client.limit_web_subdomain, client.web_servers, client.default_webserver, client.contact_name, CONCAT(IF(client.company_name != '', CONCAT(client.company_name, ' :: '), ''), client.contact_name, ' (', client.username, IF(client.customer_no != '', CONCAT(', ', client.customer_no), ''), ')') as contactname, sys_group.name, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			} elseif($this->_vhostdomain_type == 'aliasdomain') {
				$client = $app->db->queryOneRecord("SELECT client.client_id, client.limit_web_aliasdomain, client.web_servers, client.default_webserver, client.contact_name, CONCAT(IF(client.company_name != '', CONCAT(client.company_name, ' :: '), ''), client.contact_name, ' (', client.username, IF(client.customer_no != '', CONCAT(', ', client.customer_no), ''), ')') as contactname, sys_group.name, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");
			}

			$client['web_servers_ids'] = explode(',', $client['web_servers']);
			$only_one_server = count($client['web_servers_ids']) === 1;

			//* Get global web config
			foreach ($client['web_servers_ids'] as $web_server_id) {
				$web_config[$web_server_id] = $app->getconf->get_server_config($web_server_id, 'web');
			}

			$sql = "SELECT server_id, server_name FROM server WHERE server_id IN (" . $client['web_servers'] . ");";
			$web_servers = $app->db->queryAllRecords($sql);

			$options_web_servers = "";

			foreach ($web_servers as $web_server) {
				$options_web_servers .= "<option value='$web_server[server_id]'>$web_server[server_name]</option>";
			}

			$app->tpl->setVar("server_id", $options_web_servers);
			unset($options_web_servers);

			if ($settings['use_domain_module'] != 'y') {
				// Fill the client select field
				$sql = "SELECT sys_group.groupid, sys_group.name, CONCAT(IF(client.company_name != '', CONCAT(client.company_name, ' :: '), ''), client.contact_name, ' (', client.username, IF(client.customer_no != '', CONCAT(', ', client.customer_no), ''), ')') as contactname FROM sys_group, client WHERE sys_group.client_id = client.client_id AND client.parent_client_id = ".$client['client_id']." ORDER BY client.company_name, client.contact_name, sys_group.name";
				$records = $app->db->queryAllRecords($sql);
				$tmp = $app->db->queryOneRecord("SELECT groupid FROM sys_group WHERE client_id = ".$app->functions->intval($client['client_id']));
				$client_select = '<option value="'.$tmp['groupid'].'">'.$client['contactname'].'</option>';
				//$tmp_data_record = $app->tform->getDataRecord($this->id);
				if(is_array($records)) {
					$selected_client_group_id = 0; // needed to get list of PHP versions
					foreach( $records as $rec) {
						if(is_array($this->dataRecord) && ($rec["groupid"] == $this->dataRecord['client_group_id'] || $rec["groupid"] == $this->dataRecord['sys_groupid']) && !$selected_client_group_id) $selected_client_group_id = $rec["groupid"];
						$selected = @(is_array($this->dataRecord) && ($rec["groupid"] == $this->dataRecord['client_group_id'] || $rec["groupid"] == $this->dataRecord['sys_groupid']))?'SELECTED':'';
						if($selected == 'SELECTED') $selected_client_group_id = $rec["groupid"];
						$client_select .= "<option value='$rec[groupid]' $selected>$rec[contactname]</option>\r\n";
					}
				}
				$app->tpl->setVar("client_group_id", $client_select);
			}

			if($app->functions->intval($this->dataRecord["server_id"]) > 0) {
				// check if server is in client's servers or add it.
				$chk_sid = explode(',', $client['web_servers']);
				if(in_array($this->dataRecord["server_id"], $client['web_servers']) == false) {
					if($client['web_servers'] != '') $client['web_servers'] .= ',';
					$client['web_servers'] .= $app->functions->intval($this->dataRecord["server_id"]);
				}
			}
			
			//* Fill the IPv4 select field with the IP addresses that are allowed for this client
			$sql = "SELECT ip_address FROM server_ip WHERE server_id IN (" . $client['web_servers'] . ") AND ip_type = 'IPv4' AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id'].")";
			$ips = $app->db->queryAllRecords($sql);
			$ip_select = ($web_config[$server_id]['enable_ip_wildcard'] == 'y')?"<option value='*'>*</option>":"";
			//if(!in_array($this->dataRecord["ip_address"], $ips)) $ip_select .= "<option value='".$this->dataRecord["ip_address"]."' SELECTED>".$this->dataRecord["ip_address"]."</option>\r\n";
			//$ip_select = "";
			if(is_array($ips)) {
				foreach( $ips as $ip) {
					$selected = ($ip["ip_address"] == $this->dataRecord["ip_address"])?'SELECTED':'';
					$ip_select .= "<option value='$ip[ip_address]' $selected>$ip[ip_address]</option>\r\n";
				}
			}
			$app->tpl->setVar("ip_address", $ip_select);
			unset($tmp);
			unset($ips);

			//* Fill the IPv6 select field with the IP addresses that are allowed for this client
			$sql = "SELECT ip_address FROM server_ip WHERE server_id IN (" . $client['web_servers'] . ") AND ip_type = 'IPv6' AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id'].")";
			$ips = $app->db->queryAllRecords($sql);
			$ip_select = "<option value=''></option>";
			//$ip_select = "";
			if(is_array($ips)) {
				foreach( $ips as $ip) {
					$selected = ($ip["ip_address"] == $this->dataRecord["ipv6_address"])?'SELECTED':'';
					$ip_select .= "<option value='$ip[ip_address]' $selected>$ip[ip_address]</option>\r\n";
				}
			}
			$app->tpl->setVar("ipv6_address", $ip_select);
			unset($tmp);
			unset($ips);

			//PHP Version Selection (FastCGI)
			$server_type = 'apache';
			if(!empty($web_config[$server_id]['server_type'])) $server_type = $web_config[$server_id]['server_type'];
			if($server_type == 'nginx' && $this->dataRecord['php'] == 'fast-cgi') $this->dataRecord['php'] = 'php-fpm';
			$selected_client = $app->db->queryOneRecord("SELECT client_id FROM sys_group WHERE groupid = ".$app->functions->intval($selected_client_group_id));
			//$sql_where = " AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id']." OR client_id = ".intval($selected_client['client_id']).")";
			$sql_where = " AND (client_id = 0 OR client_id = ".intval($selected_client['client_id']).")";
			if($this->_vhostdomain_type == 'domain') {
				if($this->dataRecord['php'] == 'php-fpm'){
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = ".($this->id > 0 ? $app->functions->intval($this->dataRecord['server_id']) : $app->functions->intval($client['default_webserver'])).$sql_where);
				}
				if($this->dataRecord['php'] == 'fast-cgi') {
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = ".($this->id > 0 ? $app->functions->intval($this->dataRecord['server_id']) : $app->functions->intval($client['default_webserver'])).$sql_where);
				}
			} else {
				if($this->dataRecord['php'] == 'php-fpm'){
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = ".$app->functions->intval($parent_domain['server_id'])." AND (client_id = 0 OR client_id=".$app->functions->intval($_SESSION['s']['user']['client_id']).")");
				}
				if($this->dataRecord['php'] == 'fast-cgi') {
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = ".$app->functions->intval($parent_domain['server_id'])." AND (client_id = 0 OR client_id=".$app->functions->intval($_SESSION['s']['user']['client_id']).")");
				}
			}
			$php_select = "<option value=''>Default</option>";
			if(is_array($php_records) && !empty($php_records)) {
				foreach( $php_records as $php_record) {
					if($this->dataRecord['php'] == 'php-fpm'){
						$php_version = $php_record['name'].':'.$php_record['php_fpm_init_script'].':'.$php_record['php_fpm_ini_dir'].':'.$php_record['php_fpm_pool_dir'];
					} else {
						$php_version = $php_record['name'].':'.$php_record['php_fastcgi_binary'].':'.$php_record['php_fastcgi_ini_dir'];
					}
					$selected = ($php_version == $this->dataRecord["fastcgi_php_version"])?'SELECTED':'';
					$php_select .= "<option value='$php_version' $selected>".$php_record['name']."</option>\r\n";
				}
			}
			$app->tpl->setVar("fastcgi_php_version", $php_select);
			unset($php_records);

			// add limits to template to be able to hide settings
			foreach($read_limits as $limit) $app->tpl->setVar($limit, $client[$limit]);

			$sites_config = $app->getconf->get_global_config('sites');
			if($sites_config['reseller_can_use_options']) {
				// Directive Snippets
				$php_directive_snippets = $app->db->queryAllRecords("SELECT * FROM directive_snippets WHERE type = 'php' AND active = 'y'");
				$php_directive_snippets_txt = '';
				if(is_array($php_directive_snippets) && !empty($php_directive_snippets)){
					foreach($php_directive_snippets as $php_directive_snippet){
						$php_directive_snippets_txt .= '<a href="javascript:void(0);" class="addPlaceholderContent">['.$php_directive_snippet['name'].']<pre class="addPlaceholderContent" style="display:none;">'.htmlentities($php_directive_snippet['snippet']).'</pre></a> ';
					}
				}
				if($php_directive_snippets_txt == '') $php_directive_snippets_txt = '------';
				$app->tpl->setVar("php_directive_snippets_txt", $php_directive_snippets_txt);

				if($server_type == 'apache'){
					$apache_directive_snippets = $app->db->queryAllRecords("SELECT * FROM directive_snippets WHERE type = 'apache' AND active = 'y'");
					$apache_directive_snippets_txt = '';
					if(is_array($apache_directive_snippets) && !empty($apache_directive_snippets)){
						foreach($apache_directive_snippets as $apache_directive_snippet){
							$apache_directive_snippets_txt .= '<a href="javascript:void(0);" class="addPlaceholderContent">['.$apache_directive_snippet['name'].']<pre class="addPlaceholderContent" style="display:none;">'.htmlentities($apache_directive_snippet['snippet']).'</pre></a> ';
						}
					}
					if($apache_directive_snippets_txt == '') $apache_directive_snippets_txt = '------';
					$app->tpl->setVar("apache_directive_snippets_txt", $apache_directive_snippets_txt);
				}

				if($server_type == 'nginx'){
					$nginx_directive_snippets = $app->db->queryAllRecords("SELECT * FROM directive_snippets WHERE type = 'nginx' AND active = 'y'");
					$nginx_directive_snippets_txt = '';
					if(is_array($nginx_directive_snippets) && !empty($nginx_directive_snippets)){
						foreach($nginx_directive_snippets as $nginx_directive_snippet){
							$nginx_directive_snippets_txt .= '<a href="javascript:void(0);" class="addPlaceholderContent">['.$nginx_directive_snippet['name'].']<pre class="addPlaceholderContent" style="display:none;">'.htmlentities($nginx_directive_snippet['snippet']).'</pre></a> ';
						}
					}
					if($nginx_directive_snippets_txt == '') $nginx_directive_snippets_txt = '------';
					$app->tpl->setVar("nginx_directive_snippets_txt", $nginx_directive_snippets_txt);
				}

				$proxy_directive_snippets = $app->db->queryAllRecords("SELECT * FROM directive_snippets WHERE type = 'proxy' AND active = 'y'");
				$proxy_directive_snippets_txt = '';
				if(is_array($proxy_directive_snippets) && !empty($proxy_directive_snippets)){
					foreach($proxy_directive_snippets as $proxy_directive_snippet){
						$proxy_directive_snippets_txt .= '<a href="javascript:void(0);" class="addPlaceholderContent">['.$proxy_directive_snippet['name'].']<pre class="addPlaceholderContent" style="display:none;">'.htmlentities($proxy_directive_snippet['snippet']).'</pre></a> ';
					}
				}
				if($proxy_directive_snippets_txt == '') $proxy_directive_snippets_txt = '------';
				$app->tpl->setVar("proxy_directive_snippets_txt", $proxy_directive_snippets_txt);
			}

			//* Admin: If the logged in user is admin
		} else {

			if($this->_vhostdomain_type == 'domain') {
				// The user is admin, so we fill in all IP addresses of the server
				if($this->id > 0) {
					if(!isset($this->dataRecord["server_id"])){
						$tmp = $app->db->queryOneRecord("SELECT server_id FROM web_domain WHERE domain_id = ".$app->functions->intval($this->id));
						$this->dataRecord["server_id"] = $tmp["server_id"];
						unset($tmp);
					}
					$server_id = intval(@$this->dataRecord["server_id"]);
				} else {
					$settings = $app->getconf->get_global_config('sites');
					$server_id = intval($settings['default_webserver']);
					if (!$server_id) {
						// Get the first server ID
						$tmp = $app->db->queryOneRecord("SELECT server_id FROM server WHERE web_server = 1 ORDER BY server_name LIMIT 0,1");
						$server_id = intval($tmp['server_id']);
					}
				}

				//* get global web config
				$web_config = $app->getconf->get_server_config($server_id, 'web');
			} else {
				//* get global web config
				$web_config = $app->getconf->get_server_config($parent_domain['server_id'], 'web');
			}

			//* Fill the IPv4 select field
			$sql = "SELECT ip_address FROM server_ip WHERE ip_type = 'IPv4' AND server_id = ".$app->functions->intval($server_id);
			$ips = $app->db->queryAllRecords($sql);
			$ip_select = ($web_config['enable_ip_wildcard'] == 'y')?"<option value='*'>*</option>":"";
			//$ip_select = "";
			if(is_array($ips)) {
				foreach( $ips as $ip) {
					$selected = ($ip["ip_address"] == $this->dataRecord["ip_address"])?'SELECTED':'';
					$ip_select .= "<option value='$ip[ip_address]' $selected>$ip[ip_address]</option>\r\n";
				}
			}
			$app->tpl->setVar("ip_address", $ip_select);
			unset($tmp);
			unset($ips);

			//* Fill the IPv6 select field
			$sql = "SELECT ip_address FROM server_ip WHERE ip_type = 'IPv6' AND server_id = ".$app->functions->intval($server_id);
			$ips = $app->db->queryAllRecords($sql);
			$ip_select = "<option value=''></option>";
			//$ip_select = "";
			if(is_array($ips)) {
				foreach( $ips as $ip) {
					$selected = ($ip["ip_address"] == $this->dataRecord["ipv6_address"])?'SELECTED':'';
					$ip_select .= "<option value='$ip[ip_address]' $selected>$ip[ip_address]</option>\r\n";
				}
			}
			$app->tpl->setVar("ipv6_address", $ip_select);
			unset($tmp);
			unset($ips);

			if ($settings['use_domain_module'] != 'y') {
				// Fill the client select field
				$sql = "SELECT sys_group.groupid, sys_group.name, CONCAT(IF(client.company_name != '', CONCAT(client.company_name, ' :: '), ''), client.contact_name, ' (', client.username, IF(client.customer_no != '', CONCAT(', ', client.customer_no), ''), ')') as contactname FROM sys_group, client WHERE sys_group.client_id = client.client_id AND sys_group.client_id > 0 ORDER BY client.company_name, client.contact_name, sys_group.name";
				$clients = $app->db->queryAllRecords($sql);
				$client_select = "<option value='0'></option>";
				//$tmp_data_record = $app->tform->getDataRecord($this->id);
				if(is_array($clients)) {
					$selected_client_group_id = 0; // needed to get list of PHP versions
					foreach($clients as $client) {
						if(is_array($this->dataRecord) && ($client["groupid"] == $this->dataRecord['client_group_id'] || $client["groupid"] == $this->dataRecord['sys_groupid']) && !$selected_client_group_id) $selected_client_group_id = $client["groupid"];
						//$selected = @($client["groupid"] == $tmp_data_record["sys_groupid"])?'SELECTED':'';
						$selected = @(is_array($this->dataRecord) && ($client["groupid"] == $this->dataRecord['client_group_id'] || $client["groupid"] == $this->dataRecord['sys_groupid']))?'SELECTED':'';
						if($selected == 'SELECTED') $selected_client_group_id = $client["groupid"];
						$client_select .= "<option value='$client[groupid]' $selected>$client[contactname]</option>\r\n";
					}
				}
				$app->tpl->setVar("client_group_id", $client_select);
			}

			//PHP Version Selection (FastCGI)
			$server_type = 'apache';
			if(!empty($web_config['server_type'])) $server_type = $web_config['server_type'];
			if($server_type == 'nginx' && $this->dataRecord['php'] == 'fast-cgi') $this->dataRecord['php'] = 'php-fpm';
			$selected_client = $app->db->queryOneRecord("SELECT client_id FROM sys_group WHERE groupid = ".$app->functions->intval($selected_client_group_id));
			//$sql_where = " AND (client_id = 0 OR client_id=".$_SESSION['s']['user']['client_id']." OR client_id = ".intval($selected_client['client_id']).")";
			$sql_where = " AND (client_id = 0 OR client_id = ".$app->functions->intval($selected_client['client_id']).")";
			if($this->_vhostdomain_type == 'domain') {
				if($this->dataRecord['php'] == 'php-fpm'){
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = $server_id".$sql_where);
				}
				if($this->dataRecord['php'] == 'fast-cgi') {
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = ".$app->functions->intval($server_id).$sql_where);
				}
			} else {
				if($this->dataRecord['php'] == 'php-fpm'){
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fpm_init_script != '' AND php_fpm_ini_dir != '' AND php_fpm_pool_dir != '' AND server_id = " . $app->functions->intval($parent_domain['server_id']));
				}
				if($this->dataRecord['php'] == 'fast-cgi') {
					$php_records = $app->db->queryAllRecords("SELECT * FROM server_php WHERE php_fastcgi_binary != '' AND php_fastcgi_ini_dir != '' AND server_id = " . $app->functions->intval($parent_domain['server_id']));
				}
			}
			$php_select = "<option value=''>Default</option>";
			if(is_array($php_records) && !empty($php_records)) {
				foreach( $php_records as $php_record) {
					if($this->dataRecord['php'] == 'php-fpm'){
						$php_version = $php_record['name'].':'.$php_record['php_fpm_init_script'].':'.$php_record['php_fpm_ini_dir'].':'.$php_record['php_fpm_pool_dir'];
					} else {
						$php_version = $php_record['name'].':'.$php_record['php_fastcgi_binary'].':'.$php_record['php_fastcgi_ini_dir'];
					}
					$selected = ($php_version == $this->dataRecord["fastcgi_php_version"])?'SELECTED':'';
					$php_select .= "<option value='$php_version' $selected>".$php_record['name']."</option>\r\n";
				}
			}
			$app->tpl->setVar("fastcgi_php_version", $php_select);
			unset($php_records);

			foreach($read_limits as $limit) $app->tpl->setVar($limit, ($limit == 'force_suexec' ? 'n' : 'y'));

			// Directive Snippets
			$php_directive_snippets = $app->db->queryAllRecords("SELECT * FROM directive_snippets WHERE type = 'php' AND active = 'y'");
			$php_directive_snippets_txt = '';
			if(is_array($php_directive_snippets) && !empty($php_directive_snippets)){
				foreach($php_directive_snippets as $php_directive_snippet){
					$php_directive_snippets_txt .= '<a href="javascript:void(0);" class="addPlaceholderContent">['.$php_directive_snippet['name'].']<pre class="addPlaceholderContent" style="display:none;">'.htmlentities($php_directive_snippet['snippet']).'</pre></a> ';
				}
			}
			if($php_directive_snippets_txt == '') $php_directive_snippets_txt = '------';
			$app->tpl->setVar("php_directive_snippets_txt", $php_directive_snippets_txt);

			if($server_type == 'apache'){
				$apache_directive_snippets = $app->db->queryAllRecords("SELECT * FROM directive_snippets WHERE type = 'apache' AND active = 'y'");
				$apache_directive_snippets_txt = '';
				if(is_array($apache_directive_snippets) && !empty($apache_directive_snippets)){
					foreach($apache_directive_snippets as $apache_directive_snippet){
						$apache_directive_snippets_txt .= '<a href="javascript:void(0);" class="addPlaceholderContent">['.$apache_directive_snippet['name'].']<pre class="addPlaceholderContent" style="display:none;">'.htmlentities($apache_directive_snippet['snippet']).'</pre></a> ';
					}
				}
				if($apache_directive_snippets_txt == '') $apache_directive_snippets_txt = '------';
				$app->tpl->setVar("apache_directive_snippets_txt", $apache_directive_snippets_txt);
			}

			if($server_type == 'nginx'){
				$nginx_directive_snippets = $app->db->queryAllRecords("SELECT * FROM directive_snippets WHERE type = 'nginx' AND active = 'y'");
				$nginx_directive_snippets_txt = '';
				if(is_array($nginx_directive_snippets) && !empty($nginx_directive_snippets)){
					foreach($nginx_directive_snippets as $nginx_directive_snippet){
						$nginx_directive_snippets_txt .= '<a href="javascript:void(0);" class="addPlaceholderContent">['.$nginx_directive_snippet['name'].']<pre class="addPlaceholderContent" style="display:none;">'.htmlentities($nginx_directive_snippet['snippet']).'</pre></a> ';
					}
				}
				if($nginx_directive_snippets_txt == '') $nginx_directive_snippets_txt = '------';
				$app->tpl->setVar("nginx_directive_snippets_txt", $nginx_directive_snippets_txt);
			}

			$proxy_directive_snippets = $app->db->queryAllRecords("SELECT * FROM directive_snippets WHERE type = 'proxy' AND active = 'y'");
			$proxy_directive_snippets_txt = '';
			if(is_array($proxy_directive_snippets) && !empty($proxy_directive_snippets)){
				foreach($proxy_directive_snippets as $proxy_directive_snippet){
					$proxy_directive_snippets_txt .= '<a href="javascript:void(0);" class="addPlaceholderContent">['.$proxy_directive_snippet['name'].']<pre class="addPlaceholderContent" style="display:none;">'.htmlentities($proxy_directive_snippet['snippet']).'</pre></a> ';
				}
			}
			if($proxy_directive_snippets_txt == '') $proxy_directive_snippets_txt = '------';
			$app->tpl->setVar("proxy_directive_snippets_txt", $proxy_directive_snippets_txt);
		}

		$ssl_domain_select = '';
		$ssl_domains = array();
		$tmpd = $app->db->queryAllRecords("SELECT domain, type FROM web_domain WHERE domain_id = ".$this->id." OR parent_domain_id = ".$this->id);
		foreach($tmpd as $tmp) {
			if($tmp['type'] == 'subdomain' || $tmp['type'] == 'vhostsubdomain') {
				$ssl_domains[] = $tmp["domain"];
			} else {
				$ssl_domains = array_merge($ssl_domains, array($tmp["domain"],'www.'.$tmp["domain"],'*.'.$tmp["domain"]));
			}
		}
		if(is_array($ssl_domains)) {
			foreach( $ssl_domains as $ssl_domain) {
				$selected = ($ssl_domain == $this->dataRecord['ssl_domain'])?'SELECTED':'';
				$ssl_domain_select .= "<option value='$ssl_domain' $selected>$ssl_domain</option>\r\n";
			}
		}
		$app->tpl->setVar("ssl_domain", $ssl_domain_select);
		unset($ssl_domain_select);
		unset($ssl_domains);
		unset($ssl_domain);

		if($this->id > 0) {
			//* we are editing a existing record
			$app->tpl->setVar("edit_disabled", 1);
			$app->tpl->setVar('fixed_folder', 'y');
			if($this->_vhostdomain_type == 'domain') $app->tpl->setVar("server_id_value", $this->dataRecord["server_id"]);
			else $app->tpl->setVar('server_id_value', $parent_domain['server_id']);
		} else {
			$app->tpl->setVar("edit_disabled", 0);
			$app->tpl->setVar('fixed_folder', 'n');
			if($this->_vhostdomain_type != 'domain') $app->tpl->setVar('server_id_value', $parent_domain['server_id']);
		}

		$tmp_txt = ($this->dataRecord['traffic_quota_lock'] == 'y')?'<b>('.$app->tform->lng('traffic_quota_exceeded_txt').')</b>':'';
		$app->tpl->setVar("traffic_quota_exceeded_txt", $tmp_txt);

		/*
		 * Now we have to check, if we should use the domain-module to select the domain
		 * or not
		 */
		if ($settings['use_domain_module'] == 'y') {
			/*
			 * The domain-module is in use.
			*/
			$domains = $app->tools_sites->getDomainModuleDomains($this->_vhostdomain_type == 'subdomain' ? null : "web_domain", $this->dataRecord["domain"]);
			$domain_select = '';
			$selected_domain = '';
			if(is_array($domains) && sizeof($domains) > 0) {
				/* We have domains in the list, so create the drop-down-list */
				foreach( $domains as $domain) {
					$domain_select .= "<option value=" . $domain['domain_id'] ;
					if ($this->_vhostdomain_type == 'subdomain' && '.' . $domain['domain'] == substr($this->dataRecord["domain"], -strlen($domain['domain']) - 1)) {
						$domain_select .= " selected";
						$selected_domain = $domain['domain'];
					} elseif($this->_vhostdomain_type == 'aliasdomain' && $domain['domain'] == $this->dataRecord["domain"]) {
						$domain_select .= " selected";
					} elseif($this->_vhostdomain_type == 'domain' && $domain['domain'] == $this->dataRecord["domain"]) {
						$domain_select .= " selected";
					}
					$domain_select .= ">" . $app->functions->idn_decode($domain['domain']) . "</option>\r\n";
				}
			}
			else {
				/*
				 * We have no domains in the domain-list. This means, we can not add ANY new domain.
				 * To avoid, that the variable "domain_option" is empty and so the user can
				 * free enter a domain, we have to create a empty option!
				*/
				$domain_select .= "<option value=''></option>\r\n";
			}
			$app->tpl->setVar("domain_option", $domain_select);
		}
		if($this->_vhostdomain_type != 'domain') $app->tpl->setVar("domain", $this->dataRecord["domain"]);

		// check for configuration errors in sys_datalog
		if($this->id > 0) {
			$datalog = $app->db->queryOneRecord("SELECT sys_datalog.error, sys_log.tstamp FROM sys_datalog, sys_log WHERE sys_datalog.dbtable = 'web_domain' AND sys_datalog.dbidx = 'domain_id:".$app->functions->intval($this->id)."' AND sys_datalog.datalog_id = sys_log.datalog_id AND sys_log.message = CONCAT('Processed datalog_id ',sys_log.datalog_id) ORDER BY sys_datalog.tstamp DESC");
			if(is_array($datalog) && !empty($datalog)){
				if(trim($datalog['error']) != ''){
					$app->tpl->setVar("config_error_msg", nl2br(htmlentities($datalog['error'])));
					$app->tpl->setVar("config_error_tstamp", date($app->lng('conf_format_datetime'), $datalog['tstamp']));
				}
			}
		}
		
		$app->tpl->setVar('vhostdomain_type', $this->_vhostdomain_type);

		$app->tpl->setVar('is_spdy_enabled', ($web_config['enable_spdy'] === 'y'));

		parent::onShowEnd();
	}

	function onShowEdit() {
		global $app;
		if($app->tform->checkPerm($this->id, 'riud')) $app->tform->formDef['tabs']['domain']['readonly'] = false;
		parent::onShowEdit();
	}

	function onSubmit() {
		global $app, $conf;

		// Set a few fixed values
		$this->dataRecord["vhost_type"] = 'name';
		if($this->_vhostdomain_type == 'domain') {
			$this->dataRecord["parent_domain_id"] = 0;
			$this->dataRecord["type"] = 'vhost';
		} else {
			// Get the record of the parent domain
			if(!@$this->dataRecord["parent_domain_id"] && $this->id) {
				$tmp = $app->db->queryOneRecord("SELECT parent_domain_id FROM web_domain WHERE domain_id = ".$app->functions->intval($this->id));
				if($tmp) $this->dataRecord["parent_domain_id"] = $tmp['parent_domain_id'];
				unset($tmp);
			}

			$parent_domain = $app->db->queryOneRecord("select * FROM web_domain WHERE domain_id = ".$app->functions->intval(@$this->dataRecord["parent_domain_id"]) . " AND ".$app->tform->getAuthSQL('r'));
			if(!$parent_domain || $parent_domain['domain_id'] != @$this->dataRecord['parent_domain_id']) $app->tform->errorMessage .= $app->tform->lng("no_domain_perm");

			if($this->_vhostdomain_type == 'subdomain') {
				$this->dataRecord["type"] = 'vhostsubdomain';
			} else {
				$this->dataRecord["type"] = 'vhostalias';
			}
			$this->dataRecord["server_id"] = $parent_domain["server_id"];
			$this->dataRecord["ip_address"] = $parent_domain["ip_address"];
			$this->dataRecord["ipv6_address"] = $parent_domain["ipv6_address"];
			$this->dataRecord["client_group_id"] = $parent_domain["client_group_id"];

			$this->parent_domain_record = $parent_domain;
		}

		$read_limits = array('limit_cgi', 'limit_ssi', 'limit_perl', 'limit_ruby', 'limit_python', 'force_suexec', 'limit_hterror', 'limit_wildcard', 'limit_ssl');

		/* check if the domain module is used - and check if the selected domain can be used! */
		if($app->tform->getCurrentTab() == 'domain') {
			if($this->_vhostdomain_type == 'subdomain') {
				// Check that domain (the subdomain part) is not empty
				if(!preg_match('/^[a-zA-Z0-9].*/',$this->dataRecord['domain'])) {
					$app->tform->errorMessage .= $app->tform->lng("subdomain_error_empty")."<br />";
				}
			}
			
			/* check if the domain module is used - and check if the selected domain can be used! */
			$app->uses('ini_parser,getconf');
			$settings = $app->getconf->get_global_config('domains');
			if ($settings['use_domain_module'] == 'y') {
				if($this->_vhostdomain_type == 'subdomain') $domain_check = $app->tools_sites->checkDomainModuleDomain($this->dataRecord['sel_domain']);
				else $domain_check = $app->tools_sites->checkDomainModuleDomain($this->dataRecord['domain']);
				if(!$domain_check) {
					// invalid domain selected
					$app->tform->errorMessage .= $app->tform->lng("domain_error_empty")."<br />";
				} else {
					if ($this->_vhostdomain_type == 'domain' &&
							($_SESSION["s"]["user"]["typ"] == 'admin' || $app->auth->has_clients($_SESSION['s']['user']['userid']))) {
						$this->dataRecord['client_group_id'] = $app->tools_sites->getClientIdForDomain($this->dataRecord['domain']);
					}
					if($this->_vhostdomain_type == 'subdomain') $this->dataRecord['domain'] = $this->dataRecord['domain'] . '.' . $domain_check;
					else $this->dataRecord['domain'] = $domain_check;
				}
			} else {
				if($this->_vhostdomain_type == 'subdomain') $this->dataRecord["domain"] = $this->dataRecord["domain"].'.'.$parent_domain["domain"];
			}

			if($this->_vhostdomain_type != 'domain') {
				$this->dataRecord['web_folder'] = strtolower($this->dataRecord['web_folder']);
				if(substr($this->dataRecord['web_folder'], 0, 1) === '/') $this->dataRecord['web_folder'] = substr($this->dataRecord['web_folder'], 1);
				if(substr($this->dataRecord['web_folder'], -1) === '/') $this->dataRecord['web_folder'] = substr($this->dataRecord['web_folder'], 0, -1);
				$forbidden_folders = array('', 'cgi-bin', 'log', 'private', 'ssl', 'tmp', 'webdav');
				$check_folder = strtolower($this->dataRecord['web_folder']);
				if(substr($check_folder, 0, 1) === '/') $check_folder = substr($check_folder, 1); // strip / at beginning to check against forbidden entries
				if(strpos($check_folder, '/') !== false) $check_folder = substr($check_folder, 0, strpos($check_folder, '/')); // get the first part of the path to check it
				if(in_array($check_folder, $forbidden_folders)) {
					$app->tform->errorMessage .= $app->tform->lng("web_folder_invalid_txt")."<br>";
				}

				// vhostaliasdomains do not have a quota of their own
				$this->dataRecord["hd_quota"] = 0;

				// check for duplicate folder usage
				/*
		        $check = $app->db->queryOneRecord("SELECT COUNT(*) as `cnt` FROM `web_domain` WHERE `type` = 'vhostalias' AND `parent_domain_id` = '" . $app->functions->intval($this->dataRecord['parent_domain_id']) . "' AND `web_folder` = '" . $app->db->quote($this->dataRecord['web_folder']) . "' AND `domain_id` != '" . $app->functions->intval($this->id) . "'");
		        if($check && $check['cnt'] > 0) {
		            $app->tform->errorMessage .= $app->tform->lng("web_folder_unique_txt")."<br>";
		        }
				*/
			}
		}



		if($_SESSION["s"]["user"]["typ"] != 'admin') {
			// Get the limits of the client
			$client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);
			$client = $app->db->queryOneRecord("SELECT limit_traffic_quota, limit_web_domain, limit_web_aliasdomain, limit_web_subdomain, web_servers, parent_client_id, limit_web_quota, client." . implode(", client.", $read_limits) . " FROM sys_group, client WHERE sys_group.client_id = client.client_id and sys_group.groupid = $client_group_id");

			$client['web_servers_ids'] = explode(',', $client['web_servers']);

			if($client['limit_cgi'] != 'y') $this->dataRecord['cgi'] = 'n';
			if($client['limit_ssi'] != 'y') $this->dataRecord['ssi'] = 'n';
			if($client['limit_perl'] != 'y') $this->dataRecord['perl'] = 'n';
			if($client['limit_ruby'] != 'y') $this->dataRecord['ruby'] = 'n';
			if($client['limit_python'] != 'y') $this->dataRecord['python'] = 'n';
			if($client['force_suexec'] == 'y') $this->dataRecord['suexec'] = 'y';
			if($client['limit_hterror'] != 'y') $this->dataRecord['errordocs'] = 'n';
			if($client['limit_wildcard'] != 'y' && $this->dataRecord['subdomain'] == '*') $this->dataRecord['subdomain'] = 'n';
			if($client['limit_ssl'] != 'y') $this->dataRecord['ssl'] = 'n';

			// only generate quota and traffic warnings if value has changed
			if($this->id > 0) {
				$old_web_values = $app->db->queryOneRecord("SELECT * FROM web_domain WHERE domain_id = ".$app->functions->intval($this->id));
			} else {
				$old_web_values = array();
			}
			
			if($this->_vhostdomain_type == 'domain') {
				//* Check the website quota of the client
				if(isset($_POST["hd_quota"]) && $client["limit_web_quota"] >= 0 && $_POST["hd_quota"] != $old_web_values["hd_quota"]) {
					$tmp = $app->db->queryOneRecord("SELECT sum(hd_quota) as webquota FROM web_domain WHERE domain_id != ".$app->functions->intval($this->id)." AND type = 'vhost' AND ".$app->tform->getAuthSQL('u'));
					$webquota = $tmp["webquota"];
					$new_web_quota = $app->functions->intval($this->dataRecord["hd_quota"]);
					if(($webquota + $new_web_quota > $client["limit_web_quota"]) || ($new_web_quota < 0 && $client["limit_web_quota"] >= 0)) {
						$max_free_quota = floor($client["limit_web_quota"] - $webquota);
						if($max_free_quota < 0) $max_free_quota = 0;
						$app->tform->errorMessage .= $app->tform->lng("limit_web_quota_free_txt").": ".$max_free_quota." MB<br>";
						// Set the quota field to the max free space
						$this->dataRecord["hd_quota"] = $max_free_quota;
					}
					unset($tmp);
					unset($tmp_quota);
				}
			}

			//* Check the traffic quota of the client
			if(isset($_POST["traffic_quota"]) && $client["limit_traffic_quota"] > 0 && $_POST["traffic_quota"] != $old_web_values["traffic_quota"]) {
				$tmp = $app->db->queryOneRecord("SELECT sum(traffic_quota) as trafficquota FROM web_domain WHERE domain_id != ".$app->functions->intval($this->id)." AND ".$app->tform->getAuthSQL('u'));
				$trafficquota = $tmp["trafficquota"];
				$new_traffic_quota = $app->functions->intval($this->dataRecord["traffic_quota"]);
				if(($trafficquota + $new_traffic_quota > $client["limit_traffic_quota"]) || ($new_traffic_quota < 0 && $client["limit_traffic_quota"] >= 0)) {
					$max_free_quota = floor($client["limit_traffic_quota"] - $trafficquota);
					if($max_free_quota < 0) $max_free_quota = 0;
					$app->tform->errorMessage .= $app->tform->lng("limit_traffic_quota_free_txt").": ".$max_free_quota." MB<br>";
					// Set the quota field to the max free space
					$this->dataRecord["traffic_quota"] = $max_free_quota;
				}
				unset($tmp);
				unset($tmp_quota);
			}

			if($client['parent_client_id'] > 0) {
				// Get the limits of the reseller
				$reseller = $app->db->queryOneRecord("SELECT limit_traffic_quota, limit_web_domain, limit_web_aliasdomain, limit_web_subdomain, web_servers, limit_web_quota FROM client WHERE client_id = ".$client['parent_client_id']);

				if($this->_vhostdomain_type == 'domain') {
					//* Check the website quota of the client
					if(isset($_POST["hd_quota"]) && $reseller["limit_web_quota"] >= 0 && $_POST["hd_quota"] != $old_web_values["hd_quota"]) {
						$tmp = $app->db->queryOneRecord("SELECT sum(hd_quota) as webquota FROM web_domain, sys_group, client WHERE web_domain.sys_groupid=sys_group.groupid AND sys_group.client_id=client.client_id AND ".$client['parent_client_id']." IN (client.parent_client_id, client.client_id) AND domain_id != ".$app->functions->intval($this->id)." AND type = 'vhost'");

						$webquota = $tmp["webquota"];
						$new_web_quota = $app->functions->intval($this->dataRecord["hd_quota"]);
						if(($webquota + $new_web_quota > $reseller["limit_web_quota"]) || ($new_web_quota < 0 && $reseller["limit_web_quota"] >= 0)) {
							$max_free_quota = floor($reseller["limit_web_quota"] - $webquota);
							if($max_free_quota < 0) $max_free_quota = 0;
							$app->tform->errorMessage .= $app->tform->lng("limit_web_quota_free_txt").": ".$max_free_quota." MB<br>";
							// Set the quota field to the max free space
							$this->dataRecord["hd_quota"] = $max_free_quota;
						}
						unset($tmp);
						unset($tmp_quota);
					}
				}

				//* Check the traffic quota of the client
				if(isset($_POST["traffic_quota"]) && $reseller["limit_traffic_quota"] > 0 && $_POST["traffic_quota"] != $old_web_values["traffic_quota"]) {
					$tmp = $app->db->queryOneRecord("SELECT sum(traffic_quota) as trafficquota FROM web_domain, sys_group, client WHERE web_domain.sys_groupid=sys_group.groupid AND sys_group.client_id=client.client_id AND ".$client['parent_client_id']." IN (client.parent_client_id, client.client_id) AND domain_id != ".$app->functions->intval($this->id)." AND type = 'vhost'");
					$trafficquota = $tmp["trafficquota"];
					$new_traffic_quota = $app->functions->intval($this->dataRecord["traffic_quota"]);
					if(($trafficquota + $new_traffic_quota > $reseller["limit_traffic_quota"]) || ($new_traffic_quota < 0 && $reseller["limit_traffic_quota"] >= 0)) {
						$max_free_quota = floor($reseller["limit_traffic_quota"] - $trafficquota);
						if($max_free_quota < 0) $max_free_quota = 0;
						$app->tform->errorMessage .= $app->tform->lng("limit_traffic_quota_free_txt").": ".$max_free_quota." MB<br>";
						// Set the quota field to the max free space
						$this->dataRecord["traffic_quota"] = $max_free_quota;
					}
					unset($tmp);
					unset($tmp_quota);
				}
			}

			// When the record is updated
			if($this->id > 0) {
				// restore the server ID if the user is not admin and record is edited
				$tmp = $app->db->queryOneRecord("SELECT server_id, `system_user`, `system_group`, `web_folder`, `cgi`, `ssi`, `perl`, `ruby`, `python`, `suexec`, `errordocs`, `subdomain`, `ssl` FROM web_domain WHERE domain_id = ".$app->functions->intval($this->id));
				$this->dataRecord["server_id"] = $tmp["server_id"];
				$this->dataRecord['web_folder'] = $tmp['web_folder']; // cannot be changed!
				$this->dataRecord['system_user'] = $tmp['system_user'];
				$this->dataRecord['system_group'] = $tmp['system_group'];

				// set the settings to current if not provided (or cleared due to limits)
				if($this->dataRecord['cgi'] == 'n') $this->dataRecord['cgi'] = $tmp['cgi'];
				if($this->dataRecord['ssi'] == 'n') $this->dataRecord['ssi'] = $tmp['ssi'];
				if($this->dataRecord['perl'] == 'n') $this->dataRecord['perl'] = $tmp['perl'];
				if($this->dataRecord['ruby'] == 'n') $this->dataRecord['ruby'] = $tmp['ruby'];
				if($this->dataRecord['python'] == 'n') $this->dataRecord['python'] = $tmp['python'];
				if($this->dataRecord['suexec'] == 'n') $this->dataRecord['suexec'] = $tmp['suexec'];
				if($this->dataRecord['errordocs'] == 'n') $this->dataRecord['errordocs'] = $tmp['errordocs'];
				if($this->dataRecord['subdomain'] == 'n') $this->dataRecord['subdomain'] = $tmp['subdomain'];
				if($this->dataRecord['ssl'] == 'n') $this->dataRecord['ssl'] = $tmp['ssl'];

				unset($tmp);
				// When the record is inserted
			} else {
				if($this->_vhostdomain_type == 'domain') {
					//* display an error if chosen server is not allowed for this client
					if (!is_array($client['web_servers_ids']) || !in_array($this->dataRecord['server_id'], $client['web_servers_ids'])) {
						$app->error($app->tform->wordbook['server_chosen_not_ok']);
					}
				}

				// Check if the user may add another web_domain
				if($this->_vhostdomain_type == 'domain' && $client["limit_web_domain"] >= 0) {
					$tmp = $app->db->queryOneRecord("SELECT count(domain_id) as number FROM web_domain WHERE sys_groupid = $client_group_id and type = 'vhost'");
					if($tmp["number"] >= $client["limit_web_domain"]) {
						$app->error($app->tform->wordbook["limit_web_domain_txt"]);
					}
				} elseif($this->_vhostdomain_type == 'aliasdomain' && $client["limit_web_aliasdomain"] >= 0) {
					$tmp = $app->db->queryOneRecord("SELECT count(domain_id) as number FROM web_domain WHERE sys_groupid = $client_group_id and (type = 'alias' OR type = 'vhostalias')");
					if($tmp["number"] >= $client["limit_web_aliasdomain"]) {
						$app->error($app->tform->wordbook["limit_web_aliasdomain_txt"]);
					}
				} elseif($this->_vhostdomain_type == 'subdomain' && $client["limit_web_subdomain"] >= 0) {
					$tmp = $app->db->queryOneRecord("SELECT count(domain_id) as number FROM web_domain WHERE sys_groupid = $client_group_id and (type = 'subdomain' OR type = 'vhostsubdomain')");
					if($tmp["number"] >= $client["limit_web_subdomain"]) {
						$app->error($app->tform->wordbook["limit_web_subdomain_txt"]);
					}
				}
			}

			// Clients may not set the client_group_id, so we unset them if user is not a admin and the client is not a reseller
			if(!$app->auth->has_clients($_SESSION['s']['user']['userid'])) unset($this->dataRecord["client_group_id"]);
		}

		//* make sure that the domain is lowercase
		if(isset($this->dataRecord["domain"])) $this->dataRecord["domain"] = strtolower($this->dataRecord["domain"]);

		//* get the server config for this server
		$app->uses("getconf");
		if($this->id > 0){
			$web_rec = $app->tform->getDataRecord($this->id);
			$server_id = $web_rec["server_id"];
		} else {
			// Get the first server ID
			$tmp = $app->db->queryOneRecord("SELECT server_id FROM server WHERE web_server = 1 ORDER BY server_name LIMIT 0,1");
			$server_id = intval($tmp['server_id']);
		}
		$web_config = $app->getconf->get_server_config($app->functions->intval(isset($this->dataRecord["server_id"]) ? $this->dataRecord["server_id"] : $server_id), 'web');
		//* Check for duplicate ssl certs per IP if SNI is disabled
		if(isset($this->dataRecord['ssl']) && $this->dataRecord['ssl'] == 'y' && $web_config['enable_sni'] != 'y') {
			$sql = "SELECT count(domain_id) as number FROM web_domain WHERE `ssl` = 'y' AND ip_address = '".$app->db->quote($this->dataRecord['ip_address'])."' and domain_id != ".$this->id;
			$tmp = $app->db->queryOneRecord($sql);
			if($tmp['number'] > 0) $app->tform->errorMessage .= $app->tform->lng("error_no_sni_txt");
		}

		// Check if pm.max_children >= pm.max_spare_servers >= pm.start_servers >= pm.min_spare_servers > 0
		if(isset($this->dataRecord['pm_max_children']) && $this->dataRecord['pm'] == 'dynamic') {
			if($app->functions->intval($this->dataRecord['pm_max_children'], true) >= $app->functions->intval($this->dataRecord['pm_max_spare_servers'], true) && $app->functions->intval($this->dataRecord['pm_max_spare_servers'], true) >= $app->functions->intval($this->dataRecord['pm_start_servers'], true) && $app->functions->intval($this->dataRecord['pm_start_servers'], true) >= $app->functions->intval($this->dataRecord['pm_min_spare_servers'], true) && $app->functions->intval($this->dataRecord['pm_min_spare_servers'], true) > 0){

			} else {
				$app->tform->errorMessage .= $app->tform->lng("error_php_fpm_pm_settings_txt").'<br>';
			}
		}

		// Check rewrite rules
		$server_type = $web_config['server_type'];

		if($server_type == 'nginx' && isset($this->dataRecord['rewrite_rules']) && trim($this->dataRecord['rewrite_rules']) != '') {
			$rewrite_rules = trim($this->dataRecord['rewrite_rules']);
			$rewrites_are_valid = true;
			// use this counter to make sure all curly brackets are properly closed
			$if_level = 0;
			// Make sure we only have Unix linebreaks
			$rewrite_rules = str_replace("\r\n", "\n", $rewrite_rules);
			$rewrite_rules = str_replace("\r", "\n", $rewrite_rules);
			$rewrite_rule_lines = explode("\n", $rewrite_rules);
			if(is_array($rewrite_rule_lines) && !empty($rewrite_rule_lines)){
				foreach($rewrite_rule_lines as $rewrite_rule_line){
					// ignore comments
					if(substr(ltrim($rewrite_rule_line), 0, 1) == '#') continue;
					// empty lines
					if(trim($rewrite_rule_line) == '') continue;
					// rewrite
					if(preg_match('@^\s*rewrite\s+(^/)?\S+(\$)?\s+\S+(\s+(last|break|redirect|permanent|))?\s*;\s*$@', $rewrite_rule_line)) continue;
					if(preg_match('@^\s*rewrite\s+(^/)?(\'[^\']+\'|"[^"]+")+(\$)?\s+(\'[^\']+\'|"[^"]+")+(\s+(last|break|redirect|permanent|))?\s*;\s*$@', $rewrite_rule_line)) continue;
					if(preg_match('@^\s*rewrite\s+(^/)?(\'[^\']+\'|"[^"]+")+(\$)?\s+\S+(\s+(last|break|redirect|permanent|))?\s*;\s*$@', $rewrite_rule_line)) continue;
					if(preg_match('@^\s*rewrite\s+(^/)?\S+(\$)?\s+(\'[^\']+\'|"[^"]+")+(\s+(last|break|redirect|permanent|))?\s*;\s*$@', $rewrite_rule_line)) continue;
					// if
					if(preg_match('@^\s*if\s+\(\s*\$\S+(\s+(\!?(=|~|~\*))\s+(\S+|\".+\"))?\s*\)\s*\{\s*$@', $rewrite_rule_line)){
						$if_level += 1;
						continue;
					}
					// if - check for files, directories, etc.
					if(preg_match('@^\s*if\s+\(\s*\!?-(f|d|e|x)\s+\S+\s*\)\s*\{\s*$@', $rewrite_rule_line)){
						$if_level += 1;
						continue;
					}
					// break
					if(preg_match('@^\s*break\s*;\s*$@', $rewrite_rule_line)){
						continue;
					}
					// return code [ text ]
					if(preg_match('@^\s*return\s+\d\d\d.*;\s*$@', $rewrite_rule_line)) continue;
					// return code URL
					// return URL
					if(preg_match('@^\s*return(\s+\d\d\d)?\s+(http|https|ftp)\://([a-zA-Z0-9\.\-]+(\:[a-zA-Z0-9\.&%\$\-]+)*\@)*((25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9])\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9]|0)\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9]|0)\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[0-9])|localhost|([a-zA-Z0-9\-]+\.)*[a-zA-Z0-9\-]+\.(com|edu|gov|int|mil|net|org|biz|arpa|info|name|pro|aero|coop|museum|[a-zA-Z]{2}))(\:[0-9]+)*(/($|[a-zA-Z0-9\.\,\?\'\\\+&%\$#\=~_\-]+))*\s*;\s*$@', $rewrite_rule_line)) continue;
					// set
					if(preg_match('@^\s*set\s+\$\S+\s+\S+\s*;\s*$@', $rewrite_rule_line)) continue;
					// closing curly bracket
					if(trim($rewrite_rule_line) == '}'){
						$if_level -= 1;
						continue;
					}
					$rewrites_are_valid = false;
					break;
				}
			}

			if(!$rewrites_are_valid || $if_level != 0){
				$app->tform->errorMessage .= $app->tform->lng("invalid_rewrite_rules_txt").'<br>';
			}
		}
		
		// check custom php.ini settings
		if(isset($this->dataRecord['custom_php_ini']) && trim($this->dataRecord['custom_php_ini']) != '') {
			$custom_php_ini_settings = trim($this->dataRecord['custom_php_ini']);
			$custom_php_ini_settings_are_valid = true;
			// Make sure we only have Unix linebreaks
			$custom_php_ini_settings = str_replace("\r\n", "\n", $custom_php_ini_settings);
			$custom_php_ini_settings = str_replace("\r", "\n", $custom_php_ini_settings);
			$custom_php_ini_settings_lines = explode("\n", $custom_php_ini_settings);
			if(is_array($custom_php_ini_settings_lines) && !empty($custom_php_ini_settings_lines)){
				foreach($custom_php_ini_settings_lines as $custom_php_ini_settings_line){
					if(trim($custom_php_ini_settings_line) == '') continue;
					if(substr(trim($custom_php_ini_settings_line),0,1) == ';') continue;
					// empty value
					if(preg_match('@^\s*;*\s*[a-zA-Z0-9._]*\s*=\s*;*\s*$@', $custom_php_ini_settings_line)) continue;
					// value inside ""
					if(preg_match('@^\s*;*\s*[a-zA-Z0-9._]*\s*=\s*".*"\s*;*\s*$@', $custom_php_ini_settings_line)) continue;
					// value inside ''
					if(preg_match('@^\s*;*\s*[a-zA-Z0-9._]*\s*=\s*\'.*\'\s*;*\s*$@', $custom_php_ini_settings_line)) continue;
					// everything else
					if(preg_match('@^\s*;*\s*[a-zA-Z0-9._]*\s*=\s*[-a-zA-Z0-9~&=_\@/,.#\s]*\s*;*\s*$@', $custom_php_ini_settings_line)) continue;
					$custom_php_ini_settings_are_valid = false;
					break;
				}
			}
			if(!$custom_php_ini_settings_are_valid){
				$app->tform->errorMessage .= $app->tform->lng("invalid_custom_php_ini_settings_txt").'<br>';
			}
		}

		if($web_config['enable_spdy'] === 'n') {
			unset($app->tform->formDef["tabs"]['ssl']['fields']['enable_spdy']);
		}

		parent::onSubmit();
	}

	function onAfterInsert() {
		global $app, $conf;

		// make sure that the record belongs to the clinet group and not the admin group when admin inserts it
		// also make sure that the user can not delete domain created by a admin
		if($_SESSION["s"]["user"]["typ"] == 'admin' && isset($this->dataRecord["client_group_id"])) {
			$client_group_id = $app->functions->intval($this->dataRecord["client_group_id"]);
			$app->db->query("UPDATE web_domain SET sys_groupid = $client_group_id, sys_perm_group = 'ru' WHERE domain_id = ".$this->id);
		}
		if($app->auth->has_clients($_SESSION['s']['user']['userid']) && isset($this->dataRecord["client_group_id"])) {
			$client_group_id = $app->functions->intval($this->dataRecord["client_group_id"]);
			$app->db->query("UPDATE web_domain SET sys_groupid = $client_group_id, sys_perm_group = 'riud' WHERE domain_id = ".$this->id);
		}

		// Get configuration for the web system
		$app->uses("getconf");
		$web_rec = $app->tform->getDataRecord($this->id);
		$web_config = $app->getconf->get_server_config($app->functions->intval($web_rec["server_id"]), 'web');

		if($this->_vhostdomain_type == 'domain') {
			$document_root = str_replace("[website_id]", $this->id, $web_config["website_path"]);
			$document_root = str_replace("[website_idhash_1]", $this->id_hash($page_form->id, 1), $document_root);
			$document_root = str_replace("[website_idhash_2]", $this->id_hash($page_form->id, 1), $document_root);
			$document_root = str_replace("[website_idhash_3]", $this->id_hash($page_form->id, 1), $document_root);
			$document_root = str_replace("[website_idhash_4]", $this->id_hash($page_form->id, 1), $document_root);

			// get the ID of the client
			if($_SESSION["s"]["user"]["typ"] != 'admin' && !$app->auth->has_clients($_SESSION['s']['user']['userid'])) {
				$client_group_id = $app->functions->intval($_SESSION["s"]["user"]["default_group"]);
				$client = $app->db->queryOneRecord("SELECT client_id FROM sys_group WHERE sys_group.groupid = $client_group_id");
				$client_id = $app->functions->intval($client["client_id"]);
			} else {
				//$client_id = $app->functions->intval($this->dataRecord["client_group_id"]);
				$client = $app->db->queryOneRecord("SELECT client_id FROM sys_group WHERE sys_group.groupid = ".$app->functions->intval($this->dataRecord["client_group_id"]));
				$client_id = $app->functions->intval($client["client_id"]);
			}

			// Set the values for document_root, system_user and system_group
			$system_user = $app->db->quote('web'.$this->id);
			$system_group = $app->db->quote('client'.$client_id);
			$document_root = str_replace("[client_id]", $client_id, $document_root);
			$document_root = str_replace("[client_idhash_1]", $this->id_hash($client_id, 1), $document_root);
			$document_root = str_replace("[client_idhash_2]", $this->id_hash($client_id, 2), $document_root);
			$document_root = str_replace("[client_idhash_3]", $this->id_hash($client_id, 3), $document_root);
			$document_root = str_replace("[client_idhash_4]", $this->id_hash($client_id, 4), $document_root);
			$document_root = $app->db->quote($document_root);
			$php_open_basedir = str_replace("[website_path]", $document_root, $web_config["php_open_basedir"]);
			$php_open_basedir = $app->db->quote(str_replace("[website_domain]", $web_rec['domain'], $php_open_basedir));
			$htaccess_allow_override = $app->db->quote($web_config["htaccess_allow_override"]);
			$added_date = date($app->lng('conf_format_dateshort'));
			$added_by = $app->db->quote($_SESSION['s']['user']['username']);

			$sql = "UPDATE web_domain SET system_user = '$system_user', system_group = '$system_group', document_root = '$document_root', allow_override = '$htaccess_allow_override', php_open_basedir = '$php_open_basedir', added_date = '$added_date', added_by = '$added_by'  WHERE domain_id = ".$this->id;
		} else  {
			// Set the values for document_root, system_user and system_group
			$system_user = $app->db->quote($this->parent_domain_record['system_user']);
			$system_group = $app->db->quote($this->parent_domain_record['system_group']);
			$document_root = $app->db->quote($this->parent_domain_record['document_root']);
			$php_open_basedir = str_replace("[website_path]/web", $document_root.'/'.$web_rec['web_folder'], $web_config["php_open_basedir"]);
			$php_open_basedir = str_replace("[website_domain]/web", $web_rec['domain'].'/'.$web_rec['web_folder'], $php_open_basedir);
			$php_open_basedir = str_replace("[website_path]", $document_root, $php_open_basedir);
			$php_open_basedir = $app->db->quote(str_replace("[website_domain]", $web_rec['domain'], $php_open_basedir));
			$htaccess_allow_override = $app->db->quote($this->parent_domain_record['allow_override']);
			$added_date = date($app->lng('conf_format_dateshort'));
			$added_by = $app->db->quote($_SESSION['s']['user']['username']);

			$sql = "UPDATE web_domain SET sys_groupid = ".$app->functions->intval($this->parent_domain_record['sys_groupid']).",system_user = '$system_user', system_group = '$system_group', document_root = '$document_root', allow_override = '$htaccess_allow_override', php_open_basedir = '$php_open_basedir', added_date = '$added_date', added_by = '$added_by' WHERE domain_id = ".$this->id;
		}

		$app->db->query($sql);
	}

	function onBeforeUpdate () {
		global $app, $conf;

		if($this->_vhostdomain_type == 'domain') {
			//* Check if the server has been changed
			// We do this only for the admin or reseller users, as normal clients can not change the server ID anyway
			if($_SESSION["s"]["user"]["typ"] == 'admin' || $app->auth->has_clients($_SESSION['s']['user']['userid'])) {
				if (isset($this->dataRecord["server_id"])) {
					$rec = $app->db->queryOneRecord("SELECT server_id from web_domain WHERE domain_id = ".$this->id);
					if($rec['server_id'] != $this->dataRecord["server_id"]) {
						//* Add a error message and switch back to old server
						$app->tform->errorMessage .= $app->lng('The Server can not be changed.');
						$this->dataRecord["server_id"] = $rec['server_id'];
					}
					unset($rec);
				}
				//* If the user is neither admin nor reseller
			} else {
				//* We do not allow users to change a domain which has been created by the admin
				$rec = $app->db->queryOneRecord("SELECT sys_perm_group, domain, ip_address, ipv6_address from web_domain WHERE domain_id = ".$this->id);
				if(isset($this->dataRecord["domain"]) && $rec['domain'] != $this->dataRecord["domain"] && $app->tform->checkPerm($this->id, 'u')) {
					//* Add a error message and switch back to old server
					$app->tform->errorMessage .= $app->lng('The Domain can not be changed. Please ask your Administrator if you want to change the domain name.');
					$this->dataRecord["domain"] = $rec['domain'];
				}
				if(isset($this->dataRecord["ip_address"]) && $rec['ip_address'] != $this->dataRecord["ip_address"] && $rec['sys_perm_group'] != 'riud') {
					$this->dataRecord["ip_address"] = $rec['ip_address'];
				}
				if(isset($this->dataRecord["ipv6_address"]) && $rec['ipv6_address'] != $this->dataRecord["ipv6_address"] && $rec['sys_perm_group'] != 'riud') {
					$this->dataRecord["ipv6_address"] = $rec['ipv6_address'];
				}
				unset($rec);
			}
		}

		//* Check that all fields for the SSL cert creation are filled
		if(isset($this->dataRecord['ssl_action']) && $this->dataRecord['ssl_action'] == 'create') {
			if($this->dataRecord['ssl_state'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_state_empty').'<br />';
			if($this->dataRecord['ssl_locality'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_locality_empty').'<br />';
			if($this->dataRecord['ssl_organisation'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_organisation_empty').'<br />';
			if($this->dataRecord['ssl_organisation_unit'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_organisation_unit_empty').'<br />';
			if($this->dataRecord['ssl_country'] == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_country_empty').'<br />';
		}

		if(isset($this->dataRecord['ssl_action']) && $this->dataRecord['ssl_action'] == 'save') {
			if(trim($this->dataRecord['ssl_cert']) == '') $app->tform->errorMessage .= $app->tform->lng('error_ssl_cert_empty').'<br />';
		}

	}
}

$page = new page_action;
$page->onLoad();

?>
