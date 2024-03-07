---
layout: cover
background: '/loops.jpg'
---
# event loops in PHP

Een kennismaking met

<!--
en coroutines 😉
we bouwen steeds verder op een concept, dus snap het niet? Vraag het! 
Heb inhoudelijke vragen wacht dan even tot het einde zodat we flow behouden
-->

---

# Fibonacci

```php
function fibonacci($n) {
    if ($n <= 2) {
        return 1;
    }

    return fibonacci($n - 1) + fibonacci($n - 2);
}
```

<!--
We beginnen met een langzame implementatie van de Fibonacci-reeks om lange rekentijden te simuleren.
-->
---

# Server

```php{2-5|8|10} 
function fib_server() {
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            $client = socket_accept($sock);

            fib_handler($client);
        }
    } finally {
        socket_close($sock);
    }
}
```
<!--

-->

---

```php{4|10-11|13}
function fib_handler($client)
{
    while (true) {
        $data = socket_read($client, 1024);

        if ($data === '') {
            break;
        }

        $n = (int)($data);
        $result = fibonacci($n);

        socket_write($client, $result . "\n");
    }

    echo "Connection closed\n";
}
```

<!--
Zie dit als een soort controller / echo server
-->

---

<video controls>
  <source src="/demo_exponential.mp4" type="video/mp4">
</video>

<!--
Wat je ziet is dat de tijd exponentieel toeneemt naarmate de input groter wordt
-->

--- 

# Benchmark

```php
$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($sock, '127.0.0.1', 12021);

while (true) {
    $start = microtime(true);
    socket_write($sock, "30");
    $result = socket_read($sock, 1024);
    $end = microtime(true);
    echo ($end - $start) . "\n";
}
```

<!-- 
Om een idee te krijgen van de rekentijd van de Fibonacci-reeks, schrijven we een benchmark script dat de tijd meet die nodig is om de Fibonacci-reeks te berekenen.

Maakt constant verbinding met de server en meet de tijd die nodig is om de reeks te berekenen
-->

---

<video controls>
  <source src="/demo_single_client.mp4" type="video/mp4">
</video>

<!--
Als we het benchmark script uitvoeren, zien we dat de rekentijd gelijk blijft
-->

--- 

<video controls>
  <source src="/demo_blocking.mp4" type="video/mp4">
</video>

<!-- 
Starten we echter een tweede client, dan zien we dat de die blijft wachten tot de eerste client klaar is.
-->

---

```php{8,10}
function fib_server() {
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            $client = socket_accept($sock);

            fib_handler($client);
        }
    } finally {
        socket_close($sock);
    }
}
```

<!--
Als we kijken naar de server zien we dat socket_accept blocking is en dat de server dus maar één client tegelijk kan bedienen.
-->

---

```php{3|10}
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
```

<!--
In de handler zien we dat de lees blocking is, de schrijf operation _kan_ ook blocking zijn als de buffers vol zijn
Wat nou als we terwijl we wachten tot er iets te lezen valt, we ondertussen een andere client kunnen accepteren? Dus we geven de controle terug aan de aanroepende code.
-->

---
layout: cover
background: /dominos.jpg
---


# Generators

<v-click>Speciaal voor Dick</v-click>

<!--
Wellicht bekend, generators zijn een manier om een iterator te maken zonder dat je een klasse hoeft te schrijven.
Generators kunnen oneindig zijn en geven per iteratie een waarde _en_ de controle terug aan de aanroepende code.
-->

---

```php{1-13|4}
function countdown($n)
{
    while ($n > 0) {
        yield $n;
        $n--;
    }
}

$generator = countdown(3);

foreach ($generator as $value) {
    echo $value . "\n";
}
```

<!--


Een generator in PHP is een speciaal type functie dat iteratie mogelijk maakt zonder de volledige dataset in het geheugen te laden. Door het gebruik van het yield-woord kan een generator waarden één voor één produceren, wat efficiëntie bevordert bij het verwerken van grote datasets of oneindige reeksen.

Zie: lazy collections in Laravel 
-->

---

```php{1|3|4|5|6|7|8}
$generator = countdown(3);

echo $generator->current() . "\n";
$generator->next();
echo $generator->current() . "\n";
$generator->next();
echo $generator->current() . "\n";
$generator->next();
```

<!--
Maar we kunnen ook de generator "handmatig" itereren
-->

---

