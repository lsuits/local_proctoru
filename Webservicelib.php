<?php

global $CFG;
require_once 'lib.php';
require_once $CFG->libdir . '/filelib.php';

class CurlXmlClient {

    public $response;
    public $baseUrl;
    public $method;
    public $options;
    public $stdParams;
    public $params;

    public function __construct($baseUrl, $method, $options) {
        if (!preg_match('/^[http|https]/', $baseUrl)) {
            throw new Exception('bad_url');
        }
        $this->baseUrl      = $baseUrl;
        $this->method       = $method;
        $this->options      = $options;
        $this->stdParams    = array();
    }

    public function addParams(array $params= array()) {
        $this->params = array_merge($this->stdParams, $params);
        return $this;
    }

    public function strGetRawResponse() {
        $curl = new curl($this->options);
        $meth = $this->method;
        $this->resp = $curl->$meth($this->baseUrl, $this->params);
        return $this->resp;
    }

    public function xmlFetchResponse() {
        return new SimpleXMLElement($this->strGetRawResponse());
    }

}

class CredentialsClient extends CurlXmlClient {

    public function __construct() {
        $baseUrl   = get_config('local_proctoru', 'credentials_location');
        $method    = 'post';
        $options   = array('cache' => true);

        parent::__construct($baseUrl, $method, $options);

        $this->stdParams = array('credentials' => 'get');
        $this->addParams();
    }

}

class LocalDataStoreClient extends CurlXmlClient {

    public function __construct() {

        $baseUrl = get_config('local_proctoru', 'localwebservice_url');
        $method = 'get';
        $options = array();

        parent::__construct($baseUrl, $method, $options);
        list($w1, $w2) = $this->getWidgets();

        $this->stdParams = array(
            "widget1" => $w1,
            "widget2" => $w2,
        );
    }

    public function getWidgets() {

        $client = new CredentialsClient();
        $resp   = $client->strGetRawResponse();

        list($widget1, $widget2) = explode("\n", $resp);

        if (empty($widget1) or empty($widget2)) {
            throw new Exception('bad_resp');
        }

        return array(strtolower(trim($widget1)), trim($widget2));
    }

    public function blnUserExists($idnumber) {
        mtrace(sprintf("check user %s exists in DAS", $idnumber));
        $this->addParams();
        $this->params['serviceId'] = get_config('local_proctoru', 'localwebservice_userexists_servicename');
        $this->params['1'] = $idnumber;
        $this->params['2'] = get_config('local_proctoru', 'stu_profile');

        $xml = $this->xmlFetchResponse();
        
//        if(isset($xml->ERROR_MSG)){
//            throw new Exception(sprintf("Problem obtaining data for service %s, message was %s ",$this->params['serviceId'], $xml->ERROR_MSG));
//        }
        
        return (string)$xml->ROW->HAS_PROFILE == 'Y' ? true : false;
    }

    public function intPseudoId($idnumber){
        mtrace(sprintf("fetch PseudoID from DAS for user %s", $idnumber));
        $this->addParams();
        $this->params['serviceId'] = get_config('local_proctoru', 'localwebservice_fetchuser_servicename');
        $this->params['1'] = $idnumber;

        $xml = $this->xmlFetchResponse();

        if(isset($xml->ERROR_MSG)){
            throw new Exception(sprintf("Problem obtaining data for service %s, message was %s ",$this->params['serviceId'], $xml->ERROR_MSG));
        }
        return isset($xml->ROW->PSEUDO_ID) ? (int)(string)$xml->ROW->PSEUDO_ID : false;
    }
}

class ProctorUClient extends CurlXmlClient {
    static $errorCount;
    
    public function __construct(){
        $baseUrl   = get_config('local_proctoru', 'proctoru_api');
        $method    = 'get';
        $options   = array('cache' => true);
        parent::__construct($baseUrl, $method, $options);
        
        self::$errorCount = 0;
    }
    
    public function getCurl($remoteStudentIdnumber,$serviceName){
        $now   = new DateTime();
        $url   = $this->baseUrl.'/'.$serviceName;
        $meth  = $this->method;
        $curl  = new curl($this->options);
        $token = get_config('local_proctoru', 'proctoru_token');

        $curl->setHeader(sprintf('Authorization-Token: %s', $token));
        $this->params = array(
            'time_sent'     => $now->format(DateTime::ISO8601),
            'student_Id'    => $remoteStudentIdnumber
        );

        return $curl->$meth($url, $this->params);
    }

    /**
     * @Override
     * @return type
     */
    public function strRequestUserProfile($remoteStudentIdnumber) {
        mtrace(sprintf("fetching PU profile for user id = %s\n", $remoteStudentIdnumber));
        return json_decode($this->getCurl($remoteStudentIdnumber, 'getStudentProfile'));
    }
    
    /**
     * 
     * @param int $remoteStudentIdnumber 
     * @return int any of the ProctorU class constants
     */
    public function constUserStatus($remoteStudentIdnumber){
        $response    = $this->strRequestUserProfile($remoteStudentIdnumber);
        $strNotFound = isset($response->message) && strpos($response->message, 'Student Not Found');
        if($strNotFound){
            self::$errorCount++;
            if(self::$erroCount > 2){
                throw new Exception(sprintf("Exceeded 404 quota for class instance; count = %d", self::$errorCount));
            }
            return ProctorU::PU_NOT_FOUND;
        }else{
            return $response->data->hasimage == true ? ProctorU::VERIFIED : ProctorU::REGISTERED;
        }
    }
    
    public function filGetUserImage($remoteStudentIdnumber){
        global $CFG;
        
        $now   = new DateTime();
        $url   = $this->baseUrl.'/getStudentImage';

        $savePath = $CFG->dataroot.'/'.$remoteStudentIdnumber.'.jpg';
//        $dloadOptions = array('filepath' => $savePath);
//        $this->options += $dloadOptions;
        
        $curl  = new curl($this->options);
        $token = get_config('local_proctoru', 'proctoru_token');

        $curl->setHeader(sprintf('Authorization-Token: %s', $token));
        $this->params = array(
            'time_sent'     => $now->format(DateTime::ISO8601),
            'student_Id'    => $remoteStudentIdnumber
        );

        if($curl->download_one(
                $url, 
                $this->params, 
                array(
                    'filepath' => $savePath, 
                    'timeout' => 5, 
                    'followlocation' => true, 
                    'maxredirs' => 3
                    )
                )){
            return $savePath;
        }else{
            return false;
        }
        
        
    }
}
?>
