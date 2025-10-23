<?php

namespace Splp\Messaging\Core;

use Splp\Messaging\Contracts\KafkaClientInterface;
use Splp\Messaging\Contracts\MessageProcessorInterface;
use Splp\Messaging\Types\EncryptedMessage;
use Splp\Messaging\Types\KafkaConfig;
use Splp\Messaging\Types\CommandCenterMessage;
use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Core\CassandraLogger;

class Service1MessageProcessor implements MessageProcessorInterface
{
    private EncryptionService $encryptor;
    private CassandraLogger $logger;
    private KafkaConfig $config;
    private KafkaClientInterface $kafkaClient;
    private string $workerName;
    private array $processedRequests = [];
    private ?string $currentRequestId = null;

    public function __construct(
        EncryptionService $encryptor,
        CassandraLogger $logger,
        KafkaConfig $config,
        KafkaClientInterface $kafkaClient,
        string $workerName = 'initial-publisher'
    ) {
        $this->encryptor = $encryptor;
        $this->logger = $logger;
        $this->config = $config;
        $this->kafkaClient = $kafkaClient;
        $this->workerName = $workerName;
    }

    public function setCurrentRequestId(?string $requestId): void
    {
        $this->currentRequestId = $requestId;
    }

    public function processMessage(EncryptedMessage $message): void
    {
        $startTime = microtime(true);

        echo str_repeat("─", 60) . "\n";
        echo "📥 [DUKCAPIL] Menerima data dari Command Center\n";

        try {
            // Decrypt the message
            [$requestId, $decryptedData] = $this->encryptor->decrypt($message);
            
            // Use currentRequestId from Command Center if available
            if ($this->currentRequestId && $this->currentRequestId !== 'unknown') {
                $requestId = $this->currentRequestId;
                echo "🔗 Using request_id from Command Center: {$requestId}\n";
            }
            
            // Clear current request ID after use
            $this->currentRequestId = null;

            // Parse the decrypted payload
            $payload = $this->parsePayload($decryptedData);

            echo "  Request ID: {$requestId}\n";
            echo "  Registration ID: {$payload['registrationId']}\n";
            echo "  NIK: {$payload['nik']}\n";
            echo "  Nama: {$payload['fullName']}\n";
            echo "  Tanggal Lahir: {$payload['dateOfBirth']}\n";
            echo "  Alamat: {$payload['address']}\n";
            echo "  Jenis Bantuan: {$payload['assistanceType']}\n";
            echo "  Nominal: Rp " . number_format($payload['requestedAmount'] ?? 0, 0, ',', '.') . "\n\n";

            // Verify population data
            echo "🔄 Memverifikasi data kependudukan...\n";
            usleep(500000); // Simulate verification delay (0.5 seconds)

            // Process the verification
            $result = $this->verifyPopulationData($payload, $requestId);

            // Encrypt processed data
            $encryptedResult = $this->encryptor->encrypt($result, $requestId);

            // Send back to Command Center
            $this->sendReply($encryptedResult, $requestId);

            $duration = microtime(true) - $startTime;
            echo "✓ Hasil verifikasi terkirim ke Command Center\n";
            echo "  Waktu proses: " . number_format($duration * 1000, 2) . "ms\n";
            echo str_repeat("─", 60) . "\n\n";

            // Log successful processing
            $this->logger->logMessage(
                $this->config->consumerTopic,
                json_encode($result),
                'processed'
            );

            // Log metadata for Command Center
            $this->logger->logMetadata([
                'request_id' => $requestId,
                'worker_name' => $this->workerName,
                'source_topic' => $this->config->consumerTopic,
                'target_topic' => $this->config->producerTopic,
                'route_id' => 'route-service1-001',
                'message_type' => 'response',
                'success' => true,
                'processing_time_ms' => (int)($duration * 1000)
            ]);

        } catch (\Exception $e) {
            echo "❌ Error processing message: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            $this->logger->logError($e->getMessage(), [
                'request_id' => $requestId ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s'),
                'error_details' => $e->getTraceAsString()
            ]);

            // Log failed metadata
            $this->logger->logMetadata([
                'request_id' => $requestId ?? 'unknown',
                'worker_name' => $this->workerName,
                'source_topic' => $this->config->consumerTopic,
                'target_topic' => $this->config->producerTopic,
                'route_id' => 'route-service1-001',
                'message_type' => 'response',
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time_ms' => null
            ]);
            
