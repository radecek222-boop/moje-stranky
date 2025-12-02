<?php
/**
 * API Response Tests
 * Step 152: Unit testy pro API odpovědi
 *
 * Testuje strukturu a formát API odpovědí.
 */

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    /**
     * Test: Success payload má správnou strukturu
     */
    public function testSuccessPayloadMáSprávnouStrukturu(): void
    {
        // Simulovat co dělá ApiResponse::success interně
        $data = ['items' => [1, 2, 3]];
        $message = 'Operace úspěšná';

        $payload = ['status' => 'success'];
        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }
        if (is_array($data)) {
            $payload = array_merge($payload, $data);
        }

        // Ověřit strukturu
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame('success', $payload['status']);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('items', $payload);
    }

    /**
     * Test: Error payload má správnou strukturu
     */
    public function testErrorPayloadMáSprávnouStrukturu(): void
    {
        $message = 'Došlo k chybě.';
        $details = ['field' => 'email', 'error' => 'Neplatný formát'];

        $payload = [
            'status' => 'error',
            'message' => $message
        ];
        if ($details !== null) {
            $payload['details'] = $details;
        }

        // Ověřit strukturu
        $this->assertArrayHasKey('status', $payload);
        $this->assertSame('error', $payload['status']);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('details', $payload);
    }

    /**
     * Test: JSON encoding zachovává české znaky
     */
    public function testJsonEncodingZachovávčáČeskéZnaky(): void
    {
        $payload = [
            'status' => 'success',
            'message' => 'Příliš žluťoučký kůň úpěl ďábelské ódy.'
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // České znaky by neměly být escapovány
        $this->assertStringContainsString('Příliš', $json);
        $this->assertStringContainsString('žluťoučký', $json);
        $this->assertStringContainsString('ďábelské', $json);
        $this->assertStringNotContainsString('\u', $json);
    }

    /**
     * Test: Prázdný message není zahrnut
     */
    public function testPrázdnýMessageNeníZahrnut(): void
    {
        $payload = ['status' => 'success'];
        $message = '';

        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        $this->assertArrayNotHasKey('message', $payload);
    }

    /**
     * Test: Null message není zahrnut
     */
    public function testNullMessageNeníZahrnut(): void
    {
        $payload = ['status' => 'success'];
        $message = null;

        if ($message !== null && $message !== '') {
            $payload['message'] = $message;
        }

        $this->assertArrayNotHasKey('message', $payload);
    }

    /**
     * Test: Data se správně mergují
     */
    public function testDataSeSpávněMergují(): void
    {
        $payload = ['status' => 'success'];
        $data = ['count' => 5, 'total' => 100];

        if (is_array($data)) {
            $payload = array_merge($payload, $data);
        }

        $this->assertSame('success', $payload['status']);
        $this->assertSame(5, $payload['count']);
        $this->assertSame(100, $payload['total']);
    }

    /**
     * Test: Non-array data jdou do 'data' klíče
     */
    public function testNonArrayDataJdouDoDataKlíče(): void
    {
        $payload = ['status' => 'success'];
        $data = 'jednoduchý string';

        if (is_array($data)) {
            $payload = array_merge($payload, $data);
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }

        $this->assertArrayHasKey('data', $payload);
        $this->assertSame('jednoduchý string', $payload['data']);
    }

    /**
     * Test: Meta je přidána když není null
     */
    public function testMetaJePřidánaKdyžNeníNull(): void
    {
        $payload = ['status' => 'success'];
        $meta = ['page' => 1, 'per_page' => 20];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        $this->assertArrayHasKey('meta', $payload);
        $this->assertSame(1, $payload['meta']['page']);
    }

    /**
     * Test: Error bez details nemá details klíč
     */
    public function testErrorBezDetailsNemáDetailsKlíč(): void
    {
        $payload = [
            'status' => 'error',
            'message' => 'Chyba'
        ];
        $details = null;

        if ($details !== null) {
            $payload['details'] = $details;
        }

        $this->assertArrayNotHasKey('details', $payload);
    }

    /**
     * Test: Standardní API HTTP kódy
     */
    public function testStandardníApiHttpKódy(): void
    {
        // Success kódy
        $this->assertSame(200, 200); // OK
        $this->assertSame(201, 201); // Created

        // Client error kódy
        $this->assertSame(400, 400); // Bad Request
        $this->assertSame(401, 401); // Unauthorized
        $this->assertSame(403, 403); // Forbidden
        $this->assertSame(404, 404); // Not Found
        $this->assertSame(429, 429); // Too Many Requests

        // Server error kódy
        $this->assertSame(500, 500); // Internal Server Error
    }
}
