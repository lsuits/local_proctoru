<?php

global $CFG;
require_once $CFG->dirroot . '/local/proctoru/Webservicelib.php';
require_once $CFG->dirroot . '/local/proctoru/tests/conf/ConfigProctorU.php';

class CredentialsClient_testcase extends advanced_testcase {

    public function setup() {
        $this->resetAfterTest();

        //init local config
        $this->conf = new ConfigProctorU();
        $this->conf->setConfigs();
    }

    public function test_getLocalWebservicesCredentials() {
        $client = new CredentialsClient();

        $resp = $client->strGetRawResponse();
        list($username, $password) = explode("\n", $resp);

        $this->assertNotEmpty($username);
        $this->assertNotEmpty($password);
        $this->assertInternalType('string', $username);
        $this->assertInternalType('string', $password);
    }

}

?>
