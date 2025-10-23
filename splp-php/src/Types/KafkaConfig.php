<?php

namespace Splp\Messaging\Types;

class KafkaConfig
{
    public array $brokers;
    public string $clientId;
    public string $groupId;
    public int $requestTimeoutMs;
    public string $consumerTopic;
    public string $producerTopic;

    public function __construct(
        array $brokers,
        string $clientId,
        string $groupId,
        int $requestTimeoutMs = 30000,
        string $consumerTopic = '',
        string $producerTopic = ''
    ) {
        $this->brokers = $brokers;
        $this->clientId = $clientId;
        $this->groupId = $groupId;
        $this->requestTimeoutMs = $requestTimeoutMs;
        $this->consumerTopic = $consumerTopic;
        $this->producerTopic = $producerTopic;
    }
}
