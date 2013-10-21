<?php

function xmldb_local_proctoru_uninstall() {
    global $DB;
    $fieldid = $DB->get_field('user_info_field', 'id', 
            array('shortname'=>ProctorU::_c('profilefield_shortname')));
    
    if($DB->delete_records('user_info_data', array('fieldid'=>$fieldid))){
        if($DB->delete_records('user_info_field', array('id'=>$fieldid))){
            return true;
        }
    }
    
    return false;
}

?>
