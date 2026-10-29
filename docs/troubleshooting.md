---
title: Troubleshooting
nav_order: 8
description: "Why an alert did not arrive, came twice or came for the wrong reason, and how to fix the Google REQUEST_DENIED alert."
---

# Troubleshooting

## No mail arrives

- `API_KEY_ALERT_MAIL_TO` is empty or not a valid address. The log then says
  "API key alert: no recipients".
- The service is in its throttle window. Each service mails once per six hours; check the log for
  "API key alert: … rejected the API key".
- `API_KEY_ALERT_MAIL_QUEUE=true` without a running queue worker.
- The call does not go through the Http client and its exception is caught without `report()`. Wrap
  it in `ApiKeyAlert::guard()`.
- The host is not listed under any service. Add it, see [Adding a service](custom-services.md).
- The config is cached. Run `php artisan config:clear` after changing `.env`.

## A recovery mail never comes

Recovery needs a successful response from the same service after the failure. The outage is kept in
the cache for 30 days; clearing the cache in between forgets it. Run `php artisan api-key-alert:check`
to test the key on demand.

## Every server sends its own mail

The throttle lives in the default cache store. A `file` or `array` store is per server; use a shared
store (database, Redis).

## Google says REQUEST_DENIED

The message usually reads "This IP, site or mobile application is not authorized to use this API
key. Request received from IP address …". The key has application restrictions that do not allow
your server:

- A key restricted to **HTTP referrers** never works from PHP, because a server request has no
  referrer. Use a separate key for server calls, restricted by IP address.
- A key restricted to **IP addresses** needs the address from the message. Servers often send over
  **IPv6**, so add the IPv6 address as well as the IPv4 one.
- Check under **API restrictions** that the API you call (Geocoding, Directions) is enabled for the key.

## A false alarm

Some APIs answer 403 for a permission on one resource, not for a bad key. Give that service a
detector that only reports what is really a key problem, see
[Adding a service](custom-services.md#watch-out-with-403).
