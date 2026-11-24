# 📋 ReqMaster - Sistema de Requisições de Serviços & Compra de Materiais

Sistema corporativo desenvolvido em **PHP 8.5** com banco de dados **MySQL**, projetado para gerenciar com total precisão o ciclo de vida de requisições de compras e contratação de serviços, cadastro de prestadores/fornecedores (**"para quem solicitar"**) e **gestão de contratos** com controle automático de saldo e vigência.

---

## 🚀 Funcionalidades Principais

### 1. Requisições Flexíveis (Materiais ou Serviços)
- **Compra de Materiais**: Controle de itens, marcas de referência, unidades (UN, CX, PCT, KG, M, L, etc.), quantidade e preço unitário.
- **Prestação de Serviços**: Definição de escopo, diárias/horas/global, prazos de atendimento e especificações técnicas.
- **Adição dinâmica de itens**: Cálculo em tempo real de subtotais e valor total da requisição.
- **Priorização e Justificativa**: Classificação em Baixa, Média, Alta e Urgente, com justificativa obrigatória da necessidade operacional.

### 2. Cadastro de "Para Quem Solicitar" (Fornecedores & Prestadores)
- Cadastro de **Pessoa Jurídica (CNPJ)** e **Pessoa Física (CPF)** com máscara e validação.
- Nome Fantasia, Razão Social, Segmento/Categoria de atuação.
- Contatos comerciais, telefone/WhatsApp e e-mail.
- **Dados Bancários e Chave Pix** para liquidação financeira ágil.
- Histórico completo de requisições e contratos vinculados ao fornecedor.

### 3. Gestão e Possibilidade de Vínculo com Contratos
- Cadastro de contratos com **número identificador (ex: CT-2026/001)**, objeto, vigência e cláusulas de renovação.
- **Teto Orçamentário & Saldo Remanescente**: Cada requisição aprovada desconta automaticamente do teto contratual.
- **Alerta em Tempo Real**: Na criação da requisição, ao selecionar o contrato, o sistema exibe o saldo livre restante e avisa caso a requisição ultrapasse o limite.
- **Painel de Alertas de Vencimento**: Aviso automático dos contratos que expiram nos próximos 60 dias.

### 4. Fluxo de Trabalho (Workflow) e Auditoria
- Estados: *Pendente*, *Em Análise*, *Aprovada*, *Rejeitada* (com modal de justificativa da recusa), *Em Execução* e *Concluída*.
- **Trilha de Auditoria**: Registro completo de quem criou, quem aprovou, data, hora, IP e motivo de qualquer alteração de status.

### 5. Documentos e Relatórios
- **Ficha Oficial de Requisição A4**: Layout profissional pronto para impressão física ou geração em PDF (Ctrl+P) com campos formais para assinatura do Solicitante, Aprovador e Fornecedor.
- **Exportação para Excel (CSV)** com codificação UTF-8 BOM.
- **Gráficos e Indicadores no Dashboard** com métricas financeiras e de volume.

---

## 🛠️ Tecnologias Utilizadas

- **PHP 8.5** (Compatível com PHP 8.2 a 8.5+)
  - Tipagem estrita (`declare(strict_types=1);`), *match expressions*, *nullsafe operator*, PDO com *prepared statements*.
- **MySQL 8.0+ / MariaDB 10.4+**
  - Chaves estrangeiras com integridade referencial (*Foreign Keys*), índices e charset `utf8mb4`.
- **Frontend Moderno**:
  - Tailwind CSS 3 (via CDN otimizado)
  - Font Awesome 6 (Ícones vetoriais)
  - Google Fonts Inter
  - Chart.js (Gráficos interativos)
  - JavaScript nativo puro (cálculos em tempo real e máscaras dinâmicas)

---

## 📂 Estrutura do Projeto

