package bmailu

import (
	"bufio"
	"context"
	"crypto/rand"
	"encoding/base64"
	"encoding/json"
	"errors"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"testing"
	"time"
)

type testConfig struct {
	API        string `json:"api"`
	Key        string `json:"key"`
	SendingKey string `json:"sending_key"`
}

var cfg testConfig

const (
	from = "Shop <hello@shop.test>"
	to   = "delivered@simulator.bmailu.com"
)

// TestMain uses BMAILU_TEST_JSON (set by test.sh) or starts ../test-server.js on a fixed port itself.
func TestMain(m *testing.M) {
	raw := os.Getenv("BMAILU_TEST_JSON")
	var server *exec.Cmd
	if raw == "" {
		server = exec.Command("node", "../test-server.js", "47131")
		out, err := server.StdoutPipe()
		if err != nil {
			panic(err)
		}
		server.Stderr = os.Stderr
		if err := server.Start(); err != nil {
			panic(err)
		}
		line, err := bufio.NewReader(out).ReadString('\n')
		if err != nil {
			_ = server.Process.Kill()
			panic("test server did not start: " + err.Error())
		}
		raw = line
	}
	if err := json.Unmarshal([]byte(raw), &cfg); err != nil {
		panic(err)
	}
	code := m.Run()
	if server != nil {
		_ = server.Process.Kill()
		_ = server.Wait()
	}
	os.Exit(code)
}

func client() *Client { return NewClient(cfg.Key, WithBaseURL(cfg.API)) }

func apiError(t *testing.T, err error) *Error {
	t.Helper()
	var e *Error
	if !errors.As(err, &e) {
		t.Fatalf("want *bmailu.Error, got %v", err)
	}
	return e
}

func TestSendIdempotentGet(t *testing.T) {
	ctx := context.Background()
	c := client()
	req := &SendEmailRequest{From: from, To: []string{to}, Subject: "Hello", HTML: "<p>Hello</p>"}
	a, err := c.Emails.Send(ctx, req, WithIdempotencyKey("go-idem-1"))
	if err != nil {
		t.Fatal(err)
	}
	b, err := c.Emails.Send(ctx, req, WithIdempotencyKey("go-idem-1"))
	if err != nil {
		t.Fatal(err)
	}
	if a.ID == "" || a.ID != b.ID {
		t.Fatalf("ids differ: %q %q", a.ID, b.ID)
	}
	got, err := c.Emails.Get(ctx, a.ID)
	if err != nil || got.Object != "email" || got.Subject != "Hello" || len(got.To) != 1 {
		t.Fatalf("get: %+v %v", got, err)
	}
	list, err := c.Emails.List(ctx, url.Values{"limit": {"5"}})
	if err != nil || len(list.Data) == 0 {
		t.Fatalf("list: %+v %v", list, err)
	}
	var csv string
	if err := c.Do(ctx, "GET", "/emails/export", nil, &csv); err != nil || !strings.HasPrefix(csv, "id,") {
		t.Fatalf("export: %q %v", csv, err)
	}
}

func TestBatch(t *testing.T) {
	req := SendEmailRequest{From: from, To: []string{to}, Subject: "Batch", Text: "Hi"}
	r, err := client().Emails.Batch(context.Background(), []SendEmailRequest{req, req})
	if err != nil || len(r.Data) != 2 {
		t.Fatalf("batch: %+v %v", r, err)
	}
}

func TestContactAndEvent(t *testing.T) {
	ctx := context.Background()
	c := client()
	contact, err := c.Contacts.Create(ctx, M{"email": "go-ann@example.com", "first_name": "Ann"})
	if err != nil || contact["email"] != "go-ann@example.com" {
		t.Fatalf("contact: %v %v", contact, err)
	}
	ev, err := c.Events.Send(ctx, M{"event": "signed_up", "email": "go-ann@example.com"})
	if err != nil || ev["object"] != "event" || ev["contact_id"] != contact["id"] {
		t.Fatalf("event: %v %v", ev, err)
	}
}

