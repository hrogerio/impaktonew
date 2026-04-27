<?php
// app/Controllers/AuthController.php
class AuthController extends BaseController {
    private $usuarioModel;
    
    public function __construct() {
        $this->usuarioModel = new UsuarioModel();
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->validateCSRF();
                
                $usuario = ValidationService::sanitizeString($_POST['usuario']);
                $senha = $_POST['senha'] ?? '';
                
                if (empty($usuario) || empty($senha)) {
                    throw new Exception("Usuário e senha são obrigatórios");
                }
                
                $user = $this->usuarioModel->buscarPorCredenciais($usuario, $senha);
                
                if (!$user) {
                    // Log da tentativa de login inválida
                    error_log("Tentativa de login inválida para usuário: $usuario");
                    throw new Exception("Usuário ou senha incorretos");
                }
                
                // Configurar sessão
                session_regenerate_id(true);
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['last_activity'] = time();
                
                // Log do login bem-sucedido
                error_log("Login bem-sucedido para usuário: {$user['usuario']}");
                
                $this->redirect('/gestor/', 'Login realizado com sucesso!');
                
            } catch (Exception $e) {
                $this->view('auth/login', ['erro' => $e->getMessage()]);
            }
        } else {
            // Se já está logado, redireciona
            if (isset($_SESSION['usuario'])) {
                $this->redirect('/gestor/');
            }
            
            $this->view('auth/login', ['csrf_token' => AuthMiddleware::generateCSRF()]);
        }
    }
    
    public function logout() {
        session_start();
        
        // Log do logout
        if (isset($_SESSION['usuario'])) {
            error_log("Logout realizado para usuário: {$_SESSION['usuario']}");
        }
        
        // Limpar sessão
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        $this->redirect('/', 'Logout realizado com sucesso!');
    }
}