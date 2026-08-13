# Offline, GPS, selfie, and field attendance

## Offline queue

The browser module uses a PeopleHub-namespaced IndexedDB store only after a
submission fails because the device is offline. It queues a unique event ID,
source, event type, occurrence/device time, optional GPS/accuracy, and limited
device locale information. It does not queue selfies, notes, activity text, or
customer/destination text. Successful synchronization deletes the local item.
The server endpoint uses the same source external ID and returns an existing event
for retries, preventing duplicate raw punches.

Offline data is not trusted merely because it came from the registered browser.
The server stores both device and receipt timestamps. Missing device time, large
clock mismatch, delayed synchronization, absent GPS, or insufficient GPS accuracy
become explicit anomaly codes. Such events and their normalized records are
blocked from downstream payroll use until reviewed. Authenticated HTML is not
placed into a service-worker cache by this phase.

## GPS and field context

GPS requirement, acceptable accuracy, and maximum offline delay belong to each
attendance source. Coordinates and accuracy are evidence; Phase 7 does not
implement a production geofence decision because authorized work locations,
radius rules, indoor accuracy, and exception policy have not yet been approved.
Field activity, destination/customer, and notes are optional encrypted fields.
The employee UI must explain permission failure and must never fabricate a
location when browser geolocation is unavailable.

## Selfie controls

An online source may require a selfie through source configuration. The image is
stored as a restricted private employee document with a generated path and
checksum. Selfies are not sent to public storage, logs, notifications, or the
offline queue. Production use requires documented consent/legal basis, purpose,
retention/deletion schedule, access review, malware/content scanning, and incident
handling. Until those controls and an employee-facing privacy notice are approved,
selfie remains a controlled pilot capability rather than a universal policy.
