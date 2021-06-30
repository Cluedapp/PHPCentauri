<?php
	/**
	 * @package PHPCentauri
	 *
	 * Required Composer packages:
	 *    sendgrid/sendgrid
	 *
	 * Required app settings:
	 *    SendgridApiKey
	 */

	function sendgrid($from, $to, $subject, $body, $is_html) {
		$email = new \SendGrid\Mail\Mail(); 
		$email->setFrom($from['email'], $from['name']);
		$email->setSubject($subject);
		foreach ($to as $_to)
			$email->addTo($_to['email'], $_to['name']);
		$email->addContent($is_html ? 'text/html' : 'text/plain', $body);
		$sendgrid = new \SendGrid(AppSettings['SendgridApiKey']);
		try {
			$response = $sendgrid->send($email);
			return $response->statusCode();
		} catch (Exception $e) {
		}
		return null;
	}
?>
