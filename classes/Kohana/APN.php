<?php defined('SYSPATH') or die('No direct script access.');

class Kohana_APN {
    /* Config constants */
    const PASSPHRASE = "passphrase";
    const ENVIRONMENT = "environment";
    const CERTIFICATE = "certificate";

    const PRODUCTION_SERVER = "production_server";
    const SANDBOX_SERVER = "sandbox_server";

    const PRODUCTION_FEEDBACK_SERVER = "production_feedback_server";
    const SANDBOX_FEEDBACK_SERVER = "sandbox_feedback_server";

    const CONNECT_TIMEOUT = 30;

	protected $m_config = null, $m_app_config = null;
    protected $m_stream, $m_context;

	public static function factory($app) {
		$apn = new APN();
        $apn->m_config = Kohana::$config->load("apn");
        $apn->m_app_config = $apn->m_config[$app];

        return $apn;
	}

    protected function serverAddress()
    {
        $env = $this->m_app_config[APN::ENVIRONMENT];
        if ($env == "production") {
            return $this->m_config[APN::PRODUCTION_SERVER];
        } else if ($env == "sandbox") {
            return $this->m_config[APN::SANDBOX_SERVER];
        } else {
            throw new Kohana_Exception("No such environment: " + $env);
        }
    }

    public function connect()
    {
        $this->m_context = stream_context_create();

        stream_context_set_option($this->m_context, 'ssl', 'local_cert',
            Kohana::find_file("certificates",$this->m_app_config[APN::CERTIFICATE], false));
        stream_context_set_option($this->m_context, 'ssl', 'passphrase', $this->m_app_config[APN::PASSPHRASE]);

        $this->m_stream = stream_socket_client($this->serverAddress(), $err, $errstr, $this->m_config[APN::CONNECT_TIMEOUT],
            STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $this->m_context);

        if (!$this->m_stream) {
            throw new Kohana_Exception("Connection failed: " . $err . ": " + $errstr);
        }
    }

    protected function performSend($deviceToken, $payload, $enhanced = false, $identifier = null, $expiry = null) {
        if ($enhanced) {
            if ($identifier === null) $identifier = rand(0, pow(2,32)-1);
            if ($expiry === null) $expiry = time() + (3600 * 24 * 30); /* 30 days */

            $msg =  chr(1) . pack('N', $identifier) . pack('N', $expiry) .
                    pack('n', 32) . pack('H*', $deviceToken) .
                    pack('n', strlen($payload)) . $payload;

            $result = fwrite($this->m_stream, $msg);

            if ($result === FALSE) {
                throw new Kohana_Exception("Couldn't send push notification");
            }

            return TRUE;
        } else {
            $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
            $result = fwrite($this->m_stream, $msg);

            if ($result === FALSE) {
                throw new Kohana_Exception("Couldn't send push notification");
            }

            return TRUE;
        }
    }

    public function send($deviceToken, $body = null, $identifier = null, $expiry = null) {
        $this->m_context && $this->m_stream || $this->connect();

        if ($body == null) {
            throw new Kohana_Exception("Parameter 'body' is mandatory.");
        }

        if (!is_array($body)) {
            $body = array(
                'aps' => array(
                    'alert' => $body,
                    'sound' => 'default'
                )
            );
        }

        $payload = json_encode($body);

        if (is_array($deviceToken)) {
            foreach($deviceToken as $token) {
                $this->performSend($token, $payload, true, $identifier, $expiry);
            }
        } else {
            $this->performSend($deviceToken, $payload, true, $identifier, $expiry);
        }
    }
}
