# Runbook

## "Nothing is being delivered"

Open the standing widget first. It answers the question in one line.

| It says | What it means | What to do |
|---|---|---|
| Something to carry a request — **—**, "nothing is bound" | No transport. This is a fresh install. | Bind one. See `docs/adoption.md`. |
| Something to carry a request — **—**, "misconfiguration" | Something *is* configured and the container cannot build it. | Fix the class name. Nothing is recorded while this holds — the domain raises rather than writing a refusal row. |
| Something to carry a request — **Bound** | The seam is fine. Read the refusal lines below it. | Each refusal names its own cause. |
| Refused: `endpoint_retired` / `extension_retired` | Somebody retired it. | Reinstate it from its own screen. |
| Refused: `destination_not_public` | The URL resolves into private or reserved space **now**, whatever it resolved to at registration. | Check the receiver's DNS. |
| Refused: `window_closed` | The delivery window closed. There is no re-open verb. | Whoever owns the event raises it again under a new cause reference. |
| Refused: `concurrent_attempt` | Two workers reached the same attempt number and the index arbitrated. | Nothing. The other worker owns the reschedule. |

## "I lost a signing secret"

It is gone. Both columns are encrypted and `$hidden` and nothing reads one back —
not a query, not an event, not a serialisation. Rotate the endpoint, copy the new
secret from the notification before dismissing it, and give the receiver the
overlap window to pick it up.

## "A rotation broke my receiver"

It should not have: the previous secret stays live for a bounded overlap and every
request carries a signature under each. Check the endpoint's **Rotation overlap**
line. If it says the overlap has closed, somebody used "Close the overlap now", or
`rotation_overlap_hours` elapsed.

## "The delivery says it went, but the receiver saw nothing"

Read the attempt row rather than the delivery.

- **Outcome `Delivered`, a status in the 2xx range** — something at that URL
  accepted it. That is as far as this module can see.
- **Outcome `Not accepted`, a status** — the receiver answered and refused.
- **Outcome `Not accepted`, "the transport never completed"** — nothing was
  received at all. The excerpt on that row is *the sender's own exception
  message*, not anything the receiver said, and its duration is suppressed
  because the zero stored beside it was never measured.
- **Outcome `Never settled`** — a worker was killed between writing the row and
  settling it. Whether the request went is unknown. The row is the evidence that
  something was attempted; the host's equivalent wrote nothing at all.

## "A number on the panel looks wrong"

Every figure is a count over an indexed, tenant-scoped query, read once per
request and shared. Nothing here computes, derives, clamps or divides. If a count
disagrees with the table beside it, the table is paginated and the count is not —
report it rather than reconciling it on screen.

## "The panel shows an error about a tenant"

`Commerce extensions could not resolve a tenant for this panel` means the host
attached the plugin without `tenantUsing()` and the panel has no Filament tenant
either. There is no "show everything" to fall back to.
