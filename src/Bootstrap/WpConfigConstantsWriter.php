<?php
/**
 * Auto-geração e injeção das 6 constantes criptográficas em wp-config.php.
 *
 * Executado uma vez durante a ativação do plugin (via Activator::activate).
 * Se as constantes já estiverem definidas (em wp-config, variáveis de
 * ambiente ou outro lugar) elas não são tocadas.
 *
 * Segurança:
 *  - Cria backup de wp-config.php antes de qualquer modificação.
 *  - Usa random_bytes(32) — gerador criptograficamente seguro do sistema.
 *  - Cada constante recebe um valor INDEPENDENTE (LGPD §4.6).
 *  - Falha "soft": se não conseguir escrever, registra o erro em
 *    `pi_activation_last_error` e deixa o pre-flight check em
 *    `participe-ibram.php` mostrar instruções manuais ao usuário.
 *
 * @package Ibram\ParticipeIbram\Bootstrap
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Bootstrap;

/**
 * Garante que as 6 constantes mandatórias do plugin estejam definidas em
 * wp-config.php. Se faltam, gera valores aleatórios e injeta o bloco antes
 * de "/* That's all, stop editing! *\/" do WordPress.
 */
final class WpConfigConstantsWriter
{
    /**
     * Constantes que o plugin precisa para criptografia + HMAC + voto + token.
     * PI_ENC_KEY_CURRENT é sempre 'v1' (versão da chave de criptografia em uso).
     *
     * @var list<string>
     */
    private const CONSTANTS = [
        'PI_ENC_KEY_V1',
        'PI_ENC_KEY_CURRENT',
        'PI_HMAC_KEY',
        'PI_IP_PEPPER',
        'PI_VOTING_SECRET',
        'PI_UNSUBSCRIBE_SECRET',
    ];

    private const MARKER_BEGIN = '// BEGIN Participe Ibram constants — gerado em ativacao';
    private const MARKER_END   = '// END Participe Ibram constants';

    /**
     * Verifica quais constantes faltam, gera valores seguros para elas e
     * injeta um bloco delimitado em wp-config.php.
     *
     * @return array{written:list<string>,skipped:list<string>,error:?string,backup:?string}
     */
    public static function ensure(): array
    {
        $missing = [];
        foreach (self::CONSTANTS as $name) {
            if (!\defined($name)) {
                $missing[] = $name;
            }
        }

        if ($missing === []) {
            return [
                'written' => [],
                'skipped' => self::CONSTANTS,
                'error'   => null,
                'backup'  => null,
            ];
        }

        $configPath = self::locateWpConfig();
        if ($configPath === null) {
            return [
                'written' => [],
                'skipped' => $missing,
                'error'   => 'wp-config.php nao encontrado (procurou em ABSPATH e parent).',
                'backup'  => null,
            ];
        }

        if (!\is_writable($configPath)) {
            return [
                'written' => [],
                'skipped' => $missing,
                'error'   => 'wp-config.php sem permissao de escrita: ' . $configPath
                           . ' — defina as constantes manualmente.',
                'backup'  => null,
            ];
        }

        // Se ja existe nosso bloco marcador, alguem ja rodou — abortar com mensagem.
        $original = (string) @\file_get_contents($configPath);
        if ($original === '') {
            return [
                'written' => [],
                'skipped' => $missing,
                'error'   => 'wp-config.php vazio ou ilegivel: ' . $configPath,
                'backup'  => null,
            ];
        }
        if (\strpos($original, self::MARKER_BEGIN) !== false) {
            return [
                'written' => [],
                'skipped' => $missing,
                'error'   => 'Bloco Participe Ibram ja existe em wp-config.php mas alguma '
                           . 'constante esta indefinida — verifique o conteudo do bloco.',
                'backup'  => null,
            ];
        }

        // Gera valores seguros (32 bytes random → base64 → 44 chars).
        $values = self::generateValues($missing);

        // Monta snippet com o bloco delimitado.
        $snippet = self::buildSnippet($values);

        // Backup com timestamp antes de qualquer modificacao.
        $backupPath = $configPath . '.bak.' . \gmdate('Ymd-His');
        if (!@\copy($configPath, $backupPath)) {
            return [
                'written' => [],
                'skipped' => $missing,
                'error'   => 'Falha ao criar backup de wp-config.php (sem permissao?).',
                'backup'  => null,
            ];
        }

        // Localiza ponto de injecao: antes da linha "stop editing" do WP.
        $injected = self::injectSnippet($original, $snippet);
        if ($injected === null) {
            return [
                'written' => [],
                'skipped' => $missing,
                'error'   => 'Nao foi possivel localizar ponto de injecao em wp-config.php.',
                'backup'  => $backupPath,
            ];
        }

        // Sanity-check: o novo conteudo deve ser maior que o original e conter
        // os marcadores; nao verificamos sintaxe PHP via php -l aqui porque
        // a sandbox da ativacao do plugin pode nao ter acesso a shell_exec.
        if (\strlen($injected) <= \strlen($original) || \strpos($injected, self::MARKER_END) === false) {
            return [
                'written' => [],
                'skipped' => $missing,
                'error'   => 'Bloco injetado parece invalido — abortado sem escrita.',
                'backup'  => $backupPath,
            ];
        }

        // Escreve nova versao de wp-config.php.
        $bytes = @\file_put_contents($configPath, $injected, LOCK_EX);
        if ($bytes === false) {
            return [
                'written' => [],
                'skipped' => $missing,
                'error'   => 'Falha ao escrever wp-config.php apos backup.',
                'backup'  => $backupPath,
            ];
        }

        // Define as constantes no PROCESSO atual para que os proximos passos
        // do Activator (migrations + cron + roles) ja vejam os valores.
        foreach ($values as $name => $value) {
            if (!\defined($name)) {
                \define($name, $value);
            }
        }

        return [
            'written' => \array_keys($values),
            'skipped' => \array_values(\array_diff(self::CONSTANTS, $missing)),
            'error'   => null,
            'backup'  => $backupPath,
        ];
    }

