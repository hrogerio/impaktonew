<?php
/**
 * Registra quem ativou/desativou um ponto no pontos_log.
 *
 * O trigger trg_pontos_update já grava a mudança do campo `ativo`, mas sempre
 * com alterado_por = 'sistema'. Aqui carimbamos o usuário nessa linha recém-criada;
 * se o trigger não existir no ambiente, insere a linha manualmente.
 * Falhas são ignoradas: o log nunca deve impedir a ativação/desativação.
 */
function registrarMudancaAtivo(PDO $pdo, int $pontoId, int $novoValor, string $usuario): void {
    try {
        $upd = $pdo->prepare("
            UPDATE pontos_log SET alterado_por = ?
            WHERE ponto_id = ? AND campo = 'ativo' AND valor_depois = ?
              AND alterado_por = 'sistema' AND alterado_em >= NOW() - INTERVAL 1 MINUTE
            ORDER BY id DESC LIMIT 1
        ");
        $upd->execute([$usuario, $pontoId, (string)$novoValor]);

        if ($upd->rowCount() === 0) {
            $pdo->prepare("
                INSERT INTO pontos_log (ponto_id, numero, campo, valor_antes, valor_depois, alterado_por)
                SELECT id, numero, 'ativo', ?, ?, ? FROM pontos WHERE id = ?
            ")->execute([(string)(1 - $novoValor), (string)$novoValor, $usuario, $pontoId]);
        }
    } catch (Exception $e) {
        // log é opcional
    }
}
