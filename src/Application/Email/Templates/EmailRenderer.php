<?php
/**
 * Renderiza templates HTML+texto de e-mail acessíveis.
 *
 * @package Ibram\ParticipeIbram\Application\Email\Templates
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email\Templates;

use InvalidArgumentException;
use RuntimeException;

/**
 * Render simples baseado em arquivos PHP includes.
 *
 * Estrutura por template (em `$templateBaseDir/<nome>/`):
 *  - `<nome>.subject.txt` — template do assunto. Substituições via {@see strtr}.
 *  - `<nome>.html.php`    — corpo HTML do "main" (será envolvido pelo wrapper acessível).
 *  - `<nome>.text.txt`    — versão texto puro (fallback obrigatório RFC 8058 / boas práticas WCAG).
 *
 * Vars são todos sanitizadas no momento da substituição:
 *  - Strings comuns são escapadas para HTML via `htmlspecialchars` no contexto HTML.
 *  - Vars com sufixo `_md` aceitam um subset MUITO restrito (`**bold**`, `[link](url)`)
 *    e ainda passam por `wp_kses_post` quando disponível para manter HTML básico
 *    seguro (R5 contra XSS em e-mail).
 *
 * O wrapper HTML garante:
 *  - `<!doctype html>` + `<html lang="pt-BR">`
 *  - `<meta charset="UTF-8">`, `<meta name="viewport" content="width=device-width">`
 *  - Layout 1 coluna max-width 600px, alto contraste, dark-mode automático
 *  - Header textual "Participe Ibram" (sem imagem — clientes corporativos
 *    bloqueiam imagens por padrão; texto é mais acessível)
 *  - Footer com link "Cancelar comunicações" (recebe `{unsubscribe_url}`) e DPO
 *
 * O renderer é stateless e thread-safe.
 */
final class EmailRenderer
{
    private string $templateBaseDir;

    /**
     * @param string $templateBaseDir Caminho absoluto até `templates/emails`.
     */
    public function __construct(string $templateBaseDir)
    {
        $templateBaseDir = rtrim($templateBaseDir, "/\\");
        if ($templateBaseDir === '') {
            throw new InvalidArgumentException('templateBaseDir nao pode ser vazio.');
        }
        $this->templateBaseDir = $templateBaseDir;
    }

    /**
     * Renderiza um template.
     *
     * @param string                $template Nome (igual ao subdirectorio em emails/).
     * @param array<string,mixed>   $vars     Vars de substituição.
     *
     * @return array{assunto:string, html:string, text:string}
     *
     * @throws RuntimeException Quando algum dos arquivos do template não existe.
     */
    public function render(string $template, array $vars): array
    {
        $template = self::sanitizeTemplateName($template);

        $dir = $this->templateBaseDir . DIRECTORY_SEPARATOR . $template;
        $subjectFile = $dir . DIRECTORY_SEPARATOR . $template . '.subject.txt';
        $htmlFile    = $dir . DIRECTORY_SEPARATOR . $template . '.html.php';
        $textFile    = $dir . DIRECTORY_SEPARATOR . $template . '.text.txt';

        if (!is_file($subjectFile) || !is_file($htmlFile) || !is_file($textFile)) {
            throw new RuntimeException(sprintf(
                'Template "%s" incompleto (esperado .subject.txt, .html.php, .text.txt em %s).',
                $template,
                $dir
            ));
        }

        // Map de substituições de vars para os textos puros.
        $strtrMap = self::buildStrtrMap($vars, false);
        $strtrMapHtml = self::buildStrtrMap($vars, true);

        // Subject: é texto puro mas pode ter caracteres especiais; sanitiza
        // para single-line e limita 78 chars (RFC 5322 recomenda <= 78 CTL).
        $subjectRaw  = (string) file_get_contents($subjectFile);
        $assunto     = strtr($subjectRaw, $strtrMap);
        $assunto     = self::sanitizeSubject($assunto);

        // HTML: o partial (.html.php) recebe `$vars` no escopo e devolve HTML
        // do "main". O wrapper acessível envolve antes de devolver.
        $mainHtml = self::renderPhpPartial($htmlFile, $vars);
        $html     = self::wrapAccessibleHtml($mainHtml, $vars, $assunto);

        $textRaw  = (string) file_get_contents($textFile);
        $textBody = strtr($textRaw, $strtrMap);
        $text     = self::wrapText($textBody, $vars);

        return [
            'assunto' => $assunto,
            'html'    => $html,
            'text'    => $text,
        ];
    }

    /* =====================================================================
     * Internals
     * ===================================================================== */

