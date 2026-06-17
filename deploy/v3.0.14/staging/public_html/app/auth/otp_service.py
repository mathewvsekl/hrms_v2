"""
Core OTP authentication business logic.
Handles: OTP generation, storage, expiry, brute-force tracking,
session token issuance, and user-enumeration-safe flows.

Storage backend: Redis (via aioredis). Swap out the Redis calls
with any async KV store (Memcached, DynamoDB, etc.) as needed.
"""

import asyncio
import hashlib
import hmac
import logging
import os
import secrets
import time
from dataclasses import dataclass
from typing import Optional

import redis.asyncio as aioredis

logger = logging.getLogger(__name__)

# ─── Configuration (override via env) ────────────────────────────────────────
OTP_TTL_SECONDS: int = 300          # 5 minutes
MAX_OTP_ATTEMPTS: int = 3           # Invalidate after N wrong guesses
RATE_LIMIT_WINDOW: int = 3600       # 1 hour
RATE_LIMIT_MAX_REQUESTS: int = 3    # Max OTP requests per IP per hour
OTP_DIGITS: int = 6

# Redis key prefixes
_OTP_KEY   = "hrms:otp:{email}"
_RATE_KEY  = "hrms:rate:{ip}"
_SESS_KEY  = "hrms:sess:{token}"

SESSION_TTL_SECONDS: int = 43_200   # 12 hours


# ─── Redis client singleton (replace DSN with your env variable) ─────────────
_redis_client: Optional[aioredis.Redis] = None


async def get_redis() -> aioredis.Redis:
    global _redis_client
    if _redis_client is None:
        _redis_client = aioredis.from_url(
            os.environ.get("REDIS_URL", "redis://localhost:6379/0"),
            decode_responses=True,
        )
    return _redis_client


# ─── Data classes ─────────────────────────────────────────────────────────────
@dataclass
class OTPRecord:
    otp_hash: str          # bcrypt/sha256 hash, never store plaintext
    created_at: float      # unix timestamp
    failed_attempts: int


@dataclass
class AuthResult:
    success: bool
    session_token: Optional[str] = None
    message: str = ""
    code: str = ""         # Machine-readable code for the frontend


# ─── Helpers ─────────────────────────────────────────────────────────────────
def _generate_otp() -> str:
    """Cryptographically secure N-digit numeric OTP."""
    return "".join([str(secrets.randbelow(10)) for _ in range(OTP_DIGITS)])


def _hash_otp(otp: str) -> str:
    """HMAC-SHA256 hash of the OTP using a server-side secret."""
    secret = os.environ.get("OTP_HMAC_SECRET", "change-me-in-production").encode()
    return hmac.new(secret, otp.encode(), hashlib.sha256).hexdigest()


def _otp_redis_key(email: str) -> str:
    return _OTP_KEY.format(email=email.lower().strip())


def _rate_redis_key(ip: str) -> str:
    return _RATE_KEY.format(ip=ip)


# ─── Rate Limiting ────────────────────────────────────────────────────────────
async def is_rate_limited(ip: str) -> bool:
    """
    Sliding window counter: max RATE_LIMIT_MAX_REQUESTS per RATE_LIMIT_WINDOW.
    Returns True when the IP should be blocked.
    """
    redis = await get_redis()
    key = _rate_redis_key(ip)
    count = await redis.incr(key)
    if count == 1:
        # First hit in this window — set expiry
        await redis.expire(key, RATE_LIMIT_WINDOW)
    return count > RATE_LIMIT_MAX_REQUESTS


