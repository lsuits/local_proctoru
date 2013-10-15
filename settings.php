<?php

defined('MOODLE_INTERNAL') || die;

require_once $CFG->dirroot.'/local/proctoru/lib.php';

if ($hassiteconfig) {
    $_s = function($id, $a=null){
        return get_string($id,'local_proctoru',$a);
    };
    $br = function(){return html_writer::empty_tag('br');};
    
    $settings = new admin_settingpage('local_proctoru', $_s('mod_name'));
    
    //@TODO cleanup this entire section and conform to standard practice
    
    //report heading
    $counts ="";
    $rawCounts = ProctorU::dbrGetUserCountByStatus();
    
    foreach(ProctorU::mapRawUserCountToFriendlyNames($rawCounts) as $name => $count) {
        $counts .= sprintf("%s%s: %d",$br(),$name, $count->count);
    }
    
    $reportLinkUrl  = new moodle_url('/local/proctoru/report.php');
    $reportLinkText = html_writer::tag('a', "Full Report", array('href'=>$reportLinkUrl));
    
    $statsText = $_s('report_link_text').$br().$counts.$br().$br().$reportLinkText;
    
    $settings->add(
            new admin_setting_heading('report_link_head', $_s('report_head'), $statsText));

    $settings->add(
            new admin_setting_heading('config_head', $_s('config_head'),''));
    
    $roles       = role_get_names(null, null, true);
    $exemptRoles = array('teacher');

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
                    'local_proctoru/profilefield_longname',
                    get_string('profilefield_longname', 'local_proctoru'),
                    get_string('profilefield_longname_description', 'local_proctoru'),
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
