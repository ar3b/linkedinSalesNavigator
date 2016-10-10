<?php

// log
function l($txt=" ") {
    print htmlspecialchars($txt).PHP_EOL;
}

function r($key, $value) {
    l("\t".$key.":\n \t\t".$value);
}

// log separator
function sep() {
    l(str_repeat("-",80));
}