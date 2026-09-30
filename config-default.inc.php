<?php
// URL des CaseTime Servers
define('CASETIME_SERVER','http://localhost:8080');

define('CASETIME_SERVER_IP', '10.129.0.15');
define('CASETIME_SERVER_PORT', '22');

// Sync-Start-Date Format: YYYY-MM-DD
// if not specified last 40 Days
define('CASETIME_SYNC_START_ABSOLUTE','');

// Mail address to send sync-log to
// leave empty to disable mailing
define('CASETIME_SYNC_ADMIN_EMAIL', '');
// Mail address to send DienstF entries to
define('CASETIME_SYNC_HR_EMAIL', '');

// user:password for authentication on casetime-server
// used in monatsliste.php
define('CASETIME_ZOPE_USER', '_user_');
define('CASETIME_ZOPE_PASS', '_pass_');

// user:password for authentication on casetime-server to retreive files
define('CASETIME_ZOPE_SYSUSER', '_user_');
define('CASETIME_ZOPE_SYSPASS', '_pass_');

// date of casetime timesheet go-live (=first possible date to create monthlists)
define('CASETIME_TIMESHEET_GOLIVE', '2018-09-01');

// Gibt an ob die All-In Summe in der Uebersicht angezeigt wird
define('CASETIME_SHOW_ALLINSUMME', true);

// casetime server errors that should block the sending of timesheet
define ('CASETIME_BLOCKING_ERR', serialize(
	array(
		'Tag fehlt',
		'kein Zeitmodell zugeordnet',
		'G erwartet',
		'nicht alle Pausen wurden korrekt beendet',
		'Werte sind nicht AKTUELL',
		'jedoch nur eine Pausenzeit von',
		'KO- bzw. GT-Stempelung'
	)));

// user array to be included in sync-log for CASETIME_SYNC_HR_EMAIL
define ('CASETIME_MSGLOG_HR_INCLUDE_USERS', serialize(
	array(
			'UID1',
			'UID2'
  )));

// HR Mail address for timesheet eMails
define('CASETIME_TIMESHEET_HR_EMAIL', '');

?>
