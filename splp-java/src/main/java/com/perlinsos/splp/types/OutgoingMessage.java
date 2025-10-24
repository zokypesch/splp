package com.perlinsos.splp.types;

import com.fasterxml.jackson.annotation.JsonIgnoreProperties;
import com.fasterxml.jackson.annotation.JsonProperty;

/**
 * Outgoing message structure for sending to Command Center
 * This wraps the encrypted data with routing information
 */
@JsonIgnoreProperties(ignoreUnknown = true)
public class OutgoingMessage {
    @JsonProperty("request_id")
    private String requestId;
    
    @JsonProperty("worker_name")
    private String workerName;
    
    private String data;
    private String iv;
    private String tag;

    // Default constructor for Jackson
    public OutgoingMessage() {}

    public OutgoingMessage(String requestId, String workerName, String data, String iv, String tag) {
        this.requestId = requestId;
        this.workerName = workerName;
        this.data = data;
        this.iv = iv;
        this.tag = tag;
    }

    // Getters and setters
    public String getRequestId() {
        return requestId;
    }

    public void setRequestId(String requestId) {
        this.requestId = requestId;
    }

    public String getWorkerName() {
        return workerName;
    }

    public void setWorkerName(String workerName) {
        this.workerName = workerName;
    }

    public String getData() {
        return data;
    }

    public void setData(String data) {
        this.data = data;
    }

    public String getIv() {
        return iv;
    }

    public void setIv(String iv) {
        this.iv = iv;
    }

    public String getTag() {
        return tag;
    }

    public void setTag(String tag) {
        this.tag = tag;
    }

    @Override
    public String toString() {
        return "OutgoingMessage{" +
               "requestId='" + requestId + '\'' +
               ", workerName='" + workerName + '\'' +
               ", data='[ENCRYPTED]'" +
               ", iv='" + iv + '\'' +
               ", tag='" + tag + '\'' +
               '}';
    }
}
