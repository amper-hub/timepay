const MAX_FIELD_LENGTH = 120;

const sanitizeField = (value) => {
  if (typeof value !== "string" && typeof value !== "number") return undefined;

  const sanitized = String(value)
    .replace(/\bBearer\s+[^\s,;]+/gi, "Bearer [redacted]")
    .replace(/\b(token|authorization|password|secret|api[_-]?key)\s*[:=]\s*[^\s,;&]+/gi, "$1=[redacted]")
    .replace(/(?:file|https?):\/\/[^\s,;]+/gi, "[redacted-uri]")
    .replace(/(?:[A-Za-z]:\\|\\\\)[^\s,;]+/g, "[redacted-path]")
    .replace(/\/(?:data|storage|private|var|tmp)\/[^\s,;]+/gi, "[redacted-path]")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, MAX_FIELD_LENGTH);

  return sanitized || undefined;
};

export const getSafeErrorDetails = (error) => {
  const source = error && typeof error === "object" ? error : {};
  const details = {
    name: sanitizeField(source.name),
    code: sanitizeField(source.code),
    message: sanitizeField(source.message),
  };

  return Object.fromEntries(
    Object.entries(details).filter(([, value]) => value !== undefined)
  );
};
