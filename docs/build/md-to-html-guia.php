<?php
/**
 * Converte docs/GUIA-USUARIO.md em docs/build/GUIA-USUARIO.html.
 * CSS rico com caixas coloridas, grids, timeline, role cards.
 * Uso: php docs/build/md-to-html-guia.php
 */
declare(strict_types=1);

require __DIR__ . '/Parsedown.php';

$mdPath   = __DIR__ . '/../GUIA-USUARIO.md';
$htmlPath = __DIR__ . '/GUIA-USUARIO.html';

$md = file_get_contents($mdPath);
if ($md === false) { fwrite(STDERR, "Falha ao ler $mdPath\n"); exit(1); }

$parser = new Parsedown();
$parser->setSafeMode(false);
$parser->setMarkupEscaped(false);
$body = $parser->text($md);

$css = <<<'CSS'
@page {
  size: A4;
  margin: 1.8cm 1.8cm 2cm 1.8cm;
  @bottom-center {
    content: counter(page) " / " counter(pages);
    font-size: 9pt;
    color: #6b7280;
  }
  @bottom-left {
    content: "Participe Ibram — Guia do Usuário";
    font-size: 9pt;
    color: #1351b4;
    font-weight: 600;
  }
}

* { box-sizing: border-box; }

html, body {
  font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
  font-size: 10.5pt;
  line-height: 1.55;
  color: #1f2937;
  margin: 0;
  padding: 0;
  background: #fff;
}

/* ==== Capa ==== */

h1:first-of-type {
  font-size: 36pt;
  color: #0c326f;
  margin: 0 0 6px;
  page-break-after: avoid;
  letter-spacing: -.02em;
}

h2:first-of-type {
  font-size: 18pt;
  color: #1351b4;
  margin: 0 0 6px;
  font-weight: 600;
}

h1:first-of-type + h2 + p strong {
  display: block;
  font-size: 13pt;
  color: #374151;
  font-weight: 600;
  margin: 0 0 24px;
}

