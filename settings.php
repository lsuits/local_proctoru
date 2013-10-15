<?php

defined('MOODLE_INTERNAL') || die;
global $DB;

if ($hassiteconfig) {
    
    $settings = new admin_settingpage('local_proctoru', get_string('mod_name','local_proctoru'));

    $roles = role_get_names(null, null, true);

    $exemptRoles = array('student');

    
    $settings->add(
            new admin_setting_configmultiselect(
                    'local_proctoru/roleselection',
                    get_string('roleselection_label', 'local_proctoru'),
                    get_string('roleselection_description', 'local_proctoru'),
                    $exemptRoles,
                    $roles
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/profilefield_shortname',
                    get_string('profilefield_shortname', 'local_proctoru'),
                    get_string('profilefield_shortname_description', 'local_proctoru'),
                    ''
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/proctoru_token',
                    get_string('proctoru_token', 'local_proctoru'),
                    get_string('proctoru_token_description', 'local_proctoru'),
                    ''
            )
    );
    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/proctoru_api',
                    get_string('proctoru_api', 'local_proctoru'),
                    get_string('proctoru_api_description', 'local_proctoru'),
                    ''
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/credentials_location',
                    get_string('credentials_location', 'local_proctoru'),
                    get_string('credentials_location_description', 'local_proctoru'),
                    ''
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/localwebservice_url',
                    get_string('localwebservice_url', 'local_proctoru'),
                    get_string('localwebservice_url_description', 'local_proctoru'),
                    ''
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/localwebservice_userexists_servicename',
                    get_string('localwebservice_userexists_servicename', 'local_proctoru'),
                    get_string('localwebservice_userexists_servicename_description', 'local_proctoru'),
                    ''
            )
    );
    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/localwebservice_fetchuser_servicename',
                    get_string('localwebservice_fetchuser_servicename', 'local_proctoru'),
                    get_string('localwebservice_fetchuser_servicename_description', 'local_proctoru'),
                    ''
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/stu_profile',
                    get_string('stu_profile', 'local_proctoru'),
                    get_string('stu_profile_description', 'local_proctoru'),
                    '')
    );

    $settings->add(
            new admin_setting_configcheckbox(
                    'local_proctoru/bool_cron',
                    get_string('cron_run', 'local_proctoru'),
                    get_string('cron_desc', 'local_proctoru'),
                    true, true, false)
    );
    
    $ADMIN->add('localplugins', $settings);
}
?>
