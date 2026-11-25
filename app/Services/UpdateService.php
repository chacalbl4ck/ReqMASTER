<?php
declare(strict_types=1);

namespace App\Services;

use Exception;
use Throwable;

class UpdateService
{
    private const MANIFEST_FILE = 'manifest.enc';
    private const MAINTENANCE_FILE = 'maintenance.lock';

    /**
     * Caminhos locais protegidos que jamais devem ser sobrescritos por atualizações remotas.
     */
    private const PROTECTED_PREFIXES = [
        'config/db_config.php',
        'config/vault/',
        'install/',
        'public/uploads/',
        'license-generator-java/',
        '.git/',
        '.gitignore',
    ];

    private VaultService $vault;
    private string $projectRoot;

    public function __construct()
    {
        $this->vault = new VaultService();
        $this->projectRoot = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');
    }

    public function isMaintenanceMode(): bool
    {
        return file_exists($this->vault->getVaultDir() . '/' . self::MAINTENANCE_FILE);
    }

    public function setMaintenanceMode(bool $enabled): void
    {
        $lockPath = $this->vault->getVaultDir() . '/' . self::MAINTENANCE_FILE;
        if ($enabled) {
            @file_put_contents($lockPath, date('c'), LOCK_EX);
        } else {
            if (file_exists($lockPath)) {
                @unlink($lockPath);
            }
        }
    }

    public function getLocalStatus(): array
    {
        $manifest = $this->vault->readEncryptedFile(self::MANIFEST_FILE);
        if (!$manifest) {
            return [
                'has_manifest' => false,
                'commit_sha'   => null,
                'commit_short' => 'Não sincronizado',
                'updated_at'   => 'Nunca atualizado',
                'file_count'   => 0,
            ];
        }

        return [
            'has_manifest' => true,
            'commit_sha'   => $manifest['commit_sha'] ?? '',
            'commit_short' => !empty($manifest['commit_sha']) ? substr((string)$manifest['commit_sha'], 0, 7) : 'N/A',
            'updated_at'   => !empty($manifest['updated_at']) ? date('d/m/Y H:i:s', (int)$manifest['updated_at']) : 'N/A',
            'file_count'   => is_array($manifest['files'] ?? null) ? count($manifest['files']) : 0,
        ];
    }

    /**
     * Consulta o repositório remoto criptografado, compara árvore de arquivos com a listagem criptografada local
     * e retorna a lista de arquivos novos/modificados sem aplicar nada ainda.
     */
    public function checkUpdates(): array
    {
        $repo  = $this->vault->getCredential('upd_repo');
        $token = $this->vault->getCredential('upd_token');

        if (empty($repo) || empty($token)) {
            return ['success' => false, 'message' => 'Canal oficial de atualizações indisponível.'];
        }

        // 1. Busca o último commit do branch principal
        $commits = $this->githubApiJson("https://api.github.com/repos/{$repo}/commits?per_page=1", $token);
        if (!is_array($commits) || empty($commits[0]['sha'])) {
            return ['success' => false, 'message' => 'Não foi possível consultar a versão mais recente no servidor remoto.'];
        }

        $latestCommit = $commits[0];
        $remoteCommitSha = (string)$latestCommit['sha'];

        // 2. Obtém árvore completa de arquivos do commit
        $treeData = $this->githubApiJson("https://api.github.com/repos/{$repo}/git/trees/{$remoteCommitSha}?recursive=1", $token);
        if (!is_array($treeData) || empty($treeData['tree'])) {
            return ['success' => false, 'message' => 'Falha ao obter a listagem de arquivos da nova versão.'];
        }

        $manifest = $this->vault->readEncryptedFile(self::MANIFEST_FILE);
        $storedFiles = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        $localCommitSha = $manifest['commit_sha'] ?? null;

        $remoteFileMap = [];
        $filesToUpdate = [];

        foreach ($treeData['tree'] as $node) {
            if (($node['type'] ?? '') !== 'blob' || empty($node['path']) || empty($node['sha'])) {
                continue;
            }
            $relPath = str_replace('\\', '/', (string)$node['path']);
            if ($this->isProtectedPath($relPath)) {
                continue;
            }

            $remoteSha = (string)$node['sha'];
            $remoteFileMap[$relPath] = $remoteSha;
            $localPath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

            if (!file_exists($localPath)) {
                $filesToUpdate[] = [
                    'path'   => $relPath,
                    'status' => 'added',
                    'sha'    => $remoteSha,
                    'size'   => (int)($node['size'] ?? 0),
                ];
                continue;
            }

            // Verifica primeiramente contra a hash real local em disco (suporta CRLF e LF)
            $localMatchesDisk = $this->localFileMatchesGitSha($localPath, $remoteSha);
            $localMatchesManifest = isset($storedFiles[$relPath]) && hash_equals((string)$storedFiles[$relPath], $remoteSha);

            if (!$localMatchesDisk && !$localMatchesManifest) {
                $filesToUpdate[] = [
                    'path'   => $relPath,
                    'status' => 'modified',
                    'sha'    => $remoteSha,
                    'size'   => (int)($node['size'] ?? 0),
                ];
            }
        }

        // Se não existe manifesto criptografado inicial e já está tudo igual, grava a base inicial criptografada
        if (!$manifest && empty($filesToUpdate)) {
            $this->vault->writeEncryptedFile(self::MANIFEST_FILE, [
                'commit_sha' => $remoteCommitSha,
                'updated_at' => time(),
                'files'      => $remoteFileMap,
            ]);
            $localCommitSha = $remoteCommitSha;
        }

        return [
            'success'         => true,
            'has_updates'     => !empty($filesToUpdate),
            'latest_commit'   => substr($remoteCommitSha, 0, 7),
            'full_commit_sha' => $remoteCommitSha,
            'local_commit'    => $localCommitSha ? substr((string)$localCommitSha, 0, 7) : 'Base Local',
            'commit_message'  => (string)($latestCommit['commit']['message'] ?? 'Atualização do sistema'),
            'commit_author'   => (string)($latestCommit['commit']['author']['name'] ?? 'Equipe ReqMaster'),
            'commit_date'     => date('d/m/Y H:i', strtotime((string)($latestCommit['commit']['author']['date'] ?? 'now'))),
            'files_to_update' => $filesToUpdate,
            'total_remote'    => count($remoteFileMap),
        ];
    }

