<?php

// internal functions

function get_clo() {
	$o = '';

	$o .= MAKEZIP . '::';
	$o .= WORKDIR . '::';
	$o .= GETHELP;
	$o .= MAKEFCL;
	$o .= EXTRFCL;
	$o .= LISTFCL;

	$options = getopt($o);
	$clo = [];

	if (isset($options[MAKEZIP])) {
		$clo[MAKEZIP] = ($options[MAKEZIP] !== false)
			? ($options[MAKEZIP] == (int)$options[MAKEZIP] ? (int)$options[MAKEZIP] : false)
			: false;

		if (isset($options[WORKDIR])) {
			$options[WORKDIR] ??= '';
		}

		// if ($options[WORKDIR] && !in_array($options[WORKDIR], ['2x', '3x', '4x', 'old'])) {
		//   output('Allowed sub-directory values are: "2x", "3x", "4x", "old"!', true);
		// }

		$clo[WORKDIR] = $options[WORKDIR] ?? '';
	} elseif (isset($options[GETHELP])) {
		$clo[GETHELP] = 1;
	} elseif (isset($options[MAKEFCL])) {
		$clo[MAKEFCL] = 1;
	} elseif (isset($options[EXTRFCL])) {
		$clo[EXTRFCL] = 1;
	} elseif (isset($options[LISTFCL])) {
		$clo[LISTFCL] = 1;
	}

	return $clo;
}

// returns path with a directory separator on the right or without
function getConcatPath() {
	$path = '';

	foreach (func_get_args() as $dir) {
		if ($dir) $path .= trim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}

	return trim($path, DIRECTORY_SEPARATOR);
}

function getWd($num) {
	if ($num === 0 && is_dir(MDIR)) return MDIR;

	$addons = getDirList(ADIR);

	if ($addons && isset($addons[$num - 1])) return getConcatPath(ADIR, $addons[$num - 1]);

	return false;
}

function getDirList(string $path) {
	$list = [];

	if (is_dir($path)) {
		foreach (scandir($path) as $dir) {
			if ($dir != '.' && $dir != '..' && is_dir(getConcatPath($path, $dir))) {
				// $list[] = getConcatPath($path, $dir);
				$list[] = $dir;
			}
		}
	}

	return $list;
}

function getFileList($path) {
	$files = [];

	if (is_dir($path)) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isDir()) {
				// continue;
			}

			$files[] = $file->getPathname();
		}
	}

	return $files;
}

function chkdir(string $dir) {
	if (is_dir($dir)) return true;

	return mkdir($dir);
}

function mkzip($srcdir, $zipfile, $force = false): void {
	if (is_file($zipfile) && !$force) {
		output($zipfile . ' already exists! Use force flag', true);
	}

	$zip = new ZipArchive();

	if ($zip->open($zipfile, ZipArchive::CREATE) === true) {
		foreach (getFileList($srcdir) as $file) {
			$relative = ltrim(substr($file, strlen($srcdir)), DIRECTORY_SEPARATOR);

			if (is_file($file)) {
				$content = replacer($file);

				$zip->addFromString($relative, $content);
			} elseif (is_dir($file)) {
				$zip->addEmptyDir($relative);
			}
		}

		if ($zip->close() === true) {
			output('ZIP-file was created successfully: ' . $zipfile . PHP_EOL);

			// PHP >= 8.0.0, PECL zip >= 1.16.0
			if (version_compare(PHP_VERSION, '8.0.0') >= 0) {
				$zip->open($zipfile);

				for ($i = 0; $i < $zip->numFiles; ++$i) {
					$stat = $zip->statIndex($i);

					$zip->setMtimeIndex($i, strtotime('2024-01-01 00:00:01'));
				}

				$zip->close();
			}
		} else {
			output('Failure to creating file: "' . $zipfile . PHP_EOL . $e, true);
		}
	} else {
		output('can not create "' . $zipfile . '"!', true);
	}
}

