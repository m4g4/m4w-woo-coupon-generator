# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.1] - 2026-10-08

### Fixed
- Copy button icon not in center.

## [1.3.0] - 2026-10-08

### Changed
- Rebranded plugin to **M4W Woo Coupon Generator** (`m4w-woo-coupon-generator`).
- Plugin folder, main file, and asset handles renamed to the new slug.
- Child coupon description now includes the parent coupon name in quotes: `Generated from "NAME"` (MailPoet) and `Generated from NAME for EMAIL` (FluentCRM).
- Parent link meta key renamed from `_ar_parent_coupon_id` to `_m4w_wcg_parent_coupon_id`. The legacy key is still honored as a fallback so children created by older versions remain removable.
- Admin panel HTML ids / CSS selectors renamed from `ar_coupon*` to `m4w_wcg*`.

### Fixed
- "Remove All Child Coupons" reported `Removed undefined child coupon(s)` because JS read `result.count` instead of `result.data.count`.
- Removal found no children for coupons generated before the parent-link meta existed. Children are now auto-linked by parsing `Generated from <parent>` from their description, so legacy children are also removed correctly.

## [1.2.0] - 2026-08-27

### Added
- "Remove All Child Coupons" button in the coupon panel, with an AJAX endpoint that deletes all children of a parent coupon.

### Changed
- Settings methods made public.

## [1.1.0] - 2026-04-08

### Changed
- Clipboard copy button improvements.
- Removed debug logging.

## [1.0.0] - 2025-11-21

### Added
- First release.
- MailPoet custom shortcode support (`[custom:coupon_BASECOUPON]`).
- FluentCRM smartcode support (`{{generate_coupon:BASECOUPON}}`).
- One-time coupon cloning from a template coupon, with per-contact tracking.
- Admin meta box with shortcode/smartcode copy buttons and preview detection.