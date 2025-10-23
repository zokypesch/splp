namespace SplpNet.Utils;

/// <summary>
/// Utility for generating and validating request IDs
/// </summary>
public static class RequestIdGenerator
{
    /// <summary>
    /// Generates a new UUID v4 request ID for distributed tracing
    /// </summary>
    /// <returns>UUID v4 string</returns>
    public static string GenerateRequestId()
    {
        return Guid.NewGuid().ToString();
    }

    /// <summary>
    /// Validates if a string is a valid UUID format
    /// </summary>
    /// <param name="requestId">The request ID to validate</param>
    /// <returns>True if valid UUID format, false otherwise</returns>
    public static bool IsValidRequestId(string requestId)
    {
        return Guid.TryParse(requestId, out _);
    }
}
