<?php
defined('SYSPATH') or die('No direct script access.');

class Kohana_APN
   {
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

   public static function factory($app)
      {
      $apn = new APN();
      $apn->m_config = Kohana::$config->load("apn");
      $apn->m_app_config = $apn->m_config[$app];
      return $apn;

      }

   protected function serverAddress()
      {
      $env = $this->m_app_config[APN::ENVIRONMENT];
      if ($env == "production")
         {
         return $this->m_config[APN::PRODUCTION_SERVER];
         }
      else if ($env == "sandbox")
         {
         return $this->m_config[APN::SANDBOX_SERVER];
         }
      else
         {
         throw new Kohana_Exception("No such environment: " + $env);
         }

      }

   public function connect()
      {
      $this->m_context = stream_context_create();
      stream_context_set_option($this->m_context, 'ssl', 'local_cert', Kohana::find_file("certificates", $this->m_app_config[APN::CERTIFICATE], false));
      stream_context_set_option($this->m_context, 'ssl', 'passphrase', $this->m_app_config[APN::PASSPHRASE]);
      //echo "Connection to server: " . $this->serverAddress();
      $this->m_stream = stream_socket_client($this->serverAddress(), $err, $errstr, $this->m_config[APN::CONNECT_TIMEOUT], STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $this->m_context);
      //echo "\r\nConnected";
      if (!$this->m_stream)
         {
         throw new Kohana_Exception("Connection failed: " . $err . ": " + $errstr);
         }
      stream_set_blocking($this->m_stream, 0);
      }

   protected function performSend($deviceToken, $payload, $enhanced = false, $identifier = null, $expiry = null)
      {
      //echo "\r\nSending to: " . $deviceToken . " message: " . $payload . "\r\n";
      if ($enhanced)
         {
         if ($identifier === null)
            {
            $identifier = rand(0, pow(2, 32) - 1);
            }
         if ($expiry === null)
            {
            $expiry = time() + (3600 * 24 * 30);
            } /* 30 days */
         $msg = chr(1) . pack('N', $identifier) . pack('N', $expiry) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
         $result = fwrite($this->m_stream, $msg);
         if ($result === FALSE)
            {
            throw new Kohana_Exception("Couldn't send push notification");
            }
         //
         // Check for errors
         //
         $this->checkAppleErrorResponse($this->m_stream);
         return TRUE;
         }
      else
         {
         $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
         $result = fwrite($this->m_stream, $msg);
         if ($result === FALSE)
            {
            throw new Kohana_Exception("Couldn't send push notification");
            }
         //
         // Check for errors
         //
         $this->checkAppleErrorResponse($this->m_stream);
         return TRUE;
         }

      }

   public function send($deviceToken, $body = null, $identifier = null, $expiry = null)
      {
      $this->m_context && $this->m_stream || $this->connect();
      if ($body == null)
         {
         throw new Kohana_Exception("Parameter 'body' is mandatory.");
         }
      if (!is_array($body))
         {
         $body = array('aps' => array('alert' => $body, 'sound' => 'default'));
         }
      $payload = json_encode($body);
      if (is_array($deviceToken))
         {
         foreach ($deviceToken as $token)
            {
            if ($token != "")
               {
               echo "Sending to:" . $token . PHP_EOL;
               $this->performSend($token, $payload, true, $identifier, $expiry);
               }
            }
         }
      else
         {
         $this->performSend($deviceToken, $payload, true, $identifier, $expiry);
         }

      }

   /**
    *
    * FUNCTION to check if there is an error response from Apple
    *
    * @param $fp
    * @return bool - Returns TRUE if there was and FALSE if there was not
    */
   function checkAppleErrorResponse($fp)
      {

      $apple_error_response = fread($fp, 6); //byte1=always 8, byte2=StatusCode, bytes3,4,5,6=identifier(rowID). Should return nothing if OK.
      //NOTE: Make sure you set stream_set_blocking($fp, 0) or else fread will pause your script and wait forever when there is no response to be sent.

      if ($apple_error_response)
         {

         $error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response); //unpack the error response (first byte 'command" should always be 8)

         if ($error_response['status_code'] == '0')
            {
            $error_response['status_code'] = '0-No errors encountered';

            }
         else if ($error_response['status_code'] == '1')
            {
            $error_response['status_code'] = '1-Processing error';

            }
         else if ($error_response['status_code'] == '2')
            {
            $error_response['status_code'] = '2-Missing device token';

            }
         else if ($error_response['status_code'] == '3')
            {
            $error_response['status_code'] = '3-Missing topic';

            }
         else if ($error_response['status_code'] == '4')
            {
            $error_response['status_code'] = '4-Missing payload';

            }
         else if ($error_response['status_code'] == '5')
            {
            $error_response['status_code'] = '5-Invalid token size';

            }
         else if ($error_response['status_code'] == '6')
            {
            $error_response['status_code'] = '6-Invalid topic size';

            }
         else if ($error_response['status_code'] == '7')
            {
            $error_response['status_code'] = '7-Invalid payload size';

            }
         else if ($error_response['status_code'] == '8')
            {
            $error_response['status_code'] = '8-Invalid token';

            }
         else if ($error_response['status_code'] == '255')
            {
            $error_response['status_code'] = '255-None (unknown)';

            }
         else
            {
            $error_response['status_code'] = $error_response['status_code'] . '-Not listed';

            }

         echo '<br><b>+ + + + + + ERROR</b> Response Command:<b>' . $error_response['command'] . '</b>&nbsp;&nbsp;&nbsp;Identifier:<b>' . $error_response['identifier'] . '</b>&nbsp;&nbsp;&nbsp;Status:<b>' . $error_response['status_code'] . '</b><br>';
         echo 'Identifier is the rowID (index) in the database that caused the problem, and Apple will disconnect you from server. To continue sending Push Notifications, just start at the next rowID after this Identifier.<br>';

         return true;
         }
      return false;
      }

   /**
    *
    * Invoke Apple feedback service to obtain not valid device tokens
    *
    */
   public function feedback()
      {

      $ctx = stream_context_create();
      stream_context_set_option($ctx, 'ssl', 'local_cert', Kohana::find_file("certificates", $this->m_app_config[APN::CERTIFICATE], false));
      stream_context_set_option($ctx, 'ssl', 'passphrase', $this->m_app_config[APN::PASSPHRASE]);

      // Open a connection to the APNS server
      $fp = stream_socket_client('ssl://feedback.push.apple.com:2196', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);

      if (!$fp)
         {
         exit("Failed to connect: $err $errstr" . PHP_EOL);
         }

      echo 'Connected to APNS' . PHP_EOL;

      while (!feof($fp))
         {
         $data = fgets($fp, 1024);
         echo $data;
         if (strlen($data) > 0)
            {
            var_dump(unpack("N1timestamp/n1length/H*devtoken", $data));
            }
         }
      // Close the connection to the server
      fclose($fp);

      }
   }
