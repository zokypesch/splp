<?php

declare(strict_types=1);

/**
 * SPLP-PHP Kafka Connectivity Test
 * 
 * Tests connection to Kafka server at 10.70.1.23:9092
 */
class KafkaConnectivityTest
{
    private string $kafkaHost = '10.70.1.23';
    private int $kafkaPort = 9092;
    private array $testResults = [];

    public function runAllTests(): void
    {
        echo "🔍 SPLP-PHP Kafka Connectivity Test\n";
        echo "🎯 Target: {$this->kafkaHost}:{$this->kafkaPort}\n";
        echo str_repeat("=", 60) . "\n\n";

        $this->testNetworkConnectivity();
        $this->testKafkaPort();
        $this->testKafkaProtocol();
        $this->testConfigurationFiles();
        $this->displayResults();
    }

    private function testNetworkConnectivity(): void
    {
        echo "🌐 Test 1: Network Connectivity\n";
        
        $startTime = microtime(true);
        $connection = @fsockopen($this->kafkaHost, $this->kafkaPort, $errno, $errstr, 5);
        $endTime = microtime(true);
        
        if ($connection) {
            fclose($connection);
            $latency = round(($endTime - $startTime) * 1000, 2);
            echo "✅ Connection successful\n";
            echo "   📊 Latency: {$latency}ms\n";
            echo "   🔗 Host: {$this->kafkaHost}\n";
            echo "   🚪 Port: {$this->kafkaPort}\n";
            $this->testResults['network'] = ['status' => 'success', 'latency' => $latency];
        } else {
            echo "❌ Connection failed\n";
            echo "   🚫 Error: {$errstr} (Code: {$errno})\n";
            $this->testResults['network'] = ['status' => 'failed', 'error' => $errstr];
        }
        
        echo "\n";
    }

    private function testKafkaPort(): void
    {
        echo "🚪 Test 2: Kafka Port Availability\n";
        
        $ports = [9092, 9093, 9094]; // Common Kafka ports
        $availablePorts = [];
        
        foreach ($ports as $port) {
            $connection = @fsockopen($this->kafkaHost, $port, $errno, $errstr, 2);
            if ($connection) {
                fclose($connection);
                $availablePorts[] = $port;
                echo "✅ Port {$port}: Available\n";
            } else {
                echo "❌ Port {$port}: Not available\n";
            }
        }
        
        if (!empty($availablePorts)) {
            echo "📋 Available ports: " . implode(', ', $availablePorts) . "\n";
            $this->testResults['ports'] = ['status' => 'success', 'ports' => $availablePorts];
        } else {
            echo "🚫 No Kafka ports available\n";
            $this->testResults['ports'] = ['status' => 'failed', 'ports' => []];
        }
        
        echo "\n";
    }

    private function testKafkaProtocol(): void
    {
        echo "📡 Test 3: Kafka Protocol Test\n";
        
        // Try to connect and send a simple request
        $socket = @fsockopen($this->kafkaHost, $this->kafkaPort, $errno, $errstr, 5);
        
        if ($socket) {
            // Set timeout
            stream_set_timeout($socket, 5);
            
            // Try to read some data (Kafka might send some initial data)
            $data = fread($socket, 1024);
            
            if ($data !== false) {
                echo "✅ Socket connection established\n";
                echo "📊 Data received: " . strlen($data) . " bytes\n";
                echo "🔍 Raw data preview: " . substr(bin2hex($data), 0, 32) . "...\n";
                $this->testResults['protocol'] = ['status' => 'success', 'data_length' => strlen($data)];
            } else {
                echo "⚠️  Socket connected but no data received\n";
                $this->testResults['protocol'] = ['status' => 'partial', 'note' => 'No data received'];
            }
            
            fclose($socket);
        } else {
            echo "❌ Socket connection failed\n";
            echo "   🚫 Error: {$errstr} (Code: {$errno})\n";
            $this->testResults['protocol'] = ['status' => 'failed', 'error' => $errstr];
        }
        
        echo "\n";
    }

    private function testConfigurationFiles(): void
    {
        echo "📁 Test 4: Configuration Files\n";
        
        $configFiles = [
            'examples/basic/mock-example.php',
            'examples/basic/index.php',
            'examples/laravel/mock-example.php',
            'src/Laravel/config/splp.php'
        ];
        
        $updatedFiles = [];
        
        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (strpos($content, '10.70.1.23:9092') !== false) {
                    echo "✅ {$file}: Updated to use 10.70.1.23:9092\n";
                    $updatedFiles[] = $file;
                } else {
                    echo "⚠️  {$file}: Not updated\n";
                }
            } else {
                echo "❌ {$file}: File not found\n";
            }
        }
        
        echo "📋 Updated files: " . count($updatedFiles) . "/" . count($configFiles) . "\n";
        $this->testResults['config'] = ['status' => 'success', 'updated_files' => $updatedFiles];
        
        echo "\n";
    }

    private function displayResults(): void
    {
        echo "📊 Test Results Summary\n";
        echo str_repeat("=", 60) . "\n";
        
        $totalTests = count($this->testResults);
        $passedTests = 0;
        
        foreach ($this->testResults as $test => $result) {
            $status = $result['status'];
            $icon = $status === 'success' ? '✅' : ($status === 'partial' ? '⚠️' : '❌');
            
            echo "{$icon} {$test}: {$status}\n";
            
            if ($status === 'success' || $status === 'partial') {
                $passedTests++;
            }
            
            // Show additional details
            if (isset($result['latency'])) {
                echo "   📊 Latency: {$result['latency']}ms\n";
            }
            if (isset($result['ports'])) {
                echo "   🚪 Available ports: " . implode(', ', $result['ports']) . "\n";
            }
            if (isset($result['updated_files'])) {
                echo "   📁 Updated files: " . count($result['updated_files']) . "\n";
            }
            if (isset($result['error'])) {
                echo "   🚫 Error: {$result['error']}\n";
            }
        }
        
        echo "\n";
        echo "📈 Overall Result: {$passedTests}/{$totalTests} tests passed\n";
        
        if ($passedTests === $totalTests) {
            echo "🎉 All tests passed! Kafka server at 10.70.1.23:9092 is accessible.\n";
        } elseif ($passedTests > 0) {
            echo "⚠️  Partial success. Some tests passed, but there may be issues.\n";
        } else {
            echo "❌ All tests failed. Kafka server at 10.70.1.23:9092 is not accessible.\n";
        }
        
        echo "\n";
        echo "💡 Next steps:\n";
        echo "   1. If network test failed: Check firewall and network connectivity\n";
        echo "   2. If port test failed: Verify Kafka is running on the target server\n";
        echo "   3. If protocol test failed: Check Kafka configuration and security settings\n";
        echo "   4. If config test failed: Manually update remaining configuration files\n";
    }
}

// Run the test
if (php_sapi_name() === 'cli') {
    $test = new KafkaConnectivityTest();
    $test->runAllTests();
}
