<?php
// app/Models/UsuarioModel.php
require_once __DIR__ . '/BaseModel.php';

class UsuarioModel extends BaseModel {
    protected $table = 'admins';
    protected $fillable = ['usuario', 'senha', 'email', 'ativo'];
    
    public function buscarPorCredenciais($usuario, $senha) {
        $sql = "SELECT id, usuario, senha FROM {$this->table} WHERE usuario = :usuario AND ativo = 1 LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario', $usuario);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($senha, $user['senha'])) {
            // Remove senha do retorno por segurança
            unset($user['senha']);
            return $user;
        }
        
        return false;
    }
    
    public function criarComSenhaHash(array $data) {
        if (isset($data['senha'])) {
            $data['senha'] = password_hash($data['senha'], PASSWORD_DEFAULT);
        }
        
        return $this->create($data);
    }
    
    public function atualizarUltimoLogin($userId) {
        $sql = "UPDATE {$this->table} SET ultimo_login = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}