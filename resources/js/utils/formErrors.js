// Convierte el `errors` de una respuesta 422 de Laravel ({campo: [mensajes]}) en un mapa plano
// {campo: primerMensaje}, listo para resaltar el input y mostrar la razón debajo.
export const extractFieldErrors = (error) => {
  const errors = error?.response?.data?.errors;
  if (!errors) return {};
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages])
  );
};

// Arma el mensaje para el modal. Prioriza los errores por campo (ya vienen en español, uno por
// campo) en vez del `message` genérico de Laravel: con varios campos inválidos a la vez, Laravel
// arma ese resumen agregando " (and N more errors)" en inglés al final, que no queremos mostrar.
export const buildErrorMessage = (error, fallback) => {
  const fieldErrors = extractFieldErrors(error);
  const fieldMessages = Object.values(fieldErrors);
  if (fieldMessages.length > 0) return fieldMessages.join(' ');
  return error?.response?.data?.message || fallback;
};
