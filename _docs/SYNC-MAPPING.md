# Service and Guest Mapping

The addon module's **Sync** tab reconciles WHMCS services with live Proxmox
guests. Use it when a module command reports that no guest is linked to a
service, or when the stored mapping no longer matches the guest configuration.

Mapping actions update the module's database records. They do not start, stop,
suspend, resume, or delete a Proxmox guest.

## Prerequisites

- Administrator access to the WHMCS addon module.
- A configured and enabled WHMCS server using the `pvewhmcs` module type.
- Working Proxmox API credentials for that server.
- A known relationship between the WHMCS service and Proxmox guest. Confirm the
  service ID, VMID, hostname, and dedicated IP before changing a mapping.
- To import an orphaned guest as a new service: an active client, a non-retired
  `pvewhmcs` product compatible with the selected server, pricing in the
  client's currency, an active payment method, and a product custom field named
  `vmid` or `vpsid`.

## Open and analyze the Sync page

1. In the WHMCS administration area, open **Addons > Proxmox VE for WHMCS**.
2. Select the **Sync** tab.
3. Select the Proxmox server that owns the service.
4. Click **Analyze server**.
5. Use the filters or search field to find the service ID, VMID, domain, client,
   or IP address.

The page prioritizes records that need attention and keeps healthy mappings out
of the way by default. The table is scrollable so large clusters do not create a
very long administration page.

## Alignment states

| State | Meaning | Available action |
| --- | --- | --- |
| `Mapped (OK)` | The stored service mapping agrees with the live guest. | Unlink the database mapping. |
| `Discrepancy` | A mapping exists, but its VMID, name, IP, type, or node differs from current data. | Synchronize from Proxmox or unlink after review. |
| `Network pending` | The service and guest are mapped, but no primary IPv4 is stored yet. | Complete network details from verified Proxmox data or manual entry. |
| `Unmapped` | A WHMCS service has no module mapping. | Link the verified auto-match or select a guest manually. |
| `Orphaned VM` | A live Proxmox guest has no active WHMCS service mapping. | Select and link a service manually, or create a WHMCS service from the guest. |

## Restore a missing service mapping

1. Search for the affected service ID.
2. Verify the proposed guest's VMID, hostname, node, type, and IP address.
3. If the page presents **Link VMID _n_**, verify the evidence and click it.
4. Confirm the mapping in the browser prompt.
5. Verify the success message and review the resulting alignment state. A
   verified auto-match should become `Mapped (OK)`; an intentional manual link
   may remain a `Discrepancy` when its hostname differs from the service domain.
6. Return to the WHMCS service and retry the required module command.

If no auto-match is available, use the manual selector only after independently
confirming the service-to-guest relationship. A deliberate manual link may be
needed when a guest name differs from the service domain.

## Import an orphaned guest as a new service

Use this workflow only when the guest should have a new WHMCS service. If the
service already exists, link it instead so the client is not charged twice.

1. Select the Proxmox server and click **Analyze server**.
2. Filter the table to **Orphaned guests** and verify the VMID, guest name, node,
   type, resource limits, state, and available network data.
3. Click **Create WHMCS service** for the verified guest.
4. In **Service details**, search for and select the active client inside the
   client dropdown by client ID, email address, name, or company. Every result
   keeps all four identity fields visible and marks missing database values as
   `Not provided`. Clients
   missing both a name and company appear at the end of the initial list. Then
   select a compatible Proxmox product. Billing cycles are limited to those
   enabled for the product in the client's currency.
5. Select the billing treatment:
   - **Use product pricing** keeps the configured product price and normal
     billing workflow.
   - **Internal / Free** overrides the service price to zero, requires an
     audit reason, and converts the service to `Free Account`.
   - **Custom price override** requires an amount and audit reason. A zero
     override becomes `Free Account`; a positive override keeps the selected
     cycle and remains pending for billing review.
