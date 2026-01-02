<?php
// includes/recorrencia_helper.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cria uma série de agendamentos recorrentes
 * 
 * @param PDO   $pdo    Conexão com banco de dados
 * @param int   $userId ID do usuário/profissional
 * @param array $dados  Dados do agendamento
 * @return array Resultado com sucesso e dados ou mensagem de erro
 */
function criarAgendamentosRecorrentes($pdo, $userId, $dados) {
    try {
        $recorrenciaAtiva = !empty($dados['recorrencia_ativa']);
        $servicoId = $dados['servico_id'] ?? null;
        $recDiasSemana = $dados['recorrencia_dias_semana'] ?? null;
        if (is_array($recDiasSemana)) {
            $recDiasSemana = json_encode(array_values($recDiasSemana));
        }
        $temDiasSemana = !empty($recDiasSemana);
        $config = null;

        if (!empty($servicoId)) {
            // Buscar configurações de recorrência do serviço
            $stmt = $pdo->prepare("
                SELECT permite_recorrencia,
                       tipo_recorrencia,
                       intervalo_dias,
                       duracao_meses,
                       qtd_ocorrencias,
                       dias_semana,
                       dia_fixo_mes
                FROM servicos
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$servicoId, $userId]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Montar configuração de datas
        $dataInicioStr = $dados['data_inicio'] ?? ($dados['data_agendamento'] ?? null);
        if (empty($dataInicioStr)) {
            return [
                'sucesso' => false,
                'erro'    => 'Data de início não informada.'
            ];
        }

        $dataInicio = new DateTime($dataInicioStr);

        if ($recorrenciaAtiva) {
            if ($temDiasSemana) {
                $configDatas = [
                    'tipo_recorrencia' => 'semanal',
                    'qtd_ocorrencias'  => max(1, (int)($dados['recorrencia_qtd'] ?? 1)),
                    'intervalo_dias'   => max(1, (int)($dados['recorrencia_intervalo'] ?? 7)),
                    'dias_semana'      => $recDiasSemana,
                    'dia_fixo_mes'     => null,
                    'user_id'          => $userId
                ];
            } else {
                $configDatas = [
                    'tipo_recorrencia' => 'personalizada',
                    'qtd_ocorrencias'  => max(1, (int)($dados['recorrencia_qtd'] ?? 1)),
                    'intervalo_dias'   => max(1, (int)($dados['recorrencia_intervalo'] ?? 1)),
                    'dias_semana'      => null,
                    'dia_fixo_mes'     => null,
                    'user_id'          => $userId
                ];
            }
        } elseif (!empty($config) && !empty($config['permite_recorrencia'])
            && !empty($config['tipo_recorrencia'])
            && $config['tipo_recorrencia'] !== 'sem_recorrencia') {
            $configDatas = [
                'tipo_recorrencia' => $config['tipo_recorrencia'],
                'qtd_ocorrencias'  => $config['qtd_ocorrencias'],
                'intervalo_dias'   => $config['intervalo_dias'],
                'dias_semana'      => $config['dias_semana'],
                'dia_fixo_mes'     => $config['dia_fixo_mes'],
                'user_id'          => $userId
            ];
        } else {
            return [
                'sucesso' => false,
                'erro'    => 'Recorrência não ativada ou não permitida para este serviço.'
            ];
        }

        $datas = gerarDatasRecorrencia($dataInicio, $configDatas);

        if (empty($datas)) {
            return [
                'sucesso' => false,
                'erro'    => 'Nenhuma data gerada para a recorrência.'
            ];
        }

        $serieId = 'SER' . bin2hex(random_bytes(6));
        $dataFim = end($datas)->format('Y-m-d');
        $qtdTotal = count($datas);

        $pdo->beginTransaction();

        $stmtSerie = $pdo->prepare("
            INSERT INTO agendamentos_recorrentes
                (user_id, serie_id, cliente_id, cliente_nome, servico_id, servico_nome, valor, horario,
                 tipo_recorrencia, intervalo_dias, dias_semana, dia_fixo_mes, data_inicio, data_fim, qtd_total, observacoes, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmtSerie->execute([
            $userId,
            $serieId,
            $dados['cliente_id'] ?? null,
            $dados['cliente_nome'],
            $dados['servico_id'] ?? null,
            $dados['servico_nome'] ?? '',
            $dados['valor'] ?? 0,
            $dados['horario'],
            $configDatas['tipo_recorrencia'],
            $configDatas['intervalo_dias'] ?? 1,
            $configDatas['dias_semana'] ?? null,
            $configDatas['dia_fixo_mes'] ?? null,
            $dataInicio->format('Y-m-d'),
            $dataFim,
            $qtdTotal,
            $dados['observacoes'] ?? null
        ]);

        $stmtAg = $pdo->prepare("
            INSERT INTO agendamentos
                (user_id, cliente_id, cliente_nome, servico, valor, data_agendamento, horario, status, observacoes, e_recorrente, serie_id, indice_serie)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', ?, 1, ?, ?)
        ");

        $idsCriados = [];
        foreach ($datas as $i => $dataObj) {
            $stmtAg->execute([
                $userId,
                $dados['cliente_id'] ?? null,
                $dados['cliente_nome'],
                $dados['servico_nome'] ?? '',
                $dados['valor'] ?? 0,
                $dataObj->format('Y-m-d'),
                $dados['horario'],
                $dados['observacoes'] ?? null,
                $serieId,
                $i + 1
            ]);
            $idsCriados[] = (int)$pdo->lastInsertId();
        }

        $pdo->commit();

        return [
            'sucesso'     => true,
            'datas'       => $datas,
            'serie_id'    => $serieId,
            'qtd_criados' => $qtdTotal,
            'ids_criados' => $idsCriados
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}

// ================= FUNÇÕES DE RECORRÊNCIA =================

/**
 * Gera array de datas conforme configuração de recorrência
 *
 * @param DateTime $dataInicio
 * @param array    $config  (tipo_recorrencia, qtd_ocorrencias, intervalo_dias, dias_semana, dia_fixo_mes, user_id)
 * @return DateTime[]
 */
function gerarDatasRecorrencia($dataInicio, $config) {
    global $pdo;

    $datas          = [];
    $dataAtual      = clone $dataInicio;
    $qtdOcorrencias = isset($config['qtd_ocorrencias']) ? max(1, (int)$config['qtd_ocorrencias']) : 1;
    $tipo           = isset($config['tipo_recorrencia']) ? $config['tipo_recorrencia'] : 'nenhuma';
    $userId         = isset($config['user_id']) ? (int)$config['user_id'] : ($_SESSION['user_id'] ?? 1);

    switch ($tipo) {
        case 'diaria':
            for ($i = 0; $i < $qtdOcorrencias; $i++) {
                $dataUtil = proximoDiaUtil($pdo, $userId, clone $dataAtual);
                $datas[]  = $dataUtil;
                $dataAtual->modify('+1 day');
            }
            break;

        case 'semanal':
            $diasSemana = !empty($config['dias_semana']) ? json_decode($config['dias_semana'], true) : [];
            if (empty($diasSemana)) {
                $diasSemana = [(int)$dataInicio->format('w')];
            }
            sort($diasSemana);

            $dataParaAdicionar = clone $dataAtual;
            // recua um dia para o loop encontrar o primeiro dia válido
            $dataParaAdicionar->modify('-1 day');

            while (count($datas) < $qtdOcorrencias) {
                $diaCorrente = (int)$dataParaAdicionar->format('w');
                $proximoDia  = null;

                // próximo dia da semana válido ainda nesta semana
                foreach ($diasSemana as $dia) {
                    if ($dia > $diaCorrente) {
                        $proximoDia = $dia;
                        break;
                    }
                }

                if ($proximoDia === null) {
                    // pula para o primeiro dia válido da semana seguinte
                    $diasParaSomar = (7 - $diaCorrente) + $diasSemana[0];
                    $dataParaAdicionar->modify("+{$diasParaSomar} days");
                } else {
                    // mesma semana, só avança até o próximo dia válido
                    $diasParaSomar = $proximoDia - $diaCorrente;
                    $dataParaAdicionar->modify("+{$diasParaSomar} days");
                }

                if (count($datas) < $qtdOcorrencias) {
                    $dataUtil = proximoDiaUtil($pdo, $userId, clone $dataParaAdicionar);
                    if ($dataUtil >= $dataInicio) {
                        $datas[] = $dataUtil;
                    }
                }
            }
            break;

        case 'quinzenal':
            for ($i = 0; $i < $qtdOcorrencias; $i++) {
                $dataUtil = proximoDiaUtil($pdo, $userId, clone $dataAtual);
                $datas[]  = $dataUtil;
                $dataAtual->modify('+15 days');
            }
            break;

        case 'mensal_dia':
            $diaFixo = (int)($config['dia_fixo_mes'] ?? $dataInicio->format('d'));
            for ($i = 0; $i < $qtdOcorrencias; $i++) {
                $ano          = $dataAtual->format('Y');
                $mes          = $dataAtual->format('m');
                $ultimoDiaMes = (int)date('t', strtotime("$ano-$mes-01"));

                $diaUsar   = min($diaFixo, $ultimoDiaMes);
                $dataTemp  = new DateTime("$ano-$mes-$diaUsar");
                $dataUtil  = proximoDiaUtil($pdo, $userId, $dataTemp);
                $datas[]   = $dataUtil;

                $dataAtual->modify('+1 month');
            }
            break;

        case 'mensal_semana':
            $diaSemana = (int)($config['dia_semana'] ?? $dataInicio->format('w'));
            $semanaMes = (int)ceil($dataInicio->format('d') / 7);

            for ($i = 0; $i < $qtdOcorrencias; $i++) {
                $dataTemp = encontrarDiaMesSemanaNaData($dataAtual, $diaSemana, $semanaMes);
                if ($dataTemp instanceof DateTime) {
                    $dataUtil = proximoDiaUtil($pdo, $userId, $dataTemp);
                    $datas[]  = $dataUtil;
                }
                $dataAtual->modify('+1 month');
            }
            break;

        case 'personalizada':
            $intervalo  = max(1, (int)($config['intervalo_dias'] ?? 1));
            $diasSemana = !empty($config['dias_semana']) ? json_decode($config['dias_semana'], true) : [];

            if (empty($diasSemana)) {
                for ($i = 0; $i < $qtdOcorrencias; $i++) {
                    $dataUtil = proximoDiaUtil($pdo, $userId, clone $dataAtual);
                    $datas[]  = $dataUtil;
                    $dataAtual->modify("+{$intervalo} days");
                }
            } else {
                while (count($datas) < $qtdOcorrencias) {
                    $diaSemanaAtual = (int)$dataAtual->format('w');
                    if (in_array($diaSemanaAtual, $diasSemana)) {
                        $dataUtil = proximoDiaUtil($pdo, $userId, clone $dataAtual);
                        $datas[]  = $dataUtil;
                    }
                    $dataAtual->modify("+{$intervalo} days");
                }
            }
            break;

        default:
            $datas[] = proximoDiaUtil($pdo, $userId, clone $dataInicio);
            break;
    }

    return array_slice($datas, 0, $qtdOcorrencias);
}

/**
 * Verifica se a data é feriado/dia de fechamento especial
 *
 * @return string|false  nome do feriado se for, ou false se não for
 */
function isFeriado($pdo, $userId, DateTime $date) {
    $sql = "
        SELECT nome 
        FROM dias_especiais_fechamento 
        WHERE user_id = ? 
          AND (
                data = ? 
                OR (recorrente = 1 AND strftime('%m-%d', data) = strftime('%m-%d', ?))
          )
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $userId,
        $date->format('Y-m-d'),
        $date->format('Y-m-d')
    ]);

    $row = $stmt->fetch();
    return $row ? $row['nome'] : false;
}

/**
 * Avança para o próximo dia útil (não feriado), com limite de tentativas
 */
function proximoDiaUtil($pdo, $userId, DateTime $date) {
    $maxTries = 7; // no máximo 1 semana à frente

    for ($i = 0; $i < $maxTries; $i++) {
        if (!isFeriado($pdo, $userId, $date)) {
            return $date;
        }
        $date->modify('+1 day');
    }

    return $date;
}

/**
 * Encontra uma data específica em um mês (ex: 2ª segunda-feira)
 *
 * @param DateTime $data         mês/ano de referência
 * @param int      $diaSemana    0 (domingo) a 6 (sábado)
 * @param int      $numeroSemana 1 = primeira, 2 = segunda, etc.
 * @return DateTime|null
 */
function encontrarDiaMesSemanaNaData($data, $diaSemana, $numeroSemana) {
    $ano = $data->format('Y');
    $mes = $data->format('m');

    $contador = 0;

    for ($dia = 1; $dia <= 31; $dia++) {
        try {
            $dataTemp = new DateTime("$ano-$mes-$dia");
        } catch (Exception $e) {
            break; // data inválida
        }

        if ($dataTemp->format('m') !== $mes) {
            break; // passou para o próximo mês
        }

        if ((int)$dataTemp->format('w') === (int)$diaSemana) {
            $contador++;
            if ($contador === (int)$numeroSemana) {
                return $dataTemp;
            }
        }
    }

    return null;
}

/**
 * Calcula data final da série baseado na duração em meses
 */
function calcularDataFim($dataInicio, $config) {
    $dataFim      = clone $dataInicio;
    $duracaoMeses = max(1, (int)$config['duracao_meses']);
    $dataFim->modify("+{$duracaoMeses} months");
    return $dataFim;
}

/**
 * Cancela uma ocorrência específica de agendamento recorrente
 */
function cancelarOcorrencia($pdo, $agendamentoId, $userId) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM agendamentos 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$agendamentoId, $userId]);
        
        return ['sucesso' => true];
    } catch (Exception $e) {
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}

/**
 * Cancela esta ocorrência e todas as próximas
 */
function cancelarOcorrenciaEProximas($pdo, $agendamentoId, $userId) {
    try {
        // Buscar série e índice do agendamento
        $stmt = $pdo->prepare("
            SELECT serie_id, indice_serie, data_agendamento 
            FROM agendamentos 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$agendamentoId, $userId]);
        $agendamento = $stmt->fetch();
        
        if (!$agendamento || empty($agendamento['serie_id'])) {
            return ['sucesso' => false, 'erro' => 'Agendamento não encontrado'];
        }

        // Deletar este e todos os próximos
        $stmt = $pdo->prepare("
            DELETE FROM agendamentos 
            WHERE serie_id = ? 
              AND user_id = ? 
              AND data_agendamento >= ?
        ");
        $stmt->execute([
            $agendamento['serie_id'], 
            $userId, 
            $agendamento['data_agendamento']
        ]);

        $qtdRemovidos = $stmt->rowCount();

        // Verificar se ainda há agendamentos na série
        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM agendamentos 
            WHERE serie_id = ?
        ");
        $stmtCheck->execute([$agendamento['serie_id']]);
        $resultado = $stmtCheck->fetch();

        // Se não há mais agendamentos, marcar série como inativa
        if ($resultado && (int)$resultado['total'] === 0) {
            $stmtUpdate = $pdo->prepare("
                UPDATE agendamentos_recorrentes 
                SET ativo = 0 
                WHERE serie_id = ?
            ");
            $stmtUpdate->execute([$agendamento['serie_id']]);
        }

        return [
            'sucesso'       => true,
            'qtd_removidos' => $qtdRemovidos
        ];
    } catch (Exception $e) {
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}

/**
 * Cancela toda a série de agendamentos
 */
function cancelarSerieCompleta($pdo, $serieId, $userId) {
    try {
        // Deletar todos os agendamentos da série
        $stmt = $pdo->prepare("
            DELETE FROM agendamentos 
            WHERE serie_id = ? AND user_id = ?
        ");
        $stmt->execute([$serieId, $userId]);
        $qtdRemovidos = $stmt->rowCount();

        // Marcar série como inativa
        $stmtUpdate = $pdo->prepare("
            UPDATE agendamentos_recorrentes 
            SET ativo = 0 
            WHERE serie_id = ? AND user_id = ?
        ");
        $stmtUpdate->execute([$serieId, $userId]);

        return [
            'sucesso'       => true,
            'qtd_removidos' => $qtdRemovidos
        ];
    } catch (Exception $e) {
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}

/**
 * Busca informações da série recorrente
 */
function buscarInfoSerie($pdo, $serieId, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ar.*, 
                   (SELECT COUNT(*) FROM agendamentos WHERE serie_id = ar.serie_id) as total_agendamentos,
                   (SELECT COUNT(*) FROM agendamentos WHERE serie_id = ar.serie_id AND data_agendamento >= date('now')) as proximos
            FROM agendamentos_recorrentes ar
            WHERE ar.serie_id = ? AND ar.user_id = ?
        ");
        $stmt->execute([$serieId, $userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}
