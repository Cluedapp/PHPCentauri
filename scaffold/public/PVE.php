<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file implements the main router entry point (i.e. handler)
	 * for the PHPCentauri View Engine (PVE), which is Cluedapp's
	 * generic HTML View Engine middleware.
	 *
	 * By convention, a file with a .pchtml extension should be
	 * regarded as a PVE view, and such as file is called a PCHTML
	 * view.
	 *
	 * A rewrite should be added to IIS, Nginx or Apache, so that
	 * URLs are routed correctly to PVE:
	 * # Route to PVE handler (PHPCentauri View Engine)
	 * rewrite /([^.?/]+)/?$ /api/pve.php?view=[1]&view_dir=[2] last;
	 
	 * where [1] = absolute path to .pchtml file, including
	 *             filename and extension
	 *   and [2] = absolute path to the common ancestor directory
	 *             under which all PVE views are stored
	 * for example:
     * rewrite (?i)/(.+)/?$ /api/pve.php?view=$1.pchtml&view_dir=/root/path/to/pchtml/files last;
	 *
	 * An example PVE .pchtml view file is given below. Note
	 * that any <tag> names can be used, as tags are simply
	 * section placeholders used by the master template file.
	 *
	 * PHP code can be used anywhere in a view file or in any
	 * of its master template files, by using <?php code; ?>.
	 *
	 * When compared to the ASP.NET Razor view engine, a PVE
	 * master template file is analogous to a Razor "@Layout",
	 * and a PVE <tag> section placeholder is similar to a
	 * Razor "@Section".
	 *
	 * <template>
	 *     path/to/template.php
	 *     OR empty (i.e. <template />), then AppSettings['DefaultPVETemplate'] is used as the view's template
	 *     OR null, then the content of the <content> tag is returned as the view's response
	 * </template>
	 *
	 * <content>
	 *     <div><?php echo 'The date and time is: ', strftime('%c'); ?></div>
	 * </content>
	 *
	 * <script>
	 *     if (window) { window.alert('The date and time is: <?php echo strftime('%c'); ?>'); }
	 * </script>
	 *
	 * <footer>
	 *     <div>Some content</div>
	 * </footer>
	 */

	require_once '../vendor/autoload.php';

	if (isset($_GET['view'], $_GET['view_dir'])) {
		$view = $_GET['view']; # passed by web server rewrite
		$view_dir = $_GET['view_dir'] ?? realpath(AppSettings['ViewRelativePath'] ?? ''); # passed by web server rewrite or read from default setting
		view($view, $view_dir, false);
	} else
		log_error('PVE no view to display', 'view', $_GET['view'], 'passed view_dir', $_GET['view_dir'], 'default view_dir', realpath(AppSettings['ViewRelativePath'] ?? ''));
?>
