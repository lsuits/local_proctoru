<?php

global $CFG;
require_once $CFG->libdir . '/filelib.php';
require_once($CFG->dirroot.'/user/filters/profilefield.php');
require_once($CFG->dirroot.'/user/filters/yesno.php');
require_once 'Cronlib.php';

function handle_local_proctoru() {

    //format exception messages in a standard template
    $outputException = function(Exception $e, $headline){
        $class = get_class($e);
        
        $a      = new stdClass();
        $a->cls = $class;
        $a->hln = $headline;
        $a->msg = $e->getMessage();
        $a->trc = $e->getTraceAsString();
        
        $out    = ProctorU::_s('exception_envelope', $a);
        
        mtrace($out);
        ProctorUCronProcessor::emailAdmins($out);
    };

    mtrace(ProctorU::_s('start_task'));
    //ensure profile field exists
    ProctorU::default_profile_field();
    
    try{
        $cron = new ProctorUCronProcessor();
    }catch(ProctorUWebserviceLocalDataStoreException $e){
        
        $a      = new stdClass();
        $a->msg = $e->getMessage();
        $a->trc = $e->getTrace();
        
        $outputException($e,ProctorU::_s('toplevel_datastore_exception', $a));
        return true;
        
    }catch(ProctorUWebserviceCredentialsClientException $e){
        
        $a      = new stdClass();
        $a->msg = $e->getMessage();
        $a->trc = $e->getTrace();
        
        $outputException($e,ProctorU::_s('toplevel_credentials_exception', $a));
        return true;
        
    }catch(ProctorUException $e){
        
        $a      = new stdClass();
        $a->msg = $e->getMessage();
        
        $outputException($e,ProctorU::_s('toplevel_generic_exception', $a));
        return true;
    }

    //get users without status (new users)
    list($unreg,$exempt) = $cron->objPartitionUsersWithoutStatus();

    //set new users as unregistered
    $intUnreg = $cron->intSetStatusForUser($unreg, ProctorU::UNREGISTERED);
    mtrace(sprintf("Set status %s for %d of %d unregistered users.",
            ProctorU::UNREGISTERED, $intUnreg, count($unreg)));

    //set exempt status
    $intExempt = $cron->intSetStatusForUser($exempt, ProctorU::EXEMPT);
    mtrace(sprintf("Set status %s for %d of %d exempt users.",
            ProctorU::EXEMPT, $intExempt, count($exempt)));

    //get unverified users
    $needProcessing = $cron->objGetUnverifiedUsers();
    mtrace(sprintf("Begin processing user status for %d users", count($needProcessing)));
    try{
        // Add the users who need exemption processing to the list
        $needProcessing += $cron->checkExemptUsersForStudentStatus();
        $cron->blnProcessUsers($needProcessing);
    }
    catch(ProctorUException $e){
        $outputException($e,ProctorU::_s('general_exception'));
        return true;
    }

    return true;
}

class ProctorU {

    const UNREGISTERED  = 1;
    const REGISTERED    = 2;
    const VERIFIED      = 3;
    const EXEMPT        = 4;
    const SAM_HAS_PROFILE_ERROR = -1;
    const NO_IDNUMBER   = -2;
    const PU_NOT_FOUND  = -404;

    public static function _c($c){
        return get_config('local_proctoru', $c);
    }
    
    public static function _s($s, $a=null){
        $b = get_string('franken_name', 'local_proctoru');
        return get_string($s, $b, $a);
    }
    