6. Select the billing cycle and payment method. A zero-cost import cannot use a
   cycle with a setup fee; select another cycle or a native free product.
7. At the end of **Service details**, review the optional primary IPv4 address. Static
   Proxmox guest configuration is preferred. For QEMU guests without a static
   address, the addon asks QEMU Guest Agent and accepts only IPv4 addresses on
   interfaces whose MAC address matches the guest configuration. One verified
   address is selected automatically. Choose one of multiple verified addresses,
   enter a usable IPv4 manually, or select **Complete network details later**.
8. Click **Continue to review**. The stepper advances to **Review and create**,
   where guest identity, VMID, client, product, payment method, billing,
   primary IPv4 source, and intended status are grouped for final verification.
9. Use **Back to service details** to amend any value without losing the form,
   or confirm the review checkbox and submit the import.
10. Open the service link in the success message and verify the client, product,
   server, hostname, billing cycle, status, VMID custom field, and module mapping.
   If an address was selected, also verify the dedicated and assigned IP fields.

The import takes its guest identity from the selected live Proxmox guest. On
submit, the server re-fetches discovered network candidates and accepts a
selected address only when it still belongs to that verified set. A manual
address is validated as a usable IPv4 value. The browser does not submit
editable hostname, guest type, subnet, gateway, or node values.

Deferring network details stores an empty address in the service and mapping
while preserving the verified VMID and guest type. Start, stop, suspend, resume,
terminate, and console actions continue to identify the guest from that mapping.
The Sync table marks the service as `Network pending` until an address is saved.

## Complete network details after import

1. Open **Sync**, select the Proxmox server, and analyze it.
2. Find the mapped service marked **Network pending**.
3. Click **Complete network details**. The addon retries static configuration
   and QEMU Guest Agent discovery for that guest.
4. Select a verified address or choose manual entry and provide a usable IPv4.
5. Confirm the service, VMID, guest, and address, then click **Save network
   details**.
6. Verify that the service dedicated IP, assigned IPs, and module mapping now
   contain the selected address and that the network-pending state is cleared.

The completion action revalidates the service, mapping, live guest, VMID
ownership, and address source. It rejects an address already assigned to another
active service and updates the WHMCS service and module mapping in one database
transaction. It never changes the guest network configuration in Proxmox.

WHMCS creates the order and service through the local API with invoices and
emails suppressed. The module create command is never run:

- Product-priced and positive custom-price orders remain `Pending` for an
  administrator to review and accept through the normal WHMCS order and billing
  workflow.
- A native free product, **Internal / Free**, or zero custom override is accepted
  automatically with module setup disabled. Its service becomes `Active` when
  the guest is running, `Suspended` when the guest is stopped, and remains
  `Pending` when the live state is unknown.
- Override reasons and the effective billing treatment are stored in the service
  notes and WHMCS activity log.

For every zero-cost import, the addon verifies that the order total is zero and
that WHMCS persisted the service as `Free Account` with zero first and recurring
amounts before it creates the guest mapping. A positive custom price is also
verified against the stored service amounts. Every import writes the guest VMID
to each exact `vmid` or `vpsid` product custom field and verifies the stored
value before creating the guest mapping. Failed verification triggers the normal
pending-order rollback path.

If the import fails before the order is accepted, the addon removes its mapping
and attempts to cancel and delete the pending order. If WHMCS cannot complete
that cleanup, the error names the order that requires administrator review. If
status alignment fails after a free order has been accepted, the accepted
service and mapping are retained and the service ID is reported for manual
review; submitting the import again is blocked by duplicate-VMID validation.

The legacy **Import existing guest** action in the Plans tab now opens the
orphaned-guest section in Sync. It no longer exposes the direct database import
form.

## Action behavior

### Link

Creates a row in `mod_pvewhmcs_vms` for the selected service and copies current
guest metadata from Proxmox. The addon fetches network configuration on the
server; it does not trust hidden IP, subnet, gateway, node, or guest-type values
from the browser.

