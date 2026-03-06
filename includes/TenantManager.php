<?php
/**
 * TenantManager - Správa multi-tenant kontextu
 *
 * Architektura: Sdílená databáze, oddělená data (shared DB, separate rows)
 * Každý záznam nese tenant_id — nejjednodušší a nejúdržbovatelnější přístup
 * pro malý počet tenantů (desítky).
 *
 * Detekce tenanta (v pořadí priority):
 *   1. Subdoména: tenant1.wgs-service.cz → slug = "tenant1"
 *   2. Session (uložená při přihlášení)
 *   3. Výchozí tenant (slug = "default")
 *
 * Použití:
 *   $spravce = TenantManager::getInstance();
 *   $tenantId = $spravce->getTenantId();  // int
 *   $tenantSlug = $spravce->getSlug();    // string
 *
 *   // V SQL dotazech:
 *   $stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE tenant_id = :tid AND id = :id");
 *   $stmt->execute(['tid' => $spravce->getTenantId(), 'id' => $id]);
 */

if (!defined('BASE_PATH')) {
    die('Přímý přístup zakázán.');
}

class TenantManager
{
    private static ?TenantManager $instance = null;

    private int    $tenantId   = 1;
    private string $slug       = 'default';
    private array  $nastaveni  = [];
    private bool   $nacten     = false;

    // Cache tenantů v rámci requestu
    private static array $cache = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializuje tenant kontext — volá se z init.php po startu session.
     */
    public function inicializovat(PDO $pdo): void
    {
        if ($this->nacten) {
            return;
        }

        // Rychlá cesta: tenant_id je již uložen v session z minulého requestu
        if (!empty($_SESSION['tenant_id']) && !empty($_SESSION['tenant_slug'])) {
            $this->tenantId = (int) $_SESSION['tenant_id'];
            $this->slug     = $_SESSION['tenant_slug'];
            $this->nacten   = true;
            return;
        }

        $slug = $this->detekujSlug();
        $this->nactiTenanta($pdo, $slug);
        $this->nacten = true;
    }

    // =============================================
    // Gettery
    // =============================================

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getNastaveni(string $klic, mixed $vychozi = null): mixed
    {
        return $this->nastaveni[$klic] ?? $vychozi;
    }

    public function getNazev(): string
    {
        return $this->nastaveni['nazev'] ?? 'WGS Service';
    }

    public function jeVychozi(): bool
    {
        return $this->slug === 'default';
    }

    // =============================================
    // WHERE podmínka pro SQL dotazy
    // =============================================

    /**
     * Vrátí podmínku pro prepared statement: "tenant_id = :tenant_id"
     * Použití: "SELECT * FROM tabulka WHERE " . $tm->whereKlauzule()
     */
    public function whereKlauzule(string $alias = 'tenant_id'): string
    {
        return "tenant_id = :{$alias}";
    }

    /**
     * Vrátí pole parametrů pro execute(): ['tenant_id' => 1]
     */
    public function whereParametry(string $alias = 'tenant_id'): array
    {
        return [$alias => $this->tenantId];
    }

    // =============================================
    // Správa tenantů (admin operace)
    // =============================================

    /**
     * Načte všechny aktivní tenanty.
     */
    public static function vsichniTenanti(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT tenant_id, slug, nazev, domena, je_aktivni, datum_vytvoreni
             FROM wgs_tenants
             WHERE je_aktivni = 1
             ORDER BY nazev"
        );
        return $stmt->fetchAll();
    }

    /**
     * Vytvoří nového tenanta.
     */
    public static function vytvorit(PDO $pdo, string $slug, string $nazev, string $domena = ''): int
    {
        $slug = self::normalizujSlug($slug);

        // Kontrola unikátnosti
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_tenants WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        if ($stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException("Tenant se slugem '{$slug}' již existuje.");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO wgs_tenants (slug, nazev, domena, je_aktivni, datum_vytvoreni)
             VALUES (:slug, :nazev, :domena, 1, NOW())"
        );
        $stmt->execute([
            'slug'   => $slug,
            'nazev'  => trim($nazev),
            'domena' => trim($domena),
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Deaktivuje tenanta (soft delete).
     */
    public static function deaktivovat(PDO $pdo, int $tenantId): void
    {
        if ($tenantId === 1) {
            throw new InvalidArgumentException('Výchozí tenant nelze deaktivovat.');
        }
        $stmt = $pdo->prepare("UPDATE wgs_tenants SET je_aktivni = 0 WHERE tenant_id = :id");
        $stmt->execute(['id' => $tenantId]);
    }

    // =============================================
    // Privátní pomocné metody
    // =============================================

    private function detekujSlug(): string
    {
        // 1. Subdoména (tenant1.wgs-service.cz → "tenant1")
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $hlavniDomena = $_ENV['APP_DOMAIN'] ?? getenv('APP_DOMAIN') ?: 'wgs-service.cz';
        if ($host && str_ends_with($host, '.' . $hlavniDomena)) {
            $subdomena = substr($host, 0, -(strlen($hlavniDomena) + 1));
            if ($subdomena && $subdomena !== 'www' && preg_match('/^[a-z0-9-]+$/', $subdomena)) {
                return $subdomena;
            }
        }

        // 2. Session (uložená při přihlášení uživatele)
        if (!empty($_SESSION['tenant_slug'])) {
            return $_SESSION['tenant_slug'];
        }

        // 3. Výchozí
        return 'default';
    }

    private function nactiTenanta(PDO $pdo, string $slug): void
    {
        // Cache pro opakované volání v rámci requestu
        if (isset(self::$cache[$slug])) {
            $radek = self::$cache[$slug];
        } else {
            $stmt = $pdo->prepare(
                "SELECT tenant_id, slug, nazev, domena, nastaveni_json
                 FROM wgs_tenants
                 WHERE slug = :slug AND je_aktivni = 1
                 LIMIT 1"
            );
            $stmt->execute(['slug' => $slug]);
            $radek = $stmt->fetch();
            self::$cache[$slug] = $radek;
        }

        if ($radek) {
            $this->tenantId  = (int) $radek['tenant_id'];
            $this->slug      = $radek['slug'];
            $this->nastaveni = $radek['nastaveni_json']
                ? (json_decode($radek['nastaveni_json'], true) ?? [])
                : [];
        } else {
            // Neznámý slug → fallback na výchozí tenant
            error_log("TenantManager: Tenant '{$slug}' nenalezen, použit výchozí.");
            $this->nactiTenanta($pdo, 'default');
            return;
        }

        // Uložit do session pro následující requesty (včetně tenant_id pro cache)
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['tenant_slug'] = $this->slug;
            $_SESSION['tenant_id']   = $this->tenantId;
        }
    }

    private static function normalizujSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
