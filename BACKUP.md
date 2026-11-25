# 💾 Guia Oficial de Backup Completo & Restauração — ReqMaster

Manter backups frequentes e completos é essencial para proteger os dados das requisições, históricos de manutenção, trilha de auditoria, contratos, anexos e licenciamento do sistema contra falhas de hardware, exclusões acidentais ou incidentes durante atualizações.

> **⚠️ Recomendação:** Realize um backup completo **antes de aplicar qualquer atualização do sistema** e mantenha uma rotina automática **diária ou semanal** armazenada fora do servidor principal (HD externo, NAS ou nuvem).

---

## 📦 O que compõe um Backup Completo do ReqMaster?

Para garantir uma recuperação 100% funcional em outro servidor ou após uma falha, você precisa salvar **dois conjuntos de dados**:

1. **Banco de Dados MySQL** (todas as tabelas: `users`, `suppliers`, `contracts`, `requisitions`, `requisition_items`, `requisition_attachments`, `products`, `product_photos`, `services`, `cost_centers`, `activity_logs`, `settings`, `security_attempts`).
2. **Arquivos Essenciais do Servidor**:
   - `config/db_config.php` — credenciais de conexão exclusiva com o MySQL;
   - `config/vault/` — cofre criptografado da licença (`license.enc`) e manifesto de versões (`manifest.enc`);
   - `public/uploads/` — fotos das requisições, anexos PDF, fotos de produtos, logos da empresa, avatares e assinaturas digitais.

*(Se desejar, você também pode compactar toda a pasta da aplicação para preservar versões customizadas de `.htaccess` ou ajustes locais.)*

---

## 🐧 Como fazer Backup no Linux (Ubuntu Server)

Considere no exemplo abaixo que o sistema está instalado em `/var/www/html/reqmaster` e o banco se chama `reqmaster_db`. Ajuste os nomes conforme o seu ambiente.

### 1. Backup Manual Completo (Banco + Arquivos)

```bash
# 1. Defina a data e pasta destino dos backups
BACKUP_DIR="/var/backups/reqmaster"
DATE=$(date +"%Y%m%d_%H%M%S")
sudo mkdir -p "$BACKUP_DIR"

# 2. Backup do banco de dados MySQL (será solicitada a senha do usuário MySQL)
mysqldump -h 127.0.0.1 -u SEU_USUARIO_MYSQL -p \
  --single-transaction --routines --triggers --events \
  SEU_BANCO_MYSQL | gzip > "$BACKUP_DIR/reqmaster_db_${DATE}.sql.gz"

# 3. Backup dos arquivos críticos (configurações, cofre e uploads)
sudo tar -czf "$BACKUP_DIR/reqmaster_files_${DATE}.tar.gz" \
  -C /var/www/html/reqmaster \
  config/db_config.php config/vault public/uploads
```

> **Dica:** Para incluir **todo o código do sistema** junto, substitua o passo 3 por:
> ```bash
> sudo tar -czf "$BACKUP_DIR/reqmaster_full_${DATE}.tar.gz" -C /var/www/html reqmaster
> ```

### 2. Backup Automático Diário com Cron (Recomendado)

Crie um script em `/usr/local/bin/backup-reqmaster.sh`:

```bash
#!/bin/bash
set -e

BACKUP_DIR="/var/backups/reqmaster"
APP_DIR="/var/www/html/reqmaster"
DB_HOST="127.0.0.1"
DB_NAME="SEU_BANCO_MYSQL"
DB_USER="SEU_USUARIO_MYSQL"
DB_PASS="SUA_SENHA_MYSQL"
DATE=$(date +"%Y%m%d_%H%M%S")

mkdir -p "$BACKUP_DIR"

MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -u "$DB_USER" \
  --single-transaction --routines --triggers \
  "$DB_NAME" | gzip > "$BACKUP_DIR/reqmaster_db_${DATE}.sql.gz"

tar -czf "$BACKUP_DIR/reqmaster_files_${DATE}.tar.gz" \
  -C "$APP_DIR" \
  config/db_config.php config/vault public/uploads

# Mantém somente os backups dos últimos 30 dias
find "$BACKUP_DIR" -type f -mtime +30 -delete
```

Dê permissão restrita ao script e agende para rodar todos os dias às **02:00**:

