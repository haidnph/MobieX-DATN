# VNPAY / MoMo sandbox setup

Checkout stores pending payments locally. Configure the following environment variables before using online sandbox checkout. Never commit real secrets.

VNPAY: `VNP_TMN_CODE`, `VNP_HASH_SECRET`, `VNP_RETURN_URL`; optional `VNP_PAYMENT_URL` (defaults to the official sandbox endpoint).

MoMo: `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, `MOMO_SECRET_KEY`, `MOMO_RETURN_URL`, `MOMO_IPN_URL`; optional `MOMO_CREATE_URL` (defaults to the official test endpoint).

The return and IPN URLs must be publicly reachable HTTPS URLs for the payment provider. Point them to `index.php?page=payment-return&provider=vnpay` / `index.php?page=payment-ipn&provider=vnpay` and the equivalent `provider=momo` URLs. Configure these exact URLs in the merchant sandbox portal. A local-only Laragon URL cannot receive provider callbacks.

VNPAY sandbox uses its documented payment URL and signed query parameters. MoMo uses its documented test payment endpoint and HMAC signature. Callbacks are signature and amount checked before updating `payments` and `orders`.
