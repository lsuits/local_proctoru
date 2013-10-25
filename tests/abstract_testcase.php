<?php
global $CFG;

require_once $CFG->dirroot . '/local/proctoru/db/install.php';
require_once $CFG->dirroot . '/local/proctoru/lib.php';
require_once $CFG->dirroot . '/local/proctoru/Cronlib.php';
require_once $CFG->dirroot . '/local/proctoru/tests/conf/ConfigProctorU.php';
require_once $CFG->dirroot . '/enrol/externallib.php';

abstract class abstract_testcase extends advanced_testcase{
    public $conf;
    public $pu;
    public $cron;
    public $users;
    public $teacherRoleId;
    public $studentRoleId;
    
    public $localDataStore;
    public $puClient;
    
    public function setup(){
        global $DB;
        $this->resetAfterTest();

        xmldb_local_proctoru_install();
        
        //init configs
        $this->conf = new ConfigProctorU();
        $this->conf->setConfigs();
        
        $this->cron = new ProctorUCronProcessor();
        $this->courses[] = $this->getDataGenerator()->create_course();
        $this->assertEquals(2,$this->courses[0]->id);
        
        //these come in handy
        $this->studentRoleId = $DB->get_field('role', 'id', array('shortname'=>'student'));
        $this->teacherRoleId = $DB->get_field('role', 'id', array('shortname'=>'teacher'));
        
        $this->localDataStore = new LocalDataStoreClient();
        $this->puClient      = new ProctorUClient();

        $shortname = ProctorU::_c( 'profilefield_shortname');
        $this->assertNotEmpty($DB->get_record('user_info_field',array('shortname' => $shortname)));
        $this->assertNotEmpty(ProctorU::_c('localwebservice_url'));
        
        $this->assertNotEmpty(ProctorU::_c( 'localwebservice_url'));
        $this->assertInternalType('string', ProctorU::_c('localwebservice_url'));
        $this->assertInternalType('string', ProctorU::_c( 'localwebservice_url'));
        
    }
    
    /**
     * creates and enrols the test users as students in an anonymous course
     * enrols admin as the teach simulating a population where there are 
     * EXEMPT, UNREGISTERED, REGISTERED and VERIFIED users
     * @global type $DB
     */
    protected function enrolTestUsers(){
        $this->insertOneUserOfEachFlavor();
        $tmpUsers = $this->users;
        $course = $this->courses[0];
        $teacher =& $tmpUsers['teacher'];
        $this->enrolUser($teacher, $course,$this->teacherRoleId);
        unset($teacher);
        
        foreach($tmpUsers as $u){
            assert($this->getDataGenerator()->enrol_user(
                    $u->id, 
                    $course->id, 
                    $this->studentRoleId)
                    );
        }
    }
    
    protected function enrolUser($user, $course, $role) {
        $this->getDataGenerator()->enrol_user(
                $user->id, 
                $course->id,
                $role
                );
    }
    
    protected function insertOneUserOfEachFlavor(){
        global $DB;
        $before = count($DB->get_records('user'));
        
        $data = $this->conf->data;
        $gen  = $this->getDataGenerator();

        $this->users['userUnregistered']   = $gen->create_user($data['testUser1']);
        $this->users['userRegistered']     = $gen->create_user($data['testUser2']);
        $this->users['userVerified']       = $gen->create_user($data['testUser3']);
        $this->users['teacher']            = $gen->create_user(array('username'=>'teacher'));
        $this->assertEquals($before + 4, count($DB->get_records('user')));
        
        $this->setProfileField($this->users['userUnregistered']->id, ProctorU::UNREGISTERED);
        $this->setProfileField($this->users['userRegistered']->id,   ProctorU::REGISTERED);
        $this->setProfileField($this->users['userVerified']->id,     ProctorU::VERIFIED);
        $this->setProfileField($this->users['teacher']->id,          ProctorU::EXEMPT);
    }
    
    protected function setProfileField($userid, $value){
        global $DB;
        $shortname = ProctorU::_c( 'profilefield_shortname');
        
        //create profile field
        ProctorU::default_profile_field();
        
        $fieldid = $DB->get_field('user_info_field', 'id', array('shortname'=>$shortname));

        $fieldData = new stdClass();
        $fieldData->userid  = $userid;
        $fieldData->fieldid = $fieldid;
        $fieldData->data    = $value;
        $fieldData->dataFormat = 0;
        
        $DB->insert_record('user_info_data',$fieldData, true, false);
        $this->assertEquals(1, count($DB->get_records_select('user_info_data',
                "fieldid = {$fieldid} AND userid = {$userid} AND data = {$value}")));
    }
    
