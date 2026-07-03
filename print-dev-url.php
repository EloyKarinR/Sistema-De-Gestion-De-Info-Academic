<?php

/**
 * gethostbyname(gethostname()) no sirve aquí: si hay una VPN activa (ej.
 * ProtonVPN), suele devolver la IP del túnel en vez de la del Wi-Fi real, y el
 * celular no podría conectarse a esa. Por eso se listan todas las IPv4 locales
 * y se prioriza el rango típico de routers domésticos (192.168.x.x).
 */
function localLanIp(): ?string
{
    $output = shell_exec('ipconfig');

    if (! $output) {
        return null;
    }

    preg_match_all('/IPv4[^:]*:\s*([\d.]+)/i', $output, $matches);
    $ips = $matches[1] ?? [];

    foreach ($ips as $ip) {
        if (str_starts_with($ip, '192.168.')) {
            return $ip;
        }
    }

    foreach ($ips as $ip) {
        if ($ip !== '127.0.0.1') {
            return $ip;
        }
    }

    return null;
}

$ip = localLanIp();

echo PHP_EOL;
if ($ip) {
    echo "  👉 Abre tu sistema en: http://{$ip}:8000  (o http://localhost:8000 en esta laptop)".PHP_EOL;
} else {
    echo '  👉 Abre tu sistema en: http://localhost:8000'.PHP_EOL;
}
echo PHP_EOL;
