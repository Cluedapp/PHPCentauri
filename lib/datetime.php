<?php
	/**
	 * @package PHPCentauri
	 */

	function date_now() {
		return date_format(new DateTime, 'Y-m-d');
	}

	function time_ms() {
		return (int)(microtime(true) * 1000);
	}

	function time_since($old_time) {
		$diff = date_diff(date_create("@$old_time"), date_create('@' . time()));
		$years = $diff->y;
		$months = $diff->m;
		$days = $diff->d;
		$hours = $diff->h;
		$minutes = $diff->i;
		$plural = function($amount) { return $amount == 1 ? '' : 's'; };
		$conjugate = function() { $args = func_get_args(); $count = count(array_filter($args, function($value) { return $value > 0; })); return $count == 0 ? '' : ($count == 1 ? ' and ' : ', '); };
		$result = ($years >= 1 ? "$years year{$plural($years)}{$conjugate($months, $days, $hours, $minutes)}" : '') . ($months >= 1 ? "$months month{$plural($months)}{$conjugate($days, $hours, $minutes)}" : '') . ($days >= 1 ? "$days day{$plural($days)}{$conjugate($hours, $minutes)}" : '') . ($hours >= 1 ? "$hours hour{$plural($hours)}{$conjugate($minutes)}" : '') . ($minutes >= 1 ? "$minutes minute{$plural($minutes)}{$conjugate()}" : '');
		return $result . ($result ? ' ago' : 'just now');
	}
?>
