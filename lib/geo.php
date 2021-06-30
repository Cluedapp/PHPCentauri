<?php
	/**
	 * @package PHPCentauri
	 *
	 * Required Composer packages:
	 *    geoip2/geoip2
	 */

	use GeoIp2\Database\Reader;

	function geo() {
		static $reader = null;

		if ($reader === null)
			$reader = new Reader(AppSettings['GeoIPCityMaxMindDatabasePath'] ?? '');

		return $reader;
	}

	function geoip() {
		try {
			$record = geo()->city(get_ip());
			return "{$record->city->name}, {$record->country->isoCode}";
		} catch (Exception $e) {
			return 'Unknown City, Unknown Country';
		}
	}

	function country() {
		try {
			return geo()->city(get_ip())->country->isoCode;
		} catch (Exception $e) {
			return null;
		}
	}
?>
