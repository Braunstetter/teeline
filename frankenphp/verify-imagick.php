<?php

declare(strict_types=1);

$formats = (new Imagick())->queryFormats();
$required = ['JPEG', 'PNG', 'WEBP'];
$missing = array_diff($required, $formats);

if ($missing !== []) {
    fwrite(STDERR, 'Imagick coder modules missing in the prod image: ' . implode(', ', $missing) . "\n");
    fwrite(STDERR, "The module directory did not survive the copy into frankenphp_prod.\n");
    exit(1);
}

echo 'OK: ' . count($formats) . " Imagick formats available in the prod image.\n";