# ─── OTP Request ─────────────────────────────────────────────────────────────
async def process_otp_request(email: str, user_exists: bool) -> None:
    """
    Called AFTER Turnstile + rate-limit checks pass.

    - If user exists: generates and stores an OTP, then triggers email dispatch.
    - If user does NOT exist: sleeps a random delay to prevent timing attacks.
      The caller receives the SAME response in both cases (enumeration-safe).
    """
    if not user_exists:
        # Mimic the latency of OTP generation + DB write + email queue push
        await asyncio.sleep(secrets.randbelow(200) / 1000 + 0.1)  # 100–300 ms
        return

    otp_plain = _generate_otp()
    otp_hash  = _hash_otp(otp_plain)
    redis     = await get_redis()
    key       = _otp_redis_key(email)

    # Store as a Redis hash: { otp_hash, created_at, failed_attempts }
    await redis.hset(key, mapping={
        "otp_hash":        otp_hash,
        "created_at":      str(time.time()),
        "failed_attempts": "0",
    })
    await redis.expire(key, OTP_TTL_SECONDS)

    # ── Dispatch email ────────────────────────────────────────────────────────
    # Replace this call with your actual email service (Mailgun, SES, etc.)
    await _dispatch_otp_email(email, otp_plain)
    logger.info("OTP dispatched to %s", email)


async def _dispatch_otp_email(email: str, otp: str) -> None:
    """
    Stub — replace with your real email provider integration.
    Example: send via Mailgun or FastAPI BackgroundTasks.
    """
    logger.debug(">>> [STUB] Sending OTP %s to %s", otp, email)
    # await mailgun_client.send(to=email, subject="Your HRMS Access Code", body=f"Code: {otp}")


# ─── OTP Verification ─────────────────────────────────────────────────────────
async def verify_otp(email: str, otp_code: str) -> AuthResult:
    """
    Verifies the OTP, enforces attempt limits, and issues a session token.
    """
    redis = await get_redis()
    key   = _otp_redis_key(email)

    record = await redis.hgetall(key)
    if not record:
        # No active OTP — either never requested, expired, or already invalidated
        return AuthResult(
            success=False,
            message="Invalid or expired code. Please request a new one.",
            code="OTP_NOT_FOUND",
        )

    failed_attempts = int(record.get("failed_attempts", 0))
    created_at      = float(record.get("created_at", 0))
    stored_hash     = record.get("otp_hash", "")

    # Guard: check expiry (Redis TTL is the primary gate, this is a secondary check)
    if time.time() - created_at > OTP_TTL_SECONDS:
        await redis.delete(key)
        return AuthResult(
            success=False,
            message="Your code has expired. Please request a new one.",
            code="OTP_EXPIRED",
        )

    # Guard: max attempts already exceeded
    if failed_attempts >= MAX_OTP_ATTEMPTS:
        await redis.delete(key)
        return AuthResult(
            success=False,
            message="Maximum attempts exceeded. Please request a new code.",
            code="MAX_ATTEMPTS_EXCEEDED",
        )

    # Constant-time comparison to prevent timing oracle attacks
    if not hmac.compare_digest(_hash_otp(otp_code), stored_hash):
        new_attempts = failed_attempts + 1
        if new_attempts >= MAX_OTP_ATTEMPTS:
            await redis.delete(key)
            logger.warning("OTP for %s invalidated after %d failed attempts.", email, new_attempts)
            return AuthResult(
                success=False,
                message="Maximum attempts exceeded. Please request a new code.",
                code="MAX_ATTEMPTS_EXCEEDED",
            )
        await redis.hset(key, "failed_attempts", str(new_attempts))
        return AuthResult(
            success=False,
            message=f"Invalid code. {MAX_OTP_ATTEMPTS - new_attempts} attempt(s) remaining.",
            code="OTP_INVALID",
        )

    # ✓ OTP is correct — invalidate immediately (one-time use)
    await redis.delete(key)

    # Issue session token
    session_token = await _create_session(email)
    logger.info("Successful authentication for %s", email)
    return AuthResult(success=True, session_token=session_token)


async def _create_session(email: str) -> str:
    """
    Creates a cryptographically secure session token in Redis.
    Returns the opaque token string to be sent to the client.
    """
    token = secrets.token_urlsafe(48)
    redis = await get_redis()
    await redis.setex(
        _SESS_KEY.format(token=token),
        SESSION_TTL_SECONDS,
        email.lower().strip(),
    )
    return token
