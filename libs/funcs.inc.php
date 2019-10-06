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
