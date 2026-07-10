# Impakto Mídia — Sistema de Gestão OOH

## Visão Geral
Sistema web de gestão de pontos de mídia exterior (OOH), desenvolvido em PHP puro com padrão MVC manual. Operado pela Impakto Mídia OOH, empresa do Nordeste com painéis na BR-232 e presença em PE, PB, RN e AL.

---

## Stack
- **Backend:** PHP puro (sem framework), padrão MVC manual
- **Banco de dados:** MySQL
- **PDF:** tFPDF (suporte a fontes TTF/Unicode) com fallback para FPDF
- **Mapas:** Google Maps JavaScript API
- **Ambiente local:** Laragon (Windows)
- **Ambiente produção:** cPanel / UOLHost (Linux)
- **Versionamento:** Git + GitHub (`hrogerio/impaktonew`)
- **Deploy:** automático via cPanel Git (quando não divergente)

---

## Estrutura do Projeto
```
impaktonew/
├── app/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Views/
│       └── gestor/
│           ├── campanhas/
│           │   └── checking_pdf.php   ← geração de PDF de checking
│           ├── campanha.php
│           ├── ponto_detalhes.php
│           └── ...
├── config/
├── database/
├── gestor/
│   └── index.php                      ← dashboard principal
├── lib/
│   └── fpdf/
│       ├── tfpdf.php
│       └── font/
│           └── unifont/
│               ├── Inter-Regular.ttf
│               ├── Inter-Medium.ttf
│               ├── Inter-SemiBold.ttf
│               ├── inter-regular.mtx.php
│               ├── inter-medium.mtx.php
│               └── inter-semibold.mtx.php
├── public/
├── storage/
│   └── logs/
├── index.php                          ← ponto de entrada principal
├── .env                               ← NÃO vai para o Git
├── .env.example                       ← template do .env
└── CLAUDE.md                          ← este arquivo
```

---

## Banco de Dados

### Produção (UOLHost)
```
DB_HOST=localhost
DB_NAME=impaktom14266d67_ipk2024
DB_USER=impaktom14266d67_ipk
```

### Local (Laragon)
```
DB_HOST=localhost
DB_NAME=impaktomidia
DB_USER=root
DB_PASS=        ← vazio
```

### Tabelas principais
| Tabela | Descrição |
|---|---|
| `pontos` | Cadastro de pontos OOH |
| `pontos_backup` | Backup dos pontos |
| `pontos_log` | Log de alterações |
| `ponto_fotos` | Fotos dos pontos |
| `campanhas` | Campanhas/contratos ativos |
| `checking_fotos` | Fotos de checking de campanha |
| `pre_selecao_pontos` | Pontos em pré-seleção |
| `pre_selecoes` | Pré-seleções (propostas) |
| `admins` | Usuários administradores |

---

## Ambiente Local — Observações Importantes

### MySQL 8.4 (Laragon)
O MySQL 8.4 é mais estrito que o servidor de produção. Requer ajuste do `sql_mode` para aceitar datas legadas (`0000-00-00` e `''`).

**Correção permanente** — já aplicada em `C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini`:
```ini
[mysqld]
sql_mode=NO_ENGINE_SUBSTITUTION
```

**Correção temporária** (se necessário após restart):
```bash
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -e "SET GLOBAL sql_mode = 'NO_ENGINE_SUBSTITUTION';"
```

---

## Configuração do .env

O `.env` está no `.gitignore` e nunca vai para o Git. Cada ambiente tem o seu próprio.

**Template** (`.env.example`):
```
DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=

APP_NAME="Impakto Mídia"
APP_ENV=
APP_DEBUG=
APP_URL=
APP_KEY=

GOOGLE_MAPS_KEY=
SESSION_LIFETIME=120
SESSION_HTTP_ONLY=true
SESSION_SECURE=
```

---

## Deploy

O deploy é automático via **GitHub Actions + FTP** (`.github/workflows/deploy.yml`), não via cPanel Git — o `.cpanel.yml` na raiz não é usado nesse fluxo.

### Fluxo normal
```bash
git add .
git commit -m "tipo: descrição"
git push origin main
# GitHub Actions ("🚀 Deploy para Produção") envia via FTP pra public_html automaticamente
```
Acompanhar em: https://github.com/hrogerio/impaktonew/actions (leva ~1-3 min; primeira execução após uma mudança nos secrets/config pode ser mais lenta por ser sync completo).

**Credenciais de FTP** ficam em Settings → Secrets and variables → Actions do repositório (`FTP_HOST`, `FTP_USER`, `FTP_PASS`). Conta atual: `deploy@impaktomidia.com.br`.

**Atenção ao host de FTP:** `ftp.impaktomidia.com.br` tem um DNS desatualizado (aponta pra um servidor antigo, ProFTPD, que não tem as contas atuais). O servidor real responde em `impaktomidia.com.br` (ou `a16-asgard8.hospedagemuolhost.com.br`), rodando Pure-FTPd — é esse host que deve estar em `FTP_HOST`.

O workflow ignora `.git*`, `.env`, `fotos/`, `storage/`, `database/`, `.claude/` (não sobrescreve uploads nem configs sensíveis).

### Se o deploy falhar
Ver o log da execução em Actions. Erros comuns: secret ausente/errado, ou host de FTP errado (ver aviso acima).

### Rollback de emergência
```bash
git revert <hash-do-commit>
git push origin main
# Isso gera um novo commit que desfaz a mudança e dispara um novo deploy automático
```

---

## Bugs Conhecidos / Já Corrigidos

| Arquivo | Problema | Solução |
|---|---|---|
| `gestor/index.php` | `DATE value: ''` no MySQL 8.4 | Substituir `!= ''` por `IS NOT NULL` |
| `lib/fpdf/font/unifont/*.mtx.php` | Caminho hardcoded Windows no `$ttffile` | Usar `__DIR__.'/NomeDaFonte.ttf'` |

---

## Convenções de Commit
```
feat: nova funcionalidade
fix: correção de bug
ci: configuração de CI/CD
refactor: refatoração sem mudança de comportamento
style: mudanças de CSS/layout
docs: documentação
```

---

## Acesso ao Sistema
- **Produção:** https://impaktomidia.com.br/gestor
- **Local:** http://localhost/gestor
- **cPanel:** https://a16-asgard8.hospedagemuolhost.com.br:2083
- **Usuário cPanel:** impaktom14266d67
