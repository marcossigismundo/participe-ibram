<?php
/**
 * Configuração SMTP (cifrada) para o envio de e-mails.
 *
 * @package Ibram\ParticipeIbram\Application\Email
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Email;

use Ibram\ParticipeIbram\Core\Encryption\EncryptionException;
use Ibram\ParticipeIbram\Core\Encryption\SodiumCipher;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;

/**
 * Lê/grava configurações SMTP em `wp_options` e aplica via `phpmailer_init`.
 *
 *  - `pi_smtp_host`           string
 *  - `pi_smtp_port`           int
 *  - `pi_smtp_encryption`     'tls' | 'ssl' | ''
 *  - `pi_smtp_user`           string
 *  - `pi_smtp_password_enc`   string (ciphertext via SodiumCipher) — R5 V-10
 *  - `pi_smtp_from_email`     string
 *  - `pi_smtp_from_name`      string
 *
 * O password EM CLARO nunca é persistido. Ao salvar pelo admin, o controller
 * chama {@see savePassword} que aplica `SodiumCipher::encrypt` antes do
 * `update_option`. A leitura do password só ocorre dentro de
 * `phpmailer_init` (configura PHPMailer e descarta o plaintext).
 */
final class SmtpConfig
{
    public const OPT_HOST          = 'pi_smtp_host';
    public const OPT_PORT          = 'pi_smtp_port';
    public const OPT_ENCRYPTION    = 'pi_smtp_encryption';
    public const OPT_USER          = 'pi_smtp_user';
    public const OPT_PASSWORD_ENC  = 'pi_smtp_password_enc';
    public const OPT_FROM_EMAIL    = 'pi_smtp_from_email';
    public const OPT_FROM_NAME     = 'pi_smtp_from_name';

    /** @var array<int,string> */
    private const ENC_VALUES = ['', 'tls', 'ssl'];

    private SodiumCipher $cipher;
    private SecureLogger $logger;

    public function __construct(SodiumCipher $cipher, SecureLogger $logger)
    {
        $this->cipher = $cipher;
        $this->logger = $logger;
    }

