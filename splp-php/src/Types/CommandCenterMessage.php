<?php

namespace Splp\Messaging\Types;

class CommandCenterMessage
{
    public string $requestId;
    public string $workerName;
    public string $data;
    public string $iv;
    public string $tag;

    public function __construct(
        string $requestId,
        string $workerName,
        string $data,
        string $iv,
        string $tag
    ) {
        $this->requestId = $requestId;
        $this->workerName = $workerName;
        $this->data = $data;
        $this->iv = $iv;
        $this->tag = $tag;
    }

    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'worker_name' => $this->workerName,
            'data' => $this->data,
            'iv' => $this->iv,
            'tag' => $this->tag
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