```php{1-11|4|10-11}
function receiver()
{
    while (true) {
        $value = yield;
        echo "Received: $value\n";
    }
}

$recv = receiver();
$recv->send('Hello');
$recv->send('Hello');
```

<!-- 
Sterker nog, we kunnen ook waarden naar de generator sturen
-->

---

```php{15|8}
function receiver()
{
    while (true) {
        try {
            $value = yield;
            echo "Received: $value\n";
        } catch (Throwable $e) {
            echo "Caught: " . $e->getMessage() . "\n";
        }
    }
}

$recv = receiver();
$recv->send('Hello');
$recv->throw(new Exception('¯\_(ツ)_/¯'));
```

<!--
Of zelfs de generator laten crashen
-->

---
layout: cover
background: /queue.jpg
---

# Scheduling

---

```php{1-5|7|8|9|11|13|15-16|18}
$tasks = [
    countdown(10),
    countdown(5),
    countdown(20),
];

while ($tasks) {
    $task = array_shift($tasks);
    $result = $task->current();

    echo $result, PHP_EOL;

    $task->next();

    if ($task->valid()) {
        $tasks[] = $task;
    } else {
        echo "Task complete", PHP_EOL;
    }
}
```

<!--
We definiëren een task array met daarin een aantal generators, iedere iteratie halen we een task uit de array en voeren we de volgende stap uit.
Zolang de task "valid" is, of te wel, nog niet klaar, voegen we de task weer toe aan de array.

Dit concept noemen we round-robin scheduling.
-->

---
layout: cover
background: /control.jpg
---

# I/O multiplexing

---

```php{5|4|15|14}
function fib_handler($client)
{
    while (true) {
        yield ['read', $client];
        $data = socket_read($client, 1024);  // blocking

        if ($data === '') {
            break;
        }

        $n = (int)($data);
        $result = fibonacci($n);

        yield ['write', $client];
        socket_write($client, $result . "\n");  // potentially blocking if buffers are full
    }

    echo "Connection closed\n";
}
```

<!--
Als we kijken naar de client handler hebben we eerder al gezien dat de lees en schrijf operaties blocking zijn.
We zouden dus de controle terug willen geven aan de aanroepende code zodat we ondertussen een andere client kunnen accepteren.

Dit lijkt heel erg op het countdown concept, maar in dit geval willen we weten _wie_ er wacht en _wat_ deze wil doen.  
-->

---

```php{10|9|12}
function fib_server(&$tasks)
{
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            yield ['read', $sock];
            $client = socket_accept($sock); // blocking

            $tasks[] = fib_handler($client);
        }
    } finally {
        socket_close($sock);
    }
}
```

<!--
Hetzelfde geldt voor de server. 
Belangrijk om te onthouden is dat we de `fib_handler` ook in de task array stoppen, deze gaat immers zijn eigen leven leiden 
-->

---

```php{3,4|7|9|12,13|14,15|17|20}
$tasks = [];

$readWait = [];
$writeWait = [];

while ($tasks) {
    $task = array_shift($tasks);

    [$why, $who] = $task->current();
    $id = spl_object_id($who);

    if ($why === 'read') {
        $readWait[$id] = $task;
    } elseif ($why === 'write') {
        $writeWait[$id] = $task;
    } else {
        throw new \Exception("¯\_(ツ)_/¯");
    }

    $task->next();

    if ($task->valid()) {
        $tasks[] = $task;
    } else {
        echo "Task complete", PHP_EOL;
    }
}
```

<!--
Als we nu ons round-robin scheduling script aanpassen zodat we kunnen wachten op lees en schrijf operaties, dan kunnen we ondertussen andere clients accepteren.
-->

--- 

```php{4|5|7,8|11|13|14|16|17|20-25}
$readWait = [];
$writeWait = [];

while ($tasks || $readWait || $writeWait) {
    while (!$tasks) {
        if ($readWait || $writeWait) {
            $readable = array_map(static fn($task) => $task[0], $readWait);
            $writable = array_map(static fn($task) => $task[0], $writeWait);
            $except = [];

            socket_select($readable, $writable, $except, null);

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
    
    // Task running...
}
```

<!--
We passen de scheduler aan zodat we blijven loopen zolang er taken of wachtende sockets zijn.

4 argument is de timeout, door null te geven wachten pollen we het OS
-->

---

<video controls>
  <source src="/demo_rr.mp4" type="video/mp4">
</video>

