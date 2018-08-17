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
define('CASETIME_TIMESHEET_START', '01.01.1970');
?>
