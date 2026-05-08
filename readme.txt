=== Participe Ibram ===
Contributors: ibram
Tags: ibram, participe, museus, federal, lgpd
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: participe-ibram

Plataforma federal Participe Ibram para Cadastro de Agentes para Participação Social do Ibram.

== Description ==

O Participe Ibram é a plataforma federal mantida pelo Instituto Brasileiro de Museus (IBRAM) que implementa o Cadastro de Agentes para Participação Social previsto na Portaria IBRAM nº 3230/2024 e o fluxo de editais e votação do CCDEM (Despacho 98/2025-DDFEM). O sistema oferece cadastro multi-etapas para Pessoas Físicas, Organizações (PJ ou coletivos) e Sistemas/Secretarias de Museus, análise técnica com máquina de estados, recursos administrativos, gestão de editais com habilitação e votação auditável, conformidade rigorosa com a LGPD (consentimento granular, criptografia de PII, direitos do titular) e acessibilidade WCAG 2.1 AA + eMAG alinhada ao Design System gov.br.

== Installation ==

1. Faça upload do diretório `participe-ibram` para `/wp-content/plugins/`.
2. Execute `composer install --no-dev` na raiz do plugin (necessário para o autoloader e bibliotecas de criptografia).
3. Garanta que a extensão `sodium` do PHP esteja habilitada no servidor.
4. Ative o plugin pelo menu Plugins do WordPress.
5. Após a ativação, configure as opções em "Participe Ibram → Configurações".

== Changelog ==

= 0.1.0 =
* Bootstrap inicial: estrutura de diretórios, container de injeção de dependência, Activator com criação de roles e capabilities, autoloader PSR-4 e checagem de requisitos (PHP 7.4+, WordPress 6.2+, libsodium).
