<?php

global $CFG;
require_once $CFG->libdir . '/filelib.php';
require_once($CFG->dirroot.'/user/filters/profilefield.php');
require_once($CFG->dirroot.'/user/filters/yesno.php');
require_once 'Cronlib.php';

function local_proctoru_cron() {

    if (get_config('local_proctoru','bool_cron' == 1)) {

        $outputException = function(Exception $e, $headline){
            $class = get_class($e);
            
            $out = sprintf("caught Exception of type %s:\n%s\n",$class,$headline);
            $out.= sprintf("Message was:\n%s\n",$e->getMessage());
            $out.= sprintf("Stack trace:\n\n%s", $e->getTraceAsString());
            mtrace($out);
            ProctorUCronProcessor::emailAdmins($out);
        };
        
        try{
            ProctorU::default_profile_field();
        }
        catch(ProctorUException $e){
            $msg = "";
            $outputException($e,$msg);
            return true;
        }

        mtrace(sprintf("Running ProctorU cron tasks"));
        try{
            $cron = new ProctorUCronProcessor();
        }catch(ProctorUWebserviceLocalDataStoreException $e){
            $msg = sprintf("!!!Trouble initializing LocalDataStore component 
                of the CronProcessor:\n
                %s\n%s\n
                Aborting ProctorU cron tasks\n", $e->getMessage(), $e->getTrace());
            $outputException($e,$msg);
            return true;
        }catch(ProctorUWebserviceCredentialsClientException $e){
            $msg = sprintf("!!!Trouble initializing CredentialsClient component 
                of the CronProcessor:\n
                %s\n%s\n
                Aborting ProctorU cron tasks\n", $e->getMessage(), $e->getTraceAsString());
            $outputException($e,$msg);
            return true;
        }catch(ProctorUException $e){
            $msg = sprintf("!!!Trouble initializing CronProcessor:\n%s\nAborting ProctorU cron tasks\n", $e->getMessage());
            $outputException($e,$msg);
            return true;
        }

        //get users without status (new users)
        list($unreg,$exempt) = $cron->objPartitionUsersWithoutStatus();

        //set appropriate status for new users
        $intUnreg = $cron->intSetStatusForUser($unreg, ProctorU::UNREGISTERED);
        mtrace(sprintf("Set status %s for %d of %d unregistered users.",ProctorU::UNREGISTERED, $intUnreg, count($unreg)));

        $intExempt = $cron->intSetStatusForUser($exempt, ProctorU::EXEMPT);
        mtrace(sprintf("Set status %s for %d of %d exempt users.",ProctorU::EXEMPT, $intExempt, count($exempt)));

        $needProcessing = $cron->objGetUnverifiedUsers();
        mtrace(sprintf("Begin processing user status for %d users", count($needProcessing)));
        try{
            $cron->blnProcessUsers($needProcessing);
        }
        catch(ProctorUException $e){
            $msg = "caught exception while processing users; \naborting...";
            $outputException($e,$msg);
            return true;
        }
    } else {
        mtrace("Skipping ProctorU");
    }
    return true;
}

class ProctorU {

    public $username, $password, $localWebservicesCredentialsUrl, $localWebserviceUrl;
    
    const UNREGISTERED  = 1;
    const REGISTERED    = 2;
    const VERIFIED      = 3;
    const EXEMPT        = 4;
    const SAM_HAS_PROFILE_ERROR = -1;
    const NO_IDNUMBER   = -2;
    const PU_NOT_FOUND  = -404;
        
    public function __construct() {

        self::default_profile_field();

        $this->localWebservicesCredentialsUrl = get_config('local_proctoru', 'credentials_location');
        $this->localWebservicesUrl            = get_config('local_proctoru', 'localwebservice_url');
    }

    /**
     * for a given const status, returns a human-freindly string
     */
    public static function strMapStatusToLangString($status){
        if(empty($status)) return ''; //necessary so that users without status do not cause array index errors
        $_s = function($str){
            return get_string($str, 'local_proctoru');
        };
        
        $map = array(
            ProctorU::UNREGISTERED  => 'unregistered',
            ProctorU::REGISTERED    => 'registered',
            ProctorU::VERIFIED  => 'verified',
            ProctorU::EXEMPT    => 'exempt',
            ProctorU::SAM_HAS_PROFILE_ERROR => 'sam_profile_error',
            ProctorU::NO_IDNUMBER   => 'no_idnumber',
            ProctorU::PU_NOT_FOUND  => 'pu_404',
                );
        return $_s($map[$status]);
    }
    
    /**
     * insert new record into {user_info_field}
     * @global type $DB
     * @param type $params
     * @return \stdClass
     */
    public static function default_profile_field() {
        global $DB;
        $shortname = get_config('local_proctoru', 'profilefield_shortname');

        if(!$shortname){
            throw new ProctorUException('profile field shortname is not configured');
        }

        if (!$field = $DB->get_record('user_info_field', array('shortname' => $shortname))) {
            $field              = new stdClass;
            $field->shortname   = $shortname;
            $field->name        = get_config('local_proctoru', 'profilefield_longname');
            $field->description = get_string('profilefield_shortname', 'local_proctoru');
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
        return get_config('local_proctoru','profilefield_shortname');
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
        $exemptRoleIds = get_config('local_proctoru', 'roleselection');
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
            $record = new stdClass();
            $record->data = $status;
            $record->userid = $userid;
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
                           $search='', $firstinitial='', $lastinitial='') {

    // $status = PROCTORU::VERIFIED; 
    // echo $status;
    // the extraselect needs to vary to allow the user to specify 'is not empty', etc
    $proFilter  = new user_filter_profilefield('profile','Profile',1);

    if(!isset($status)){
        $extraselect = '';
        $extraparams = array();
    }else{
        //figure out which field key the filter function uses for our field
        $fieldKey = null;
        $fieldShortname = get_config('local_proctoru', 'profilefield_shortname');
        foreach($proFilter->get_profile_fields() as $k=>$sn){
            if($sn == $fieldShortname){
                $fieldKey = $k;
            }
        }
        if(is_null($fieldKey)){
            throw new Exception("attempt to filter by non-existent profile field; 
                check your field shortname exists.");
        }

        $data['profile']    = $fieldKey;
        $data['operator']   = 2;
        $data['value']      = $status;

        list($extraselect, $extraparams) = $proFilter->get_sql_filter($data);
    }

    $suspFilter = new user_filter_yesno('suspended', 'Suspended',1,'suspended');
    $suspData = array(
        'value' => "0",
    );
    list($suspXSelect, $suspXParams) = $suspFilter->get_sql_filter($suspData);
    
    $extraselect .= " AND ".$suspXSelect . "AND deleted = 0";
    $extraparams += $suspXParams;
    
    $extracontext= context_system::instance();
    
    return get_users_listing($sort,$dir,$page,$recordsperpage,$search,
            $firstinitial,$lastinitial, $extraselect, $extraparams, $extracontext);
    }
    
    public static function partial_get_users_listing_by_roleid($roleid){
        $roFilter     = new user_filter_courserole('role', 'Role', 1);
        $data         = array('value'=>false, 'roleid'=>$roleid, 'categoryid'=>0);
        $extracontext = context_system::instance();
        
        list($extraselect, $extraparams) = $roFilter->get_sql_filter($data);
        
        //exclude suspended users
        {
            $suspFilter = new user_filter_yesno('suspended', 'Suspended',1,'suspended');
            $suspData = array(
                'value' => "0",
            );
            list($suspXSelect, $suspXParams) = $suspFilter->get_sql_filter($suspData);

            $extraselect .= " AND ".$suspXSelect;
            $extraparams += $suspXParams;
        }
        
        return get_users_listing('','',null,null,'',
            '','', $extraselect, $extraparams, $extracontext);
    }
    
    /**
     * 
     * @global type $DB
     * @return object[] role records of type stdClass, keyed by id
     */
    public static function objGetExemptRoles(){
        global $DB;
        $rolesConfig = get_config('local_proctoru', 'roleselection');
        return $DB->get_records_list('role', 'id', explode(',', $rolesConfig));
    }

    public static function objGetAllUsers(){
        global $DB;
        //@TODO should be rewritten as a single query
        $guestUserId  = $DB->get_field('user', 'id', array('username'=>'guest'));
        $active       = $DB->get_records('user', array('suspended'=>0,'deleted'=>0));

        unset($active[$guestUserId]);
        return $active;
    }
    
    public static function objGetAllUsersWithProctorStatus(){
        return self::objGetUsersWithStatusUnregistered() +
                self::objGetUsersWithStatusRegistered()  +
                self::objGetUsersWithStatusVerified()    +
                self::objGetUsersWithStatusExempt();
    }

    /**
     * Gets
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
     * @TODO replace the 4 methods below with calls to this
     * @param int $status class constants
     * @return object[]
     */
    public static function objGetUsersWithStatus($status){
        return ProctorU::partial_get_users_listing($status);
    }
    
    /**
     * get users with ProctorU status set to ProctorU::UNREGISTERED
     * @return object[] array of unregistered users
     */
    public static function objGetUsersWithStatusUnregistered(){
        return ProctorU::partial_get_users_listing(ProctorU::UNREGISTERED);
    }
    
    /**
     * get users with ProctorU status set to ProctorU::REGISTERED
     * @return object[] array of registered users
     */
    public static function objGetUsersWithStatusRegistered(){
        return ProctorU::partial_get_users_listing(ProctorU::REGISTERED);
    }
    
    /**
     * get users with ProctorU status set to ProctorU::VERIFIED
     * @return object[] array of verified users
     */
    public static function objGetUsersWithStatusVerified(){
        return ProctorU::partial_get_users_listing(ProctorU::VERIFIED);
    }
    
    /**
     * get users with ProctorU status set to ProctorU::EXEMPT
     * @return object[] array of exempt users
     */
    public static function objGetUsersWithStatusExempt(){
        return ProctorU::partial_get_users_listing(ProctorU::EXEMPT);
    }

    /**
     * 
     * @return object[] users having the exempt role in any course
     */
    public static function objGetExemptUsers() {
        $exemptRoles = self::objGetExemptRoles();
        $exempt = array();
        foreach (array_keys($exemptRoles) as $roleid) {
            $exempt += ProctorU::partial_get_users_listing_by_roleid($roleid);
        }
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
?>
