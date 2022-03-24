<?php
/**
Copyright (c) 2007-2022, Till Brehm, projektfarm Gmbh
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

require_once 'compatibility.inc.php';

// Set timezone
if(isset($conf['timezone']) && $conf['timezone'] != '') {	// note: !empty($conf['timezone']) should give the same result and is more idiomatic for current versions of PHP (gwyneth 20220315)
	date_default_timezone_set($conf['timezone']);
}

/**
 * Class for defining (mostly static) methods that are commonly used across the whole application.
 *
 * @category unknown
 * @package server
 * @author Till Brehm
 * @license bsd-3-clause
 * @link empty
 **/
class app {
	/** @var array	List of modules that have been loaded. */
	var $loaded_modules = [];
	/** @var array	List of plugins that have been loaded. */
	var $loaded_plugins = [];
	/** @var callable	Script calling this. */
	var $_calling_script = '';
	/** @var resource?	Database used for ISPConfig3. */
	public $db;

	/**
	 * Class constructor, which depends on the global configuration stored in $conf.
	 *
	 * @param void
	 * @return void
	 */
	function __construct() {
		/** @var object Global object storing this application's configuration. */
		global $conf;

		if($conf['start_db'] == true) {
			$this->load('db_' . $conf['db_type']);
			try {
				$this->db = new db;
			} catch(Exception $e) {
				$this->db = false;
			}

			/*
				Initialize the connection to the master DB,
				if we are in a multiserver setup
			*/

			if($conf['dbmaster_host'] != '' && ($conf['dbmaster_host'] != $conf['db_host'] || ($conf['dbmaster_host'] == $conf['db_host'] && $conf['dbmaster_database'] != $conf['db_database']))) {
				try {
					$this->dbmaster = new db($conf['dbmaster_host'], $conf['dbmaster_user'], $conf['dbmaster_password'], $conf['dbmaster_database'], $conf['dbmaster_port'], $conf['dbmaster_client_flags']);
				} catch (Exception $e) {
					$this->dbmaster = false;
				}
			} else {
				$this->dbmaster = $this->db;
			}
		}
	} // end constructor

	/**
	 * Getter method for some of the (valid) proprieties.
	 *
	 * @param string $name	A valid property name to get. Will be checked for validity first!
	 *
	 * @return mixed
	 */
	public function __get($name) {
		/** @var array List of all possible proprieties that are valid to get. */
		$valid_names = ['functions', 'getconf', 'letsencrypt', 'modules', 'plugins', 'services', 'system'];
		if(!in_array($name, $valid_names)) {
			trigger_error('Undefined property ' . $name . ' of class app', E_USER_WARNING);
		}
		if(property_exists($this, $name)) {
			return $this->{$name};
		}
		$this->uses($name);
		if(property_exists($this, $name)) {
			return $this->{$name};
		} else {
			trigger_error('Undefined property ' . $name . ' of class app', E_USER_WARNING);
		}
	}

	/**
	 * Sets the calling script.
	 *
	 * @param callable $caller	Calling script function.
	 *
	 * @return void
	 */
	function setCaller($caller) {
		$this->_calling_script = $caller;
	}

	/**
 	* Gets the calling script.
	*
	* Note that there is no error checking!
 	*
 	* @param void
 	*
 	* @return callable|null
 	*/
	function getCaller() {
		return $this->_calling_script;
	}

	/**
	 * Emergency exit funcion.
	 *
	 * @param string $errmsg	Error message to be displayedby the die() command on exit.
	 *
	 * @return void
	 */
	function forceErrorExit($errmsg = 'undefined') {
		global $conf;

		if($this->_calling_script == 'server') {
			@unlink($conf['temppath'] . $conf['fs_div'] . '.ispconfig_lock');
		}
		die('Exiting because of error: ' . $errmsg);
	}

