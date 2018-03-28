<?php
namespace Enola\Afip;

/**
 * Token Autorization
 *
 * @package Enola\Afip
 * @author Ivan Muñoz
 **/
class TokenAutorization {
    /**
     * Authorization and authentication web service Token
     *
     * @var string
     **/
    var $token;

    /**
     * Authorization and authentication web service Sign
     *
     * @var string
     **/
    var $sign;

    function __construct($token, $sign)
    {
        $this->token 	= $token;
        $this->sign 	= $sign;
    }
}