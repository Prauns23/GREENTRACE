# GreenTrace WebSocket service

The chat page connects to a separate WebSocket process on port `8080`. PHP
actions publish small refresh events to a loopback-only UDP listener on port
`8081`. Message data continues to come from the authenticated HTTP endpoints;
the socket only tells the browser when it should refresh.

Start the service from the project root:

```powershell
C:\xampp\php\php.exe ws\server.php
```

Optional environment variables:

- `GREENTRACE_WS_HOST` — listening interface; defaults to `127.0.0.1`.
- `GREENTRACE_WS_PORT` — browser WebSocket port; defaults to `8080`.
- `GREENTRACE_WS_EVENT_PORT` — loopback event port; defaults to `8081`.

When the process is unavailable, the chat automatically falls back to its
existing polling intervals.

