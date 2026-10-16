# Zembula Connect for Magento 2

`zembula/module-connect` connects a Magento Open Source or Adobe Commerce store to Zembula.

## What it does

- Adds a **Zembula** integration under **System > Extensions > Integrations**. When you activate it, Magento sends the integration's API credentials to Zembula, and you sign in to Zembula to choose the account the store connects to.
- Adds two fields to Magento's REST API responses so Zembula can match variants to their configurable product (see [REST API fields](#rest-api-fields)).

The module has no settings, Admin pages, cron jobs or database tables, and it makes no outbound requests of its own. The only request to Zembula is the one Magento makes when you click Activate. The REST fields apply to REST API calls only; the storefront, checkout and Admin are unchanged.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.4 or later, on your own servers or on Adobe Commerce Cloud. Adobe Commerce as a Cloud Service is not supported.
- PHP 8.1 to 8.5, within the range your Magento version supports.
- A Zembula account. You sign in to it while activating.

## Install

### With Composer (recommended)

```bash
composer require zembula/module-connect
bin/magento module:enable Zembula_Connect
bin/magento setup:upgrade
```

In production mode, also run your usual deployment steps:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

On Adobe Commerce Cloud, run `composer require` and `module:enable` in your project, then commit `composer.json`, `composer.lock` and `app/etc/config.php` and push.

### From a zip

1. Copy the module's files into `app/code/Zembula/Connect`, so that `app/code/Zembula/Connect/registration.php` exists.
2. Run the same `module:enable`, `setup:upgrade` and production commands as above.

A zip install doesn't update itself. To update it, replace the folder and run the commands again.

## Connect your store

Magento opens the Zembula sign-in in a pop-up window, so allow pop-ups for your Magento Admin first.

1. In the Admin, go to **System > Extensions > Integrations**.
2. Find **Zembula** (status *Inactive*) and click **Activate**.
3. Review the permissions and click **Allow**.
4. A Zembula window opens. Sign in, choose the Zembula account and confirm the store.

When the window closes, the integration shows as *Active*.

## What Zembula can access

| Magento permission | What Zembula does with it |
|---|---|
| Catalog > Inventory > Products | Reads products and variants |
| Catalog > Inventory > Categories | Reads categories |
| Sales > Operations > Orders > Actions > View | Reads orders |
| Sales > Operations > Shipments | Reads shipments and tracking |
| Stores > Settings > All Stores | Reads websites, stores and store views |
| Stores > Inventory > Stocks | Reads stocks (stores using Magento Inventory only) |
| Marketing > Promotions > Cart Price Rules | Reads cart price rules, and **creates cart price rules and coupon codes** |

Magento adds each permission's parent entries itself. Orders include the customer details stored on each order. The integration has no access to Customers (customer accounts) or to inventory sources.

Magento has no read-only permissions. Through the API, the Products, Categories, Shipments and Cart Price Rules permissions also allow changes. The only data Zembula writes is cart price rules and coupon codes.

## REST API fields

| Field | Returned by | Value |
|---|---|---|
| `extension_attributes.zembula_parent_sku` (string) | `GET /V1/orders`, `GET /V1/orders/{id}` | On a configurable product's order line and its child line: the configurable product's current SKU. Absent on other lines, and when that product has been deleted. |
| `extension_attributes.zembula_parent_skus` (string[]) | `GET /V1/products`, `GET /V1/products/{sku}` | On simple and virtual products: the SKUs of the configurable products they belong to. Absent when there are none. |

Each REST request adds at most one database query. The fields are read-only.

## Uninstall

Removing the module does **not** revoke Zembula's access.

1. In **System > Extensions > Integrations**, delete the **Zembula** integration. This revokes its access immediately.
2. Remove the module:

   ```bash
   bin/magento module:disable Zembula_Connect
   composer remove zembula/module-connect
   bin/magento setup:upgrade
   ```

   For a zip install, delete `app/code/Zembula/Connect` instead of running `composer remove`. In production mode, run `setup:di:compile` and `setup:static-content:deploy` again.

If you delete the integration but keep the module, the next `setup:upgrade` adds the integration back as *Inactive*. It has no access until someone activates it.

## License

MIT. See [LICENSE](LICENSE).
