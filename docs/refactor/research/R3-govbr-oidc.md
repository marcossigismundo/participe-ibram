# R3 — Integração com Login Único gov.br (OIDC)

> Pesquisa técnica para preparar a interface de autenticação federada na plataforma `cadastro.museus.gov.br` (Ibram). Última atualização do roteiro técnico oficial consultado: **30/10/2025**.

## 1. Resumo executivo

- **O que é**: Login Único gov.br é o IdP federal baseado em **OAuth 2.0 + OpenID Connect (OIDC)**. Toda integração se dá via **Authorization Code Flow com PKCE** (S256). HTTPS obrigatório; WebView em apps móveis é proibido.
- **Quem pode integrar**: somente serviços públicos com benefício ao cidadão. Ibram (autarquia federal) é elegível direto.
- **Como obter credenciais**: serviço **"Solicitar Integração aos Produtos de Identidade Digital gov.br"** (gov.br/pt-br/servicos). Prazo: 3 dias úteis para homologação. Produção exige vídeo demonstrando o fluxo em homologação.
- **Bibliotecas PHP**: `soarescbm/login-unico-govbr` (provider para `league/oauth2-client`), `ufvjm/govbr-auth` (PKCE explícito), `brenoroosevelt/oauth2-govbr`. Sem SDK oficial PHP.
- **Plugin WordPress oficial gov.br**: **não existe**. Existem plugins OIDC genéricos; recomendado fazer camada própria com `league/oauth2-client` + provider gov.br.
- **Estratégia agora**: definir `AuthProviderInterface`, ter `WordPressAuth` (default) e `GovBrAuth` como **stub que retorna `feature_disabled`**. Quando o Ibram receber as credenciais, troca-se a implementação.

## 2. Processo de cadastro do Ibram no gov.br

### 2.1 Pré-requisitos

| Item | Detalhe |
|------|---------|
| Solicitante | Agente público (servidor efetivo/comissionado) com conta gov.br **nível Prata ou Ouro** |
| Gestor de Negócio | Nome, cargo, e-mail institucional, telefone, CPF |
| Gestor Técnico | Nome, cargo, e-mail institucional, telefone, CPF |
| Chave GPG pública | Do gestor técnico |
| Descrição do sistema | Nome, URL, finalidade, justificativa de impacto cidadão |
| URLs de redirect | `redirect_uri` e `redirect_uri_logout` por ambiente. **HTTPS exatos**. |
| Escopos solicitados | Quanto mais sensível, mais justificativa |

### 2.2 Passos

1. Acessar "Solicitar Integração aos Produtos de Identidade Digital gov.br" em gov.br/pt-br/servicos.
2. Logar com conta gov.br do agente público.
3. Preencher dados do solicitante, do órgão (Ibram), gestor de negócio e gestor técnico.
4. Para cada produto desejado abrir uma solicitação separada.
5. Aguardar análise (até 3 dias úteis). Receber `client_id` + `client_secret` de **homologação**.
6. Implementar fluxo no ambiente de homologação (`sso.staging.acesso.gov.br`).
7. Gravar **vídeo curto** demonstrando o login funcionando.
8. Enviar evidência. Receber credenciais de **produção** (`sso.acesso.gov.br`).
9. Pós-integração: alterações via **Portal de Pós-Integração**.
10. Canal de dúvidas: **integracaoid@gestao.gov.br**.

## 3. Fluxo OIDC completo

### 3.1 Diagrama (texto)

```
+------------+         +-----------------+        +----------------+
|  Cidadão   |         |   PI/WP         |        |   gov.br       |
|  (Browser) |         |   (RP/Cliente)  |        |   (IdP)        |
+------------+         +-----------------+        +----------------+
       | 1. clica "Entrar com    |                          |
       |    gov.br"              |                          |
       |------------------------>|                          |
       |                         | 2. gera state + nonce    |
       |                         |    + code_verifier       |
       |                         |    code_challenge=S256   |
       |                         |    salva em transient    |
       | 3. 302 -> /authorize    |                          |
       |    client_id, redirect  |                          |
       |    scope, state, nonce  |                          |
       |    code_challenge       |                          |
       |<------------------------|                          |
       | 4. GET /authorize?...                              |
       |--------------------------------------------------->|
       | 5. tela login gov.br (CPF + senha/cert/QR)         |
       |<---------------------------------------------------|
       |--------------------------------------------------->|
       | 6. consentimento de scopes                         |
       |<---------------------------------------------------|
       |--------------------------------------------------->|
       | 7. 302 -> redirect_uri?code=...&state=...          |
       |<---------------------------------------------------|
       | 8. GET callback         |                          |
       |------------------------>|                          |
       |                         | 9. valida state          |
       |                         | 10. POST /token          |
       |                         |    Basic auth header     |
       |                         |    grant_type=auth_code  |
       |                         |    code, redirect_uri,   |
       |                         |    code_verifier         |
       |                         |------------------------->|
       |                         | 11. {access_token,       |
       |                         |     id_token, expires_in}|
       |                         |<-------------------------|
       |                         | 12. busca JWKS /jwk      |
       |                         |    valida assinatura,    |
       |                         |    iss, aud, exp, nonce  |
       |                         | 13. (opc) GET /userinfo  |
       |                         |    Authorization:Bearer  |
       |                         |------------------------->|
       |                         | 14. claims do usuário    |
       |                         |<-------------------------|
       |                         | 15. cria/atualiza WP_User|
       |                         |    wp_set_auth_cookie()  |
       | 16. cookie WP + 302     |                          |
       |     dashboard           |                          |
       |<------------------------|                          |
```

