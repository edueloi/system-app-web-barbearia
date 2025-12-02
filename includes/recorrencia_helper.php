<?php
// includes/recorrencia_helper.php

/**
 * Cria uma série de agendamentos recorrentes
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @param int $userId ID do usuário/profissional
 * @param array $dados Dados do agendamento
 * @return array Resultado com sucesso e série_id ou mensagem de erro
 */
function criarAgendamentosRecorrentes($pdo, $userId, $dados) {
    try {
        // Validar dados obrigatórios
        $camposObrigatorios = ['cliente_nome', 'servico_id', 'servico_nome', 'valor', 'horario', 'data_inicio'];
        foreach ($camposObrigatorios as $campo) {
            if (empty($dados[$campo])) {
                return ['sucesso' => false, 'erro' => "Campo obrigatório ausente: {$campo}"];
            }
        }

        // Buscar configurações de recorrência do serviço
        $stmt = $pdo->prepare("
            SELECT permite_recorrencia, tipo_recorrencia, intervalo_dias, 
                   duracao_meses, qtd_ocorrencias, dias_semana, dia_fixo_mes
            FROM servicos 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$dados['servico_id'], $userId]);
        $servico = $stmt->fetch();

        if (!$servico || !$servico['permite_recorrencia']) {
            // Criar agendamento único (sem recorrência)
            return criarAgendamentoUnico($pdo, $userId, $dados);
        }

        // Gerar série_id único
        $serieId = uniqid('serie_', true);

        // Criar registro da série recorrente
        $stmtSerie = $pdo->prepare("
            INSERT INTO agendamentos_recorrentes 
            (user_id, serie_id, cliente_id, cliente_nome, servico_id, servico_nome, valor, horario,
             tipo_recorrencia, intervalo_dias, dias_semana, dia_fixo_mes, data_inicio, data_fim,
             qtd_total, observacoes, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $dataInicio = new DateTime($dados['data_inicio']);
        $dataFim = calcularDataFim($dataInicio, $servico);
        
        $stmtSerie->execute([
            $userId,
            $serieId,
            $dados['cliente_id'] ?? null,
            $dados['cliente_nome'],
            $dados['servico_id'],
            $dados['servico_nome'],
            $dados['valor'],
            $dados['horario'],
            $servico['tipo_recorrencia'],
            $servico['intervalo_dias'],
            $servico['dias_semana'],
            $servico['dia_fixo_mes'],
            $dataInicio->format('Y-m-d'),
            $dataFim->format('Y-m-d'),
            $servico['qtd_ocorrencias'],
            $dados['observacoes'] ?? null
        ]);

        // Gerar todas as datas de ocorrência
        $datasOcorrencia = gerarDatasRecorrencia($dataInicio, $servico);

        // Criar agendamentos individuais
        $stmtAg = $pdo->prepare("
            INSERT INTO agendamentos 
            (user_id, cliente_id, cliente_nome, servico, valor, data_agendamento, horario,
             status, observacoes, serie_id, indice_serie, e_recorrente)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmado', ?, ?, ?, 1)
        ");

        $indice = 1;
        foreach ($datasOcorrencia as $data) {
            $stmtAg->execute([
                $userId,
                $dados['cliente_id'] ?? null,
                $dados['cliente_nome'],
                $dados['servico_nome'],
                $dados['valor'],
                $data->format('Y-m-d'),
                $dados['horario'],
                $dados['observacoes'] ?? null,
                $serieId,
                $indice++
            ]);
        }

        return [
            'sucesso' => true,
            'serie_id' => $serieId,
            'qtd_criados' => count($datasOcorrencia),
            'datas' => array_map(function($d) { return $d->format('Y-m-d'); }, $datasOcorrencia)
        ];

    } catch (Exception $e) {
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}

/**
 * Cria um agendamento único (não recorrente)
 */
function criarAgendamentoUnico($pdo, $userId, $dados) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO agendamentos 
            (user_id, cliente_id, cliente_nome, servico, valor, data_agendamento, horario,
             status, observacoes, e_recorrente)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmado', ?, 0)
        ");

        $stmt->execute([
            $userId,
            $dados['cliente_id'] ?? null,
            $dados['cliente_nome'],
            $dados['servico_nome'],
            $dados['valor'],
            $dados['data_inicio'],
            $dados['horario'],
            $dados['observacoes'] ?? null
        ]);

        return [
            'sucesso' => true,
            'id_agendamento' => $pdo->lastInsertId()
        ];
    } catch (Exception $e) {
        return ['sucesso' => false, 'erro' => $e->getMessage()];
    }
}

/**
 * Gera array de datas conforme configuração de recorrência
 */
function gerarDatasRecorrencia($dataInicio, $config) {
    $datas = [];
    $dataAtual = clone $dataInicio;
    $qtdOcorrencias = (int)$config['qtd_ocorrencias'];
    $tipo = $config['tipo_recorrencia'];

    switch ($tipo) {
        case 'diaria':
            // A cada dia
            for ($i = 0; $i < $qtdOcorrencias; $i++) {
                $datas[] = clone $dataAtual;
                $dataAtual->modify('+1 day');
            }
            break;

        case 'semanal':
            // Semanalmente (mesmos dias da semana)
            $diasSemana = !empty($config['dias_semana']) ? json_decode($config['dias_semana']) : [];
            
            if (empty($diasSemana)) {
                // Se não especificou dias, usar o dia da semana da data inicial
                $diasSemana = [$dataInicio->format('w')];
            }

            while (count($datas) < $qtdOcorrencias) {
                $diaSemanaAtual = $dataAtual->format('w');
                if (in_array($diaSemanaAtual, $diasSemana)) {
                    $datas[] = clone $dataAtual;
                }
                $dataAtual->modify('+1 day');
            }
            break;

        case 'quinzenal':
            // A cada 15 dias
            for ($i = 0; $i < $qtdOcorrencias; $i++) {
                $datas[] = clone $dataAtual;
                $dataAtual->modify('+15 days');
            }
            break;

        case 'mensal_dia':
            // Mensal no mesmo dia do mês
            $diaFixo = $config['dia_fixo_mes'] ?? $dataInicio->format('d');
            
            for ($i = 0; $i < $qtdOcorrencias; $i++) {
                $ano = $dataAtual->format('Y');
                $mes = $dataAtual->format('m');
                
                // Ajustar para dias que não existem no mês (ex: 31 em fevereiro)
                $ultimoDiaMes = date('t', strtotime("$ano-$mes-01"));
                $diaUsar = min($diaFixo, $ultimoDiaMes);
                
                $dataTemp = new DateTime("$ano-$mes-$diaUsar");
                $datas[] = $dataTemp;
                
                $dataAtual->modify('+1 month');
            }
            break;

        case 'mensal_semana':
            // Mensal na mesma semana e dia da semana
            // Ex: Toda 2ª segunda-feira do mês
            $diaSemana = $dataInicio->format('w');
            $semanaMes = ceil($dataInicio->format('d') / 7);
            
            for ($i = 0; $i < $qtdOcorrencias; $i++) {
                $dataTemp = encontrarDiaMesSemanaNaData($dataAtual, $diaSemana, $semanaMes);
                if ($dataTemp) {
                    $datas[] = $dataTemp;
                }
                $dataAtual->modify('+1 month');
            }
            break;

        case 'personalizada':
            // Intervalo personalizado em dias
            $intervalo = max(1, (int)$config['intervalo_dias']);
            $diasSemana = !empty($config['dias_semana']) ? json_decode($config['dias_semana']) : [];
            
            if (empty($diasSemana)) {
                // Intervalo simples sem restrição de dias da semana
                for ($i = 0; $i < $qtdOcorrencias; $i++) {
                    $datas[] = clone $dataAtual;
                    $dataAtual->modify("+{$intervalo} days");
                }
            } else {
                // Intervalo com restrição de dias da semana
                while (count($datas) < $qtdOcorrencias) {
                    $diaSemanaAtual = $dataAtual->format('w');
                    if (in_array($diaSemanaAtual, $diasSemana)) {
                        $datas[] = clone $dataAtual;
                    }
                    $dataAtual->modify("+{$intervalo} days");
                }
            }
            break;

        default:
            // Sem recorrência
            $datas[] = clone $dataInicio;
    }

    return array_slice($datas, 0, $qtdOcorrencias);
}

/**
 * Encontra uma data específica em um mês (ex: 2ª segunda-feira)
 */
function encontrarDiaMesSemanaNaData($data, $diaSemana, $numeroSemana) {
    $ano = $data->format('Y');
    $mes = $data->format('m');
    
    $primeiroDia = new DateTime("$ano-$mes-01");
    $contador = 0;
    
    for ($dia = 1; $dia <= 31; $dia++) {
        try {
            $dataTemp = new DateTime("$ano-$mes-$dia");
            if ($dataTemp->format('w') == $diaSemana) {
                $contador++;
                if ($contador == $numeroSemana) {
                    return $dataTemp;
                }
            }
        } catch (Exception $e) {
            break;
        }
    }
    
    return null;
}

/**
 * Calcula data final da série baseado na duração em meses
 */
function calcularDataFim($dataInicio, $config) {
    $dataFim = clone $dataInicio;
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
        if ($resultado['total'] == 0) {
            $stmtUpdate = $pdo->prepare("
                UPDATE agendamentos_recorrentes 
                SET ativo = 0 
                WHERE serie_id = ?
            ");
            $stmtUpdate->execute([$agendamento['serie_id']]);
        }

        return [
            'sucesso' => true,
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
            'sucesso' => true,
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
?>
