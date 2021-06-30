<?php
	/**
	 * @package PHPCentauri
	 */

	# Generate a random South African ID number
	function generate_sa_id() {
		$year = random_int(2000, 2099);
		$month = random_int(1, 12);
		$days = random_int(1, (function($year, $month) { $days = [31, 0, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]; return $month == 2 ? ($year % 4 == 0 && $year % 400 != 0 ? 29 : 28) : $days[$month - 1]; })($year, $month));
		$id = substr($year, 2, 2) .  str_pad($month, 2, '0', STR_PAD_LEFT) . str_pad($days, 2, '0', STR_PAD_LEFT) . str_pad(random_int(0, 9999), 4, '0') . '08';
		return $id . ((10 - ($id[0] + $id[2] + $id[4] + $id[6] + $id[8] + $id[10] + array_sum(preg_grep('/./', preg_split('//', (string)((int)($id[1] . $id[3] . $id[5] . $id[7] . $id[9] . $id[11]) * 2))))) % 10) % 10);
	}

	# Return true if $id is a valid South African ID number
	function sa_id($id) {
		return preg_match('/^\d{13}$/', $id) && (10 - ($id[0] + $id[2] + $id[4] + $id[6] + $id[8] + $id[10] + array_sum(preg_grep('/./', preg_split('//', (string)((int)($id[1] . $id[3] . $id[5] . $id[7] . $id[9] . $id[11]) * 2))))) % 10) % 10 == $id[12];
	}

	# Return true if $ip_address is within the range of known South African IP addresses
	function sa_ip($ip_address) {
		$ip = ip2long($ip_address);

		# Method 1 - https://geolite.maxmind.com/download/geoip/database/GeoLite2-Country-CSV.zip
		$sa_ip = false;
		if ($ip !== false && (AppSettings['GeoIPCountryCSVDatabasePath'] ?? '') && file_exists(AppSettings['GeoIPCountryCSVDatabasePath'])) {
			$ip_file = fopen(AppSettings['GeoIPCountryCSVDatabasePath'], 'r');
			while ($ip_entry = fgetcsv($ip_file)) {
				if ($ip_entry[1] == '953987' || $ip_entry[2] == '953987') { # South Africa == 953987
					$data = explode('/', $ip_entry[0]);
					$base_ip = ip2long($data[0]);
					$cidr_mask = (int)$data[1];
					for ($i = 31; $i >= 32 - $cidr_mask; --$i)
						if (($base_ip & (1 << $i)) != ($ip & (1 << $i)))
							continue 2; 
					$sa_ip = true;
					break;
				}
			}
			fclose($ip_file);
		}
		if ($sa_ip)
			return true;

		# Method 2
		preg_match_all('#<td>(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\</td>#', file_get_contents('https://lite.ip2location.com/south-africa-ip-address-ranges'), $data);
		$data = $data[1];
		for ($i = 0, $len = count($data); $i < $len; $i += 2) {
			$from = ip2long($data[$i]);
			$to = ip2long($data[$i + 1]);
			if ($from !== false && $to !== false && $ip >= $from && $ip <= $to) {
				return true;
			}
		}

		# Method 3
		$sa_ip = false;
		if ($ip !== false) {
			$ip_file = fopen('http://www.nirsoft.net/countryip/za.csv', 'r');
			while ($ip_entry = fgetcsv($ip_file)) {
				if (count($ip_entry) >= 2) {
					$from = ip2long($ip_entry[0]);
					$to = ip2long($ip_entry[1]);
					if ($from !== false && $to !== false && $ip >= $from && $ip <= $to) {
						$sa_ip = true;
						break;
					}
				}
			}
			fclose($ip_file);
		}
		return $sa_ip;
	}

	function sa_time() {
		$sa_offset = 7200;
		return time() + $sa_offset;
	}
?>
