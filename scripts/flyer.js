const fs = require('fs');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
    WidthType, ShadingType, BorderStyle, AlignmentType, LevelFormat, HeadingLevel,
} = require('docx');

// ── Farben der Anwendung ────────────────────────────────────────────────
const GRUEN = '457A00';
const TERRA = 'C86D51';
const BEIGE = 'F2EFE9';
const CREME = 'FDFBF7';
const TEXT = '1C1D1B';
const GRAU = '6A6B66';

// A4 mit 1 Zoll Rand: 11906 - 2*1440
const BREITE = 9026;
const SP2 = [4513, 4513];

const ohneRand = {
    top: { style: BorderStyle.NONE }, bottom: { style: BorderStyle.NONE },
    left: { style: BorderStyle.NONE }, right: { style: BorderStyle.NONE },
    insideHorizontal: { style: BorderStyle.NONE }, insideVertical: { style: BorderStyle.NONE },
};

const p = (text, opt = {}) => new Paragraph({
    spacing: { after: opt.after ?? 120, before: opt.before ?? 0, line: 252 },
    alignment: opt.align,
    children: [new TextRun({
        text,
        size: opt.size ?? 20,
        bold: opt.bold,
        italics: opt.italics,
        color: opt.color ?? TEXT,
        font: 'Calibri',
    })],
});

/** Eine Nutzen-Kachel: fette Überschrift, darunter zwei Zeilen Text. */
const kachel = (titel, text) => new TableCell({
    width: { size: SP2[0], type: WidthType.DXA },
    shading: { type: ShadingType.CLEAR, fill: BEIGE, color: 'auto' },
    margins: { top: 120, bottom: 120, left: 190, right: 190 },
    borders: {
        top: { style: BorderStyle.SINGLE, size: 2, color: CREME },
        bottom: { style: BorderStyle.SINGLE, size: 2, color: CREME },
        left: { style: BorderStyle.SINGLE, size: 2, color: CREME },
        right: { style: BorderStyle.SINGLE, size: 2, color: CREME },
    },
    children: [
        p(titel, { bold: true, color: GRUEN, size: 21, after: 60 }),
        p(text, { size: 19, after: 0 }),
    ],
});

const kachelReihe = (a, b) => new TableRow({ children: [kachel(...a), kachel(...b)] });

// ── Inhalt ───────────────────────────────────────────────────────────────
const inhalt = [];

// Kopf: Farbbalken mit Titel
inhalt.push(new Table({
    columnWidths: [BREITE],
    width: { size: BREITE, type: WidthType.DXA },
    borders: ohneRand,
    rows: [new TableRow({
        children: [new TableCell({
            width: { size: BREITE, type: WidthType.DXA },
            shading: { type: ShadingType.CLEAR, fill: GRUEN, color: 'auto' },
            margins: { top: 280, bottom: 240, left: 320, right: 320 },
            children: [
                p('Wocheneinkauf', { bold: true, size: 44, color: CREME, after: 70 }),
                p('Die Einkaufsliste, die den Weg durch den Laden kennt.',
                    { size: 26, color: CREME, after: 0 }),
            ],
        })],
    })],
}));

inhalt.push(p('', { after: 140 }));

inhalt.push(p(
    'Eine gemeinsame Liste für die ganze Familie — sortiert nach den Regalen Ihres Marktes, '
    + 'gefüttert aus Rezepten, Vorrat und Wochenplan. Auf dem Telefon so gebaut, dass sie '
    + 'zwischen Kühltruhe und Kasse mit einem Daumen zu bedienen ist.',
    { size: 22, after: 200 }
));

// Nutzen-Kacheln
inhalt.push(new Table({
    columnWidths: SP2,
    width: { size: BREITE, type: WidthType.DXA },
    borders: ohneRand,
    rows: [
        kachelReihe(
            ['Eine Liste, alle Geräte',
                'Wer unterwegs Milch aufschreibt, sieht sie zu Hause auf dem Bildschirm. Änderungen fließen zusammen, statt sich zu überschreiben.'],
            ['In der Reihenfolge des Ladens',
                'Die Liste folgt Ihren Regalbereichen — nicht dem Alphabet. Kein Zurücklaufen zur Molkerei.']
        ),
        kachelReihe(
            ['Rezepte werden zu Zutaten',
                'Rezept auswählen, Portionen wählen, fertig. Zwiebeln aus zwei Gerichten ergeben einen Eintrag, nicht zwei.'],
            ['Vorrat mit Inventur',
                'Was da ist, wo es liegt und wann es abläuft. Was zum Sollbestand fehlt, wandert auf Knopfdruck auf die Liste.']
        ),
        kachelReihe(
            ['Wochenplan, der mitdenkt',
                'Vorschläge nach Abwechslung, Aufwand, Vorratslage und Haltbarkeit. Nachvollziehbar statt geheimnisvoll.'],
            ['Funkloch eingeplant',
                'Die geöffnete Liste bleibt im Laden lesbar, auch wenn das Netz weg ist. Als App auf dem Startbildschirm.']
        ),
    ],
}));