            // Don't throw the exception to prevent stopping the consumer
            // Instead, log the error and continue processing
            echo "⚠️  Continuing to process next message...\n";
        }
    }

    private function parsePayload(array $data): array
    {
        $requiredFields = ['registrationId', 'nik', 'fullName', 'dateOfBirth', 'address'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Add default values for optional fields
        $data['assistanceType'] = $data['assistanceType'] ?? 'Bansos';
        $data['requestedAmount'] = $data['requestedAmount'] ?? 0;

        return $data;
    }

    private function verifyPopulationData(array $payload, string $requestId): array
    {
        // Simulate NIK verification
        $nikValid = strlen($payload['nik']) == 16 && str_starts_with($payload['nik'], '317');
        $dataMatch = strlen($payload['fullName']) > 0;
        $familyMembers = rand(1, 5);

        $result = [
            'registrationId' => $payload['registrationId'],
            'nik' => $payload['nik'],
            'fullName' => "Hamba Allah",
            'dateOfBirth' => $payload['dateOfBirth'],
            'address' => $payload['address'],
            'assistanceType' => $payload['assistanceType'] ?? 'Bansos',
            'requestedAmount' => $payload['requestedAmount'] ?? 0,
            'processedBy' => 'dukcapil',
            'nikStatus' => $nikValid ? 'valid' : 'invalid',
            'dataMatch' => $dataMatch,
            'familyMembers' => $familyMembers,
            'addressVerified' => true,
            'verifiedAt' => date('Y-m-d H:i:s'),
            'notes' => $nikValid ? 'Data kependudukan terverifikasi' : 'NIK tidak valid',
            'requestId' => $requestId
        ];

        echo "  ✅ Status NIK: " . strtoupper($result['nikStatus']) . "\n";
        echo "  ✅ Data Cocok: " . ($dataMatch ? 'YA' : 'TIDAK') . "\n";
        echo "  ✅ Jumlah Anggota Keluarga: {$familyMembers}\n";
        echo "  ✅ Alamat Terverifikasi: " . ($result['addressVerified'] ? 'YA' : 'TIDAK') . "\n";
        echo "  📋 Catatan: {$result['notes']}\n";
        echo "  🏢 Diproses oleh: DUKCAPIL\n\n";

        return $result;
    }

    private function sendReply(EncryptedMessage $encryptedResult, string $requestId): void
    {
        // Validate inputs
        if (empty($requestId)) {
            echo "❌ Error: Request ID is empty\n";
            return;
        }

        if (empty($this->config->producerTopic)) {
            echo "❌ Error: Producer topic is not configured\n";
            return;
        }

        if (empty($this->workerName)) {
            echo "❌ Error: Worker name is not configured\n";
            return;
        }

        // Create Command Center message format
        $outgoingMessage = new CommandCenterMessage(
            requestId: $requestId,
            workerName: $this->workerName, // This identifies routing: service_1 -> service_2
            data: $encryptedResult->data,
            iv: $encryptedResult->iv,
            tag: $encryptedResult->tag
        );

        $messageBytes = $outgoingMessage->toJson();
        if ($messageBytes === false) {
            echo "❌ Error: Failed to marshal outgoing message\n";
            return;
        }

        echo "📤 Mengirim hasil verifikasi ke Command Center...\n";
        echo "  🎯 Target Topic: {$this->config->producerTopic}\n";
        echo "  🆔 Request ID: {$requestId}\n";
        echo "  👤 Worker Name: {$this->workerName}\n";
        echo "  📦 Message Size: " . strlen($messageBytes) . " bytes\n";
        
        // Send to real Kafka Command Center topic
        try {
            $this->kafkaClient->sendMessage($this->config->producerTopic, $messageBytes);
            echo "✅ Hasil verifikasi berhasil dikirim ke Command Center\n";
            echo "🔄 Command Center akan meroute ke service berikutnya\n";
        } catch (\Exception $e) {
            echo "❌ Failed to send message to Command Center: " . $e->getMessage() . "\n";
            echo "⚠️  Message processing completed but reply failed\n";
            
            // Log the error but don't throw to prevent stopping the consumer
            $this->logger->logError("Failed to send reply to Command Center: " . $e->getMessage(), [
                'request_id' => $requestId,
                'target_topic' => $this->config->producerTopic,
                'worker_name' => $this->workerName,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // Log failed metadata
            $this->logger->logMetadata([
                'request_id' => $requestId,
                'worker_name' => $this->workerName,
                'source_topic' => $this->config->consumerTopic,
                'target_topic' => $this->config->producerTopic,
                'route_id' => 'route-service1-001',
                'message_type' => 'response',
                'success' => false,
                'error' => "Failed to send reply: " . $e->getMessage(),
                'processing_time_ms' => null
            ]);
        }
    }
}
