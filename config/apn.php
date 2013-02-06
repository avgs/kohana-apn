<?php defined('SYSPATH') or die('No direct script access.');

return array(
	'appname' => array(
		APN::CERTIFICATE  => "ck.pem",
		APN::PASSPHRASE => "I don't always set passphrases on my certs, but when I do, I don't set them in the default config.",
		APN::ENVIRONMENT => "sandbox", /* or "production" */
	),

    APN::CONNECT_TIMEOUT => 60,

	/* Don't touch */
	APN::PRODUCTION_SERVER => "ssl://gateway.push.apple.com:2195",
	APN::SANDBOX_SERVER => "ssl://gateway.push.apple.com:2195"


);