	/**
	 * Dynamic plugin loader and instantiator.
	 *
	 * This will include PHP scripts on demand, each representing a class to be loaded,
	 * and if the process succeeds, it will retrieve an instance for the class.
	 *
	 * @param string $classes	A list of plugin classes to be loaded (e.g. their files will be included)
	 *							and subsequently instantiated; it's a comma-separated string.
	 *
	 * @return void
	 */
	function uses($classes) {
		global $conf;

		/** @var array|null List of classes to be used, as an array, after successful 'explosion' */
		$cl = explode(',', $classes);
		if(is_array($cl)) {
			foreach($cl as $classname) {
				if(!@is_object($this->$classname)) {
					if(is_file($conf['classpath'] . '/' . $classname . '.inc.php') && (DEVSYSTEM || !is_link($conf['classpath'] . '/' . $classname . '.inc.php'))) {
						include_once $conf['classpath'] . '/' . $classname . '.inc.php';
						$this->$classname = new $classname;
					}
				}
			}
		}
	}

	/**
 	* Dynamic plugin loader (no instantation).
 	*
	* Similar to uses() but does _not_ instantate a new class; files are merely included.
	* die() is called on a failure to include the file for a class.
	*
	* @param string $classes	A list of plugin classes to be loaded (e.g. their files will be included);
	*							it's a comma-separated string.
	*
 	* @return void
 	*/
	function load($classes) {
		global $conf;

		/** @var array|null List of classes to be loaded, as an array, after successful 'explosion' */
		$cl = explode(',', $classes);
		if(is_array($cl)) {
			foreach($cl as $classname) {
				if(is_file($conf['classpath'] . '/' . $classname . '.inc.php') && (DEVSYSTEM || !is_link($conf['classpath'] . '/' . $classname . '.inc.php'))) {
					include_once $conf['classpath'] . '/' . $classname . '.inc.php';
				} else {
					die('Unable to load: ' . $conf['classpath'] . '/' . $classname . '.inc.php');
				}
			}
		}
	}

	/**
  	* Logs a message with a certain priority to the different log backends.
  	*
  	* This method will check if the priority is equal or larger than what the user has
	* defined as the minimum logging level, and will output to several logging facilities:
	*  - At the very least, the message will _usually_ go to stdout;
	*  - It may optionally also go to the file log (usually `/var/log/ispconfig/ispconfig.log`)
	*      which will be created if it doesn't exist;
	*  - When the $dblog parameter is set to true (the default), the message will also be logged
	*      to the database;
	*  - If the system is configured to send email messages to the administrator,
	*      this method will also handle those (assuming, again, that the priority matches).
	*
	* Debugging messages will also have the name of the calling module/script as well as a line number
	*   to assist error tracking (gwyneth 20220315). This incurs in a slight performance hit.
	*
  	* @param string $msg	The message to be logged.
  	* @param int $priority	Should be set to 0 = DEBUG, 1 = WARNING or 2 = ERROR; anything else
	*   will skip setting the priority textual variable.
	* @param bool $dblog	Should the message also be logged to the database? (Default is _true_)
  	*
  	* @return void
	*
	* @note The error() method below seems to write to an invalid priority (3), which will cause
	* no message priority text to be emitted, and will _force_ a database write and/or sending
	* an email to the administrator.
  	*/
	function log($msg, $priority = 0, $dblog = true) {
		global $conf;

		/**
		 * @var string $file_line_caller
		 *
		 * For debugging, deal with retrieving caller information from the stack. (gwyneth 20220315)
		 * See https://stackoverflow.com/q/1252529/1035977 (including the precious comments!) for an explanation
		 * of how this works.
		 **/
		$file_line_caller = "";
		/** @var string	Defined here because recent versions of PHP are stricter with scoping issues. (gwyneth 20220315) */
		$priority_txt = '';

		switch ($priority) {
		case 0:
				$priority_txt = 'DEBUG';
				$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);	// we don't need _all_ data, so we save some processing time here (gwyneth 20220315)
				$caller = array_shift($bt);
				if(!empty($caller['file']) && !empty($caller['line'])) {
					$file_line_caller = '[' . strtr(basename($caller['file'], '.php'), '_', ' ') . ':' . $caller['line'] . '] ';
				}
				break;
		case 1:
				$priority_txt = 'WARNING';
				break;
		case 2:
				$priority_txt = 'ERROR';
				break;
		// Note: $this->error() seems to use case 3 to deliberately skip setting a priority text.
		// It will also *force* a write to the logs and/or send emails. (gwyneth 20220315)
		}

