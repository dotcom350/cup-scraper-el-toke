<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/*
|--------------------------------------------------------------------------
| Funciones
|--------------------------------------------------------------------------
*/

function sendJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function loadEnv(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $variables = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if (
            $line === '' ||
            str_starts_with($line, '#') ||
            str_starts_with($line, ';')
        ) {
            continue;
        }

        $position = strpos($line, '=');

        if ($position === false) {
            continue;
        }

        $key = trim(substr($line, 0, $position));
        $value = trim(substr($line, $position + 1));

        $value = trim($value, "\"'");

        if ($key !== '') {
            $variables[$key] = $value;
        }
    }

    return $variables;
}

function normalizeText(string $text): string
{
    $text = html_entity_decode(
        $text,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $text = str_replace("\u{00A0}", ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim($text ?? '');
}

function downloadPage(string $url, int $timeout): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión cURL de PHP no está habilitada.');
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
        ],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',
    ]);

    $html = curl_exec($ch);

    if ($html === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException('Error cURL: ' . $error);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode < 200 || $statusCode >= 400) {
        throw new RuntimeException(
            'La página de origen respondió con HTTP ' . $statusCode
        );
    }

    if (trim($html) === '') {
        throw new RuntimeException('La página descargada está vacía.');
    }

    return $html;
}

function extractMarketSection(string $html): string
{
    /*
    |--------------------------------------------------------------------------
    | Buscar el inicio exacto de la sección estable
    |--------------------------------------------------------------------------
    */

    if (!preg_match(
        '/<div\b[^>]*\bid=["\']mercado-informal["\'][^>]*>/i',
        $html,
        $match,
        PREG_OFFSET_CAPTURE
    )) {
        throw new RuntimeException(
            'No se encontró la sección con id="mercado-informal".'
        );
    }

    $sectionStart = $match[0][1];

    /*
    |--------------------------------------------------------------------------
    | Buscar la tabla dentro de la sección
    |--------------------------------------------------------------------------
    */

    $tableStart = stripos($html, '<table', $sectionStart);

    if ($tableStart === false) {
        throw new RuntimeException(
            'No se encontró ninguna tabla dentro de mercado-informal.'
        );
    }

    $sectionLimit = stripos($html, 'Ver más sobre el mercado informal', $sectionStart);

    if ($sectionLimit !== false && $tableStart > $sectionLimit) {
        throw new RuntimeException(
            'La tabla encontrada no pertenece a mercado-informal.'
        );
    }

    $tableEnd = stripos($html, '</table>', $tableStart);

    if ($tableEnd === false) {
        throw new RuntimeException(
            'No se encontró el cierre de la tabla de tasas.'
        );
    }

    $tableEnd += strlen('</table>');

    return substr(
        $html,
        $tableStart,
        $tableEnd - $tableStart
    );
}

function extractSourceDate(string $html): ?string
{
    if (!preg_match(
        '/<div\b[^>]*\bid=["\']mercado-informal["\'][^>]*>/i',
        $html,
        $match,
        PREG_OFFSET_CAPTURE
    )) {
        return null;
    }

    $sectionStart = $match[0][1];

    $timeStart = stripos($html, '<time', $sectionStart);

    if ($timeStart === false) {
        return null;
    }

    $timeEnd = stripos($html, '</time>', $timeStart);

    if ($timeEnd === false) {
        return null;
    }

    $timeFragment = substr(
        $html,
        $timeStart,
        ($timeEnd - $timeStart) + strlen('</time>')
    );

    return normalizeText(strip_tags($timeFragment));
}

