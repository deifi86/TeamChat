<?php

namespace Tests\Unit\Services;

use App\Services\MessageEncryptionService;
use Tests\TestCase;

class MessageEncryptionServiceTest extends TestCase
{
    private MessageEncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageEncryptionService::class);
    }

    public function test_encrypt_decrypt_returns_original(): void
    {
        $original = 'Hello, World!';

        $encrypted = $this->service->encrypt($original);
        $decrypted = $this->service->decrypt($encrypted['encrypted'], $encrypted['iv']);

        $this->assertEquals($original, $decrypted);
    }

    public function test_same_text_produces_different_encrypted_output(): void
    {
        $text = 'Hello';

        $encrypted1 = $this->service->encrypt($text);
        $encrypted2 = $this->service->encrypt($text);

        $this->assertNotEquals($encrypted1['encrypted'], $encrypted2['encrypted']);
        $this->assertNotEquals($encrypted1['iv'], $encrypted2['iv']);
    }

    public function test_encrypts_unicode_correctly(): void
    {
        $original = '👋 Hallo! Grüße aus München 🇩🇪';

        $encrypted = $this->service->encrypt($original);
        $decrypted = $this->service->decrypt($encrypted['encrypted'], $encrypted['iv']);

        $this->assertEquals($original, $decrypted);
    }

    public function test_encrypts_long_text(): void
    {
        $original = str_repeat('Lorem ipsum dolor sit amet. ', 1000);

        $encrypted = $this->service->encrypt($original);
        $decrypted = $this->service->decrypt($encrypted['encrypted'], $encrypted['iv']);

        $this->assertEquals($original, $decrypted);
    }

    public function test_encrypt_for_storage_format(): void
    {
        $content = 'Test message';

        $result = $this->service->encryptForStorage($content);

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('content_iv', $result);
        $this->assertNotEquals($content, $result['content']);
    }

    public function test_decrypt_from_storage(): void
    {
        $original = 'Test message';
        $stored = $this->service->encryptForStorage($original);

        $decrypted = $this->service->decryptFromStorage($stored['content'], $stored['content_iv']);

        $this->assertEquals($original, $decrypted);
    }

    public function test_decrypt_from_storage_handles_null_iv(): void
    {
        $content = 'Unencrypted content';

        $result = $this->service->decryptFromStorage($content, null);

        $this->assertEquals($content, $result);
    }
}
