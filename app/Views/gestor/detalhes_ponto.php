<?php
session_start();

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../../../public/index.php");
    exit;
}

if (!isset($_SESSION['usuario'])) {
    header("Location: ../../../public/index.php?erro=nao_logado");
    exit;
}

// Conectar ao banco
try {
    require_once __DIR__ . '/../../../config/database.php';
    $pdo = getDatabase();
} catch (Exception $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Pegar ID do ponto
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: listar_ponto.php");
    exit;
}

// Buscar dados do ponto
$sql = "SELECT * FROM pontos WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$ponto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ponto) {
    header("Location: listar_ponto.php?erro=ponto_nao_encontrado");
    exit;
}

function formatarDataCompleta($data) {
    if (!$data || $data === '0000-00-00') {
        return '-';
    }
    try {
        $date = new DateTime($data);
        return $date->format('d/m/Y');
    } catch (Exception $e) {
        return 'Data inválida';
    }
}

function badgeSituacao($situacao) {
    $situacao = trim($situacao);
    
    $classes = [
        'Disponível' => 'situacao-disponivel',
        'Ocupado' => 'situacao-ocupado',
        'Reservado' => 'situacao-reservado',
        'Vencido' => 'situacao-vencido',
        'Permuta' => 'situacao-permuta',
        'Bisemana' => 'situacao-bisemana',
    ];
    
    $class = $classes[$situacao] ?? 'situacao-outro';
    
    return "<span class='badge-situacao {$class}'>" . htmlspecialchars($situacao) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/impaktonew/public/img/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <title>Ponto <?= htmlspecialchars($ponto['numero']) ?> - Impakto Mídia</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #C0392B;
            --primary-dark: #A93226;
            --bg: #f0f2f5;
            --card-bg: #ffffff;
            --text: #1a1a1a;
            --text-secondary: #4a4a4a;
            --text-muted: #6c757d;
            --border: #dfe3e8;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            font-size: 15px;
        }
        
        .header {
            background: var(--card-bg);
            box-shadow: var(--shadow-sm);
            padding: 0.7rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1700px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-img {
            height: 36px;
        }
        
        .btn-voltar {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1.3rem;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 7px;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(192, 57, 43, 0.2);
        }
        
        .btn-voltar:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(192, 57, 43, 0.35);
        }
        
        .container {
            max-width: 1700px;
            margin: 0 auto;
            padding: 1rem 1.5rem 3rem;
        }
        
        .numero-titulo {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
            letter-spacing: -1px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 1.2rem;
        }
        
        .coluna-esquerda {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .coluna-direita {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1.1rem 1.3rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.9rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        
        .info-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f5f5f5;
            align-items: flex-start;
            gap: 0.9rem;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 700;
            color: var(--text);
            min-width: 110px;
            font-size: 13.5px;
        }
        
        .info-value {
            color: var(--text-secondary);
            flex: 1;
            font-size: 13.5px;
            line-height: 1.6;
            font-weight: 500;
        }
        
        .media-card {
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            border: 2px solid var(--border);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .media-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .foto-container {
            width: 100%;
            height: 420px;
            border-radius: 8px;
            overflow: hidden;
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .foto-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .foto-container:hover img {
            transform: scale(1.05);
        }
        
        .sem-foto {
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 600;
        }
        
        .mapa-container {
            width: 100%;
            height: 420px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .coordenadas {
            text-align: center;
            margin-top: 0.7rem;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
        }
        
        .coordenadas a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: all 0.2s;
        }
        
        .coordenadas a:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }
        
        .badge-situacao {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        
        .situacao-disponivel { background: #1a9059ff; color: white; }
        .situacao-ocupado { background: #dc3545; color: white; }
        .situacao-reservado { background: #fd7e14; color: white; }
        .situacao-vencido { background: #6c757d; color: white; }
        .situacao-permuta { background: #51086eff; color: white; }
        .situacao-bisemana { background: #0dcaf0; color: white; }
        .situacao-outro { background: #198754ff; color: white; }
        
        @media (max-width: 1200px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .foto-container,
            .mapa-container {
                height: 450px;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .numero-titulo {
                font-size: 1.8rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .card {
                padding: 1.2rem;
            }
            
            .foto-container,
            .mapa-container {
                height: 400px;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <div class="logo">
            <img src="/impaktonew/public/assets/img/logo.png" alt="Impakto Mídia" class="logo-img">
        </div>
        <a href="listar_ponto.php" class="btn-voltar">
            ← Voltar
        </a>
    </div>
</div>

<div class="container">
    <div class="numero-titulo"><?= htmlspecialchars($ponto['numero']) ?></div>
    
    <div class="grid">
        <!-- Coluna Esquerda -->
        <div class="coluna-esquerda">
            <!-- Informações Principais -->
            <div class="card">
                <h2 class="card-title">Informações do Ponto</h2>
                
                <div class="info-row">
                    <div class="info-label">Logradouro:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['logradouro'] ?? '-') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Descrição:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['descricao'] ?? '-') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Sentido:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['sentido'] ?? '-') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Cidade:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['cidade'] ?? '-') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Região:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['regiao'] ?? '-') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Tipo:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['tipo'] ?? '-') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Formato:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['formato'] ?? '-') ?></div>
                </div>
            </div>
            
            <!-- Informações Comerciais -->
            <div class="card">
                <h2 class="card-title">Informações Comerciais</h2>
                
                <div class="info-row">
                    <div class="info-label">Cliente:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['cliente'] ?? '-') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Agência:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['agencia'] ?? '-') ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">Situação:</div>
                    <div class="info-value"><?= badgeSituacao($ponto['situacao'] ?? 'N/A') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Início:</div>
                    <div class="info-value"><?= formatarDataCompleta($ponto['inicio_contrato']) ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Fim:</div>
                    <div class="info-value"><?= formatarDataCompleta($ponto['fim_contrato']) ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Observações:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['observacoes'] ?? '-') ?></div>
                </div>                

                <div class="info-row">
                    <div class="info-label">Contato:</div>
                    <div class="info-value"><?= htmlspecialchars($ponto['contato'] ?? '-') ?></div>
                </div>
            </div>
        </div>
        
        <!-- Coluna Direita -->
        <div class="coluna-direita">
            <!-- Foto -->
            <div class="card media-card">
                <h2 class="card-title">📷 Foto do Ponto</h2>
                <div class="foto-container">
                    <?php if (!empty($ponto['foto'])): ?>
                        <img src="/impaktonew/<?= htmlspecialchars($ponto['foto']) ?>" 
                             alt="Foto do ponto <?= htmlspecialchars($ponto['numero']) ?>"
                             onerror="this.parentElement.innerHTML='<div class=\'sem-foto\'>Foto não disponível</div>'">
                    <?php else: ?>
                        <div class="sem-foto">📸 Sem foto cadastrada</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Mapa -->
            <div class="card media-card">
                <h2 class="card-title">📍 Localização</h2>
                <?php if (!empty($ponto['latitude']) && !empty($ponto['longitude'])): ?>
                    <div class="mapa-container" id="map"></div>
                    <div class="coordenadas">
                        <strong>Ponto <?= htmlspecialchars($ponto['numero']) ?></strong> - <?= htmlspecialchars($ponto['logradouro']) ?>
                        <br>
                        <?= htmlspecialchars($ponto['latitude']) ?>°S <?= htmlspecialchars($ponto['longitude']) ?>°W
                        <br>
                        <a href="https://www.google.com/maps?q=<?= $ponto['latitude'] ?>,<?= $ponto['longitude'] ?>&ll=<?= $ponto['latitude'] ?>,<?= $ponto['longitude'] ?>&z=17" 
                           target="_blank">🌐 Ver no Google Maps</a>
                    </div>
                <?php else: ?>
                    <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📍</div>
                        <div style="font-weight: 600;">Coordenadas não cadastradas</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($ponto['latitude']) && !empty($ponto['longitude'])): ?>
<script>
    function initMap() {
        const position = {
            lat: <?= floatval($ponto['latitude']) ?>,
            lng: <?= floatval($ponto['longitude']) ?>
        };
        
        const map = new google.maps.Map(document.getElementById('map'), {
            zoom: 17,
            center: position,
            mapTypeId: 'satellite',
            mapTypeControl: true,
            mapTypeControlOptions: {
                style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR,
                position: google.maps.ControlPosition.TOP_RIGHT,
                mapTypeIds: ['roadmap', 'satellite', 'hybrid', 'terrain']
            },
            streetViewControl: true,
            fullscreenControl: true,
            zoomControl: true
        });
        
        const contentString = `
            <div style="font-family: 'Montserrat', sans-serif; padding: 8px; min-width: 220px;">
                <div style="font-size: 17px; font-weight: 800; color: #C0392B; margin-bottom: 8px;">
                    Ponto <?= htmlspecialchars($ponto['numero']) ?>
                </div>
                <div style="font-size: 14px; font-weight: 600; color: #2c3e50; margin-bottom: 6px; line-height: 1.4;">
                    <?= htmlspecialchars($ponto['logradouro']) ?>
                </div>
                <div style="font-size: 12px; color: #6c757d; margin-top: 8px;">
                    📍 <?= htmlspecialchars($ponto['cidade']) ?> - <?= htmlspecialchars($ponto['regiao']) ?>
                </div>
            </div>
        `;
        
        const infoWindow = new google.maps.InfoWindow({
            content: contentString,
            ariaLabel: 'Ponto <?= htmlspecialchars($ponto['numero']) ?>'
        });
        
        const marker = new google.maps.Marker({
            position: position,
            map: map,
            title: 'Ponto <?= htmlspecialchars($ponto['numero']) ?>',
            animation: google.maps.Animation.DROP
        });
        
        marker.addListener('click', () => {
            infoWindow.open({
                anchor: marker,
                map
            });
        });
        
        // Abrir automaticamente ao carregar
        infoWindow.open({
            anchor: marker,
            map
        });
    }

    window.initMap = initMap;
</script>

<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC0IQ7LJoqCJAZwB___0i-641DqHSVjBaM&callback=initMap&v=weekly">
</script>

<style>
    .gm-style-iw {
        border-radius: 8px !important;
    }
    
    .gm-style-iw-d {
        overflow: hidden !important;
    }
    
    .gm-style .gm-style-iw-c {
        padding: 0 !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    }
</style>
<?php endif; ?>

</body>
</html>