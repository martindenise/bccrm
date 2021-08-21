<?php
/**
 * CLICKATELL SMS API
 *
 * This class is meant to send SMS messages (with unicode support) via 
 * the Clickatell gateway and provides support to authenticate to this service and
 * query for the current account balance. This class uses the fopen to
 * communicate with the gateway via HTTP.
 *
 * For more information about CLICKATELL service visit http://www.clickatell.com
 * This class designed for PHP 4.3.0 or above
 */

/**
 * Main SMS-API class
 *
 * Example:
 * <code>
 * <?php
 * require_once "sms.class.php";
 * $mysms = new sms("331234");
 * $mysms->auth("user","xxx");
 * echo $mysms->getbalance();
 * $mysms->send("38160123", "TEST MESSAGE");
 * ?>
 * </code>
 */
 
 require_once "HttpClient.class.php";

class sms {
    /**
    * Clickatell API-ID
    * @var integer
    */
    var $api_id = "";

    /**
    * Callback
    * 0 - Off
    * 1 - Returns only intermediate statuses
    * 2 - Returns only final statuses
    * 3 - Returns both intermediate and final statuses
    * @var integer
    */
    var $callback = 0;

    /**
    * Session variable
    * @var mixed
    */
    var $session;

    /**
    * Class constructor
    * Create SMS object and authenticate SMS gateway
    * @param api_id string  Clickatell API-ID
    * @return object  New SMS object.
    * @access public
    */
    function sms($api_id) {
        $this->api_id = $api_id;
    }

    /**
    * Authenticate SMS gateway
    * @param user string  Clickatell username
    * @param password string  Clickatell password
    * @return mixed  "OK" or "ERR:<error-message>"
    * @access public
    */
    function auth($user, $password) {
        /* form url used to authenticate and request server to authenticate us */
        $resp = $this->_execgw(sprintf("auth?api_id=%s&user=%s&password=%s",
            $this->api_id, $user, $password));
        
        /* parse server response */
        if (strncmp("ERR:",$resp,4) == 0) {
            return $resp; /* an error has occured */
        }
    	$session = substr($resp, 4);
        $code = substr($resp, 0, 2);
        
        if (strcmp($code, "OK") != 0) {
            return "ERR:" . $resp;
        }
        
        $this->session = trim($session);
        return "OK";
    }

    /**
    * Query SMS credit balance
    * @return mixed  number of SMS credits or "ERR:<error-message>"
    * @access public
    */
    function getbalance() {
        /* form url used to get balance and request server to get balance */
        $resp = $this->_execgw(sprintf("getbalance?session_id=%s", $this->session));
        
        /* parse server response */
        if (strncmp("ERR:",$resp,4) == 0) {
            return $resp; /* an error has occured */
        }
    	$result = substr($resp, 8);
        
        return (int)$result; /* return the result */
    }

    /**
    * Send SMS message
    * @param to string  The destination address.
    * @param text string  The text content of the message
    * @param unicode bool  true if the text content in unicode format, false otherwise. (default is false)
    * @return mixed  "OK" or "ERR:<error-message>"
    * @access public
    */
    function send($to, $text, $unicode = false) {
        /* check text message length and requirement for concatenation */
        if ($unicode == true) {
            /* check the installation unicode PHP extension (mbstring) */
            if (!extension_loaded('mbstring')) {
                return "ERR:unicode extension (mbstring) module has not been loaded yet.";
            }
            
            $len = mb_strlen($text);
            if ($len > 210) {
        	    return "ERR:unicode text message could not be more than 210 characters.";
        	}
            $concat = ($len > 70 ? "&concat=3" : "");
        } else {
            $len = strlen($text);
            if ($len > 459) {
    	        return "ERR:text message could not be more than 459 characters.";
    	    }
            $concat = ($len > 160 ? "&concat=3" : "");
        }
        
        /* check for emptiness of source and and destination address */
        if (empty($to)) {
    	    return "ERR:no destination address given.";
    	}
        
        /* cleanup number, remove '+','(',')',whitespace character from number */
        $cleanup_chr = array ("+", " ", "(", ")", "\r", "\n", "\r\n", "\t");
        $to = str_replace($cleanup_chr, "", $to);
        
    	/* form url used to send sms */
    	$comm = sprintf("sendmsg?session_id=%s&to=%s&text=%s&callback=%s&unicode=%s%s",
            $this->session,
            rawurlencode($to),
            $this->_encode_message($text, $unicode),
            $this->callback,
            $unicode,
            $concat
        );
        
        /* request server to send sms */
        $resp = $this->_execgw($comm);
        
        /* parse server response */
        if (strncmp("ERR:", $resp, 4) == 0) {
            return $resp;
        }
    	$code = substr($resp, 0, 2);
    	if (strcmp($code,"ID") != 0) {
    	    return "ERR:" . $resp;
        }
        
        return "OK";
    }

    /**
    * Encode message text according to required standard
    * @param text string  Input text of message.
    * @param unicode bool  true if the text of message is in unicode format, false otherwise.
    * @return string  Return encoded text of message.
    * @access private
    */
    function _encode_message($text,$unicode) {
        if ($this->unicode != true) {
            /* standard encoding */
            return rawurlencode($text);
        }
        
        /* unicode encoding */
        $uni_text_len = mb_strlen($text, "UTF-8");
        $out_text = "";

        /* encode each character in text */
        for ($i=0; $i<$uni_text_len; $i++) {
            $out_text .= $this->_uniord(mb_substr($text, $i, 1, "UTF-8"));
        }

        return $out_text;
    }

    /**
    * Unicode function replacement for ord()
    * @param c mixed  Unicode character.
    * @return mixed  Return HEX value (with leading zero) of unicode character.
    * @access private
    */
    function _uniord($c) {
        $ud = 0;
        if (ord($c{0})>=0 && ord($c{0})<=127)
            $ud = ord($c{0});
        if (ord($c{0})>=192 && ord($c{0})<=223)
            $ud = (ord($c{0})-192)*64 + (ord($c{1})-128);
        if (ord($c{0})>=224 && ord($c{0})<=239)
            $ud = (ord($c{0})-224)*4096 + (ord($c{1})-128)*64 + (ord($c{2})-128);
        if (ord($c{0})>=240 && ord($c{0})<=247)
            $ud = (ord($c{0})-240)*262144 + (ord($c{1})-128)*4096 + (ord($c{2})-128)*64 + (ord($c{3})-128);
        if (ord($c{0})>=248 && ord($c{0})<=251)
            $ud = (ord($c{0})-248)*16777216 + (ord($c{1})-128)*262144 + (ord($c{2})-128)*4096 + (ord($c{3})-128)*64 + (ord($c{4})-128);
        if (ord($c{0})>=252 && ord($c{0})<=253)
            $ud = (ord($c{0})-252)*1073741824 + (ord($c{1})-128)*16777216 + (ord($c{2})-128)*262144 + (ord($c{3})-128)*4096 + (ord($c{4})-128)*64 + (ord($c{5})-128);
        if (ord($c{0})>=254 && ord($c{0})<=255) //error
            $ud = false;
        return sprintf("%04x", $ud);
    }

    /**
     * Execute gateway commands using fopen method
     * @return string  "ERR:<error-message>" or gateway response
     * @access private
     */
    function _execgw($command) {
        $result = "";
        
        /* open connection */
		$handler = new HttpClient("api.clickatell.com");
		if (!$handler->get("/http/$command")) {
			return "ERR:could not connect to server (" . $handler->getError() . ").";
		}
        
        /* return server response */
        return $handler->getContent();
    }
}

?>