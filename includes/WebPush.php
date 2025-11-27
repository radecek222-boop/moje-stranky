<?php
/**
 * WebPush - Trida pro odesilani Web Push notifikaci
 *
 * Pouziva knihovnu minishlink/web-push pro sifrovani a odesilani
 * push zprav na registrovana zarizeni (iOS 16.4+, Android, desktop).
 */

// Nacist autoloader pokud existuje
$autoloadSoubor = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadSoubor)) {
    require_once $autoloadSoubor;
}

// POZOR: use statements jsou uvnitr metod jako fully qualified names
// aby nedoslo k fatal error kdyz knihovna neni nainstalovana

class WGSWebPush {

    private $webPush = null; // Minishlink\WebPush\WebPush
    private ?PDO $pdo = null;
    private bool $inicializovano = false;
    private string $chyba = '';

    /**
     * Konstruktor - inicializace WebPush s VAPID klici
     */
    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;

        // Kontrola knihovny
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            $this->chyba = 'Knihovna minishlink/web-push neni nainstalovana. Spustte: composer update';
            return;
        }

        // Nacist VAPID klice z .env
        $vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
        $vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '';
        $vapidSubject = $_ENV['VAPID_SUBJECT'] ?? getenv('VAPID_SUBJECT') ?? 'mailto:info@wgs-service.cz';

        if (empty($vapidPublic) || empty($vapidPrivate)) {
            $this->chyba = 'VAPID klice nejsou nakonfigurovany. Spustte setup_web_push.php';
            return;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => $vapidSubject,
                    'publicKey' => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ];

            // POZOR: Hosting neumi overit Apple Push SSL certifikaty
            // - System CA bundle nema Apple certifikaty (error 60)
            // - Hosting blokuje vlastni CA soubory (error 77)
            // Proto vytvorime vlastni Guzzle client s custom cURL handlerem
            // TODO: Kontaktovat hosting pro opravu SSL/CA konfigurace

            // Vytvorit custom cURL handler s vypnutou SSL verifikaci
            $curlHandler = new \GuzzleHttp\Handler\CurlHandler();
            $handlerStack = \GuzzleHttp\HandlerStack::create($curlHandler);

            // Vytvorit Guzzle HTTP client s custom handlerem
            $httpClient = new \GuzzleHttp\Client([
                'handler' => $handlerStack,
                'verify' => false,
                'curl' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ],
            ]);

            error_log('[WebPush] VAROVANI: SSL verifikace vypnuta - custom cURL handler');

            // Predat vlastni HTTP client do WebPush (4. parametr)
            $this->webPush = new \Minishlink\WebPush\WebPush($auth, [], 30, $httpClient);
            $this->webPush->setReuseVAPIDHeaders(true);
            $this->inicializovano = true;

        } catch (Exception $e) {
            $this->chyba = 'Chyba inicializace WebPush: ' . $e->getMessage();
            error_log('[WebPush] ' . $this->chyba);
        }
    }

    /**
     * Zkontrolovat zda je WebPush pripraveny
     */
    public function jeInicializovano(): bool {
        return $this->inicializovano;
    }

    /**
     * Ziskat chybovou zpravu
     */
    public function getChyba(): string {
        return $this->chyba;
    }

    /**
     * Ziskat VAPID public key pro frontend
     */
    public static function getVapidPublicKey(): string {
        return $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '';
    }

    /**
     * Odeslat notifikaci na konkretni subscription
     *
     * @param array $subscription ['endpoint', 'p256dh', 'auth']
     * @param array $payload ['title', 'body', 'icon', 'url', 'data']
     * @return array ['uspech' => bool, 'zprava' => string]
     */
    public function odeslatNotifikaci(array $subscription, array $payload): array {
        if (!$this->inicializovano) {
            return ['uspech' => false, 'zprava' => $this->chyba];
        }

        try {
            // Vytvorit Subscription objekt
            $sub = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $subscription['endpoint'],
                'publicKey' => $subscription['p256dh'],
                'authToken' => $subscription['auth'],
            ]);

            // Pripravit payload jako JSON
            $jsonPayload = json_encode([
                'title' => $payload['title'] ?? 'WGS Notifikace',
                'body' => $payload['body'] ?? '',
                'icon' => $payload['icon'] ?? '/icon192.png',
                'badge' => $payload['badge'] ?? '/icon192.png',
                'tag' => $payload['tag'] ?? 'wgs-notification',
                'data' => $payload['data'] ?? [],
            ], JSON_UNESCAPED_UNICODE);

            // Odeslat
            $report = $this->webPush->sendOneNotification($sub, $jsonPayload);

            if ($report->isSuccess()) {
                return ['uspech' => true, 'zprava' => 'Notifikace odeslana'];
            } else {
                $reason = $report->getReason();
                return ['uspech' => false, 'zprava' => 'Push failed: ' . $reason];
            }

        } catch (Exception $e) {
            error_log('[WebPush] Chyba odeslani: ' . $e->getMessage());
            return ['uspech' => false, 'zprava' => $e->getMessage()];
        }
    }

    /**
     * Odeslat notifikaci vsem aktivnim subscription pro daneho uzivatele
     *
     * @param int $userId ID uzivatele
     * @param array $payload Data notifikace
     * @return array Vysledky odeslani
     */
    public function odeslatUzivateli(int $userId, array $payload): array {
        if (!$this->pdo) {
            return ['uspech' => false, 'zprava' => 'Neni pripojeni k databazi'];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, endpoint, p256dh, auth
            FROM wgs_push_subscriptions
            WHERE user_id = :user_id AND aktivni = 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subscriptions)) {
            return ['uspech' => true, 'zprava' => 'Uzivatel nema aktivni subscriptions', 'odeslano' => 0];
        }

        return $this->odeslatVice($subscriptions, $payload);
    }

    /**
     * Odeslat notifikaci vsem aktivnim subscriptions (broadcast)
     *
     * @param array $payload Data notifikace
     * @param string|null $platforma Filtr na platformu (ios, android, desktop)
     * @return array Vysledky odeslani
     */
    public function odeslatVsem(array $payload, ?string $platforma = null): array {
        if (!$this->pdo) {
            return ['uspech' => false, 'zprava' => 'Neni pripojeni k databazi'];
        }

        $sql = "SELECT id, endpoint, p256dh, auth FROM wgs_push_subscriptions WHERE aktivni = 1";
        $params = [];

        if ($platforma) {
            $sql .= " AND platforma = :platforma";
            $params['platforma'] = $platforma;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subscriptions)) {
            return ['uspech' => true, 'zprava' => 'Zadne aktivni subscriptions', 'odeslano' => 0];
        }

        return $this->odeslatVice($subscriptions, $payload);
    }

    /**
     * Odeslat notifikaci na vice subscriptions najednou
     *
     * @param array $subscriptions Pole subscription
     * @param array $payload Data notifikace
     * @return array Vysledky
     */
    public function odeslatVice(array $subscriptions, array $payload): array {
        if (!$this->inicializovano) {
            return ['uspech' => false, 'zprava' => $this->chyba];
        }

        $jsonPayload = json_encode([
            'title' => $payload['title'] ?? 'WGS Notifikace',
            'body' => $payload['body'] ?? '',
            'icon' => $payload['icon'] ?? '/icon192.png',
            'badge' => $payload['badge'] ?? '/icon192.png',
            'tag' => $payload['tag'] ?? 'wgs-' . time(),
            'data' => $payload['data'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        $odeslano = 0;
        $chyby = 0;
        $neplatne = [];

        foreach ($subscriptions as $sub) {
            try {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'publicKey' => $sub['p256dh'],
                    'authToken' => $sub['auth'],
                ]);

                $this->webPush->queueNotification($subscription, $jsonPayload);

            } catch (Exception $e) {
                error_log('[WebPush] Chyba vytvoreni subscription: ' . $e->getMessage());
                $chyby++;
            }
        }

        // Odeslat vsechny najednou
        foreach ($this->webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                $odeslano++;

                // Aktualizovat cas posledniho odeslani
                if ($this->pdo) {
                    $this->aktualizovatUspech($endpoint);
                }

            } else {
                $chyby++;
                $reason = $report->getReason();

                // Pokud je subscription neplatna, deaktivovat ji
                if ($report->isSubscriptionExpired()) {
                    $neplatne[] = $endpoint;
                    $this->deaktivovatSubscription($endpoint);
                } else {
                    $this->zaznamChybu($endpoint, $reason);
                }

                error_log("[WebPush] Chyba pro {$endpoint}: {$reason}");
            }
        }

        // Zalogovat do wgs_push_log
        if ($this->pdo && ($odeslano > 0 || $chyby > 0)) {
            $this->zalogovatOdeslani($payload, $odeslano, $chyby);
        }

        return [
            'uspech' => $odeslano > 0,
            'zprava' => "Odeslano: {$odeslano}, Chyby: {$chyby}",
            'odeslano' => $odeslano,
            'chyby' => $chyby,
            'neplatne' => count($neplatne)
        ];
    }

    /**
     * Aktualizovat cas uspesneho odeslani
     */
    private function aktualizovatUspech(string $endpoint): void {
        if (!$this->pdo) return;

        try {
            $stmt = $this->pdo->prepare("
                UPDATE wgs_push_subscriptions
                SET posledni_uspesne_odeslani = NOW(), pocet_chyb = 0
                WHERE endpoint = :endpoint
            ");
            $stmt->execute(['endpoint' => $endpoint]);
        } catch (PDOException $e) {
            error_log('[WebPush] DB chyba: ' . $e->getMessage());
        }
    }

    /**
     * Zaznamenat chybu pro subscription
     */
    private function zaznamChybu(string $endpoint, string $duvod): void {
        if (!$this->pdo) return;

        try {
            $stmt = $this->pdo->prepare("
                UPDATE wgs_push_subscriptions
                SET pocet_chyb = pocet_chyb + 1
                WHERE endpoint = :endpoint
            ");
            $stmt->execute(['endpoint' => $endpoint]);

            // Deaktivovat po 5 chybach
            $stmt = $this->pdo->prepare("
                UPDATE wgs_push_subscriptions
                SET aktivni = 0
                WHERE endpoint = :endpoint AND pocet_chyb >= 5
            ");
            $stmt->execute(['endpoint' => $endpoint]);

        } catch (PDOException $e) {
            error_log('[WebPush] DB chyba: ' . $e->getMessage());
        }
    }

    /**
     * Deaktivovat neplatnou subscription
     */
    private function deaktivovatSubscription(string $endpoint): void {
        if (!$this->pdo) return;

        try {
            $stmt = $this->pdo->prepare("
                UPDATE wgs_push_subscriptions
                SET aktivni = 0
                WHERE endpoint = :endpoint
            ");
            $stmt->execute(['endpoint' => $endpoint]);
        } catch (PDOException $e) {
            error_log('[WebPush] DB chyba: ' . $e->getMessage());
        }
    }

    /**
     * Zalogovat odeslani do wgs_push_log
     */
    private function zalogovatOdeslani(array $payload, int $odeslano, int $chyby): void {
        if (!$this->pdo) return;

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO wgs_push_log (typ_notifikace, reklamace_id, titulek, zprava, stav)
                VALUES (:typ, :reklamace_id, :titulek, :zprava, :stav)
            ");

            $stmt->execute([
                'typ' => $payload['typ'] ?? 'obecna',
                'reklamace_id' => $payload['data']['claim_id'] ?? null,
                'titulek' => $payload['title'] ?? '',
                'zprava' => $payload['body'] ?? '',
                'stav' => $odeslano > 0 ? 'odeslano' : 'chyba'
            ]);

        } catch (PDOException $e) {
            error_log('[WebPush] Log chyba: ' . $e->getMessage());
        }
    }

    /**
     * Ulozit novou subscription do databaze
     *
     * @param array $data Subscription data
     * @return array Vysledek
     */
    public function ulozitSubscription(array $data): array {
        if (!$this->pdo) {
            return ['uspech' => false, 'zprava' => 'Neni pripojeni k databazi'];
        }

        $endpoint = $data['endpoint'] ?? '';
        $p256dh = $data['keys']['p256dh'] ?? $data['p256dh'] ?? '';
        $auth = $data['keys']['auth'] ?? $data['auth'] ?? '';

        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            return ['uspech' => false, 'zprava' => 'Chybi povinne udaje subscription'];
        }

        try {
            // Zkontrolovat zda uz existuje
            $stmt = $this->pdo->prepare("
                SELECT id, aktivni FROM wgs_push_subscriptions WHERE endpoint = :endpoint
            ");
            $stmt->execute(['endpoint' => $endpoint]);
            $existuje = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existuje) {
                // Aktualizovat existujici
                $stmt = $this->pdo->prepare("
                    UPDATE wgs_push_subscriptions
                    SET p256dh = :p256dh,
                        auth = :auth,
                        user_id = :user_id,
                        user_agent = :user_agent,
                        platforma = :platforma,
                        aktivni = 1,
                        pocet_chyb = 0,
                        datum_posledni_aktualizace = NOW()
                    WHERE endpoint = :endpoint
                ");

                $stmt->execute([
                    'p256dh' => $p256dh,
                    'auth' => $auth,
                    'user_id' => $data['user_id'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                    'platforma' => $data['platforma'] ?? $this->detekujPlatformu($data['user_agent'] ?? ''),
                    'endpoint' => $endpoint
                ]);

                return ['uspech' => true, 'zprava' => 'Subscription aktualizovana', 'id' => $existuje['id']];

            } else {
                // Vlozit novou
                $stmt = $this->pdo->prepare("
                    INSERT INTO wgs_push_subscriptions
                    (endpoint, p256dh, auth, user_id, user_agent, platforma, aktivni)
                    VALUES
                    (:endpoint, :p256dh, :auth, :user_id, :user_agent, :platforma, 1)
                ");

                $stmt->execute([
                    'endpoint' => $endpoint,
                    'p256dh' => $p256dh,
                    'auth' => $auth,
                    'user_id' => $data['user_id'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                    'platforma' => $data['platforma'] ?? $this->detekujPlatformu($data['user_agent'] ?? '')
                ]);

                return ['uspech' => true, 'zprava' => 'Subscription ulozena', 'id' => $this->pdo->lastInsertId()];
            }

        } catch (PDOException $e) {
            error_log('[WebPush] DB chyba pri ukladani: ' . $e->getMessage());
            return ['uspech' => false, 'zprava' => 'Chyba databaze: ' . $e->getMessage()];
        }
    }

    /**
     * Odstranit subscription
     */
    public function odstranSubscription(string $endpoint): array {
        if (!$this->pdo) {
            return ['uspech' => false, 'zprava' => 'Neni pripojeni k databazi'];
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM wgs_push_subscriptions WHERE endpoint = :endpoint");
            $stmt->execute(['endpoint' => $endpoint]);

            return ['uspech' => true, 'zprava' => 'Subscription odstranena'];

        } catch (PDOException $e) {
            return ['uspech' => false, 'zprava' => 'Chyba databaze'];
        }
    }

    /**
     * Detekovat platformu z user agent
     */
    private function detekujPlatformu(string $userAgent): string {
        $ua = strtolower($userAgent);

        if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) {
            return 'ios';
        } elseif (strpos($ua, 'android') !== false) {
            return 'android';
        } elseif (strpos($ua, 'windows') !== false || strpos($ua, 'macintosh') !== false || strpos($ua, 'linux') !== false) {
            return 'desktop';
        }

        return 'unknown';
    }

    /**
     * Ziskat statistiky subscriptions
     */
    public function getStatistiky(): array {
        if (!$this->pdo) {
            return [];
        }

        try {
            $stmt = $this->pdo->query("
                SELECT
                    COUNT(*) as celkem,
                    SUM(CASE WHEN aktivni = 1 THEN 1 ELSE 0 END) as aktivni,
                    SUM(CASE WHEN platforma = 'ios' THEN 1 ELSE 0 END) as ios,
                    SUM(CASE WHEN platforma = 'android' THEN 1 ELSE 0 END) as android,
                    SUM(CASE WHEN platforma = 'desktop' THEN 1 ELSE 0 END) as desktop
                FROM wgs_push_subscriptions
            ");

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (PDOException $e) {
            return [];
        }
    }
}
