using Cassandra;
using Microsoft.Extensions.Logging;
using SplpNet.Models;
using System.Text.Json;

namespace SplpNet.Logging;

/// <summary>
/// Cassandra logger for distributed tracing and request/response logging
/// </summary>
public class CassandraLogger : IDisposable
{
    private readonly CassandraConfig _config;
    private readonly ILogger<CassandraLogger>? _logger;
    private ISession? _session;
    private ICluster? _cluster;
    private bool _disposed;

    // Prepared statements for better performance
    private PreparedStatement? _insertLogStatement;
    private PreparedStatement? _selectByRequestIdStatement;
    private PreparedStatement? _selectByTimeRangeStatement;

    public CassandraLogger(CassandraConfig config, ILogger<CassandraLogger>? logger = null)
    {
        _config = config ?? throw new ArgumentNullException(nameof(config));
        _logger = logger;
    }

    /// <summary>
    /// Initializes the Cassandra connection and creates necessary tables
    /// </summary>
    public async Task InitializeAsync()
    {
        if (_session != null)
            return;

        try
        {
            _cluster = Cluster.Builder()
                .AddContactPoints(_config.ContactPoints)
                .Build();

            _session = await _cluster.ConnectAsync();
            _logger?.LogInformation("Connected to Cassandra cluster");

            // Create keyspace if it doesn't exist
            await CreateKeyspaceAsync();

            // Use the keyspace
            await _session.ExecuteAsync(new SimpleStatement($"USE {_config.Keyspace}"));

            // Create tables if they don't exist
            await CreateTablesAsync();

            // Prepare statements
            await PrepareStatementsAsync();

            _logger?.LogInformation("Cassandra logger initialized successfully");
        }
        catch (Exception ex)
        {
            _logger?.LogError(ex, "Failed to initialize Cassandra logger");
            throw;
        }
    }

    /// <summary>
    /// Logs a request or response entry
    /// </summary>
    public async Task LogAsync(LogEntry entry)
    {
        if (_session == null || _insertLogStatement == null)
            throw new InvalidOperationException("CassandraLogger not initialized. Call InitializeAsync first.");

        try
        {
            var payloadJson = JsonSerializer.Serialize(entry.Payload);
            
            var boundStatement = _insertLogStatement.Bind(
                entry.RequestId,
                entry.Timestamp,
                entry.Type,
                entry.Topic,
                payloadJson,
                entry.Success,
                entry.Error,
                entry.DurationMs
            );

            await _session.ExecuteAsync(boundStatement);
            
            _logger?.LogDebug("Logged entry for request {RequestId}", entry.RequestId);
        }
        catch (Exception ex)
        {
            _logger?.LogError(ex, "Failed to log entry for request {RequestId}", entry.RequestId);
            throw;
        }
    }

    /// <summary>
    /// Gets all log entries for a specific request ID
    /// </summary>
    public async Task<List<LogEntry>> GetLogsByRequestIdAsync(string requestId)
    {
        if (_session == null || _selectByRequestIdStatement == null)
            throw new InvalidOperationException("CassandraLogger not initialized. Call InitializeAsync first.");

        try
        {
            var boundStatement = _selectByRequestIdStatement.Bind(requestId);
            var rowSet = await _session.ExecuteAsync(boundStatement);

            var entries = new List<LogEntry>();
            foreach (var row in rowSet)
            {
                entries.Add(MapRowToLogEntry(row));
            }

            return entries.OrderBy(e => e.Timestamp).ToList();
        }
        catch (Exception ex)
        {
            _logger?.LogError(ex, "Failed to get logs for request {RequestId}", requestId);
            throw;
        }
    }

