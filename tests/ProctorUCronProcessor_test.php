<?php
global $CFG;
require_once $CFG->dirroot . '/local/proctoru/lib.php';
require_once $CFG->dirroot . '/local/proctoru/Cronlib.php';
require_once $CFG->dirroot . '/local/proctoru/tests/conf/ConfigProctorU.php';
require_once $CFG->dirroot . '/local/proctoru/tests/abstract_testcase.php';

class ProctorUCronProcessor_testcase extends abstract_testcase{

    public function test_objPartitionUsersWithoutStatus(){
        $numTeachers = 10;
        $numStudents = 20;
        
        $students = $this->addNUsersToDatabase($numStudents);
        $teachers = $this->addNUsersToDatabase($numTeachers);
        $course   = $this->getDataGenerator()->create_course();
        
        foreach($students as $s) {
            $this->enrolUser($s, $course, $this->studentRoleId);
        }
        
        foreach($teachers as $t) {
            $this->enrolUser($t, $course, $this->teacherRoleId);
        }
        
        list($unreg, $exempt) = $this->cron->objPartitionUsersWithoutStatus();
        $this->assertEquals($numStudents +1, count($unreg)); //+1 for admin
        $this->assertEquals($numTeachers, count($exempt));
    }
    
    public function test_objGetUnverifiedUsers(){
        $v = 11;
        $u = 23;
        $n = 8;
        $x = 32;
        $r = 43;
        $p = 13;
        
        $verified       = $this->addNUsersToDatabase($v);
        $this->setProfileFieldBulk($verified, ProctorU::VERIFIED);
        
        $unregistered   = $this->addNUsersToDatabase($u);
        $this->setProfileFieldBulk($unregistered, ProctorU::UNREGISTERED);
        
        $noIdnumber     = $this->addNUsersToDatabase($n);
        $this->setProfileFieldBulk($noIdnumber, ProctorU::NO_IDNUMBER);
        
        $exempt         = $this->addNUsersToDatabase($x);
        $this->setProfileFieldBulk($exempt, ProctorU::EXEMPT);
        
        $registeed      = $this->addNUsersToDatabase($r);
        $this->setProfileFieldBulk($registeed, ProctorU::REGISTERED);
        
        $pu404          = $this->addNUsersToDatabase($p);
        $this->setProfileFieldBulk($pu404, ProctorU::PU_NOT_FOUND);
        
        // +1 for admin user
        $this->assertEquals(1+$u+$n+$r+$x+$v+$p, count(ProctorU::objGetAllUsers()));
        
        $this->assertEquals(1+$u+$n+$r,count($this->cron->objGetUnverifiedUsers()), sprintf("Added %d total users + admin", $u+$n+$r+$x+$v));
    }
    
    public function test_intSetStatusForUsers(){

        $numTeachers = 11;
        $numStudents = 24;

        $students = $this->addNUsersToDatabase($numStudents);
        $teachers = $this->addNUsersToDatabase($numTeachers);

        $intStudents = $this->cron->intSetStatusForUser($students,  ProctorU::UNREGISTERED);
        $intTeachers = $this->cron->intSetStatusForUser($teachers,  ProctorU::EXEMPT);

        $this->assertEquals($intStudents, count($students));
        $this->assertEquals($intTeachers, count($teachers));

        $dbCount = function($status){
            global $DB;
            $sql = sprintf("SELECT id FROM {user_info_data} WHERE data = %s and fieldid = %s",
                $status, ProctorU::intCustomFieldID());
            return count($DB->get_records_sql($sql));
        };

        //triple check that the number of status records are correct
        $this->assertEquals($numTeachers, $dbCount(ProctorU::EXEMPT));
        $this->assertEquals($numStudents, $dbCount(ProctorU::UNREGISTERED));
        
    }
    
    public function test_intGetPseudoID(){
        // not in prod service
        $this->setClientMode($this->localDataStore, 'test');

        //set up test users
        $this->enrolTestUsers();

        $noPseudo   = $this->conf->data['testUser1'];
        $this->assertFalse($this->cron->intGetPseudoID($noPseudo['idnumber']));

        $hasPseudo  = $this->conf->data['testUser2'];
        $this->assertInternalType('integer',$this->cron->intGetPseudoID($hasPseudo['idnumber']));
    }

    public function test_constProcessUser(){
        $this->enrolTestUsers();

        // not in prod service
        $this->setClientMode($this->localDataStore, 'test');
        $this->setClientMode($this->puClient, 'test');

        $anonUser = $this->getDataGenerator()->create_user();
        if(isset($anonUser->idnumber)){
            unset($anonUser->idnumber);
        }
        $this->assertEquals(ProctorU::NO_IDNUMBER, $this->cron->constProcessUser($anonUser));

        $anonUser->idnumber = rand(999,9999);
        $this->assertEquals(ProctorU::SAM_HAS_PROFILE_ERROR, $this->cron->constProcessUser($anonUser));

        $regUserWithSamAndPuRegInTest = $this->users['userRegistered'];
        $this->assertEquals(ProctorU::REGISTERED, $this->cron->constProcessUser($regUserWithSamAndPuRegInTest));

        //now prod
        $this->setClientMode($this->puClient, 'prod');
        $this->setClientMode($this->localDataStore, 'prod');

        $verifiedUser = $this->users['userVerified'];
        $this->assertEquals(ProctorU::VERIFIED, $this->cron->constProcessUser($verifiedUser));
    }
}
?>
