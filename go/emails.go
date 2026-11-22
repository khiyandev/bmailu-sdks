package bmailu

import (
	"context"
	"net/url"
)

// Tag is a name/value pair on an email (letters, digits, _ and -).
type Tag struct {
	Name  string `json:"name"`
	Value string `json:"value"`
}

// Attachment is a file sent with an email. Content is base64.
type Attachment struct {
	Filename    string `json:"filename"`
	Content     string `json:"content"`
	ContentType string `json:"content_type,omitempty"`
	ContentID   string `json:"content_id,omitempty"`
}

// EmailTemplate sends a published template instead of HTML/text.
type EmailTemplate struct {
	ID        string         `json:"id"`
	Variables map[string]any `json:"variables,omitempty"`
}

// SendEmailRequest is the body of POST /emails. To + Cc + Bcc ≤ 50 addresses.
type SendEmailRequest struct {
	From        string            `json:"from,omitempty"`
	To          []string          `json:"to"`
	Cc          []string          `json:"cc,omitempty"`
	Bcc         []string          `json:"bcc,omitempty"`
	ReplyTo     []string          `json:"reply_to,omitempty"`
	Subject     string            `json:"subject,omitempty"`
	HTML        string            `json:"html,omitempty"`
	Text        string            `json:"text,omitempty"`
	Template    *EmailTemplate    `json:"template,omitempty"`
	Headers     map[string]string `json:"headers,omitempty"`
	Tags        []Tag             `json:"tags,omitempty"`
	Attachments []Attachment      `json:"attachments,omitempty"`
	// ScheduledAt is ISO 8601 with a zone, or "in 10 minutes" / "in 2 hours" / "in 1 day". Not in batch sends.
	ScheduledAt string `json:"scheduled_at,omitempty"`
}

// SendEmailResponse is the answer to a send.
type SendEmailResponse struct {
	ID string `json:"id"`
}

// BatchResponse is the answer to a batch send, one id per email in order.
type BatchResponse struct {
	Data []SendEmailResponse `json:"data"`
}

// Email is one sent (or scheduled) email.
type Email struct {
	Object      string   `json:"object"`
	ID          string   `json:"id"`
	From        string   `json:"from"`
	To          []string `json:"to"`
	Cc          []string `json:"cc"`
	Bcc         []string `json:"bcc"`
	ReplyTo     []string `json:"reply_to"`
	Subject     string   `json:"subject"`
	LastEvent   string   `json:"last_event"`
	CreatedAt   *string  `json:"created_at"`
	ScheduledAt *string  `json:"scheduled_at"`
	SentAt      *string  `json:"sent_at"`
	Tags        []Tag    `json:"tags"`
	Source      string   `json:"source"`
	APIKeyID    *int64   `json:"api_key_id"`
	Test        bool     `json:"test"`
	Error       *string  `json:"error"`
	HTML        *string  `json:"html"`
	Text        *string  `json:"text"`
	MessageID   string   `json:"message_id"`
	Recipients  []M      `json:"recipients"`
	Events      []M      `json:"events"`
}

// EmailList is a page of emails, newest first.
type EmailList struct {
	Object      string  `json:"object"`
	HasMore     bool    `json:"has_more"`
	Data        []Email `json:"data"`
	SentLast24h int     `json:"sent_last_24h"`
	DailyLimit  int     `json:"daily_limit"`
}

// EmailsService covers /emails.
type EmailsService struct{ c *Client }

// Send sends one email. Add WithIdempotencyKey to make a retry safe.
func (s *EmailsService) Send(ctx context.Context, req *SendEmailRequest, opts ...RequestOption) (*SendEmailResponse, error) {
	return do[SendEmailResponse](ctx, s.c, "POST", "/emails", req, opts...)
}

// Batch sends up to 100 emails at once (no attachments, no scheduling).
func (s *EmailsService) Batch(ctx context.Context, emails []SendEmailRequest, opts ...RequestOption) (*BatchResponse, error) {
	return do[BatchResponse](ctx, s.c, "POST", "/emails/batch", emails, opts...)
}

// Get returns an email with per-recipient status and events.
func (s *EmailsService) Get(ctx context.Context, id string) (*Email, error) {
	return do[Email](ctx, s.c, "GET", "/emails/"+seg(id), nil)
}

// List returns emails, newest first. query: limit, before, status, source, key, domain, q, days.
func (s *EmailsService) List(ctx context.Context, query url.Values) (*EmailList, error) {
	return do[EmailList](ctx, s.c, "GET", withQuery("/emails", query), nil)
}

// Reschedule moves a scheduled email to a new time.
func (s *EmailsService) Reschedule(ctx context.Context, id, scheduledAt string) (M, error) {
	return deref(do[M](ctx, s.c, "PATCH", "/emails/"+seg(id), M{"scheduled_at": scheduledAt}))
}

// Cancel cancels a scheduled email.
func (s *EmailsService) Cancel(ctx context.Context, id string) (M, error) {
	return deref(do[M](ctx, s.c, "POST", "/emails/"+seg(id)+"/cancel", nil))
}

func deref(m *M, err error) (M, error) {
	if err != nil {
		return nil, err
	}
	return *m, nil
}
