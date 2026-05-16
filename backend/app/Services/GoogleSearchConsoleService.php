<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GoogleSearchConsoleService
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $refreshToken;
    protected $siteUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->clientId = env('GSC_CLIENT_ID');
        $this->clientSecret = env('GSC_CLIENT_SECRET');
        $this->refreshToken = env('GSC_REFRESH_TOKEN');
        $this->siteUrl = env('GSC_SITE_URL', 'https://kilatech.ma/');
    }

    /**
     * Get an access token using the refresh token.
     */
    protected function getAccessToken()
    {
        try {
            $response = $this->client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        } catch (\Exception $e) {
            Log::error('GSC Auth Failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch search analytics for a specific URL.
     */
    public function getUrlStats($path)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) return null;

        $url = rtrim($this->siteUrl, '/') . '/' . ltrim($path, '/');
        
        try {
            $response = $this->client->post(
                "https://www.googleapis.com/webmasters/v3/sites/" . urlencode($this->siteUrl) . "/searchAnalytics/query",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'startDate' => now()->subMonths(3)->format('Y-m-d'),
                        'endDate' => now()->format('Y-m-d'),
                        'dimensions' => ['page'],
                        'dimensionFilterGroups' => [
                            [
                                'filters' => [
                                    [
                                        'dimension' => 'page',
                                        'operator' => 'equals',
                                        'expression' => $url
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            );

            $data = json_decode($response->getBody(), true);
            
            if (empty($data['rows'])) {
                return [
                    'clicks' => 0,
                    'impressions' => 0,
                    'ctr' => 0,
                    'position' => 0
                ];
            }

            $row = $data['rows'][0];
            return [
                'clicks' => $row['clicks'],
                'impressions' => $row['impressions'],
                'ctr' => round($row['ctr'] * 100, 2),
                'position' => round($row['position'], 1)
            ];

        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '403')) {
                $msg = "Accès refusé. Vérifiez que le site {$this->siteUrl} est bien dans votre Search Console et que l'API est activée.";
            } elseif (str_contains($msg, '401')) {
                $msg = "Authentification échouée. Votre Refresh Token est peut-être expiré.";
            }
            throw new \Exception($msg);
        }
    }
}