    /**
     * Ativa modo manutenção, baixa os arquivos alterados/novos do repositório oficial,
     * substitui com verificação de integridade SHA-1 e atualiza a listagem criptografada.
     */
    public function applyUpdates(): array
    {
        $repo  = $this->vault->getCredential('upd_repo');
        $token = $this->vault->getCredential('upd_token');

        if (empty($repo) || empty($token)) {
            return ['success' => false, 'message' => 'Canal oficial de atualizações indisponível.'];
        }

        $check = $this->checkUpdates();
        if (empty($check['success'])) {
            return $check;
        }

        if (empty($check['has_updates'])) {
            return [
                'success' => true,
                'updated_count' => 0,
                'message' => 'O sistema já está totalmente atualizado na versão mais recente.'
            ];
        }

        $remoteCommitSha = (string)$check['full_commit_sha'];
        $treeData = $this->githubApiJson("https://api.github.com/repos/{$repo}/git/trees/{$remoteCommitSha}?recursive=1", $token);
        if (!is_array($treeData) || empty($treeData['tree'])) {
            return ['success' => false, 'message' => 'Falha ao carregar árvore de arquivos para atualização.'];
        }

        // Ativa modo manutenção imediatamente
        $this->setMaintenanceMode(true);

        $updatedFiles = [];
        $errors = [];

        try {
            $remoteFileMap = [];
            foreach ($treeData['tree'] as $node) {
                if (($node['type'] ?? '') === 'blob' && !empty($node['path']) && !empty($node['sha'])) {
                    $p = str_replace('\\', '/', (string)$node['path']);
                    if (!$this->isProtectedPath($p)) {
                        $remoteFileMap[$p] = (string)$node['sha'];
                    }
                }
            }

            foreach ($check['files_to_update'] as $item) {
                $relPath = (string)$item['path'];
                $blobSha = (string)$item['sha'];

                if ($this->isProtectedPath($relPath) || !$this->isSafeRelativePath($relPath)) {
                    continue;
                }

                $blob = $this->githubApiJson("https://api.github.com/repos/{$repo}/git/blobs/{$blobSha}", $token);
                if (!is_array($blob) || !isset($blob['content'])) {
                    $errors[] = "Falha ao baixar: {$relPath}";
                    continue;
                }

                $content = (($blob['encoding'] ?? '') === 'base64')
                    ? base64_decode(str_replace(["\r", "\n"], '', (string)$blob['content']), true)
                    : (string)$blob['content'];

                if ($content === false) {
                    $errors[] = "Falha ao decodificar arquivo: {$relPath}";
                    continue;
                }

                // Verifica integridade do conteúdo via Git Blob SHA-1
                $computedSha = sha1("blob " . strlen($content) . "\0" . $content);
                if (!hash_equals($blobSha, $computedSha)) {
                    $errors[] = "Falha de integridade SHA-1 em: {$relPath}";
                    continue;
                }

                $destPath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
                $destDir = dirname($destPath);
                if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
                    $errors[] = "Sem permissão para criar diretório: {$relPath}";
                    continue;
                }

                if (!$this->writeFileResilient($destPath, $content)) {
                    $errors[] = "Sem permissão para gravar arquivo: {$relPath}";
                    continue;
                }

                $updatedFiles[] = $relPath;
            }

            // Atualiza manifesto criptografado com as novas versões
            $existingManifest = $this->vault->readEncryptedFile(self::MANIFEST_FILE);
            $newFilesMap = is_array($existingManifest['files'] ?? null) ? $existingManifest['files'] : [];
            foreach ($updatedFiles as $uf) {
                if (isset($remoteFileMap[$uf])) {
                    $newFilesMap[$uf] = $remoteFileMap[$uf];
                }
            }
            if (empty($errors)) {
                $newFilesMap = $remoteFileMap;
            }

            $this->vault->writeEncryptedFile(self::MANIFEST_FILE, [
                'commit_sha' => empty($errors) ? $remoteCommitSha : ($existingManifest['commit_sha'] ?? ''),
                'updated_at' => time(),
                'files'      => $newFilesMap,
            ]);

            if (!empty($errors)) {
                return [
                    'success'       => false,
                    'updated_count' => count($updatedFiles),
                    'updated_files' => $updatedFiles,
                    'errors'        => $errors,
                    'message'       => 'Alguns arquivos não puderam ser substituídos: ' . implode('; ', $errors) . '. No Linux/Ubuntu, garanta que o usuário do Apache (www-data) tenha posse e permissão de escrita na pasta do sistema (ex: sudo chown -R www-data:www-data ' . $this->projectRoot . ').',
                ];
            }

            return [
                'success'       => true,
                'updated_count' => count($updatedFiles),
                'updated_files' => $updatedFiles,
                'commit'        => substr($remoteCommitSha, 0, 7),
                'message'       => count($updatedFiles) . ' arquivo(s) atualizado(s) com sucesso para a versão ' . substr($remoteCommitSha, 0, 7) . '!',
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Erro durante a atualização: ' . $e->getMessage()];
        } finally {
            // Garante saída do modo manutenção ao finalizar
            $this->setMaintenanceMode(false);
        }
    }

