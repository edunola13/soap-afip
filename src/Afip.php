<?php
namespace Enola\Afip;
use SimpleXMLElement, SoapClient;
use DateTime;
use Exception;

include dirname(__DIR__) . '/src/TokenAuthorization.php';

/**
 * @author Ivan Muñoz ivanalemunioz@gmail.com
 * @package Enola\Afip
 */
class Afip {
    /**
     * File name for the WSDL corresponding to WSAA
     * @var string
     **/
    var $WSAA_WSDL;
    /**
     * The url to get WSAA token
     * @var string
     **/
    var $WSAA_URL;
    /**
     * File name for the X.509 certificate in PEM format
     * @var string
     **/
    var $CERT;
    /**
     * File name for the private key correspoding to CERT (PEM)
     * @var string
     **/
    var $PRIVATEKEY;
    /**
     * The passphrase (if any) to sign
     * @var string
     **/
    var $PASSPHRASE;
    /**
     * Afip resources folder
     * @var string
     **/
    var $RES_FOLDER;
    /**
     * Afip WS folder
     * @var string
     **/
    var $WS_FOLDER;
    /**
     * Afip CERT folder
     * Tambien se va a utilizar para guardar las credenciales de acceso
     * Requiere acceso de lectura/escritura
     * @var string
     **/
    var $CERT_FOLDER;
    /**
     * The CUIT to use
     * @var int
     **/
    var $CUIT;

    /**
     * Implemented Web Services
     * @var array[string]
     * @deprecated ahora usamos factory no mas __get
     **/
    var $implemented_ws = array(
        'Wsfev1',
        'Ws_sr_padron_a4',
        'Ws_sr_padron_a5',
        'Ws_sr_padron_a10'
    );

    /**
     * 
     * @param mixed $options
     * array(
     *      'CUIT' => CUIT del responsable (Obligatorio),
     *      'production' => FALSE/TRUE (Default FALSE),
     *      'passphrase' => (Opcional),
     *      'folderCert' => Carpeta donde se encuentran cert y key (bligatorio)
     *      'cert' => Nombre archivo certificado (Opcional, default 'cert'),
     *      'key' => Nombre archivo key (Opcional, default 'key')
     * )
     * @throws Exception
     */
    function __construct($options)
    {
        ini_set("soap.wsdl_cache_enabled", "0");

        if (!isset($options['CUIT'])) {
            throw new Exception("CUIT field is required in options array");
        }
        if (!isset($options['folderCert'])){
            throw new Exception("folderCert field is required in options array");
        }
        
        $this->CUIT = $options['CUIT'];
        if (!isset($options['production'])) {
            $options['production'] = FALSE;
        }
        if (!isset($options['passphrase'])) {
            $options['passphrase'] = 'xxxxx';
        }
        if (!isset($options['cert'])) {
            $options['cert'] = 'cert';
        }
        if (!isset($options['key'])) {
            $options['key'] = 'key';
        }

        $this->PASSPHRASE = $options['passphrase'];
        $this->CERT_FOLDER= rtrim($options['folderCert'], '/') . '/';
        $this->options = $options;        
 
        $dir_name = dirname(__FILE__);

        $this->RES_FOLDER 	= $dir_name.'/wsdl/';
        $this->WS_FOLDER 	= $dir_name.'/ws/';
        $this->CERT 		= $this->CERT_FOLDER .$options['cert'];
        $this->PRIVATEKEY 	= $this->CERT_FOLDER .$options['key'];

        $this->WSAA_WSDL 	= $this->RES_FOLDER.'wsaa.wsdl';
        if ($options['production'] === TRUE) {
            $this->WSAA_URL = 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
        }
        else{
            $this->WSAA_URL = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
        }
        
        if (!file_exists($this->CERT)) 
            throw new Exception("Failed to open ".$this->CERT."\n", 1);
        if (!file_exists($this->PRIVATEKEY)) 
            throw new Exception("Failed to open ".$this->PRIVATEKEY."\n", 2);
        if (!file_exists($this->WSAA_WSDL)) 
            throw new Exception("Failed to open ".$this->WSAA_WSDL."\n", 3);
    }

