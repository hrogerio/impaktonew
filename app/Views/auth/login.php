<?php
// ============================================
// app/Views/auth/login.php
?>
<div class="login-container">
    <div class="login-box">
        <div class="logo">
            <img src="/assets/img/logo.png" alt="Logo Impakto">
        </div>
        
        <h2>Acesso ao Sistema</h2>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="/login">
            <input type="hidden" name="_token" value="<?= $csrf_token ?? '' ?>">
            
            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text" id="usuario" name="usuario" required 
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
    </div>
</div>

<style>
.login-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-box {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 400px;
}

.logo img {
    max-width: 150px;
    display: block;
    margin: 0 auto 2rem;
}

.alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.alert-error {
    background-color: #fee;
    color: #c53030;
    border: 1px solid #fed7d7;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-group input:focus {
    border-color: #C0392B;
    outline: none;
    box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.1);
}

.btn {
    width: 100%;
    padding: 12px;
    background-color: #C0392B;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn:hover {
    background-color: #a12a20;
}
</style>