## 4. URLs por ambiente

> **Recomendação**: nunca hard-codar — carregar do `.well-known/openid-configuration` 1x/dia e cachear.

| Endpoint | Homologação | Produção |
|----------|-------------|----------|
| Discovery (well-known) | `https://sso.staging.acesso.gov.br/.well-known/openid-configuration` | `https://sso.acesso.gov.br/.well-known/openid-configuration` |
| Authorization | `https://sso.staging.acesso.gov.br/authorize` | `https://sso.acesso.gov.br/authorize` |
| Token | `https://sso.staging.acesso.gov.br/token` | `https://sso.acesso.gov.br/token` |
| Userinfo | `https://sso.staging.acesso.gov.br/userinfo` | `https://sso.acesso.gov.br/userinfo` |
| JWKS | `https://sso.staging.acesso.gov.br/jwk` | `https://sso.acesso.gov.br/jwk` |
| Logout | `https://sso.staging.acesso.gov.br/logout` | `https://sso.acesso.gov.br/logout` |
| API Confiabilidades | `https://api.staging.acesso.gov.br/confiabilidades/v3/contas/cpf/{cpf}/confiabilidades` | `https://api.acesso.gov.br/confiabilidades/v3/contas/cpf/{cpf}/confiabilidades` |

## 5. Tabela de scopes

| Scope | Quando usar | Claims |
|-------|-------------|--------|
| `openid` | **Sempre obrigatório** | `sub` (CPF), `iss`, `aud`, `exp`, `iat`, `nonce`, `amr` |
| `profile` | Nome / nome social / foto | `name`, `social_name`, `profile`, `picture` |
| `email` | E-mail como contato | `email`, `email_verified` |
| `phone` | Raramente | `phone_number`, `phone_number_verified` |
| `govbr_confiabilidades` | Regra de negócio exige selo Prata/Ouro | Lista de selos |
| `govbr_empresa` | Serviços empresariais (CNPJ) | CNPJs vinculados |

### 5.1 Selos de confiabilidade — quando exigir

| Nível | Como obter | Quando exigir no Participe Ibram |
|-------|-----------|----------------------------------|
| **Bronze** | Cadastro básico (Receita/INSS) | Login inicial, consulta de editais |
| **Prata** | Reconhecimento facial CNH ou validação bancária ou Sigepe | **Submissão de cadastro**, edição, votação |
| **Ouro** | Biometria TSE / CIN / ICP-Brasil | Atos formais (assinatura de termo, troca de representante legal) |

> **Recomendação**: mínimo **Prata** para submeter cadastro e votar (combina segurança com inclusão).

## 6. Bibliotecas PHP recomendadas

### Decisão

> **`league/oauth2-client` + `firebase/php-jwt`** com **provider customizado próprio** dentro do plugin. Inspirar-se no `soarescbm/login-unico-govbr`, mantendo o código sob controle direto. PKCE habilitado por padrão.

### Outras opções avaliadas
- `soarescbm/login-unico-govbr` — base League, switch staging/production por config; PKCE manual; PHP 5.6+ (manutenção legada). Útil como referência.
- `ufvjm/govbr-auth` — PKCE S256 explícito; menor adoção, perfil acadêmico.
- `brenoroosevelt/oauth2-govbr` — base League; cobre OAuth2; OIDC fica por conta do consumidor.
- Plugins WordPress OIDC genéricos (`daggerhart/openid-connect-generic`) — atalho viável mas reduz controle sobre selos.

## 7. Mapeamento claims → user WordPress