    private static function sanitizeTemplateName(string $name): string
    {
        $name = strtolower(trim($name));
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Nome de template invalido: "%s" (apenas a-z 0-9 _ permitidos).',
                $name
            ));
        }

        return $name;
    }

    /**
     * Constroi o map para `strtr`, escapando valores em contexto HTML.
     *
     * Vars com sufixo `_md` são tratadas como markdown muito restrito.
     *
     * @param array<string,mixed> $vars
     *
     * @return array<string,string>
     */
    private static function buildStrtrMap(array $vars, bool $forHtml): array
    {
        $out = [];
        foreach ($vars as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $placeholder = '{' . $key . '}';
            if (is_array($value) || is_object($value)) {
                continue; // Defensivo — vars complexas devem ser pré-formatadas.
            }
            $string = $value === null ? '' : (string) $value;

            $isMarkdown = self::endsWith($key, '_md');
            if ($isMarkdown) {
                $string = $forHtml ? self::renderMiniMarkdown($string) : self::markdownToText($string);
            } elseif ($forHtml) {
                $string = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            $out[$placeholder] = $string;
        }

        return $out;
    }

    /**
     * Includes o partial .html.php em escopo isolado, com `$vars` disponível.
     *
     * O partial DEVE escapar valores ele mesmo via `htmlspecialchars` — o
     * renderer não tem como inferir o contexto. Helper {@see e()} é injetado
     * via fechamento.
     *
     * @param array<string,mixed> $vars
     */
    private static function renderPhpPartial(string $file, array $vars): string
    {
        // Closure escape helper (para uso dentro do template como echo $e($var)).
        $e = static function ($value): string {
            $string = is_scalar($value) || $value === null ? (string) $value : '';
            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        // mini markdown helper (echo $md($var_md)).
        $md = static function ($value): string {
            return self::renderMiniMarkdown(is_scalar($value) ? (string) $value : '');
        };

        $vars['_e']  = $e;
        $vars['_md'] = $md;

        ob_start();
        try {
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                /** @psalm-suppress UnresolvableInclude */
                include $__file;
            })($file, $vars);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new RuntimeException('Falha ao renderizar template HTML: ' . $e->getMessage(), 0, $e);
        }
        $output = (string) ob_get_clean();

        return $output;
    }

    /**
     * Sanitiza o subject: 1 linha, <= 78 chars (boas práticas RFC 5322).
     */
    private static function sanitizeSubject(string $subject): string
    {
        $clean = preg_replace('/[\r\n\t\x00]+/', ' ', $subject) ?? '';
        $clean = trim((string) preg_replace('/\s+/', ' ', $clean));

        // Hard cap em 78 caracteres (visíveis em mb).
        if (function_exists('mb_strlen') && mb_strlen($clean, 'UTF-8') > 78) {
            $clean = (string) mb_substr($clean, 0, 78, 'UTF-8');
        } elseif (strlen($clean) > 78) {
            $clean = substr($clean, 0, 78);
        }

        return $clean;
    }

    /**
     * Wrapper HTML acessível (1 coluna, max 600px, alto contraste, dark-mode).
     *
     * @param array<string,mixed> $vars
     */
    private static function wrapAccessibleHtml(string $main, array $vars, string $title): string
    {
        $unsubscribeUrl = isset($vars['unsubscribe_url']) ? (string) $vars['unsubscribe_url'] : '';
        $dpoEmail       = isset($vars['dpo_email']) ? (string) $vars['dpo_email'] : 'encarregado@museus.gov.br';

        $titleEsc          = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $unsubscribeUrlEsc = htmlspecialchars($unsubscribeUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $dpoEmailEsc       = htmlspecialchars($dpoEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $unsubscribeBlock = '';
        if ($unsubscribeUrl !== '') {
            $unsubscribeBlock = '<p style="margin:0 0 8px;">'
                . '<a href="' . $unsubscribeUrlEsc . '" '
                . 'style="color:#1351b4;text-decoration:underline;">'
                . 'Cancelar comunicacoes nao essenciais'
                . '</a>'
                . '</p>';
        }

        // Style usa `color-scheme` para dark-mode automático em clientes
        // compatíveis (Outlook web, Apple Mail, Gmail). Tabelas fazem o layout
        // (ainda padrão de email para máximo suporte).
        return '<!doctype html>'
            . '<html lang="pt-BR">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="color-scheme" content="light dark">'
            . '<meta name="supported-color-schemes" content="light dark">'
            . '<title>' . $titleEsc . '</title>'
            . '<style>'
            . '@media (prefers-color-scheme: dark) {'
            . '  body, table { background:#0b1320 !important; color:#f0f2f5 !important; }'
            . '  .pi-card { background:#10182a !important; color:#f0f2f5 !important; }'
            . '  a { color:#9bb6ff !important; }'
            . '}'
            . '@media only screen and (max-width:600px) {'
            . '  .pi-card { padding:16px !important; }'
            . '}'
            . '</style>'
            . '</head>'
            . '<body style="margin:0;padding:0;background:#f0f2f5;color:#1c1c1c;'
            . 'font-family:Arial,Helvetica,sans-serif;line-height:1.5;">'
            . '<!-- Skip-link / preheader oculto para leitores de tela -->'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">'
            . $titleEsc
            . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="background:#f0f2f5;">'
            . '<tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" '
            . 'style="max-width:600px;width:100%;">'
            . '<tr><td>'
            . '<header role="banner" style="padding:8px 16px;font-size:14px;color:#555;">'
            . '<strong style="color:#1351b4;">Participe Ibram</strong>'
            . ' &middot; '
            . 'Instituto Brasileiro de Museus'
            . '</header>'
            . '<main role="main" class="pi-card" '
            . 'style="background:#ffffff;color:#1c1c1c;padding:24px;border-radius:8px;'
            . 'box-shadow:0 1px 3px rgba(0,0,0,0.06);">'
            . $main
            . '</main>'
            . '<footer role="contentinfo" style="padding:16px;font-size:12px;color:#555;">'
            . '<p style="margin:0 0 8px;">Voce esta recebendo esta mensagem porque '
            . 'esta cadastrado no Participe Ibram.</p>'
            . $unsubscribeBlock
            . '<p style="margin:0;">Encarregado de Dados (DPO): '
            . '<a href="mailto:' . $dpoEmailEsc . '" '
            . 'style="color:#1351b4;text-decoration:underline;">'
            . $dpoEmailEsc . '</a></p>'
            . '</footer>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>'
            . '</body>'
            . '</html>';
    }

    /**
     * Wrapper para a versão texto: adiciona footer com unsubscribe e DPO.
     *
     * @param array<string,mixed> $vars
     */
    private static function wrapText(string $body, array $vars): string
    {
        $unsubscribeUrl = isset($vars['unsubscribe_url']) ? (string) $vars['unsubscribe_url'] : '';
        $dpoEmail       = isset($vars['dpo_email']) ? (string) $vars['dpo_email'] : 'encarregado@museus.gov.br';

        $footer = "\n\n----\nParticipe Ibram - Instituto Brasileiro de Museus\n"
            . "Voce esta recebendo esta mensagem porque esta cadastrado no Participe Ibram.\n";
        if ($unsubscribeUrl !== '') {
            $footer .= "Cancelar comunicacoes nao essenciais: " . $unsubscribeUrl . "\n";
        }
        $footer .= "Encarregado de Dados (DPO): " . $dpoEmail . "\n";

        return rtrim($body) . $footer;
    }

    /**
     * Mini parser de markdown: somente `**bold**` e `[label](url)`.
     *
     * NÃO usa lib externa por design — escopo intencionalmente reduzido.
     * Toda saída é HTML-escaped antes do parse, depois aplica as poucas tags
     * permitidas. URLs são validadas com filter_var.
     */
    private static function renderMiniMarkdown(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // **bold**
        $html = preg_replace(
            '/\*\*([^*<>]+)\*\*/u',
            '<strong>$1</strong>',
            $escaped
        );
        $html = is_string($html) ? $html : $escaped;

        // [label](url) - url precisa começar com http(s) ou mailto
        $html = preg_replace_callback(
            '/\[([^\]<>]+)\]\(([^)<>\s]+)\)/u',
            static function (array $m): string {
                $label = $m[1];
                $url   = $m[2];
                if (!preg_match('#^(https?|mailto):#i', $url)) {
                    return $m[0]; // deixa literal
                }
                $urlEsc = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<a href="' . $urlEsc . '" '
                    . 'style="color:#1351b4;text-decoration:underline;">'
                    . $label . '</a>';
            },
            $html
        );
        $html = is_string($html) ? $html : $escaped;

        // Pós-validação: passa por wp_kses_post (quando disponível) para
        // estreitar ainda mais. wp_kses_post permite tags básicas de post.
        if (function_exists('wp_kses_post')) {
            $html = (string) wp_kses_post($html);
        }

        return $html;
    }

    /**
     * Converte o subset markdown para texto puro (remove `**` e desambiguam links).
     */
    private static function markdownToText(string $value): string
    {
        $out = preg_replace('/\*\*([^*]+)\*\*/u', '$1', $value);
        $out = is_string($out) ? $out : $value;
        $out = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/u',
            static function (array $m): string {
                return $m[1] . ' (' . $m[2] . ')';
            },
            $out
        );

        return is_string($out) ? $out : $value;
    }

    private static function endsWith(string $haystack, string $needle): bool
    {
        $lh = strlen($haystack);
        $ln = strlen($needle);
        if ($ln === 0 || $ln > $lh) {
            return false;
        }
        return substr_compare($haystack, $needle, -$ln, $ln) === 0;
    }
}