    /**
     * Localiza o caminho do wp-config.php em uso. WordPress permite duas
     * localizacoes padrao: ABSPATH (mesmo dir do wp-load.php) ou parent
     * (um nivel acima, mais protegido).
     */
    private static function locateWpConfig(): ?string
    {
        $candidates = [];
        if (\defined('ABSPATH')) {
            $candidates[] = \ABSPATH . 'wp-config.php';
            $candidates[] = \dirname(\rtrim(\ABSPATH, '/\\')) . '/wp-config.php';
        }
        foreach ($candidates as $path) {
            if (\is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Gera valores criptograficamente seguros para as constantes faltantes.
     * PI_ENC_KEY_CURRENT recebe 'v1' (literal); demais recebem base64(random_bytes(32)).
     *
     * @param list<string> $missing
     * @return array<string,string>
     */
    private static function generateValues(array $missing): array
    {
        $values = [];
        foreach ($missing as $name) {
            if ($name === 'PI_ENC_KEY_CURRENT') {
                $values[$name] = 'v1';
                continue;
            }
            // random_bytes lanca Exception se entropy nao disponivel — deixar propagar.
            $values[$name] = \base64_encode(\random_bytes(32));
        }
        return $values;
    }

    /**
     * Constroi o snippet PHP que sera injetado em wp-config.php.
     *
     * @param array<string,string> $values
     */
    private static function buildSnippet(array $values): string
    {
        $lines   = [];
        $lines[] = self::MARKER_BEGIN . ' (' . \gmdate('Y-m-d H:i:s') . ' UTC)';
        $lines[] = '// Chaves criptograficas independentes — NAO REUTILIZAR.';
        $lines[] = '// Cada constante tem proposito distinto (LGPD R2 art. 4.6):';
        $lines[] = '//   PI_ENC_KEY_V1         cifragem libsodium (CPF/RG/CNPJ/endereco)';
        $lines[] = '//   PI_ENC_KEY_CURRENT    versao em uso para novas escritas';
        $lines[] = '//   PI_HMAC_KEY           HMAC para busca por CPF/CNPJ sem decifrar';
        $lines[] = '//   PI_IP_PEPPER          peper para hash de IPs no audit log';
        $lines[] = '//   PI_VOTING_SECRET      anti-rastreio voto-eleitor';
        $lines[] = '//   PI_UNSUBSCRIBE_SECRET assinatura de URLs de unsubscribe/anonimizacao';
        $lines[] = '// Para regenerar: desative o plugin, remova este bloco, reative.';

        foreach (self::CONSTANTS as $name) {
            if (!isset($values[$name])) {
                // Ja definida fora — nao redefinir (evita "constant already defined").
                continue;
            }
            $lines[] = \sprintf("define('%s', '%s');", $name, \addslashes($values[$name]));
        }

        $lines[] = self::MARKER_END;
        return \implode("\n", $lines);
    }

    /**
     * Insere o snippet imediatamente antes da linha "/* That's all, stop
     * editing! Happy publishing. *\/" do WP. Se essa linha nao existir,
     * tenta variacoes; ultimo recurso: antes de "require_once ABSPATH .
     * 'wp-settings.php';".
     */
    private static function injectSnippet(string $content, string $snippet): ?string
    {
        $anchors = [
            "/* That's all, stop editing! Happy publishing. */",
            "/* That’s all, stop editing! Happy publishing. */", // curly apostrophe
            "/* That's all, stop editing! */",
            "require_once ABSPATH . 'wp-settings.php';",
            "require_once( ABSPATH . 'wp-settings.php' );",
        ];

        foreach ($anchors as $anchor) {
            $pos = \strpos($content, $anchor);
            if ($pos !== false) {
                $before = \substr($content, 0, $pos);
                $after  = \substr($content, $pos);
                return $before . $snippet . "\n\n" . $after;
            }
        }
        return null;
    }
}
