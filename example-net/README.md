# Example-Net: Chained Service Communication

Demonstrates a complete request-reply chain using Command Center routing in .NET:

```
Publisher → Command Center → Service 1 → Command Center → Service 2
```

## Architecture

### Message Flow

1. **Publisher** creates a social assistance request and sends to **Command Center**
   - worker_name: `initial-publisher`
   - Command Center routes to: `service-1-topic`

2. **Service 1 (Dukcapil)** receives, validates, and sends back to **Command Center**
   - Processes citizen data (NIK validation, address verification)
   - worker_name: `service-1-publisher`
   - Command Center routes to: `service-2-topic`

3. **Service 2 (Kemensos)** receives and completes processing
   - Final eligibility decision
   - Saves to database
   - Sends confirmations

### Key Features

- **Chained Routing**: Services communicate through Command Center
- **Encrypted Messages**: All payloads encrypted end-to-end using AES-256-GCM
- **Metadata Logging**: Every hop logged to Cassandra (NO PAYLOAD)
- **Automatic Routing**: Command Center handles all routing based on worker_name
- **1 Week TTL**: All Kafka and Cassandra data expires after 1 week
- **Cross-platform**: Runs on .NET 8, .NET 6, and .NET Standard 2.1

## Project Structure

```
example-net/
├── Publisher/
│   ├── Publisher.csproj
│   └── Program.cs              # Initial publisher (Kemensos)
├── Service1/
│   ├── Service1.csproj
│   └── Program.cs              # Dukcapil verification service
├── Service2/
│   ├── Service2.csproj
│   └── Program.cs              # Kemensos aggregation service
├── CommandCenter/
│   ├── CommandCenter.csproj
│   └── Program.cs              # Message routing service
├── ExampleNet.sln              # Solution file
└── README.md
```

## Prerequisites

1. **.NET 8 SDK** (or .NET 6 SDK for older versions)
2. **Kafka** running on `localhost:9092`
3. **Cassandra** running on `localhost:9042`
4. **Same ENCRYPTION_KEY** for all services

## Quick Start

### Step 1: Start Infrastructure

```bash
# From project root (splp directory)
docker-compose up -d

# Wait for services to be healthy (30 seconds)
Start-Sleep 30

# Create Kafka topics (if needed)
# Topics will be auto-created by Kafka
```

### Step 2: Generate Encryption Key

```bash
# Option 1: Use the key generator
cd E:\perlinsos\splp\KeyGenerator
dotnet run

# Option 2: Use PowerShell
$bytes = [System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32)
$key = [System.BitConverter]::ToString($bytes).Replace('-', '').ToLower()
$env:ENCRYPTION_KEY = $key
Write-Host "Generated key: $key"
```

### Step 3: Set Environment Variable

```powershell
# PowerShell
$env:ENCRYPTION_KEY = "your-generated-key-here"

# Or CMD
set ENCRYPTION_KEY=your-generated-key-here
```

### Step 4: Build Solution

```bash
cd E:\perlinsos\splp\example-net
dotnet restore --force
dotnet build
```

### Step 5: Start Command Center

```bash
# Terminal 1
cd E:\perlinsos\splp\example-net\CommandCenter
dotnet run --framework net8.0
```

You should see:
```
✓ Route: initial-publisher → service-1-topic
✓ Route: service-1-publisher → service-2-topic
```

### Step 6: Start Service 1 (Dukcapil)

```bash
# Terminal 2
cd E:\perlinsos\splp\example-net\Service1
dotnet run
```

### Step 7: Start Service 2 (Kemensos)

```bash
# Terminal 3
cd E:\perlinsos\splp\example-net\Service2
dotnet run
```

### Step 8: Run Publisher

```bash
# Terminal 4
cd E:\perlinsos\splp\example-net\Publisher
dotnet run
```

## Expected Output

### Publisher Output
```
🏛️  KEMENSOS - Kementerian Sosial RI
    Social Assistance Verification System
═══════════════════════════════════════════════════════════

✓ Kemensos connected to Kafka

📋 Pengajuan Bantuan Sosial:
  Request ID: <uuid>
  Registration ID: BANSOS-1234567890
  NIK: 3174012501850001
  Nama Lengkap: Budi Santoso
  Tanggal Lahir: 1985-01-25
  Alamat: Jl. Merdeka No. 123, Jakarta Pusat
  Jenis Bantuan: PKH
  Nominal: Rp 3,000,000

✓ Data terkirim ke Command Center
  → Akan diverifikasi oleh:
     • Dukcapil (Data Kependudukan)
```

### Service 1 Output
```
🏛️  DUKCAPIL - Ditjen Kependudukan & Catatan Sipil
    Population Data Verification Service
═══════════════════════════════════════════════════════════

📥 [DUKCAPIL] Menerima data dari Command Center
  Request ID: <uuid>
  Registration ID: BANSOS-1234567890
  NIK: 3174012501850001
  Nama: Budi Santoso

🔄 Memverifikasi data kependudukan...
  ✅ Status NIK: VALID
  ✅ Data Cocok: YA
  ✅ Jumlah Anggota Keluarga: 4
  ✅ Alamat Terverifikasi: YA
  📋 Catatan: Data kependudukan terverifikasi
  🏢 Diproses oleh: DUKCAPIL

📤 Mengirim hasil verifikasi ke Command Center...
✓ Hasil verifikasi terkirim ke Command Center
```

