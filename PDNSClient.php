<?php

class PDNSClient
{
    private $host;
    private $port;
    private $apiKey;

    public function __construct($host, $port, $apiKey)
    {
        $this->host = $host;
        $this->port = $port;
        $this->apiKey = $apiKey;
    }

    public function testConnection()
    {
        // Simple health check against the server info endpoint
        $res = $this->request('GET', '');
        return $res['code'] === 200;
    }

    public function request($method, $endpoint, $data = null)
    {
        $base = "http://{$this->host}:{$this->port}/api/v1/servers/localhost";
        $url = $endpoint ? "$base/$endpoint" : $base;
        $ch = curl_init($url);

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    public function getZones()
    {
        return $this->request('GET', 'zones');
    }

    public function createZone($zoneName, $nameservers)
    {
        $data = [
            'name' => $zoneName,
            'kind' => 'Native',
            'masters' => [],
            'nameservers' => $nameservers
        ];
        return $this->request('POST', 'zones', $data);
    }

    public function deleteZone($zoneId)
    {
        // Ensure canonical zone name
        if (substr($zoneId, -1) !== '.') $zoneId .= '.';
        return $this->request('DELETE', "zones/$zoneId");
    }

    // Function to add a record (PATCH)
    public function addRecord($zoneName, $name, $type, $content, $ttl = 3600)
    {
        // Ensure name is canonical
        if (substr($name, -1) !== '.') $name .= '.';
        if (substr($zoneName, -1) !== '.') $zoneName .= '.';

        $rrset = [
            'name' => $name,
            'type' => $type,
            'ttl'  => $ttl,
            'changetype' => 'REPLACE',
            'records' => [
                [
                    'content' => $content,
                    'disabled' => false
                ]
            ]
        ];

        $data = ['rrsets' => [$rrset]];
        return $this->request('PATCH', "zones/$zoneName", $data);
    }

    public function deleteRecord($zoneName, $name, $type)
    {
        // Ensure name is canonical
        if (substr($name, -1) !== '.') $name .= '.';
        if (substr($zoneName, -1) !== '.') $zoneName .= '.';

        $rrset = [
            'name' => $name,
            'type' => $type,
            'changetype' => 'DELETE',
            'records' => []
        ];

        $data = ['rrsets' => [$rrset]];
        return $this->request('PATCH', "zones/$zoneName", $data);
    }
}