| Claim gov.br | Origem | Destino no WP | Observações |
|--------------|--------|---------------|-------------|
| `sub` | id_token / userinfo | `user_login` **e** `user_meta:govbr_sub` | É o CPF (apenas dígitos). **Nunca exibir CPF cru** (LGPD). |
| `name` | userinfo | `display_name`, `first_name`+`last_name` | |
| `social_name` | userinfo | `user_meta:govbr_social_name`, sobrescreve `display_name` | Decreto 8.727/2016 — precedência do nome social. |
| `email` | userinfo | `user_email` se `email_verified=true` | Senão, pedir confirmação. |
| `phone_number` | userinfo | `user_meta:govbr_phone` | Só se scope `phone`. |
| `picture` | `/userinfo/picture` | `user_meta:govbr_picture_url` | Não baixar; referenciar URL. |
| `amr` | id_token | `user_meta:govbr_amr` | Ex.: `passwd`, `x509`, `qrcode`. Auditoria. |
| `reliabilities` | userinfo | `user_meta:govbr_nivel` (`bronze`/`prata`/`ouro`) | Maior selo válido. Reavaliar a cada login. |

### Regras de provisionamento

1. Lookup primário: `WP_User` cujo `govbr_sub == sub`.
2. Lookup secundário (migração): `user_email == email && email_verified` e usuário sem `govbr_sub`. Vincular + notificar por e-mail.
3. Caso novo: criar `WP_User` com `user_login = sub` (CPF), senha aleatória forte (não usada), role default.

## 8. Considerações técnicas

- **PKCE S256**: obrigatório no roteiro do gov.br, mesmo para clientes confidenciais. `code_verifier` 43-128 chars via `random_bytes(64)`.
- **State e nonce**: mandatórios, persistência server-side (transient WP), TTL 5 min, single-use.
- **Validação id_token**: JWKS em `/jwk`, cache 24h. Verificar `iss`, `aud`, `azp`, `exp`, `iat`, `nbf`, `nonce`. Skew tolerável: 60s. Algoritmo: RS256.
- **Logout**: hook `wp_logout` redireciona para `{AUTH_URL}/logout` com `id_token_hint` + `post_logout_redirect_uri`.

## 9. Considerações de segurança

| Tópico | Regra |
|--------|-------|
| HTTPS | Obrigatório. Forçar `is_ssl()` no callback; abortar se não for. |
| `client_secret` | Nunca em código versionado. `wp-config.php` carregando de env var. |
| `redirect_uri` | Match estrito com o registrado. |
| Storage de tokens | Não persistir `access_token` em DB sem necessidade. Se persistir, criptografar (libsodium). |
| Rate limiting | 10/min/IP no callback. |
| Logs | Nunca logar `code`, `access_token`, `id_token`. CPF mascarado: `XXX.XXX.XXX-99`. |
| CSRF | `state` no callback; nonce WP no botão de início. |
| LGPD | Minimização de scopes; documentar finalidade. |
| Auditoria | Tabela `pi_auth_log` (sub mascarado, amr, ip_hash, ua, timestamp). Retenção 6 meses. |

## 10. Interface PHP — `AuthProviderInterface`

> Caminho previsto: `src/Infrastructure/Auth/AuthProviderInterface.php`.

```php
<?php
declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Auth;

/**
 * Interface comum a todos os provedores de autenticação do Participe Ibram.
 */
interface AuthProviderInterface {

    public function getId(): string;
    public function getLabel(): string;
    public function isEnabled(): bool;

    /**
     * URL para iniciar o login.
     * Para providers OIDC: /authorize com state, nonce e PKCE persistidos.
     * Para WordPressAuth: wp_login_url($redirect_after).
     */
    public function getAuthorizationUrl(string $redirect_after = ''): string;

    /**
     * Troca authorization code por tokens e retorna identidade neutra.
     * Validações: state (CSRF), assinatura/iss/aud/exp/nonce do id_token.
     * Sem efeitos colaterais no banco.
     *
     * @throws AuthException Em qualquer falha.
     * @throws AuthDisabledException Se !isEnabled().
     */
    public function exchangeCode(string $code, string $state): AuthIdentity;

    public function getUserInfo(string $access_token): array;

    /**
     * URL para encerrar sessão no provider externo.
     * OIDC: end_session_endpoint com id_token_hint + post_logout.
     * WP: wp_logout_url().
     */
    public function getLogoutUrl(string $id_token_hint = '', string $post_logout = ''): string;
}
```

### DTO `AuthIdentity`

