<?php

/**
 * Opens a socket at port 12021 and acts as world's worst microservice to calculate fibonacci numbers
 * Cannot handle more than 1 request at a time
 */
require __DIR__ . '/../generators/fibonacci_deteriorate.php';

class AsyncSocket
{
    public function __construct(
        private Socket $socket
    )
    {

    }

    public function read(int $maxsize = 1024)
    {
        yield ['read', $this->socket];

        return socket_read($this->socket, $maxsize);
    }

    public function write(string $data)
    {
        yield ['write', $this->socket];

        return socket_write($this->socket, $data);
    }

    public function accept()
    {
        yield ['read', $this->socket];

        return new AsyncSocket(socket_accept($this->socket));
    }
}

/**
 * Handles a single client connection while it's alive. Respond with fibonacci number for each request
 *
 * @param $client
 * @return void
 */
function fib_handler(AsyncSocket $client)
{
    while (true) {
        $data = yield from $client->read(1024);

        if ($data === '') {
            break;
        }

        $n = (int)($data);
        $result = fibonacci($n);

        yield from $client->write($result . "\n");
    }

    echo "Connection closed\n";
}

/**
 * Opens a socket and listens for incoming connections. For each connection, start a new handler
 */
function fib_server(&$tasks)
{
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            $asock = new AsyncSocket($sock);
            $client = yield from $asock->accept();

            // Could be moved to a process pool to handle multiple requests at once
            // with the caveat that data needs to be serialized and deserialized
            $tasks[] = fib_handler($client);
        }
    } finally {
        socket_close($sock);
    }
}

/**
 * Round-robin scheduler, uses yield statements to pause and resume tasks and communicate the intent of the stop
 * @return void
 */
function run(&$tasks)
{
    // Waiting area; mapping sockets to tasks to resume when data is available (tasks are generators
    $readWait = [];
    $writeWait = [];

    // Run while we have _anything_ to do
    while ($tasks || $readWait || $writeWait) {
        echo "doing work...\n";

        // no active tasks, wait for I/O
        while (!$tasks) {
            echo "waiting for I/O...\n";

            if ($readWait || $writeWait) {
                $readable = array_map(static fn($task) => $task[0], $readWait);
                $writable = array_map(static fn($task) => $task[0], $writeWait);

                // Stuff like timeouts, connection errors, overflows etc. are not handled
                $exceptional = [];

                // Poll the OS for socket activity
                socket_select($readable, $writable, $exceptional, null);

                // Pull the tasks from the waiting area and add them back to the main queue
                // Note, we must do this for both read and write
                foreach ($readable as $socket) {
                    $id = spl_object_id($socket);

                    $tasks[] = $readWait[$id][1];
                    unset($readWait[$id]);
                }

                foreach ($writable as $socket) {
                    $id = spl_object_id($socket);

                    $tasks[] = $writeWait[$id][1];
                    unset($writeWait[$id]);
                }
            }
        }

        /** @var Generator $task */
        $task = array_shift($tasks);
        $task->next();

        /**
         * @var $why string
         * @var $who Socket
         */
        [$why, $who] = $task->current();
        if ($who === null) {
            // Client went away or something
            continue;
        }

        $id = spl_object_id($who);

        if ($why === 'read') {
            $readWait[$id] = [$who, $task];
        } elseif ($why === 'write') {
            $writeWait[$id] = [$who, $task];
        } else {
            throw new \Exception("¯\_(ツ)_/¯");
        }
    }
}

$tasks = [

];
// Must start the server as a task to be able to pause and resume it
$tasks[] = fib_server($tasks);
run($tasks);
