# Creating SplpNet NuGet Package

## 🚀 Quick Create Package

### Option 1: Using PowerShell Script (Recommended)
```powershell
.\create-nuget-package.ps1
```

### Option 2: Using Batch Script (Windows)
```cmd
.\create-nuget-package.bat
```

### Option 3: Manual Commands
```bash
# Create output directory
mkdir nupkgs

# Build and pack
cd splp-net
dotnet clean --configuration Release
dotnet restore
dotnet build --configuration Release --no-restore
dotnet pack --configuration Release --no-build --output ../nupkgs
```

## 📦 Package Details

- **Package ID**: `SplpNet`
- **Version**: `1.0.0`
- **Target Frameworks**: 
  - .NET 8.0
  - .NET 6.0  
  - .NET Standard 2.1
- **Output Location**: `E:\perlinsos\splp\nupkgs\SplpNet.1.0.0.nupkg`

## 🔧 Package Contents

The NuGet package includes:
- ✅ **Multi-target assemblies** for all supported frameworks
- ✅ **Dependencies** automatically resolved per framework
- ✅ **README.md** with usage instructions
- ✅ **XML documentation** for IntelliSense
- ✅ **Package metadata** (description, tags, license)

## 📋 Dependencies Included

### Common Dependencies
- `Confluent.Kafka` 2.3.0
- `CassandraCSharpDriver` 3.20.1

### Framework-Specific Dependencies
**.NET 8.0 / .NET 6.0:**
- `System.Text.Json` 8.0.0
- `Microsoft.Extensions.Logging.Abstractions` 8.0.0
- `Microsoft.Extensions.Options` 8.0.0
- `Microsoft.Extensions.DependencyInjection.Abstractions` 8.0.0

**.NET Standard 2.1:**
- `System.Text.Json` 6.0.0
- `Microsoft.Extensions.Logging.Abstractions` 6.0.0
- `Microsoft.Extensions.Options` 6.0.0
- `Microsoft.Extensions.DependencyInjection.Abstractions` 6.0.0

## 🌐 Using the Package

### Install from Local Source
```bash
# Add local package source
dotnet nuget add source E:\perlinsos\splp\nupkgs --name "Local SPLP Packages"

# Install the package
dotnet add package SplpNet --source "Local SPLP Packages"
```

### Install in Project
```bash
# In your project directory
dotnet add package SplpNet --source E:\perlinsos\splp\nupkgs
```

### Usage Example
```csharp
using SplpNet;

var config = new MessagingConfig
{
    Kafka = new KafkaConfig
    {
        Brokers = new[] { "localhost:9092" },
        ClientId = "my-service"
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

using var client = new MessagingClient(config);
await client.InitializeAsync();
```

## 🚀 Publishing to NuGet.org

### Prerequisites
1. Create account at [nuget.org](https://www.nuget.org)
2. Generate API key from your account settings
3. Configure API key locally:
   ```bash
   dotnet nuget setapikey YOUR_API_KEY --source https://api.nuget.org/v3/index.json
   ```

### Publish Command
```bash
dotnet nuget push nupkgs\SplpNet.1.0.0.nupkg --source https://api.nuget.org/v3/index.json
```

### Verify Publication
- Check package at: `https://www.nuget.org/packages/SplpNet/`
- Install from NuGet: `dotnet add package SplpNet`

## 🔄 Version Management

### Update Version
Edit `splp-net/SplpNet.csproj`:
```xml
<Version>1.0.1</Version>
<PackageReleaseNotes>Bug fixes and improvements</PackageReleaseNotes>
```

### Semantic Versioning
- **Major** (1.0.0 → 2.0.0): Breaking changes
- **Minor** (1.0.0 → 1.1.0): New features, backward compatible
- **Patch** (1.0.0 → 1.0.1): Bug fixes, backward compatible

## 🛠️ Troubleshooting

### Build Errors
```bash
# Clean and restore
dotnet clean
dotnet restore
dotnet build --verbosity detailed
```

### Package Validation
```bash
# Inspect package contents
dotnet tool install -g dotnet-validate
dotnet validate package nupkgs\SplpNet.1.0.0.nupkg
```

### Dependency Issues
- Check target framework compatibility
- Verify package references in `.csproj`
- Use `dotnet list package` to inspect dependencies

## 📁 File Structure

```
E:\perlinsos\splp\
├── nupkgs/                          # Package output directory
│   └── SplpNet.1.0.0.nupkg         # Generated package
├── splp-net/                        # Source project
│   ├── SplpNet.csproj              # Project file with package metadata
│   ├── PACKAGE_README.md           # Package documentation
│   └── [source files...]
├── create-nuget-package.ps1        # PowerShell creation script
├── create-nuget-package.bat        # Batch creation script
└── nuget.config                    # NuGet configuration
```

## ✅ Verification Checklist

- [ ] Package builds successfully for all target frameworks
- [ ] All dependencies are correctly specified
- [ ] README.md is included in package
- [ ] Package metadata is complete
- [ ] Version number is appropriate
- [ ] Package installs correctly in test project
- [ ] All public APIs have XML documentation
- [ ] License is specified (MIT)

The package is now ready for distribution! 🎉
