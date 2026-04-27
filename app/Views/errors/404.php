<?php
// ============================================
// app/Views/errors/404.php
?>
<div class="error-page">
    <div class="error-container">
        <div class="error-code">404</div>
        <h1>Página Não Encontrada</h1>
        <p>A página que você está procurando não existe ou foi movida.</p>
        <a href="/gestor" class="btn btn-primary">Voltar ao Dashboard</a>
    </div>
</div>

<style>
.error-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    text-align: center;
}

.error-container {
    max-width: 500px;
    padding: 2rem;
}

.error-code {
    font-size: 8rem;
    font-weight: 700;
    color: #C0392B;
    line-height: 1;
    margin-bottom: 1rem;
}

.error-container h1 {
    font-size: 2rem;
    color: #333;
    margin-bottom: 1rem;
}

.error-container p {
    color: #666;
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    background-color: #C0392B;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: background-color 0.3s;
}

.btn:hover {
    background-color: #a12a20;
}
</style>

<?php
// ============================================
// app/Views/errors/500.php
?>
<div class="error-page">
    <div class="error-container">
        <div class="error-code">500</div>
        <h1>Erro Interno</h1>
        <p><?= htmlspecialchars($message ?? 'Ocorreu um erro interno no servidor. Tente novamente mais tarde.') ?></p>
        <a href="/gestor" class="btn btn-primary">Voltar ao Dashboard</a>
    </div>
</div>

<style>
.error-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    text-align: center;
}

.error-container {
    max-width: 500px;
    padding: 2rem;
}

.error-code {
    font-size: 8rem;
    font-weight: 700;
    color: #E74C3C;
    line-height: 1;
    margin-bottom: 1rem;
}

.error-container h1 {
    font-size: 2rem;
    color: #333;
    margin-bottom: 1rem;
}

.error-container p {
    color: #666;
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    background-color: #C0392B;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: background-color 0.3s;
}

.btn:hover {
    background-color: #a12a20;
}
</style>