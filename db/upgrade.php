<?php

function xmldb_local_proctoru_upgrade($oldversion = 0) {
    global $DB;

    $result = true;
    
    if($oldversion <= 2013101510){
//    update mdl_config_plugins

//    set localwebservice_userexists_servicename = userid_service
        $oldServName = $DB->get_record('config_plugins', array('plugin'=>'local_proctoru', 'name'=>'localwebservice_userexists_servicename'));
        $oldServName->name = 'eligible_users_service';
        if(!$DB->update_record('config_plugins', $oldServName)){
            $result = false;
        }

    //    set localwebservice_userexists_servicename = eligible_users_service
        $oldServName2 = $DB->get_record('config_plugins', array('plugin'=>'local_proctoru', 'name'=>'localwebservice_fetchuser_servicename'));
        $oldServName2->name = 'userid_service';
        if(!$DB->update_record('config_plugins', $oldServName2)){
            $result = false;
        }
    }
    
    return $result;
}
?>
