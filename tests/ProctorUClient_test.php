<?php

global $CFG;
require_once $CFG->dirroot . '/local/proctoru/Webservicelib.php';
require_once $CFG->dirroot . '/local/proctoru/tests/conf/ConfigProctorU.php';

class ProctorUClient_testcase extends advanced_testcase {
    
    /**
     * load our local config and instantaite a new 
     * webservice client
     */
    public function setup() {
        $this->resetAfterTest();

        //init local config
        $this->conf = new ConfigProctorU();
        $this->conf->setConfigs();
        $this->class = new ProctorUClient();
    }

    /**
     * Fetch a user known to exist in the test system
     * and verify that this user's fields agree with 
     * the values we expect for 'hasimage, etc...
     */
    public function test_LookupFoundUser_TestAPI() {
        $this->class->baseUrl = 'https://apitest.proctoru.com/getStudentProfile';
        $user       = $this->conf->data['testUser2']['pseudoId'];

        $response   = $this->class->strRequestUserProfile($user);
        
        $this->assertNotEmpty($response);
        
        $puHasImage = $this->conf->data['testUser2']['puHasImage'];
        $puActive   = $this->conf->data['testUser2']['puActive'];
        $puUserId   = $this->conf->data['testUser2']['puUserId'];
        
        $puUser     = $response;

        $this->assertEquals($puHasImage, $puUser->data->hasimage);
        $this->assertEquals($puActive,   $puUser->data->active);
        $this->assertEquals($puUserId,   $puUser->data->user_id);
    }
    
    public function test_LookupFoundUser_ProductionApi() {
        //be sure we'ere using the prod service
        $this->class->baseUrl = 'https://go.proctoru.com/api/getStudentProfile/';
        
        $user       = $this->conf->data['testUser3']['pseudoId'];
        $response   = $this->class->strRequestUserProfile($user);
        
        $this->assertNotEmpty($response);
        
        $puHasImage = $this->conf->data['testUser3']['puHasImage'];
        $puActive   = $this->conf->data['testUser3']['puActive'];
        $puUserId   = $this->conf->data['testUser3']['puUserId'];
        
        $puUser     = $response;

        $this->assertNotEmpty($puUser);
        $this->assertNotEmpty($puUser->data);
        $this->assertEquals($puHasImage, $puUser->data->hasimage);
        $this->assertEquals($puActive,   $puUser->data->active);
        $this->assertEquals($puUserId,   $puUser->data->user_id);
    }
    
    public function test_intUserStatus_REGISTERED(){
        $this->class->baseUrl = 'https://apitest.proctoru.com/getStudentProfile';
        $unit = $this->class->constUserStatus($this->conf->data['testUser2']['pseudoId']);
        $this->assertEquals(ProctorU::REGISTERED, $unit);
    }
    public function test_intUserStatus_VERIFIED(){
        //be sure we'ere using the prod service
        $this->class->baseUrl = 'https://go.proctoru.com/api/getStudentProfile/';
        $unit = $this->class->constUserStatus($this->conf->data['testUser3']['pseudoId']);
        $this->assertEquals(ProctorU::VERIFIED, $unit);
    }

}
?>
