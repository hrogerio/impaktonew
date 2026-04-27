<?php
// ============================================
// README.md
?>
# 🎯 Sistema de Gestão - Impakto Mídia

Sistema completo para gestão de pontos de mídia exterior com interface moderna e funcionalidades avançadas.

## ⚡ Funcionalidades

- 🔐 **Autenticação Segura** - Login com proteção CSRF
- 📊 **Dashboard** - Visão geral com métricas importantes  
- 🔍 **Busca Avançada** - Localização rápida de pontos
- 📄 **Relatórios** - Pré-seleção e exportação
- 📱 **Responsivo** - Interface adaptada para mobile
- ⚡ **Performance** - Cache e otimizações

## 🛠️ Tecnologias

- **Backend**: PHP 7.4+ com arquitetura MVC
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **Cache**: Sistema de cache em arquivo
- **Segurança**: Proteção CSRF, validação robusta

## 📁 Estrutura do Projeto

```
project/
├── app/                 # Lógica da aplicação
│   ├── Controllers/     # Controladores
│   ├── Models/         # Modelos de dados
│   ├── Services/       # Serviços
│   └── Views/          # Templates
├── config/             # Configurações
├── public/             # Arquivos públicos
├── storage/            # Cache, logs, uploads
└── .env               # Variáveis de ambiente
```

## 🚀 Instalação

1. **Clone o repositório**
```bash
git clone [url-do-repositorio]
cd impakto-system
```

2. **Configure o ambiente**
```bash
cp .env.example .env
# Edite o .env com suas configurações
```

3. **Configure o servidor web**
- Aponte o DocumentRoot para `/public`
- Habilite mod_rewrite (Apache)

4. **Configure permissões**
```bash
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/cache/
```

## ⚙️ Configuração

### Banco de Dados
```env
DB_HOST=seu-host
DB_NAME=sua-base
DB_USER=seu-usuario  
DB_PASS=sua-senha
```

### Aplicação
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com
```

## 📝 Changelog

### v2.0.0 (2024)
- ✅ Nova arquitetura MVC
- ✅ Sistema de segurança robusto
- ✅ Interface moderna
- ✅ Sistema de cache
- ✅ Logs estruturados

### v1.0.0 (2023)
- ✅ Versão inicial
- ✅ CRUD básico de pontos
- ✅ Sistema de autenticação

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch (`git checkout -b feature/nova-funcionalidade`)
3. Commit suas mudanças (`git commit -am 'Add nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um Pull Request

## 📞 Suporte

Para suporte técnico, entre em contato:
- Email: suporte@impaktomidia.com.br
- Site: https://impaktomidia.com.br

## 📜 Licença

© 2024 Impakto Mídia. Todos os direitos reservados.