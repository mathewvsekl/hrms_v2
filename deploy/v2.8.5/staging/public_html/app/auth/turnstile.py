"""
Cloudflare Turnstile server-side verification utility.
Verifies the client token + remote IP against the Turnstile siteverify API.
All requests are rejected immediately on failure — no fallback.
"""

import httpx
import logging
from typing import Optional

logger = logging.getLogger(__name__)

TURNSTILE_VERIFY_URL = "https://challenges.cloudflare.com/turnstile/v0/siteverify"


async def verify_turnstile(
    secret_key: str,
    token: str,
    remote_ip: Optional[str] = None,
) -> bool:
    """
    Returns True only if Turnstile validates the token successfully.
    Logs failures but never raises — callers decide how to handle False.
    """
    payload = {"secret": secret_key, "response": token}
    if remote_ip:
        payload["remoteip"] = remote_ip

    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.post(TURNSTILE_VERIFY_URL, data=payload)
            resp.raise_for_status()
            result = resp.json()

            if not result.get("success"):
                logger.warning(
                    "Turnstile verification failed. Codes: %s | IP: %s",
                    result.get("error-codes", []),
                    remote_ip,
                )
                return False

            return True

    except httpx.HTTPError as exc:
        logger.error("Turnstile HTTP error: %s", exc)
        return False
    except Exception as exc:
        logger.error("Turnstile unexpected error: %s", exc)
        return False