    public function setProfileFieldBulk($users, $value){
        foreach($users as $u){
            $this->setProfileField($u->id, $value);
        }
    }
    
    protected function resetUserTables(){
        global $DB;
        $DB->delete_records('user');
        $DB->delete_records('user_info_data');
        
        $this->assertEmpty($DB->get_records('user'));
        $this->assertEmpty($DB->get_records('user_info_data'));
    }
    
    protected function addNUsersToDatabase($i, array $params=array()){
        $gen = $this->getDataGenerator();
        $count = $i;
        $users = array();
        while($i > 0){
            $users[] = $gen->create_user($params);
            $i--;
        }
        $this->assertEquals($count, count($users));
        return $users;
    }
    
    protected function buildDataset($wipe = true, $confUsers = false, $numSusp=40, $numDele=35,$numAnon=30, $numUnreg=25, $numReg=20,  $numVer=15, $numExempt=10){
        global $DB;
        $countAll = 0;
        if(!$wipe){
            $countAll += 2; //admin + guest
        }else{
            $this->resetUserTables();
        }
        
        $users = array();
        $users['anonymous']             = $this->addNUsersToDatabase($numAnon);
        $users['suspended']             = $this->addNUsersToDatabase($numSusp, array('suspended'=>1));
        $users['deleted']               = $this->addNUsersToDatabase($numDele, array('deleted'  =>1));
        $users[ProctorU::UNREGISTERED]  = $this->addNUsersToDatabase($numUnreg);
        $users[ProctorU::REGISTERED]    = $this->addNUsersToDatabase($numReg);
        $users[ProctorU::VERIFIED]      = $this->addNUsersToDatabase($numVer);
        $users[ProctorU::EXEMPT]        = $this->addNUsersToDatabase($numExempt);
        
        foreach($users as $k=>$v){
            if(in_array($k,array('anonymous', 'suspended','deleted'))) continue;
            
            foreach($v as $user){
                $this->setProfileField($user->id, $k);
            }
        }
        
        if($confUsers){
            $this->enrolTestUsers();
            $numUnreg++;
            $numReg++;
            $numVer++;
            $numExempt++;
        }
        
        $countAll += $numDele + $numSusp + $numAnon + $numUnreg + $numReg + $numVer + $numExempt;
        
        $this->assertEquals($numDele, count($DB->get_records('user', array('deleted'=>1))));
        $this->assertEquals($numSusp, count($DB->get_records('user', array('suspended'=>1))));
        
        //verify counts for ProctorU records
        $select = sprintf("fieldid = %s AND data = ",ProctorU::intCustomFieldID());
        
        $this->assertEquals($numUnreg,count($DB->get_records_select('user_info_data',
                $select.ProctorU::UNREGISTERED)));
        $this->assertEquals($numReg,count($DB->get_records_select('user_info_data',
                $select.ProctorU::REGISTERED)));
        $this->assertEquals($numVer,count($DB->get_records_select('user_info_data',
                $select.ProctorU::VERIFIED)));
        $this->assertEquals($numExempt,count($DB->get_records_select('user_info_data',
                $select.ProctorU::EXEMPT)));
        $this->assertEquals($countAll, count($DB->get_records('user')));
    }
    
    protected function setClientMode($client, $mode){
        if($client instanceof LocalDataStoreClient){
            if($mode == 'prod'){
                set_config('localwebservice_url',  $this->conf->config[5][1], 'local_proctoru');
                set_config('credentials_location', $this->conf->config[4][1], 'local_proctoru');

                $this->cron->localDataStore = new LocalDataStoreClient();
                $this->assertEquals($this->conf->config[5][1],$this->cron->localDataStore->baseUrl);
            }
            else{
                set_config('localwebservice_url',  $this->conf->config[6][1], 'local_proctoru');
                set_config('credentials_location', $this->conf->config[3][1], 'local_proctoru');

                $this->cron->localDataStore = new LocalDataStoreClient();
                $this->assertEquals($this->conf->config[6][1],$this->cron->localDataStore->baseUrl);
            }

        }else{
            if($mode == 'prod'){
                set_config('proctoru_api', $this->conf->config[12][1], 'local_proctoru');

                $this->cron->puClient = new ProctorUClient();
                $this->assertEquals($this->conf->config[12][1],$this->cron->puClient->baseUrl);
            }else{
                set_config('proctoru_api', $this->conf->config[11][1], 'local_proctoru');

                $this->cron->puClient = new ProctorUClient();
                $this->assertEquals($this->conf->config[11][1],$this->cron->puClient->baseUrl);
            }

        }
    }
}

?>