    /**
     * Gets token authorization for an AFIP Web Service
     * 
     * @param string $service Service for token authorization
     * 
     * @throws Exception if an error occurs
     *
     * @return TokenAutorization Token Autorization for AFIP Web Service 
    **/
    public function GetServiceTA($service, $continue = TRUE)
    {
        if (file_exists($this->CERT_FOLDER.'TA-'.$this->options['CUIT'].'-'.$service.'.xml')) {
            $TA = new SimpleXMLElement(file_get_contents($this->CERT_FOLDER.'TA-'.$this->options['CUIT'].'-'.$service.'.xml'));

            $actual_time = new DateTime(date('c',date('U')+600));
            $expiration_time = new DateTime($TA->header->expirationTime);

            if ($actual_time < $expiration_time) 
                return new TokenAutorization($TA->credentials->token, $TA->credentials->sign);
            else if ($continue === FALSE)
                throw new Exception("Error Getting TA", 5);
        }

        if ($this->CreateServiceTA($service)) 
            return $this->GetServiceTA($service, FALSE);
    }

    /**
     * Create an TA from WSAA
     *
     * Request to WSAA for a tokent authorization for service and save this
     * in a xml file
     *
     * @param string $service Service for token authorization
     * 
     * @throws Exception if an error occurs creating token authorization
     *
     * @return true if token authorization is created success
    **/
    private function CreateServiceTA($service)
    {
        //Creating TRA
        $TRA = new SimpleXMLElement(
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<loginTicketRequest version="1.0">'.
        '</loginTicketRequest>');
        $TRA->addChild('header');
        $TRA->header->addChild('uniqueId',date('U'));
        $TRA->header->addChild('generationTime',date('c',date('U')-600));
        $TRA->header->addChild('expirationTime',date('c',date('U')+600));
        $TRA->addChild('service',$service);
        $TRA->asXML($this->CERT_FOLDER.'TRA-'.$this->options['CUIT'].'-'.$service.'.xml');

        //Signing TRA
        $STATUS = openssl_pkcs7_sign($this->CERT_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".xml", $this->CERT_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".tmp", "file://".$this->CERT,
            array("file://".$this->PRIVATEKEY, $this->PASSPHRASE),
            array(),
            !PKCS7_DETACHED
        );
        if (!$STATUS) {return FALSE;}
        $inf = fopen($this->CERT_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".tmp", "r");
        $i = 0;
        $CMS="";
        while (!feof($inf)) {
            $buffer=fgets($inf);
            if ( $i++ >= 4 ) {$CMS.=$buffer;}
        }
        fclose($inf);
        unlink($this->CERT_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".xml");
        unlink($this->CERT_FOLDER."TRA-".$this->options['CUIT'].'-'.$service.".tmp");

        //Request TA to WSAA
        $client = new SoapClient($this->WSAA_WSDL, array(
            'soap_version'   => SOAP_1_2,
            'location'       => $this->WSAA_URL,
            'trace'          => 1,
            'exceptions'     => 0
        )); 
        $results=$client->loginCms(array('in0'=>$CMS));
        if (is_soap_fault($results)) 
            throw new Exception("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n", 4);

        $TA = $results->loginCmsReturn;

        if (file_put_contents($this->CERT_FOLDER.'TA-'.$this->options['CUIT'].'-'.$service.'.xml', $TA)) 
            return TRUE;
        else
            throw new Exception('Error writing "TA-'.$this->options['CUIT'].'-'.$service.'.xml"', 5);
    }

    /**
     * Lo deprecamos ya que ahora tenemos un factory
     * @deprecated
     */
    /*public function __get($property)
    {
        if (in_array($property, $this->implemented_ws)) {
            if (isset($this->{$property})) {
                return $this->{$property};
            }
            else{
                $property= "Enola\\Afip\\ws\\" . $property;
//                $file = $this->WS_FOLDER . $property. '.php';
//                if (!file_exists($file)) 
//                    throw new Exception("Failed to open ".$file."\n", 1);
//
//                include $file;
                return ($this->{$property} = new $property($this));
            }
        }
        else{
            return $this->{$property};
        }
    }*/    
    
    //
    //FACTORY
    public function getWsfev1(): ws\Wsfev1{
        return new ws\Wsfev1($this);
    }
    public function getWsSrPadronA4(): ws\Ws_sr_padron_a4{
        return new ws\Ws_sr_padron_a4($this);
    }
    public function getWsSrPadronA5(): ws\Ws_sr_padron_a5{
        return new ws\Ws_sr_padron_a5($this);
    }
    public function getWsSrPadronA10(): ws\Ws_sr_padron_a10{
        return new ws\Ws_sr_padron_a10($this);
    }
}