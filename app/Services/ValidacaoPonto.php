<?php
// app/Services/ValidacaoPonto.php
// Validações para o formulário de cadastro/edição de pontos
// Usar no CRUD quando for desenvolvido

class ValidacaoPonto {

    /**
     * Valida todos os campos do ponto
     * Retorna array com os erros encontrados (vazio = sem erros)
     */
    public static function validar(array $dados): array {
        $erros = array();

        // --- Campos obrigatórios ---
        if (empty(trim($dados['numero'] ?? ''))) {
            $erros['numero'] = 'Número é obrigatório.';
        } elseif (!is_numeric($dados['numero']) || $dados['numero'] < 1) {
            $erros['numero'] = 'Número deve ser um valor positivo.';
        }

        if (empty(trim($dados['logradouro'] ?? ''))) {
            $erros['logradouro'] = 'Logradouro é obrigatório.';
        }

        if (empty(trim($dados['cidade'] ?? ''))) {
            $erros['cidade'] = 'Cidade é obrigatória.';
        }

        // --- Validação de datas ---
        $inicio = trim($dados['inicio_contrato'] ?? '');
        $fim    = trim($dados['fim_contrato'] ?? '');

        if (!empty($inicio) && !self::dataValida($inicio)) {
            $erros['inicio_contrato'] = 'Data de início inválida.';
        }

        if (!empty($fim) && !self::dataValida($fim)) {
            $erros['fim_contrato'] = 'Data de fim inválida.';
        }

        // Garantia principal: fim_contrato > inicio_contrato
        if (!empty($inicio) && !empty($fim)
            && self::dataValida($inicio) && self::dataValida($fim)) {

            $dtInicio = new DateTime($inicio);
            $dtFim    = new DateTime($fim);

            if ($dtFim <= $dtInicio) {
                $erros['fim_contrato'] = 'A data de fim deve ser posterior à data de início.';
            }

            // Contrato mínimo: 15 dias
            $diff = $dtInicio->diff($dtFim)->days;
            if ($diff < 15) {
                $erros['fim_contrato'] = 'O contrato deve ter no mínimo 15 dias.';
            }

            // Contrato máximo: 12 meses (366 dias)
            if ($diff > 366) {
                $erros['fim_contrato'] = 'O contrato não pode exceder 12 meses.';
            }
        }

        return $erros;
    }

    /**
     * Verifica se uma string é uma data válida no formato Y-m-d ou d/m/Y
     */
    private static function dataValida(string $data): bool {
        // Aceita Y-m-d (banco) ou d/m/Y (formulário)
        foreach (array('Y-m-d', 'd/m/Y') as $formato) {
            $dt = DateTime::createFromFormat($formato, $data);
            if ($dt && $dt->format($formato) === $data) {
                return true;
            }
        }
        return false;
    }

    /**
     * Converte d/m/Y para Y-m-d (formato do banco)
     */
    public static function formatarDataBanco(string $data): ?string {
        $dt = DateTime::createFromFormat('d/m/Y', $data);
        if ($dt) return $dt->format('Y-m-d');
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        if ($dt) return $dt->format('Y-m-d');
        return null;
    }

    /**
     * Sanitiza string simples — usar em todos os campos texto
     */
    public static function sanitizar(string $valor): string {
        return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
    }
}


// ============================================================
// EXEMPLO DE USO NO CRUD (salvar_ponto.php)
// ============================================================
/*
require_once __DIR__ . '/../Services/ValidacaoPonto.php';

$dados = array(
    'numero'          => $_POST['numero']          ?? '',
    'logradouro'      => $_POST['logradouro']      ?? '',
    'cidade'          => $_POST['cidade']           ?? '',
    'inicio_contrato' => $_POST['inicio_contrato'] ?? '',
    'fim_contrato'    => $_POST['fim_contrato']    ?? '',
);

$erros = ValidacaoPonto::validar($dados);

if (!empty($erros)) {
    // Retornar erros para o formulário
    $_SESSION['erros']  = $erros;
    $_SESSION['dados']  = $dados;
    header('Location: /app/Views/gestor/pontos/form.php');
    exit;
}

// Dados válidos — salvar no banco
$stmt = $pdo->prepare("
    INSERT INTO pontos (numero, logradouro, cidade, inicio_contrato, fim_contrato)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute(array(
    $dados['numero'],
    ValidacaoPonto::sanitizar($dados['logradouro']),
    ValidacaoPonto::sanitizar($dados['cidade']),
    ValidacaoPonto::formatarDataBanco($dados['inicio_contrato']),
    ValidacaoPonto::formatarDataBanco($dados['fim_contrato']),
));
*/