<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\SplpMessagingService;

class DukcapilController extends Controller
{
    protected SplpMessagingService $splpService;

    public function __construct(SplpMessagingService $splpService)
    {
        $this->splpService = $splpService;
    }

    /**
     * Send population data to SPLP for verification
     */
    public function sendPopulationData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'registrationId' => 'required|string|max:255',
                'nik' => 'required|string|size:16',
                'fullName' => 'required|string|max:255',
                'dateOfBirth' => 'required|date',
                'address' => 'required|string|max:500',
                'assistanceType' => 'nullable|string|max:100',
                'requestedAmount' => 'nullable|numeric|min:0'
            ]);

            $response = $this->splpService->sendPopulationData($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dikirim ke Dukcapil untuk verifikasi',
                'data' => $response,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send data to Dukcapil',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Health check endpoint for SPLP services
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $healthStatus = $this->splpService->getHealthStatus();
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'services' => $healthStatus,
                'message' => 'SPLP Laravel integration is working properly'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
                'message' => 'SPLP services are not available'
            ], 500);
        }
    }

    /**
     * Get SPLP configuration info (for debugging)
     */
    public function getConfig(): JsonResponse
    {
        try {
            $config = $this->splpService->getConfiguration();
            
            // Remove sensitive data
            unset($config['encryption']['key']);
            
            return response()->json([
                'success' => true,
                'config' => $config,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test endpoint to simulate sending data
     */
    public function testSend(): JsonResponse
    {
        try {
            $testData = [
                'registrationId' => 'REG_' . uniqid(),
                'nik' => '317' . str_pad(rand(1, 999999999999), 13, '0', STR_PAD_LEFT),
                'fullName' => 'John Doe Test',
                'dateOfBirth' => '1990-01-01',
                'address' => 'Jakarta Selatan, Test Address',
                'assistanceType' => 'Bansos',
                'requestedAmount' => 500000
            ];

            $response = $this->splpService->sendPopulationData($testData);
            
            return response()->json([
                'success' => true,
                'message' => 'Test data sent successfully',
                'testData' => $testData,
                'response' => $response,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }
}
