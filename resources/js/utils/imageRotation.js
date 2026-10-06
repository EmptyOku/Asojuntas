// Girar una foto recién tomada (plancha o acta) antes de enviarla.
//
// El giro se aplica al ARCHIVO, no solo a la vista: se redibuja en un lienzo y
// se genera un JPEG nuevo. Así la extracción de datos lee la hoja derecha y la
// evidencia queda guardada derecha.

const loadBitmap = async (file) => {
  if ('createImageBitmap' in window) {
    try {
      // Respeta la orientación que la cámara guardó en la foto.
      return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
      // Navegadores que no aceptan la opción: se cae al método clásico.
    }
  }

  const url = URL.createObjectURL(file);
  try {
    return await new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = () => reject(new Error('No se pudo leer la imagen.'));
      image.src = url;
    });
  } finally {
    URL.revokeObjectURL(url);
  }
};

/** Devuelve un archivo nuevo con la imagen girada 90° (a la derecha por defecto). */
export async function rotateImageFile(file, degrees = 90) {
  const source = await loadBitmap(file);
  const quarterTurns = (((Math.round(degrees / 90) % 4) + 4) % 4);
  const swap = quarterTurns % 2 === 1;

  const canvas = document.createElement('canvas');
  canvas.width = swap ? source.height : source.width;
  canvas.height = swap ? source.width : source.height;

  const context = canvas.getContext('2d');
  context.translate(canvas.width / 2, canvas.height / 2);
  context.rotate((quarterTurns * Math.PI) / 2);
  context.drawImage(source, -source.width / 2, -source.height / 2);
  source.close?.();

  const blob = await new Promise((resolve, reject) => {
    canvas.toBlob((result) => (result ? resolve(result) : reject(new Error('No se pudo girar la imagen.'))), 'image/jpeg', 0.92);
  });

  const name = String(file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
  return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}

/**
 * Gira una de las fotos capturadas ({ id, file, url }) y devuelve la lista
 * nueva, liberando la vista previa anterior.
 */
export async function rotateCapturedImage(images, id, degrees = 90) {
  const target = images.find((image) => image.id === id);
  if (!target?.file) return images;

  const file = await rotateImageFile(target.file, degrees);
  URL.revokeObjectURL(target.url);

  return images.map((image) => (image.id === id ? { ...image, file, url: URL.createObjectURL(file) } : image));
}
