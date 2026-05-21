<?php
// Redirecionador temporário de ponto público
// URL: /public/p.php?id=13
// Redireciona para a página de detalhes do ponto
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); die('Ponto não encontrado.'); }
header("Location: /gestor/pontos/detalhes?id={$id}&view=publico");
exit;
