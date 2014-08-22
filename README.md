1. Create a 'certificates' directory in your application/ folder
2. Copy your pem file (which includes) its private key in application/certificates
3. Copy modules/apn/config/apn.php into application/config/apn.php and edit it:
	3.1 Change 'appname' in config array key to 'the name of your application'


 $apn = APN::factory('the name of your application');

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