```bash
sudo chmod 700 /usr/local/bin/backup-reqmaster.sh
sudo crontab -e
```

Adicione a linha abaixo no `crontab`:

```cron
0 2 * * * /usr/local/bin/backup-reqmaster.sh >/dev/null 2>&1
```

---

## 🪟 Como fazer Backup no Windows (XAMPP)

### Opção A — Via Linha de Comando (PowerShell)

```powershell
$date = Get-Date -Format "yyyyMMdd_HHmmss"
$dest = "C:\Backups\ReqMaster\$date"
New-Item -ItemType Directory -Path $dest -Force | Out-Null

# 1. Exportar banco MySQL
& "C:\xampp\mysql\bin\mysqldump.exe" -h 127.0.0.1 -u SEU_USUARIO_MYSQL -p --single-transaction SEU_BANCO_MYSQL > "$dest\banco_reqmaster.sql"

# 2. Copiar configurações, cofre e uploads
Copy-Item "C:\xampp\htdocs\config" "$dest\config" -Recurse -Force
Copy-Item "C:\xampp\htdocs\public\uploads" "$dest\uploads" -Recurse -Force

# 3. Compactar tudo em um arquivo ZIP
Compress-Archive -Path "$dest\*" -DestinationPath "C:\Backups\ReqMaster\reqmaster_backup_$date.zip"
```

### Opção B — Via phpMyAdmin (Interface Gráfica)

1. Acesse `http://localhost/phpmyadmin`.
2. Selecione o banco de dados do ReqMaster na coluna esquerda.
3. Clique na aba **Exportar**, escolha o método **Personalizado** (garantindo que todas as tabelas estejam marcadas) e formato **SQL**, e clique em **Executar**.
4. Salve o arquivo `.sql` gerado junto com uma cópia compactada das pastas `config/` e `public/uploads/`.

---

## 🔄 Como Restaurar um Backup Completo

### No Ubuntu Server

```bash
# 1. Extrair arquivos de volta na pasta da aplicação
sudo tar -xzf /var/backups/reqmaster/reqmaster_files_DATA.tar.gz -C /var/www/html/reqmaster

# 2. Ajustar permissões para o Apache (www-data)
sudo chown -R www-data:www-data /var/www/html/reqmaster/config /var/www/html/reqmaster/public/uploads

# 3. Restaurar o banco de dados MySQL
gunzip < /var/backups/reqmaster/reqmaster_db_DATA.sql.gz | mysql -h 127.0.0.1 -u SEU_USUARIO_MYSQL -p SEU_BANCO_MYSQL
```

### No Windows (XAMPP)

1. Extraia as pastas `config/` e `public/uploads/` de volta para dentro da pasta do projeto (`C:\xampp\htdocs\`).
2. Importe o arquivo `.sql` pelo **phpMyAdmin** (aba *Importar*) ou pelo terminal:
   ```powershell
   Get-Content "banco_reqmaster.sql" | & "C:\xampp\mysql\bin\mysql.exe" -h 127.0.0.1 -u SEU_USUARIO_MYSQL -p SEU_BANCO_MYSQL
   ```

---

## 🔒 Boas Práticas de Segurança para Backups

- **Nunca** salve arquivos `.sql`, `.zip` ou `.tar.gz` de backup dentro de `public/` ou qualquer diretório acessível pela internet.
- Armazene cópias em **local externo ao servidor** (nuvem criptografada, NAS ou mídia externa).
- Restrinja a leitura dos arquivos de backup no Linux (`chmod 600`), pois contêm credenciais de banco e dados corporativos.
- **Teste a restauração** periodicamente em um ambiente de homologação para garantir que o backup está íntegro.
- Lembre-se de que a licença é vinculada à instância do servidor; ao migrar o backup para um servidor novo, pode ser necessário revalidar a chave na tela de **Licenciamento**.

---

**CRM Soluções em Tecnologia** &bull; **Caio Monteiro** &bull; **2027** &bull; **DEUS SEJA LOUVADO**  
Suporte (Seg a Sex, das 9h às 17h): `caiormleo@outlook.com.br` &bull; WhatsApp: `@caior.monteiro` &bull; GitHub: `chacalbl4ck/reqmaster`
