<?php
// app/Services/ImageOptimizationService.php
class ImageOptimizationService {
    private $uploadDir;
    private $maxWidth = 1200;
    private $maxHeight = 800;
    private $quality = 85;
    
    public function __construct($uploadDir) {
        $this->uploadDir = $uploadDir;
    }
    
    public function optimizeAndSave($uploadedFile, $filename) {
        $originalPath = $uploadedFile['tmp_name'];
        $targetPath = $this->uploadDir . '/' . $filename;
        
        // Detectar tipo de imagem
        $imageInfo = getimagesize($originalPath);
        if (!$imageInfo) {
            throw new Exception("Arquivo não é uma imagem válida");
        }
        
        $mimeType = $imageInfo['mime'];
        
        // Criar resource da imagem original
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($originalPath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($originalPath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($originalPath);
                break;
            default:
                throw new Exception("Tipo de imagem não suportado");
        }
        
        if (!$sourceImage) {
            throw new Exception("Erro ao processar imagem");
        }
        
        // Calcular novas dimensões
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);
        
        $ratio = min($this->maxWidth / $originalWidth, $this->maxHeight / $originalHeight, 1);
        
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);
        
        // Criar nova imagem redimensionada
        $optimizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preservar transparência para PNG
        if ($mimeType === 'image/png') {
            imagealphablending($optimizedImage, false);
            imagesavealpha($optimizedImage, true);
        }
        
        // Redimensionar
        imagecopyresampled(
            $optimizedImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );
        
        // Salvar otimizada
        $success = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $success = imagejpeg($optimizedImage, $targetPath, $this->quality);
                break;
            case 'image/png':
                $success = imagepng($optimizedImage, $targetPath, 9);
                break;
            case 'image/gif':
                $success = imagegif($optimizedImage, $targetPath);
                break;
        }
        
        // Limpar memória
        imagedestroy($sourceImage);
        imagedestroy($optimizedImage);
        
        if (!$success) {
            throw new Exception("Erro ao salvar imagem otimizada");
        }
        
        return [
            'filename' => $filename,
            'original_size' => filesize($originalPath),
            'optimized_size' => filesize($targetPath),
            'compression_ratio' => round((1 - filesize($targetPath) / filesize($originalPath)) * 100, 1)
        ];
    }
}

// SQL para criar índices otimizados
/*
-- Índices para otimização de consultas
ALTER TABLE pontos ADD FULLTEXT(logradouro, descricao, cliente);
ALTER TABLE pontos ADD INDEX idx_ativo_situacao (ativo, situacao);
ALTER TABLE pontos ADD INDEX idx_ativo_regiao (ativo, regiao);
ALTER TABLE pontos ADD INDEX idx_ativo_tipo (ativo, tipo);
ALTER TABLE pontos ADD INDEX idx_ativo_cidade (ativo, cidade);
ALTER TABLE pontos ADD INDEX idx_fim_contrato (fim_contrato);
ALTER TABLE pontos ADD INDEX idx_numero (numero);
ALTER TABLE pontos ADD INDEX idx_cliente (cliente);

-- Índice composto para consultas com filtros múltiplos
ALTER TABLE pontos ADD INDEX idx_filtros_complexos (ativo, situacao, regiao, tipo, cidade);

-- Índice para ordenação por vencimento
ALTER TABLE pontos ADD INDEX idx_vencimento_ordenacao (
    ativo,
    CASE 
        WHEN fim_contrato IS NULL OR fim_contrato = '0000-00-00' OR fim_contrato = '' THEN 1
        ELSE 0 
    END,
    fim_contrato
);
*/