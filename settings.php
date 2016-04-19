<?php

defined('MOODLE_INTERNAL') || die;

require_once $CFG->dirroot.'/local/proctoru/lib.php';

if ($hassiteconfig) {

    $br = function(){return html_writer::empty_tag('br');};
    
    $settings = new admin_settingpage('local_proctoru', ProctorU::_s('mod_name'));
    
    //report heading
    $counts ="";
    $rawCounts = ProctorU::dbrGetUserCountByStatus();
    
    foreach(ProctorU::mapRawUserCountToFriendlyNames($rawCounts) as $name => $count) {
        $counts .= sprintf("%s%s: %d",$br(),$name, $count->count);
    }
    
    $reportLinkUrl  = new moodle_url('/local/proctoru/report.php');
    $reportLinkText = html_writer::tag('a', "Full Report", array('href'=>$reportLinkUrl));
    
    $statsText = ProctorU::_s('report_link_text').$br().$counts.$br().$br().$reportLinkText;
    
    $settings->add(
            new admin_setting_heading('report_link_head', ProctorU::_s('report_head'), $statsText));

    $settings->add(
            new admin_setting_heading('config_head', ProctorU::_s('config_head'),''));
    
    $roles       = role_get_names(null, null, true);
    $exemptRoles = array('teacher');

    $settings->add(
            new admin_setting_configmultiselect(
                    'local_proctoru/roleselection',
                    ProctorU::_s('roleselection_label'),
                    ProctorU::_s('roleselection_description'),
                    $exemptRoles,
                    $roles
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/profilefield_shortname',
                    ProctorU::_s('profilefield_shortname'),
                    ProctorU::_s('profilefield_shortname_description'),
                    ProctorU::_s('profilefield_default_shortname'),
                    PARAM_ALPHANUM
            )
    );
    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/profilefield_longname',
                    ProctorU::_s('profilefield_longname'),
                    ProctorU::_s('profilefield_longname_description'),
                    ''
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/credentials_location',
                    ProctorU::_s('credentials_location'),
                    ProctorU::_s('credentials_location_description'),
                    ''
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/localwebservice_url',
                    ProctorU::_s('localwebservice_url'),
                    ProctorU::_s('localwebservice_url_description'),
                    ''
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/eligible_users_service',
                    ProctorU::_s('eligible_users_service'),
                    ProctorU::_s('eligible_users_service_description'),
                    ''
            )
    );
    
    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/stu_profile',
                    ProctorU::_s('stu_profile'),
                    ProctorU::_s('stu_profile_description'),
                    '')
    );
    
    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/userid_service',
                    ProctorU::_s('userid_service'),
                    ProctorU::_s('userid_service_description'),
                    ''
            )
    );
    
    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/proctoru_api',
                    ProctorU::_s('proctoru_api'),
                    ProctorU::_s('proctoru_api_description'),
                    ''
            )
    );
    $settings->add(
            new admin_setting_configtext(
                    'local_proctoru/proctoru_token',
                    ProctorU::_s('proctoru_token'),
                    ProctorU::_s('proctoru_token_description'),
                    ''
            )
    );

    $ADMIN->add('localplugins', $settings);
}

?>
