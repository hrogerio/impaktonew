<?php
abstract class BaseController {
    protected function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    protected function redirect($url, $message = null, $type = 'success') {
        if ($message) {
            $_SESSION["flash_{$type}"] = $message;
        }
        
        // Garantir que a URL seja válida
        if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^\//', $url)) {
            $url = '/' . ltrim($url, '/');
        }
        
        header("Location: $url");
        exit;
    }
    
    protected function view($viewPath, $data = []) {
        // Extrair dados para as views
        extract($data);
        
        // Buffer de saída
        ob_start();
        
        // Incluir view
        $fullPath = __DIR__ . "/../Views/{$viewPath}.php";
        if (file_exists($fullPath)) {
            include $fullPath;
        } else {
            throw new Exception("View não encontrada: {$viewPath}");
        }
        
        $content = ob_get_clean();
        
        // Se for AJAX, retornar só o conteúdo
        if ($this->isAjax()) {
            echo $content;
        } else {
            // Incluir layout
            $title = $data['title'] ?? 'Sistema Impakto';
            include __DIR__ . '/../Views/layouts/app.php';
        }
    }
    
    protected function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    protected function validateCSRF() {
        $token = $_POST['_token'] ?? $_GET['_token'] ?? null;
        
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            if ($this->isAjax()) {
                $this->jsonResponse(['error' => 'Token CSRF inválido'], 403);
            } else {
                throw new Exception("Token CSRF inválido");
            }
        }
        
        return true;
    }
    
    protected function handleException(Exception $e, $message = 'Erro interno') {
        error_log("Erro no controller: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        
        if ($this->isAjax()) {
            $this->jsonResponse(['error' => $message], 500);
        } else {
            $this->view('errors/500', ['message' => $message]);
        }
    }
}