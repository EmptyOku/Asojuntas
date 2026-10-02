// Exportación de los resultados de un barrio a PDF y Excel.
//
// Las librerías (jsPDF y ExcelJS) pesan cerca de 1 MB: se cargan con import()
// solo cuando alguien pulsa el botón, así no hacen más lenta la carga de la app.
//
// Los reportes llevan nombre e identificación de los dignatarios, pero no
// celular ni correo: son documentos que se imprimen y circulan.

// Color estable por plancha: el mismo en pantalla, PDF y Excel.
// Colores del escudo primero (verde, azul, amarillo, rojo) y luego neutros de apoyo.
export const PLANCHA_COLORS = ['#45821f', '#3576d1', '#dcae0c', '#d0141d', '#7b5ea7', '#1f8a8a'];

export const planchaColor = (name) => {
  const match = String(name ?? '').match(/(\d+)/);
  const index = match ? Number(match[1]) - 1 : 0;
  return PLANCHA_COLORS[((index % PLANCHA_COLORS.length) + PLANCHA_COLORS.length) % PLANCHA_COLORS.length];
};

// "COMISIÓN DE CONVIVENCIA..." en mayúsculas desde el OCR: se muestra en formato título.
export const blockTitle = (bloque) => {
  const name = String(bloque?.nombre_bloque ?? 'Bloque').toLowerCase();
  return name.charAt(0).toUpperCase() + name.slice(1);
};

// Con empate en curules, la plancha con más votos es la que provee la
// presidencia y los primeros cargos: se dice así en vez de solo "Empate".
export const winnerInfo = (bloque) => {
  if (bloque?.plancha_ganadora?.plancha) {
    return { label: bloque.plancha_ganadora.plancha, tie: false };
  }

  const tied = Array.isArray(bloque?.planchas_ganadoras) ? bloque.planchas_ganadoras : [];
  if (tied.length > 1) {
    const top = [...tied].sort((a, b) => (b.votos ?? 0) - (a.votos ?? 0))[0];
    return { label: `${top.plancha} · empate en curules`, tie: true };
  }

  return { label: 'Sin resultado', tie: true };
};

const share = (votos, total) => (total ? Number(votos || 0) / Number(total) : 0);

const formatNumber = (value, digits = 0) => Number(value || 0)
  .toLocaleString('es-CO', { minimumFractionDigits: digits, maximumFractionDigits: digits });

const slug = (text) => String(text || 'barrio')
  .normalize('NFD')
  .replace(/[̀-ͯ]/g, '')
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '') || 'barrio';

const stamp = () => {
  const now = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return {
    file: `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`,
    human: now.toLocaleString('es-CO', { dateStyle: 'long', timeStyle: 'short' }),
  };
};

const fileName = (barrio, extension) => `resultados-${slug(barrio?.name)}-${stamp().file}.${extension}`;

const hexToRgb = (hex) => {
  const value = String(hex).replace('#', '');
  return [0, 2, 4].map((i) => parseInt(value.slice(i, i + 2), 16));
};

// Una fila por cargo, lista para cualquiera de los dos formatos.
const dignatarioRows = (bloque) => (bloque.cargos ?? []).map((item, index) => ({
  numero: index + 1,
  cargo: item.cargo,
  plancha: item.plancha,
  nombre: item.sin_candidato ? 'Sin candidato inscrito' : (item.persona?.nombre ?? ''),
  identificacion: item.sin_candidato ? '' : (item.persona?.identificacion ?? ''),
  suplente: item.suplente || '',
  sinCandidato: Boolean(item.sin_candidato),
}));

// Votos válidos del barrio: cada persona vota en todos los bloques, así que
// se toma el total de un bloque (el mayor) en vez de sumarlos.
const summary = (barrio) => {
  const blocks = barrio?.resultados ?? [];
  return {
    votosValidos: blocks.reduce((max, b) => Math.max(max, Number(b.estadisticas?.validos ?? 0)), 0),
    bloques: blocks.length,
    cargos: blocks.reduce((sum, b) => sum + (b.cargos_a_proveer ?? 0), 0),
    ganadora: barrio?.plancha_ganadora?.plancha || 'Sin resultado',
  };
};

const BRAND = {
  deep: '#1a350b',
  primary: '#45821f',
  yellow: '#f2c81e',
  red: '#d0141d',
  blue: '#6fb0e8',
  gray: '#6b7280',
  light: '#f3f4f6',
  amberBg: '#fef6dc',
  amberText: '#8a6508',
};

// ---------------------------------------------------------------------------
// PDF
// ---------------------------------------------------------------------------

