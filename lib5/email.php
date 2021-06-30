<?php
	/**
	 * @package PHPCentauri for PHP 5
	 *
	 * Required Composer packages:
	 *    phpmailer/phpmailer
	 */

	/**
		@summary		Function email sends an email using PHPMailer library

		@param $from	1d array [from email address, from name]
		@param $to		2d array [[to email address 1, to name 1], [to email address 2, to name 2], ...]
		@param $subject	The email subject
		@param $body	The email body
		@param $is_html	Truthy if email body should be sent as HTML
	*/
	function email($settings, $from, $to, $subject, $body, $is_html) {
		$mail = new PHPMailer\PHPMailer\PHPMailer(false);               # Passing `true` enables exceptions

		# Server settings
		# $mail->SMTPDebug = 2;                                         # Enable verbose debug output
		$mail->isSMTP();                                                # Set mailer to use SMTP
		$mail->Host = $settings['PHPMailerHost'];                       # Specify main and backup SMTP servers
		$mail->SMTPAuth = $settings['PHPMailerSMTPAuth'];               # Enable SMTP authentication, `true` or `false`
		if ($mail->SMTPAuth) {
			$mail->Username = $settings['PHPMailerUsername'];           # SMTP username
			$mail->Password = $settings['PHPMailerPassword'];           # SMTP password
		}
		$mail->SMTPSecure = $settings['PHPMailerSMTPSecure'];           # Enable TLS encryption with `tls`, `ssl` also accepted, disable with `false`
		$mail->SMTPAutoTLS = $settings['PHPMailerSMTPAutoTLS'];
		$mail->Port = $settings['PHPMailerPort'];                       # TCP port to connect to

		# Recipients
		$mail->setFrom($from[0], $from[1]);
		foreach ($to as $_to)
			$mail->addAddress($_to[0], $_to[1]);

		# Content
		$mail->isHTML(!!$is_html);
		$mail->Subject = $subject;
		$mail->Body    = $body;

		$mail->send();
	}
?>