function parseRates(string $tableHtml): array
{
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException(
            'La extensión DOM/XML de PHP no está habilitada.'
        );
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8">' . $tableHtml,
        LIBXML_NOERROR |
        LIBXML_NOWARNING |
        LIBXML_NONET
    );

    libxml_clear_errors();

    if (!$loaded) {
        throw new RuntimeException(
            'No se pudo interpretar la tabla HTML.'
        );
    }

    $xpath = new DOMXPath($dom);

    /*
    |--------------------------------------------------------------------------
    | Buscar las filas que tengan ids cell-title-v2-
    |--------------------------------------------------------------------------
    */

    $rows = $xpath->query(
        '//tr[.//span[starts-with(@id, "cell-title-v2-")]]'
    );

    if ($rows === false || $rows->length === 0) {
        throw new RuntimeException(
            'No se encontraron tasas dentro de la tabla.'
        );
    }

    $rates = [];

    foreach ($rows as $row) {
        $currencyNode = $xpath
            ->query(
                './/span[starts-with(@id, "cell-title-v2-")]',
                $row
            )
            ?->item(0);

        if (!$currencyNode) {
            continue;
        }

        $currencyText = normalizeText($currencyNode->textContent);

        if (!preg_match(
            '/^\s*([0-9]+(?:[.,][0-9]+)?)\s+([A-Z0-9]+)\s*$/iu',
            $currencyText,
            $currencyMatch
        )) {
            continue;
        }

        $unit = (float) str_replace(',', '.', $currencyMatch[1]);
        $currency = strtoupper($currencyMatch[2]);

        /*
        |--------------------------------------------------------------------------
        | Buscar el valor que termina en CUP
        |--------------------------------------------------------------------------
        */

        $price = null;
        $priceText = null;

        $spans = $xpath->query('.//span', $row);

        if ($spans !== false) {
            foreach ($spans as $span) {
                $text = normalizeText($span->textContent);

                if (
                    preg_match(
                        '/^([0-9][0-9.,]*)\s+CUP$/i',
                        $text,
                        $priceMatch
                    )
                ) {
                    $rawPrice = str_replace(',', '', $priceMatch[1]);

                    if (is_numeric($rawPrice)) {
                        $price = (float) $rawPrice;
                        $priceText = $text;
                        break;
                    }
                }
            }
        }

        if ($price === null) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Buscar variación positiva o negativa
        |--------------------------------------------------------------------------
        */

        $change = null;

        if ($spans !== false) {
            foreach ($spans as $span) {
                $text = normalizeText($span->textContent);

                if (preg_match('/^[+-]\d+(?:\.\d+)?$/', $text)) {
                    $change = (float) $text;
                    break;
                }
            }
        }

        $direction = 'unchanged';

        if ($change !== null) {
            if ($change > 0) {
                $direction = 'up';
            } elseif ($change < 0) {
                $direction = 'down';
            }
        }

        $rates[] = [
            'unit' => $unit,
            'currency' => $currency,
            'price' => $price,
            'currency_to' => 'CUP',
            'change' => $change,
            'direction' => $direction,
            'display' => $priceText,
        ];
    }

    if (count($rates) === 0) {
        throw new RuntimeException(
            'La tabla fue encontrada, pero no se pudieron interpretar las tasas.'
        );
    }

    return $rates;
}

function readValidCache(
    string $cacheFile,
    int $cacheLifetime
): ?array {
    if (!file_exists($cacheFile)) {
        return null;
    }

    $modifiedTime = filemtime($cacheFile);

    if ($modifiedTime === false) {
        return null;
    }

    $age = time() - $modifiedTime;

    if ($age >= $cacheLifetime) {
        return null;
    }

    $contents = file_get_contents($cacheFile);

    if ($contents === false || trim($contents) === '') {
        return null;
    }

    $decoded = json_decode($contents, true);

    if (
        !is_array($decoded) ||
        empty($decoded['success']) ||
        empty($decoded['rates'])
    ) {
        return null;
    }

    $decoded['cached'] = true;
    $decoded['cache_age_seconds'] = $age;
    $decoded['cache_expires_in_seconds'] = max(
        0,
        $cacheLifetime - $age
    );

    return $decoded;
}

