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
            throw new ProctorUWebserviceException(ProctorU::_s('wrong_protocol',$baseUrl));
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
            throw new ProctorUWebserviceException(ProctorU::_s('general_curl_exception',get_class($this)));
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
            $a = new stdClass();
            $a->msg = $e->getMessage();
            $a->cls = get_class($this);
            $a->url = $this->baseUrl;
            throw new ProctorUWebserviceException(ProctorU::_s('xml_exception', $a));
        }
    }
}

class CredentialsClient extends CurlXmlClient {

    public function __construct() {
        $baseUrl   = ProctorU::_c('credentials_location');
        $method    = 'post';
        $options   = array('cache' => true);

        parent::__construct($baseUrl, $method, $options);

        $this->stdParams = array('credentials' => 'get');
        $this->addParams();
    }
}

class LocalDataStoreClient extends CurlXmlClient {

    public function __construct() {

        $baseUrl = ProctorU::_c('localwebservice_url');
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
            throw new ProctorUWebserviceCredentialsClientException(ProctorU::_s('missing_credentials'));
        }

        return array(strtolower(trim($widget1)), trim($widget2));
    }

    public function voidCheckError(SimpleXMLElement $xml) {

        if (isset($xml->ERROR_MSG)) {
            $a = new stdClass();
            $a->srv = $this->params['serviceId'];
            $a->msg = (string)$xml->ERROR_MSG;
            throw new ProctorUWebserviceLocalDataStoreException(ProctorU::_s('datastore_errors', $a));
        }
    }

    public function blnUserExists($idnumber) {
        mtrace(sprintf("check user %s exists in DAS", $idnumber));
        $this->addParams();
        $this->params['serviceId'] = ProctorU::_c('eligible_users_service');
        $this->params['1'] = $idnumber;
        $this->params['2'] = ProctorU::_c('stu_profile');

        $xml = $this->xmlFetchResponse();
        $this->voidCheckError($xml);

        return (string)$xml->ROW->HAS_PROFILE == 'Y' ? true : false;
    }

    public function intPseudoId($idnumber){
        mtrace(sprintf("fetch PseudoID from DAS for user %s", $idnumber));
        $this->addParams();
        $this->params['serviceId'] = ProctorU::_c('userid_service');
        $this->params['1'] = $idnumber;

        $xml = $this->xmlFetchResponse();
        $this->voidCheckError($xml);

        return isset($xml->ROW->PSEUDO_ID) ? (int)(string)$xml->ROW->PSEUDO_ID : false;
    }
}

class ProctorUClient extends CurlXmlClient {
    static $errorCount;
    
    public function __construct(){
        $baseUrl   = ProctorU::_c('proctoru_api');
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
        $token = ProctorU::_c('proctoru_token');

        $curl->setHeader(sprintf('Authorization-Token: %s', $token));
        $this->params = array(
            'time_sent'     => $now->format(DateTime::ISO8601),
            'student_id'    => $remoteStudentIdnumber
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
            $a = new stdClass();
            $a->uid = $remoteStudentIdnumber;
            $a->msg = print_r($response);
            throw new ProctorUWebserviceProctorUException(ProctorU::_s('pu_404', $a));
        }else{
            if (isset($response->data->hasimage)) {
                return $response->data->hasimage ? ProctorU::VERIFIED : ProctorU::REGISTERED;
            } else {
                return ProctorU::REGISTERED;
            }
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
