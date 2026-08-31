(function installRequestSecurity() {
  "use strict";

  function readCSRFToken() {
    const localToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (localToken) return localToken;

    try {
      return (
        window.parent.document.querySelector('meta[name="csrf-token"]')
          ?.content || ""
      );
    } catch (_error) {
      return "";
    }
  }

  window.getCSRFToken = readCSRFToken;

  if (window.__greenTraceSecureFetchInstalled) return;
  window.__greenTraceSecureFetchInstalled = true;

  const originalFetch = window.fetch.bind(window);

  window.fetch = function secureFetch(resource, options = {}) {
    const request = resource instanceof Request ? resource : null;
    const method = String(options.method || request?.method || "GET").toUpperCase();
    const safeMethod = ["GET", "HEAD", "OPTIONS"].includes(method);
    const requestUrl = new URL(request?.url || String(resource), window.location.href);

    if (safeMethod || requestUrl.origin !== window.location.origin) {
      return originalFetch(resource, options);
    }

    const headers = new Headers(request?.headers || undefined);
    new Headers(options.headers || undefined).forEach((value, name) => {
      headers.set(name, value);
    });

    const token = readCSRFToken();
    if (token && !headers.has("X-CSRF-Token")) {
      headers.set("X-CSRF-Token", token);
    }

    return originalFetch(resource, {
      ...options,
      headers,
      credentials: options.credentials || request?.credentials || "same-origin",
    });
  };
})();
