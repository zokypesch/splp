<?php

namespace Splp\Messaging\Contracts;

interface KafkaClientInterface
{
    public function initialize(): void;
    public function setMessageHandler(MessageProcessorInterface $handler): void;
    public function startConsuming(array $topics): void;
    public function sendMessage(string $topic, string $message): void;
    public function close(): void;
    public function getHealthStatus(): array;
}
