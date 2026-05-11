<?php
/**
 * Template — Aba "Privacidade" da página "Minha conta" (LGPD self-service).
 *
 * Entrega Wave 8 (W8-B). Substitui o stub que W8-A deixou para esta wave.
 *
 * Estrutura WCAG 2.1 AA:
 *  - Cada section com `<h2>`/`<h3>` hierárquicos.
 *  - Botões destrutivos com `aria-describedby` apontando ao aviso correspondente.
 *  - Live region `#pi-privacidade-status` para feedback dinâmico (JS popula).
 *  - Foco default em "Voltar" / cancel — nunca na ação destrutiva.
 *  - Escapamento via `esc_html`, `esc_attr`, `esc_url`.
 *
 * Vars esperadas (preenchidas pelo controlador antes do `include`):
 *   array $vars = [
 *     'termo'             => Termo|null,
 *     'consentimentos'    => array<int,array<string,mixed>>,
 *     'solicitacoes'      => array<int,array<string,mixed>>,
 *     'rest_namespace'    => string,
 *     'rest_nonce'        => string,
 *     'tipos_solicitacao' => array<int,string>,
 *     'minha_conta_url'   => string,
 *   ];
 *
 * Este template é incluído pela classe controladora de "Minha conta" (W8-A).
 *
 * @package ParticipeIbram
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$vars             = isset($vars) && is_array($vars) ? $vars : [];
$termo            = $vars['termo'] ?? null;
$consentimentos   = isset($vars['consentimentos']) && is_array($vars['consentimentos']) ? $vars['consentimentos'] : [];
$solicitacoes     = isset($vars['solicitacoes']) && is_array($vars['solicitacoes']) ? $vars['solicitacoes'] : [];
$restBase         = isset($vars['rest_namespace']) ? (string) $vars['rest_namespace'] : '/wp-json/pi/v1';
$nonce            = isset($vars['rest_nonce']) ? (string) $vars['rest_nonce'] : '';
$tiposSolicitacao = isset($vars['tipos_solicitacao']) && is_array($vars['tipos_solicitacao'])
    ? $vars['tipos_solicitacao']
    : [];
$minhaContaUrl    = isset($vars['minha_conta_url']) ? (string) $vars['minha_conta_url'] : '';
?>
<div class="pi-privacidade" data-pi-mc-privacidade
     data-rest-base="<?php echo esc_attr($restBase); ?>"
     data-rest-nonce="<?php echo esc_attr($nonce); ?>">

    <h2 class="pi-privacidade__title"><?php echo esc_html__('Privacidade e dados pessoais', 'participe-ibram'); ?></h2>

    <p class="pi-privacidade__intro">
        <?php echo esc_html__(
            'Esta área concentra seus direitos como titular de dados (LGPD Art. 18). '
            . 'Você pode revisar consentimentos, abrir solicitações, exportar seus dados ou solicitar anonimização do cadastro.',
            'participe-ibram'
        ); ?>
    </p>

    <div id="pi-privacidade-status" class="pi-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

    <!-- ====================================================================
         Section 1 — Termo vigente
    ===================================================================== -->
    <section class="pi-privacidade__section" aria-labelledby="pi-priv-termo-h">
        <h3 id="pi-priv-termo-h"><?php echo esc_html__('1. Termo de privacidade vigente', 'participe-ibram'); ?></h3>
        <?php if ($termo !== null && method_exists($termo, 'versao')): ?>
            <div class="pi-card pi-card--neutral">
                <p>
                    <strong><?php echo esc_html__('Versão:', 'participe-ibram'); ?></strong>
                    <?php echo esc_html((string) $termo->versao()); ?>
                </p>
                <?php if (method_exists($termo, 'ativoEm')): ?>
                <p>
                    <strong><?php echo esc_html__('Em vigor desde:', 'participe-ibram'); ?></strong>
                    <?php echo esc_html($termo->ativoEm()->format('d/m/Y')); ?>
                </p>
                <?php endif; ?>
                <details class="pi-privacidade__termo-content">
                    <summary><?php echo esc_html__('Ver texto completo', 'participe-ibram'); ?></summary>
                    <div class="pi-privacidade__termo-md">
                        <?php echo wp_kses_post(nl2br(esc_html((string) $termo->conteudoMd()))); ?>
                    </div>
                </details>
                <p>
                    <button type="button" class="pi-btn pi-btn--secondary"
                            data-action="imprimir-termo"
                            aria-describedby="pi-priv-termo-h">
                        <?php echo esc_html__('Imprimir / Salvar como PDF', 'participe-ibram'); ?>
                    </button>
                </p>
            </div>
        <?php else: ?>
            <p class="pi-alert pi-alert--warning">
                <?php echo esc_html__('Nenhum termo de privacidade ativo no momento.', 'participe-ibram'); ?>
            </p>
        <?php endif; ?>
    </section>

    <!-- ====================================================================
         Section 2 — Consentimentos
    ===================================================================== -->
    <section class="pi-privacidade__section" aria-labelledby="pi-priv-cons-h">
        <h3 id="pi-priv-cons-h"><?php echo esc_html__('2. Meus consentimentos', 'participe-ibram'); ?></h3>
        <p class="pi-privacidade__hint">
            <?php echo esc_html__(
                'Finalidades marcadas como obrigatórias não podem ser revogadas individualmente — '
                . 'sua base legal é política pública (Art. 7º, III LGPD). Para encerrar seu cadastro, '
                . 'use "Anonimizar minha conta" abaixo.',
                'participe-ibram'
            ); ?>
        </p>

        <ul class="pi-consents-list" role="list">
            <?php foreach ($consentimentos as $c): ?>
                <?php
                $fin       = isset($c['finalidade']) ? (string) $c['finalidade'] : '';
                $label     = isset($c['label']) ? (string) $c['label'] : $fin;
                $descr     = isset($c['descricao']) ? (string) $c['descricao'] : '';
                $status    = isset($c['status']) ? (string) $c['status'] : 'sem_registro';
                $regEm     = isset($c['registrado_em']) ? (string) $c['registrado_em'] : '';
                $baseLegal = isset($c['base_legal']) ? (string) $c['base_legal'] : '';
                $obrig     = !empty($c['obrigatoria']);
                $sensivel  = !empty($c['sensivel']);
                $revogavel = !empty($c['revogavel']);
                $reacceptable = !empty($c['reaceitavel']);
                $statusClass = $status === 'aceito' ? 'pi-badge--ok' : ($status === 'revogado' ? 'pi-badge--muted' : 'pi-badge--warn');
                $descId    = 'pi-priv-c-desc-' . preg_replace('/[^a-z0-9_-]/', '', $fin);
                ?>
                <li class="pi-consents-list__item" data-finalidade="<?php echo esc_attr($fin); ?>">
                    <div class="pi-consents-list__head">
                        <h4 class="pi-consents-list__label"><?php echo esc_html($label); ?></h4>
                        <span class="pi-badge <?php echo esc_attr($statusClass); ?>"
                              data-role="status-badge">
                            <?php
                            switch ($status) {
                                case 'aceito':       echo esc_html__('Aceito', 'participe-ibram'); break;
                                case 'revogado':     echo esc_html__('Revogado', 'participe-ibram'); break;
                                case 'negado':       echo esc_html__('Negado', 'participe-ibram'); break;
                                case 'sem_registro': echo esc_html__('Sem registro', 'participe-ibram'); break;
                                default:             echo esc_html($status);
                            }
                            ?>
                        </span>
                    </div>
                    <p id="<?php echo esc_attr($descId); ?>" class="pi-consents-list__descricao">
                        <?php echo esc_html($descr); ?>
                    </p>
                    <p class="pi-consents-list__meta">
                        <span><strong><?php echo esc_html__('Base legal:', 'participe-ibram'); ?></strong>
                            <?php echo esc_html($baseLegal); ?></span>
                        <?php if ($regEm !== ''): ?>
                            <span><strong><?php echo esc_html__('Registrado em:', 'participe-ibram'); ?></strong>
                                <?php echo esc_html($regEm); ?></span>
                        <?php endif; ?>
                        <?php if ($sensivel): ?>
                            <span class="pi-badge pi-badge--info"><?php echo esc_html__('Dado sensível', 'participe-ibram'); ?></span>
                        <?php endif; ?>
                    </p>

                    <div class="pi-consents-list__actions">
                        <?php if ($obrig): ?>
                            <p class="pi-consents-list__obrig-msg">
                                <?php echo esc_html__(
                                    'Finalidade obrigatória — não pode ser revogada individualmente.',
                                    'participe-ibram'
                                ); ?>
                            </p>
                        <?php elseif ($revogavel): ?>
                            <button type="button"
                                    class="pi-btn pi-btn--danger-outline"
                                    data-action="revogar"
                                    data-finalidade="<?php echo esc_attr($fin); ?>"
                                    aria-describedby="<?php echo esc_attr($descId); ?>">
                                <?php echo esc_html__('Revogar', 'participe-ibram'); ?>
                            </button>
                        <?php elseif ($reacceptable): ?>
                            <button type="button"
                                    class="pi-btn pi-btn--primary"
                                    data-action="reaceitar"
                                    data-finalidade="<?php echo esc_attr($fin); ?>"
                                    aria-describedby="<?php echo esc_attr($descId); ?>">
                                <?php echo esc_html__('Reaceitar', 'participe-ibram'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- ====================================================================
         Section 3 — Solicitações Art. 18
    ===================================================================== -->
    <section class="pi-privacidade__section" aria-labelledby="pi-priv-solic-h">
        <h3 id="pi-priv-solic-h"><?php echo esc_html__('3. Minhas solicitações (LGPD Art. 18)', 'participe-ibram'); ?></h3>
        <p class="pi-privacidade__hint">
            <?php echo esc_html__(
                'Você tem direito a: acesso, retificação, exclusão, oposição, revisão de decisão automatizada. '
                . 'Portabilidade e anonimização possuem fluxo próprio nas seções 4 e 5.',
                'participe-ibram'
            ); ?>
        </p>

        <p>
            <button type="button" class="pi-btn pi-btn--primary"
                    data-action="abrir-modal-nova-solicitacao"
                    aria-haspopup="dialog">
                <?php echo esc_html__('Nova solicitação', 'participe-ibram'); ?>
            </button>
        </p>

        <?php if ($solicitacoes === []): ?>
            <p class="pi-privacidade__empty">
                <?php echo esc_html__('Nenhuma solicitação registrada.', 'participe-ibram'); ?>
            </p>
        <?php else: ?>
            <table class="pi-table">
                <caption class="pi-sr-only"><?php echo esc_html__('Suas solicitações LGPD', 'participe-ibram'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col"><?php echo esc_html__('Tipo', 'participe-ibram'); ?></th>
                        <th scope="col"><?php echo esc_html__('Status', 'participe-ibram'); ?></th>
                        <th scope="col"><?php echo esc_html__('Protocolada em', 'participe-ibram'); ?></th>
                        <th scope="col"><?php echo esc_html__('Prazo final', 'participe-ibram'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitacoes as $s): ?>
                        <tr>
                            <td><?php echo esc_html((string) ($s['id'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($s['tipo'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($s['status'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($s['protocolada_em'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($s['prazo_final'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Template modal: Nova solicitação -->
        <template id="pi-modal-nova-solicitacao">
            <div class="pi-modal" role="dialog" aria-modal="true" aria-labelledby="pi-modal-solic-h">
                <h2 id="pi-modal-solic-h"><?php echo esc_html__('Nova solicitação LGPD', 'participe-ibram'); ?></h2>
                <form data-form="nova-solicitacao">
                    <div class="pi-form-row">
                        <label for="pi-solic-tipo"><?php echo esc_html__('Tipo de solicitação', 'participe-ibram'); ?></label>
                        <select id="pi-solic-tipo" name="tipo" required>
                            <option value=""><?php echo esc_html__('Selecione...', 'participe-ibram'); ?></option>
                            <?php foreach ($tiposSolicitacao as $tipo): ?>
                                <option value="<?php echo esc_attr((string) $tipo); ?>"><?php echo esc_html((string) $tipo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pi-form-row">
                        <label for="pi-solic-detalhes"><?php echo esc_html__('Detalhes (até 5000 caracteres)', 'participe-ibram'); ?></label>
                        <textarea id="pi-solic-detalhes" name="detalhes_md" rows="6" maxlength="5000"></textarea>
                    </div>
                    <div class="pi-modal__actions">
                        <button type="button" class="pi-btn pi-btn--secondary" data-action="fechar-modal" autofocus>
                            <?php echo esc_html__('Cancelar', 'participe-ibram'); ?>
                        </button>
                        <button type="submit" class="pi-btn pi-btn--primary" data-action="enviar-solicitacao">
                            <?php echo esc_html__('Enviar solicitação', 'participe-ibram'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </section>

    <!-- ====================================================================
         Section 4 — Exportar dados (Portabilidade)
    ===================================================================== -->
    <section class="pi-privacidade__section" aria-labelledby="pi-priv-export-h">
        <h3 id="pi-priv-export-h"><?php echo esc_html__('4. Exportar meus dados (Portabilidade)', 'participe-ibram'); ?></h3>
        <p id="pi-priv-export-desc">
            <?php echo esc_html__(
                'Você pode baixar uma cópia completa dos seus dados pessoais em formato JSON+CSV '
                . '(LGPD Art. 18, II e V). Por segurança, é necessário confirmar sua senha. '
                . 'Limite: 1 export grátis por dia.',
                'participe-ibram'
            ); ?>
        </p>
        <p>
            <button type="button" class="pi-btn pi-btn--primary"
                    data-action="abrir-modal-export"
                    aria-haspopup="dialog"
                    aria-describedby="pi-priv-export-desc">
                <?php echo esc_html__('Solicitar export', 'participe-ibram'); ?>
            </button>
        </p>

        <template id="pi-modal-export">
            <div class="pi-modal" role="dialog" aria-modal="true" aria-labelledby="pi-modal-export-h">
                <h2 id="pi-modal-export-h"><?php echo esc_html__('Confirmar senha para exportar dados', 'participe-ibram'); ?></h2>
                <p><?php echo esc_html__('Digite sua senha para autorizar a geração do pacote.', 'participe-ibram'); ?></p>
                <form data-form="export-reauth">
                    <div class="pi-form-row">
                        <label for="pi-export-senha"><?php echo esc_html__('Senha', 'participe-ibram'); ?></label>
                        <input id="pi-export-senha" name="confirmacao_senha" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="pi-modal__actions">
                        <button type="button" class="pi-btn pi-btn--secondary" data-action="fechar-modal" autofocus>
                            <?php echo esc_html__('Cancelar', 'participe-ibram'); ?>
                        </button>
                        <button type="submit" class="pi-btn pi-btn--primary" data-action="enviar-export">
                            <?php echo esc_html__('Confirmar e gerar', 'participe-ibram'); ?>
                        </button>
                    </div>
                </form>
                <div data-region="export-result" hidden></div>
            </div>
        </template>
    </section>

    <!-- ====================================================================
         Section 5 — Anonimizar conta (IRREVERSÍVEL)
    ===================================================================== -->
    <section class="pi-privacidade__section pi-privacidade__section--danger"
             aria-labelledby="pi-priv-anon-h">
        <h3 id="pi-priv-anon-h" class="pi-privacidade__danger-title">
            <?php echo esc_html__('5. Anonimizar minha conta (IRREVERSÍVEL)', 'participe-ibram'); ?>
        </h3>

        <div class="pi-alert pi-alert--danger" id="pi-priv-anon-aviso" role="region"
             aria-label="<?php echo esc_attr__('Aviso importante', 'participe-ibram'); ?>">
            <p>
                <strong><?php echo esc_html__('Atenção:', 'participe-ibram'); ?></strong>
                <?php echo esc_html__(
                    'A anonimização é IRREVERSÍVEL. Nome, CPF/RG, telefone, email e documentos serão '
                    . 'removidos ou substituídos por valores anônimos. Por obrigação legal (LGPD Art. 16, II), '
                    . 'a trilha de auditoria será preservada (sem seus dados pessoais).',
                    'participe-ibram'
                ); ?>
            </p>
            <p>
                <?php echo esc_html__(
                    'O processo exige duas confirmações: (a) sua senha agora e (b) clicar no link enviado para seu email em até 24h.',
                    'participe-ibram'
                ); ?>
            </p>
        </div>

        <p>
            <button type="button" class="pi-btn pi-btn--danger"
                    data-action="abrir-modal-anon"
                    aria-haspopup="dialog"
                    aria-describedby="pi-priv-anon-aviso">
                <?php echo esc_html__('Solicitar anonimização', 'participe-ibram'); ?>
            </button>
        </p>

        <template id="pi-modal-anon">
            <div class="pi-modal pi-modal--danger" role="alertdialog" aria-modal="true"
                 aria-labelledby="pi-modal-anon-h" aria-describedby="pi-modal-anon-desc">
                <h2 id="pi-modal-anon-h"><?php echo esc_html__('Confirmar solicitação de anonimização', 'participe-ibram'); ?></h2>
                <p id="pi-modal-anon-desc">
                    <strong><?php echo esc_html__('Esta ação é IRREVERSÍVEL.', 'participe-ibram'); ?></strong>
                    <?php echo esc_html__('Você receberá um email com link de confirmação válido por 24 horas.', 'participe-ibram'); ?>
                </p>
                <form data-form="anon-reauth">
                    <div class="pi-form-row">
                        <label for="pi-anon-senha"><?php echo esc_html__('Sua senha', 'participe-ibram'); ?></label>
                        <input id="pi-anon-senha" name="confirmacao_senha" type="password"
                               autocomplete="current-password" required>
                    </div>
                    <div class="pi-form-row">
                        <label for="pi-anon-motivo">
                            <?php echo esc_html__('Motivo (opcional, até 1000 caracteres)', 'participe-ibram'); ?>
                        </label>
                        <textarea id="pi-anon-motivo" name="motivo" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="pi-modal__actions">
                        <button type="button" class="pi-btn pi-btn--secondary" data-action="fechar-modal" autofocus>
                            <?php echo esc_html__('Cancelar', 'participe-ibram'); ?>
                        </button>
                        <button type="submit" class="pi-btn pi-btn--danger" data-action="enviar-anon">
                            <?php echo esc_html__('Enviar pedido de anonimização', 'participe-ibram'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </section>
</div>
