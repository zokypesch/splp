<?php

namespace Splp\Messaging\Types;

class CassandraConfig
{
    public array $contactPoints;
    public string $localDataCenter;
    public string $keyspace;

    public function __construct(
        array $contactPoints,
        string $localDataCenter = 'datacenter1',
        string $keyspace = 'splp_keyspace'
    ) {
        $this->contactPoints = $contactPoints;
        $this->localDataCenter = $localDataCenter;
        $this->keyspace = $keyspace;
    }
}
