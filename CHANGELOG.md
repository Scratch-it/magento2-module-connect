# Changelog

All notable changes to this module are recorded here. Versions follow [Semantic Versioning](https://semver.org/).

## [1.0.0] - Unreleased

### Added

- The **Zembula** integration (System > Extensions > Integrations). Activating it sends the integration's credentials to Zembula and opens Zembula's sign-in window.
- Permissions: Products, Categories, Orders > Actions > View, Shipments, All Stores, Stocks and Cart Price Rules.
- `extension_attributes.zembula_parent_sku` on order items in `GET /V1/orders` and `GET /V1/orders/{id}`.
- `extension_attributes.zembula_parent_skus` on simple and virtual products in `GET /V1/products` and `GET /V1/products/{sku}`.
