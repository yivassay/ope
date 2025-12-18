<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class Telegram
{
    public function __construct(private string $botToken)
    {
        $this->botToken = trim($this->botToken);
        if ($this->botToken === '') {
            throw new RuntimeException('Telegram bot token is empty');
        }
    }

    public function sendMessage(string $chatId, string $text): void
    {
        $chatId = trim($chatId);
        if ($chatId === '') {
            throw new RuntimeException('Telegram chat_id is empty');
        }
        $url = 'https://api.telegram.org/bot' . $this->botToken . '/sendMessage';

        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 1,
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Telegram request failed: ' . $err);
        }
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($raw, true);
        if ($code >= 400 || !is_array($data) || ($data['ok'] ?? false) !== true) {
            throw new RuntimeException('Telegram API error: HTTP ' . $code . ' ' . substr($raw, 0, 400));
        }
    }
}