function replacer($file, $to_replace = []) {
	if (!$to_replace) {
		$to_replace = get_defined_constants(true)['user'];
	}

	$content = '';

	if ($pointer = fopen($file, 'r')) {
		while (!feof($pointer)) {
			$line = fgets($pointer);

			if (!$line) break;

			if ($line && strpos($line, '<insertfile>') !== false) {
				$ifile = getSubstrBetween($line, '<insertfile>', '</insertfile>');

				if (!is_file($ifile) || empty($ifile)) {
					output('in "' . $file . '". Check placeholder file "' . $ifile . '"', true);
				}

				$search = '<insertfile>' . $ifile . '</insertfile>';
				$replace = trim(file_get_contents($ifile));
				$line = str_replace($search, $replace, $line);
			}

			if ($line && strpos($line, '<insertfile base64>') !== false) {
				$ifile = getSubstrBetween($line, '<insertfile base64>', '</insertfile>');

				if (!is_file($ifile) || empty($ifile)) {
					output('in "' . $file . '". Check placeholder file "' . $ifile . '"', true);
				}

				$search = '<insertfile base64>' . $ifile . '</insertfile>';
				$replace = base64_encode(trim(file_get_contents($ifile)));
				$line = str_replace($search, $replace, $line);
			}

			while (strpos($line, '<insertvar>') !== false) {
				$ivar = getSubstrBetween($line, '<insertvar>', '</insertvar>');
				$ivar = preg_replace('/[^a-z0-9]+$/i', '', $ivar);

				if (empty($ivar) || !array_key_exists($ivar, $to_replace)) {
					output('in "' . $file . '". Check placeholder var "' . $ivar . '"', true);
				}

				$search = '<insertvar>' . $ivar . '</insertvar>';
				$replace = $to_replace[$ivar];
				$line = str_replace($search, $replace, $line);
			}

			$content .= $line;
		}

		fclose($pointer);
	}

	return $content;
}

function fclignore($file) {
	$fclignore = '';

	if (is_file($file)) {
		if ($pointer = fopen($file, 'r')) {
			while (!feof($pointer)) {
				$line = fgets($pointer);

				if (!$line) break;

				$line = trim($line);
				$line = rtrim($line, DIRECTORY_SEPARATOR);

				if ($line && strpos($line, '#') !== 0) {
					if (strpos($line, '!') === 0) {
						$fclignore .= ' -D' . $line;
					} else {
						$fclignore .= ' -E' . $line;
					}
				}
			}

			fclose($pointer);
		}
	}

	return $fclignore;
}

function fcl(string $cmd, string $file, string $opts = '') {
	return shell_exec('fcl ' . $cmd . ($opts ? ' ' . $opts : '') . ' ' . $file);
}

function hideg($file) {
	if (!is_file('hideg.pwd')) {
		// $f = fopen("hideg.pwd", "w") or die("Unable to open file!");
		// $n = readline('Enter name: ');
		// $p = readline('Enter password: ');
		// fwrite($f, $n . PHP_EOL);
		// fwrite($f, $p . PHP_EOL);
		// fclose($f);

		output('File hideg.pwd is missing!');
		output('Enter your name and press ENTER, then do the same for a password...');
		shell_exec('hideg');
	}

	return shell_exec('hideg ' . $file);
}

function numbered() {
	$list = [];

	if (is_dir(MDIR) && is_dir(getConcatPath(MDIR, SRCDIR))) {
		$list[] = strtolower(basename(getcwd()));
	} else {
		$list[] = false;

		unset($list[0]);
	}

	if (is_dir(ADIR)) {
		$addons = getDirList(ADIR);

		foreach ($addons as $name) {
			if (is_dir(getConcatPath(getConcatPath(ADIR, $name), SRCDIR))) {
				$list[] = getConcatPath(ADIR, $name);
			}
		}
	}

	return $list;
}

function output(string $text = '', bool $error = false): void {
	$text = ($error) ? 'ERROR: ' . $text : $text;

	echo $text . PHP_EOL;

	($error) ? exit(1) : null;
}

function getSubstrBetween($string, $start, $end) {
	$ini = strpos($string, $start);

	if ($ini === false) {
		return '';
	}

	$ini += strlen($start);
	$len = strpos($string, $end, $ini) - $ini;

	return substr($string, $ini, $len);
}

function deleteContent($path) {
	try {
		$iterator = new DirectoryIterator($path);
		foreach ($iterator as $fileinfo) {
			if ($fileinfo->isDot()) {
				continue;
			}
			if ($fileinfo->isDir()) {
				if (deleteContent($fileinfo->getPathname())) {
					@rmdir($fileinfo->getPathname());
				}
			}
			if ($fileinfo->isFile()) {
				@unlink($fileinfo->getPathname());
			}
		}
	} catch (Exception $e) {
		// write log
		return false;
	}

	return true;
}
