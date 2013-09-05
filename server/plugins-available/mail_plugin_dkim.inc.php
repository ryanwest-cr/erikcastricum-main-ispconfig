<?php

/*
Copyright (c) 2007 - 2013, Till Brehm, projektfarm Gmbh
Copyright (c) 2013, Florian Schaal, info@schaal-24.de
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

class mail_plugin_dkim {

	var $plugin_name = 'mail_plugin_dkim';
	var $class_name = 'mail_plugin_dkim';

	// private variables
	var $action = '';

	/* 
		This function is called during ispconfig installation to determine
		if a symlink shall be created for this plugin.
	*/
	function onInstall() {
		global $conf;

		if($conf['services']['mail'] == true) {
			return true;
		} else {
			return false;
		}

	}

	/*
	 	This function is called when the plugin is loaded
	*/
	function onLoad() {
		global $app,$conf;
		/*
		Register for the events
		*/
		$app->plugins->registerEvent('mail_domain_delete',$this->plugin_name,'domain_dkim_delete');
		$app->plugins->registerEvent('mail_domain_insert',$this->plugin_name,'domain_dkim_insert');
		$app->plugins->registerEvent('mail_domain_update',$this->plugin_name,'domain_dkim_update');
	}

        /*
                This function gets the amavisd-config file
        */
	function get_amavis_config() {
		$pos_config=array(
			'/etc/amavisd.conf',
			'/etc/amavisd.conf/50-user',
			'/etc/amavis/conf.d/50-user'
		);
		$amavis_configfile='';
                foreach($pos_config as $conf) {
			if (is_file($conf)) {
				$amavis_configfile=$conf;
				break;
			}
                }
		return $amavis_configfile;
	}

	/*
		This function checks the relevant configs and disables dkim for the domain 
		if the directory for dkim is not writeable or does not exist
	*/
	function check_system($data) {
		global $app,$mail_config;
                $app->uses('getconf');
		$check=true;
		/* check for amavis-config */
		if ( $this->get_amavis_config() == '' || !is_writeable($this->get_amavis_config()) ) {
			$app->log('Amavis-config not found or not writeable.',LOGLEVEL_ERROR);
			$check=false;
		}
		/* dir for dkim-keys writeable? */
                $mail_config = $app->getconf->get_server_config($conf['server_id'], 'mail');
		if (isset($mail_config['dkim_path']) && isset($data['new']['dkim_private']) && !empty($data['new']['dkim_private'])) {
			if (!is_writeable($mail_config['dkim_path'])) {
				$app->log('DKIM Path '.$mail_config['dkim_path'].' not found or not writeable.',LOGLEVEL_ERROR);
				$check=false;
               		}
		} else {
			$app->log('Unable to write DKIM settings. Check your config!',LOGLEVEL_ERROR);
			$check=false;
		}
		return $check;
	}
        
	/*
		This function restarts amavis
	*/
	function restart_amavis() {
		global $app,$conf;
		$initfile=$conf['init_scripts'].'/amavis';
		$app->log('Reloading amavis.',LOGLEVEL_DEBUG);
		exec(escapeshellarg($conf['init_scripts']).escapeshellarg('/amavis').' reload',$output);
		foreach($output as $logline) $app->log($logline,LOGLEVEL_DEBUG);
	}

	/*
                This function writes the keyfiles (public and private)
        */
	function write_dkim_key($key_file,$key_value,$key_domain) {
                global $app,$mailconfig;
		$success=false;
		if (!file_put_contents($key_file.'.private',$key_value) === false) { 
			$app->log('Saved DKIM Private-key to '.$key_file.'.private',LOGLEVEL_DEBUG);
			$success=true;
			/* now we get the DKIM Public-key */
			exec('cat '.escapeshellarg($key_file.'.private').'|openssl rsa -pubout',$pubkey,$result);
			$public_key='';
			foreach($pubkey as $values) $public_key=$public_key.$values."\n";
			/* save the DKIM Public-key in dkim-dir */
			if (!file_put_contents($key_file.'.public',$public_key) === false) 
				$app->log('Saved DKIM Public to '.$key_domain.'.',LOGLEVEL_DEBUG);
			else $app->log('Unable to save DKIM Public to '.$key_domain.'.',LOGLEVEL_WARNING);
		} 
		return $success;
	}

	/*
		This function removes the keyfiles
	*/
	function remove_dkim_key($key_file,$key_domain) {
		global $app;
		if (file_exists($key_file.'.private')) {
			exec('rm -f '.escapeshellarg($key_file.'.private'));
			$app->log('Deleted the DKIM Private-key for '.$key_domain.'.',LOGLEVEL_DEBUG);
		} else $app->log('Unable to delete the DKIM Private-key for '.$key_domain.' (not found).',LOGLEVEL_DEBUG);
		if (file_exists($key_file.'.public')) {
			exec('rm -f '.escapeshellarg($key_file.'.public'));
			$app->log('Deleted the DKIM Public-key for '.$key_domain.'.',LOGLEVEL_DEBUG);
		} else $app->log('Unable to delete the DKIM Public-key for '.$key_domain.' (not found).',LOGLEVEL_DEBUG);
	}

	/*
		This function adds the entry to the amavisd-config
	*/
	function add_to_amavis($key_domain) {
		global $app,$mail_config;
		$amavis_config = file_get_contents($this->get_amavis_config());
	       	$key_value="dkim_key('".$key_domain."', 'default', '".$mail_config['dkim_path']."/".$key_domain.".private');\n";
       	        if(strpos($amavis_config, $key_value) !== false) $amavis_config = str_replace($key_value, '', $amavis_config);
		if (!file_put_contents($this->get_amavis_config(),$key_value,FILE_APPEND) === false) {
			$app->log('Adding DKIM Private-key to amavis-config.',LOGLEVEL_DEBUG);
			$this->restart_amavis();
		}
	}

	/*
		This function removes the entry from the amavisd-config
	*/
	function remove_from_amavis($key_domain) {
		global $app;
		$amavis_config = file($this->get_amavis_config());
		$i=0;$found=false;
		foreach($amavis_config as $line) {
			if (preg_match("/^\bdkim_key\b.*\b".$key_domain."\b/",$line)) {
				unset($amavis_config[$i]);
				$found=true;
			}
			$i++;
		}
		if ($found) {
			file_put_contents($this->get_amavis_config(), $amavis_config);
			$app->log('Deleted the DKIM settings from amavis-config for '.$key_domain.'.',LOGLEVEL_DEBUG);
			$this->restart_amavis();
		} else $app->log('Unable to delete the DKIM settings from amavis-config for '.$key_domain.'.',LOGLEVEL_ERROR);
	}

	/*
		This function controlls new key-files and amavisd-entries
	*/
	function add_dkim($data) {
		global $app;
                $mail_config = $app->getconf->get_server_config($conf['server_id'], 'mail');
		if ( substr($mail_config['dkim_path'],strlen($mail_config['dkim_path'])-1) == '/' )
			$mail_config['dkim_path'] = substr($mail_config['dkim_path'],0,strlen($mail_config['dkim_path'])-1);
		if ($this->write_dkim_key($mail_config['dkim_path']."/".$data['new']['domain'],$data['new']['dkim_private'],$data['new']['domain'])) {
	       	        $this->add_to_amavis($data['new']['domain']);
		} else {
			$app->log('Error saving the DKIM Private-key for '.$data['new']['domain'].' - DKIM is not enabled for the domain.',LOGLEVEL_ERROR);
		}
	}

	/*
		This function controlls the removement of keyfiles (public and private)
		and the entry in the amavisd-config
	*/
	function remove_dkim($_data) {
		global $app;
                $mail_config = $app->getconf->get_server_config($conf['server_id'], 'mail');
		if ( substr($mail_config['dkim_path'],strlen($mail_config['dkim_path'])-1) == '/' )
			$mail_config['dkim_path'] = substr($mail_config['dkim_path'],0,strlen($mail_config['dkim_path'])-1);
		$this->remove_dkim_key($mail_config['dkim_path']."/".$_data['domain'],$_data['domain']);
               	$this->remove_from_amavis($_data['domain']);
	}

	/*
		Functions called by onLoad
	*/
	function domain_dkim_delete($event_name,$data) {
		if (isset($data['old']['dkim']) && $data['old']['dkim'] == 'y') $this->remove_dkim($data['old']);
	}

	function domain_dkim_insert($event_name,$data) {
		if (isset($data['new']['dkim']) && $data['new']['dkim']=='y' && $this->check_system($data)) {
			$this->add_dkim($data);
		}
	}

	function domain_dkim_update($event_name,$data) {
		global $app;
                /* get the config */
		if (isset($data['new']['dkim']) && $data['new']['dkim']=='y') { /* DKIM enabled */
			if ($this->check_system($data)) {
				/* new domain-name */
        		        if ($data['old']['domain'] != $data['new']['domain']) {
					$this->remove_dkim($data['old']);
					$this->add_dkim($data);
				}
				/* new key */
        		        if (($data['old']['dkim_private'] != $data['new']['dkim_private']) || ($data['old']['dkim'] != $data['new']['dkim'])) {
				if ($data['new']['dkim_private'] != $data['old']['dkim_private']) $this->remove_dkim($data['new']);
					$this->add_dkim($data);
				}
				/* change active (on / off) */
                		if ($data['old']['active'] != $data['new']['active']) {
                        		if ($data['new']['active'] == 'y') {
	                        		$this->add_dkim($data);
					} else {
        	                		$this->remove_dkim($data['new']);
	        	                }
        	        	}
			}	
		}
		if (isset($data['new']['dkim']) && $data['old']['dkim'] != $data['new']['dkim'])
			if ($this->check_system($data) && $data['new']['dkim'] == 'n') $this->remove_dkim($data['new']);
	}
}
?>