    /**
     * Simply returns an array of the class constants
     * @return int[]
     */
    public static function arrStatuses(){
        return array(
            ProctorU::EXEMPT,
            ProctorU::NO_IDNUMBER, 
            ProctorU::PU_NOT_FOUND, 
            ProctorU::REGISTERED,
            ProctorU::SAM_HAS_PROFILE_ERROR,
            ProctorU::UNREGISTERED,
            ProctorU::VERIFIED);
    }
    /**
     * for a given const status, returns a human-freindly string
     */
    public static function strMapStatusToLangString($status){
        if(empty($status)) return ''; //necessary so that users without status do not cause array index errors

        $map = array(
            ProctorU::UNREGISTERED          => 'unregistered',
            ProctorU::REGISTERED            => 'registered',
            ProctorU::VERIFIED              => 'verified',
            ProctorU::EXEMPT                => 'exempt',
            ProctorU::SAM_HAS_PROFILE_ERROR => 'sam_profile_error',
            ProctorU::NO_IDNUMBER           => 'no_idnumber',
            ProctorU::PU_NOT_FOUND          => 'pu_404',
                );
        return ProctorU::_s($map[$status]);
    }
    
    /**
     * insert new record into {user_info_field}
     * @global type $DB
     * @param type $params
     * @return \stdClass
     */
    public static function default_profile_field() {
        global $DB;
        
        $shortname = ProctorU::_c( 'profilefield_shortname');
        if($shortname == false){
            $shortname = ProctorU::_s( 'profilefield_default_shortname');
        }

        if (!$field = $DB->get_record('user_info_field', array('shortname' => $shortname))) {
            $field              = new stdClass;
            $field->shortname   = $shortname;
            $field->name        = ProctorU::_c('profilefield_longname');
            $field->description = ProctorU::_s('profilefield_shortname');
            $field->descriptionformat = 1;
            $field->datatype    = 'text';
            $field->categoryid  = 1;
            $field->locked      = 1;
            $field->visible     = 1;
            $field->param1      = 30;
            $field->param2      = 2048;

            $field->id = $DB->insert_record('user_info_field', $field);
        }

        return $field;
    }
    
    /**
     * helper fn
     * @return string shortname of the custom field in the DB
     */
    public static function strFieldname() {
        return ProctorU::_c('profilefield_shortname');
    }
    
    /**
     * helper fn returning the record ID of the custom field
     * @global type $DB
     * @return int ID of the custom field
     */
    public static function intCustomFieldID(){
        global $DB;
        return $DB->get_field('user_info_field', 'id', array('shortname'=>self::strFieldname()));
    }

    /**
     * Simple DB lookup, directly in the {user_info_data} table,
     * for an occurence of the userid WHERE fieldid = ??
     * @global stdClass $USER
     * @return stdClass|false
     */
    public static function blnUserHasProctoruProfileFieldValue($userid) {
        global $DB;
        $result = $DB->record_exists('user_info_data',
                array('id'=>$userid, 'fieldid'=>self::intCustomFieldID()));
        
        return $result;
    }
    
    /**
     * Similar to @see ProctorU::blnUserHasProctoruProfileFieldValue()
     * except that returning boolean exists ?, we return the value in question
     * @global type $DB
     * @param type $userid
     * @return type
     */
    public static function constProctorUStatusForUserId($userid){
        global $DB;
        $status = $DB->get_field('user_info_data','data',
                array('userid'=>$userid, 'fieldid'=>self::intCustomFieldID()));

        return $status === false ? false : $status;
    }
    
    public static function blnUserHasAcceptableStatus($userid) {
        $status = self::constProctorUStatusForUserId($userid);
        
        if($status == ProctorU::VERIFIED || $status == ProctorU::EXEMPT){
            return true;
        }elseif(self::blnUserHasExemptRole($userid)){
            return true;
        }else{
            return false;
        }
    }
    
    public static function blnUserHasExemptRole($userid){
        global $DB;
        $exemptRoleIds = ProctorU::_c( 'roleselection');
        $sql = "SELECT id
                FROM {role_assignments} 
                WHERE roleid IN ({$exemptRoleIds}) AND userid = {$userid}";
                
        $intRoles = count($DB->get_records_sql($sql));
        return  $intRoles > 0 ? true : false;
    }
    
