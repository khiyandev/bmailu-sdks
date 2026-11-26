# frozen_string_literal: true

# Generated from the Bmailu OpenAPI operation list. One method per operation.
module Bmailu
  module Resources
    class Me < Resource
      # Who the key belongs to. GET /me
      def get
        @client.request("GET", "/me")
      end

      # Set the recovery email. PATCH /me
      def update(params)
        @client.request("PATCH", "/me", body: params)
      end
    end

    class Domains < Resource
      # List mailbox domains. GET /domains
      def list
        @client.request("GET", "/domains")
      end

      # Add a mailbox domain. POST /domains
      def create(params)
        @client.request("POST", "/domains", body: params)
      end

      # Get a domain with DNS records, mailboxes and aliases. GET /domains/{domain_id}
      def get(domain_id)
        @client.request("GET", "/domains/#{seg(domain_id)}")
      end

      # Remove a pending domain. DELETE /domains/{domain_id}
      def delete(domain_id)
        @client.request("DELETE", "/domains/#{seg(domain_id)}")
      end

      # Check the ownership TXT record. POST /domains/{domain_id}/verify
      def verify(domain_id)
        @client.request("POST", "/domains/#{seg(domain_id)}/verify")
      end

      # Check MX, SPF, DKIM and DMARC. POST /domains/{domain_id}/dns-check
      def check_dns(domain_id)
        @client.request("POST", "/domains/#{seg(domain_id)}/dns-check")
      end

      # Catch-all on (mailbox id) or off (null). PUT /domains/{domain_id}/catch-all
      def set_catch_all(domain_id, mailbox_id)
        @client.request("PUT", "/domains/#{seg(domain_id)}/catch-all", body: { mailbox_id: mailbox_id })
      end
    end

    class Mailboxes < Resource
      # Create a mailbox (password shown once). POST /domains/{domain_id}/mailboxes
      def create(domain_id, params)
        @client.request("POST", "/domains/#{seg(domain_id)}/mailboxes", body: params)
      end

      # Delete a mailbox (restorable for 14 days). DELETE /mailboxes/{mailbox_id}
      def delete(mailbox_id)
        @client.request("DELETE", "/mailboxes/#{seg(mailbox_id)}")
      end

      # Restore a deleted mailbox. POST /mailboxes/{mailbox_id}/restore
      def restore(mailbox_id)
        @client.request("POST", "/mailboxes/#{seg(mailbox_id)}/restore")
      end

      # Set a new random password. POST /mailboxes/{mailbox_id}/password
      def reset_password(mailbox_id)
        @client.request("POST", "/mailboxes/#{seg(mailbox_id)}/password")
      end

      # Forward copies (or stop with null). PUT /mailboxes/{mailbox_id}/forward
      def set_forward(mailbox_id, to)
        @client.request("PUT", "/mailboxes/#{seg(mailbox_id)}/forward", body: { to: to })
      end

      # Send a test email to the mailbox. POST /mailboxes/{mailbox_id}/test-email
      def send_test_email(mailbox_id)
        @client.request("POST", "/mailboxes/#{seg(mailbox_id)}/test-email")
      end
    end

    class Aliases < Resource
      # Add an alias. POST /domains/{domain_id}/aliases
      def create(domain_id, params)
        @client.request("POST", "/domains/#{seg(domain_id)}/aliases", body: params)
      end

      # Remove an alias. DELETE /aliases/{alias_id}
      def delete(alias_id)
        @client.request("DELETE", "/aliases/#{seg(alias_id)}")
      end
    end

    class ApiKeys < Resource
      # List API keys. GET /api-keys
      def list
        @client.request("GET", "/api-keys")
      end

      # Create an API key (shown once). POST /api-keys
      def create(params)
        @client.request("POST", "/api-keys", body: params)
      end

      # Revoke an API key. DELETE /api-keys/{key_id}
      def revoke(key_id)
        @client.request("DELETE", "/api-keys/#{seg(key_id)}")
      end
    end

    class SendingDomains < Resource
      # List sending domains. GET /sending-domains
      def list
        @client.request("GET", "/sending-domains")
      end

      # Add a sending domain. POST /sending-domains
      def create(params)
        @client.request("POST", "/sending-domains", body: params)
      end

      # Get a sending domain. GET /sending-domains/{sending_domain_id}
      def get(sending_domain_id)
        @client.request("GET", "/sending-domains/#{seg(sending_domain_id)}")
      end

      # Remove a sending domain. DELETE /sending-domains/{sending_domain_id}
      def delete(sending_domain_id)
        @client.request("DELETE", "/sending-domains/#{seg(sending_domain_id)}")
      end

      # Check the TXT and create DKIM keys. POST /sending-domains/{sending_domain_id}/verify
      def verify(sending_domain_id)
        @client.request("POST", "/sending-domains/#{seg(sending_domain_id)}/verify")
      end

      # Check DKIM, bounce MX and SPF. POST /sending-domains/{sending_domain_id}/check
      def check(sending_domain_id)
        @client.request("POST", "/sending-domains/#{seg(sending_domain_id)}/check")
      end

      # Change settings, e.g. { tls: "enforced" }. PATCH /sending-domains/{sending_domain_id}
      def update(sending_domain_id, params)
        @client.request("PATCH", "/sending-domains/#{seg(sending_domain_id)}", body: params)
      end

      # Deliverability insights. GET /sending-domains/{sending_domain_id}/insights
      def insights(sending_domain_id)
        @client.request("GET", "/sending-domains/#{seg(sending_domain_id)}/insights")
      end
    end

    class Emails < Resource
      # Send an email. Returns {id}. POST /emails
      def send(params, idempotency_key: nil)
        @client.request("POST", "/emails", body: params, headers: idem(idempotency_key))
      end

      # Send up to 100 emails. Returns {data: [{id}]}. POST /emails/batch
      def batch(emails, idempotency_key: nil)
        @client.request("POST", "/emails/batch", body: emails, headers: idem(idempotency_key))
      end

      # Get an email with per-recipient status and events. GET /emails/{email_id}
      def get(email_id)
        @client.request("GET", "/emails/#{seg(email_id)}")
      end

      # query: limit, before, status, source, key, domain, q, days. GET /emails
      def list(query = {})
        @client.request("GET", "/emails", body: nil, query: query)
      end

      # CSV text of the filtered list. GET /emails/export
      def export(query = {})
        @client.request("GET", "/emails/export", body: nil, query: query)
      end

      # Reschedule: {scheduled_at}. PATCH /emails/{email_id}
      def update(email_id, params)
        @client.request("PATCH", "/emails/#{seg(email_id)}", body: params)
      end

      # Reschedule a scheduled email. PATCH /emails/{email_id}
      def reschedule(email_id, scheduled_at)
        @client.request("PATCH", "/emails/#{seg(email_id)}", body: { scheduled_at: scheduled_at })
      end

      # Cancel a scheduled email. POST /emails/{email_id}/cancel
      def cancel(email_id)
        @client.request("POST", "/emails/#{seg(email_id)}/cancel")
      end
    end

    class Webhooks < Resource
      # List webhook endpoints. GET /webhooks
      def list
        @client.request("GET", "/webhooks")
      end

      # Add a webhook endpoint. POST /webhooks
      def create(params)
        @client.request("POST", "/webhooks", body: params)
      end

      # Get a webhook (with its signing secret). GET /webhooks/{webhook_id}
      def get(webhook_id)
        @client.request("GET", "/webhooks/#{seg(webhook_id)}")
      end

      # Change a webhook. PATCH /webhooks/{webhook_id}
      def update(webhook_id, params)
        @client.request("PATCH", "/webhooks/#{seg(webhook_id)}", body: params)
      end

      # Delete a webhook. DELETE /webhooks/{webhook_id}
      def delete(webhook_id)
        @client.request("DELETE", "/webhooks/#{seg(webhook_id)}")
      end

      # New signing secret. POST /webhooks/{webhook_id}/rotate-secret
      def rotate_secret(webhook_id)
        @client.request("POST", "/webhooks/#{seg(webhook_id)}/rotate-secret")
      end

      # Send a test email.delivered event. POST /webhooks/{webhook_id}/test
      def test(webhook_id)
        @client.request("POST", "/webhooks/#{seg(webhook_id)}/test")
      end

      # Latest 100 deliveries. GET /webhooks/{webhook_id}/deliveries
      def deliveries(webhook_id)
        @client.request("GET", "/webhooks/#{seg(webhook_id)}/deliveries")
      end

      # Deliver an event again. POST /webhook-deliveries/{delivery_id}/replay
      def replay_delivery(delivery_id)
        @client.request("POST", "/webhook-deliveries/#{seg(delivery_id)}/replay")
      end
    end

    class Suppressions < Resource
      # query: email. GET /suppressions
      def list(query = {})
        @client.request("GET", "/suppressions", body: nil, query: query)
      end

      # Suppress an address. POST /suppressions
      def create(params)
        @client.request("POST", "/suppressions", body: params)
      end

      # Remove an address. DELETE /suppressions/{email}
      def delete(email)
        @client.request("DELETE", "/suppressions/#{seg(email)}")
      end
    end

    class Metrics < Resource
      # query: days, domain. GET /metrics
      def get(query = {})
        @client.request("GET", "/metrics", body: nil, query: query)
      end
    end

    class Logs < Resource
      # query: source, status, key, limit. GET /logs
      def list(query = {})
        @client.request("GET", "/logs", body: nil, query: query)
      end
    end

    class Inbox < Resource
      # List folders with counts. GET /mailboxes/{mailbox_id}/folders
      def list_folders(mailbox_id)
        @client.request("GET", "/mailboxes/#{seg(mailbox_id)}/folders")
      end

      # Create a folder. POST /mailboxes/{mailbox_id}/folders
      def create_folder(mailbox_id, params)
        @client.request("POST", "/mailboxes/#{seg(mailbox_id)}/folders", body: params)
      end

      # Delete an empty own folder. DELETE /mailboxes/{mailbox_id}/folders/{folder_id}
      def delete_folder(mailbox_id, folder_id)
        @client.request("DELETE", "/mailboxes/#{seg(mailbox_id)}/folders/#{seg(folder_id)}")
      end

      # query: folder, q, from, unread, flagged, label, message_id, threads, limit, position. GET /mailboxes/{mailbox_id}/messages
      def list_messages(mailbox_id, query = {})
        @client.request("GET", "/mailboxes/#{seg(mailbox_id)}/messages", body: nil, query: query)
      end

      # Write a new email (or a draft). POST /mailboxes/{mailbox_id}/messages
      def create_message(mailbox_id, params)
        @client.request("POST", "/mailboxes/#{seg(mailbox_id)}/messages", body: params)
      end

      # Read a message. GET /mailboxes/{mailbox_id}/messages/{message_id}
      def get_message(mailbox_id, message_id)
        @client.request("GET", "/mailboxes/#{seg(mailbox_id)}/messages/#{seg(message_id)}")
      end

      # Mark, label or move a message. PATCH /mailboxes/{mailbox_id}/messages/{message_id}
      def update_message(mailbox_id, message_id, params)
        @client.request("PATCH", "/mailboxes/#{seg(mailbox_id)}/messages/#{seg(message_id)}", body: params)
      end

      # Move a message to Deleted Items. DELETE /mailboxes/{mailbox_id}/messages/{message_id}
      def trash_message(mailbox_id, message_id)
        @client.request("DELETE", "/mailboxes/#{seg(mailbox_id)}/messages/#{seg(message_id)}")
      end

      # Download an attachment (raw bytes). GET /mailboxes/{mailbox_id}/messages/{message_id}/attachments/{blob_id}
      def get_attachment(mailbox_id, message_id, blob_id)
        @client.request("GET", "/mailboxes/#{seg(mailbox_id)}/messages/#{seg(message_id)}/attachments/#{seg(blob_id)}")
      end

      # Reply in the same thread. POST /mailboxes/{mailbox_id}/messages/{message_id}/reply
      def reply(mailbox_id, message_id, params)
        @client.request("POST", "/mailboxes/#{seg(mailbox_id)}/messages/#{seg(message_id)}/reply", body: params)
      end

      # Forward with the attachments. POST /mailboxes/{mailbox_id}/messages/{message_id}/forward
      def forward(mailbox_id, message_id, params)
        @client.request("POST", "/mailboxes/#{seg(mailbox_id)}/messages/#{seg(message_id)}/forward", body: params)
      end

      # Send a draft. POST /mailboxes/{mailbox_id}/messages/{message_id}/send
      def send_draft(mailbox_id, message_id)
        @client.request("POST", "/mailboxes/#{seg(mailbox_id)}/messages/#{seg(message_id)}/send")
      end

      # Read a whole conversation. GET /mailboxes/{mailbox_id}/threads/{thread_id}
      def get_thread(mailbox_id, thread_id)
        @client.request("GET", "/mailboxes/#{seg(mailbox_id)}/threads/#{seg(thread_id)}")
      end
    end

    class Templates < Resource
      # List templates. GET /templates
      def list
        @client.request("GET", "/templates")
      end

      # Create a template. POST /templates
      def create(params)
        @client.request("POST", "/templates", body: params)
      end

      # Get a template (id or alias). GET /templates/{template}
      def get(template)
        @client.request("GET", "/templates/#{seg(template)}")
      end

      # Change the draft. PATCH /templates/{template}
      def update(template, params)
        @client.request("PATCH", "/templates/#{seg(template)}", body: params)
      end

      # Delete a template and its versions. DELETE /templates/{template}
      def delete(template)
        @client.request("DELETE", "/templates/#{seg(template)}")
      end

      # Publish the draft as a new version. POST /templates/{template}/publish
      def publish(template)
        @client.request("POST", "/templates/#{seg(template)}/publish")
      end

      # Copy a template. POST /templates/{template}/duplicate
      def duplicate(template)
        @client.request("POST", "/templates/#{seg(template)}/duplicate")
      end

      # List published versions. GET /templates/{template}/versions
      def versions(template)
        @client.request("GET", "/templates/#{seg(template)}/versions")
      end

      # Make an earlier version the published one. POST /templates/{template}/versions/{version}/publish
      def publish_version(template, version)
        @client.request("POST", "/templates/#{seg(template)}/versions/#{seg(version)}/publish")
      end

      # params: variables, version. POST /templates/{template}/render
      def render(template, params = {})
        @client.request("POST", "/templates/#{seg(template)}/render", body: params)
      end

      # HTML preview with fallback values. GET /templates/{template}/preview.html
      def preview(template)
        @client.request("GET", "/templates/#{seg(template)}/preview.html")
      end
    end

    class Contacts < Resource
      # query: segment_id, status, q, limit, position. GET /contacts
      def list(query = {})
        @client.request("GET", "/contacts", body: nil, query: query)
      end

      # Add or update a contact (by email). POST /contacts
      def create(params)
        @client.request("POST", "/contacts", body: params)
      end

      # params: csv, mode, from, consent_statement, segment_id. POST /contacts/import
      def import_csv(params)
        @client.request("POST", "/contacts/import", body: params)
      end

      # Get a contact (id or email). GET /contacts/{contact}
      def get(contact)
        @client.request("GET", "/contacts/#{seg(contact)}")
      end

      # Change a contact, (un)subscribe, set topics. PATCH /contacts/{contact}
      def update(contact, params)
        @client.request("PATCH", "/contacts/#{seg(contact)}", body: params)
      end

      # Delete a contact. DELETE /contacts/{contact}
      def delete(contact)
        @client.request("DELETE", "/contacts/#{seg(contact)}")
      end
    end

    class Segments < Resource
      # List segments. GET /segments
      def list
        @client.request("GET", "/segments")
      end

      # Create a segment. POST /segments
      def create(params)
        @client.request("POST", "/segments", body: params)
      end

      # Get a segment. GET /segments/{segment_id}
      def get(segment_id)
        @client.request("GET", "/segments/#{seg(segment_id)}")
      end

      # Rename a segment. PATCH /segments/{segment_id}
      def update(segment_id, params)
        @client.request("PATCH", "/segments/#{seg(segment_id)}", body: params)
      end

      # Delete a segment. DELETE /segments/{segment_id}
      def delete(segment_id)
        @client.request("DELETE", "/segments/#{seg(segment_id)}")
      end

      # Add contacts (ids or emails). POST /segments/{segment_id}/contacts
      def add_contacts(segment_id, contacts)
        @client.request("POST", "/segments/#{seg(segment_id)}/contacts", body: { contacts: contacts })
      end

      # Remove a contact. DELETE /segments/{segment_id}/contacts/{contact}
      def remove_contact(segment_id, contact)
        @client.request("DELETE", "/segments/#{seg(segment_id)}/contacts/#{seg(contact)}")
      end
    end

    class Topics < Resource
      # List topics. GET /topics
      def list
        @client.request("GET", "/topics")
      end

      # Create a topic. POST /topics
      def create(params)
        @client.request("POST", "/topics", body: params)
      end

      # Delete a topic. DELETE /topics/{topic_id}
      def delete(topic_id)
        @client.request("DELETE", "/topics/#{seg(topic_id)}")
      end
    end

    class Broadcasts < Resource
      # List broadcasts. GET /broadcasts
      def list
        @client.request("GET", "/broadcasts")
      end

      # Create a draft broadcast. POST /broadcasts
      def create(params)
        @client.request("POST", "/broadcasts", body: params)
      end

      # Get a broadcast. GET /broadcasts/{broadcast_id}
      def get(broadcast_id)
        @client.request("GET", "/broadcasts/#{seg(broadcast_id)}")
      end

      # Change a draft or rejected broadcast. PATCH /broadcasts/{broadcast_id}
      def update(broadcast_id, params)
        @client.request("PATCH", "/broadcasts/#{seg(broadcast_id)}", body: params)
      end

      # Delete a broadcast. DELETE /broadcasts/{broadcast_id}
      def delete(broadcast_id)
        @client.request("DELETE", "/broadcasts/#{seg(broadcast_id)}")
      end

      # Send or schedule. params: scheduled_at. POST /broadcasts/{broadcast_id}/send
      def send(broadcast_id, params = {})
        @client.request("POST", "/broadcasts/#{seg(broadcast_id)}/send", body: params)
      end

      # Cancel (back to draft). POST /broadcasts/{broadcast_id}/cancel
      def cancel(broadcast_id)
        @client.request("POST", "/broadcasts/#{seg(broadcast_id)}/cancel")
      end

      # Send one test copy. POST /broadcasts/{broadcast_id}/test
      def test(broadcast_id, to)
        @client.request("POST", "/broadcasts/#{seg(broadcast_id)}/test", body: { to: to })
      end

      # Delivery, open, click and unsubscribe counts. GET /broadcasts/{broadcast_id}/stats
      def stats(broadcast_id)
        @client.request("GET", "/broadcasts/#{seg(broadcast_id)}/stats")
      end
    end

    class Events < Resource
      # params: event, email or contact_id, first_name, data. POST /events
      def send(params)
        @client.request("POST", "/events", body: params)
      end

      # query: contact. GET /events
      def list(query = {})
        @client.request("GET", "/events", body: nil, query: query)
      end
    end

    class Automations < Resource
      # List automations. GET /automations
      def list
        @client.request("GET", "/automations")
      end

      # Create an automation (draft). POST /automations
      def create(params)
        @client.request("POST", "/automations", body: params)
      end

      # Get an automation. GET /automations/{automation_id}
      def get(automation_id)
        @client.request("GET", "/automations/#{seg(automation_id)}")
      end

      # Change a stopped or draft automation. PATCH /automations/{automation_id}
      def update(automation_id, params)
        @client.request("PATCH", "/automations/#{seg(automation_id)}", body: params)
      end

      # Delete a stopped automation. DELETE /automations/{automation_id}
      def delete(automation_id)
        @client.request("DELETE", "/automations/#{seg(automation_id)}")
      end

      # Start it. POST /automations/{automation_id}/start
      def start(automation_id)
        @client.request("POST", "/automations/#{seg(automation_id)}/start")
      end

      # Stop it and every run. POST /automations/{automation_id}/stop
      def stop(automation_id)
        @client.request("POST", "/automations/#{seg(automation_id)}/stop")
      end

      # Copy it as a draft. POST /automations/{automation_id}/duplicate
      def duplicate(automation_id)
        @client.request("POST", "/automations/#{seg(automation_id)}/duplicate")
      end

      # query: status. GET /automations/{automation_id}/runs
      def runs(automation_id, query = {})
        @client.request("GET", "/automations/#{seg(automation_id)}/runs", body: nil, query: query)
      end

      # One run with its step log. GET /automations/{automation_id}/runs/{run_id}
      def get_run(automation_id, run_id)
        @client.request("GET", "/automations/#{seg(automation_id)}/runs/#{seg(run_id)}")
      end
    end
  end
end
