// Candidatos que el OCR no pudo leer (ver app/Support/UnknownCandidate.php).
//
// El modelo de IA escribe "<Unknown>" cuando un nombre es ilegible. En pantalla
// y al guardar se usa "<DESCONOCIDO>"; si el documento viene vacío, el servidor
// asigna uno provisional único de 11 dígitos (00000000001, …).

export const UNKNOWN_NAME = '<DESCONOCIDO>';

export const normalizeUnknownName = (name) => String(name ?? '')
  .replace(/<\s*unknown\s*>|\bunknown\b/gi, UNKNOWN_NAME)
  .replace(/\s+/g, ' ')
  .trim();

export const isUnknownName = (name) => /<\s*(desconocido|unknown)\s*>/i.test(String(name ?? ''));

export const isPlaceholderDocument = (document) => /^0{6}\d{5}$/.test(String(document ?? ''));

// "ANA MARIA PEREZ" -> "Ana Maria Perez"; "<DESCONOCIDO> SIN_APELLIDO" -> "<Desconocido> Sin apellido".
export const formatPersonName = (name) => {
  const text = String(name ?? '').replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
  if (!text) return 'Sin nombre';
  return text.toLowerCase().replace(/(^|[\s<(])([a-záéíóúñü])/g, (_, lead, char) => lead + char.toUpperCase());
};
