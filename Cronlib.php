<?php
require_once 'lib.php';
require_once 'Webservicelib.php';
require_once($CFG->libdir . '/gdlib.php');

class ProctorUCronProcessor {
    
    public function __construct(){
        $this->localDataStore = new LocalDataStoreClient();
        $this->puClient       = new ProctorUClient();
    }

    /**
     * CRON PHASES:
     * A.
     * 1. get all users without a status set as STATUS_UNKNOWN users
     * 2. partition STATUS_UNKNOWN set into EXEMPT and NON_EXEMPT
     * 3. update status fields for each group as appropriate
     * 
     * B. 
     * 1. Fetch all rows where the status is STATUS_UNACCEPTABLE
     * 2. attempt to process each of these users with STATUS_UNACCEPTABLE
     * 
     * C.
     * 1. For all unregeistered users, lookup pseudoID in DAS; IF NOT EXISTS, set DAS ERROR CODE
     * 2. for those that have PseudoIds, look them up in PU and set appropriate code
     *  a. do not allow more than a few 404 errors to happen, otherwise, account lockout
     * 
     * D.
     * 1. report errors, esp 404
     */

    /**
     * Gets all users without any value in their ProctorU 
     * registration status field and divides them into 
     * EXEMPT and UNREGISTERED groups.
     * 
     * @return array(array[object]) array of user objects for each group
     */
    public function objPartitionUsersWithoutStatus() {
        $all    = ProctorU::objGetAllUsersWithoutProctorStatus();
        $exempt = ProctorU::objGetExemptUsers();
        $unreg  = array_diff_key($all, $exempt);

        return array($unreg, $exempt);
    }
    
    /**
     * get users that need to be looked up again
     * NB that this function excludes the set of users who have
     * previously generated a 404 in the PU system.
     * PU deactivates the webservice account once the error count 
     * exceeds their quota.
     * 
     * @return object[] user objects
     */
    public function objGetUnverifiedUsers(){
        $all        = ProctorU::objGetAllUsers();
        $exempt     = ProctorU::objGetUsersWithStatus(ProctorU::EXEMPT);
        $verified   = ProctorU::objGetUsersWithStatus(ProctorU::VERIFIED);
        $pu404      = ProctorU::objGetUsersWithStatus(ProctorU::PU_NOT_FOUND);

        return array_diff_key($all, $exempt, $verified, $pu404);
    }

    /**
     * 
     * @param object[] $users users to set status for
     * @param int $status the status to set
     * @return int the number of records set
     */
    public function intSetStatusForUser($users, $status){

        if(!is_array($users)){
            if(!isset($users->id)){
                throw new Exception("user has no id");
            }
            mtrace(sprintf("Setting status %s for user %d", $status, $users->id));
            ProctorU::intSaveProfileFieldStatus($users->id, $status);
            return 1;
        }

        $i=0;
        foreach($users as $u){
            mtrace(sprintf("Setting status %s for user %d", $status, $u->id));
            ProctorU::intSaveProfileFieldStatus($u->id, $status);
            $i++;
        }
        return $i;
    }

    public function intGetPseudoID($idnumber){
        if($this->localDataStore->blnUserExists($idnumber)){
            return $this->localDataStore->intPseudoId($idnumber);
        }
        return false;
    }

    public function blnProcessUsers($users){        
        foreach($users as $u){

            //prepare user object
            //@TODO find a way to get this data in the initial query !!!
            global $DB;
            $idnumber = $DB->get_field('user','idnumber',array('id'=>$u->id));
            $u->idnumber = is_numeric($idnumber) ? $idnumber : null;

            //process user obj
            $status = $this->constProcessUser($u);
            $this->intSetStatusForUser($u, $status);
        }
    }

    public function constProcessUser($u){

        //handle the case where a user has no idnumber
        if(empty($u->idnumber)){
            mtrace(sprintf("No idnumber for user with id %d", $u->id));
            return ProctorU::NO_IDNUMBER;
        }

        //handle the case where we are looking up a 
        //non-online student in the online database;
        //By decree, these students are exempt from PU verification
        if(!$this->localDataStore->blnUserExists($u->idnumber)){
            mtrace(sprintf("User %d does not have the online profile, therefore, exempt\n", $u->id));
            return ProctorU::EXEMPT;
        }

        //fetch proxy id
        $pseudoID = $this->localDataStore->intPseudoId($u->idnumber);
        if($pseudoID == false){
            mtrace(sprintf("Pseudo id lookup failed with unknown error for user with id %d", $u->id));
            return ProctorU::UNREGISTERED;
        }

        //get PU status
        $puStatus = $this->puClient->constUserStatus($pseudoID);
        
        return $puStatus;
    }

    public function blnInsertPicture($path, $userid){
        global $DB;
        $context = context_user::instance($userid);
        process_new_icon($context, 'user', 'icon', 0, $path);
        $DB->set_field('user', 'picture', 1, array('id' => $userid));
    }
    
    public static function emailAdmins($msg){
        global $CFG,$DB;

        $from = $CFG->wwwroot;
        
        $adminIds     = explode(',',$CFG->siteadmins);
        $admins = $DB->get_records_list('user', 'id',$adminIds);
        
        foreach($admins as $a){
            email_to_user($a, $from, 'proctoru message', $msg);
        }
    }
}
?>