    /**
     * @global type $DB
     * @param int $userid
     * @param ProctorU $status one of the class constants
     * @return int insert id
     */
    public static function intSaveProfileFieldStatus($userid, $status){
        global $DB;
        $msg = sprintf("Setting ProctorU status for user %s: ", $userid);

        $fieldId = self::intCustomFieldID();

        $record  = $DB->get_record('user_info_data', array('userid'=>$userid, 'fieldid'=>$fieldId));
        
        if(!$record){
            $record          = new stdClass();
            $record->data    = $status;
            $record->userid  = $userid;
            $record->fieldid = $fieldId;
            
            mtrace(sprintf("%sInsert new record, status %s", $msg,$status));
            return $DB->insert_record('user_info_data',$record, true, false);
            
        }elseif($record->data != $status){
            
            mtrace(sprintf("%supdate from %s to %s.",$msg,$record->data,$status));
            $record->data = $status;
            return $DB->update_record('user_info_data',$record, false);
        }else{
            mtrace(sprintf("%s Already set - do nothing", $msg));
            return true;
        }
        
    }
    
    /**
     * Partial application of the datalib.php function get_users_listing tailored to 
     * the task at hand
     * 
     * Return filtered (if provided) list of users in site, except guest and deleted users.
     *
     * @param string $sort          PASSTHROUGH An SQL field to sort by
     * @param string $dir           PASSTHROUGH The sort direction ASC|DESC
     * @param int $page             PASSTHROUGH The page or records to return
     * @param int                   PASSTHROUGH $recordsperpage The number of records to return per page
     * @param string                PASSTHROUGH(|IGNORE) $search A simple string to search for
     * @param string $firstinitial  PASSTHROUGH Users whose first name starts with $firstinitial
     * @param string $lastinitial   PASSTHROUGH Users whose last name starts with $lastinitial
     * @param string $extraselect   An additional SQL select statement to append to the query
     * @param array  $extraparams   Additional parameters to use for the above $extraselect
     * @param stdClass $extracontext If specified, will include user 'extra fields'
     *   as appropriate for current user and given context
     * @return array Array of {@link $USER} records
     */
    public static function partial_get_users_listing($status= null,$sort='lastaccess', $dir='ASC', $page=0, $recordsperpage=0,
                               $search='', $firstinitial='', $lastinitial='')
    {

        // $status = PROCTORU::VERIFIED; 
        // echo $status;
        // the extraselect needs to vary to allow the user to specify 'is not empty', etc
        $proFilter  = new user_filter_profilefield('profile','Profile',1);

        if(!isset($status)){
            $extraselect = '';
            $extraparams = array();
        } else {
            //figure out which field key the filter function uses for our field
            $fieldKey       = null;
            $fieldShortname = ProctorU::_c( 'profilefield_shortname');
            
            foreach($proFilter->get_profile_fields() as $k=>$sn){

                if($sn == $fieldShortname){
                    $fieldKey = $k;
                }
            }
            
            if(is_null($fieldKey)){
                throw new Exception(ProctorU::_s('profilefield_not_foud'));
            }

            $data['profile']    = $fieldKey;
            $data['operator']   = 2;
            $data['value']      = $status;

            list($extraselect, $extraparams) = $proFilter->get_sql_filter($data);
        }

        //get filter for suspended users
        list($extraselect, $extraparams) = self::arrAddSuspendedUserFilter($extraselect, $extraparams);
        
        $extracontext = context_system::instance();
        
        return get_users_listing($sort,$dir,$page,$recordsperpage,$search,
                $firstinitial,$lastinitial, $extraselect, $extraparams, $extracontext);
    }
    
    public static function partial_get_users_listing_by_roleid($roleid){
        $roFilter     = new user_filter_courserole('role', 'Role', 1);
        $data         = array('value'=>false, 'roleid'=>$roleid, 'categoryid'=>0);
        $extracontext = context_system::instance();
        
        list($extraselectRo, $extraparamsRo) = $roFilter->get_sql_filter($data);
        
        //get filter for suspended users 
        list($extraselect, $extraparams) = self::arrAddSuspendedUserFilter($extraselectRo, $extraparamsRo);
        return get_users_listing('','',null,null,'',
            '','', $extraselect, $extraparams, $extracontext);
    }

