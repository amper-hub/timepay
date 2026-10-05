const messages = {
    network: 'Unable to connect to TimePay. Please check your internet connection and try again.',
    server: 'Something went wrong on our end. Please try again later.',
    unavailable: 'This service is temporarily unavailable. Please try again shortly.',
    login: 'The email or password you entered is incorrect.',
    unauthorized: 'Your session has expired. Please sign in again.',
    forbidden: "You don't have permission to perform this action.",
    notFound: "We couldn't find what you're looking for.",
    validation: 'Please check the information you entered and try again.',
    timeout: 'The request took too long. Please try again.',
    unknown: 'Something went wrong. Please try again.',
};

const technicalContent = /SQLSTATE|SQL syntax|PDOException|Illuminate\\|Symfony\\|\b\w+Exception\b|\bER_[A-Z0-9_]+\b|\b(?:MySQL|MariaDB|PostgreSQL|SQLite)\b|\b(?:select\s+.+\s+from|insert\s+into|update\s+\w+\s+set|delete\s+from|alter\s+table|drop\s+table)\b|\b(?:unknown column|no such (?:table|column)|duplicate entry|connection refused|could not connect to (?:database|host))\b|AxiosError|Network Error|HTTP\s*[45]\d{2}|stack trace|traceback|https?:\/\/|file:\/\/|(?:[A-Za-z]:[\\/]|\/(?:var|home|srv|opt|app|vendor|Users)\/)|\b(?:\d{1,3}\.){3}\d{1,3}\b|\b(?:localhost|[\w.-]+\.(?:internal|local|lan))(?::\d{2,5})?\b|\b(?:[A-Z][A-Z0-9_]*(?:PASSWORD|SECRET|TOKEN|API_KEY|APP_KEY|DB_HOST))\s*=|\b(?:api[_ -]?key|token|password|secret|credential|authorization)\s*[:=]|\bBearer\s+[A-Za-z0-9._~-]{12,}\b|\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b/i;

const isSafeMessage = (value) => {
    if (typeof value !== 'string') return false;

    const message = value.trim();
    return Boolean(
        message &&
        message.length <= 240 &&
        !/[\r\n]/.test(message) &&
        !message.startsWith('{') &&
        !message.startsWith('[') &&
        !/<\/?[a-z][^>]*>/i.test(message) &&
        !technicalContent.test(message)
    );
};

const firstSafePayloadMessage = (payload) => {
    if (!payload || typeof payload !== 'object') return null;

    const fields = payload.errors;
    if (fields && typeof fields === 'object') {
        for (const value of Object.values(fields)) {
            const candidates = Array.isArray(value) ? value : [value];
            const safeMessage = candidates.find(isSafeMessage);
            if (safeMessage) return safeMessage.trim();
        }
    }

    return isSafeMessage(payload.message) ? payload.message.trim() : null;
};

const contextFallback = (context) => {
    if (context === 'login') return 'We could not sign you in. Please try again.';
    if (context === 'employee') return 'Unable to create employee. Please try again.';
    return messages.unknown;
};

const fromResponse = (response, payload, context = 'general', fallback) => {
    const status = response?.status;

    if (status === 503) return messages.unavailable;
    if (status >= 500) return messages.server;
    if (status === 408) return messages.timeout;
    if (status === 401) return context === 'login' ? messages.login : messages.unauthorized;
    if (status === 403) return messages.forbidden;
    if (status === 404) return messages.notFound;
    if (status === 429) return 'Too many attempts. Please wait a moment and try again.';
    if (status === 400 || status === 422 || status === 409) {
        return firstSafePayloadMessage(payload) || messages.validation;
    }

    return firstSafePayloadMessage(payload) || fallback || contextFallback(context);
};

const validationErrors = (errors) => {
    if (!errors || typeof errors !== 'object') return {};

    return Object.fromEntries(
        Object.entries(errors)
            .filter(([field]) => /^[A-Za-z][A-Za-z0-9_.]*$/.test(field))
            .map(([field, value]) => {
                const candidates = Array.isArray(value) ? value : [value];
                const safeMessages = candidates.filter(isSafeMessage).map((message) => message.trim());

                return [field, safeMessages.length ? safeMessages : ['Please check this field and try again.']];
            })
    );
};

export const timePayErrorMessages = {
    fromNetwork: () => messages.network,
    fromResponse,
    validationErrors,
};
