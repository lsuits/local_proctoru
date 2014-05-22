<?php
require_once $CFG->dirroot.'/blocks/sentinel/lib.php';
require_once $CFG->dirroot.'/local/proctoru/lib.php';

/**
 * proxy class exists soley so that block_sentinel may call its method
 */
class local_proctoru implements Sentinel {
    public static function allowUser(stdClass $user) {
        $acceptableStatus = ProctorU::blnUserHasAcceptableStatus($user->id);
        $hasExemptRole    = ProctorU::blnUserHasExemptRole($user->id);

        if ($acceptableStatus or $hasExemptRole or is_siteadmin()) {
            return true;
        }else{
            return false;
        }
    }
}
?>
