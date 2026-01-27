<?php
/**
 * Classe de funções auxiliares
 *
 * @package CRM_Developer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Dev_Helpers {

    /**
     * Lista de estados brasileiros
     */
    public static function get_estados() {
        return array(
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins',
        );
    }

    /**
     * Lista de regiões
     */
    public static function get_regioes() {
        return array(
            'Norte' => 'Norte',
            'Nordeste' => 'Nordeste',
            'Centro-Oeste' => 'Centro-Oeste',
            'Sudeste' => 'Sudeste',
            'Sul' => 'Sul',
        );
    }

    /**
     * Retorna a região de um estado
     */
    public static function get_regiao_by_estado($estado) {
        $mapa = array(
            'AC' => 'Norte', 'AP' => 'Norte', 'AM' => 'Norte', 'PA' => 'Norte', 'RO' => 'Norte', 'RR' => 'Norte', 'TO' => 'Norte',
            'AL' => 'Nordeste', 'BA' => 'Nordeste', 'CE' => 'Nordeste', 'MA' => 'Nordeste', 'PB' => 'Nordeste', 'PE' => 'Nordeste', 'PI' => 'Nordeste', 'RN' => 'Nordeste', 'SE' => 'Nordeste',
            'DF' => 'Centro-Oeste', 'GO' => 'Centro-Oeste', 'MT' => 'Centro-Oeste', 'MS' => 'Centro-Oeste',
            'ES' => 'Sudeste', 'MG' => 'Sudeste', 'RJ' => 'Sudeste', 'SP' => 'Sudeste',
            'PR' => 'Sul', 'RS' => 'Sul', 'SC' => 'Sul',
        );
        return isset($mapa[$estado]) ? $mapa[$estado] : '';
    }

    /**
     * Lista de gêneros
     */
    public static function get_generos() {
        return array(
            'feminino' => 'Feminino',
            'masculino' => 'Masculino',
            'nao_binario' => 'Não-binário',
            'outro' => 'Outro',
            'prefiro_nao_informar' => 'Prefiro não informar',
        );
    }

    /**
     * Lista de raça/etnia (padrão IBGE)
     */
    public static function get_racas() {
        return array(
            'branca' => 'Branca',
            'preta' => 'Preta',
            'parda' => 'Parda',
            'amarela' => 'Amarela',
            'indigena' => 'Indígena',
            'prefiro_nao_informar' => 'Prefiro não informar',
        );
    }

    /**
     * Lista de etapas de participação
     */
    public static function get_etapas_participacao() {
        return array(
            'municipal' => 'Municipal',
            'estadual' => 'Estadual',
            'nacional' => 'Nacional',
            'autogestionada' => 'Atividade Autogestionada',
            'debates_tematicos' => 'Ciclo de Debates Temáticos',
            'conferencia_livre' => 'Conferência Livre',
            'convidadas' => 'Convidadas',
            'outro' => 'Outro',
        );
    }

    /**
     * Lista de tipos de participação
     */
    public static function get_tipos_participacao() {
        return array(
            'delegado' => 'Delegado(a) Eleito(a)',
            'convidado' => 'Convidado(a)',
            'observador' => 'Observador(a)',
            'proponente' => 'Proponente de Atividade',
            'facilitador' => 'Facilitador(a)',
            'coordenacao' => 'Membro da Coordenação',
            'publico' => 'Público em Geral',
        );
    }

    /**
     * Lista de categorias de representação
     */
    public static function get_categorias_representacao() {
        return array(
            'sociedade_civil' => 'Sociedade Civil',
            'juventude' => 'Juventude',
            'povos_tradicionais' => 'Povos e Comunidades Tradicionais',
            'governo_municipal' => 'Governo Municipal',
            'governo_estadual' => 'Governo Estadual',
            'governo_federal' => 'Governo Federal',
            'setor_privado' => 'Setor Privado',
            'academia' => 'Academia',
            'outro' => 'Outro',
        );
    }

    /**
     * Lista de eixos temáticos
     */
    public static function get_eixos_tematicos() {
        return array(
            'mitigacao' => 'Mitigação',
            'adaptacao' => 'Adaptação e Preparação para Desastres',
            'justica_climatica' => 'Justiça Climática',
            'transformacao_ecologica' => 'Transformação Ecológica',
            'governanca_educacao' => 'Governança e Educação Ambiental',
        );
    }

    /**
     * Lista de tipos de interação
     */
    public static function get_tipos_interacao() {
        return array(
            'email' => 'Email',
            'telefone' => 'Ligação Telefônica',
            'whatsapp' => 'WhatsApp',
            'reuniao_presencial' => 'Reunião Presencial',
            'reuniao_virtual' => 'Reunião Virtual',
            'evento' => 'Participação em Evento',
            'formulario' => 'Resposta a Formulário',
            'nota' => 'Nota/Observação',
            'outro' => 'Outro',
        );
    }

    /**
     * Lista de resultados de interação
     */
    public static function get_resultados_interacao() {
        return array(
            'positivo' => 'Positivo',
            'neutro' => 'Neutro',
            'negativo' => 'Negativo',
            'pendente' => 'Pendente',
        );
    }

    /**
     * Formata data para exibição
     */
    public static function format_date($date, $format = 'd/m/Y') {
        if (empty($date) || $date === '0000-00-00') {
            return '-';
        }
        $timestamp = strtotime($date);
        return date_i18n($format, $timestamp);
    }

    /**
     * Formata data e hora para exibição
     */
    public static function format_datetime($datetime, $format = 'd/m/Y H:i') {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return '-';
        }
        $timestamp = strtotime($datetime);
        return date_i18n($format, $timestamp);
    }

    /**
     * Calcula idade a partir da data de nascimento
     */
    public static function calculate_age($birth_date) {
        if (empty($birth_date) || $birth_date === '0000-00-00') {
            return null;
        }
        $birth = new DateTime($birth_date);
        $today = new DateTime();
        return $birth->diff($today)->y;
    }

    /**
     * Sanitiza telefone
     */
    public static function sanitize_phone($phone) {
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    /**
     * Formata telefone para exibição
     */
    public static function format_phone($phone) {
        $phone = self::sanitize_phone($phone);
        $len = strlen($phone);

        if ($len === 11) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
        } elseif ($len === 10) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
        }

        return $phone;
    }

    /**
     * Gera link do WhatsApp
     */
    public static function get_whatsapp_link($phone, $message = '') {
        $phone = self::sanitize_phone($phone);

        // Adiciona código do país se não tiver
        if (strlen($phone) <= 11 && substr($phone, 0, 2) !== '55') {
            $phone = '55' . $phone;
        }

        $url = 'https://wa.me/' . $phone;

        if (!empty($message)) {
            $url .= '?text=' . urlencode($message);
        }

        return $url;
    }

    /**
     * Calcula score de engajamento
     */
    public static function calculate_engagement_score($contact) {
        $score = 0;

        // Campos preenchidos (até 30 pontos)
        $campos_importantes = array(
            'email', 'telefone', 'whatsapp', 'municipio', 'estado',
            'etapa_participacao', 'tipo_participacao', 'categoria_representacao',
            'eixo_tematico', 'participa_coletivos', 'continuar_participando'
        );

        foreach ($campos_importantes as $campo) {
            if (!empty($contact[$campo])) {
                $score += 3;
            }
        }

        // Interesse em mobilização (até 30 pontos)
        $interesses = array(
            'interesse_formacao', 'interesse_conteudo', 'interesse_incidencia',
            'interesse_mobilizacao', 'interesse_voluntariado', 'interesse_foruns'
        );

        foreach ($interesses as $interesse) {
            if (!empty($contact[$interesse]) && $contact[$interesse] === 'sim') {
                $score += 5;
            }
        }

        // Papel de liderança (10 pontos)
        if (!empty($contact['papel_lideranca']) && $contact['papel_lideranca'] === 'sim') {
            $score += 10;
        }

        // Participa de coletivos (10 pontos)
        if (!empty($contact['participa_coletivos']) && $contact['participa_coletivos'] === 'sim') {
            $score += 10;
        }

        // Deseja continuar participando (10 pontos)
        if (!empty($contact['continuar_participando']) && $contact['continuar_participando'] === 'sim') {
            $score += 10;
        }

        // Limita a 100
        return min($score, 100);
    }

    /**
     * Retorna cor do score
     */
    public static function get_score_color($score) {
        if ($score >= 70) {
            return '#27ae60';
        } elseif ($score >= 40) {
            return '#f39c12';
        } else {
            return '#e74c3c';
        }
    }

    /**
     * Retorna label do score
     */
    public static function get_score_label($score) {
        if ($score >= 70) {
            return 'Alto Engajamento';
        } elseif ($score >= 40) {
            return 'Médio Engajamento';
        } else {
            return 'Baixo Engajamento';
        }
    }

    /**
     * Verifica permissão
     */
    public static function can_user($capability) {
        return current_user_can($capability) || current_user_can('manage_options');
    }

    /**
     * Gera código único
     */
    public static function generate_unique_code($prefix = 'CRM') {
        return $prefix . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }
}
