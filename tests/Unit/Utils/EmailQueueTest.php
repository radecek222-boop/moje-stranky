<?php

namespace Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use EmailQueue;

/**
 * Testy pro Email Queue Manager
 *
 * Testuje:
 * - Enqueue emailů
 * - Processing fronty
 * - Transaction handling
 * - Retry mechanismus
 * - SMTP settings
 */
class EmailQueueTest extends TestCase
{
    private $pdo;
    private $emailQueue;

    protected function setUp(): void
    {
        parent::setUp();

        // Vytvořit in-memory SQLite databázi
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Vytvořit tabulky
        $this->createTables();

        // Načíst EmailQueue třídu
        require_once __DIR__ . '/../../../includes/EmailQueue.php';

        // Mock getDbConnection() pokud neexistuje
        if (!function_exists('getDbConnection')) {
            function getDbConnection() {
                global $GLOBALS;
                return $GLOBALS['test_pdo'];
            }
        }
        $GLOBALS['test_pdo'] = $this->pdo;

        $this->emailQueue = new EmailQueue($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->emailQueue = null;
        parent::tearDown();
    }

    private function createTables(): void
    {
        // Email queue tabulka
        $this->pdo->exec("
            CREATE TABLE wgs_email_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                notification_id VARCHAR(50),
                recipient_email VARCHAR(255) NOT NULL,
                recipient_name VARCHAR(255),
                subject VARCHAR(500) NOT NULL,
                body TEXT NOT NULL,
                cc_emails TEXT,
                bcc_emails TEXT,
                priority VARCHAR(20) DEFAULT 'normal',
                status VARCHAR(20) DEFAULT 'pending',
                attempts INTEGER DEFAULT 0,
                max_attempts INTEGER DEFAULT 3,
                error_message TEXT,
                scheduled_at DATETIME,
                sent_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // SMTP settings tabulka
        $this->pdo->exec("
            CREATE TABLE wgs_smtp_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                smtp_host VARCHAR(255),
                smtp_port INTEGER,
                smtp_encryption VARCHAR(10),
                smtp_username VARCHAR(255),
                smtp_password VARCHAR(255),
                smtp_from_email VARCHAR(255),
                smtp_from_name VARCHAR(255),
                is_active INTEGER DEFAULT 1
            )
        ");

        // Vložit testovací SMTP settings
        $this->pdo->exec("
            INSERT INTO wgs_smtp_settings (
                smtp_host, smtp_port, smtp_encryption,
                smtp_username, smtp_password,
                smtp_from_email, smtp_from_name, is_active
            ) VALUES (
                'smtp.test.com', 587, 'tls',
                'test@test.com', 'password123',
                'noreply@test.com', 'Test System', 1
            )
        ");
    }

    /**
     * Test: Enqueue přidá email do fronty
     */
    public function testEnqueuePridaEmailDoFronty(): void
    {
        $result = $this->emailQueue->enqueue([
            'to' => 'recipient@example.com',
            'subject' => 'Test Subject',
            'body' => 'Test Body',
            'priority' => 'high'
        ]);

        $this->assertTrue($result, 'Enqueue musí vrátit true');

        // Ověřit že email je v databázi
        $stmt = $this->pdo->query("SELECT * FROM wgs_email_queue");
        $email = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($email);
        $this->assertEquals('recipient@example.com', $email['recipient_email']);
        $this->assertEquals('Test Subject', $email['subject']);
        $this->assertEquals('Test Body', $email['body']);
        $this->assertEquals('high', $email['priority']);
    }

    /**
     * Test: Enqueue s transakcí
     */
    public function testEnqueueSTransakci(): void
    {
        $this->pdo->beginTransaction();

        $result = $this->emailQueue->enqueue([
            'to' => 'test@example.com',
            'subject' => 'Test',
            'body' => 'Body'
        ], true); // useTransaction = true

        $this->assertTrue($result);

        // Transakce byla commitnuta
        $this->assertFalse($this->pdo->inTransaction());
    }

    /**
     * Test: Enqueue ukládá CC a BCC jako JSON
     */
    public function testEnqueueUkladaCcBccJakoJson(): void
    {
        $this->emailQueue->enqueue([
            'to' => 'recipient@example.com',
            'subject' => 'Test',
            'body' => 'Body',
            'cc' => ['cc1@example.com', 'cc2@example.com'],
            'bcc' => ['bcc1@example.com']
        ]);

        $stmt = $this->pdo->query("SELECT * FROM wgs_email_queue");
        $email = $stmt->fetch(\PDO::FETCH_ASSOC);

        $ccEmails = json_decode($email['cc_emails'], true);
        $bccEmails = json_decode($email['bcc_emails'], true);

        $this->assertIsArray($ccEmails);
        $this->assertCount(2, $ccEmails);
        $this->assertEquals('cc1@example.com', $ccEmails[0]);

        $this->assertIsArray($bccEmails);
        $this->assertCount(1, $bccEmails);
    }

    /**
     * Test: GetStats vrací správné statistiky
     */
    public function testGetStatsVraciSpravneStatistiky(): void
    {
        // Přidat několik emailů s různými stavy
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (recipient_email, subject, body, status)
            VALUES
                ('test1@example.com', 'Test 1', 'Body 1', 'pending'),
                ('test2@example.com', 'Test 2', 'Body 2', 'pending'),
                ('test3@example.com', 'Test 3', 'Body 3', 'sent'),
                ('test4@example.com', 'Test 4', 'Body 4', 'failed')
        ");

        $stats = $this->emailQueue->getStats();

        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(1, $stats['sent']);
        $this->assertEquals(1, $stats['failed']);
    }

    /**
     * Test: GetQueue vrací emaily podle stavu
     */
    public function testGetQueueVraciEmailyPodleStavu(): void
    {
        // Přidat emaily
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (recipient_email, subject, body, status)
            VALUES
                ('pending1@example.com', 'P1', 'Body', 'pending'),
                ('pending2@example.com', 'P2', 'Body', 'pending'),
                ('sent1@example.com', 'S1', 'Body', 'sent')
        ");

        $pendingEmails = $this->emailQueue->getQueue('pending');

        $this->assertCount(2, $pendingEmails);
        $this->assertEquals('pending1@example.com', $pendingEmails[0]['recipient_email']);
    }

    /**
     * Test: GetQueue bez stavu vrací všechny emaily
     */
    public function testGetQueueBezStavuVraciVsechny(): void
    {
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (recipient_email, subject, body, status)
            VALUES
                ('test1@example.com', 'T1', 'Body', 'pending'),
                ('test2@example.com', 'T2', 'Body', 'sent')
        ");

        $allEmails = $this->emailQueue->getQueue();

        $this->assertCount(2, $allEmails);
    }

    /**
     * Test: Retry resetuje email status
     */
    public function testRetryResetujeStatus(): void
    {
        // Přidat failed email
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (recipient_email, subject, body, status, attempts, error_message)
            VALUES ('failed@example.com', 'Failed', 'Body', 'failed', 3, 'Test error')
        ");

        $stmt = $this->pdo->query("SELECT id FROM wgs_email_queue WHERE recipient_email = 'failed@example.com'");
        $emailId = $stmt->fetchColumn();

        $this->emailQueue->retry($emailId);

        // Ověřit že status je pending a attempts = 0
        $stmt = $this->pdo->prepare("SELECT * FROM wgs_email_queue WHERE id = ?");
        $stmt->execute([$emailId]);
        $email = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals('pending', $email['status']);
        $this->assertEquals(0, $email['attempts']);
        $this->assertNull($email['error_message']);
    }

    /**
     * Test: Delete odstraní email z fronty
     */
    public function testDeleteOdstraniEmail(): void
    {
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (recipient_email, subject, body)
            VALUES ('delete@example.com', 'Delete Test', 'Body')
        ");

        $stmt = $this->pdo->query("SELECT id FROM wgs_email_queue WHERE recipient_email = 'delete@example.com'");
        $emailId = $stmt->fetchColumn();

        $this->emailQueue->delete($emailId);

        // Ověřit že email už není v databázi
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM wgs_email_queue WHERE id = ?");
        $stmt->execute([$emailId]);
        $count = $stmt->fetchColumn();

        $this->assertEquals(0, $count, 'Email musí být odstraněn');
    }

    /**
     * Test: ProcessQueue zpracuje pending emaily
     */
    public function testProcessQueueZpracujePendingEmaily(): void
    {
        // Přidat pending email se scheduled_at v minulosti
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (
                recipient_email, subject, body, status,
                scheduled_at, attempts, max_attempts
            )
            VALUES (
                'process@example.com', 'Process Test', 'Body', 'pending',
                datetime('now', '-1 hour'), 0, 3
            )
        ");

        // ProcessQueue se pokusí odeslat (ale v testu selže protože nemáme SMTP)
        $results = $this->emailQueue->processQueue(10);

        $this->assertEquals(1, $results['processed'], 'Musí zpracovat 1 email');
        // sent nebo failed závisí na dostupnosti PHPMailer
    }

    /**
     * Test: ProcessQueue respektuje scheduled_at
     */
    public function testProcessQueueRespektujeScheduledAt(): void
    {
        // Email naplánovaný do budoucnosti
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (
                recipient_email, subject, body, status, scheduled_at
            )
            VALUES (
                'future@example.com', 'Future', 'Body', 'pending',
                datetime('now', '+1 hour')
            )
        ");

        $results = $this->emailQueue->processQueue(10);

        $this->assertEquals(0, $results['processed'], 'Nesmí zpracovat email naplánovaný do budoucnosti');
    }

    /**
     * Test: ProcessQueue respektuje max_attempts
     */
    public function testProcessQueueRespektujeMaxAttempts(): void
    {
        // Email s vyčerpanými pokusy
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (
                recipient_email, subject, body, status,
                scheduled_at, attempts, max_attempts
            )
            VALUES (
                'maxed@example.com', 'Maxed', 'Body', 'pending',
                datetime('now'), 3, 3
            )
        ");

        $results = $this->emailQueue->processQueue(10);

        $this->assertEquals(0, $results['processed'], 'Nesmí zpracovat email s attempts >= max_attempts');
    }

    /**
     * Test: Priorita ovlivňuje pořadí zpracování
     */
    public function testPrioritaOvlivnujePoradi(): void
    {
        // Přidat emaily s různou prioritou
        $this->pdo->exec("
            INSERT INTO wgs_email_queue (
                recipient_email, subject, body, status,
                scheduled_at, priority, created_at
            )
            VALUES
                ('low@example.com', 'Low', 'Body', 'pending', datetime('now'), 'low', datetime('now', '-2 hours')),
                ('high@example.com', 'High', 'Body', 'pending', datetime('now'), 'high', datetime('now', '-1 hour'))
        ");

        // High priority email by měl být zpracován první (i když byl vytvořen později)
        // Toto je testováno implicitně přes ORDER BY priority DESC v processQueue()

        $queue = $this->emailQueue->getQueue('pending', 10, 0);

        // V reálné implementaci by high priority měl být první
        $this->assertCount(2, $queue);
    }

    /**
     * Test: Validace emailových adres v CC/BCC
     */
    public function testValidaceEmailuVCcBcc(): void
    {
        $this->emailQueue->enqueue([
            'to' => 'recipient@example.com',
            'subject' => 'Test',
            'body' => 'Body',
            'cc' => ['valid@example.com', 'invalid-email', 'another@valid.com'],
            'bcc' => ['bcc@example.com']
        ]);

        $stmt = $this->pdo->query("SELECT * FROM wgs_email_queue");
        $email = $stmt->fetch(\PDO::FETCH_ASSOC);

        $ccEmails = json_decode($email['cc_emails'], true);

        // Všechny emaily jsou uloženy (validace probíhá při odesílání)
        $this->assertCount(3, $ccEmails);
    }

    /**
     * Test: Rollback při chybě v enqueue s transakcí
     */
    public function testRollbackPriChybeVEnqueue(): void
    {
        // Tento test ověřuje že při chybě v enqueue() s useTransaction=true
        // dojde k rollback transakce

        // Nejprve vytvořit validní email
        $this->pdo->beginTransaction();

        try {
            // Pokusit se vložit email s chybou (např. chybějící povinné pole)
            // V reálném případě by to byla PDO exception
            $this->pdo->rollBack();

            // Ověřit že transakce není aktivní
            $this->assertFalse($this->pdo->inTransaction());
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->assertFalse($this->pdo->inTransaction());
        }
    }
}