    /**
     * helper function wrapping functionality needed in two fns;
     * Mainly exists to de-clutter the partial functions above and to
     * avoid repreated code.
     * @param string $extraselect
     * @param array $extraparams
     * @return array
     */
    private static function arrAddSuspendedUserFilter($extraselect, $extraparams){
        //exclude suspended users
        $suspFilter = new user_filter_yesno('suspended', 'Suspended',1,'suspended');
        $suspData   = array('value' => "0",);
        list($suspXSelect, $suspXParams) = $suspFilter->get_sql_filter($suspData);
        
        $extraselect .= " AND ".$suspXSelect;
        $extraparams += $suspXParams;
        
        return array($extraselect, $extraparams);
    }
    
    /**
     * Gets role rows frmo the DB that are in the admin setting 'roles to exempt'
     * @global type $DB
     * @return object[] role records of type stdClass, keyed by id
     */
    public static function objGetExemptRoles(){
        global $DB;
        $rolesConfig = ProctorU::_c( 'roleselection');
        return $DB->get_records_list('role', 'id', explode(',', $rolesConfig));
    }

    /**
     * Gets all non-suspended, non-deleted, non-guest users from the db
     * @global type $DB
     * @return object[] db result row objects
     */
    public static function objGetAllUsers(){
        global $DB;

        $guestUserId  = $DB->get_field('user', 'id', array('username'=>'guest'));

        $active       = $DB->get_records('user', array('suspended'=>0,'deleted'=>0));

        unset($active[$guestUserId]);

        return $active;
    }
    
    /**
     * Get all users with one of the ProctorU statuses.
     * Used to set 
     * 
     * @return object[] user rows objects
     */
    public static function objGetAllUsersWithProctorStatus(){
        $users = array();
        foreach(self::arrStatuses() as $st){
            $users += self::objGetUsersWithStatus($st);
        }
        return $users;
    }

    /**
     * Gets users without a value in the proctoru profile field
     * @return int[]
     */
    public static function objGetAllUsersWithoutProctorStatus(){

        $allUsers   = self::objGetAllUsers();

        $haveStatus = self::objGetAllUsersWithProctorStatus();
        
        $ids = array_diff(
                array_keys($allUsers),
                array_keys($haveStatus)
                );
        
        $noStatus   = array_intersect_key($allUsers, array_flip($ids));

        return $noStatus;
    }
    
    
    /**
     * @param int $status class constants
     * @return object[] db user row objects having the given proctoru status
     */
    public static function objGetUsersWithStatus($status){
        return ProctorU::partial_get_users_listing($status);
    }

    /**
     * Find users that are exempt for proctoru lookup based
     * on their membership in a one of te exempt roles in some context
     * @return object[] users having the exempt role in any course
     */
    public static function objGetExemptUsers() {
        $exemptRoles = self::objGetExemptRoles();
        $exempt = array();
        $total = 0;
        foreach (array_keys($exemptRoles) as $roleid) {
            $ex = ProctorU::partial_get_users_listing_by_roleid($roleid);
            mtrace(sprintf("found %d users with exempt roleid %d", count($ex), $roleid));
//            $exempt = array_merge($exempt, $ex);
            $exempt += $ex;
            $total += count($ex);
        }
        mtrace(sprintf("%d TOTAL users are exempt from ProctorU limitations. This number should be reflected below.", count($exempt)));
        return $exempt;
    }
    
    public static function dbrGetUserCountByStatus() {
        global $DB;
        $sql = "
            SELECT data, count(userid) AS count 
            FROM {user_info_data}
            WHERE fieldid = :fieldid 
            GROUP BY data;
            ";
        return $DB->get_records_sql($sql, array('fieldid'=>self::intCustomFieldID()));
    }

    /**
     * returns an associative array of status names to user counts
     * @param array $dbr such as that returned from self::dbrGetUserCountByStatus
     */
    public static function mapRawUserCountToFriendlyNames(array $dbr){
        $friendly = array();
        foreach($dbr as $status => $count){
            $friendly[self::strMapStatusToLangString($status)] = $count;
        }
        return $friendly;
    }
}

class ProctorUException extends moodle_exception{
    
}