export async function exportResultsPdf(barrio) {
  const [{ jsPDF }, { autoTable }] = await Promise.all([import('jspdf'), import('jspdf-autotable')]);

  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const pageWidth = doc.internal.pageSize.getWidth();
  const pageHeight = doc.internal.pageSize.getHeight();
  const margin = 14;
  const generated = stamp().human;
  const info = summary(barrio);

  // Encabezado: banda verde oscura con la franja de colores del escudo.
  doc.setFillColor(...hexToRgb(BRAND.deep));
  doc.rect(0, 0, pageWidth, 30, 'F');
  [BRAND.primary, BRAND.yellow, BRAND.red, BRAND.blue].forEach((color, i) => {
    doc.setFillColor(...hexToRgb(color));
    doc.rect((pageWidth / 4) * i, 30, pageWidth / 4, 1.6, 'F');
  });

  doc.setTextColor(...hexToRgb(BRAND.yellow));
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(9);
  doc.text('ASOJUNTAS GIRARDOT', margin, 11);
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(18);
  doc.text('Resultados del escrutinio', margin, 20);
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.text(`Generado el ${generated}`, margin, 26);

  // Datos del barrio.
  let y = 42;
  doc.setTextColor(17, 24, 39);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(15);
  doc.text(barrio?.name || 'Barrio', margin, y);
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9.5);
  doc.setTextColor(...hexToRgb(BRAND.gray));
  const details = [barrio?.comuna && `Comuna: ${barrio.comuna}`, barrio?.eleccion && `Elección: ${barrio.eleccion}`].filter(Boolean);
  if (details.length) {
    y += 5.5;
    doc.text(details.join('   ·   '), margin, y);
  }

  // Resumen en cuatro recuadros.
  y += 6;
  const boxes = [
    ['Votos válidos', formatNumber(info.votosValidos)],
    ['Bloques', String(info.bloques)],
    ['Cargos a proveer', String(info.cargos)],
    ['Mayor votación', info.ganadora],
  ];
  const gap = 3;
  const boxWidth = (pageWidth - margin * 2 - gap * 3) / 4;
  boxes.forEach(([label, value], i) => {
    const x = margin + i * (boxWidth + gap);
    doc.setFillColor(...hexToRgb(BRAND.light));
    doc.roundedRect(x, y, boxWidth, 16, 2, 2, 'F');
    doc.setFontSize(7.5);
    doc.setTextColor(...hexToRgb(BRAND.gray));
    doc.text(label.toUpperCase(), x + 3, y + 5.5);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(12);
    doc.setTextColor(17, 24, 39);
    doc.text(doc.splitTextToSize(value, boxWidth - 6)[0], x + 3, y + 12.5);
    doc.setFont('helvetica', 'normal');
  });
  y += 24;

  const tableTheme = {
    theme: 'grid',
    margin: { left: margin, right: margin, top: 18, bottom: 16 },
    styles: { font: 'helvetica', fontSize: 9, cellPadding: 2.2, lineColor: [229, 231, 235], lineWidth: 0.2, textColor: [31, 41, 55] },
    headStyles: { fillColor: hexToRgb(BRAND.primary), textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [249, 250, 251] },
  };

  const ensureSpace = (needed) => {
    if (y + needed > pageHeight - 20) {
      doc.addPage();
      y = 20;
    }
  };

  (barrio?.resultados ?? []).forEach((bloque) => {
    const total = bloque.estadisticas?.total ?? 0;
    const winner = winnerInfo(bloque);

    // Título del bloque con barra lateral verde.
    ensureSpace(40);
    doc.setFillColor(...hexToRgb(BRAND.primary));
    doc.rect(margin, y - 4.5, 1.4, 6, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(12.5);
    doc.setTextColor(17, 24, 39);
    doc.text(blockTitle(bloque), margin + 4, y);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(...hexToRgb(BRAND.gray));
    const cargos = bloque.cargos_a_proveer ?? 0;
    doc.text(
      `Cuociente ${formatNumber(bloque.cuociente_electoral, 2)}  ·  ${cargos} ${cargos === 1 ? 'cargo' : 'cargos'}  ·  Mayor votación: ${winner.label}`,
      margin + 4,
      y + 5,
    );
    y += 9;

    // Votación por plancha.
    const planchas = bloque.votos_planchas ?? [];
    autoTable(doc, {
      ...tableTheme,
      startY: y,
      head: [['Plancha', 'Votos', '%', 'Entero', 'Residuo', 'Curules']],
      body: planchas.map((p) => [
        p.plancha,
        formatNumber(p.votos),
        `${Math.round(share(p.votos, total) * 100)}%`,
        String(p.entero ?? 0),
        formatNumber(p.residuo, 3),
        String(p.curules ?? 0),
      ]),
      foot: [[
        { content: `Válidos ${formatNumber(bloque.estadisticas?.validos)}   ·   Blancos ${formatNumber(bloque.estadisticas?.blancos)}   ·   Nulos ${formatNumber(bloque.estadisticas?.nulos)}`, colSpan: 6 },
      ]],
      footStyles: { fillColor: [243, 244, 246], textColor: [55, 65, 81], fontStyle: 'normal', fontSize: 8.5 },
      columnStyles: {
        0: { cellWidth: 'auto', fontStyle: 'bold', cellPadding: { left: 7, top: 2.2, bottom: 2.2, right: 2.2 } },
        1: { halign: 'right' },
        2: { halign: 'right' },
        3: { halign: 'right' },
        4: { halign: 'right' },
        5: { halign: 'right', fontStyle: 'bold' },
      },
      didDrawCell: (data) => {
        // Punto de color de la plancha, igual que en pantalla.
        if (data.section === 'body' && data.column.index === 0) {
          doc.setFillColor(...hexToRgb(planchaColor(data.cell.raw)));
          doc.circle(data.cell.x + 3.5, data.cell.y + data.cell.height / 2, 1.3, 'F');
        }
      },
    });
    y = doc.lastAutoTable.finalY + 5;

    // Dignatarios electos.
    const rows = dignatarioRows(bloque);
    if (rows.length) {
      ensureSpace(20);
      autoTable(doc, {
        ...tableTheme,
        startY: y,
        head: [['#', 'Cargo', 'Plancha', 'Nombre', 'Identificación', 'Suplente']],
        body: rows.map((r) => [r.numero, r.cargo, r.plancha, r.nombre, r.identificacion, r.suplente]),
        headStyles: { ...tableTheme.headStyles, fillColor: hexToRgb(BRAND.deep) },
        columnStyles: {
          0: { halign: 'center', cellWidth: 8 },
          1: { fontStyle: 'bold' },
          4: { cellWidth: 26 },
        },
        didParseCell: (data) => {
          if (data.section === 'body' && rows[data.row.index]?.sinCandidato && data.column.index === 3) {
            data.cell.styles.fillColor = hexToRgb(BRAND.amberBg);
            data.cell.styles.textColor = hexToRgb(BRAND.amberText);
            data.cell.styles.fontStyle = 'italic';
          }
          if (data.section === 'body' && data.column.index === 2) {
            data.cell.styles.textColor = hexToRgb(planchaColor(data.cell.raw));
            data.cell.styles.fontStyle = 'bold';
          }
        },
      });
      y = doc.lastAutoTable.finalY + 12;
    } else {
      doc.setFontSize(9);
      doc.setTextColor(...hexToRgb(BRAND.gray));
      doc.text('Sin dignatarios para este bloque.', margin, y + 2);
      y += 12;
    }
  });

  // Pie de página con numeración en todas las hojas.
  const pages = doc.getNumberOfPages();
  for (let page = 1; page <= pages; page += 1) {
    doc.setPage(page);
    doc.setDrawColor(229, 231, 235);
    doc.line(margin, pageHeight - 12, pageWidth - margin, pageHeight - 12);
    doc.setFontSize(8);
    doc.setTextColor(...hexToRgb(BRAND.gray));
    doc.text(`Asojuntas Girardot · ${barrio?.name ?? ''}`, margin, pageHeight - 7);
    doc.text(`Página ${page} de ${pages}`, pageWidth - margin, pageHeight - 7, { align: 'right' });
  }

  doc.save(fileName(barrio, 'pdf'));
}

