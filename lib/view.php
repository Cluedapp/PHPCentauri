<?php
	/**
	 * @package PHPCentauri
	 *
	 * This file implements the infrastructure methods for
	 * PHPCentauri View Engine (PVE).
	 */

	function render($tag_name, $view_context = null, $recurse = true) {
		$doc = new DOMDocument;
		$view_contexts = view_contexts();
		if ($view_context !== null)
			$view_contexts[] = $view_context;
		if (!$recurse)
			$view_contexts = [first($view_contexts)];
		foreach ($view_contexts as $view_context) {
			/*
			@$doc->loadHTML($view_context); # since PCHTML can use any tags as sections, ignore warnings that HTML is invalid
			$content = $doc->getElementsByTagName($tag_name)->item(0);
			if ($content) $content = $content->c14n();
			*/
			$idx_start = strpos($view_context, "<$tag_name");
			$idx_end1 = strpos($view_context, ">", $idx_start);
			$idx_end2 = strpos($view_context, "/>", $idx_start);
			$idx_end3 = strpos($view_context, "</$tag_name>", $idx_start);
			$idx_end = $idx_end1 !== false && $idx_end2 !== false && $idx_end2 < $idx_end1 ? $idx_end2 : $idx_end3;
			if ($idx_start !== false && $idx_end !== false) {
				if ($idx_end == $idx_end3) { # if true, the tag has content. if false, then an empty tag (with a resultant empty string) was found
					$content = substr($view_context, $idx_end1 + 1, $idx_end3 - $idx_end1 - 1);
					$ret = html_entity_decode($content);
					log_error('PVE render()', 'tag name', $tag_name, 'found with content', $ret);
					return $ret;
				} else {
					log_error('PVE render()', 'tag name', $tag_name, 'found with empty tag and empty content');
					return '';
				}
			}
		}
		log_error('PVE render()', 'didn\'t find tag', $tag_name, 'returning empty string');
		return '';
	}

	function parse($file) {
		log_error('PVE parse()', 'file', $file);
		ob_start();
		include $file;
		$ret = utf8_decode(ob_get_clean());
		# log_error('PVE parse()', 'return', $ret);
		return $ret;
	}

	function view_contexts($new_context = null) {
		static $context = [];
		if ($new_context !== null) {
			# log_error('PVE view_contexts() adding new view context', $new_context);
			array_unshift($context, $new_context);
		}
		return $context;
	}

	function view($view_file, $view_dir = null, $called_from_controller = true) {
		ob_start();
		if ($view_dir === null)
			$view_dir = $called_from_controller ? realpath(AppSettings['ViewRelativePath']) : dirname($view_file);
		$dir = getcwd();
		try {
			if ($called_from_controller) {
				log_error('PVE view() called from controller method');
				log_error('PVE view() current directory', getcwd(), 'changing to directory', AppSettings['ViewRelativePath']);
				chdir(AppSettings['ViewRelativePath']);
				chdir(dirname($view_file));
				$view_file = preg_replace('/\/+$/', '', $view_file);
			} else {
				log_error('PVE view() not called from controller method');
				log_error('PVE view() current directory', getcwd(), 'changing to directory', $view_dir);
				chdir($view_dir);
				log_error('PVE view() current directory', getcwd(), 'changing to directory', dirname($view_file));
				chdir(dirname($view_file));
				$view_file = basename($view_file);
			}

			if (!file_exists($view_file))
				$view_file .= '.pchtml';
			if (!file_exists($view_file))
				fail('Nonexist');
			
			view_contexts(parse($view_file));
			while (($template = render('template', null, false)) && $template != 'null' && realpath($template) != realpath(AppSettings['DefaultPVETemplate'])) {
				log_error('PVE view() now parsing template', $template);
				log_error('PVE view() current directory', getcwd(), 'changing to directory', preg_replace('/\.([a-zA-Z0-9])/', '$1', dirname($template)));
				chdir(dirname($template));
				view_contexts(parse(basename($template)));
				$view_file = $template;
			}
			if ($template === 'null') {
				log_error('PVE view() got null template');
				log_error('PVE view() current directory', getcwd(), 'changing to directory', dirname($view_file));
				chdir(dirname($view_file));
				$file = tempnam(sys_get_temp_dir(), '');
				$view_contexts = view_contexts();
				file_put_contents($file, $view_contexts[0]);
				$data = parse($file);
				unlink($file);
				echo render('content', $data);
				$output = ob_get_clean();
				log_error('PVE null final template output', $output);
				echo $output;
				die;
			} else {
				$template = AppSettings['DefaultPVETemplate'];
				# log_error('PVE view() including default template from view common ancestor directory', $template);
				log_error('PVE view() current directory', getcwd(), 'changing to directory', $view_dir);
				chdir($view_dir);
				include $template;
				$output = ob_get_clean();
				log_error('PVE default final template output', $output);
				echo $output;
				die;
			}
		}
		finally {
			chdir($dir);
		}
	}
?>