### Service 2 Output
```
🏛️  KEMENSOS - Result Aggregation Service
    Final Social Assistance Decision System
═══════════════════════════════════════════════════════════

📬 [KEMENSOS] FINAL MESSAGE RECEIVED
📋 Order Details:
  Registration ID: BANSOS-1234567890
  NIK: 3174012501850001
  Nama: Budi Santoso
  Jenis Bantuan: PKH
  Nominal: Rp 3,000,000

✅ Processing History:
  Processed by: dukcapil
  NIK Status: valid
  Data Match: YA
  Family Members: 4
  Address Verified: YA

✅ BANTUAN SOSIAL DISETUJUI - Saving to database
✅ Sending confirmation email to citizen
✅ Updating assistance registry
✅ Scheduling disbursement

💰 Disbursement Details:
  Amount: Rp 3,000,000
  Method: Bank Transfer
  Expected Date: 2024-10-29

🎉 PROCESSING CHAIN COMPLETED!
   Publisher → Service 1 (Dukcapil) → Service 2 (Kemensos)
```

## Message Details

### Publisher Message Structure
```json
{
  "request_id": "uuid",
  "worker_name": "initial-publisher",
  "data": "encrypted...",
  "iv": "...",
  "tag": "..."
}
```

### Service 1 → Command Center
```json
{
  "request_id": "uuid",
  "worker_name": "service-1-publisher",
  "data": "encrypted...",
  "iv": "...",
  "tag": "..."
}
```

## Running Individual Services

### Publisher Only
```bash
cd E:\perlinsos\splp\example-net\Publisher
dotnet run --framework net8.0
# or
dotnet run --framework net6.0
```

### Service 1 Only
```bash
cd E:\perlinsos\splp\example-net\Service1
dotnet run
```

### Service 2 Only
```bash
cd E:\perlinsos\splp\example-net\Service2
dotnet run
```

### Command Center Only
```bash
cd E:\perlinsos\splp\example-net\CommandCenter
dotnet run
```

## Configuration

All services use the same configuration pattern:

```csharp
var config = new MessagingConfig
{
    Kafka = new KafkaConfig
    {
        Brokers = new[] { "localhost:9092" },
        ClientId = "service-name",
        GroupId = "service-group"
    },
    Cassandra = new CassandraConfig
    {
        ContactPoints = new[] { "localhost" },
        LocalDataCenter = "datacenter1",
        Keyspace = "messaging"
    },
    Encryption = new EncryptionConfig
    {
        EncryptionKey = Environment.GetEnvironmentVariable("ENCRYPTION_KEY")!
    }
};
```

## Metadata Logging

Every routing operation is logged to Cassandra:

```sql
SELECT * FROM messaging.message_logs;
```

### What's Logged (NO PAYLOAD):
- request_id
- timestamp
- type (request/response)
- topic
- success/failure
- processing_time_ms

## Data Retention (TTL)

### Kafka
- Message retention: **1 week** (604800000 ms)
- Automatic cleanup after 7 days

### Cassandra
- Table TTL: **1 week** (604800 seconds)
- Automatic data expiration after 7 days

## Customization

### Add More Services

1. Create new service project
2. Add route in `CommandCenter/Program.cs`:
```csharp
_routingTable = new Dictionary<string, string>
{
    { "initial-publisher", "service-1-topic" },
    { "service-1-publisher", "service-2-topic" },
    { "service-2-publisher", "service-3-topic" }  // New route
};
```

### Modify Processing Logic

Edit the service Program.cs files:
- `Service1/Program.cs` - Dukcapil validation logic
- `Service2/Program.cs` - Kemensos aggregation logic

## Monitoring

### View Kafka Topics
```bash
# If using Kafka UI
start http://localhost:8080
```

### Query Cassandra Logs
```bash
docker exec -it cassandra cqlsh

# View message logs
SELECT * FROM messaging.message_logs LIMIT 10;

# View by request ID
SELECT * FROM messaging.message_logs 
WHERE request_id = 'your-uuid-here';
```

## Troubleshooting

### Service not receiving messages
1. Check Command Center is running
2. Verify routing table in CommandCenter
3. Check worker_name matches route config
4. Ensure ENCRYPTION_KEY is same across all services

### Decryption errors
- All services must use identical ENCRYPTION_KEY
- Re-set the environment variable in all terminals

### Build errors
```bash
# Clean and rebuild
dotnet clean
dotnet restore
dotnet build
```

### Cassandra connection failed
```bash
# Check Cassandra health
docker-compose ps cassandra

# View logs
docker-compose logs cassandra
```

### Kafka connection failed
```bash
# Check Kafka health
docker-compose ps kafka

# View logs
docker-compose logs kafka
```

## Performance

### Concurrent Processing
Each service can handle multiple concurrent messages automatically.

### Scaling
- Run multiple instances of each service with same GroupId
- Kafka will distribute messages across instances
- Each instance processes different partitions

## Security

- **Encryption**: AES-256-GCM for all payloads
- **Metadata Only**: Cassandra logs contain NO sensitive payload data
- **request_id visible**: Needed for distributed tracing
- **Shared Key**: All services must use same ENCRYPTION_KEY

## Compatibility

- **.NET 8.0** - Full feature support, latest performance
- **.NET 6.0** - Full feature support, LTS version
- **Windows, Linux, macOS** - Cross-platform support

## License

MIT
