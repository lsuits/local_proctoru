<?php
global $CFG;
require_once $CFG->dirroot . '/local/proctoru/lib.php';
require_once $CFG->dirroot . '/local/proctoru/tests/abstract_testcase.php';
require_once $CFG->dirroot . '/local/proctoru/tests/conf/ConfigProctorU.php';

class ProctorU_testcase extends abstract_testcase{
    
    
    public function setup(){
        parent::setup();
        global $DB;
        //map lookup is a little anonymous; refactor the map to make semantic sense
        $this->assertEquals($this->conf->config[1][1], ProctorU::_c('roleselection'));
        $shortname = ProctorU::_c('profilefield_shortname');
        $this->assertNotEmpty($DB->get_record('user_info_field',array('shortname' => $shortname)));
        $this->assertNotEmpty(ProctorU::_c('localwebservice_url'));
        
        $this->assertNotEmpty(ProctorU::_c( 'localwebservice_url'));
        $this->assertInternalType('string', ProctorU::_c('localwebservice_url'));
        $this->assertInternalType('string', ProctorU::_c( 'localwebservice_url'));
    }

    
    public function test_ensureLocalUserExists(){
        $dasInvalidParamsResponse = 
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
        <RESULTS>
            <ERROR_MSG>                 
                Problem validating input parameters.
            </ERROR_MSG>  
        </RESULTS>";
    }
    
    public function test_intCustomFieldID(){
        global $DB;
        $conf = ProctorU::_c($this->conf->config[2][0]);
        $id   = $DB->get_field('user_info_field','id',array('shortname' => $conf));
        $this->assertEquals($id, ProctorU::intCustomFieldID());
    }
    
    public function test_blnUserHasProctoruProfileFieldValue(){
        global $DB;
        $this->resetUserTables();   //get rid of testcase fixture users
        
        //first try a fresh user 
        $u = $this->getDataGenerator()->create_user();
        $this->assertFalse(ProctorU::blnUserHasProctoruProfileFieldValue($u->id), sprintf("user %s has a value set", $u->id));
        
        //now give user a pu status
        $status = new stdClass();
        $status->userid  = $u->id;
        $status->fieldid = ProctorU::intCustomFieldID();
        $status->data    = ProctorU::UNREGISTERED;
        $DB->insert_record('user_info_data', $status);
        $this->assertTrue(ProctorU::blnUserHasProctoruProfileFieldValue($u->id));
    }
    
    public function test_blnUserHasExemptRole(){
        $this->enrolTestUsers();
        $this->assertTrue(ProctorU::blnUserHasExemptRole($this->users['teacher']->id));
        $this->assertFalse(ProctorU::blnUserHasExemptRole($this->users['userUnregistered']->id));
    }

    public function test_constProctorUStatusForUserId(){
        $this->enrolTestUsers();
        
        //user has no record whatsoever in the custom profile field
        $anonUser = $this->getDataGenerator()->create_user();
        $this->assertFalse(ProctorU::constProctorUStatusForUserId($anonUser->id));
        
        //user has status UNREGISTERED
        $this->assertEquals(1,ProctorU::constProctorUStatusForUserId($this->users['userUnregistered']->id));
        
        //user has status REGISTERED
        $this->assertEquals(2,ProctorU::constProctorUStatusForUserId($this->users['userRegistered']->id));
        
        //user has status VERIFIED
        $this->assertEquals(3,ProctorU::constProctorUStatusForUserId($this->users['userVerified']->id));
        
        //user has status EXEMPT
        $this->assertEquals(4,ProctorU::constProctorUStatusForUserId($this->users['teacher']->id));
    }
    
    public function test_blnUserHasAcceptableStatus() {
        global $DB;
        $this->enrolTestUsers();

        //fresh unknown user
        $unknown = $this->getDataGenerator()->create_user();
        $this->assertFalse(ProctorU::blnUserHasAcceptableStatus($unknown->id));
        
        //user with exempt role
        $this->assertTrue(ProctorU::blnUserHasAcceptableStatus($this->users['teacher']->id));
        
        //user with VERIFIED status
        $verifiedUserID = $this->users['userVerified']->id;
        $statusRecordShouldEqualVerified = $DB->get_record('user_info_data',array('userid'=>$verifiedUserID, 'fieldid'=>ProctorU::intCustomFieldID()));
        $this->assertEquals(ProctorU::VERIFIED, $statusRecordShouldEqualVerified->data);
        
        $this->assertTrue(ProctorU::blnUserHasAcceptableStatus($verifiedUserID));
    }
    
    public function test_objGetExemptRoles(){
        $config = explode(',',ProctorU::_c( 'roleselection'));
        $unit   = ProctorU::objGetExemptRoles();

        $this->assertEquals(count($config), count($unit));
        $this->assertEmpty(array_diff(array_keys($unit), $config));
    }
    
    public function test_partial_get_users_listing_by_roleid(){
        $this->enrolTestUsers();
        $teachers = ProctorU::partial_get_users_listing_by_roleid($this->teacherRoleId);
        $students = ProctorU::partial_get_users_listing_by_roleid($this->studentRoleId);
        
        $this->assertEquals(1, count($teachers));
        $this->assertEquals(4, count($students));
    }
    
