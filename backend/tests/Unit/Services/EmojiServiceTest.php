<?php

namespace Tests\Unit\Services;

use App\Services\EmojiService;
use Tests\TestCase;

class EmojiServiceTest extends TestCase
{
    private EmojiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmojiService::class);
    }

    public function test_converts_shortcodes_to_emojis(): void
    {
        $text = 'Hello :smile: how are you :thumbsup:';
        $result = $this->service->convertShortcodes($text);

        $this->assertEquals('Hello 😊 how are you 👍', $result);
    }

    public function test_leaves_unknown_shortcodes_unchanged(): void
    {
        $text = 'This is :unknown: shortcode';
        $result = $this->service->convertShortcodes($text);

        $this->assertEquals('This is :unknown: shortcode', $result);
    }

    public function test_converts_multiple_same_shortcodes(): void
    {
        $text = ':heart: :heart: :heart:';
        $result = $this->service->convertShortcodes($text);

        $this->assertEquals('❤️ ❤️ ❤️', $result);
    }

    public function test_is_valid_emoji_returns_true_for_unicode(): void
    {
        $this->assertTrue($this->service->isValidEmoji('😊'));
        $this->assertTrue($this->service->isValidEmoji('👍'));
        $this->assertTrue($this->service->isValidEmoji('❤️'));
    }

    public function test_is_valid_emoji_returns_false_for_text(): void
    {
        $this->assertFalse($this->service->isValidEmoji('hello'));
        $this->assertFalse($this->service->isValidEmoji('123'));
    }

    public function test_get_available_emojis(): void
    {
        $emojis = $this->service->getAvailableEmojis();

        $this->assertContains('😊', $emojis);
        $this->assertContains('👍', $emojis);
        $this->assertContains('❤️', $emojis);
    }
}