inhalt.push(p('', { after: 180 }));

// Ablauf
inhalt.push(p('So läuft eine Woche', { bold: true, size: 25, color: GRUEN, after: 110 }));

const schritte = [
    ['Samstagmorgen', 'Der Assistent schließt den letzten Einkauf ab, übernimmt was gefehlt hat, ergänzt den Grundvorrat und Ihre Vorlage.'],
    ['Wochenplan', 'Sieben Abendessen vorschlagen lassen, zwei tauschen, den Rest so lassen — und alle Zutaten in einem Zug auf die Liste.'],
    ['Im Laden', 'Telefon in die eine Hand, Wagen in die andere. Ganze Zeile antippen, Regalbereich bleibt oben stehen.'],
    ['An der Kasse', '„Einkauf beenden". Nicht Abgehaktes steht schon auf der nächsten Liste.'],
];

schritte.forEach(([titel, text], i) => {
    inhalt.push(new Paragraph({
        spacing: { after: 80, line: 252 },
        children: [
            new TextRun({ text: `${i + 1}. `, bold: true, color: TERRA, size: 22, font: 'Calibri' }),
            new TextRun({ text: `${titel} — `, bold: true, size: 21, color: TEXT, font: 'Calibri' }),
            new TextRun({ text, size: 21, color: TEXT, font: 'Calibri' }),
        ],
    }));
});

inhalt.push(p('', { after: 140 }));

// Datenschutz-Kasten
inhalt.push(new Table({
    columnWidths: [BREITE],
    width: { size: BREITE, type: WidthType.DXA },
    borders: {
        ...ohneRand,
        left: { style: BorderStyle.SINGLE, size: 18, color: TERRA },
    },
    rows: [new TableRow({
        children: [new TableCell({
            width: { size: BREITE, type: WidthType.DXA },
            shading: { type: ShadingType.CLEAR, fill: CREME, color: 'auto' },
            margins: { top: 150, bottom: 150, left: 250, right: 250 },
            children: [
                p('Was wir weglassen', { bold: true, size: 22, color: TERRA, after: 80 }),
                p('Kein Tracking. Keine Werbung. Keine Analysewerkzeuge. Keine Schriften und Skripte von '
                    + 'fremden Servern — deshalb auch kein Einwilligungsbanner. Es bleiben zwei technisch '
                    + 'notwendige Cookies. Ihre Daten gehören Ihrer Familie; Auskunft und Löschung lösen Sie '
                    + 'selbst mit einem Klick aus.',
                    { size: 20, after: 0 }),
            ],
        })],
    })],
}));

inhalt.push(p('', { after: 160 }));

// Fakten in zwei Spalten
inhalt.push(new Table({
    columnWidths: SP2,
    width: { size: BREITE, type: WidthType.DXA },
    borders: ohneRand,
    rows: [new TableRow({
        children: [
            new TableCell({
                width: { size: SP2[0], type: WidthType.DXA },
                margins: { top: 0, bottom: 0, left: 0, right: 200 },
                children: [
                    p('Für wen', { bold: true, size: 21, color: GRUEN, after: 60 }),
                    p('Familien und Wohngemeinschaften, die zu mehreren einkaufen und sich das Zurückrufen '
                        + '„Brauchen wir noch Butter?" sparen wollen.', { size: 20, after: 0 }),
                ],
            }),
            new TableCell({
                width: { size: SP2[1], type: WidthType.DXA },
                margins: { top: 0, bottom: 0, left: 200, right: 0 },
                children: [
                    p('Was es braucht', { bold: true, size: 21, color: GRUEN, after: 60 }),
                    p('Einen Browser. Auf dem Telefon einmal „Zum Startbildschirm hinzufügen" — danach '
                        + 'startet die Einkaufsansicht wie eine App.', { size: 20, after: 0 }),
                ],
            }),
        ],
    })],
}));

inhalt.push(p('', { after: 140 }));

// Fußzeile
inhalt.push(new Paragraph({
    spacing: { before: 120 },
    border: { top: { style: BorderStyle.SINGLE, size: 6, color: 'DCD6CB', space: 8 } },
    children: [new TextRun({
        text: 'Anwenderhandbuch und Kontakt: siehe /handbuch in der laufenden Anwendung.',
        size: 18, color: GRAU, font: 'Calibri',
    })],
}));

// ── Dokument ─────────────────────────────────────────────────────────────
const doc = new Document({
    creator: 'Wocheneinkauf',
    title: 'Wocheneinkauf — Flyer',
    description: 'Einseitiger Flyer zur Anwendung Wocheneinkauf',
    sections: [{
        properties: { page: { margin: { top: 1000, bottom: 900, left: 1000, right: 1000 } } },
        children: inhalt,
    }],
});

Packer.toBuffer(doc).then((buffer) => {
    fs.writeFileSync('/home/dev/Wocheneinkauf/docs/Wocheneinkauf-Flyer.docx', buffer);
    console.log('geschrieben: docs/Wocheneinkauf-Flyer.docx');
});
