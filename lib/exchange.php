<?php
	/**
	 * @package PHPCentauri
	 */

	function add_exchange_rate(&$to_dataset, $currency_code, $rate, $weight) {
		$currency_code = strtoupper($currency_code);
		if (!isset($to_dataset[$currency_code]))
			$to_dataset[$currency_code] = [];
		if ($rate > 0 && $weight > 0)
			$to_dataset[$currency_code][] = [$rate, $weight];
	}

	function exchange_rate_cryptowatch($cryptocurrency_code, $currencies) {
		$rates = [];

		foreach ($currencies as $currency_code) {
			$currency_code = strtolower($currency_code);
			$temp = json_decode(file_get_contents("https://api.cryptowat.ch/pairs/$cryptocurrency_code$currency_code"));
			if ($temp->result->markets ?? false) {
				foreach ($temp->result->markets as $market) {
					# Get exchange rate volume (weight)
					$volume = json_decode(file_get_contents("{$market->route}/summary"));
					if ($volume->result->volume ?? false) {
						log_error("Cryptowatch $cryptocurrency_code $currency_code {$market->exchange} volume {$volume->result->volume}");
						$volume = $volume->result->volume;

						# Get exchange rate price
						$price = json_decode(file_get_contents("{$market->route}/price"));
						if ($price->result->price ?? false) {
							log_error("Cryptowatch $cryptocurrency_code $currency_code {$market->exchange} price {$price->result->price}");
							add_exchange_rate($rates, $currency_code, $price->result->price, $volume);
						}
					}
				}
			}
		}

		# Get the arithmetic weighted mean price for each currency
		$output = [];
		foreach ($rates as $currency_code => $rate_weight_array) {
			$sum = 0;
			$rate = 0;
			foreach ($rate_weight_array as $rate_weight)
				$sum += $rate_weight[1];
			foreach ($rate_weight_array as $rate_weight)
				$rate += $rate_weight[0] * $rate_weight[1] / $sum;
			$output[$currency_code] = $rate;
		}

		return $output;
	}
?>
