package bmailu

import (
	"context"
	"net/url"
	"strconv"
)

// call runs a request that answers with a JSON object.
func (c *Client) call(ctx context.Context, method, path string, body any) (M, error) {
	return deref(do[M](ctx, c, method, path, body))
}

// EventsService covers /events.
type EventsService struct{ c *Client }

// Send sends a custom event for a contact. params: event (required), email or contact_id, first_name, data.
func (s *EventsService) Send(ctx context.Context, params M) (M, error) {
	return s.c.call(ctx, "POST", "/events", obj(params))
}

// List returns the latest 100 events. query: contact.
func (s *EventsService) List(ctx context.Context, query url.Values) (M, error) {
	return s.c.call(ctx, "GET", withQuery("/events", query), nil)
}

// ContactsService covers /contacts. A contact is named by id or email.
type ContactsService struct{ c *Client }

// List returns contacts. query: segment_id, status, q, limit, position.
func (s *ContactsService) List(ctx context.Context, query url.Values) (M, error) {
	return s.c.call(ctx, "GET", withQuery("/contacts", query), nil)
}

// Create adds or updates a contact (by email).
func (s *ContactsService) Create(ctx context.Context, params M) (M, error) {
	return s.c.call(ctx, "POST", "/contacts", obj(params))
}

// Get returns a contact.
func (s *ContactsService) Get(ctx context.Context, contact string) (M, error) {
	return s.c.call(ctx, "GET", "/contacts/"+seg(contact), nil)
}

// Update changes a contact, (un)subscribes it or sets topics.
func (s *ContactsService) Update(ctx context.Context, contact string, params M) (M, error) {
	return s.c.call(ctx, "PATCH", "/contacts/"+seg(contact), obj(params))
}

// Delete deletes a contact.
func (s *ContactsService) Delete(ctx context.Context, contact string) (M, error) {
	return s.c.call(ctx, "DELETE", "/contacts/"+seg(contact), nil)
}

// BroadcastsService covers /broadcasts.
type BroadcastsService struct{ c *Client }

// List returns broadcasts.
func (s *BroadcastsService) List(ctx context.Context) (M, error) {
	return s.c.call(ctx, "GET", "/broadcasts", nil)
}

// Create creates a draft broadcast.
func (s *BroadcastsService) Create(ctx context.Context, params M) (M, error) {
	return s.c.call(ctx, "POST", "/broadcasts", obj(params))
}

// Get returns a broadcast with its audience size.
func (s *BroadcastsService) Get(ctx context.Context, id string) (M, error) {
	return s.c.call(ctx, "GET", "/broadcasts/"+seg(id), nil)
}

// Update changes a draft or rejected broadcast.
func (s *BroadcastsService) Update(ctx context.Context, id string, params M) (M, error) {
	return s.c.call(ctx, "PATCH", "/broadcasts/"+seg(id), obj(params))
}

// Delete deletes a draft, rejected or cancelled broadcast.
func (s *BroadcastsService) Delete(ctx context.Context, id string) (M, error) {
	return s.c.call(ctx, "DELETE", "/broadcasts/"+seg(id), nil)
}

// Send sends or schedules a broadcast. scheduledAt may be empty (now).
func (s *BroadcastsService) Send(ctx context.Context, id, scheduledAt string) (M, error) {
	body := M{}
	if scheduledAt != "" {
		body["scheduled_at"] = scheduledAt
	}
	return s.c.call(ctx, "POST", "/broadcasts/"+seg(id)+"/send", body)
}

// Cancel cancels a broadcast waiting for approval or scheduled (back to draft).
func (s *BroadcastsService) Cancel(ctx context.Context, id string) (M, error) {
	return s.c.call(ctx, "POST", "/broadcasts/"+seg(id)+"/cancel", nil)
}

// Test sends one test copy (no tracking).
func (s *BroadcastsService) Test(ctx context.Context, id, to string) (M, error) {
	return s.c.call(ctx, "POST", "/broadcasts/"+seg(id)+"/test", M{"to": to})
}

// Stats returns delivery, open, click and unsubscribe counts.
func (s *BroadcastsService) Stats(ctx context.Context, id string) (M, error) {
	return s.c.call(ctx, "GET", "/broadcasts/"+seg(id)+"/stats", nil)
}

// TemplatesService covers /templates. A template is named by id or alias.
type TemplatesService struct{ c *Client }

// List returns templates.
func (s *TemplatesService) List(ctx context.Context) (M, error) {
	return s.c.call(ctx, "GET", "/templates", nil)
}

