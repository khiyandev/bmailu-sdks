"""Bmailu SDK for Python. https://bmailu.com/docs/"""
from .client import DEFAULT_BASE_URL, VERSION, Bmailu, BmailuError
from .webhooks import WebhookVerificationError, verify_webhook

__version__ = VERSION
__all__ = ["Bmailu", "BmailuError", "verify_webhook", "WebhookVerificationError", "DEFAULT_BASE_URL", "__version__"]