func TestTemplatePublishSend(t *testing.T) {
	ctx := context.Background()
	c := client()
	tpl, err := c.Templates.Create(ctx, M{"name": "Welcome go", "alias": "welcome-go", "subject": "Hi {{name}}",
		"html": "<p>Hi {{name}}</p>", "variables": []M{{"key": "name", "fallback_value": "there"}}})
	if err != nil {
		t.Fatal(err)
	}
	p, err := c.Templates.Publish(ctx, tpl["id"].(string))
	if err != nil || p["published_version"] != float64(1) {
		t.Fatalf("publish: %v %v", p, err)
	}
	html, err := c.Templates.Preview(ctx, "welcome-go")
	if err != nil || !strings.Contains(html, "Hi there") {
		t.Fatalf("preview: %q %v", html, err)
	}
	if _, err := c.Templates.Render(ctx, "welcome-go", nil); err != nil {
		t.Fatalf("render: %v", err)
	}
	r, err := c.Emails.Send(ctx, &SendEmailRequest{From: from, To: []string{to},
		Template: &EmailTemplate{ID: "welcome-go", Variables: map[string]any{"name": "Ann"}}})
	if err != nil {
		t.Fatal(err)
	}
	got, err := c.Emails.Get(ctx, r.ID)
	if err != nil || got.Subject != "Hi Ann" {
		t.Fatalf("subject: %+v %v", got, err)
	}
}

func TestWebhooksList(t *testing.T) {
	r, err := client().Webhooks.List(context.Background())
	if err != nil || r["object"] != "list" {
		t.Fatalf("webhooks: %v %v", r, err)
	}
	if _, ok := r["data"].([]any); !ok {
		t.Fatalf("data: %v", r["data"])
	}
}

func TestErrorDomainNotVerified(t *testing.T) {
	_, err := client().Emails.Send(context.Background(), &SendEmailRequest{From: "x@nope.test", To: []string{to}, Subject: "x", Text: "x"})
	e := apiError(t, err)
	if e.Code != "domain_not_verified" || e.StatusCode != 403 {
		t.Fatalf("got %+v", e)
	}
}

func TestSendingKeyRestricted(t *testing.T) {
	s := NewClient(cfg.SendingKey, WithBaseURL(cfg.API))
	err := s.Do(context.Background(), "GET", "/domains", nil, nil)
	if e := apiError(t, err); e.Code != "restricted_api_key" {
		t.Fatalf("got %+v", e)
	}
}

func TestGenericDo(t *testing.T) {
	var me M
	if err := client().Do(context.Background(), "GET", "/me", nil, &me); err != nil || me == nil {
		t.Fatalf("me: %v %v", me, err)
	}
	err := client().Do(context.Background(), "DELETE", "/suppressions/"+seg("nobody@example.com"), nil, nil)
	if e := apiError(t, err); e.Code != "not_found" {
		t.Fatalf("got %+v", e)
	}
}

func TestContextCancel(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	if _, err := client().Webhooks.List(ctx); apiError(t, err).Code != "connection_error" {
		t.Fatal("cancelled context should fail")
	}
}

func panelSign(t *testing.T, secret, id string, ts int64, body string) string {
	js := "process.stdout.write(require(process.argv[1]).sign(process.argv[2], process.argv[3], process.argv[4], process.argv[5]))"
	out, err := exec.Command("node", "-e", js, "../../panel/src/webhook-routes.js", secret, id, strconv.FormatInt(ts, 10), body).Output()
	if err != nil {
		t.Fatal(err)
	}
	return string(out)
}

func TestVerifyWebhook(t *testing.T) {
	k := make([]byte, 24)
	_, _ = rand.Read(k)
	secret := "whsec_" + base64.StdEncoding.EncodeToString(k)
	body := `{"type":"email.delivered","data":{"email_id":"e1"}}`
	ts := time.Now().Unix()
	sig := panelSign(t, secret, "msg_1", ts, body)
	h := http.Header{}
	h.Set("Webhook-Id", "msg_1")
	h.Set("webhook-timestamp", strconv.FormatInt(ts, 10))
	h.Set("webhook-signature", "v1,bogus "+sig)
	if err := VerifyWebhook([]byte(body), h, secret); err != nil {
		t.Fatalf("valid webhook refused: %v", err)
	}
	if err := VerifyWebhook([]byte(strings.Replace(body, "e1", "e2", 1)), h, secret); !errors.Is(err, ErrWebhookVerification) {
		t.Fatalf("tampered body accepted: %v", err)
	}
	if err := VerifyWebhookAt([]byte(body), h, secret, time.Unix(ts+301, 0)); !errors.Is(err, ErrWebhookVerification) {
		t.Fatalf("old timestamp accepted: %v", err)
	}
}
