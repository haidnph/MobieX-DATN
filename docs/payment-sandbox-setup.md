# Payment sandbox setup

Copy `backend/.env.example` to the untracked `backend/.env` and configure merchant credentials there. Never commit real or sandbox merchant credentials.


## Provider settings

- **VNPay:** `VNP_TMN_CODE`, `VNP_HASH_SECRET`, `VNP_URL`, `VNP_RETURN_URL`, `VNP_TRANSACTION_API_URL`
- **MoMo:** `MOMO_PARTNER_CODE`, `MOMO_ACCESS_KEY`, `MOMO_SECRET_KEY`, `MOMO_ENDPOINT`, `MOMO_QUERY_ENDPOINT`, `MOMO_REFUND_ENDPOINT`, `MOMO_REFUND_QUERY_ENDPOINT`, `MOMO_RETURN_URL`, `MOMO_CALLBACK_URL`
- **ZaloPay:** `ZALOPAY_APP_ID`, `ZALOPAY_KEY1`, `ZALOPAY_KEY2`, `ZALOPAY_ENDPOINT`, `ZALOPAY_QUERY_ENDPOINT`, `ZALOPAY_REFUND_ENDPOINT`, `ZALOPAY_REFUND_QUERY_ENDPOINT`, `ZALOPAY_RETURN_URL`, `ZALOPAY_CALLBACK_URL`

The return URLs are customer browser redirects. The provider callbacks are server-to-server endpoints and must be publicly reachable over HTTPS:

- VNPay IPN: `https://<your-api-host>/api/payments/vnpay/ipn` (configure in the VNPay merchant portal)
- MoMo callback: set `MOMO_CALLBACK_URL` to `https://<your-api-host>/api/payments/momo/callback`
- ZaloPay callback: set `ZALOPAY_CALLBACK_URL` to `https://<your-api-host>/api/payments/zalopay/callback`

A localhost-only Laragon URL cannot receive provider callbacks. For local sandbox checkout, expose the API through a trusted HTTPS tunnel and use that URL in the provider configuration.

## Refund operations

Admin users with `orders.manage` can send one full refund request for a captured VNPay, MoMo, or ZaloPay payment from the order detail page. MobieX stores the attempt before calling the provider and queries the provider for the final result. A timeout is recorded as unknown; check its status before taking any other action. Do not resubmit an unknown request. The order is marked refunded only after the provider reports a successful final status.

If a gateway does not support the transaction or its credentials are unavailable, an administrator may refund in the merchant portal and use the manual reconciliation form to record the provider refund reference.