```php
<?php
declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Auth;

final class AuthIdentity {
    public function __construct(
        public readonly string $sub,
        public readonly string $provider,
        public readonly array $claims = [],
        public readonly ?string $access_token = null,
        public readonly ?string $id_token = null,
        public readonly int $expires_at = 0
    ) {}
}
```

### Exceções

```php
<?php
namespace Ibram\ParticipeIbram\Infrastructure\Auth;

class AuthException extends \RuntimeException {}
class AuthDisabledException extends AuthException {}
class UnsupportedOperationException extends AuthException {}
```

## 11. Stub `GovBrAuth` (placeholder)

```php
<?php
declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Auth;

/**
 * Provider gov.br - stub.
 *
 * A integração efetiva requer credenciais (client_id + client_secret) emitidas
 * pelo gov.br após cadastro do Ibram. Enquanto isso, isEnabled() retorna false
 * e qualquer chamada lança AuthDisabledException com 'feature_disabled'.
 *
 * Quando ativar:
 *  1. composer require league/oauth2-client firebase/php-jwt
 *  2. Implementar getAuthorizationUrl() gerando state/nonce/PKCE
 *  3. Implementar exchangeCode() chamando /token e validando id_token
 *  4. Remover early-return de isEnabled()
 */
final class GovBrAuth implements AuthProviderInterface {

    public const ID                 = 'govbr';
    public const ENV_HOMOLOGACAO    = 'staging';
    public const ENV_PRODUCAO       = 'production';
    public const ISSUER_HOMOLOGACAO = 'https://sso.staging.acesso.gov.br';
    public const ISSUER_PRODUCAO    = 'https://sso.acesso.gov.br';

    private string $client_id;
    private string $client_secret;
    private string $redirect_uri;
    private string $logout_redirect_uri;
    private string $environment;
    /** @var string[] */
    private array $scopes;

    public function __construct(array $config = []) {
        $this->client_id           = (string) ($config['client_id'] ?? '');
        $this->client_secret       = (string) ($config['client_secret'] ?? '');
        $this->redirect_uri        = (string) ($config['redirect_uri'] ?? '');
        $this->logout_redirect_uri = (string) ($config['logout_redirect_uri'] ?? '');
        $this->environment         = (string) ($config['environment'] ?? self::ENV_HOMOLOGACAO);
        $this->scopes              = (array)  ($config['scopes'] ?? ['openid', 'email', 'profile']);
    }

    public function getId(): string { return self::ID; }
    public function getLabel(): string { return 'gov.br'; }

    public function isEnabled(): bool {
        if (!defined('PI_GOVBR_ENABLED') || !PI_GOVBR_ENABLED) return false;
        if ('' === $this->client_id || '' === $this->client_secret || '' === $this->redirect_uri) return false;
        // Stub: mesmo configurado, retorna false até implementação real.
        return false;
    }

    public function getAuthorizationUrl(string $redirect_after = ''): string {
        if (!$this->isEnabled()) return '';
        // TODO(gov.br): gerar state/nonce/code_verifier, persistir em transient, montar URL.
        throw new AuthDisabledException('feature_disabled: govbr.getAuthorizationUrl');
    }

    public function exchangeCode(string $code, string $state): AuthIdentity {
        if (!$this->isEnabled()) throw new AuthDisabledException('feature_disabled: govbr.exchangeCode');
        // TODO(gov.br): validar state, POST /token, validar id_token via JWKS.
        throw new AuthDisabledException('feature_disabled: govbr.exchangeCode');
    }

    public function getUserInfo(string $access_token): array {
        if (!$this->isEnabled()) throw new AuthDisabledException('feature_disabled: govbr.getUserInfo');
        // TODO(gov.br): GET {issuer}/userinfo com Bearer.
        throw new AuthDisabledException('feature_disabled: govbr.getUserInfo');
    }

    public function getLogoutUrl(string $id_token_hint = '', string $post_logout = ''): string {
        if (!$this->isEnabled()) return '';
        return '';
    }

    private function issuer(): string {
        return self::ENV_PRODUCAO === $this->environment
            ? self::ISSUER_PRODUCAO
            : self::ISSUER_HOMOLOGACAO;
    }
}
```

## 12. `WordPressAuth` (default)

