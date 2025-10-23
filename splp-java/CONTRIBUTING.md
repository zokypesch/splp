# Contributing to SPLP Java

Thank you for your interest in contributing to the SPLP Java library! This document provides guidelines and information for contributors.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Contributing Process](#contributing-process)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Documentation](#documentation)
- [Pull Request Process](#pull-request-process)
- [Issue Reporting](#issue-reporting)
- [Security Issues](#security-issues)

## Code of Conduct

This project adheres to a code of conduct that we expect all contributors to follow. Please be respectful and professional in all interactions.

### Our Standards

- Use welcoming and inclusive language
- Be respectful of differing viewpoints and experiences
- Gracefully accept constructive criticism
- Focus on what is best for the community
- Show empathy towards other community members

## Getting Started

### Prerequisites

Before contributing, ensure you have:

- Java 11 or higher installed
- Maven 3.6 or higher installed
- Docker Desktop for running integration tests
- Git for version control
- A GitHub account

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/your-username/splp-java.git
   cd splp-java
   ```
3. Add the upstream repository:
   ```bash
   git remote add upstream https://github.com/perlinsos/splp-java.git
   ```

## Development Setup

### Environment Setup

1. **Install Dependencies**
   ```bash
   # Build the project
   scripts\build.bat clean package
   
   # Start infrastructure
   scripts\start-infra.bat
   
   # Run tests
   scripts\test.bat
   ```

2. **IDE Configuration**
   - Import the project as a Maven project
   - Configure code formatting (see [Coding Standards](#coding-standards))
   - Install recommended plugins:
     - SonarLint for code quality
     - SpotBugs for bug detection
     - Checkstyle for code style

3. **Environment Variables**
   ```bash
   # Required for integration tests
   KAFKA_BOOTSTRAP_SERVERS=localhost:9092
   CASSANDRA_CONTACT_POINTS=localhost
   CASSANDRA_PORT=9042
   CASSANDRA_KEYSPACE=splp_test
   ENCRYPTION_KEY=your-32-character-encryption-key
   ```

### Project Structure

```
splp-java/
├── src/
│   ├── main/java/com/perlinsos/splp/
│   │   ├── messaging/          # Core messaging components
│   │   ├── encryption/         # Encryption services
│   │   ├── logging/           # Cassandra logging
│   │   ├── utils/             # Utility classes
│   │   └── examples/          # Example applications
│   ├── test/java/             # Test classes
│   └── main/resources/        # Configuration files
├── scripts/                   # Build and utility scripts
├── docs/                      # Documentation
└── docker-compose*.yml       # Infrastructure setup
```

## Contributing Process

### 1. Choose an Issue

- Look for issues labeled `good first issue` for beginners
- Check `help wanted` for areas needing assistance
- Create a new issue if you find a bug or want to propose a feature

### 2. Create a Branch

```bash
# Update your fork
git checkout main
git pull upstream main

# Create a feature branch
git checkout -b feature/your-feature-name
# or for bug fixes
git checkout -b fix/issue-number-description
```

### 3. Make Changes

- Follow the [Coding Standards](#coding-standards)
- Write tests for new functionality
- Update documentation as needed
- Ensure all tests pass

### 4. Commit Changes

```bash
# Stage your changes
git add .

# Commit with a descriptive message
git commit -m "feat: add new messaging feature

- Implement request batching functionality
- Add unit tests for batch processing
- Update documentation with usage examples

Closes #123"
```

#### Commit Message Format

Use conventional commits format:

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(messaging): add request batching support
fix(encryption): resolve key validation issue
docs(readme): update installation instructions
test(integration): add Kafka failover tests
```

## Coding Standards

### Java Code Style

We follow Google Java Style Guide with some modifications:

1. **Indentation**: 4 spaces (not tabs)
2. **Line Length**: 120 characters maximum
3. **Imports**: 
   - No wildcard imports
   - Group imports: java.*, javax.*, org.*, com.*
   - Static imports at the end

4. **Naming Conventions**:
   - Classes: `PascalCase`
   - Methods/Variables: `camelCase`
   - Constants: `UPPER_SNAKE_CASE`
   - Packages: `lowercase`

5. **Documentation**:
   - All public classes and methods must have Javadoc
   - Include `@param`, `@return`, `@throws` as appropriate
   - Use `{@code}` for code references

### Code Quality

- **Null Safety**: Use `Optional` for nullable returns
- **Exception Handling**: Use specific exception types
- **Resource Management**: Use try-with-resources
- **Immutability**: Prefer immutable objects where possible
- **Thread Safety**: Document thread safety guarantees

### Example Code Style

```java
/**
 * Handles secure messaging between services using Kafka transport.
 * 
 * <p>This class provides request-reply messaging patterns with automatic
 * encryption, logging, and fault tolerance capabilities.
 * 
 * @author Your Name
 * @since 1.0.0
 */
public class MessagingClient implements AutoCloseable {
    
    private static final Logger logger = LoggerFactory.getLogger(MessagingClient.class);
    private static final int DEFAULT_TIMEOUT_MS = 30000;
    
    private final KafkaProducer<String, String> producer;
    private final EncryptionService encryptionService;
    
    /**
     * Creates a new messaging client with the specified configuration.
     * 
     * @param config the client configuration, must not be null
     * @throws IllegalArgumentException if config is invalid
     * @throws MessagingException if initialization fails
     */
    public MessagingClient(MessagingConfig config) {
        this.producer = Objects.requireNonNull(config, "config must not be null")
            .createProducer();
        this.encryptionService = new EncryptionService(config.getEncryptionKey());
    }
    
    /**
     * Sends a request and waits for a reply.
     * 
     * @param <T> the response type
     * @param topic the target topic
     * @param payload the request payload
     * @param responseType the expected response type
     * @return the response, never null
     * @throws MessagingException if the request fails
     */
    public <T> CompletableFuture<T> request(String topic, Object payload, 
            Class<T> responseType) {
        // Implementation...
    }
}
```

## Testing Guidelines

### Test Structure

1. **Unit Tests** (`*Test.java`)
   - Test individual components in isolation
   - Use mocks for external dependencies
   - Fast execution (< 1 second per test)
   - No external infrastructure required

2. **Integration Tests** (`*IntegrationTest.java`)
   - Test end-to-end functionality
   - Use Testcontainers for infrastructure
   - Test real Kafka and Cassandra interactions
   - Slower execution acceptable

### Test Naming

```java
// Pattern: should_ExpectedBehavior_When_StateUnderTest
@Test
void should_ReturnEncryptedMessage_When_ValidPayloadProvided() {
    // Test implementation
}

@Test
void should_ThrowException_When_InvalidKeyProvided() {
    // Test implementation
}
```

### Test Organization

```java
@DisplayName("MessagingClient Tests")
class MessagingClientTest {
    
    @Nested
    @DisplayName("Request Processing")
    class RequestProcessing {
        
        @Test
        @DisplayName("Should encrypt and send request successfully")
        void should_EncryptAndSendRequest_When_ValidInputProvided() {
            // Test implementation
        }
    }
    
    @Nested
    @DisplayName("Error Handling")
    class ErrorHandling {
        
        @Test
        @DisplayName("Should handle connection failures gracefully")
        void should_HandleConnectionFailure_When_KafkaUnavailable() {
            // Test implementation
        }
    }
}
```

### Running Tests

```bash
# Run all tests
scripts\test.bat

# Run unit tests only
scripts\test.bat unit

# Run integration tests only
scripts\test.bat integration

# Run with coverage
scripts\test.bat --coverage

# Run specific test class
mvn test -Dtest=MessagingClientTest
```

## Documentation

### Code Documentation

- **Javadoc**: All public APIs must have comprehensive Javadoc
- **README Updates**: Update README.md for new features
- **Examples**: Provide usage examples for new functionality
- **Architecture Docs**: Update design documents for significant changes

### Documentation Standards

```java
/**
 * Brief description of the class or method.
 * 
 * <p>Longer description with more details, usage examples,
 * and important notes about behavior.
 * 
 * <p>Example usage:
 * <pre>{@code
 * MessagingClient client = new MessagingClient(config);
 * CompletableFuture<Response> future = client.request("topic", payload, Response.class);
 * Response response = future.get();
 * }</pre>
 * 
 * @param <T> the type parameter description
 * @param paramName description of the parameter
 * @return description of the return value
 * @throws ExceptionType when this exception is thrown
 * @since 1.0.0
 * @see RelatedClass
 */
```

## Pull Request Process

### Before Submitting

1. **Code Quality Checks**
   ```bash
   # Run all tests
   scripts\test.bat --coverage
   
   # Check code style
   mvn checkstyle:check
   
   # Run static analysis
   mvn spotbugs:check
   ```

2. **Documentation Updates**
   - Update README.md if needed
   - Add/update Javadoc comments
   - Update CHANGELOG.md

3. **Self Review**
   - Review your own changes
   - Ensure commits are clean and logical
   - Verify all tests pass

### Pull Request Template

When creating a pull request, include:

```markdown
## Description
Brief description of changes and motivation.

## Type of Change
- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Testing
- [ ] Unit tests added/updated
- [ ] Integration tests added/updated
- [ ] All tests pass locally
- [ ] Manual testing completed

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] No breaking changes (or clearly documented)

## Related Issues
Closes #123
Related to #456
```

### Review Process

1. **Automated Checks**: CI/CD pipeline runs tests and quality checks
2. **Code Review**: At least one maintainer reviews the code
3. **Feedback**: Address any review comments
4. **Approval**: Maintainer approves the changes
5. **Merge**: Changes are merged to main branch

## Issue Reporting

### Bug Reports

Use the bug report template:

```markdown
**Describe the Bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Initialize client with '...'
2. Send request to '...'
3. See error

**Expected Behavior**
What you expected to happen.

**Actual Behavior**
What actually happened.

**Environment**
- OS: [e.g., Windows 10]
- Java Version: [e.g., 11.0.2]
- SPLP Version: [e.g., 1.0.0]
- Kafka Version: [e.g., 2.8.0]
- Cassandra Version: [e.g., 4.0.0]

**Additional Context**
Any other context about the problem.

**Logs**
```
Paste relevant log output here
```
```

### Feature Requests

Use the feature request template:

```markdown
**Is your feature request related to a problem?**
A clear description of what the problem is.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Alternative solutions or features you've considered.

**Additional context**
Any other context or screenshots about the feature request.
```

## Security Issues

**DO NOT** create public issues for security vulnerabilities.

Instead:
1. Email security concerns to: security@perlinsos.com
2. Include detailed information about the vulnerability
3. Allow time for the issue to be addressed before public disclosure

## Recognition

Contributors will be recognized in:
- CHANGELOG.md for significant contributions
- README.md contributors section
- Release notes for major features

## Questions?

If you have questions about contributing:
- Create a discussion on GitHub
- Join our community chat
- Email the maintainers

Thank you for contributing to SPLP Java! 🎉