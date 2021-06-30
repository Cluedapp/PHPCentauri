<?php
	/**
	 * @package PHPCentauri
	 *
	 * Required PHP extensions:
	 *    php_gd2
	 */

	function crop_image($image) {
		return imagecropauto($image, IMG_CROP_SIDES);
	}
?>
