<?php
/**
 * Router - Centrální routovací systém WGS Service
 *
 * Funguje VEDLE existujících PHP stránek — nerozbíjí je.
 * Přidává parametrizované trasy, middleware pipeline a sdílený error handling.
 *
 * Použití v routes.php:
 *   Router::get('/r/{cislo}',        [OvladaceReklamaci::class, 'presmeruj']);
 *   Router::post('/api/v2/reklamace', [ApiV2Reklamace::class, 'vytvor']);
 *   Router::middleware([OverAuth::class, 'over']);
 *
 * Použití ve front controlleru (router.php):
 *   require_once 'routes.php';
 *   Router::odeslat($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
 */

if (!defined('BASE_PATH')) {
    die('Přímý přístup zakázán.');
}

class Router
{
    // -------------------------------------------------------------------------
    // Interní stav (statický singleton)
    // -------------------------------------------------------------------------

    /** @var array<string, array{vzor: string, regex: string, parametry: list<string>, obsluha: callable, middleware: list<callable>}[]> */
    private static array $trasy = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'DELETE' => [],
        'PATCH'  => [],
    ];

    /** @var list<callable> Globální middleware (platí pro všechny trasy) */
    private static array $globalniMiddleware = [];

    /** @var callable|null Handler pro 404 */
    private static mixed $handler404 = null;

    /** @var callable|null Handler pro 403 */
    private static mixed $handler403 = null;

    /** @var callable|null Handler pro 500 */
    private static mixed $handler500 = null;

    // -------------------------------------------------------------------------
    // Registrace tras
    // -------------------------------------------------------------------------

    public static function get(string $vzor, callable|array $obsluha, array $middleware = []): void
    {
        self::registrovat('GET', $vzor, $obsluha, $middleware);
    }

    public static function post(string $vzor, callable|array $obsluha, array $middleware = []): void
    {
        self::registrovat('POST', $vzor, $obsluha, $middleware);
    }

    public static function put(string $vzor, callable|array $obsluha, array $middleware = []): void
    {
        self::registrovat('PUT', $vzor, $obsluha, $middleware);
    }

    public static function delete(string $vzor, callable|array $obsluha, array $middleware = []): void
    {
        self::registrovat('DELETE', $vzor, $obsluha, $middleware);
    }

    /**
     * Zkratka pro GET + POST na stejnou trasu.
     */
    public static function any(string $vzor, callable|array $obsluha, array $middleware = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $metoda) {
            self::registrovat($metoda, $vzor, $obsluha, $middleware);
        }
    }

    // -------------------------------------------------------------------------
    // Middleware
    // -------------------------------------------------------------------------

    /**
     * Přidá globální middleware — platí pro VŠECHNY trasy.
     * Pořadí volání: middleware jsou volány v pořadí registrace.
     *
     * Middleware signatura: function(array $parametry): bool
     *   - Vrátí true  → pokračuje dál
     *   - Vrátí false → zastaví zpracování (musí sám poslat response)
     */
    public static function middleware(callable $fn): void
    {
        self::$globalniMiddleware[] = $fn;
    }

    // -------------------------------------------------------------------------
    // Error handlery
    // -------------------------------------------------------------------------

    public static function chyba404(callable $fn): void { self::$handler404 = $fn; }
    public static function chyba403(callable $fn): void { self::$handler403 = $fn; }
    public static function chyba500(callable $fn): void { self::$handler500 = $fn; }

    // -------------------------------------------------------------------------
    // Dispatch — hlavní vstupní bod
    // -------------------------------------------------------------------------

    /**
     * Zpracuje příchozí požadavek. Volat z router.php.
     *
     * @param string $metoda   HTTP metoda ($_SERVER['REQUEST_METHOD'])
     * @param string $url      Cesta ($_SERVER['REQUEST_URI'])
     * @return bool            true = trasa nalezena a zpracována
     */
    public static function odeslat(string $metoda, string $url): bool
    {
        $metoda = strtoupper($metoda);
        $cesta  = self::normalizujCestu($url);

        // Method override pro formuláře (POST + _method=DELETE apod.)
        if ($metoda === 'POST' && !empty($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $metoda = $override;
            }
        }

        $trasySProMetodou = self::$trasy[$metoda] ?? [];

        foreach ($trasySProMetodou as $trasa) {
            if (!preg_match($trasa['regex'], $cesta, $shody)) {
                continue;
            }

            // Extrahovat pojmenované parametry
            $parametry = [];
            foreach ($trasa['parametry'] as $nazev) {
                $parametry[$nazev] = $shody[$nazev] ?? '';
            }

            // Spustit globální middleware
            foreach (self::$globalniMiddleware as $mw) {
                if (call_user_func($mw, $parametry) === false) {
                    return true; // Middleware zastavil zpracování
                }
            }

            // Spustit middleware specifický pro trasu
            foreach ($trasa['middleware'] as $mw) {
                if (call_user_func($mw, $parametry) === false) {
                    return true;
                }
            }

            // Spustit obsluhu trasy
            try {
                call_user_func($trasa['obsluha'], $parametry);
            } catch (Throwable $e) {
                error_log("Router: Chyba při zpracování trasy '{$trasa['vzor']}': " . $e->getMessage());
                if (self::$handler500) {
                    call_user_func(self::$handler500, $e);
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => 'Interní chyba serveru']);
                }
            }

            return true;
        }

        // Trasa nenalezena → 404
        http_response_code(404);
        if (self::$handler404) {
            call_user_func(self::$handler404, $cesta);
        } else {
            self::vychozi404($cesta);
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Pomocné metody
    // -------------------------------------------------------------------------

    /**
     * Generuje URL pro pojmenovanou trasu.
     * Nahradí {parametry} skutečnými hodnotami.
     *
     * @example Router::url('/r/{cislo}', ['cislo' => 'WGS-001']) → '/r/WGS-001'
     */
    public static function url(string $vzor, array $parametry = []): string
    {
        foreach ($parametry as $klic => $hodnota) {
            $vzor = str_replace('{' . $klic . '}', rawurlencode((string)$hodnota), $vzor);
        }
        return $vzor;
    }

    /**
     * Přesměruje na jinou URL.
     */
    public static function presmerovat(string $url, int $kod = 302): void
    {
        header('Location: ' . $url, true, $kod);
        exit;
    }

    /**
     * Vrátí JSON odpověď a ukončí skript.
     */
    public static function json(mixed $data, int $kod = 200): void
    {
        http_response_code($kod);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Vrátí seznam registrovaných tras (pro debugování/dokumentaci).
     */
    public static function listTrasy(): array
    {
        $vysledek = [];
        foreach (self::$trasy as $metoda => $trasy) {
            foreach ($trasy as $trasa) {
                $vysledek[] = [
                    'metoda'    => $metoda,
                    'vzor'      => $trasa['vzor'],
                    'parametry' => $trasa['parametry'],
                ];
            }
        }
        return $vysledek;
    }

    // -------------------------------------------------------------------------
    // Privátní implementace
    // -------------------------------------------------------------------------

    private static function registrovat(string $metoda, string $vzor, callable|array $obsluha, array $middleware): void
    {
        [$regex, $parametry] = self::sestavRegex($vzor);

        self::$trasy[$metoda][] = [
            'vzor'       => $vzor,
            'regex'      => $regex,
            'parametry'  => $parametry,
            'obsluha'    => is_array($obsluha) ? $obsluha : $obsluha,
            'middleware' => $middleware,
        ];
    }

    /**
     * Převede vzor trasy na regulární výraz.
     *
     * Podporované formáty:
     *   {nazev}          → libovolný segment bez lomítka
     *   {nazev:[a-z]+}   → segment s vlastním regex
     *   {nazev:int}      → celé číslo (zkratka pro [0-9]+)
     *   {nazev:slug}     → slug (zkratka pro [a-z0-9-]+)
     *   {nazev:any}      → cokoliv včetně lomítek
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function sestavRegex(string $vzor): array
    {
        $parametry = [];

        $regex = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}/',
            function (array $shoda) use (&$parametry): string {
                $nazev    = $shoda[1];
                $typ      = $shoda[2] ?? null;
                $parametry[] = $nazev;

                $vzorSegmentu = match ($typ) {
                    'int'   => '[0-9]+',
                    'slug'  => '[a-z0-9-]+',
                    'any'   => '.+',
                    null    => '[^/]+',
                    default => $typ,
                };

                return '(?P<' . $nazev . '>' . $vzorSegmentu . ')';
            },
            preg_quote($vzor, '#')
        );

        return ['#^' . $regex . '$#u', $parametry];
    }

    private static function normalizujCestu(string $url): string
    {
        $cesta = parse_url($url, PHP_URL_PATH) ?: '/';
        $cesta = '/' . trim($cesta, '/');
        if ($cesta !== '/') {
            $cesta = rtrim($cesta, '/');
        }
        return $cesta;
    }

    private static function vychozi404(string $cesta): void
    {
        if (str_starts_with($cesta, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'error',
                'message' => "Endpoint '{$cesta}' nenalezen",
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8">'
                . '<title>404 – Stránka nenalezena</title>'
                . '<style>body{font-family:sans-serif;max-width:600px;margin:80px auto;padding:20px}'
                . 'h1{font-size:1.8rem}a{color:#333}</style></head><body>'
                . '<h1>404 – Stránka nenalezena</h1>'
                . '<p>Cesta <code>' . htmlspecialchars($cesta, ENT_QUOTES, 'UTF-8') . '</code> neexistuje.</p>'
                . '<p><a href="/">Zpět na hlavní stránku</a></p>'
                . '</body></html>';
        }
    }
}