### Sync from Proxmox

Replaces the stored VMID, guest type, and available network metadata with
the current live guest configuration. Review every discrepancy before using
this action.

### Unlink mapping

Deletes only the module database mapping for the service. It does not modify or
delete the Proxmox guest.

## Validation and safety controls

Every mapping change is submitted with WHMCS CSRF protection and is validated
again on the server. The addon verifies that:

- the selected WHMCS server is enabled and uses the `pvewhmcs` module;
- the service belongs to the selected server;
- the VMID exists on the selected live Proxmox cluster;
- another service on the same WHMCS server does not already own that VMID;
- an auto-match still agrees with the service's `vmid` custom field;
- an auto-match does not silently accept a hostname or known IP mismatch.

Link and synchronization writes use a database transaction so a failed action
does not leave a partial mapping. Unexpected errors are logged without exposing
credentials in the administration page.

Before a link or synchronization write, the addon derives a cluster identity
from the sorted live Proxmox node set and acquires a database advisory lock for
that cluster and VMID. It then rechecks VMID ownership across every WHMCS server
record that may point to the same cluster. Concurrent Sync requests therefore
cannot both claim the same cluster/VMID pair. Separate clusters can reuse a VMID
without creating a false conflict. If the addon cannot verify the cluster
identity of an existing owner, it fails closed and keeps the mapping unchanged.

## Verification checklist

Before deployment:

- Run `php -l modules/addons/pvewhmcs/pvewhmcs.php`.
- Check the diff for whitespace errors.
- Render the Sync page against a read-only test or production snapshot.
- Confirm that every mapping and import POST form contains a WHMCS token and
  selected server ID.
- Confirm that no submit button or option element is rendered outside its form
  or select element.
- Confirm that the import form has no preselected client, product, billing
  cycle, or payment method and cannot submit until the review checkbox is set.
- Confirm that native client dropdown search, compatible-product filtering,
  client-currency pricing, and billing-treatment fields render correctly.
- Confirm that the stepper starts on **Service details**, places the network
  controls after payment method, advances to the separate **Review and create**
  stage, and returns to editing without losing values.
- Confirm that the final review groups guest identity, client and product,
  billing and status, and cannot submit until the confirmation is selected.
- Confirm that zero and multiple detected IPv4 states default to **Complete
  network details later**, while one verified address is preselected. Confirm
  that deferred network details do not block review and that choosing manual
  entry requires a valid address. Confirm that changing the choice clears the
  review confirmation.
- Confirm that loopback, link-local, multicast, malformed, and reserved manual
  IPv4 values cannot be submitted.
- Confirm that override reason and custom price are required only for their
  applicable billing treatments, and that a zero override with a setup fee is
  rejected before submission.
- Exercise validation with an invalid or unavailable VMID and verify that order,
  service, and mapping counts do not change.
- Render **Complete network details** for zero, one, and multiple detected
  addresses. Confirm that it requires a verified selection or valid manual IPv4,
  rejects duplicate ownership, and cannot submit without its confirmation.

After deployment:

- Open the Sync tab in an authenticated WHMCS administration session.
- Confirm that the server selector, filters, search field, and table render.
- Search for a known service and confirm that the proposed action is correct.
- Open an orphaned guest's import review and confirm that its live identity,
  IPv4 candidates and source, compatible products, billing cycles, effective
  treatment, price, and intended status are correct.
- Do not execute a mapping action as part of a display-only smoke test.

## Deployment and rollback

Back up the existing addon file outside the web root before deployment. Preserve
its owner, group, permissions, and checksum. Upload the candidate outside the
web root, lint it, verify its checksum, then move it into place atomically.

Rollback when the Sync page fails to load, a form is malformed, or the expected
service cannot be analyzed. Restore the backup, restore the original owner and
permissions, run the PHP syntax check, and reload the Sync page. No database
migration is required by this feature.
