<?php

/**
 * Opens a socket at port 12021 and acts as world's worst microservice to calculate fibonacci numbers
 * Cannot handle more than 1 request at a time
 */


require __DIR__ . '/../generators/fibonacci_deteriorate.php';

/**
 * Handles a single client connection while it's alive. Respond with fibonacci number for each request
 *
 * @param $client
 * @return void
 */
function fib_handler($client) {
    while (true) {
        $data = socket_read($client, 1024);
        if ($data === '') {
            break;
        }

        $n = (int) ($data);
        $result = fibonacci($n);
        socket_write($client,  $result . "\n");
    }

    echo "Connection closed\n";
}

/**
 * Opens a socket and listens for incoming connections. For each connection, start a new handler
 */
function fib_server() {
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            $client = socket_accept($sock);

            // Could be moved to a process pool to handle multiple requests at once
            // with the caveat that data needs to be serialized and deserialized
            fib_handler($client);
        }
    } finally {
        socket_close($sock);
    }
}


fib_server();