    /// <summary>
    /// Gets log entries within a time range
    /// </summary>
    public async Task<List<LogEntry>> GetLogsByTimeRangeAsync(DateTime startTime, DateTime endTime)
    {
        if (_session == null || _selectByTimeRangeStatement == null)
            throw new InvalidOperationException("CassandraLogger not initialized. Call InitializeAsync first.");

        try
        {
            var boundStatement = _selectByTimeRangeStatement.Bind(startTime, endTime);
            var rowSet = await _session.ExecuteAsync(boundStatement);

            var entries = new List<LogEntry>();
            foreach (var row in rowSet)
            {
                entries.Add(MapRowToLogEntry(row));
            }

            return entries.OrderBy(e => e.Timestamp).ToList();
        }
        catch (Exception ex)
        {
            _logger?.LogError(ex, "Failed to get logs for time range {StartTime} - {EndTime}", startTime, endTime);
            throw;
        }
    }

    private async Task CreateKeyspaceAsync()
    {
        var createKeyspaceCql = $@"
            CREATE KEYSPACE IF NOT EXISTS {_config.Keyspace}
            WITH REPLICATION = {{
                'class': 'SimpleStrategy',
                'replication_factor': 1
            }}";

        await _session!.ExecuteAsync(new SimpleStatement(createKeyspaceCql));
        _logger?.LogInformation("Keyspace {Keyspace} created or already exists", _config.Keyspace);
    }

    private async Task CreateTablesAsync()
    {
        // Create logs table with TTL (1 week = 604800 seconds)
        var createLogTableCql = @"
            CREATE TABLE IF NOT EXISTS message_logs (
                request_id text,
                timestamp timestamp,
                type text,
                topic text,
                payload text,
                success boolean,
                error text,
                duration_ms bigint,
                PRIMARY KEY (request_id, timestamp)
            ) WITH CLUSTERING ORDER BY (timestamp ASC)
            AND default_time_to_live = 604800";

        await _session!.ExecuteAsync(new SimpleStatement(createLogTableCql));

        // Create index on timestamp for time range queries
        var createTimestampIndexCql = @"
            CREATE INDEX IF NOT EXISTS idx_message_logs_timestamp 
            ON message_logs (timestamp)";

        await _session.ExecuteAsync(new SimpleStatement(createTimestampIndexCql));

        _logger?.LogInformation("Cassandra tables created or already exist");
    }

    private async Task PrepareStatementsAsync()
    {
        // Insert statement
        var insertCql = @"
            INSERT INTO message_logs (request_id, timestamp, type, topic, payload, success, error, duration_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        _insertLogStatement = await _session!.PrepareAsync(insertCql);

        // Select by request ID
        var selectByRequestIdCql = @"
            SELECT * FROM message_logs WHERE request_id = ?";
        _selectByRequestIdStatement = await _session.PrepareAsync(selectByRequestIdCql);

        // Select by time range
        var selectByTimeRangeCql = @"
            SELECT * FROM message_logs WHERE timestamp >= ? AND timestamp <= ? ALLOW FILTERING";
        _selectByTimeRangeStatement = await _session.PrepareAsync(selectByTimeRangeCql);

        _logger?.LogDebug("Prepared statements created");
    }

    private static LogEntry MapRowToLogEntry(Row row)
    {
        var payloadJson = row.GetValue<string>("payload");
        var payload = string.IsNullOrEmpty(payloadJson) 
            ? new object() 
            : JsonSerializer.Deserialize<object>(payloadJson) ?? new object();

        return new LogEntry
        {
            RequestId = row.GetValue<string>("request_id"),
            Timestamp = row.GetValue<DateTimeOffset>("timestamp").DateTime,
            Type = row.GetValue<string>("type"),
            Topic = row.GetValue<string>("topic"),
            Payload = payload,
            Success = row.GetValue<bool?>("success"),
            Error = row.GetValue<string?>("error"),
            DurationMs = row.GetValue<long?>("duration_ms")
        };
    }

    public void Dispose()
    {
        if (!_disposed)
        {
            _session?.Dispose();
            _cluster?.Dispose();
            _disposed = true;
        }
        GC.SuppressFinalize(this);
    }
}
