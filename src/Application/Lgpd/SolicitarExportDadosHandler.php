<?php
/**
 * Handler de portabilidade — gera ZIP, assina URL, registra solicitação.
 *
 * @package Ibram\ParticipeIbram\Application\Lgpd
 */

declare(strict_types=1);

namespace Ibram\ParticipeIbram\Application\Lgpd;

use DateTimeImmutable;
use Ibram\ParticipeIbram\Core\Audit\AuditLogger;
use Ibram\ParticipeIbram\Core\Logger\SecureLogger;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitular;
use Ibram\ParticipeIbram\Domain\Consentimento\SolicitacaoTitularRepository;
use RuntimeException;

/**
 * Orquestra:
 *  1. Cria solicitação `tipo=portabilidade` status=aberta.
 *  2. Invoca {@see ExportarDadosTitularHandler::handle} (gera ZIP em storage privado).
 *  3. Assina URL de download (TTL 24h).
 *  4. Encerra solicitação como `atendida` com a URL no campo resposta_md.
 *  5. Audita em cada passo.
 *  6. Dispara `pi_lgpd_export_gerado` (listener envia email com link).
 *
 * O `urlBuilder` é injetado para que o handler não dependa de `home_url()` —
 * recebe o `sig` e devolve a URL absoluta (ex.: `https://.../wp-json/pi/v1/me/exportar-dados/download?sig=...`).
 */
final class SolicitarExportDadosHandler
{
    private ExportarDadosTitularHandler $exporter;
    private SolicitacaoTitularRepository $solicitacoes;
    private ExportUrlSigner $signer;
    private AuditLogger $audit;
    private SecureLogger $logger;

    /** @var callable(string):string */
    private $urlBuilder;

    /**
     * @param callable(string):string $urlBuilder Recebe `sig`, devolve URL absoluta.
     */
    public function __construct(
        ExportarDadosTitularHandler $exporter,
        SolicitacaoTitularRepository $solicitacoes,
        ExportUrlSigner $signer,
        AuditLogger $audit,
        SecureLogger $logger,
        callable $urlBuilder
    ) {
        $this->exporter     = $exporter;
        $this->solicitacoes = $solicitacoes;
        $this->signer       = $signer;
        $this->audit        = $audit;
        $this->logger       = $logger;
        $this->urlBuilder   = $urlBuilder;
    }

    /**
     * @return array{solicitacao_id:int,download_url:string,expira_em:string}
     */
    public function handle(SolicitarExportDadosCommand $command): array
    {
        $agenteId = $command->agenteId();
        $userId   = $command->userId();

        // 1. Auditoria de intent.
        $this->audit->log(
            'lgpd_solicitacao_titular',
            null,
            'export_solicitado',
            null,
            ['agente_id' => $agenteId, 'user_id' => $userId, 'ip_hash' => $command->ipHash()],
            $userId
        );

        // 2. Protocola solicitação portabilidade.
        $solic = SolicitacaoTitular::protocolar(
            $agenteId,
            SolicitacaoTitular::TIPO_PORTABILIDADE,
            '## Portabilidade (LGPD Art. 18 V) — self-service.'
        );
        $solicId = $this->solicitacoes->save($solic);

        // 3. Gera ZIP.
        try {
            $zipPath = $this->exporter->handle($agenteId);
        } catch (\Throwable $e) {
            $this->audit->log(
                'lgpd_solicitacao_titular',
                $solicId,
                'export_falhou',
                null,
                ['agente_id' => $agenteId, 'erro' => $e->getMessage()],
                $userId
            );
            throw new RuntimeException('Falha ao gerar export: ' . $e->getMessage(), 0, $e);
        }

        // 4. Assina URL.
        $fileId   = basename($zipPath, '.zip');
        $expira   = (new DateTimeImmutable('now'))->modify('+' . ExportUrlSigner::TTL_SECONDS . ' seconds');
        $sig      = $this->signer->sign($agenteId, $fileId, $expira);
        $downloadUrl = ($this->urlBuilder)($sig);

        // 5. Atualiza solicitação como atendida.
        $resposta = sprintf(
            "Pacote de portabilidade gerado.\n\nDownload válido até %s (TTL 24h).\nLink: %s",
            $expira->format(\DateTimeInterface::ATOM),
            $downloadUrl
        );
        $solic->responder($resposta, $userId, true);
        $this->solicitacoes->save($solic);

        // 6. Auditoria final + hook.
        $this->audit->log(
            'lgpd_solicitacao_titular',
            $solicId,
            'export_atendido',
            null,
            [
                'agente_id' => $agenteId,
                'file_id'   => $fileId,
                'expira_em' => $expira->format(\DateTimeInterface::ATOM),
            ],
            $userId
        );

        if (function_exists('do_action')) {
            \do_action('pi_lgpd_export_gerado', $agenteId, $downloadUrl, $expira->format(\DateTimeInterface::ATOM));
        }

        $this->logger->info('LGPD export portabilidade gerado.', [
            'solicitacao_id' => $solicId,
            'agente_id'      => $agenteId,
        ]);

        return [
            'solicitacao_id' => $solicId,
            'download_url'   => $downloadUrl,
            'expira_em'      => $expira->format(\DateTimeInterface::ATOM),
        ];
    }
}