function saveCache(string $cacheFile, array $data): void
{
    $directory = dirname($cacheFile);

    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(
                'No se pudo crear el directorio del caché.'
            );
        }
    }

    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        throw new RuntimeException(
            'No se pudo generar el JSON para el caché.'
        );
    }

    $temporaryFile = $cacheFile . '.tmp';

    if (
        file_put_contents(
            $temporaryFile,
            $json,
            LOCK_EX
        ) === false
    ) {
        throw new RuntimeException(
            'No se pudo escribir el archivo temporal del caché.'
        );
    }

    if (!rename($temporaryFile, $cacheFile)) {
        @unlink($temporaryFile);

        throw new RuntimeException(
            'No se pudo reemplazar el archivo de caché.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Configuración
|--------------------------------------------------------------------------
*/

$env = loadEnv(__DIR__ . '/.env');

$sourceUrl = $env['SOURCE_URL']
    ?? 'https://eltoque.com/tasas-de-cambio-cuba';

$cacheLifetime = isset($env['CACHE_TIME'])
    ? max(1, (int) $env['CACHE_TIME'])
    : 3600;

$timeout = isset($env['REQUEST_TIMEOUT'])
    ? max(5, (int) $env['REQUEST_TIMEOUT'])
    : 30;

$timezone = $env['TIMEZONE']
    ?? 'America/Havana';

$cacheFileName = $env['CACHE_FILE']
    ?? 'storage/cache.json';

$cacheFileName = ltrim(
    str_replace(['../', '..\\'], '', $cacheFileName),
    '/\\'
);

$cacheFile = __DIR__ . DIRECTORY_SEPARATOR . $cacheFileName;

date_default_timezone_set($timezone);

/*
|--------------------------------------------------------------------------
| Forzar actualización opcional
|--------------------------------------------------------------------------
|
| Para permitirlo, coloque ALLOW_FORCE_REFRESH=true en .env
| y abra:
|
| index.php?refresh=1
|
*/

$allowForceRefresh = filter_var(
    $env['ALLOW_FORCE_REFRESH'] ?? 'false',
    FILTER_VALIDATE_BOOLEAN
);

$forceRefresh = $allowForceRefresh
    && isset($_GET['refresh'])
    && $_GET['refresh'] === '1';

/*
|--------------------------------------------------------------------------
| Devolver caché válido
|--------------------------------------------------------------------------
*/

if (!$forceRefresh) {
    $cachedData = readValidCache(
        $cacheFile,
        $cacheLifetime
    );

    if ($cachedData !== null) {
        sendJson($cachedData);
    }
}

/*
|--------------------------------------------------------------------------
| Descargar y extraer la información
|--------------------------------------------------------------------------
*/

try {
    $html = downloadPage($sourceUrl, $timeout);

    $tableHtml = extractMarketSection($html);

    $rates = parseRates($tableHtml);

    $sourceUpdatedAt = extractSourceDate($html);

    $result = [
        'success' => true,
        'cached' => false,
        'source' => $sourceUrl,
        'section_id' => 'mercado-informal',
        'source_updated_at' => $sourceUpdatedAt,
        'scraped_at' => date(DATE_ATOM),
        'cache_lifetime_seconds' => $cacheLifetime,
        'cache_expires_at' => date(
            DATE_ATOM,
            time() + $cacheLifetime
        ),
        'count' => count($rates),
        'rates' => $rates,
    ];

    saveCache($cacheFile, $result);

    sendJson($result);
} catch (Throwable $exception) {
    /*
    |--------------------------------------------------------------------------
    | Usar caché expirado como respaldo
    |--------------------------------------------------------------------------
    */

    if (file_exists($cacheFile)) {
        $oldCache = file_get_contents($cacheFile);
        $oldData = $oldCache !== false
            ? json_decode($oldCache, true)
            : null;

        if (
            is_array($oldData) &&
            !empty($oldData['rates'])
        ) {
            $oldData['success'] = true;
            $oldData['cached'] = true;
            $oldData['stale'] = true;
            $oldData['warning'] =
                'No se pudo actualizar la información. Se devuelve el último caché disponible.';
            $oldData['update_error'] =
                $exception->getMessage();

            sendJson($oldData);
        }
    }

    sendJson([
        'success' => false,
        'error' => $exception->getMessage(),
        'source' => $sourceUrl,
        'scraped_at' => date(DATE_ATOM),
    ], 500);
}
