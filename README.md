1. Create a certificate directory in your application/ folder
2. Copy your pem file (which includes) its private key in application/certificate
3. Copy modules/apn/config/apn.php into application/config/apn.php and edit it


 $apn = APN::factory('name of the application');

 public function send($deviceToken, $body, $identifier = null, $expiry = null)

Simple usage:
 $apn->send("device token here", "alert text");
or
 $apn->send(array("device1", "device2"), "alert text");

Advanced usage:
 $body = array(
	    'aps' => array(
	        'alert' => 'APN test',
	        'sound' => 'default'
	    )
 );

 $apn->send("device token here", $body);
 or
 $apn->send(array("device1", "device2"), "alert text"); 