    public function test_partial_get_users_listing_by_roleid_teacher(){
        global $DB;

        $this->assertEquals(2,count($DB->get_records('user'))); //admin + guest
        
        $teacherCount = 5;
        $teachers     = $this->addNUsersToDatabase($teacherCount);
        $this->assertEquals($teacherCount, count($teachers));
        $course       = $this->getDataGenerator()->create_course();
        
        $teacherKeys = array();
        foreach($teachers as $t){
            $this->enrolUser($t, $course, $this->teacherRoleId);
            $teacherKeys[] = $t->id;
        }

        $unitTeachers = ProctorU::partial_get_users_listing_by_roleid($this->teacherRoleId);
        
        $unitKeys = array_keys($unitTeachers);

        $this->assertEquals($teacherCount, count($unitTeachers));
    }
    
    
    public function test_objGetUsersWithStatusUnregistered() {
        $n = 8;
        //$wipe = true, $confUsers = false, $numSusp=40, $numDele=35,$numAnon=30, $numUnreg=25, $numReg=20,  $numVer=15, $numExempt=10
        $this->buildDataset(false, false, null, null, null,$n);
        //this assumes admin has not been set to any exempt role
        $this->assertEquals($n, count(ProctorU::objGetUsersWithStatus(ProctorU::UNREGISTERED)));
    }
    public function test_objGetUsersWithStatusRegistered() {
        $n = 17;
        $this->buildDataset(false, false, null, null, null,null,$n);
        //this assumes admin has not been set to any exempt role
        $this->assertEquals($n, count(ProctorU::objGetUsersWithStatus(ProctorU::REGISTERED)));
    }
    public function test_objGetVerifiedUsers() {
        $n = 13;
        $this->buildDataset(false, false, null, null, null, null, null,$n);
        //this assumes admin has not been set to any exempt role
        $this->assertEquals($n, count(ProctorU::objGetUsersWithStatus(ProctorU::VERIFIED)));
    }
    public function test_objGetUsersWithStatusExempt() {
        $n = 14;
        $this->buildDataset(false, false, null, null, null, null, null,null,$n);
        //this assumes admin has not been set to any exempt role
        $this->assertEquals($n, count(ProctorU::objGetUsersWithStatus(ProctorU::EXEMPT)));
    }
    
    public function test_objGetAllUsersWithoutProctorStatus_excludesPeopleWithPUStatus() {
        global $DB;
        $this->enrolTestUsers(); //4 users
        $this->addNUsersToDatabase(20); //random, anonymous users
        
        // 4 test users + admin, guest should be ignored
        $this->assertEquals(25, count(ProctorU::objGetAllUsers()));
        
        $usersWithoutStatus = ProctorU::objGetAllUsersWithoutProctorStatus();
        $this->assertEquals(21,count($usersWithoutStatus));
        
        $sql = "SELECT count(id) AS count FROM {user_info_data} WHERE fieldid = ".ProctorU::intCustomFieldID();
        $allSql = array_values($DB->get_records_sql($sql));
        $this->assertFalse($allSql[0]->count == count($usersWithoutStatus));
        
    }
    
    public function test_objGetAllUsersWithProctorStatus() {
        global $DB;
        
        $deleted    = 3;
        $susp       = 4;
        $anon       = 12;
        $unreg      = 14;
        $reg        = 17;
        $verif      = 21;
        $exempt     = 7;
        
        $this->buildDataset(true, false, $susp, $deleted, $anon, $unreg, $reg, $verif, $exempt);
        
        //simplify the groups
        $excluded   = $deleted + $susp;
        $haveStatus = $unreg + $reg + $verif + $exempt;
        $noStatus   = $anon;
        $all        = $excluded + $haveStatus + $noStatus;
        
        //ensure that we have exactly the right number of users in the DB
        $this->assertEquals($all, count($DB->get_records('user')));
        $this->assertEquals($all - $excluded, count(ProctorU::objGetAllUsers()));

        //ensure that the members of each status group are counted only once
        $this->assertEquals($haveStatus, count(ProctorU::objGetAllUsersWithProctorStatus()));        
        $this->assertEquals($noStatus, count(ProctorU::objGetAllUsersWithoutProctorStatus()));
    }
    
    public function test_objGetAllUsers() {
        global $DB;
        
        //admin & guest
        $this->assertEquals(2, count($DB->get_records('user')));
        
        //the fn under test excludes guest, deleted and suspended accounts
        $this->addNUsersToDatabase(30, array('suspended'=>1));
        $this->assertEquals(30, count($DB->get_records('user',array('suspended'=>1))));
        
        $this->addNUsersToDatabase(10, array('deleted'=>1));
        $this->assertEquals(10, count($DB->get_records('user',array('deleted'=>1))));
        
        $this->addNUsersToDatabase(10); //normal valid users
        
        //admin + normal users
        $this->assertEquals(11,count(ProctorU::objGetAllUsers()));
    }

    public function test_objGetExemptUsers() {
        $this->enrolTestUsers();
        $exempt = ProctorU::objGetExemptUsers();
        assert(count($exempt)>0);
        
        $this->assertEquals(1,count($exempt));
        $admin = array_pop($exempt);
        $this->assertEquals('teacher', $admin->username);
    }
}
?>