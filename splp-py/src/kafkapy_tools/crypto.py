"""Encryption utilities for KafkaPy Tools."""

import json
from typing import Any, Dict
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC
from cryptography.hazmat.backends import default_backend
import secrets


def generate_encryption_key() -> str:
    """Generate a random 32-byte encryption key."""
    return secrets.token_hex(32)


def encrypt_payload(payload: Any, encryption_key: str, request_id: str) -> Dict[str, str]:
    """
    Encrypt payload using AES-GCM.
    
    Args:
        payload: Data to encrypt
        encryption_key: 32-byte hex string
        request_id: Request ID for additional security
    
    Returns:
        Dictionary with encrypted data, IV, and tag
    """
    try:
        # Convert hex key to bytes
        key_bytes = bytes.fromhex(encryption_key)
        
        # Generate random IV
        iv = secrets.token_bytes(12)  # 96-bit IV for GCM
        
        # Serialize payload to JSON
        payload_json = json.dumps(payload, ensure_ascii=False)
        payload_bytes = payload_json.encode('utf-8')
        
        # Create cipher
        cipher = Cipher(algorithms.AES(key_bytes), modes.GCM(iv), backend=default_backend())
        encryptor = cipher.encryptor()
        
        # Encrypt data
        ciphertext = encryptor.update(payload_bytes) + encryptor.finalize()
        
        # Get authentication tag
        tag = encryptor.tag
        
        return {
            "request_id": request_id,
            "data": ciphertext.hex(),
            "iv": iv.hex(),
            "tag": tag.hex(),
        }
        
    except Exception as e:
        raise ValueError(f"Encryption failed: {e}")


def decrypt_payload(encrypted_message: Dict[str, str], encryption_key: str) -> Dict[str, Any]:
    """
    Decrypt payload using AES-GCM.
    
    Args:
        encrypted_message: Dictionary with encrypted data, IV, and tag
        encryption_key: 32-byte hex string
    
    Returns:
        Dictionary with decrypted payload
    """
    try:
        # Convert hex key to bytes
        key_bytes = bytes.fromhex(encryption_key)
        
        # Decode hex data (compatible with splp-bun)
        ciphertext = bytes.fromhex(encrypted_message["data"])
        iv = bytes.fromhex(encrypted_message["iv"])
        tag = bytes.fromhex(encrypted_message["tag"])
        
        # Create cipher
        cipher = Cipher(algorithms.AES(key_bytes), modes.GCM(iv, tag), backend=default_backend())
        decryptor = cipher.decryptor()
        
        # Decrypt data
        decrypted_bytes = decryptor.update(ciphertext) + decryptor.finalize()
        
        # Deserialize JSON
        payload_json = decrypted_bytes.decode('utf-8')
        payload = json.loads(payload_json)
        
        return {
            "request_id": encrypted_message["request_id"],
            "payload": payload,
        }
        
    except Exception as e:
        raise ValueError(f"Decryption failed: {e}")


def verify_encryption_key(key: str) -> bool:
    """Verify if encryption key is valid."""
    try:
        if len(key) != 64:  # 32 bytes = 64 hex chars
            return False
        bytes.fromhex(key)
        return True
    except ValueError:
        return False
