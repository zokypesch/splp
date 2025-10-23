<?php

require_once __DIR__ . '/vendor/autoload.php';

use Splp\Messaging\Core\EncryptionService;
use Splp\Messaging\Types\EncryptedMessage;

echo "🔧 Test Decryption Fix\n";
echo "======================\n\n";

try {
    // Test dengan key yang sama
    $key = 'b9c4d62e772f6e1a4f8e0a139f50d96f7aefb2dc098fe3c53ad22b4b3a9c9e7';
    $encryptionService = new EncryptionService($key);
    $encryptionService->initialize();

    // Test data
    $testData = [
        'registrationId' => 'REG_TEST_123',
        'nik' => '3171234567890123',
        'fullName' => 'John Doe Test',
        'dateOfBirth' => '1990-01-01',
        'address' => 'Jakarta Selatan',
        'assistanceType' => 'Bansos',
        'requestedAmount' => 500000
    ];

    $requestId = 'req_test_' . uniqid();

    echo "📝 Test Data:\n";
    echo "  Request ID: {$requestId}\n";
    echo "  Registration ID: {$testData['registrationId']}\n";
    echo "  NIK: {$testData['nik']}\n";
    echo "  Name: {$testData['fullName']}\n\n";

    // Encrypt data
    echo "🔐 Encrypting data...\n";
    $encryptedMessage = $encryptionService->encrypt($testData, $requestId);
    
    echo "✅ Encryption successful!\n";
    echo "  Data Length: " . strlen($encryptedMessage->data) . " chars\n";
    echo "  IV Length: " . strlen($encryptedMessage->iv) . " chars\n";
    echo "  Tag Length: " . strlen($encryptedMessage->tag) . " chars\n\n";

    // Decrypt data
    echo "🔓 Decrypting data...\n";
    [$decryptedRequestId, $decryptedData] = $encryptionService->decrypt($encryptedMessage);
    
    echo "✅ Decryption successful!\n";
    echo "  Request ID: {$decryptedRequestId}\n";
    echo "  Registration ID: {$decryptedData['registrationId']}\n";
    echo "  NIK: {$decryptedData['nik']}\n";
    echo "  Name: {$decryptedData['fullName']}\n";
    echo "  Address: {$decryptedData['address']}\n";
    echo "  Assistance Type: {$decryptedData['assistanceType']}\n";
    echo "  Requested Amount: {$decryptedData['requestedAmount']}\n\n";

    // Test dengan IV yang tidak normal (simulasi pesan dari Kafka)
    echo "🧪 Testing with abnormal IV length...\n";
    
    // Decode IV dan buat IV dengan panjang yang berbeda
    $ivBytes = base64_decode($encryptedMessage->iv);
    $abnormalIv = str_pad($ivBytes, 16, "\0", STR_PAD_RIGHT); // 16 bytes instead of 12
    $abnormalIvEncoded = base64_encode($abnormalIv);
    
    $abnormalMessage = new EncryptedMessage(
        $encryptedMessage->data,
        $abnormalIvEncoded,
        $encryptedMessage->tag
    );
    
    echo "  Original IV length: " . strlen($ivBytes) . " bytes\n";
    echo "  Abnormal IV length: " . strlen($abnormalIv) . " bytes\n";
    
    [$decryptedRequestId2, $decryptedData2] = $encryptionService->decrypt($abnormalMessage);
    
    echo "✅ Abnormal IV decryption successful!\n";
    echo "  Request ID: {$decryptedRequestId2}\n";
    echo "  Registration ID: {$decryptedData2['registrationId']}\n\n";

    echo "🎉 All tests passed! Decryption fix is working.\n";

} catch (\Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
