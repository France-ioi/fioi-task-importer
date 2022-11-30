<?php

// General functions

function startsWith($str, $sw) {
    // Returns whether $str starts with $sw
    return substr($str, 0, strlen($sw)) === $sw;
}

function pathJoin() {
    // Join path elements without repeating slashes
    if (func_num_args() < 1) {
        throw new BadFunctionCallException("Not enough parameters!");
    }
    $path = null;
    foreach(func_get_args() as $arg) {
        if($path === null) {
            $path = $arg;
            continue;
        }
        if(substr($path, -1) != '/') {
            $path .= '/';
        }
        if(substr($arg, 0, 1) == '/') {
            $path .= substr($arg, 1);
        } else {
            $path .= $arg;
        }
    }
    return $path;
}


function dirCopy($src, $dst) {
	$dir = opendir($src);
	@mkdir($dst, 0777, true);
	while(($file = readdir($dir))) {
		if(($file != '.') && ($file != '..')) {
			if(is_dir(pathJoin($src, $file))) {
				dirCopy(pathJoin($src, $file), pathJoin($dst, $file));
			} else {
				copy(pathJoin($src, $file), pathJoin($dst, $file));
			}
		}
	}
	closedir($dir);
}

function getAbsolutePath($path) {
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    $absolutes = array();
    foreach ($parts as $part) {
        if ('.' == $part) continue;
        if ('..' == $part) {
            array_pop($absolutes);
        } else {
            $absolutes[] = $part;
        }
    }
    return implode(DIRECTORY_SEPARATOR, $absolutes);
}