<!-- 
Voeren we de benchmark nog keer uit met 2 clients, dan zien we dat de rekentijd ongeveer 2x zo lang is

Dit komt doordat we het wachten op sockets non-blocking hebben gemaakt, maar de CPU moet rekenen en dat is nog steeds blocking
-->

---

```php{4|14}
function fib_handler($client)
{
    while (true) {
        yield ['read', $client];
        $data = socket_read($client, 1024);  // blocking

        if ($data === '') {
            break;
        }

        $n = (int)($data);
        $result = fibonacci($n);

        yield ['write', $client];
        socket_write($client, $result . "\n");  // potentially blocking if buffers are full
    }

    echo "Connection closed\n";
}
```

<!--
Iedere keer yield voor een socket operation typen is niet erg DRY, laten we dat eens abstraheren
-->

---

```php{10-15|17-22}
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
```

<!--
Krijgt het betreffende socket als argument in de constructor en heeft methodes voor lezen, schrijven.
Het is dus eigenlijk een wrapper om de socket functies heen, met extra yield statements 
-->

---

```php{9|10}
function fib_server(&$tasks)
{
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            $asock = new AsyncSocket($sock);
            $client = yield from $asock->accept();

            $tasks[] = fib_handler($client);
        }
    } finally {
        socket_close($sock);
    }
}
```

<!--
Vervolgens kunnen we de server en handler herschrijven en gebruik van yield from maken

Dit is een _soort van_ await, dit "kleurt" als het ware je code. Als er ergens in de call stack IO wilt uitvoeren, zal de hele stack async moeten zijn. 

Als je door oude async code heen gaat, zul je vaak yield from tegenkomen, dit is een manier om een generator te "nesten" in een andere generator
Het lijkt erg op het await keyword in andere talen
-->

---
layout: cover
background: /fibers.jpg
---

# Fibers 

De feature die wordt afgeraden

---

```php
$fibers = new SplStack();
// Must start the server as a task to be able to pause and resume it
$fiber = new Fiber('fib_server');
$fiber->start($fibers);

$fibers->push($fiber);
run($fibers);
```

<!--
Om fibers te gebruiken moeten we de task array aanpassen naar een stack zodat we deze als reference kunnen doorgeven 
-->

---

```php{4|14}
function fib_handler($client)
{
    while (true) {
        Fiber::suspend(['read', $client]);
        $data = socket_read($client, 1024);  // blocking

        if ($data === '') {
            break;
        }

        $n = (int)($data);
        $result = fibonacci($n);

        Fiber::suspend(['write', $client]);
        socket_write($client, $result . "\n");  // potentially blocking if buffers are full
    }

    echo "Connection closed\n";
}
```

---

```php{14,15|17}
function fib_server(SplStack $fibers)
{
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 12021);
    socket_listen($sock, 5);

    try {
        while (true) {
            Fiber::suspend(['read', $sock]);

            $client = socket_accept($sock); // blocking

            // Start a new fiber (task) to handle the client
            $fiber = new Fiber('fib_handler');
            $fiber->start($client);

            $fibers->push($fiber);
        }
    } finally {
        socket_close($sock);
    }
}
```

---

```php{*|10|14}
while ($fibers->count() || $readWait || $writeWait) {
    echo "doing work...\n";

    // no active tasks, wait for I/O
    while (!$fibers->count()) {
        // select sockets...
    }

    $fiber = $fibers->shift();
    if ($fiber->isTerminated()) {
        continue;
    }

    [$why, $who] = $fiber->resume();
    if (null === $why) {
        continue;
    }

    // put the fiber in the right queue...
}
```

<!--
De syntax is een beetje anders, maar het concept is hetzelfde

Dit doet fibers echter wel een beetje tekort. In tegenstelling tot generators hebben fibers een callstack en kunnen ze dus op ieder moment suspended en resumed worden. Dit vermijd het "kleuren" van de code en lijkt op Goroutines in Go
-->

---

# Conclusie

<ul>
<v-click><li>Gebruik ReactPHP</li></v-click>
<v-click><li>Gebruik LibUV</li></v-click>
<v-click><li>Doe dit niet</li> </v-click>
<v-click><li>Voorzichtig met IO & CPU</li> </v-click>
<v-click><li>Wees nieuwsgierig</li> </v-click>
</ul>

<!--
Haal de facade weg en kijk wat er onder zit
-->

---
layout: image 
image: /europapa.jpg
---