    /**
     * Registra `phpmailer_init` para aplicar a configuração no envio.
     *
     * Chame em boot do plugin (init / action `phpmailer_init` é disparada
     * pelo WP em wp_mail).
     */
    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }
        add_action('phpmailer_init', [$this, 'apply']);
    }

    /**
     * Aplica a configuração ao PHPMailer no momento do envio.
     *
     * @param object $phpmailer Geralmente \PHPMailer\PHPMailer\PHPMailer.
     */
    public function apply($phpmailer): void
    {
        $host = $this->getString(self::OPT_HOST);
        if ($host === '') {
            return; // Mantém SMTP padrão do servidor (sendmail, etc.).
        }

        try {
            // Acesso por reflection-friendly: setamos via setter quando existe,
            // senão atribuímos direto. PHPMailer expõe ambos.
            if (method_exists($phpmailer, 'isSMTP')) {
                $phpmailer->isSMTP();
            }
            $phpmailer->Host       = $host;
            $phpmailer->Port       = $this->getInt(self::OPT_PORT, 587);
            $enc                   = $this->getEncryption();
            $phpmailer->SMTPAuth   = true;
            $phpmailer->SMTPSecure = $enc; // '' | 'tls' | 'ssl'
            $phpmailer->Username   = $this->getString(self::OPT_USER);

            $password = $this->getPasswordPlaintext();
            $phpmailer->Password   = $password;

            $fromEmail = $this->getString(self::OPT_FROM_EMAIL);
            $fromName  = $this->getString(self::OPT_FROM_NAME);
            if ($fromEmail !== '' && method_exists($phpmailer, 'setFrom')) {
                $phpmailer->setFrom($fromEmail, $fromName !== '' ? $fromName : 'Participe Ibram');
            }
        } catch (\Throwable $e) {
            $this->logger->error('smtp.apply_falhou', ['erro' => $e->getMessage()]);
        }
    }

    /**
     * Persiste todas as opções em uma chamada (admin).
     *
     * O password é cifrado ANTES do update_option (R5 V-10). Quando vem
     * vazio E já existe um valor cifrado salvo, NÃO sobrescreve (UX típica:
     * deixar campo em branco mantém a senha atual).
     *
     * @param array{
     *   host?:string, port?:int|string, encryption?:string,
     *   user?:string, password?:string, from_email?:string, from_name?:string
     * } $values
     */
    public function save(array $values): void
    {
        if (isset($values['host'])) {
            $this->updateOption(self::OPT_HOST, trim((string) $values['host']));
        }
        if (isset($values['port'])) {
            $port = (int) $values['port'];
            if ($port < 1 || $port > 65535) {
                $port = 587;
            }
            $this->updateOption(self::OPT_PORT, $port);
        }
        if (isset($values['encryption'])) {
            $enc = strtolower(trim((string) $values['encryption']));
            if (!in_array($enc, self::ENC_VALUES, true)) {
                $enc = '';
            }
            $this->updateOption(self::OPT_ENCRYPTION, $enc);
        }
        if (isset($values['user'])) {
            $this->updateOption(self::OPT_USER, trim((string) $values['user']));
        }
        if (isset($values['password'])) {
            $pw = (string) $values['password'];
            if ($pw !== '') {
                $this->savePassword($pw);
            }
        }
        if (isset($values['from_email'])) {
            $email = trim((string) $values['from_email']);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }
            $this->updateOption(self::OPT_FROM_EMAIL, $email);
        }
        if (isset($values['from_name'])) {
            $this->updateOption(self::OPT_FROM_NAME, trim((string) $values['from_name']));
        }
    }

    /**
     * Cifra e grava o password.
     */
    public function savePassword(string $plaintext): void
    {
        if ($plaintext === '') {
            return;
        }
        try {
            $cipherText = $this->cipher->encrypt($plaintext);
            $this->updateOption(self::OPT_PASSWORD_ENC, $cipherText);
        } catch (EncryptionException $e) {
            $this->logger->error('smtp.password_cifrar_falhou', ['erro' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Retorna a configuração corrente (sem o password em claro) para a UI.
     *
     * @return array{
     *   host:string, port:int, encryption:string, user:string,
     *   from_email:string, from_name:string, password_set:bool
     * }
     */
    public function snapshotPublic(): array
    {
        return [
            'host'         => $this->getString(self::OPT_HOST),
            'port'         => $this->getInt(self::OPT_PORT, 587),
            'encryption'   => $this->getEncryption(),
            'user'         => $this->getString(self::OPT_USER),
            'from_email'   => $this->getString(self::OPT_FROM_EMAIL),
            'from_name'    => $this->getString(self::OPT_FROM_NAME),
            'password_set' => $this->getString(self::OPT_PASSWORD_ENC) !== '',
        ];
    }

    /**
     * Decifra e retorna o password (uso interno apenas — phpmailer_init).
     */
    private function getPasswordPlaintext(): string
    {
        $enc = $this->getString(self::OPT_PASSWORD_ENC);
        if ($enc === '') {
            return '';
        }
        try {
            return $this->cipher->decrypt($enc);
        } catch (EncryptionException $e) {
            $this->logger->error('smtp.password_decifrar_falhou', ['erro' => $e->getMessage()]);
            return '';
        }
    }

    private function getString(string $key): string
    {
        if (!function_exists('get_option')) {
            return '';
        }
        $value = \get_option($key, '');

        return is_string($value) ? $value : '';
    }

    private function getInt(string $key, int $default): int
    {
        if (!function_exists('get_option')) {
            return $default;
        }
        $value = \get_option($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function getEncryption(): string
    {
        $enc = strtolower($this->getString(self::OPT_ENCRYPTION));
        return in_array($enc, self::ENC_VALUES, true) ? $enc : '';
    }

    /**
     * @param mixed $value
     */
    private function updateOption(string $key, $value): void
    {
        if (!function_exists('update_option')) {
            return;
        }
        \update_option($key, $value, false); // autoload=false (segredo)
    }
}
