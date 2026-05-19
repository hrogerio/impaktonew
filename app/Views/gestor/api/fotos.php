<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function jsonOk(array $data = []) {
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function jsonErro($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'erro' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['usuario']))             jsonErro('Não autorizado.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    jsonErro('Método inválido.', 405);

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== ($_SESSION['csrf_token'] ?? '')) jsonErro('Token inválido.', 403);

require_once __DIR__ . '/../../../../config/database.php';
$pdo = getDatabase();

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── Upload de foto ─────────────────────────────────────────
    case 'upload':
        $pontoId = (int)($_POST['ponto_id'] ?? 0);
        if ($pontoId <= 0) jsonErro('ID do ponto inválido.');

        $chk = $pdo->prepare("SELECT id, numero FROM pontos WHERE id = ? AND (ativo = 1 OR ativo IS NULL)");
        $chk->execute([$pontoId]);
        $ponto = $chk->fetch();
        if (!$ponto) jsonErro('Ponto não encontrado.');

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $erros = [0=>'ok',1=>'muito grande',2=>'muito grande',3=>'incompleto',4=>'nenhum arquivo',6=>'sem pasta tmp',7=>'sem permissão de escrita'];
            $cod   = $_FILES['foto']['error'] ?? 4;
            jsonErro('Erro no upload: ' . ($erros[$cod] ?? "código $cod"));
        }

        $file = $_FILES['foto'];
        if ($file['size'] > 8 * 1024 * 1024) jsonErro('Arquivo muito grande (máx. 8 MB).');

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extMap[$mime])) jsonErro('Formato inválido. Use JPG, PNG ou WEBP.');
        $ext = $extMap[$mime];

        $numLimpo = preg_replace('/[^a-z0-9]/i', '', $ponto['numero']);
        $nomeArq  = 'fotos/' . $numLimpo . '_' . uniqid() . '.' . $ext;
        $destino  = __DIR__ . '/../../../../' . $nomeArq;

        if (!is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            jsonErro('Falha ao salvar o arquivo no servidor.');
        }

        // Primeira foto do ponto vira capa automaticamente
        $conta = $pdo->prepare("SELECT COUNT(*) FROM ponto_fotos WHERE ponto_id = ?");
        $conta->execute([$pontoId]);
        $isPrimeira = (int)$conta->fetchColumn() === 0;

        $ins = $pdo->prepare("INSERT INTO ponto_fotos (ponto_id, caminho, ordem, principal) VALUES (?, ?, 0, ?)");
        $ins->execute([$pontoId, $nomeArq, $isPrimeira ? 1 : 0]);
        $novoId = (int)$pdo->lastInsertId();

        jsonOk([
            'foto' => [
                'id'        => $novoId,
                'caminho'   => $nomeArq,
                'principal' => $isPrimeira ? 1 : 0,
            ]
        ]);

    // ── Definir foto como capa ─────────────────────────────────
    case 'principal':
        $fotoId  = (int)($_POST['id']       ?? 0);
        $pontoId = (int)($_POST['ponto_id'] ?? 0);
        if ($fotoId <= 0 || $pontoId <= 0) jsonErro('Parâmetros inválidos.');

        $chk = $pdo->prepare("SELECT id FROM ponto_fotos WHERE id = ? AND ponto_id = ?");
        $chk->execute([$fotoId, $pontoId]);
        if (!$chk->fetch()) jsonErro('Foto não encontrada.');

        $pdo->prepare("UPDATE ponto_fotos SET principal = 0 WHERE ponto_id = ?")->execute([$pontoId]);
        $pdo->prepare("UPDATE ponto_fotos SET principal = 1 WHERE id = ?")->execute([$fotoId]);

        jsonOk();

    // ── Excluir foto ───────────────────────────────────────────
    case 'excluir':
        $fotoId = (int)($_POST['id'] ?? 0);
        if ($fotoId <= 0) jsonErro('ID inválido.');

        $chk = $pdo->prepare("SELECT * FROM ponto_fotos WHERE id = ?");
        $chk->execute([$fotoId]);
        $foto = $chk->fetch();
        if (!$foto) jsonErro('Foto não encontrada.');

        $pdo->prepare("DELETE FROM ponto_fotos WHERE id = ?")->execute([$fotoId]);

        $arquivo = __DIR__ . '/../../../../' . $foto['caminho'];
        if (file_exists($arquivo)) @unlink($arquivo);

        // Se era a capa, promove a próxima foto
        if ($foto['principal']) {
            $prox = $pdo->prepare("SELECT id FROM ponto_fotos WHERE ponto_id = ? ORDER BY ordem ASC, id ASC LIMIT 1");
            $prox->execute([$foto['ponto_id']]);
            $proxId = $prox->fetchColumn();
            if ($proxId) {
                $pdo->prepare("UPDATE ponto_fotos SET principal = 1 WHERE id = ?")->execute([$proxId]);
            }
        }

        jsonOk();

    default:
        jsonErro('Ação desconhecida.');
}
