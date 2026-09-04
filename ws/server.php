<?php

declare(strict_types=1);

set_time_limit(0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

$webSocketHost = getenv('GREENTRACE_WS_HOST') ?: '127.0.0.1';
$webSocketPort = (int) (getenv('GREENTRACE_WS_PORT') ?: 8080);
$eventPort = (int) (getenv('GREENTRACE_WS_EVENT_PORT') ?: 8081);
$maxFrameBytes = 65536;

$errorNumber = 0;
$errorMessage = '';
$webSocketServer = stream_socket_server(
    "tcp://{$webSocketHost}:{$webSocketPort}",
    $errorNumber,
    $errorMessage,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);

if ($webSocketServer === false) {
    fwrite(STDERR, "WebSocket server failed: {$errorMessage} ({$errorNumber})\n");
    exit(1);
}

$eventServer = stream_socket_server(
    "udp://127.0.0.1:{$eventPort}",
    $errorNumber,
    $errorMessage,
    STREAM_SERVER_BIND
);

if ($eventServer === false) {
    fclose($webSocketServer);
    fwrite(STDERR, "Event listener failed: {$errorMessage} ({$errorNumber})\n");
    exit(1);
}

stream_set_blocking($webSocketServer, false);
stream_set_blocking($eventServer, false);

/** @var array<int, array{stream: resource, handshaken: bool, buffer: string, user_id: ?int, conversation_id: ?int}> $clients */
$clients = [];

fwrite(
    STDERR,
    "GreenTrace WebSocket server listening on ws://{$webSocketHost}:{$webSocketPort} " .
    "(events: udp://127.0.0.1:{$eventPort})\n"
);

function closeClient(array &$clients, int $clientId): void
{
    if (!isset($clients[$clientId])) {
        return;
    }

    $stream = $clients[$clientId]['stream'];
    if (is_resource($stream)) {
        @fclose($stream);
    }
    unset($clients[$clientId]);
}

function parseHttpHeaders(string $request): array
{
    $headers = [];
    foreach (preg_split('/\r\n/', $request) ?: [] as $index => $line) {
        if ($index === 0) {
            $headers['request-line'] = $line;
            continue;
        }

        $separator = strpos($line, ':');
        if ($separator === false) {
            continue;
        }

        $name = strtolower(trim(substr($line, 0, $separator)));
        $headers[$name] = trim(substr($line, $separator + 1));
    }
    return $headers;
}

function originMatchesHost(array $headers): bool
{
    $origin = $headers['origin'] ?? '';
    $hostHeader = $headers['host'] ?? '';
    if ($origin === '' || $hostHeader === '') {
        return false;
    }

    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $requestHost = strtolower(preg_replace('/:\d+$/', '', $hostHeader) ?? '');
    return $originHost !== '' && hash_equals($requestHost, $originHost);
}

function userIdFromSessionCookie(string $cookieHeader): ?int
{
    $cookies = [];
    foreach (explode(';', $cookieHeader) as $cookie) {
        $parts = explode('=', trim($cookie), 2);
        if (count($parts) === 2) {
            $cookies[$parts[0]] = urldecode($parts[1]);
        }
    }

    $sessionName = (string) ini_get('session.name');
    $sessionId = $cookies[$sessionName] ?? '';
    if ($sessionId === '' || !preg_match('/^[A-Za-z0-9,-]{1,128}$/', $sessionId)) {
        return null;
    }

    $savePath = (string) ini_get('session.save_path');
    if (str_contains($savePath, ';')) {
        $savePath = (string) substr(strrchr($savePath, ';'), 1);
    }
    $sessionFile = rtrim($savePath, '\\/') . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
    $sessionData = @file_get_contents($sessionFile);
    if (!is_string($sessionData) || $sessionData === '') {
        return null;
    }

    $userId = 0;
    if (ini_get('session.serialize_handler') === 'php_serialize') {
        $sessionValues = @unserialize($sessionData, ['allowed_classes' => false]);
        $userId = is_array($sessionValues)
            ? (int) ($sessionValues['user_id'] ?? 0)
            : 0;
    } elseif (
        preg_match('/(?:^|;)user_id\|i:(\d+);/', $sessionData, $matches) ||
        preg_match('/(?:^|;)user_id\|s:\d+:"(\d+)";/', $sessionData, $matches)
    ) {
        $userId = (int) $matches[1];
    }

    return $userId > 0 ? $userId : null;
}

function sendHttpError($stream, int $status, string $message): void
{
    $body = $message . "\n";
    $response = "HTTP/1.1 {$status} {$message}\r\n" .
        "Connection: close\r\n" .
        "Content-Type: text/plain; charset=utf-8\r\n" .
        'Content-Length: ' . strlen($body) . "\r\n\r\n" .
        $body;
    @fwrite($stream, $response);
}

function performHandshake($stream, string $request): ?int
{
    $headers = parseHttpHeaders($request);
    $key = $headers['sec-websocket-key'] ?? '';
    $upgrade = strtolower($headers['upgrade'] ?? '');

    if ($upgrade !== 'websocket' || $key === '') {
        sendHttpError($stream, 400, 'Bad Request');
        return null;
    }

    if (!originMatchesHost($headers)) {
        sendHttpError($stream, 403, 'Forbidden');
        return null;
    }

    $userId = userIdFromSessionCookie($headers['cookie'] ?? '');
    if ($userId === null) {
        sendHttpError($stream, 401, 'Unauthorized');
        return null;
    }

    $accept = base64_encode(
        sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
    );
    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
    @fwrite($stream, $response);

    return $userId;
}

function encodeFrame(string $payload, int $opcode = 0x1): string
{
    $length = strlen($payload);
    $header = chr(0x80 | $opcode);

    if ($length <= 125) {
        return $header . chr($length) . $payload;
    }
    if ($length <= 65535) {
        return $header . chr(126) . pack('n', $length) . $payload;
    }

    return $header . chr(127) . pack('NN', 0, $length) . $payload;
}

function sendJson($stream, array $message): bool
{
    $payload = json_encode($message, JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }
    return @fwrite($stream, encodeFrame($payload)) !== false;
}

function decodeNextFrame(string &$buffer, int $maxFrameBytes): ?array
{
    if (strlen($buffer) < 2) {
        return null;
    }

    $first = ord($buffer[0]);
    $second = ord($buffer[1]);
    $finished = ($first & 0x80) !== 0;
    $opcode = $first & 0x0f;
    $masked = ($second & 0x80) !== 0;
    $length = $second & 0x7f;
    $offset = 2;

    if (!$finished || !$masked) {
        return ['type' => 'invalid'];
    }

    if ($length === 126) {
        if (strlen($buffer) < 4) {
            return null;
        }
        $length = unpack('nlength', substr($buffer, 2, 2))['length'];
        $offset = 4;
    } elseif ($length === 127) {
        if (strlen($buffer) < 10) {
            return null;
        }
        $parts = unpack('Nhigh/Nlow', substr($buffer, 2, 8));
        if ($parts['high'] !== 0) {
            return ['type' => 'invalid'];
        }
        $length = $parts['low'];
        $offset = 10;
    }

    if ($length > $maxFrameBytes) {
        return ['type' => 'invalid'];
    }

    $required = $offset + 4 + $length;
    if (strlen($buffer) < $required) {
        return null;
    }

    $mask = substr($buffer, $offset, 4);
    $payload = substr($buffer, $offset + 4, $length);
    $decoded = '';
    for ($index = 0; $index < $length; $index++) {
        $decoded .= $payload[$index] ^ $mask[$index % 4];
    }

    $buffer = (string) substr($buffer, $required);

    return match ($opcode) {
        0x1 => ['type' => 'text', 'payload' => $decoded],
        0x8 => ['type' => 'close'],
        0x9 => ['type' => 'ping', 'payload' => $decoded],
        0xA => ['type' => 'pong'],
        default => ['type' => 'invalid'],
    };
}

function isConversationMember(mysqli $conn, int $conversationId, int $userId): bool
{
    $statement = $conn->prepare(
        'SELECT id FROM chat_conversation_members ' .
        'WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL LIMIT 1'
    );
    if ($statement === false) {
        return false;
    }

    $statement->bind_param('ii', $conversationId, $userId);
    $statement->execute();
    $isMember = $statement->get_result()->num_rows > 0;
    $statement->close();
    return $isMember;
}

function activeConversationMemberIds(mysqli $conn, int $conversationId): array
{
    $statement = $conn->prepare(
        'SELECT user_id FROM chat_conversation_members ' .
        'WHERE conversation_id = ? AND left_at IS NULL'
    );
    if ($statement === false) {
        return [];
    }

    $statement->bind_param('i', $conversationId);
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return array_map('intval', array_column($rows, 'user_id'));
}

while (true) {
    $readStreams = [$webSocketServer, $eventServer];
    foreach ($clients as $client) {
        $readStreams[] = $client['stream'];
    }

    $writeStreams = null;
    $exceptStreams = null;
    $selected = @stream_select($readStreams, $writeStreams, $exceptStreams, 1);
    if ($selected === false) {
        usleep(100000);
        continue;
    }

    foreach ($readStreams as $stream) {
        if ($stream === $webSocketServer) {
            $clientStream = @stream_socket_accept($webSocketServer, 0);
            if ($clientStream !== false) {
                stream_set_blocking($clientStream, false);
                $clientId = (int) $clientStream;
                $clients[$clientId] = [
                    'stream' => $clientStream,
                    'handshaken' => false,
                    'buffer' => '',
                    'user_id' => null,
                    'conversation_id' => null,
                ];
            }
            continue;
        }

        if ($stream === $eventServer) {
            $peer = '';
            $packet = @stream_socket_recvfrom($eventServer, 65535, 0, $peer);
            if (!is_string($packet) || $packet === '') {
                continue;
            }

            $event = json_decode($packet, true);
            $conversationId = (int) ($event['conversation_id'] ?? 0);
            if (($event['type'] ?? '') !== 'conversation.updated' || $conversationId <= 0) {
                continue;
            }

            $memberIds = array_flip(activeConversationMemberIds($conn, $conversationId));
            foreach ($clients as $clientId => $client) {
                $userId = (int) ($client['user_id'] ?? 0);
                if (!$client['handshaken'] || !isset($memberIds[$userId])) {
                    continue;
                }

                $message = $client['conversation_id'] === $conversationId
                    ? $event
                    : [
                        'type' => 'sidebar.updated',
                        'reason' => $event['reason'] ?? 'conversation.updated',
                    ];
                if (!sendJson($client['stream'], $message)) {
                    closeClient($clients, $clientId);
                }
            }
            continue;
        }

        $clientId = (int) $stream;
        if (!isset($clients[$clientId])) {
            continue;
        }

        $chunk = @fread($stream, 8192);
        if ($chunk === '' || $chunk === false) {
            if (feof($stream)) {
                closeClient($clients, $clientId);
            }
            continue;
        }
        $clients[$clientId]['buffer'] .= $chunk;

        if (!$clients[$clientId]['handshaken']) {
            $headerEnd = strpos($clients[$clientId]['buffer'], "\r\n\r\n");
            if ($headerEnd === false) {
                if (strlen($clients[$clientId]['buffer']) > 16384) {
                    closeClient($clients, $clientId);
                }
                continue;
            }

            $request = substr($clients[$clientId]['buffer'], 0, $headerEnd + 4);
            $clients[$clientId]['buffer'] = (string) substr(
                $clients[$clientId]['buffer'],
                $headerEnd + 4
            );
            $userId = performHandshake($stream, $request);
            if ($userId === null) {
                closeClient($clients, $clientId);
                continue;
            }

            $clients[$clientId]['handshaken'] = true;
            $clients[$clientId]['user_id'] = $userId;
            sendJson($stream, ['type' => 'ready']);
        }

        while (isset($clients[$clientId])) {
            $frame = decodeNextFrame($clients[$clientId]['buffer'], $maxFrameBytes);
            if ($frame === null) {
                break;
            }

            if ($frame['type'] === 'close' || $frame['type'] === 'invalid') {
                @fwrite($stream, encodeFrame('', 0x8));
                closeClient($clients, $clientId);
                break;
            }
            if ($frame['type'] === 'ping') {
                @fwrite($stream, encodeFrame($frame['payload'] ?? '', 0xA));
                continue;
            }
            if ($frame['type'] !== 'text') {
                continue;
            }

            $message = json_decode($frame['payload'], true);
            $messageType = $message['type'] ?? '';
            if ($messageType === 'ping') {
                sendJson($stream, ['type' => 'pong']);
                continue;
            }
            if ($messageType !== 'subscribe') {
                continue;
            }

            $conversationId = (int) ($message['conversation_id'] ?? 0);
            $userId = (int) $clients[$clientId]['user_id'];
            if (
                $conversationId > 0 &&
                isConversationMember($conn, $conversationId, $userId)
            ) {
                $clients[$clientId]['conversation_id'] = $conversationId;
                sendJson($stream, [
                    'type' => 'subscribed',
                    'conversation_id' => $conversationId,
                ]);
            } else {
                $clients[$clientId]['conversation_id'] = null;
                sendJson($stream, [
                    'type' => 'subscription.denied',
                    'conversation_id' => $conversationId,
                ]);
            }
        }
    }
}
