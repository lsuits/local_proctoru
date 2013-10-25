<?php
global $CFG;
require_once $CFG->dirroot . '/local/proctoru/lib.php';

//insert profile field
function xmldb_local_proctoru_install(){
    ProctorU::default_profile_field();
}
?>
