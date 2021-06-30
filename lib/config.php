<?php
	/**
	 * @package PHPCentauri
	 */

	if (defined('AppSettings')) {
		if (isset(AppSettings['Timezone']) && AppSettings['Timezone'])
			date_default_timezone_set(AppSettings['Timezone']);

		# Amazon AWS
		if (AppSettings['AWSAccessKeyID'] ?? false)
			putenv('AWS_ACCESS_KEY_ID=' . AppSettings['AWSAccessKeyID']);
		if (AppSettings['AWSSecretAccessKey'] ?? false)
			putenv('AWS_SECRET_ACCESS_KEY=' . AppSettings['AWSSecretAccessKey']);
	}
?>
