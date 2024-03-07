<?php

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($sock, '127.0.0.1', 13031);


$n = 0;

$lastCheck = time();
while (true) {
    socket_write($sock, "1");
    $result = socket_read($sock, 1024);
    $n++;

    if (time() - $lastCheck > 1) {
        echo $n . ' req/sec' . PHP_EOL;

        $n = 0;
        $lastCheck = time();
    }
}
