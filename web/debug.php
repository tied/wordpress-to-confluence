<?php

$name = "request-".date("U").".log";
file_put_contents($name, print_r($_REQUEST, true) . "\n\n" . print_r($_FILES, true));
print(json_encode(["name" => $name]));