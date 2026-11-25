<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class NtpTimeService
{
    private const PRIMARY_NTP = 'a.st1.ntp.br';
    private const BACKUP_NTP  = 'pool.ntp.org';
    private const FALLBACK_NTP = 'time.google.com';
    private const NTP_EPOCH_OFFSET = 2208988800; // 1900-01-01 -> 1970-01-01

    /**
     * Consulta o CNPJ na Receita (BrasilAPI / ReceitaWS) e determina cidade, UF e fuso horário oficial.
     */
    public function lookupCnpjLocation(string $rawCnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $rawCnpj);
        if (strlen($cnpj) !== 14) {
            return [
                'found'    => false,
                'cnpj'     => $cnpj,
                'city'     => 'Brasília',
                'uf'       => 'DF',
                'timezone' => 'America/Sao_Paulo',
            ];
        }

        // 1. BrasilAPI
        $data = $this->httpGetJson("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");
        if (is_array($data) && !empty($data['uf'])) {
            $city = trim((string)($data['municipio'] ?? ''));
            $uf   = strtoupper(trim((string)($data['uf'] ?? 'SP')));
            return [
                'found'    => true,
                'cnpj'     => $cnpj,
                'city'     => $city !== '' ? $city : $uf,
                'uf'       => $uf,
                'timezone' => $this->resolveBrazilianTimezone($city, $uf),
            ];
        }

        // 2. Fallback: ReceitaWS
        $dataWs = $this->httpGetJson("https://receitaws.com.br/v1/cnpj/{$cnpj}");
        if (is_array($dataWs) && ($dataWs['status'] ?? '') !== 'ERROR' && !empty($dataWs['uf'])) {
            $city = trim((string)($dataWs['municipio'] ?? ''));
            $uf   = strtoupper(trim((string)($dataWs['uf'] ?? 'SP')));
            return [
                'found'    => true,
                'cnpj'     => $cnpj,
                'city'     => $city !== '' ? $city : $uf,
                'uf'       => $uf,
                'timezone' => $this->resolveBrazilianTimezone($city, $uf),
            ];
        }

        return [
            'found'    => false,
            'cnpj'     => $cnpj,
            'city'     => 'Padrão Nacional',
            'uf'       => 'BR',
            'timezone' => 'America/Sao_Paulo',
        ];
    }

    /**
     * Consulta servidor NTP principal e backup (UDP 123) retornando timestamp UTC autoritativo.
     */
    public function queryNtpUtc(): array
    {
        $servers = [self::PRIMARY_NTP, self::BACKUP_NTP, self::FALLBACK_NTP];

        foreach ($servers as $idx => $host) {
            $ts = $this->querySingleNtp($host, 1);
            if ($ts !== null) {
                return [
                    'success'       => true,
                    'utc_timestamp' => $ts,
                    'ntp_server'    => $host,
                    'is_backup'     => $idx > 0,
                ];
            }
        }

        return [
            'success'       => false,
            'utc_timestamp' => time(),
            'ntp_server'    => 'Falha NTP (indisponível)',
            'is_backup'     => false,
        ];
    }

    /**
     * Obtém data e hora oficial NTP convertida exatamente para o fuso da cidade do CNPJ da licença.
     */
    public function getAuthoritativeCityTime(string $cnpj = '', ?array $cachedGeo = null): array
    {
        $cleanCnpj = preg_replace('/\D/', '', $cnpj);

        $geoCacheFresh = is_array($cachedGeo)
            && !empty($cachedGeo['timezone'])
            && ($cachedGeo['cnpj'] ?? '') === $cleanCnpj
            && (!empty($cachedGeo['found']) || (!empty($cachedGeo['checked_at']) && (time() - (int)$cachedGeo['checked_at']) < 86400));

        if ($cleanCnpj === '') {
            $geo = [
                'found'    => false,
                'cnpj'     => '',
                'city'     => 'Padrão Nacional',
                'uf'       => 'BR',
                'timezone' => 'America/Sao_Paulo',
            ];
        } elseif ($geoCacheFresh) {
            $geo = $cachedGeo;
        } else {
            $geo = $this->lookupCnpjLocation($cleanCnpj);
        }

        $ntp = $this->queryNtpUtc();
        $tzName = $geo['timezone'] ?: 'America/Sao_Paulo';

        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable $e) {
            $tzName = 'America/Sao_Paulo';
            $tz = new DateTimeZone($tzName);
        }

        $dt = (new DateTimeImmutable('@' . $ntp['utc_timestamp']))->setTimezone($tz);

        return [
            'ntp_success'    => $ntp['success'],
            'ntp_server'     => $ntp['ntp_server'],
            'utc_timestamp'  => $ntp['utc_timestamp'],
            'timezone'       => $tzName,
            'city'           => $geo['city'],
            'uf'             => $geo['uf'],
            'cnpj'           => $geo['cnpj'],
            'geo_found'      => $geo['found'],
            'local_date'     => $dt->format('Y-m-d'),
            'local_datetime' => $dt->format('Y-m-d H:i:s'),
            'local_br'       => $dt->format('d/m/Y H:i:s'),
        ];
    }

    private function querySingleNtp(string $host, int $timeoutSeconds = 1): ?int
    {
        $sock = @stream_socket_client("udp://{$host}:123", $errno, $errstr, $timeoutSeconds);
        if (!$sock) {
            return null;
        }

        stream_set_timeout($sock, $timeoutSeconds);
        $packet = "\x1b" . str_repeat("\0", 47);
        $written = @fwrite($sock, $packet);
        if ($written !== 48) {
            fclose($sock);
            return null;
        }

        $response = @fread($sock, 48);
        fclose($sock);

        if (!is_string($response) || strlen($response) < 48) {
            return null;
        }

        $unpacked = @unpack('N12', $response);
        if (!$unpacked || !isset($unpacked[11])) {
            return null;
        }

        $secs1900 = (float)$unpacked[11];
        if ($secs1900 < 0) {
            $secs1900 += 4294967296.0;
        }
        $unixTs = (int)($secs1900 - self::NTP_EPOCH_OFFSET);

        // Sanidade mínima: ano >= 2024
        return $unixTs >= 1704067200 ? $unixTs : null;
    }

    public function resolveBrazilianTimezone(string $city, string $uf): string
    {
        $uf = strtoupper(trim($uf));
        $cityNorm = $this->normalizeCity($city);

        if ($uf === 'AC') {
            return 'America/Rio_Branco';
        }
        if ($uf === 'AM') {
            $westAm = [
                'ATALAIA DO NORTE', 'BENJAMIN CONSTANT', 'BOCA DO ACRE', 'EIRUNEPE',
                'ENVIRA', 'FONTE BOA', 'GUAJARA', 'IPIXUNA', 'ITAMARATI', 'JUTAI',
                'LABREA', 'PAUINI', 'SANTO ANTONIO DO ICA', 'SAO PAULO DE OLIVENCA',
                'TABATINGA', 'TONANTINS',
            ];
            return in_array($cityNorm, $westAm, true) ? 'America/Eirunepe' : 'America/Manaus';
        }
        if ($uf === 'RR') return 'America/Boa_Vista';
        if ($uf === 'RO') return 'America/Porto_Velho';
        if ($uf === 'MT') return 'America/Cuiaba';
        if ($uf === 'MS') return 'America/Campo_Grande';
        if ($uf === 'TO') return 'America/Araguaina';
        if ($uf === 'AP') return 'America/Belem';
        if ($uf === 'BA') return 'America/Bahia';
        if (in_array($uf, ['AL', 'SE'], true)) return 'America/Maceio';
        if (in_array($uf, ['CE', 'MA', 'PB', 'PI', 'RN'], true)) return 'America/Fortaleza';
        if ($uf === 'PE') {
            return str_contains($cityNorm, 'FERNANDO DE NORONHA') ? 'America/Noronha' : 'America/Recife';
        }
        if ($uf === 'PA') {
            $westPa = [
                'ALENQUER', 'ALMEIRIM', 'AVEIRO', 'BELTERRA', 'CURUA', 'FARO',
                'ITAITUBA', 'JACAREACANGA', 'JURUTI', 'MOJUI DOS CAMPOS',
                'MONTE ALEGRE', 'NOVO PROGRESSO', 'OBIDOS', 'ORIXIMINA', 'PLACAS',
                'PRAINHA', 'RUROPOLIS', 'SANTAREM', 'TERRA SANTA', 'TRAIRAO', 'URUARA',
            ];
            return in_array($cityNorm, $westPa, true) ? 'America/Santarem' : 'America/Belem';
        }

        // SP, RJ, MG, ES, PR, SC, RS, DF, GO
        return 'America/Sao_Paulo';
    }

    private function normalizeCity(string $city): string
    {
        $upper = mb_strtoupper(trim($city), 'UTF-8');
        $map = [
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
            'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
            'Ç'=>'C',
        ];
        return strtr($upper, $map);
    }

    private function httpGetJson(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => ['User-Agent: ReqMaster-GeoTime/2.0', 'Accept: application/json'],
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($res !== false && $code >= 200 && $code < 300) {
                $decoded = json_decode((string)$res, true);
                return is_array($decoded) ? $decoded : null;
            }
            return null;
        }

        $opts = [
            'http' => [
                'method'  => 'GET',
                'timeout' => 3,
                'header'  => "User-Agent: ReqMaster-GeoTime/2.0\r\nAccept: application/json\r\n",
            ],
        ];
        $res = @file_get_contents($url, false, stream_context_create($opts));
        $decoded = $res !== false ? json_decode($res, true) : null;
        return is_array($decoded) ? $decoded : null;
    }
}