    private function isProtectedPath(string $relPath): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $relPath), '/');
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if ($normalized === rtrim($prefix, '/') || str_starts_with($normalized, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function isSafeRelativePath(string $relPath): bool
    {
        if (str_contains($relPath, '..') || str_starts_with($relPath, '/') || str_contains($relPath, "\0")) {
            return false;
        }
        return true;
    }

    /**
     * Grava o arquivo atualizado diretamente ou via substituição atômica (temp + rename)
     * para contornar bloqueios quando o arquivo pertence a outro usuário mas o diretório é gravável.
     */
    private function writeFileResilient(string $destPath, string $content): bool
    {
        if (file_exists($destPath) && !is_writable($destPath)) {
            @chmod($destPath, 0644);
        }

        if (@file_put_contents($destPath, $content, LOCK_EX) !== false) {
            return true;
        }

        $destDir = dirname($destPath);
        if (!is_writable($destDir)) {
            @chmod($destDir, 0755);
        }

        $tmpPath = @tempnam($destDir, '.rm_upd_');
        if ($tmpPath !== false) {
            if (@file_put_contents($tmpPath, $content, LOCK_EX) !== false) {
                @chmod($tmpPath, 0644);
                if (@rename($tmpPath, $destPath)) {
                    return true;
                }
            }
            @unlink($tmpPath);
        }

        return false;
    }

    private function localFileMatchesGitSha(string $localPath, string $remoteSha): bool
    {
        $raw = @file_get_contents($localPath);
        if ($raw === false) {
            return false;
        }
        $shaRaw = sha1("blob " . strlen($raw) . "\0" . $raw);
        if (hash_equals($remoteSha, $shaRaw)) {
            return true;
        }
        // Normaliza CRLF -> LF caso o arquivo no Windows tenha sido gravado com \r\n
        $norm = str_replace("\r\n", "\n", $raw);
        $shaNorm = sha1("blob " . strlen($norm) . "\0" . $norm);
        return hash_equals($remoteSha, $shaNorm);
    }

    private function githubApiJson(string $url, string $token): ?array
    {
        $raw = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => [
                    'User-Agent: ReqMaster-UpdateEngine',
                    'Accept: application/vnd.github+json',
                    'Authorization: Bearer ' . $token,
                    'X-GitHub-Api-Version: 2022-11-28',
                    'Cache-Control: no-cache',
                ],
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res !== false && $code >= 200 && $code < 300) {
                $raw = (string)$res;
            }
        } else {
            $opts = [
                'http' => [
                    'method'  => 'GET',
                    'timeout' => 20,
                    'header'  => "User-Agent: ReqMaster-UpdateEngine\r\n"
                               . "Accept: application/vnd.github+json\r\n"
                               . "Authorization: Bearer {$token}\r\n",
                ],
            ];
            $ctx = stream_context_create($opts);
            $res = @file_get_contents($url, false, $ctx);
            if ($res !== false) {
                $raw = $res;
            }
        }

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
