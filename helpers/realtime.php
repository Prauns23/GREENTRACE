<?php

/**
 * Notify the local GreenTrace WebSocket service that a conversation changed.
 * This is intentionally best-effort: normal HTTP requests must still succeed
 * when the optional WebSocket process is offline.
 */
function publishConversationRealtimeEvent(
    int $conversationId,
    string $reason,
    array $payload = []
): bool {
    if ($conversationId <= 0) {
        return false;
    }

    $eventPort = (int) (getenv('GREENTRACE_WS_EVENT_PORT') ?: 8081);
    $event = json_encode([
        'type' => 'conversation.updated',
        'conversation_id' => $conversationId,
        'reason' => $reason,
        'payload' => $payload,
        'sent_at' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);

    if ($event === false) {
        return false;
    }

    $errorNumber = 0;
    $errorMessage = '';
    $socket = @stream_socket_client(
        "udp://127.0.0.1:{$eventPort}",
        $errorNumber,
        $errorMessage,
        0.2,
        STREAM_CLIENT_CONNECT
    );

    if ($socket === false) {
        return false;
    }

    $written = @fwrite($socket, $event);
    fclose($socket);

    return $written === strlen($event);
}