		/** @var string Formatted message to be sent to the logging subsystems. */
		$log_msg = @date('d.m.Y-H:i') . ' - ' . $priority_txt . ' ' . $file_line_caller . '- '. $msg;

		// Check if the user-set priority defines that this message should be output at all.
		if($priority >= $conf['log_priority']) {
			// Prepare to add a line on the logfile, or to create the logfile in
			// append mode if it doesn't exist yet. Failure means that die() is called.

			//if(is_writable($conf["log_file"])) {
			if(!$fp = fopen($conf['log_file'], 'a')) {
				die('Unable to open logfile.');
			}

			if(!fwrite($fp, $log_msg . "\r\n")) {
				die('Unable to write to logfile.');
			}

			echo $log_msg . "\n";
			fclose($fp);

			// Log to database.
			if($dblog === true && isset($this->dbmaster)) {
				$server_id = $conf['server_id'];
				$loglevel = $priority;
				$message = $msg;
				$datalog_id = (isset($this->modules->current_datalog_id) && $this->modules->current_datalog_id > 0)? $this->modules->current_datalog_id : 0;
				if($datalog_id > 0) {
					$tmp_rec = $this->dbmaster->queryOneRecord("SELECT count(syslog_id) as number FROM sys_log WHERE datalog_id = ? AND loglevel = ?", $datalog_id, LOGLEVEL_ERROR);
					//* Do not insert duplicate errors into the web log.
					if($tmp_rec['number'] == 0) {
						$sql = "INSERT INTO sys_log (server_id,datalog_id,loglevel,tstamp,message) VALUES (?, ?, ?, UNIX_TIMESTAMP(), ?)";
						$this->dbmaster->query($sql, $server_id, $datalog_id, $loglevel, $message);
					}
				} else {
					$sql = "INSERT INTO sys_log (server_id,datalog_id,loglevel,tstamp,message) VALUES (?, 0, ?, UNIX_TIMESTAMP(), ?)";
					$this->dbmaster->query($sql, $server_id, $loglevel, $message);
				}
			}

			//} else {
			//    die("Unable to write to logfile.");
			//}

		} // if

		// Send an email to the administrator if the current priority demands it.
		if(isset($conf['admin_notify_priority']) && $priority >= $conf['admin_notify_priority'] && $conf['admin_mail'] != '') {
			if($conf['hostname'] != 'localhost' && $conf['hostname'] != '') {
				$hostname = $conf['hostname'];
			} else {
				$hostname = exec('hostname -f');
			}
			// Send notification to admin.
			$mailBody         = $hostname . " - " . $log_msg;
			$mailSubject      = substr("[" . $hostname . "]" . " " . $log_msg, 0, 70) . '...';
			$mailHeaders      = "MIME-Version: 1.0" . "\n";
			$mailHeaders     .= "Content-type: text/plain; charset=utf-8" . "\n";
			$mailHeaders     .= "Content-Transfer-Encoding: 8bit" . "\n";
			$mailHeaders     .= "From: ". $conf['admin_mail'] . "\n";
			$mailHeaders     .= "Reply-To: ". $conf['admin_mail'] . "\n";

			mail($conf['admin_mail'], $mailSubject, $mailBody, $mailHeaders);
		}
	} // func log

	/**
  	* Logs a message with an undefined priority (3) and dies.
  	*
  	* This method writes to an invalid/undefined priority level (3), which will cause
  	* no message priority text to be emitted, but will _force_ a database write and/or sending
  	* an email to the administrator.
  	*
  	* @param string $msg	The message to be logged.
  	*
  	* @return void
  	*/
	function error($msg) {
		$this->log($msg, 3);	// isn't this supposed to be error code 2? (gwyneth 20220315)
		die($msg);
	}
}

/**
 * @var \app $app
 *
 * Initialize application object.
 */
$app = new app;

?>
