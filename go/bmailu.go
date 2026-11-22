// Package bmailu is the official Go SDK for the Bmailu Email API (https://bmailu.com/docs/).
// Standard library only.
//
//	client := bmailu.NewClient("bmk_...")
//	res, err := client.Emails.Send(ctx, &bmailu.SendEmailRequest{From: "Shop <hello@shop.com>", To: []string{"ann@example.com"}, Subject: "Hi", HTML: "<p>Hi</p>"})
package bmailu

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"strings"
	"time"
)

// Version of this SDK.
const Version = "0.1.0"

// DefaultBaseURL is used when no base URL is given and BMAILU_BASE_URL is not set.
const DefaultBaseURL = "https://app.bmailu.com/api/v1"

// M is a JSON object, used for less common request and response bodies.
type M = map[string]any

// Client talks to the Bmailu API. Create it with NewClient.
type Client struct {
	APIKey     string
	BaseURL    string
	HTTPClient *http.Client

	Emails     *EmailsService
	Events     *EventsService
	Contacts   *ContactsService
	Broadcasts *BroadcastsService
	Templates  *TemplatesService
	Webhooks   *WebhooksService
}

// Option changes a Client in NewClient.
type Option func(*Client)

// WithBaseURL sets the API address (default https://app.bmailu.com/api/v1).
func WithBaseURL(u string) Option { return func(c *Client) { c.BaseURL = u } }

// WithHTTPClient sets the HTTP client (default: 30 second timeout).
func WithHTTPClient(h *http.Client) Option { return func(c *Client) { c.HTTPClient = h } }

// NewClient makes a client. An empty apiKey reads BMAILU_API_KEY; the base URL comes from BMAILU_BASE_URL when set.
func NewClient(apiKey string, opts ...Option) *Client {
	if apiKey == "" {
		apiKey = os.Getenv("BMAILU_API_KEY")
	}
	c := &Client{APIKey: apiKey, BaseURL: os.Getenv("BMAILU_BASE_URL"), HTTPClient: &http.Client{Timeout: 30 * time.Second}}
	if c.BaseURL == "" {
		c.BaseURL = DefaultBaseURL
	}
	for _, o := range opts {
		o(c)
	}
	c.BaseURL = strings.TrimRight(c.BaseURL, "/")
	c.Emails = &EmailsService{c}
	c.Events = &EventsService{c}
	c.Contacts = &ContactsService{c}
	c.Broadcasts = &BroadcastsService{c}
	c.Templates = &TemplatesService{c}
	c.Webhooks = &WebhooksService{c}
	return c
}

// Error is an error answer from the API. StatusCode is 0 for network failures.
type Error struct {
	StatusCode int
	Code       string
	Message    string
	Body       []byte
}

func (e *Error) Error() string {
	return fmt.Sprintf("bmailu: %s: %s (HTTP %d)", e.Code, e.Message, e.StatusCode)
}

// RequestOption adds something to one request, like an idempotency key.
type RequestOption func(h http.Header)

// WithIdempotencyKey sends the Idempotency-Key header: the same key returns the same email instead of sending twice.
func WithIdempotencyKey(key string) RequestOption {
	return func(h http.Header) {
		if key != "" {
			h.Set("Idempotency-Key", key)
		}
	}
}

// Do calls any endpoint. path may carry a query string ("/emails?limit=5"). body is encoded as JSON when not nil.
// out receives the answer: a *string or *[]byte gets the raw body (CSV, HTML, files), anything else is JSON-decoded.
// out may be nil.
func (c *Client) Do(ctx context.Context, method, path string, body, out any, opts ...RequestOption) error {
	var rd io.Reader
	if body != nil {
		b, err := json.Marshal(body)
		if err != nil {
			return err
		}
		rd = bytes.NewReader(b)
	}
	if !strings.HasPrefix(path, "/") {
		path = "/" + path
	}
	req, err := http.NewRequestWithContext(ctx, strings.ToUpper(method), c.BaseURL+path, rd)
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+c.APIKey)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "bmailu-go/"+Version)
	for _, o := range opts {
		o(req.Header)
	}
	res, err := c.HTTPClient.Do(req)
	if err != nil {
		return &Error{StatusCode: 0, Code: "connection_error", Message: err.Error()}
	}
	defer res.Body.Close()
	raw, err := io.ReadAll(res.Body)
	if err != nil {
		return &Error{StatusCode: res.StatusCode, Code: "connection_error", Message: err.Error()}
	}
	if res.StatusCode < 200 || res.StatusCode > 299 {
		var e struct {
			Error *struct {
				Code    string `json:"code"`
				Message string `json:"message"`
			} `json:"error"`
		}
		if json.Unmarshal(raw, &e) == nil && e.Error != nil {
			return &Error{StatusCode: res.StatusCode, Code: e.Error.Code, Message: e.Error.Message, Body: raw}
		}
		msg := string(raw)
		if len(msg) > 500 {
			msg = msg[:500]
		}
		return &Error{StatusCode: res.StatusCode, Code: "http_error", Message: msg, Body: raw}
	}
	switch o := out.(type) {
	case nil:
		return nil
	case *string:
		*o = string(raw)
		return nil
	case *[]byte:
		*o = raw
		return nil
	}
	if len(raw) == 0 {
		return nil
	}
	return json.Unmarshal(raw, out)
}

// seg encodes one URL path segment.
func seg(v string) string { return url.PathEscape(v) }

// withQuery adds a query string to path.
func withQuery(path string, q url.Values) string {
	if len(q) == 0 {
		return path
	}
	return path + "?" + q.Encode()
}

// do is Do returning a fresh value of type T.
func do[T any](ctx context.Context, c *Client, method, path string, body any, opts ...RequestOption) (*T, error) {
	out := new(T)
	if err := c.Do(ctx, method, path, body, out, opts...); err != nil {
		return nil, err
	}
	return out, nil
}

// obj sends {} instead of null for an empty body.
func obj(m M) M {
	if m == nil {
		return M{}
	}
	return m
}
