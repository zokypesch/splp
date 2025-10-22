<?php

declare(strict_types=1);

namespace Splp\Messaging\Exceptions;

/**
 * Base Messaging Exception
 */
class MessagingException extends \Exception
{
}

/**
 * Timeout Exception
 */
class TimeoutException extends MessagingException
{
}

/**
 * Encryption Exception
 */
class EncryptionException extends MessagingException
{
}

/**
 * Configuration Exception
 */
class ConfigurationException extends MessagingException
{
}

/**
 * Connection Exception
 */
class ConnectionException extends MessagingException
{
}
