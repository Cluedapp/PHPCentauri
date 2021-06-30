<?php
	/**
	 * @package PHPCentauri
	 *
	 * Required PHP extensions:
	 *    php_openssl
	 *
	 * $key should be a string, and it can contain any amount of any characters
	 */

	function encrypt($plaintext, $key) {
		$cipher = 'AES-256-CBC';
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
		$hmac = hash_hmac('sha256', $ciphertext_raw, $key, true);
		$ciphertext = base64_encode($iv . $hmac . $ciphertext_raw);
		return $ciphertext;
	}

	function decrypt($ciphertext, $key) {
		$cipher = 'AES-256-CBC';
		$c = base64_decode($ciphertext);
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = substr($c, 0, $ivlen);
		$hmac = substr($c, $ivlen, $sha2len = 32);
		$ciphertext_raw = substr($c, $ivlen + $sha2len);
		$original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv);
		$calcmac = hash_hmac('sha256', $ciphertext_raw, $key, true);
		if (hash_equals($hmac, $calcmac))
			return $original_plaintext;
		return null;
	}
?>
