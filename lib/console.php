<?php
	/**
	 * @package PHPCentauri
	 */

	# Set the console/terminal text color using ANSI/ASCII escape code sequence
	function set_console_color($color) {
		# Character 27 is the ANSI escape control code, and 27 is 1B in hex
		# The complete escape code sequence format to change the console color is:
		#   \x1B[<COLOR>m to change console text color to COLOR (without <>'s)
		#   \x1B[0m to reset to the default console text color
		echo "\x1B[{$color}m";
	}
?>
