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
            throw new ProctorUWebserviceException(sprintf("URL given in admin settings is malformed. Expected http/https, got '%s'",$baseUrl));
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
        try{
            $this->resp = $curl->$meth($this->baseUrl, $this->params);
        }catch(Exception $e){
            $msg = sprintf("Exception thrown while making a webservice request from class %s", get_class($this));
            throw new ProctorUWebserviceException($msg);
        }
        return $this->resp;
    }

    public function xmlFetchResponse() {
        try{
            $resp = $this->strGetRawResponse();

            libxml_use_internal_errors(true);
            $test = simplexml_load_string($resp);

            if($test === false){
                $msg = "";
                foreach(libxml_get_errors() as $err){
                    $msg .= sprintf("\t%s\n",$err->message);
                }
                throw new ProctorUWebserviceException($msg);
            }

            return new SimpleXMLElement($resp);
        }
        catch(Exception $e){
            $msg = sprintf("class %s generated an exception while trying to 
                convert the response from %s to XML. 
                Original exception message was\n '%s'", 
                    get_class($this),$this->baseUrl, $e->getMessage()
                    );
            throw new ProctorUWebserviceException($msg);
        }
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
            throw new ProctorUWebserviceCredentialsClientException('Missing one or both expected values in response from Credentials Client.');
        }

        return array(strtolower(trim($widget1)), trim($widget2));
    }

    public function voidCheckError(SimpleXMLElement $xml) {
        if (isset($xml->ERROR_MSG)) {
            throw new ProctorUWebserviceLocalDataStoreException(
                    sprintf("Problem obtaining data for service %s, 
                        message was %s ", $this->params['serviceId'], $xml->ERROR_MSG));
        }
    }

    public function blnUserExists($idnumber) {
        mtrace(sprintf("check user %s exists in DAS", $idnumber));
        $this->addParams();
        $this->params['serviceId'] = get_config('local_proctoru', 'localwebservice_userexists_servicename');
        $this->params['1'] = $idnumber;
        $this->params['2'] = get_config('local_proctoru', 'stu_profile');

        $xml = $this->xmlFetchResponse();
        $this->voidCheckError($xml);

        return (string)$xml->ROW->HAS_PROFILE == 'Y' ? true : false;
    }

    public function intPseudoId($idnumber){
        mtrace(sprintf("fetch PseudoID from DAS for user %s", $idnumber));
        $this->addParams();
        $this->params['serviceId'] = get_config('local_proctoru', 'localwebservice_fetchuser_servicename');
        $this->params['1'] = $idnumber;

        $xml = $this->xmlFetchResponse();
        $this->voidCheckError($xml);

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
            throw new ProctorUWebserviceProctorUException(
                    sprintf("Got 404 for user with PU id# %s\nFull response was:\n%s", 
                            $remoteStudentIdnumber,print_r($response)));
        }else{
            return $response->data->hasimage == true ? ProctorU::VERIFIED : ProctorU::REGISTERED;
        }
    }
}

class ProctorUWebserviceException extends ProctorUException {
    
}
class ProctorUWebserviceCredentialsClientException extends ProctorUException {
    
}
class ProctorUWebserviceLocalDataStoreException extends ProctorUException {
    
}
class ProctorUWebserviceProctorUException extends ProctorUException {
    
}
?>
