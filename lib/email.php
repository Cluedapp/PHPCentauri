<?php
	/**
	 * @package PHPCentauri
	 *
	 * Required Composer packages:
	 *    phpmailer/phpmailer
	 */

	/**
		@summary Function email sends an email using PHPMailer library

		@param $from			1d array [from email address, from name]
		@param $to				2d array [[to email address 1, to name 1], [to email address 2, to name 2], ...]
		@param $cc				2d array [[CC email address 1, CC name 1], [CC email address 2, CC name 2], ...]
		@param $subject			The email subject
		@param $body			The email body
		@param $attachments		2d array of objects [{Name, Data (base64)}, {Name, Data (base64)}, ...]
		@param $is_html			Truthy if email body should be sent as HTML
		@param $debug			Truthy if debug mode should be enabled
		@param $reply_to		Optional 1d array [reply to address, reply to name]
		@param $bcc				Optional 2d array [[BCC email address 1, BCC name 1], [BCC email address 2, BCC name 2], ...]
	*/
	function email($from, $to, $cc, $subject, $body, $attachments = [], $is_html = true, $debug = false, $reply_to = null, $bcc = []) {
		$mail = new PHPMailer\PHPMailer\PHPMailer(false);                 # Passing `true` enables exceptions

		# Server settings
		if ($debug)
			$mail->SMTPDebug = 1;                                         # Enable verbose debug output

		$mail->isSMTP();                                                  # Set mailer to use SMTP
		$mail->Host = AppSettings['PHPMailerHost'];                       # Specify main and backup SMTP servers
		$mail->SMTPAuth = AppSettings['PHPMailerSMTPAuth'];               # Enable SMTP authentication, `true` or `false`
		$mail->Username = AppSettings['PHPMailerUsername'];               # SMTP username
		$mail->Password = AppSettings['PHPMailerPassword'];               # SMTP password
		$mail->SMTPSecure = AppSettings['PHPMailerSMTPSecure'];           # Enable TLS encryption with `tls`, `ssl` also accepted, disable with `false`
		$mail->SMTPAutoTLS = AppSettings['PHPMailerSMTPAutoTLS'];
		$mail->Port = AppSettings['PHPMailerPort'];                       # TCP port to connect to

		# Recipients
		$mail->setFrom($from[0], $from[1]);
		foreach ($to as $_to)
			$mail->addAddress($_to[0], $_to[1]);
		foreach ($cc as $_cc)
		    $mail->addCC($_cc[0], $_cc[1]);
		foreach ($bcc as $_bcc)
		    $mail->addBCC($_bcc[0], $_bcc[1]);

		# Reply To
		if ($reply_to && is_array($reply_to))
			$mail->addReplyTo($reply_to[0], $reply_to[1]);

		# Content
		$mail->isHTML(!!$is_html);
		$mail->Subject = $subject;
		$mail->Body    = $body;

		# Attachments
		foreach ($attachments as $attachment) {
			$attachment = (array) $attachment;
			$mail->AddStringAttachment(base64_decode($attachment['Data']), $attachment['Name']);
		}

		$mail->send();
	}
?>
