<?php

function fibonacci(int $n): array
{
    [$a, $b] = [0, 1];
    $result = [];

    for ($i = 0; $i < $n; $i++) {
        $result[] = $a;
        $c = $a + $b;
        [$a, $b] = [$b, $c];
    }

    return $result;
}

foreach (fibonacci(100) as $number) {
    echo $number, PHP_EOL;
}