```text
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php         # Login, logout e controle de sessão
│   │   ├── DashboardController.php    # Métricas, KPIs e gráficos
│   │   ├── RequisitionController.php  # Fluxo completo de requisições e itens
│   │   ├── SupplierController.php     # CRUD de Fornecedores/Prestadores
│   │   ├── ContractController.php     # Gestão e controle de saldo de contratos
│   │   └── ReportController.php       # Relatórios e exportação CSV
│   ├── Helpers/
│   │   └── Security.php               # CSRF, senhas, sanitização e permissões
│   └── Models/
│       ├── Model.php                  # PDO base
│       ├── User.php                   # Usuários e perfis
│       ├── Supplier.php               # Fornecedores
│       ├── Contract.php               # Contratos e cálculo de saldo
│       ├── Requisition.php            # Requisições e itens
│       └── ActivityLog.php            # Trilha de auditoria
├── config/
│   ├── config.php                     # Constantes, helpers e formatadores
│   └── database.php                   # Conexão PDO segura com MySQL
├── install/
│   ├── index.php                      # Assistente de Setup Automatizado do Banco
│   └── installed.lock                 # Trava de segurança pós-instalação
├── public/
│   ├── assets/js/app.js               # Cálculos em tempo real e AJAX
│   ├── .htaccess                      # Reescríta de URL no Apache
│   └── index.php                      # Front Controller & Roteador
├── views/
│   ├── auth/                          # Telas de login, 2FA e perfil do usuário
│   ├── dashboard/                     # Painel de controle visual
│   ├── requisitions/                  # Listagem, criação, edição, exibição e impressão
│   ├── suppliers/                     # Cadastro e histórico de fornecedores
│   ├── contracts/                     # Gestão e acompanhamento de saldo
│   ├── reports/                       # Relatórios gerenciais
│   └── layout/                        # Header, footer e modais
├── database.sql                       # Esquema relacional do banco (sem dados fictícios)
├── docker-compose.yml                 # Subida em 1 comando com Docker
└── README.md                          # Este manual
```

---

## ⚡ Instalação & Setup Automatizado (Recomendado)

O sistema conta com um **assistente visual de instalação** seguro:

1. Crie previamente um **usuário dedicado e senha forte** no MySQL com privilégios vinculados exclusivamente ao banco de dados do sistema.
2. Inicie o seu servidor web e o MySQL.
3. Acesse `/install` no navegador.
4. Informe as credenciais do usuário MySQL criado, defina o administrador principal com senha forte e conclua a instalação.
5. 🚨 **IMPORTANTE (SEGURANÇA)**: Após a conclusão do setup, **APAGUE A PASTA `install`** imediatamente (há um botão para exclusão automática na tela final).

---

## ⚡ Outras Formas de Instalação (Manual & Docker)

### Opção Manual no phpMyAdmin

1. **Inicie o Apache e o MySQL** no painel do XAMPP ou Laragon.
2. Abra o **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Crie um banco de dados chamado `sistema_requisicoes`.
4. Clique na aba **Importar** e selecione o arquivo `database.sql` deste projeto.
5. Coloque a pasta do projeto dentro de `C:\xampp\htdocs\` ou `C:\laragon\www\`.
6. Acesse no navegador:
   ```text
   http://localhost/SOFTWARE%20DE%20requisição/public/
   ```
   *(ou aponte o Virtual Host diretamente para a pasta `public/`)*.

---

### Opção 2: Usando o Servidor Embutido do PHP

1. Certifique-se de que o serviço do MySQL está rodando na porta 3306 e importe o arquivo `database.sql`.
2. Abra o terminal (PowerShell ou CMD) nesta pasta e execute:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Abra o seu navegador em:
   ```text
   http://localhost:8000
   ```

---

### Opção 3: Usando Docker (1 comando)

Se tiver o Docker instalado, basta rodar:
```bash
docker compose up -d
```
O container configurará automaticamente o Apache com PHP e extensão PDO MySQL em `http://localhost:8080`.