// ---------------------------------------------------------------------------
// Excel
// ---------------------------------------------------------------------------

const argb = (hex) => `FF${String(hex).replace('#', '').toUpperCase()}`;

export async function exportResultsExcel(barrio) {
  const { default: ExcelJS } = await import('exceljs');

  const workbook = new ExcelJS.Workbook();
  workbook.creator = 'Asojuntas Girardot';
  workbook.created = new Date();

  const info = summary(barrio);
  const thin = { style: 'thin', color: { argb: 'FFE5E7EB' } };
  const border = { top: thin, left: thin, bottom: thin, right: thin };
  const headerStyle = (row, color = BRAND.primary) => {
    row.eachCell((cell) => {
      cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
      cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: argb(color) } };
      cell.alignment = { vertical: 'middle' };
      cell.border = border;
    });
    row.height = 20;
  };

  // Hoja 1: resumen y votación por bloque.
  const sheet = workbook.addWorksheet('Resultados', { views: [{ showGridLines: false }] });
  sheet.columns = [
    { key: 'plancha', width: 30 },
    { key: 'votos', width: 12 },
    { key: 'porcentaje', width: 12 },
    { key: 'entero', width: 10 },
    { key: 'residuo', width: 12 },
    { key: 'curules', width: 10 },
  ];

  sheet.mergeCells('A1:F1');
  const title = sheet.getCell('A1');
  title.value = `Resultados del escrutinio — ${barrio?.name ?? ''}`;
  title.font = { bold: true, size: 15, color: { argb: 'FFFFFFFF' } };
  title.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: argb(BRAND.deep) } };
  title.alignment = { vertical: 'middle', indent: 1 };
  sheet.getRow(1).height = 28;

  const meta = [
    ['Comuna', barrio?.comuna || '—'],
    ['Elección', barrio?.eleccion || '—'],
    ['Generado', stamp().human],
    ['Votos válidos', info.votosValidos],
    ['Bloques', info.bloques],
    ['Cargos a proveer', info.cargos],
    ['Mayor votación', info.ganadora],
  ];
  meta.forEach(([label, value]) => {
    const row = sheet.addRow([label, value]);
    row.getCell(1).font = { bold: true, color: { argb: 'FF6B7280' } };
    sheet.mergeCells(row.number, 2, row.number, 6);
  });

  (barrio?.resultados ?? []).forEach((bloque) => {
    const total = bloque.estadisticas?.total ?? 0;
    sheet.addRow([]);

    const blockRow = sheet.addRow([blockTitle(bloque)]);
    sheet.mergeCells(blockRow.number, 1, blockRow.number, 6);
    blockRow.getCell(1).font = { bold: true, size: 12, color: { argb: argb(BRAND.deep) } };
    blockRow.getCell(1).border = { bottom: { style: 'medium', color: { argb: argb(BRAND.primary) } } };

    const quotientRow = sheet.addRow([`Cuociente ${formatNumber(bloque.cuociente_electoral, 2)} · ${bloque.cargos_a_proveer ?? 0} cargos · Mayor votación: ${winnerInfo(bloque).label}`]);
    sheet.mergeCells(quotientRow.number, 1, quotientRow.number, 6);
    quotientRow.getCell(1).font = { italic: true, color: { argb: 'FF6B7280' } };

    headerStyle(sheet.addRow(['Plancha', 'Votos', '%', 'Entero', 'Residuo', 'Curules']));

    (bloque.votos_planchas ?? []).forEach((p) => {
      const row = sheet.addRow([p.plancha, Number(p.votos || 0), share(p.votos, total), Number(p.entero || 0), Number(p.residuo || 0), Number(p.curules || 0)]);
      row.getCell(1).font = { bold: true, color: { argb: argb(planchaColor(p.plancha)) } };
      row.getCell(2).numFmt = '#,##0';
      row.getCell(3).numFmt = '0%';
      row.getCell(5).numFmt = '0.000';
      row.getCell(6).font = { bold: true };
      row.eachCell((cell) => { cell.border = border; });
    });

    [['Válidos', bloque.estadisticas?.validos], ['Blancos', bloque.estadisticas?.blancos], ['Nulos', bloque.estadisticas?.nulos]].forEach(([label, value]) => {
      const row = sheet.addRow([label, Number(value || 0)]);
      row.getCell(1).font = { color: { argb: 'FF6B7280' } };
      row.getCell(2).numFmt = '#,##0';
      row.eachCell((cell) => {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF3F4F6' } };
        cell.border = border;
      });
    });
  });

  // Hoja 2: todos los dignatarios en una sola tabla, con filtros.
  const people = workbook.addWorksheet('Dignatarios', { views: [{ state: 'frozen', ySplit: 1 }] });
  people.columns = [
    { header: 'Bloque', key: 'bloque', width: 28 },
    { header: '#', key: 'numero', width: 5 },
    { header: 'Cargo', key: 'cargo', width: 30 },
    { header: 'Plancha', key: 'plancha', width: 14 },
    { header: 'Nombre', key: 'nombre', width: 32 },
    { header: 'Identificación', key: 'identificacion', width: 16 },
    { header: 'Suplente', key: 'suplente', width: 32 },
  ];
  headerStyle(people.getRow(1), BRAND.deep);

  (barrio?.resultados ?? []).forEach((bloque) => {
    dignatarioRows(bloque).forEach((r) => {
      const row = people.addRow({ bloque: blockTitle(bloque), ...r });
      // La cédula como texto: Excel no debe quitar ceros ni pasarla a notación científica.
      row.getCell('identificacion').numFmt = '@';
      row.getCell('plancha').font = { bold: true, color: { argb: argb(planchaColor(r.plancha)) } };
      if (r.sinCandidato) {
        row.getCell('nombre').font = { italic: true, color: { argb: argb(BRAND.amberText) } };
        row.getCell('nombre').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: argb(BRAND.amberBg) } };
      }
      row.eachCell((cell) => { cell.border = border; });
    });
  });
  people.autoFilter = { from: 'A1', to: 'G1' };

  const buffer = await workbook.xlsx.writeBuffer();
  const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName(barrio, 'xlsx');
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
