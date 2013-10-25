<?php
global $CFG;
require_once $CFG->dirroot . '/local/proctoru/lib.php';
require_once $CFG->dirroot . '/local/proctoru/Cronlib.php';
require_once $CFG->dirroot . '/local/proctoru/tests/conf/ConfigProctorU.php';
require_once $CFG->dirroot . '/local/proctoru/tests/abstract_testcase.php';

class ProctorUCronProcessor_testcase extends abstract_testcase{

    public function test_objPartitionUsersWithoutStatus(){
        global $DB;
  
        //user count vars
        $numStudents = 9;
        $numTeachers = 5;
        //don't forget that in moodle phpunit tests, 
        //admin and guest are registered automatically
        
        //create users
        $students = $this->addNUsersToDatabase($numStudents);
        $teachers = $this->addNUsersToDatabase($numTeachers);
        $course   = $this->getDataGenerator()->create_course();

        //enrol students
        $studentKeys = array();
        foreach($students as $s) {
            $this->enrolUser($s, $course, $this->studentRoleId);
            $studentKeys[] = $s->id;
        }

        //enrol teachers
        $teacherKeys = array();
        foreach($teachers as $t) {
            $this->enrolUser($t, $course, $this->teacherRoleId);
            $teacherKeys[] = $t->id;
        }
        
        //verify that the enrolment has affected the role_assignments table as expected
        $intDbRoleAssignmentsStudent = $DB->get_records('role_assignments', array('roleid'=>$this->studentRoleId));
        $this->assertEquals($numStudents, count($intDbRoleAssignmentsStudent));

        $intDbRoleAssignmentsTeacher = $DB->get_records('role_assignments', array('roleid'=>$this->teacherRoleId));
        $this->assertEquals($numTeachers, count($intDbRoleAssignmentsTeacher));

        //test the module function to ensure we both agree 
        //on the list of exempt and unregistered users
        list($unreg, $exempt) = $this->cron->objPartitionUsersWithoutStatus();
        
        //check that counts match
        $this->assertEquals($numTeachers, count($exempt));
        
        //find the set-difference between teacherIds and exemptIds
        $exemptKeys = array_keys($exempt);

        $diffTeachers      = array_diff($exemptKeys, $teacherKeys);
        $strTeacherSetDiff = sprintf("\nteachers ids: {%s}\nexempt ids: {%s}",implode(',',$teacherKeys), implode(',',$exemptKeys));
        $this->assertEmpty($diffTeachers, $strTeacherSetDiff);

        
        //find the set-difference between studentIds and unregIds
        
        //first take admin out of the picture; guest is handled by the 
        //fn under test
        $admin = $DB->get_record('user', array('username'=>'admin'));
        unset($unreg[$admin->id]);
        
        $unregKeys = array_keys($unreg);

        $diffStudents      = array_diff($unregKeys, $studentKeys);
        $strStudentSetDiff = sprintf("students ids: {%s}\nunreg ids: {%s}\nset diff: {%s}",implode(',',$studentKeys), implode(',',$unregKeys), implode(',',$diffStudents));

        $this->assertEquals($numStudents, count($unreg), sprintf("%d students found where %d were expected; differing ids = {%s}", count($unreg), $numStudents+1, $strStudentSetDiff)); //+1 for admin
        $this->assertEmpty($diffStudents, $strStudentSetDiff);
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
