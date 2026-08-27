# Product

## Register

product

## Users

WHMCS administrators and hosting operations staff who manage existing Proxmox
virtual machines and containers. They use the addon while reconciling live
infrastructure with client services and need to make high-impact changes without
guessing which guest, client, product, or cluster a record belongs to.

## Product Purpose

Proxmox VE for WHMCS provisions and manages Proxmox guests through WHMCS. Its
administration workflows must make the relationship between a live guest and a
WHMCS service explicit, validated, and auditable. Success means an administrator
can link an existing service or import an orphaned guest without creating a
duplicate mapping, assigning the wrong client or product, or leaving partial
database records after a failure.

## Brand Personality

Precise, calm, and operational. The interface should feel native to the WHMCS
administration area and communicate risk through clear labels and evidence, not
decoration.

## Anti-references

- A separate visual system that competes with the WHMCS administration theme.
- Long unsearchable select menus with arbitrary defaults.
- Forms that require administrators to retype data already available from
  Proxmox.
- One-click write actions that hide billing, service status, or mapping effects.
- Decorative dashboards, excessive motion, or unfamiliar custom controls.

## Design Principles

1. Show the live guest evidence before offering a write action.
2. Prefer progressive disclosure and a review step for consequential changes.
3. Default to no selection and fail closed when ownership is ambiguous.
4. Keep related reconciliation work in the Sync surface.
5. Make partial success impossible and leave an audit trail for every write.

## Accessibility & Inclusion

Target WCAG 2.1 AA within the constraints of the host WHMCS administration
theme. All controls must have programmatic labels, keyboard-visible focus,
meaningful button text, and status messages that do not rely on color alone.
