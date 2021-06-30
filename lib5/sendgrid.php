<?php
	/**
	 * @package PHPCentauri for PHP 5
	 *
	 * Required Composer packages:
	 *    sendgrid/sendgrid
	 */

	function sendgrid($api_key, $from, $to, $subject, $body, $is_html) {
		$email = new \SendGrid\Mail\Mail(); 
		$email->setFrom($from['email'], $from['name']);
		$email->setSubject($subject);
		foreach ($to as $_to)
			$email->addTo($_to['email'], $_to['name']);
		$email->addContent($is_html ? 'text/html' : 'text/plain', $body);
		$sendgrid = new \SendGrid($api_key);
		try {
			$response = $sendgrid->send($email);
			return $response->statusCode();
		} catch (Exception $e) {
		}
		return null;
	}
?>
