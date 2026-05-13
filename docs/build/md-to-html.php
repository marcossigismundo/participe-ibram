<?php
/**
 * Converte docs/MANUAL.md em docs/build/MANUAL.html com CSS print-friendly.
 * Uso: php docs/build/md-to-html.php
 */
declare(strict_types=1);

require __DIR__ . '/Parsedown.php';

$mdPath   = __DIR__ . '/../MANUAL.md';
$htmlPath = __DIR__ . '/MANUAL.html';

$md = file_get_contents($mdPath);
if ($md === false) {
    fwrite(STDERR, "Falha ao ler $mdPath\n");
    exit(1);
}

$parser = new Parsedown();
$parser->setSafeMode(false);
$body = $parser->text($md);

$css = <<<'CSS'
@page {
    size: A4;
    margin: 2cm 2cm 2.5cm 2cm;
    @bottom-center {
        content: counter(page) " / " counter(pages);
        font-size: 9pt;
        color: #6b7280;
    }
    @bottom-left {
        content: "Participe Ibram — Manual";
        font-size: 9pt;
        color: #6b7280;
    }
}

* { box-sizing: border-box; }

html, body {
    font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
    font-size: 10.5pt;
    line-height: 1.55;
    color: #1f2937;
    background: #ffffff;
    margin: 0;
    padding: 0;
}

.container {
    max-width: 100%;
    margin: 0 auto;
}

/* Capa */
h1:first-of-type {
    font-size: 28pt;
    color: #0c326f;
    border-bottom: 4px solid #1351b4;
    padding-bottom: 12px;
    margin-top: 0;
    margin-bottom: 6px;
    page-break-after: avoid;
}

h1 {
    font-size: 20pt;
    color: #0c326f;
    border-bottom: 2px solid #1351b4;
    padding-bottom: 6px;
    margin-top: 28px;
    margin-bottom: 14px;
    page-break-after: avoid;
    page-break-before: auto;
}

h2 {
    font-size: 15pt;
    color: #1351b4;
    border-bottom: 1px solid #c5d4eb;
    padding-bottom: 4px;
    margin-top: 22px;
    margin-bottom: 10px;
    page-break-after: avoid;
}

h3 {
    font-size: 12.5pt;
    color: #0c326f;
    margin-top: 18px;
    margin-bottom: 8px;
    page-break-after: avoid;
}

h4 {
    font-size: 11pt;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-top: 14px;
    margin-bottom: 6px;
    page-break-after: avoid;
}

p {
    margin: 6px 0 10px;
    text-align: justify;
}

ul, ol {
    margin: 8px 0 14px;
    padding-left: 22px;
}

li {
    margin-bottom: 4px;
}

a {
    color: #1351b4;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

blockquote {
    margin: 12px 0;
    padding: 10px 14px;
    background: #f0f6fc;
    border-left: 4px solid #1351b4;
    color: #1e293b;
    font-style: italic;
    page-break-inside: avoid;
}

blockquote p { margin: 0; }

code {
    font-family: "Consolas", "Courier New", monospace;
    font-size: 9.5pt;
    background: #f3f4f6;
    padding: 1px 5px;
    border-radius: 3px;
    color: #be123c;
}

pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 12px 14px;
    border-radius: 6px;
    overflow-x: auto;
    font-size: 9pt;
    line-height: 1.45;
    margin: 12px 0;
    page-break-inside: avoid;
}

pre code {
    background: transparent;
    padding: 0;
    color: inherit;
    font-size: inherit;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 12px 0 18px;
    font-size: 9.5pt;
    page-break-inside: auto;
}

thead {
    background: #1351b4;
    color: #ffffff;
}

th {
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    font-size: 9pt;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border: 1px solid #0c326f;
}

td {
    padding: 7px 10px;
    border: 1px solid #e5e7eb;
    vertical-align: top;
}

tbody tr:nth-child(even) {
    background: #f8fafc;
}

hr {
    border: none;
    border-top: 1px solid #d1d5db;
    margin: 24px 0;
}

strong {
    color: #0c326f;
    font-weight: 700;
}

em {
    color: #1e293b;
}

/* Quebras de pagina amigaveis */
h1, h2, h3, h4 { page-break-after: avoid; }
table, pre, blockquote { page-break-inside: avoid; }
li { page-break-inside: avoid; }

/* Sumario destacado */
h2 + ol {
    background: #f0f6fc;
    padding: 14px 14px 14px 38px;
    border-radius: 6px;
    border-left: 4px solid #1351b4;
}

h2 + ol li { margin-bottom: 6px; }

/* Marca dagua sutil no rodape */
.footer-note {
    text-align: center;
    color: #6b7280;
    font-size: 9pt;
    margin-top: 32px;
    padding-top: 14px;
    border-top: 1px solid #e5e7eb;
}
CSS;

$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Participe Ibram — Manual de Uso</title>
<style>$css</style>
</head>
<body>
<div class="container">
$body
</div>
</body>
</html>
HTML;

file_put_contents($htmlPath, $html);
echo "OK: $htmlPath (" . strlen($html) . " bytes)\n";
