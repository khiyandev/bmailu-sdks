package bmailu

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"errors"
	"net/http"
	"strconv"
	"strings"
	"time"
)

// ErrWebhookVerification is returned (wrapped) when a webhook signature or timestamp is not valid.
var ErrWebhookVerification = errors.New("bmailu: webhook verification failed")

func verifyErr(msg string) error { return errors.Join(ErrWebhookVerification, errors.New(msg)) }

// WebhookTolerance is how far the webhook-timestamp may be from now.
const WebhookTolerance = 5 * time.Minute

// VerifyWebhook checks a webhook (Standard Webhooks / Svix scheme). payload is the raw body exactly as received,
// header the request headers, secret the endpoint's signing secret (whsec_...). nil means the webhook is genuine.
func VerifyWebhook(payload []byte, header http.Header, secret string) error {
	return VerifyWebhookAt(payload, header, secret, time.Now())
}

// VerifyWebhookAt is VerifyWebhook with a given current time.
func VerifyWebhookAt(payload []byte, header http.Header, secret string, now time.Time) error {
	id, ts, sigs := header.Get("webhook-id"), header.Get("webhook-timestamp"), header.Get("webhook-signature")
	if id == "" || ts == "" || sigs == "" {
		return verifyErr("missing webhook-id, webhook-timestamp or webhook-signature header")
	}
	sec, err := strconv.ParseInt(ts, 10, 64)
	if err != nil {
		return verifyErr("bad webhook-timestamp")
	}
	if d := now.Sub(time.Unix(sec, 0)); d > WebhookTolerance || d < -WebhookTolerance {
		return verifyErr("timestamp is more than 5 minutes away")
	}
	key, err := base64.StdEncoding.DecodeString(strings.TrimPrefix(secret, "whsec_"))
	if err != nil {
		return verifyErr("bad secret")
	}
	mac := hmac.New(sha256.New, key)
	mac.Write([]byte(id + "." + ts + "."))
	mac.Write(payload)
	expected := []byte(base64.StdEncoding.EncodeToString(mac.Sum(nil)))
	for _, part := range strings.Split(sigs, " ") {
		version, sig, ok := strings.Cut(part, ",")
		if ok && version == "v1" && hmac.Equal([]byte(sig), expected) {
			return nil
		}
	}
	return verifyErr("no matching signature")
}
