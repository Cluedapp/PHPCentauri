<?php
	/**
	 *
	 * Example settings for a PHPCentauri API
	 */

	define('AppSettings', [
		# Amazon AWS
		'AWSAccessKeyID' => '',
		'AWSSecretAccessKey' => '',
		'AWSRegion' => '',
		'AWSEndpoint' => '', # optional

		# Azure Table Storage
		# 'DataCacheDirectory' => '',
		'DataCacheEnabled' => false,
		'DefaultPartitionKey' => '',
		# 'LoginPartitionKey' => '',
		# 'LoginTableStorageConnectionString' => '',
		'TableStorageConnectionString' => '',

		# Communication
		'MaxPostSize' => 50000, # 50 KB

		# DBI (DataBase Interface)
		'DBProvider' => 'azure', # one of the following: 'azure', 'dynamodb'

		# Logging - Debug
		'Debug' => ['.+'], # only log when the current script filename matches one or more of the regexes
		'LogStackTrace' => false,
		'LogUnhandledExceptions' => false,

		# Logging - File
		'DebugLogFile' => '/path/to/debug_log_file',
		'ErrorLogFile' => '/path/to/error_log_file',
		'ExceptionLogFile' => '/path/to/exception_log_file',
		'InfoLogFile' => '/path/to/info_log_file',
		'LogFile' => '/dev/null', # general fallback log file for PHPCentauri backwards-compatibility
		'TraceLogFile' => '/path/to/trace_log_file',
		'WarnLogFile' => '/path/to/warn_log_file',

		# Logging - UDP
		'UDPErrorLog' => true,
		'UDPErrorLogHost' => 'example.com',
		'UDPErrorLogMessageFormat' => 'API Name: %s',
		'UDPErrorLogPort' => 12345,

		# GeoIP
		'GeoIPCityMaxMindDatabasePath' => '/path/to/GeoLite2-City.mmdb',
		'GeoIPCountryCSVDatabasePath' => '/path/to/GeoLite2-Country-Blocks-IPv4.csv',

		# Google
		'GoogleAppAccountEmail' => '', # FYI
		'GoogleAppID' => '', # FYI
		'GoogleAppName' => '', # FYI
		'GoogleAppScopes' => ['openid', 'email', 'profile', /*'https://www.googleapis.com/auth/userinfo.email', 'https://www.googleapis.com/auth/userinfo.profile', Google_Service_Drive::DRIVE*/], # some standard scopes. If changes are made to this array of scopes, the old refresh token will not include the new scope(s) (and/or it will still include the old scope(s)), and so, to avoid "Insufficient Permission" API errors getting returned when calling an API in the code: 1. delete the cached refresh token, 2. delete the cached access token, 3. re-login at GoogleLoginRedirectURL
		'GoogleAutoLoginRedirect' => true, # true to automatically redirect to login page if no valid access token is available
		'GoogleClientID' => '', # set from OAuth2 Credentials created in Google Developer Console for this specific API (should match GoogleAppID above, it's not required, but just obviously logical/semantic that they should match, otherwise something must be wrong!)
		'GoogleClientSecret' => '', # set from OAuth2 Credentials created in Google Developer Console for this specific API (should match GoogleAppID above, it's not required, but just obviously logical/semantic that they should match, otherwise something must be wrong!)
		'GoogleLoginRedirectURL' => '', # the URL to be redirected back to if GoogleAutoLoginRedirect == true. This must usually also match with the value specified in Google Developer Console

		# Google Drive
		'GoogleDriveStorageEmailAddress' => '',

		# HTTP
		'HttpApiProtocol' => 4,
		'RequireHTTPS' => false,
		'SessionUserIDPropertyName' => 'UserID', # Name of the User ID property in the session object, e.g. UserID, AccountID, PersonID, etc.
		'VerifyRequest' => true, # Set to false to completely disable request verification
		'VerifyRequestTime' => true,

		# Http3
		'Http3Key' => '/path/to/key',
		'Http3Keys' => [
			'https://url' => '/path/to/http3_key_for_url'
		],

		# PHPMailer
		'PHPMailerHost' => '',
		'PHPMailerSMTPAuth' => true,
		'PHPMailerUsername' => '',
		'PHPMailerPassword' => '',
		'PHPMailerSMTPSecure' => false,
		'PHPMailerSMTPAutoTLS' => false,
		'PHPMailerPort' => 0,

		# PVE
		'ViewRelativePath' => '..', # the root directory containing your .pchtml views, relative to the "public" directory's physical path (i.e. hardlink or real) in the API website directory. In other words, not relative the path pointed to by a symlink/hardlink, but by the path of the symlink/hardlink itself (in terms of a C analogy, the path would be the C pointer's address, not the value pointed to by the C pointer.) This relative path is used by PVE when a PCE controller action calls the PVE view() function to render and return a PCHTML view
		'DefaultPVETemplate' => 'templates/master.php', # default fallback master template file, relative to the absolute path to the views base directory specified above

		# RSA
		'RsaPrivateKey' => '/path/to/rsa_private_key_pem_file',

		# Security
		'AbuseBanTime' => 300, # seconds, how long a client is banned after abuse is detected
		'AbuseWindowAttempts' => 60, # max connection attempts allowed during the AbuseWindowTime, abuse is detected if connections > AbuseWindowAttempts during AbuseWindowTime
		'AbuseWindowTime' => 60, # seconds, window of time during which to detect if a client's connection count is abusive
		'AllowedOrigins' => ['^https?:\/\/.*url.com$'],

		# Sendgrid
		'SendgridApiKey' => '',
		'SendgridEmailFrom' => '',
		'SendgridEmailFromName' => '',

		# Session
		'RedisConnectionString' => 'tcp://127.0.0.1:6379',
		'SessionIDLength' => 16,
		'SessionKeyPrefix' => 'prefix-',
		'SessionStore' => 'redis', # "file" or "redis"
		'SessionTTLInSeconds' => 86400,

		# System
		'SystemCacheSessionKeyPrefix' => 'system-prefix-',
		'SystemCacheStore' => 'redis', # "file" or "redis"
		'SystemCacheTTL' => 60 * 60 * 24 * 1,
		'Timezone' => 'UTC', # the default timezone for the time() function
		'TimeDisplayOffset' => 0, # the amount PHPCentauri adds to time() when it internally displays time textually. Does not have an effect outside PHPCentauri

		# URLs
		'ApiUrl' => 'https://url.com'
	]);

	# Amazon AWS
	if (AppSettings['AWSAccessKeyID'] ?? false)
		putenv('AWS_ACCESS_KEY_ID=' . AppSettings['AWSAccessKeyID']);
	if (AppSettings['AWSSecretAccessKey'] ?? false)
		putenv('AWS_SECRET_ACCESS_KEY=' . AppSettings['AWSSecretAccessKey']);

	# PDO Database
	define('PDO_DB_TYPE', 'mysql');
	define('PDO_HOST', 'localhost');
	define('PDO_FALLBACK_HOSTS', 'localhost'); # comma-separated string or array of fallback database hosts
	define('PDO_DB_NAME', 'db');
	define('PDO_USERNAME', 'user');
	define('PDO_PASSWORD', 'password');
	define('PDO_CHARSET', 'UTF8');
?>