```php
<?php
declare(strict_types=1);

namespace Ibram\ParticipeIbram\Infrastructure\Auth;

final class WordPressAuth implements AuthProviderInterface {

    public const ID = 'wp';

    public function getId(): string { return self::ID; }
    public function getLabel(): string { return __('WordPress', 'participe-ibram'); }
    public function isEnabled(): bool { return true; }

    public function getAuthorizationUrl(string $redirect_after = ''): string {
        return wp_login_url($redirect_after);
    }

    public function exchangeCode(string $code, string $state): AuthIdentity {
        throw new UnsupportedOperationException(
            'WordPressAuth não usa authorization_code. Login é via wp-login.php.'
        );
    }

    public function getUserInfo(string $access_token): array {
        $user = wp_get_current_user();
        if (!$user || 0 === $user->ID) return [];
        return [
            'sub'                => (string) $user->ID,
            'name'               => $user->display_name,
            'email'              => $user->user_email,
            'preferred_username' => $user->user_login,
            'roles'              => (array) $user->roles,
        ];
    }

    public function getLogoutUrl(string $id_token_hint = '', string $post_logout = ''): string {
        return wp_logout_url($post_logout);
    }
}
```

## 13. Configurações em `wp-config.php`

```php
// --- Login Único gov.br (Participe Ibram) ----------------------------------
// Feature flag: deixar em false até receber credenciais e validar homologação.
define('PI_GOVBR_ENABLED', false);

// Ambiente: 'staging' (homologação) ou 'production'.
define('PI_GOVBR_ENV', 'staging');

// Credenciais — preferencialmente carregadas de variável de ambiente.
define('PI_GOVBR_CLIENT_ID',     getenv('PI_GOVBR_CLIENT_ID')     ?: '');
define('PI_GOVBR_CLIENT_SECRET', getenv('PI_GOVBR_CLIENT_SECRET') ?: '');

// URLs registradas no portal gov.br — devem bater EXATAMENTE.
define('PI_GOVBR_REDIRECT_URI', 'https://cadastro.museus.gov.br/wp-login.php?action=govbr_callback');
define('PI_GOVBR_LOGOUT_URI',   'https://cadastro.museus.gov.br/');
// ---------------------------------------------------------------------------
```

> **Nunca** versionar `PI_GOVBR_CLIENT_SECRET`.

## 14. Próximos passos para ativação real

1. **Composer**: `league/oauth2-client` + `firebase/php-jwt`.
2. **Discovery**: fetch + cache (24h) do `.well-known/openid-configuration`.
3. **getAuthorizationUrl**: gerar `state`, `nonce`, `code_verifier`, `code_challenge`, persistir em `set_transient("pi_govbr_state_$state", [...], 5 * MINUTE_IN_SECONDS)`.
4. **Callback handler**: rota `wp-login.php?action=govbr_callback` ou rest route customizada → `exchangeCode` → `UserMapper` → `wp_set_auth_cookie`.
5. **JWT validation**: `firebase/php-jwt` com JWKS de `/jwk` cacheado.
6. **UserMapper**: classe separada, recebe `AuthIdentity`, faz lookup/criação do `WP_User`.
7. **Logout hook**: `add_action('wp_logout', ...)` redireciona para `getLogoutUrl()`.
8. **Tela de configuração admin**: campos `client_id`/`client_secret` (read-only se vierem de constants), seleção de ambiente, escopos, role default.
9. **Botão "Entrar com gov.br"**: filter no `login_form` e shortcode `[pi_govbr_login]`. Imagem oficial do guia visual gov.br.
10. **Testes em homologação** → gravar vídeo → solicitar credenciais de produção.

## 15. URLs consultadas

- https://acesso.gov.br/ — Acesso GOV.BR
- https://acesso.gov.br/roteiro-tecnico/ — Roteiro de Integração (oficial, atualizado 30/10/2025)
- https://acesso.gov.br/roteiro-tecnico/iniciarintegracao.html
- https://acesso.gov.br/roteiro-tecnico/solicitacaocredencialprocesso.html
- https://acesso.gov.br/roteiro-tecnico/escopoatributos.html
- https://manual-roteiro-integracao-login-unico.servicos.gov.br/
- https://github.com/servicosgovbr/manual-roteiro-integracao-login-unico
- https://www.gov.br/governodigital/pt-br/identidade
- https://www.gov.br/pt-br/servicos/solicitar-integracao-aos-produtos-de-identidade-digital-gov-br
- https://agenciagov.ebc.com.br/noticias/202402/entenda-a-diferenca-entre-os-selos-de-confiabilidade-do-gov.br
- https://sso.acesso.gov.br/.well-known/openid-configuration
- https://packagist.org/packages/soarescbm/login-unico-govbr
- https://github.com/soarescbm/login-unico-govbr
- https://packagist.org/packages/ufvjm/govbr-auth
- https://github.com/brenoroosevelt/oauth2-govbr
- Contato oficial: **integracaoid@gestao.gov.br**