.lead {
  background: linear-gradient(135deg, #f0f6fc 0%, #dbe8fb 100%);
  border-left: 5px solid #1351b4;
  padding: 16px 20px;
  border-radius: 0 8px 8px 0;
  font-size: 11pt;
  color: #1e293b;
  line-height: 1.6;
  margin: 22px 0 18px;
}

/* ==== Headings ==== */

h1 {
  font-size: 22pt;
  color: #0c326f;
  border-bottom: 3px solid #1351b4;
  padding-bottom: 8px;
  margin: 24px 0 16px;
  page-break-after: avoid;
}

h2 {
  font-size: 16pt;
  color: #1351b4;
  margin: 22px 0 12px;
  padding-bottom: 4px;
  border-bottom: 1px solid #c5d4eb;
  page-break-after: avoid;
}

h3 {
  font-size: 13pt;
  color: #0c326f;
  margin: 18px 0 8px;
  page-break-after: avoid;
}

h4 {
  font-size: 11pt;
  color: #1f2937;
  margin: 12px 0 6px;
  font-weight: 700;
  page-break-after: avoid;
}

p { margin: 8px 0; }

a { color: #1351b4; text-decoration: none; }
a:hover { text-decoration: underline; }

ul, ol { margin: 8px 0 12px; padding-left: 22px; }
li { margin-bottom: 4px; }

hr { border: 0; border-top: 1px solid #d1d5db; margin: 28px 0; }
strong { color: #0c326f; }

.pagebreak { page-break-before: always; }

/* ==== Sumário ==== */

h2 + ol {
  background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
  border: 1px solid #c5d4eb;
  padding: 16px 16px 16px 42px;
  border-radius: 8px;
  margin: 14px 0 22px;
}

h2 + ol li {
  margin-bottom: 6px;
  font-weight: 500;
}

/* ==== Caixas explicativas ==== */

.callout {
  border-radius: 8px;
  padding: 14px 18px;
  margin: 14px 0;
  font-size: 10.5pt;
  border-left-width: 5px;
  border-left-style: solid;
  page-break-inside: avoid;
}

.callout strong:first-child {
  display: inline-block;
  margin-right: 6px;
  font-size: 11pt;
}

.callout-tip {
  background: #ecfdf5;
  border-left-color: #10b981;
  color: #064e3b;
}
.callout-tip strong:first-child { color: #047857; }

.callout-info {
  background: #eff6ff;
  border-left-color: #1351b4;
  color: #1e3a8a;
}
.callout-info strong:first-child { color: #0c326f; }

.callout-warning {
  background: #fffbeb;
  border-left-color: #f59e0b;
  color: #78350f;
}
.callout-warning strong:first-child { color: #b45309; }

.callout-success {
  background: #f0fdf4;
  border-left-color: #16a34a;
  color: #14532d;
}
.callout-success strong:first-child { color: #15803d; }

.callout-danger {
  background: #fef2f2;
  border-left-color: #dc2626;
  color: #7f1d1d;
}
.callout-danger strong:first-child { color: #b91c1c; }

/* ==== Role cards (escolha de perfil) ==== */

.role-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin: 18px 0 22px;
}

.role-grid--3 { grid-template-columns: 1fr 1fr 1fr; }

.role-card {
  background: #fff;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px;
  position: relative;
  page-break-inside: avoid;
}

.role-card h3 {
  margin: 0 0 8px;
  font-size: 13pt;
  color: #0c326f;
}

.role-card p {
  font-size: 10pt;
  color: #374151;
  margin: 6px 0;
  line-height: 1.5;
}

.role-card__badge {
  position: absolute;
  top: -10px;
  right: 14px;
  background: #1351b4;
  color: #fff;
  font-size: 9pt;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  letter-spacing: .04em;
}

.role-card--citizen { border-color: #93c5fd; background: #eff6ff; }
.role-card--citizen .role-card__badge { background: #2563eb; }
.role-card--citizen h3 { color: #1e40af; }

.role-card--admin { border-color: #fcd34d; background: #fffbeb; }
.role-card--admin .role-card__badge { background: #d97706; }
.role-card--admin h3 { color: #78350f; }

.role-card--pf { border-color: #93c5fd; background: #eff6ff; }
.role-card--pf .role-card__badge { background: #2563eb; }

.role-card--or { border-color: #fcd34d; background: #fffbeb; }
.role-card--or .role-card__badge { background: #d97706; }

.role-card--sm { border-color: #c4b5fd; background: #f5f3ff; }
.role-card--sm .role-card__badge { background: #7c3aed; }

.role-card__action {
  font-size: 9.5pt;
  margin: 10px 0 0;
  padding: 6px 10px;
  background: rgba(255,255,255,.7);
  border-radius: 4px;
  border-left: 3px solid #1351b4;
}

/* ==== Passos numerados ==== */

.step-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  margin: 16px 0 20px;
}

.step {
  display: grid;
  grid-template-columns: 48px 1fr;
  gap: 14px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 14px 16px;
  page-break-inside: avoid;
}

.step__num {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1351b4 0%, #0c326f 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14pt;
  box-shadow: 0 2px 4px rgba(19, 81, 180, 0.3);
}

.step__body h4 {
  margin: 0 0 4px;
  color: #0c326f;
  font-size: 11pt;
}

.step__body p {
  margin: 4px 0 0;
  color: #374151;
  font-size: 10pt;
  line-height: 1.5;
}

/* ==== Workflow (steps mais compactos) ==== */

.workflow {
  margin: 14px 0 18px;
  position: relative;
  padding-left: 0;
}

.workflow__step {
  display: grid;
  grid-template-columns: 36px 1fr;
  gap: 12px;
  align-items: start;
  padding: 10px 0;
  border-bottom: 1px dashed #e5e7eb;
  page-break-inside: avoid;
}

.workflow__step:last-child { border-bottom: 0; }

.workflow__step-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #1351b4;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 11pt;
}

.workflow__step p {
  margin: 0;
  color: #1f2937;
  font-size: 10.5pt;
  line-height: 1.5;
}

/* ==== Timeline (visual) ==== */
/* Fix: border-left fica no container .timeline; itens usam APENAS padding-left
   (sem grid) para nao quebrar o layout quando o dot e position:absolute. */

.timeline {
  margin: 16px 0 20px;
  padding-left: 14px;
  border-left: 2px solid #c5d4eb;
  margin-left: 10px;
}

.timeline__item {
  position: relative;
  padding: 8px 0 14px 22px;
  page-break-inside: avoid;
}

.timeline__dot {
  position: absolute;
  left: -8px;
  top: 14px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #1351b4;
  border: 3px solid #fff;
  box-shadow: 0 0 0 2px #1351b4;
}

.timeline__dot--success {
  background: #10b981;
  box-shadow: 0 0 0 2px #10b981;
}

.timeline__body h4 {
  margin: 0 0 4px;
  color: #0c326f;
  font-size: 11pt;
}

.timeline__body p {
  margin: 4px 0;
  font-size: 10pt;
  line-height: 1.5;
}

.timeline__body ul {
  margin: 4px 0;
  padding-left: 18px;
  font-size: 10pt;
}

.timeline__body ul li { margin-bottom: 3px; }

/* ==== Rights grid (direitos LGPD) ==== */

.rights-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin: 16px 0 18px;
}

.right {
  background: #f0f6fc;
  border: 1px solid #c5d4eb;
  border-radius: 8px;
  padding: 12px;
  text-align: center;
  page-break-inside: avoid;
}

.right__icon {
  width: 36px;
  height: 36px;
  margin: 0 auto 8px;
  background: #1351b4;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16pt;
  font-weight: 700;
}

.right h4 {
  margin: 4px 0;
  font-size: 10.5pt;
  color: #0c326f;
}

.right p {
  margin: 0;
  font-size: 9.5pt;
  color: #374151;
  line-height: 1.4;
}

/* ==== FAQ ==== */

.faq {
  margin: 16px 0 22px;
}

.faq__item {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #1351b4;
  border-radius: 0 8px 8px 0;
  padding: 12px 16px;
  margin-bottom: 10px;
  page-break-inside: avoid;
}

.faq__item h4 {
  margin: 0 0 6px;
  color: #0c326f;
  font-size: 11pt;
}

.faq__item p {
  margin: 4px 0;
  font-size: 10pt;
  color: #374151;
  line-height: 1.5;
}

/* ==== Glossário ==== */

.glossary {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 18px;
  margin: 16px 0;
}

.glossary__item {
  page-break-inside: avoid;
  padding: 8px 10px;
  background: #f8fafc;
  border-radius: 6px;
  border-left: 3px solid #94a3b8;
}

.glossary__item dt {
  font-weight: 700;
  color: #0c326f;
  font-size: 10.5pt;
  margin-bottom: 3px;
}

.glossary__item dd {
  margin: 0;
  font-size: 9.5pt;
  color: #374151;
  line-height: 1.5;
}

/* ==== Contact ==== */

.contact-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin: 16px 0 22px;
}

.contact-card {
  background: linear-gradient(135deg, #ffffff 0%, #f0f6fc 100%);
  border: 1px solid #c5d4eb;
  border-radius: 8px;
  padding: 14px 16px;
  page-break-inside: avoid;
}

.contact-card h4 {
  margin: 0 0 6px;
  font-size: 11pt;
  color: #0c326f;
}

.contact-card p {
  margin: 4px 0;
  font-size: 10pt;
  color: #1f2937;
  line-height: 1.5;
}

.contact-card__note {
  font-size: 9pt !important;
  color: #6b7280 !important;
  font-style: italic;
  margin-top: 6px !important;
}

/* ==== Code (raro neste manual) ==== */

code {
  background: #f3f4f6;
  padding: 1px 6px;
  border-radius: 3px;
  font-family: Consolas, Menlo, monospace;
  font-size: 9.5pt;
  color: #be123c;
}

/* ==== Footer ==== */

.footer-note {
  margin: 32px 0 0;
  padding-top: 14px;
  border-top: 1px solid #e5e7eb;
  text-align: center;
  font-size: 9pt;
  color: #6b7280;
  font-style: italic;
}
CSS;

$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Participe Ibram — Guia do Usuário</title>
<style>$css</style>
</head>
<body>
$body
</body>
</html>
HTML;

file_put_contents($htmlPath, $html);
echo "OK: $htmlPath (" . strlen($html) . " bytes)\n";
