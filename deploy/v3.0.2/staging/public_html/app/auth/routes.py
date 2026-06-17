"""
FastAPI auth routes: /api/auth/request-otp and /api/auth/verify-otp.

Security layers in order:
  1. Input validation (Pydantic)
  2. Cloudflare Turnstile verification (request-otp only)
  3. IP-based rate limiting
  4. Business logic (otp_service)
"""

import logging
import os
from typing import Optional

from fastapi import APIRouter, HTTPException, Request, status
from pydantic import BaseModel, EmailStr, field_validator

from .otp_service import (
    AuthResult,
    is_rate_limited,
    process_otp_request,
    verify_otp,
)
from .turnstile import verify_turnstile

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/api/auth", tags=["Authentication"])

TURNSTILE_SECRET = os.environ.get("CLOUDFLARE_TURNSTILE_SECRET_KEY", "")

# ─── Guard: ensure secrets are configured in production ──────────────────────
if not TURNSTILE_SECRET:
    logger.warning(
        "CLOUDFLARE_TURNSTILE_SECRET_KEY is not set. "
        "Turnstile verification will reject all requests."
    )


# ─── Request / Response schemas ───────────────────────────────────────────────
class OTPRequestSchema(BaseModel):
    email: EmailStr
    turnstile_token: str

    @field_validator("turnstile_token")
    @classmethod
    def token_not_empty(cls, v: str) -> str:
        if not v or not v.strip():
            raise ValueError("Turnstile token is required.")
        return v.strip()


class OTPVerifySchema(BaseModel):
    email: EmailStr
    otp_code: str

    @field_validator("otp_code")
    @classmethod
    def otp_must_be_numeric_6(cls, v: str) -> str:
        v = v.strip()
        if not v.isdigit() or len(v) != 6:
            raise ValueError("OTP must be exactly 6 digits.")
        return v


class StandardResponse(BaseModel):
    status: str
    message: str


class AuthSuccessResponse(BaseModel):
    status: str
    message: str
    session_token: Optional[str] = None


# ─── Helper: extract real client IP (Cloudflare passes CF-Connecting-IP) ─────
def get_client_ip(request: Request) -> str:
    cf_ip = request.headers.get("CF-Connecting-IP")
    if cf_ip:
        return cf_ip
    x_forwarded = request.headers.get("X-Forwarded-For")
    if x_forwarded:
        return x_forwarded.split(",")[0].strip()
    return request.client.host if request.client else "unknown"


# ─── Helper: look up a user email in your DB ─────────────────────────────────
# Replace this stub with your actual database query.
async def db_user_exists(email: str) -> bool:
    """
    Example stub. Replace with:
        result = await db.fetch_one("SELECT id FROM users WHERE email = :email", {"email": email})
        return result is not None
    """
    raise NotImplementedError("Implement db_user_exists() with your DB layer.")


# ─── POST /api/auth/request-otp ───────────────────────────────────────────────
@router.post(
    "/request-otp",
    response_model=StandardResponse,
    summary="Request an OTP via email",
    description=(
        "Always returns HTTP 200 regardless of whether the email exists, "
        "to prevent user enumeration attacks."
    ),
)
async def request_otp(payload: OTPRequestSchema, request: Request) -> StandardResponse:
    client_ip = get_client_ip(request)

    # ── 1. Verify Turnstile token ────────────────────────────────────────────
    is_valid = await verify_turnstile(
        secret_key=TURNSTILE_SECRET,
        token=payload.turnstile_token,
        remote_ip=client_ip,
    )
    if not is_valid:
        # Return a descriptive 400 — this is a bot/automation failure, not enum.
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={"status": "error", "message": "Security verification failed. Please refresh and try again."},
        )

    # ── 2. IP Rate Limiting ──────────────────────────────────────────────────
    if await is_rate_limited(client_ip):
        raise HTTPException(
            status_code=status.HTTP_429_TOO_MANY_REQUESTS,
            detail={"status": "error", "message": "Too many requests. Please try again later."},
        )

    # ── 3. Check if user exists (result kept server-side only) ───────────────
    try:
        user_exists = await db_user_exists(payload.email)
    except NotImplementedError:
        logger.error("db_user_exists() not implemented — blocking request.")
        raise HTTPException(status_code=500, detail="Server configuration error.")
    except Exception as exc:
        logger.error("DB error in request_otp: %s", exc)
        raise HTTPException(status_code=500, detail="Internal server error.")

    # ── 4. Process OTP (same response regardless of user_exists) ─────────────
    await process_otp_request(email=payload.email, user_exists=user_exists)

    # ── 5. Always identical response — prevents enumeration ──────────────────
    return StandardResponse(
        status="success",
        message="Verification process initiated. If this email is registered, a code has been sent.",
    )


# ─── POST /api/auth/verify-otp ────────────────────────────────────────────────
@router.post(
    "/verify-otp",
    response_model=AuthSuccessResponse,
    summary="Verify the OTP and receive a session token",
)
async def verify_otp_route(payload: OTPVerifySchema, request: Request) -> AuthSuccessResponse:
    client_ip = get_client_ip(request)
    logger.info("OTP verification attempt from %s for email %s", client_ip, payload.email)

    result: AuthResult = await verify_otp(email=payload.email, otp_code=payload.otp_code)

    if not result.success:
        # Use 401 for auth failures; code lets the frontend branch on reason
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail={
                "status": "error",
                "message": result.message,
                "code": result.code,
            },
        )

    return AuthSuccessResponse(
        status="success",
        message="Identity verified successfully.",
        session_token=result.session_token,
    )