// Create creates a template. params: name (required), alias, subject, from, reply_to, html, text, variables, publish.
func (s *TemplatesService) Create(ctx context.Context, params M) (M, error) {
	return s.c.call(ctx, "POST", "/templates", obj(params))
}

// Get returns a template.
func (s *TemplatesService) Get(ctx context.Context, template string) (M, error) {
	return s.c.call(ctx, "GET", "/templates/"+seg(template), nil)
}

// Update changes the draft.
func (s *TemplatesService) Update(ctx context.Context, template string, params M) (M, error) {
	return s.c.call(ctx, "PATCH", "/templates/"+seg(template), obj(params))
}

// Delete deletes a template and its versions.
func (s *TemplatesService) Delete(ctx context.Context, template string) (M, error) {
	return s.c.call(ctx, "DELETE", "/templates/"+seg(template), nil)
}

// Publish publishes the draft as a new version.
func (s *TemplatesService) Publish(ctx context.Context, template string) (M, error) {
	return s.c.call(ctx, "POST", "/templates/"+seg(template)+"/publish", nil)
}

// Duplicate copies a template (without the alias).
func (s *TemplatesService) Duplicate(ctx context.Context, template string) (M, error) {
	return s.c.call(ctx, "POST", "/templates/"+seg(template)+"/duplicate", nil)
}

// Versions lists the published versions.
func (s *TemplatesService) Versions(ctx context.Context, template string) (M, error) {
	return s.c.call(ctx, "GET", "/templates/"+seg(template)+"/versions", nil)
}

// PublishVersion makes an earlier version the published one.
func (s *TemplatesService) PublishVersion(ctx context.Context, template string, version int) (M, error) {
	return s.c.call(ctx, "POST", "/templates/"+seg(template)+"/versions/"+strconv.Itoa(version)+"/publish", nil)
}

// Render fills the draft (or a version) with values. params: variables, version (both optional).
func (s *TemplatesService) Render(ctx context.Context, template string, params M) (M, error) {
	return s.c.call(ctx, "POST", "/templates/"+seg(template)+"/render", obj(params))
}

// Preview returns the HTML of the draft with fallback values.
func (s *TemplatesService) Preview(ctx context.Context, template string) (string, error) {
	var html string
	err := s.c.Do(ctx, "GET", "/templates/"+seg(template)+"/preview.html", nil, &html)
	return html, err
}

// WebhooksService covers /webhooks.
type WebhooksService struct{ c *Client }

func idPath(id int64) string { return strconv.FormatInt(id, 10) }

// List returns webhook endpoints.
func (s *WebhooksService) List(ctx context.Context) (M, error) {
	return s.c.call(ctx, "GET", "/webhooks", nil)
}

// Create adds an endpoint. params: url (required, https), events. The answer has the signing_secret.
func (s *WebhooksService) Create(ctx context.Context, params M) (M, error) {
	return s.c.call(ctx, "POST", "/webhooks", obj(params))
}

// Get returns a webhook with its signing secret.
func (s *WebhooksService) Get(ctx context.Context, id int64) (M, error) {
	return s.c.call(ctx, "GET", "/webhooks/"+idPath(id), nil)
}

// Update changes a webhook. params: url, events, enabled.
func (s *WebhooksService) Update(ctx context.Context, id int64, params M) (M, error) {
	return s.c.call(ctx, "PATCH", "/webhooks/"+idPath(id), obj(params))
}

// Delete deletes a webhook and its deliveries.
func (s *WebhooksService) Delete(ctx context.Context, id int64) (M, error) {
	return s.c.call(ctx, "DELETE", "/webhooks/"+idPath(id), nil)
}

// RotateSecret makes a new signing secret (the old one signs for 24 more hours).
func (s *WebhooksService) RotateSecret(ctx context.Context, id int64) (M, error) {
	return s.c.call(ctx, "POST", "/webhooks/"+idPath(id)+"/rotate-secret", nil)
}

// Test sends a test email.delivered event.
func (s *WebhooksService) Test(ctx context.Context, id int64) (M, error) {
	return s.c.call(ctx, "POST", "/webhooks/"+idPath(id)+"/test", nil)
}

// Deliveries returns the latest 100 deliveries.
func (s *WebhooksService) Deliveries(ctx context.Context, id int64) (M, error) {
	return s.c.call(ctx, "GET", "/webhooks/"+idPath(id)+"/deliveries", nil)
}

// ReplayDelivery delivers an event again.
func (s *WebhooksService) ReplayDelivery(ctx context.Context, deliveryID int64) (M, error) {
	return s.c.call(ctx, "POST", "/webhook-deliveries/"+idPath(deliveryID)+"/replay", nil)
}
