<?php

$string['mod_name']   = "Proctor U";
$string['pluginname'] = "Proctor U";
$string['franken_name']  = 'local_proctoru';

//status codes
$string['unregistered']      = 'Unregistered';
$string['registered']        = 'Registered';
$string['verified']          = 'Verified';
$string['exempt']            = 'Exempt';
$string['sam_profile_error'] = 'Ineligible Profile';
$string['no_idnumber']       = 'NO IDNUMBER';
$string['pu_404']            = '404 PrU';

// roles to consider exempt
$string['roleselection'] = 'roleselection';
$string['roleselection_label'] = 'Roles Exempt';
$string['roleselection_description'] = 'which roles should be excluded from the PU lookup';

// custom profile field
$string['profilefield_default_shortname'] = 'proctoru';
$string['profilefield_shortname'] = "Custom role name";
$string['profilefield_shortname_description'] = "Name of the custom profile field";

$string['profilefield_longname'] = "Custom role long name";
$string['profilefield_longname_description'] = "Full name of the custom profile field";

//$string['user_proctoru'] = "ProctorU Registration status";

// ProctorU API details
$string['proctoru_token'] = 'ProctorU token';
$string['proctoru_token_description'] = 'API token';

$string['proctoru_api'] = "ProctorU Profile API";
$string['proctoru_api_description'] = "URL for the ProctorU API URL";


// LSU-specific local data store connection settings
$string['credentials_location'] = 'Credentials Location';
$string['credentials_location_description'] = 'Location of local webservices credentials';

// More LSU-specific local data store connection settings
$string['localwebservice_url'] = 'Local Datastore (LD)';
$string['localwebservice_url_description'] = "URL for the local datastore";

$string['userid_service'] = 'LD User ID Service';
$string['userid_service_description'] = "Local source for user ids";

$string['stu_profile'] = "LD Eligible User Profile";
$string['stu_profile_description'] = "Users Eligible for PU enrollment are distinuished by the presence of this profile in the LD.";

$string['eligible_users_service'] = 'LD Eligible Users Service';
$string['eligible_users_service_description'] = "Local API to verify whether users may have a PU Profile";

//report strings
$string['report_page_title'] = 'PU Registration Report';
$string['report_breadcrumb'] = '';
$string['report_head']       = "Current Registration Statistics";
$string['report_link_text']  = 'current stats are as follows...';
$string['report_not_auth']   = 'You are not authorized to view this resource';

//config_head
$string['config_head'] = "Configuration";

// cap
$string['proctoru:viewstats'] = 'View ProctorU registration statistics';

//exceptions
$string['wrong_protocol'] = 'URL protocol given in admin settings is malformed. Expected http/https, got {$a}';
$string['general_curl_exception'] = 'Exception thrown while making a webservice request from class {$a}';
$string['xml_exception'] = 'class \'{$a->cls}\' generated an exception while trying to convert the response from {$a->url} to XML. Original exception message was \'{$a->msg}\'';
$string['missing_credentials'] = 'Missing one or both expected values in response from Credentials Client.';
$string['datastore_errors'] = 'Problem obtaining data for service {$a->srv}, message was {$a->msg}';
$string['pu_404'] = 'Got 404 for user with PU id# {$a->uid}, full response was:{$a->msg}';
$string['profilefield_not_foud'] = 'attempt to filter by non-existent profile field; check your field shortname exists.';
$string['exception_envelope'] = 'caught Exception of type {$a->cls}: {$a->hln}; Message was: {$a->msg} Stack trace: {$a->trc}';

//output
$string['start_task'] = 'Running ProctorU task';
$string['toplevel_datastore_exception'] = '!!!Trouble initializing LocalDataStore component of the CronProcessor: {$a->msg} | {$a->trc} Aborting ProctorU tasks\n';
$string['toplevel_credentials_exception'] = '!!!Trouble initializing CredentialsClient component of the CronProcessor: {$a->msg} {$a->trc} Aborting ProctorU tasks.';
$string['toplevel_generic_exception'] = '!!!Trouble initializing CronProcessor:{$a->msg} Aborting ProctorU tasks';
$string['general_exception'] = 'caught exception while processing users; aborting...';

// task
$string['task_name'] = 'Proctor Users';