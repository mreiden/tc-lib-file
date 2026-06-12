<?php

declare(strict_types=1);

$target = isset($_GET['to']) && \is_string($_GET['to']) ? $_GET['to'] : '/empty.php';
\header('Location: ' . $target, true, 302);
