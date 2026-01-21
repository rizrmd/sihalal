<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class HalalGoIdService
{
    protected string $baseUrl;
    protected string $bearerToken;

    public function __construct(?string $bearerToken = null)
    {
        $this->baseUrl = 'https://ptsp.halal.go.id';
        $this->bearerToken = $bearerToken;
    }

    /**
     * Set bearer token for authentication
     */
    public function setBearerToken(string $token): self
    {
        $this->bearerToken = $token;
        return $this;
    }

    /**
     * Get formatted bearer token (with "Bearer " prefix)
     */
    protected function getFormattedToken(): string
    {
        $token = trim($this->bearerToken);

        // Remove "Bearer " prefix if exists to avoid duplication, then add it back
        $token = preg_replace('/^Bearer\s+/i', '', $token);

        return 'Bearer ' . $token;
    }

    /**
     * Submit draft data to halal.go.id API
     *
     * @param string $type - Submission type (e.g., "JD.1")
     * @return array - Response with status and data
     */
    public function submitDraft(string $type = 'JD.1'): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
            ]);

            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => $this->getFormattedToken(),
            ];

            $body = json_encode([
                'type' => $type
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/draft';
            $url = $this->baseUrl . $endpoint;

            $request = new Request('POST', $url, $headers, $body);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $decodedData = json_decode($responseBody, true);

                // Debug logging
                Log::info('submitDraft API Response', [
                    'status_code' => $statusCode,
                    'response_body' => $responseBody,
                    'decoded_data' => $decodedData,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $decodedData,
                    'message' => 'Successfully submitted to halal.go.id',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to submit to halal.go.id',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/draft',
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get pelaku usaha profile from halal.go.id API
     *
     * @return array - Response with status and data
     */
    public function getPelakuUsahaProfile(): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Referer' => 'https://ptsp.halal.go.id/pelaku-usaha',
                ],
                'decode_content' => 'gzip',
            ]);

            $endpoint = '/api/pelaku-usaha-profile';
            $url = $this->baseUrl . $endpoint;

            $request = new Request('GET', $url);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved pelaku usaha profile',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get pelaku usaha profile',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getPelakuUsahaProfile', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/pelaku-usaha-profile',
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get pelaku usaha detail from halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @return array - Response with status and data
     */
    public function getPelakuUsahaDetail(string $idReg): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Referer' => 'https://ptsp.halal.go.id/sh-domestic/submission/reguler/' . $idReg,
                ],
                'decode_content' => 'gzip',
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/detail';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $request = new Request('GET', $url);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved pelaku usaha detail',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get pelaku usaha detail',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get pelaku usaha detail';
            $errors = $decodedError['errors'] ?? [];

            $logLevel = ($statusCode >= 400 && $statusCode < 500) ? 'warning' : 'error';

            Log::$logLevel('HalalGoId API BadResponseException - getPelakuUsahaDetail', [
                'status_code' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'endpoint' => '/api/reguler/pelaku-usaha/detail',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getPelakuUsahaDetail', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/detail',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if token is still valid (not expired)
     *
     * @return array - With keys: valid, expired, exp_date
     */
    public function validateToken(): array
    {
        try {
            $token = preg_replace('/^Bearer\s+/i', '', trim($this->bearerToken));
            $tokenParts = explode('.', $token);

            if (count($tokenParts) !== 3) {
                return [
                    'valid' => false,
                    'expired' => true,
                    'message' => 'Invalid token format',
                ];
            }

            $payload = json_decode(base64_decode($tokenParts[1]), true);

            if (!isset($payload['exp'])) {
                return [
                    'valid' => false,
                    'expired' => true,
                    'message' => 'Token does not have expiration claim',
                ];
            }

            $expired = $payload['exp'] < time();
            $expDate = date('Y-m-d H:i:s', $payload['exp']);

            return [
                'valid' => !$expired,
                'expired' => $expired,
                'exp_date' => $expDate,
                'exp_timestamp' => $payload['exp'],
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'expired' => true,
                'message' => 'Failed to validate token: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get jenis layanan (master data) from halal.go.id API
     *
     * @return array - Response with status and data
     */
    public function getJenisLayanan(): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
            ]);

            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => $this->getFormattedToken(),
            ];

            $endpoint = '/api/master/jenis-layanan';
            $url = $this->baseUrl . $endpoint;

            $request = new Request('GET', $url, $headers);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved jenis layanan',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get jenis layanan',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getJenisLayanan', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/master/jenis-layanan',
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get jenis layanan code by name
     *
     * @param string $name - Name to search for (e.g., "Makanan")
     * @return string|null - Code or null if not found
     */
    public function getJenisLayananCodeByName(string $name): ?string
    {
        $result = $this->getJenisLayanan();

        if (!$result['success']) {
            Log::error('Failed to get jenis layanan data', [
                'message' => $result['message'],
                'status' => $result['status'],
            ]);
            return null;
        }

        $data = $result['data'];

        // Handle different response formats
        $jenisLayananList = $data['data'] ?? $data ?? [];

        // Search for matching name
        foreach ($jenisLayananList as $item) {
            if (isset($item['name']) && $item['name'] === $name) {
                return $item['code'] ?? null;
            }
        }

        Log::warning('Jenis layanan not found', [
            'search_name' => $name,
            'available_data' => $jenisLayananList,
        ]);

        return null;
    }

    /**
     * Get product filter by layanan code
     *
     * @param string $layananCode - Layanan code (e.g., "MK01")
     * @return array - Response with status and data
     */
    public function getProductFilter(string $layananCode): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
            ]);

            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => $this->getFormattedToken(),
            ];

            $endpoint = '/api/master/product-filter';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($layananCode);

            $request = new Request('GET', $url, $headers);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved product filter',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get product filter',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getProductFilter', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/master/product-filter',
                'layanan_code' => $layananCode,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get business actor LPH
     *
     * @param string $idReg - ID Registrasi from submitDraft response
     * @param string $jenisLayanan - Jenis layanan code
     * @param string $areaPemasaran - Area pemasaran (e.g., "Kabupaten/Kota")
     * @return array - Response with status and data
     */
    public function getBusinessActorLph(string $idReg, string $jenisLayanan, ?string $areaPemasaran): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true, // Let Guzzle handle gzip/deflate
            ]);

            // Build the nested URL parameter (URL encoded)
            $nestedUrl = 'api/v1/halal-certificate-reguler/business-actor/' . $idReg . '/lph?jenis_layanan=' . urlencode($jenisLayanan) . '&area_pemasaran=' . urlencode($areaPemasaran);

            // Build query parameters for the list endpoint
            $queryParams = [
                'url' => $nestedUrl,
                'page' => 1,
                'size' => 10,
                'keyword' => '',
            ];

            $endpoint = '/api/reguler/list';
            $url = $this->baseUrl . $endpoint;

            $response = $client->get($url, [
                'query' => $queryParams,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved business actor LPH',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get business actor LPH',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get business actor LPH';
            $errors = $decodedError['errors'] ?? [];

            $logLevel = ($statusCode >= 400 && $statusCode < 500) ? 'warning' : 'error';

            Log::$logLevel('HalalGoId API BadResponseException - getBusinessActorLph', [
                'status_code' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'endpoint' => '/api/v1/halal-certificate-reguler/business-actor/{id}/lph',
                'id_reg' => $idReg,
                'jenis_layanan' => $jenisLayanan,
                'area_pemasaran' => $areaPemasaran,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getBusinessActorLph', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/v1/halal-certificate-reguler/business-actor/{id}/lph',
                'id_reg' => $idReg,
                'jenis_layanan' => $jenisLayanan,
                'area_pemasaran' => $areaPemasaran,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit certificate data to halal.go.id API
     *
     * @param array $certificateData - Certificate data to submit
     * @return array - Response with status and data
     */
    public function submitCertificate(array $certificateData): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/certificate';
            $url = $this->baseUrl . $endpoint;

            $body = json_encode($certificateData);

            $response = $client->put($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully submitted certificate data',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to submit certificate data',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - submitCertificate', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/certificate',
                'certificate_data' => $certificateData,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit penanggung jawab data to halal.go.id API
     *
     * @param array $penanggungJawabData - Penanggung jawab data to submit
     * @return array - Response with status and data
     */
    public function submitPenanggungJawab(array $penanggungJawabData): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/penanggung-jawab';
            $url = $this->baseUrl . $endpoint;

            $body = json_encode($penanggungJawabData);

            $response = $client->put($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully submitted penanggung jawab data',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to submit penanggung jawab data',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - submitPenanggungJawab', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/penanggung-jawab',
                'penanggung_jawab_data' => $penanggungJawabData,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload daftar bahan file to halal.go.id API
     *
     * @param string $filePath - Full path to the file
     * @param string $fileName - Original file name
     * @return array - Response with status and data
     */
    public function uploadDaftarBahan(string $filePath, string $fileName): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'File not found: ' . $filePath,
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/products/upload';
            $url = $this->baseUrl . $endpoint;

            $multipart = [
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => $fileName,
                ],
            ];

            $response = $client->post($url, [
                'multipart' => $multipart,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully uploaded daftar bahan file',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to upload daftar bahan file',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - uploadDaftarBahan', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/products/upload',
                'file_path' => $filePath,
                'file_name' => $fileName,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload daftar produk file to halal.go.id API
     *
     * @param string $filePath - Full path to the file
     * @param string $fileName - Original file name
     * @return array - Response with status and data
     */
    public function uploadDaftarProduk(string $filePath, string $fileName): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'File not found: ' . $filePath,
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/products/upload-product';
            $url = $this->baseUrl . $endpoint;

            $multipart = [
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => $fileName,
                ],
            ];

            $response = $client->post($url, [
                'multipart' => $multipart,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully uploaded daftar produk file',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to upload daftar produk file',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - uploadDaftarProduk', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/products/upload-product',
                'file_path' => $filePath,
                'file_name' => $fileName,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Bulk insert products to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param array $products - Array of products with reg_prod_name
     * @return array - Response with status and data
     */
    public function bulkInsertProducts(string $idReg, array $products): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/products/bulkInsert-product';
            $url = $this->baseUrl . $endpoint . '?id_reg=' . urlencode($idReg);

            $body = json_encode($products);

            $response = $client->put($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully bulk inserted products',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to bulk insert products',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - bulkInsertProducts', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/products/bulkInsert-product',
                'id_reg' => $idReg,
                'products' => $products,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Bulk insert bahan (materials) to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $idPabrik - Factory ID
     * @param array $validatedBahan - Array of validated bahan from upload response
     * @return array - Response with status and data
     */
    public function bulkInsertBahan(string $idReg, string $idPabrik, array $validatedBahan): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/products/bulkInsert';
            $url = $this->baseUrl . $endpoint . '?id_reg=' . urlencode($idReg);

            // Format bahan data from validated_bahan
            $bahanData = [];
            foreach ($validatedBahan as $item) {
                $bahan = $item['HalalCertificateRegulerBahan'] ?? null;
                if (!$bahan) {
                    continue;
                }

                // Format tgl_berlaku_sertifikat to ISO 8601 format if exists
                $tglBerlaku = $bahan['tgl_berlaku_sertifikat'] ?? null;
                if ($tglBerlaku && !str_ends_with($tglBerlaku, 'T00:00:00Z')) {
                    $tglBerlaku = date('Y-m-d\TH:i:s\Z', strtotime($tglBerlaku));
                }

                $bahanData[] = [
                    'no_sertifikat_halal' => $bahan['no_sertifikat_halal'] ?? '',
                    'reg_publish' => true,
                    'id_pabrik' => $idPabrik,
                    'foto_bahan' => $bahan['foto_bahan'] ?? '',
                    'merek' => $bahan['merek'] ?? '',
                    'produsen' => $bahan['produsen'] ?? '',
                    'tgl_berlaku_sertifikat' => $tglBerlaku ?? '2025-12-31T00:00:00Z',
                    'jenis_bahan' => $bahan['jenis_bahan'] ?? '',
                    'f_pendamping' => $bahan['f_pendamping'] ?? false,
                    'kelompok' => $bahan['kelompok'] ?? '',
                    'id_reg' => $bahan['id_reg'] ?? $idReg,
                    'reg_nama_bahan' => $bahan['reg_nama_bahan'] ?? '',
                    'supplier' => $bahan['supplier'] ?? '',
                    'negara' => $bahan['negara'] ?? '',
                    'lembaga' => $bahan['lembaga'] ?? '',
                    'komposisi' => $bahan['komposisi'] ?? '',
                    'alur_proses_produksi' => $bahan['alur_proses_produksi'] ?? '',
                    'coa' => $bahan['coa'] ?? '',
                ];
            }

            $body = json_encode($bahanData);

            $response = $client->put($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully bulk inserted bahan',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to bulk insert bahan',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - bulkInsertBahan', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/products/bulkInsert',
                'id_reg' => $idReg,
                'id_pabrik' => $idPabrik,
                'bahan_count' => count($validatedBahan),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get ingredient list from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @return array - Response with status and data
     */
    public function getIngredientList(string $idReg): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);

            $endpoint = '/api/self-declare/business-actor/ingredient/list';
            $url = $this->baseUrl . $endpoint . '?id_reg=' . urlencode($idReg);

            $response = $client->get($url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved ingredient list',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get ingredient list',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get ingredient list';

            Log::warning('HalalGoId API BadResponse - getIngredientList', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getIngredientList', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/self-declare/business-actor/ingredient/list',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove ingredient from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @param string $productId - Product ID
     * @return array - Response with status and data
     */
    public function removeIngredient(string $idReg, string $productId): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/ingredients/remove';
            $url = $this->baseUrl . $endpoint . '?id_reg=' . urlencode($idReg) . '&product_id=' . urlencode($productId);

            $response = $client->delete($url);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - removeIngredient', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'product_id' => $productId,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully removed ingredient',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to remove ingredient';

                Log::warning('HalalGoId API BadResponse - removeIngredient', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'product_id' => $productId,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to remove ingredient';

            Log::warning('HalalGoId API BadResponseException - removeIngredient', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'product_id' => $productId,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - removeIngredient', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/ingredients/remove',
                'id_reg' => $idReg,
                'product_id' => $productId,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get product list from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @return array - Response with status and data
     */
    public function getProductListForReset(string $idReg): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/products/list';
            $url = $this->baseUrl . $endpoint . '?id_reg=' . urlencode($idReg);

            $response = $client->get($url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved product list',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get product list',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get product list';

            Log::warning('HalalGoId API BadResponse - getProductListForReset', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getProductListForReset', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/products/list',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove product from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @param string $productId - Product ID
     * @return array - Response with status and data
     */
    public function removeProduct(string $idReg, string $productId): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/products/remove';
            $url = $this->baseUrl . $endpoint . '?id_reg=' . urlencode($idReg) . '&product_id=' . urlencode($productId);

            $response = $client->delete($url);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - removeProduct', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'product_id' => $productId,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully removed product',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to remove product';

                Log::warning('HalalGoId API BadResponse - removeProduct', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'product_id' => $productId,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to remove product';

            Log::warning('HalalGoId API BadResponseException - removeProduct', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'product_id' => $productId,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - removeProduct', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/products/remove',
                'id_reg' => $idReg,
                'product_id' => $productId,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get layout list from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @param int $page - Page number (default: 1)
     * @param int $size - Page size (default: 10)
     * @return array - Response with status and data
     */
    public function getLayoutList(string $idReg, int $page = 1, int $size = 10): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-proses/list-layout';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg) . '&page=' . $page . '&size=' . $size;

            $response = $client->get($url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved layout list',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get layout list',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get layout list';

            Log::warning('HalalGoId API BadResponse - getLayoutList', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getLayoutList', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-proses/list-layout',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove layout from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @param string $idLayout - Layout ID to delete
     * @return array - Response with status and data
     */
    public function removeLayout(string $idReg, string $idLayout): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-proses/remove-layout';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'id' => $idReg,
                'id_reg_layout' => $idLayout,
            ]);

            $response = $client->delete($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - removeLayout', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'id_layout' => $idLayout,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully removed layout',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to remove layout';

                Log::warning('HalalGoId API BadResponse - removeLayout', [
                    'status_code' => $statusCode,
                    'id_reg' => $id_reg,
                    'id_layout' => $idLayout,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to remove layout';

            Log::warning('HalalGoId API BadResponseException - removeLayout', [
                'status_code' => $statusCode,
                'id_reg' => $id_reg,
                'id_layout' => $idLayout,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - removeLayout', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-proses/remove-layout',
                'id_reg' => $id_reg,
                'id_layout' => $idLayout,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload submission document (catatan pembelian) to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $filePath - Full path to the file
     * @param string $fileName - Original file name
     * @param string $type - Document type (e.g., "produk")
     * @return array - Response with status and data
     */
    public function uploadSubmissionDocument(string $idReg, string $filePath, string $fileName, string $type = 'produk'): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'File not found: ' . $filePath,
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/shln/submission/document/upload';
            $url = $this->baseUrl . $endpoint;

            $multipart = [
                [
                    'name' => 'id',
                    'contents' => $idReg,
                ],
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => $fileName,
                ],
                [
                    'name' => 'type',
                    'contents' => $type,
                ],
            ];

            $response = $client->post($url, [
                'multipart' => $multipart,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully uploaded submission document',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to upload submission document',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - uploadSubmissionDocument', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/shln/submission/document/upload',
                'id_reg' => $idReg,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'type' => $type,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create catatan pembelian to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $fileDok - File URL from uploadSubmissionDocument response
     * @param string $nama - Catatan name (optional, default empty string)
     * @return array - Response with status and data
     */
    public function createCatatanPembelian(string $idReg, string $fileDok, string $nama = ''): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/catatan/create';
            $url = $this->baseUrl . $endpoint . '?id_reg=' . urlencode($idReg);

            $body = json_encode([
                'nama' => $nama,
                'file_dok' => $fileDok,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully created catatan pembelian',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to create catatan pembelian',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - createCatatanPembelian', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/catatan/create',
                'id_reg' => $idReg,
                'file_dok' => $fileDok,
                'nama' => $nama,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process catatan pembelian from JotForm payload
     * Combines upload and create catatan in one flow
     *
     * @param string $idReg - ID Registrasi
     * @param array $catatanPembelianField - JotForm field with structure: {name, text, answer}
     * @param string $storageBasePath - Base path where files are stored
     * @return array - Response with status and data
     */
    public function processCatatanPembelian(string $idReg, array $catatanPembelianField, string $storageBasePath): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            // Extract text and clean it for nama catatan
            $catatanPembelianText = $catatanPembelianField['text'] ?? '';
            $namaCatatan = preg_replace('/[\s.]*\(Upload file dalam format[^)]*\)/', '', $catatanPembelianText);
            $namaCatatan = trim($namaCatatan);

            // Extract file path from answer
            $filePath = $catatanPembelianField['answer'][0] ?? '';

            if (empty($filePath)) {
                return [
                    'success' => false,
                    'status' => 400,
                    'message' => 'File path not found in answer',
                ];
            }

            // Get filename from path
            $fileName = basename($filePath);

            // Build full file path
            $fullFilePath = $storageBasePath . '/' . $filePath;

            if (!file_exists($fullFilePath)) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'File not found: ' . $fullFilePath,
                ];
            }

            // STEP 1: Upload file
            $uploadResult = $this->uploadSubmissionDocument(
                idReg: $idReg,
                filePath: $fullFilePath,
                fileName: $fileName,
                type: 'produk'
            );

            if (!$uploadResult['success']) {
                return [
                    'success' => false,
                    'status' => $uploadResult['status'],
                    'message' => 'Failed to upload catatan pembelian: ' . $uploadResult['message'],
                ];
            }

            // STEP 2: Extract file_url from response
            $fileUrl = $uploadResult['data']['data']['file_url'] ?? null;

            if (!$fileUrl) {
                return [
                    'success' => false,
                    'status' => 500,
                    'message' => 'file_url not found in upload response',
                ];
            }

            // STEP 3: Create catatan pembelian
            $createResult = $this->createCatatanPembelian(
                idReg: $idReg,
                fileDok: $fileUrl,
                nama: $namaCatatan
            );

            if (!$createResult['success']) {
                return [
                    'success' => false,
                    'status' => $createResult['status'],
                    'message' => 'Failed to create catatan pembelian: ' . $createResult['message'],
                ];
            }

            return [
                'success' => true,
                'status' => $createResult['status'],
                'data' => [
                    'upload' => $uploadResult['data'],
                    'create' => $createResult['data'],
                    'nama' => $namaCatatan,
                    'file_url' => $fileUrl,
                ],
                'message' => 'Successfully processed catatan pembelian',
            ];

        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - processCatatanPembelian', [
                'message' => $e->getMessage(),
                'id_reg' => $idReg,
                'catatan_field' => $catatanPembelianField,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add formulir to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $fileDok - File URL from upload response
     * @param string $nama - Form name (optional, default empty string)
     * @return array - Response with status and data
     */
    public function addFormulir(string $idReg, string $fileDok, string $nama = ''): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/formulir/add-formulir';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'nama' => $nama,
                'file_dok' => $fileDok,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully added formulir',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to add formulir',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - addFormulir', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/formulir/add-formulir',
                'id_reg' => $idReg,
                'file_dok' => $fileDok,
                'nama' => $nama,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add diagram alur to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $fileDok - File URL from upload response
     * @param string $namaProduk - Product name (optional, default empty string)
     * @return array - Response with status and data
     */
    public function addDiagramAlur(string $idReg, string $fileDok, string $namaProduk = ''): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-proses/diagram-alur/add';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'nama_produk' => $namaProduk,
                'file_dok' => $fileDok,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully added diagram alur',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to add diagram alur',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - addDiagramAlur', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-proses/diagram-alur/add',
                'id_reg' => $idReg,
                'file_dok' => $fileDok,
                'nama_produk' => $namaProduk,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get diagram alur list from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @param int $page - Page number (default: 1)
     * @param int $size - Page size (default: 10)
     * @return array - Response with status and data
     */
    public function getDiagramAlurList(string $idReg, int $page = 1, int $size = 10): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-proses/diagram-alur/list';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg) . '&page=' . $page . '&size=' . $size;

            $response = $client->get($url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved diagram alur list',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get diagram alur list',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get diagram alur list';

            Log::warning('HalalGoId API BadResponse - getDiagramAlurList', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getDiagramAlurList', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-proses/diagram-alur/list',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove diagram alur from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @param string $idDiagramAlur - Diagram Alur ID to delete
     * @return array - Response with status and data
     */
    public function removeDiagramAlur(string $idReg, string $idDiagramAlur): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-proses/diagram-alur/remove';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'id' => $idReg,
                'id_narasi' => $idDiagramAlur,
            ]);

            $response = $client->delete($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - removeDiagramAlur', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'id_diagram_alur' => $idDiagramAlur,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully removed diagram alur',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to remove diagram alur';

                Log::warning('HalalGoId API BadResponse - removeDiagramAlur', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'id_diagram_alur' => $idDiagramAlur,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to remove diagram alur';

            Log::warning('HalalGoId API BadResponseException - removeDiagramAlur', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'id_diagram_alur' => $idDiagramAlur,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - removeDiagramAlur', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-proses/diagram-alur/remove',
                'id_reg' => $idReg,
                'id_diagram_alur' => $idDiagramAlur,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get produk list from tab-produk endpoint
     *
     * @param string $idReg - Registration ID
     * @return array - Response with status and data
     */
    public function getProdukTabProdukList(string $idReg): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-produk/list';
            $urlParam = 'api/v1/halal-certificate-reguler/lph/produk-mappabrik/' . $idReg . '/list';
            $url = $this->baseUrl . $endpoint . '?url=' . urlencode($urlParam);

            $response = $client->get($url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved produk list from tab-produk',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get produk list from tab-produk',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get produk list from tab-produk';

            Log::warning('HalalGoId API BadResponse - getProdukTabProdukList', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getProdukTabProdukList', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-produk/list',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove produk from tab-produk endpoint
     *
     * @param string $idProduk - Product ID to delete
     * @return array - Response with status and data
     */
    public function removeProdukTabProduk(string $idProduk): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-produk/remove';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idProduk);

            $response = $client->delete($url);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - removeProdukTabProduk', [
                    'status_code' => $statusCode,
                    'id_produk' => $idProduk,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully removed produk from tab-produk',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to remove produk from tab-produk';

                Log::warning('HalalGoId API BadResponse - removeProdukTabProduk', [
                    'status_code' => $statusCode,
                    'id_produk' => $idProduk,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to remove produk from tab-produk';

            Log::warning('HalalGoId API BadResponseException - removeProdukTabProduk', [
                'status_code' => $statusCode,
                'id_produk' => $idProduk,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - removeProdukTabProduk', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-produk/remove',
                'id_produk' => $idProduk,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get dokumen evaluasi list from tab-evaluasi endpoint
     *
     * @param string $idReg - Registration ID
     * @return array - Response with status and data
     */
    public function getDokumenEvaluasiList(string $idReg): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-evaluasi/list-dokumen';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $response = $client->get($url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved dokumen evaluasi list',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get dokumen evaluasi list',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get dokumen evaluasi list';

            Log::warning('HalalGoId API BadResponse - getDokumenEvaluasiList', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getDokumenEvaluasiList', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-evaluasi/list-dokumen',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete dokumen evaluasi from tab-evaluasi endpoint
     *
     * @param string $idReg - Registration ID
     * @param string $docId - Document ID to delete
     * @return array - Response with status and data
     */
    public function deleteDokumenEvaluasi(string $idReg, string $docId): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-evaluasi/delete-dokumen';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg) . '&docId=' . urlencode($docId);

            $response = $client->delete($url);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - deleteDokumenEvaluasi', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'doc_id' => $docId,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully deleted dokumen evaluasi',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to delete dokumen evaluasi';

                Log::warning('HalalGoId API BadResponse - deleteDokumenEvaluasi', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'doc_id' => $docId,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to delete dokumen evaluasi';

            Log::warning('HalalGoId API BadResponseException - deleteDokumenEvaluasi', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'doc_id' => $docId,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - deleteDokumenEvaluasi', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-evaluasi/delete-dokumen',
                'id_reg' => $idReg,
                'doc_id' => $docId,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get TTD list from tab-evaluasi endpoint
     *
     * @param string $idReg - Registration ID
     * @return array - Response with status and data
     */
    public function getTTDList(string $idReg): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-evaluasi/list-ttd';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $response = $client->get($url, [
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved TTD list',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get TTD list',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get TTD list';

            Log::warning('HalalGoId API BadResponse - getTTDList', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getTTDList', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-evaluasi/list-ttd',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete TTD from tab-evaluasi endpoint
     *
     * @param string $idReg - Registration ID
     * @param string $docId - Document ID to delete (from id_reg_ttd field)
     * @return array - Response with status and data
     */
    public function deleteTTD(string $idReg, string $docId): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-evaluasi/delete-ttd';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg) . '&docId=' . urlencode($docId);

            $response = $client->delete($url);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - deleteTTD', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'doc_id' => $docId,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully deleted TTD',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to delete TTD';

                Log::warning('HalalGoId API BadResponse - deleteTTD', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'doc_id' => $docId,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to delete TTD';

            Log::warning('HalalGoId API BadResponseException - deleteTTD', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'doc_id' => $docId,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - deleteTTD', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-evaluasi/delete-ttd',
                'id_reg' => $idReg,
                'doc_id' => $docId,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add layout/denah to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $fileLayout - File URL from upload response
     * @param string|null $idPabrik - Factory ID (optional)
     * @return array - Response with status and data
     */
    public function addLayout(string $idReg, string $fileLayout, ?string $idPabrik = null, ?string $namaPabrik = null): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-proses/add-layout';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'file_layout' => $fileLayout,
                'nama_pabrik' => $namaPabrik,
                'id_pabrik' => $idPabrik ?? '',
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully added layout',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to add layout',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - addLayout', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-proses/add-layout',
                'id_reg' => $idReg,
                'file_layout' => $fileLayout,
                'id_pabrik' => $idPabrik,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get product list from halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @return array - Response with status and data
     */
    public function getProductList(string $idReg): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-bahan/products/list';
            $url = $this->baseUrl . $endpoint . '?id_reg=' . urlencode($idReg);

            $request = new Request('GET', $url);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved product list',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get product list',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getProductList', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-bahan/products/list',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create products to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $idPabrik - Factory ID
     * @param array $productIds - Array of product IDs
     * @return array - Response with status and data
     */
    public function createProducts(string $idReg, string $idPabrik, array $productIds): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-produk/create';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'id_pabrik' => $idPabrik,
                'id_produk' => $productIds,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully created products',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to create products',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - createProducts', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-produk/create',
                'id_reg' => $idReg,
                'id_pabrik' => $idPabrik,
                'product_ids' => $productIds,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Upload dokumen lainnya to halal.go.id API (tab-evaluasi)
     *
     * @param string $idReg - ID Registrasi
     * @param string $filePath - Full path to the file
     * @param string $fileName - Original file name
     * @return array - Response with status and data
     */
    public function uploadDokumenLainnya(string $idReg, string $filePath, string $fileName): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'File not found: ' . $filePath,
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-evaluasi/upload-file';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $multipart = [
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => $fileName,
                ],
            ];

            $response = $client->post($url, [
                'multipart' => $multipart,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully uploaded dokumen lainnya',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to upload dokumen lainnya',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - uploadDokumenLainnya', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-evaluasi/upload-file',
                'id_reg' => $idReg,
                'file_path' => $filePath,
                'file_name' => $fileName,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add dokumen lainnya to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $fileDok - File URL from upload response
     * @param string $namaDokumen - Document name
     * @return array - Response with status and data
     */
    public function addDokumenLainnya(string $idReg, string $fileDok, string $namaDokumen): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-evaluasi/add-dokumen';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'nama_dokumen' => $namaDokumen,
                'file_dok' => $fileDok,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully added dokumen lainnya',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to add dokumen lainnya',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - addDokumenLainnya', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-evaluasi/add-dokumen',
                'id_reg' => $idReg,
                'file_dok' => $fileDok,
                'nama_dokumen' => $namaDokumen,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process dokumen lainnya from JotForm payload
     * Combines upload and add dokumen in one flow
     *
     * @param string $idReg - ID Registrasi
     * @param array $dokumenField - JotForm field with structure: {name, text, answer}
     * @param string $storageBasePath - Base path where files are stored
     * @return array - Response with status and data
     */
    public function processDokumenLainnya(string $idReg, array $dokumenField, string $storageBasePath): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            // Extract text for nama dokumen (clean format text)
            $dokumenText = $dokumenField['text'] ?? '';
            $namaDokumen = preg_replace('/\s*\(Upload file dalam format[^)]*\)/', '', $dokumenText);
            $namaDokumen = trim($namaDokumen);

            // Truncate to max 50 characters (database constraint)
            $namaDokumen = mb_substr($namaDokumen, 0, 50, 'UTF-8');

            // Extract file path from answer
            $filePath = $dokumenField['answer'][0] ?? '';

            if (empty($filePath)) {
                return [
                    'success' => false,
                    'status' => 400,
                    'message' => 'File path not found in answer',
                ];
            }

            // Get filename from path
            $fileName = basename($filePath);

            // Build full file path
            $fullFilePath = $storageBasePath . '/' . $filePath;

            if (!file_exists($fullFilePath)) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'File not found: ' . $fullFilePath,
                ];
            }

            // STEP 1: Upload file
            $uploadResult = $this->uploadDokumenLainnya(
                idReg: $idReg,
                filePath: $fullFilePath,
                fileName: $fileName
            );

            if (!$uploadResult['success']) {
                return [
                    'success' => false,
                    'status' => $uploadResult['status'],
                    'message' => 'Failed to upload dokumen lainnya: ' . $uploadResult['message'],
                ];
            }

            // STEP 2: Extract file_url from response
            $fileUrl = $uploadResult['data']['data']['file_url'] ?? null;

            if (!$fileUrl) {
                return [
                    'success' => false,
                    'status' => 500,
                    'message' => 'file_url not found in upload response',
                ];
            }

            // STEP 3: Add dokumen lainnya
            $addResult = $this->addDokumenLainnya(
                idReg: $idReg,
                fileDok: $fileUrl,
                namaDokumen: $namaDokumen
            );

            if (!$addResult['success']) {
                return [
                    'success' => false,
                    'status' => $addResult['status'],
                    'message' => 'Failed to add dokumen lainnya: ' . $addResult['message'],
                ];
            }

            return [
                'success' => true,
                'status' => $addResult['status'],
                'data' => [
                    'upload' => $uploadResult['data'],
                    'add' => $addResult['data'],
                    'nama_dokumen' => $namaDokumen,
                    'file_url' => $fileUrl,
                ],
                'message' => 'Successfully processed dokumen lainnya',
            ];

        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - processDokumenLainnya', [
                'message' => $e->getMessage(),
                'id_reg' => $idReg,
                'dokumen_field' => $dokumenField,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add TTD to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $namaPenyelia - Name of supervisor
     * @param string $ttdPj - File URL for TTD Penanggung Jawab
     * @param string $ttdPh - File URL for TTD Penyelia Halal
     * @return array - Response with status and data
     */
    public function addTTD(string $idReg, string $namaPenyelia, string $ttdPj, string $ttdPh): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/tab-evaluasi/add-ttd';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'nama_penyelia' => $namaPenyelia,
                'ttd_pj' => $ttdPj,
                'ttd_ph' => $ttdPh,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully added TTD',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to add TTD',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - addTTD', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/tab-evaluasi/add-ttd',
                'id_reg' => $idReg,
                'nama_penyelia' => $namaPenyelia,
                'ttd_pj' => $ttdPj,
                'ttd_ph' => $ttdPh,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add Komitmen Tanggung Jawab to halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @param string $nama - Name of the team member
     * @param string $posisi - Position/role (e.g., "Ketua", "Anggota")
     * @param string $jabatan - Job title (default: "Tim Manajemen Halal")
     * @return array - Response with status and data
     */
    public function addKomitmenTanggungJawab(string $idReg, string $nama, string $posisi, string $jabatan = 'Tim Manajemen Halal'): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/add-komitmen-tanggung-jawab';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg);

            $body = json_encode([
                'nama' => $nama,
                'jabatan' => $jabatan,
                'posisi' => $posisi,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully added Komitmen Tanggung Jawab',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to add Komitmen Tanggung Jawab',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - addKomitmenTanggungJawab', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/add-komitmen-tanggung-jawab',
                'id_reg' => $idReg,
                'nama' => $nama,
                'posisi' => $posisi,
                'jabatan' => $jabatan,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete Komitmen Tanggung Jawab from halal.go.id API
     *
     * @param string $id - Registration ID (id_reg)
     * @param string $idEdit - ID of the komitmen to delete (id_reg_tim)
     * @return array - Response with status and data
     */
    public function deleteKomitmenTanggungJawab(string $id, string $idEdit): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/delete-komitmen-tanggung-jawab';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($id) . '&id_edit=' . urlencode($idEdit);

            $response = $client->delete($url);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - deleteKomitmenTanggungJawab', [
                    'status_code' => $statusCode,
                    'id' => $id,
                    'id_edit' => $idEdit,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully deleted Komitmen Tanggung Jawab',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to delete Komitmen Tanggung Jawab';

                Log::warning('HalalGoId API BadResponse - deleteKomitmenTanggungJawab', [
                    'status_code' => $statusCode,
                    'id' => $id,
                    'id_edit' => $idEdit,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to delete Komitmen Tanggung Jawab';

            Log::warning('HalalGoId API BadResponseException - deleteKomitmenTanggungJawab', [
                'status_code' => $statusCode,
                'id' => $id,
                'id_edit' => $idEdit,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - deleteKomitmenTanggungJawab', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/delete-komitmen-tanggung-jawab',
                'id' => $id,
                'id_edit' => $idEdit,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get provinces from halal.go.id API
     *
     * @return array - Response with status and data
     */
    public function getProvinces(): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
                'decode_content' => 'gzip',
            ]);

            $endpoint = '/api/master/province';
            $url = $this->baseUrl . $endpoint;

            $request = new Request('GET', $url);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved provinces',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get provinces',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getProvinces', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/master/province',
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get districts by province code from halal.go.id API
     *
     * @param string $provinceCode - Province code
     * @return array - Response with status and data
     */
    public function getDistricts(string $provinceCode): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => 'gzip',
            ]);

            $endpoint = '/api/master/district';
            $url = $this->baseUrl . $endpoint;

            $body = json_encode([
                'province' => $provinceCode,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved districts',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get districts',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getDistricts', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/master/district',
                'province_code' => $provinceCode,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get factory status codes from halal.go.id API
     *
     * @return array - Response with status and data
     */
    public function getFactoryStatusCodes(): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                ],
                'decode_content' => 'gzip',
            ]);

            $endpoint = '/api/master/common-code';
            $url = $this->baseUrl . $endpoint . '?type=factorystatus';

            $request = new Request('GET', $url);
            $response = $client->sendAsync($request)->wait();

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved factory status codes',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get factory status codes',
                    'body' => $responseBody,
                ];
            }
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getFactoryStatusCodes', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/master/common-code',
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add factory to halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param array $factoryData - Factory data (name, address, city, province, country, zip_code, status)
     * @return array - Response with status and data
     */
    public function addFactory(string $profileId, array $factoryData): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => 'gzip',
            ]);

            $endpoint = '/api/pelaku-usaha-profile/' . $profileId . '/add-factory';
            $url = $this->baseUrl . $endpoint;

            $body = json_encode($factoryData);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('Factory added successfully', [
                    'profile_id' => $profileId,
                    'factory_data' => $factoryData,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Factory added successfully',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to add factory',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to add factory';
            $errors = $decodedError['errors'] ?? [];

            Log::error('HalalGoId API BadResponseException - addFactory', [
                'status_code' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'endpoint' => '/api/pelaku-usaha-profile/{id}/add-factory',
                'id_reg' => $idReg,
                'factory_data' => $factoryData,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - addFactory', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/pelaku-usaha-profile/{id}/add-factory',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add factory to submission from halal.go.id API
     *
     * @param string $idReg - ID Registrasi
     * @param string $idPabrik - Factory ID
     * @return array - Response with status and data
     */
    public function addFactoryToSubmission(string $idReg, string $idPabrik): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => 'gzip',
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/add-factory';
            $url = $this->baseUrl . $endpoint;

            $body = json_encode([
                'id_reg' => $idReg,
                'id_pabrik' => [$idPabrik],
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('Factory added to submission successfully', [
                    'id_reg' => $idReg,
                    'id_pabrik' => $idPabrik,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Factory added to submission successfully',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to add factory to submission',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to add factory to submission';
            $errors = $decodedError['errors'] ?? [];

            Log::error('HalalGoId API BadResponseException - addFactoryToSubmission', [
                'status_code' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'endpoint' => '/api/reguler/pelaku-usaha/add-factory',
                'id_reg' => $idReg,
                'id_pabrik' => $idPabrik,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - addFactoryToSubmission', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/add-factory',
                'id_reg' => $idReg,
                'id_pabrik' => $idPabrik,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete factory from halal.go.id API
     *
     * @param string $idPabrik - Factory ID to delete
     * @return array - Response with status and data
     */
    public function deleteFactory(string $idPabrik): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => 'gzip',
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/delete-factory';
            $url = $this->baseUrl . $endpoint;

            $body = json_encode([
                'id' => $idPabrik,
            ]);

            $response = $client->delete($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('Factory deleted successfully', [
                    'id_pabrik' => $idPabrik,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Factory deleted successfully',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to delete factory',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to delete factory';
            $errors = $decodedError['errors'] ?? [];

            $logLevel = ($statusCode >= 400 && $statusCode < 500) ? 'warning' : 'error';

            Log::$logLevel('HalalGoId API BadResponseException - deleteFactory', [
                'status_code' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'endpoint' => '/api/reguler/pelaku-usaha/delete-factory',
                'id_pabrik' => $idPabrik,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'errors' => $errors,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - deleteFactory', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/delete-factory',
                'id_pabrik' => $idPabrik,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit submission to halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @return array - Response with status and data
     */
    public function submitSubmission(string $idReg): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'allow_redirects' => true,
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Authorization' => $this->getFormattedToken(),
                    'Connection' => 'keep-alive',
                    'Content-Type' => 'application/json',
                ],
                'decode_content' => true,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/submit';
            $url = $this->baseUrl . $endpoint;

            $body = json_encode([
                'id_reg' => $idReg,
            ]);

            $response = $client->post($url, [
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - submitSubmission', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'response' => $data,
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully submitted submission',
                ];
            } else {
                $decodedError = json_decode($responseBody, true);
                $errorMessage = $decodedError['message'] ?? 'Failed to submit submission';

                Log::warning('HalalGoId API BadResponse - submitSubmission', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ]);

                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => $errorMessage,
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to submit submission';

            Log::warning('HalalGoId API BadResponseException - submitSubmission', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - submitSubmission', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/submit',
                'id_reg' => $idReg,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get detail tab data from halal.go.id API
     *
     * @param string $idReg - Registration ID
     * @param string $type - Tab type (e.g., 'tim-manajemen-halal')
     * @param int $page - Page number (default: 1)
     * @param int $size - Page size (default: 10)
     * @return array - Response with status and data
     */
    public function getDetailTab(string $idReg, string $type, int $page = 1, int $size = 10): array
    {
        try {
            if (empty($this->bearerToken)) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Bearer token is required',
                ];
            }

            $client = new Client([
                'verify' => false,
                'timeout' => 30,
            ]);

            $endpoint = '/api/reguler/pelaku-usaha/detail-tab';
            $url = $this->baseUrl . $endpoint . '?id=' . urlencode($idReg) . '&type=' . urlencode($type) . '&page=' . $page . '&size=' . $size;

            Log::info('getDetailTab Request', [
                'url' => $url,
                'id_reg' => $idReg,
                'type' => $type,
            ]);

            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => $this->getFormattedToken(),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($responseBody, true);

                Log::info('HalalGoId API Success - getDetailTab', [
                    'status_code' => $statusCode,
                    'id_reg' => $idReg,
                    'type' => $type,
                    'total_item' => $data['total_item'] ?? 'N/A',
                    'data_count' => count($data['data'] ?? []),
                ]);

                return [
                    'success' => true,
                    'status' => $statusCode,
                    'data' => $data,
                    'message' => 'Successfully retrieved detail tab',
                ];
            } else {
                return [
                    'success' => false,
                    'status' => $statusCode,
                    'message' => 'Failed to get detail tab',
                    'body' => $responseBody,
                ];
            }
        } catch (BadResponseException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = (string) $e->getResponse()->getBody();
            $decodedError = json_decode($responseBody, true);

            $errorMessage = $decodedError['message'] ?? 'Failed to get detail tab';

            Log::warning('HalalGoId API BadResponse - getDetailTab', [
                'status_code' => $statusCode,
                'id_reg' => $idReg,
                'type' => $type,
                'message' => $errorMessage,
                'body' => $responseBody,
            ]);

            return [
                'success' => false,
                'status' => $statusCode,
                'message' => $errorMessage,
                'body' => $responseBody,
            ];
        } catch (\Exception $e) {
            Log::error('HalalGoId API Exception - getDetailTab', [
                'message' => $e->getMessage(),
                'endpoint' => '/api/reguler/pelaku-usaha/detail-tab',
                'id_reg' => $idReg,
                'type' => $type,
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }
}
