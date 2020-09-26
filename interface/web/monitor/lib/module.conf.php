<?php

/*
 Config of the Module
 */
$module["name"]   = "monitor";
$module["title"]   = "top_menu_monitor";
$module["template"]  = "module.tpl.htm";
$module["tab_width"]    = '';
$module["startpage"]  = "monitor/show_sys_state.php?state=system";
$module['order']    = '60';

unset($items);
$items[] = array( 'title'  => "Overview",
	'target'  => 'content',
	'link' => 'monitor/show_sys_state.php?state=system',
	'html_id' => 'system');

$items[] = array( 'title'  => "System-Log",
	'target'  => 'content',
	'link' => 'monitor/log_list.php',
	'html_id' => 'system_log');

$items[] = array( 'title'  => 'Jobqueue',
	'target'  => 'content',
	'link' => 'monitor/datalog_list.php',
	'html_id' => 'jobqueue');

$items[] = array( 'title'  => 'Data Log History',
	'target'  => 'content',
	'link' => 'monitor/dataloghistory_list.php',
	'html_id' => 'dataloghistory');

$module["nav"][] = array( 'title' => 'System State (All Servers)',
	'open'  => 1,
	'items' => $items);


/*
 We need all the available servers on the left navigation.
 So fetch them from the database and add then to the navigation as dropdown-list
*/

$servers = $app->db->queryAllRecords("SELECT server_id, server_name FROM server order by server_name");

$dropDown = "<select id='server_id' onchange=\"ISPConfig.loadContent('monitor/show_sys_state.php?state=server&server=' + document.getElementById('server_id').value);\" class='form-control'>";
foreach ($servers as $server)
{
	$dropDown .= "<option value='" . $server['server_id'] . "|" . $server['server_name'] . "'>" . $server['server_name'] . "</option>";
}
$dropDown .= "</select>";

/*
 Now add them as dropdown to the navigation
 */
unset($items);
$items[] = array( 'title'  => $dropDown,
	'target'  => '', // no action!
	'link' => '',     // no action!
	'html_id' => 'select_server');

$module["nav"][] = array( 'title' => 'Server to Monitor',
	'open'  => 1,
	'items' => $items);

/*
  The first Server at the list is the server first selected
 */
$_SESSION['monitor']['server_id']   = $servers[0]['server_id'];
$_SESSION['monitor']['server_name'] = $servers[0]['server_name'];

/*
 * Clear and set the Navigation-Items
 */
unset($items);

$items[] = array( 'title'  => "CPU info",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=cpu_info',
	'html_id' => 'cpu_info');

$module["nav"][] = array( 'title' => 'Hardware-Information',
	'open'  => 1,
	'items' => $items);

/*
 * Clear and set the Navigation-Items
 */
unset($items);
$items[] = array( 'title'  => "Overview",
	'target'  => 'content',
	'link' => 'monitor/show_sys_state.php?state=server',
	'html_id' => 'server');

$items[] = array( 'title'  => "Update State",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=system_update',
	'html_id' => 'system_update');

$items[] = array( 'title'  => "RAID state",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=raid_state',
	'html_id' => 'raid_state');

$items[] = array( 'title'  => "Server load",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=server_load',
	'html_id' => 'serverload');

$items[] = array( 'title'  => "Disk usage",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=disk_usage',
	'html_id' => 'disk_usage');

$items[] = array( 'title'       => "MySQL Database size",
	'target'      => 'content',
	'link'        => 'monitor/show_data.php?type=database_size',
	'html_id' => 'database_usage');

$items[] = array( 'title'  => "Memory usage",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=mem_usage',
	'html_id' => 'mem_usage');

$items[] = array( 'title'  => "Services",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=services',
	'html_id' => 'services');

$items[] = array( 'title'  => "Monit",
	'target'  => 'content',
	'link' => 'monitor/show_monit.php',
	'html_id' => 'monit');

$items[] = array( 'title'  => "OpenVz VE BeanCounter",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=openvz_beancounter',
	'html_id' => 'openvz_beancounter');

$items[] = array( 'title'  => "Munin",
	'target'  => 'content',
	'link' => 'monitor/show_munin.php',
	'html_id' => 'munin');

$module["nav"][] = array( 'title' => 'Server State',
	'open'  => 1,
	'items' => $items);

/*
 * Clear and set the Navigation-Items
 */
unset($items);

$items[] = array( 'title'  => "Mail-Queue",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=mailq',
	'html_id' => 'mailq');

$items[] = array( 'title'  => "Mail-Log",
	'target'  => 'content',
	'link' => 'monitor/show_log.php?log=log_mail',
	'html_id' => 'log_mail');

$items[] = array( 'title'  => "Mail warn-Log",
	'target'  => 'content',
	'link' => 'monitor/show_log.php?log=log_mail_warn',
	'html_id' => 'log_mail_warn');

$items[] = array( 'title'  => "Mail err-Log",
	'target'  => 'content',
	'link' => 'monitor/show_log.php?log=log_mail_err',
	'html_id' => 'log_mail_err');

$items[] = array( 'title'  => "System-Log",
	'target'  => 'content',
	'link' => 'monitor/show_log.php?log=log_messages',
	'html_id' => 'log_messages');

$items[] = array( 'title'  => "ISPC Cron-Log",
	'target'  => 'content',
	'link' => 'monitor/show_log.php?log=log_ispc_cron',
	'html_id' => 'log_ispc_cron');

$items[] = array( 'title'  => "Freshclam-Log",
	'target'  => 'content',
	'link' => 'monitor/show_log.php?log=log_freshclam',
	'html_id' => 'log_freshclam');

$items[] = array( 'title'  => "Let's Encrypt log",
	'target'  => 'content',
	'link' => 'monitor/show_log.php?log=log_letsencrypt',
	'html_id' => 'log_letsencrypt');

$items[] = array( 'title'  => "Clamav-Log",
	'target'  => 'content',
	'link' => 'monitor/show_log.php?log=log_clamav',
	'html_id' => 'log_clamav');

$items[] = array( 'title'  => "RKHunter-Log",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=rkhunter',
	'html_id' => 'rkhunter');

$items[] = array( 'title'  => "fail2ban-Log",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=fail2ban',
	'html_id' => 'fai2ban');

/*
$items[] = array( 'title'  => "MongoDB-Log",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=mongodb',
	'html_id' => 'mongodb');
*/
$items[] = array( 'title'  => "IPTables",
	'target'  => 'content',
	'link' => 'monitor/show_data.php?type=iptables',
	'html_id' => 'iptables');

$module["nav"][] = array( 'title' => 'Logfiles',
	'open'  => 1,
	'items' => $items);
?>
