<?php
// URL des CaseTime Servers
define('CASETIME_SERVER','http://localhost:8080');

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

// date of casetime timesheet go-live (=first possible date to create monthlists)
define('CASETIME_TIMESHEET_GOLIVE', '2018-09-01');

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
?>
