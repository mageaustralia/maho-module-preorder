# Phase 1 Smoke Checklist

Run after `cd docker && make reset && make up` against `http://localhost:8080`.

> **Note:** `MAGE_IS_DEVELOPER_MODE=1` must be set in `docker/docker-compose.yml` (added in this
> commit). Without it, Maho's composer autoloader does not add `/app/app/code/local` to its
> include paths and local-pool classes (including our Helper) will fail to load at runtime.

## Module load

- [x] Module XML file present in container — `ls /app/app/etc/modules/Mageaustralia_Preorder.xml`
  exits 0.
  _Auto-verified: file confirmed present in running container._

- [x] No PHP errors in `var/log/system.log` or `var/log/exception.log`.
  _Auto-verified: `system.log` contains only SameSite cookie warnings (expected for HTTP — the
  Docker stack runs plain HTTP, not HTTPS). `exception.log` does not exist (no exceptions raised)._

- [x] Cache flush works: `php /app/maho cache:flush` exits 0.
  _Auto-verified: command output "Caches flushed successfully!" with exit code 0._

- [x] Admin System > Configuration page renders without Fatal error.
  _Auto-verified: `curl http://localhost:8080/index.php/admin/system_config/` returns 200 with 0
  occurrences of "Fatal error". Previously broken without `MAGE_IS_DEVELOPER_MODE=1` — fixed as
  part of this checklist run._

## Install script

- [x] 4 product attributes exist in DB (`is_preorder`, `preorder_available_date`,
  `preorder_button_text`, `preorder_message`).
  _Auto-verified: MariaDB query on `eav_attribute` joined to `eav_entity_type` returned all 4 rows
  with correct backend types (int, datetime, varchar, text)._

- [x] `is_preorder` + `preorder_available_date` columns exist on `sales_flat_quote_item` and
  `sales_flat_order_item`.
  _Auto-verified: `INFORMATION_SCHEMA.COLUMNS` query returned all 4 expected rows (2 tables × 2
  columns)._

## Admin

- [x] `system.xml` is well-formed XML and contains exactly 6 fields.
  _Auto-verified: `xml.etree.ElementTree.parse()` passed; fields are `enabled`,
  `default_button_text`, `landing_page_enabled`, `send_reminder_7d`, `send_reminder_1d`,
  `ics_attach`._

- [x] `adminhtml.xml` is well-formed XML.
  _Auto-verified: `xml.etree.ElementTree.parse()` passed._

- [x] `config.xml` is well-formed XML.
  _Auto-verified: `xml.etree.ElementTree.parse()` passed._

- [ ] MANUAL — System > Configuration > Catalog > Preorder shows all 6 fields and saves.
  _What to look for: Navigate to Admin > System > Configuration, click "Preorder" under the
  Catalog tab. You should see a "General" fieldset with 6 fields: Module Enabled, Default
  Pre-order Button Text, Enable /preorder Landing Page, Send 7-Day-Before Reminder Email, Send
  1-Day-Before Reminder Email, Attach .ics Calendar File to Order Confirmation. Change a value
  and click Save Config — should show green success message, no errors._

- [ ] MANUAL — Catalog > Manage Products > edit any product → Preorder tab/group shows 4 attributes.
  _What to look for: Edit any product in admin. Under the product attribute tabs on the left you
  should see a "Preorder" group. Inside it: Is Preorder (Yes/No dropdown), Preorder Available
  Date (date picker), Preorder Button Text (text input), Preorder Message (textarea)._

- [ ] MANUAL — Setting Is Preorder = Yes + a date saves without error.
  _What to look for: On a product, set Is Preorder = Yes, pick a date, save. No PHP errors, the
  value persists when you reload the product edit page._

## Frontend (default Maho theme)

- [x] All 3 frontend templates exist and are non-empty.
  _Auto-verified: `badge.phtml` (465 B), `button.phtml` (765 B), `cart/item.phtml` (418 B) all
  present at `src/app/design/frontend/base/default/template/mageaustralia/preorder/`._

- [x] Layout XML is well-formed and wires badge + button into PDP and category list handles.
  _Auto-verified: `layout/mageaustralia_preorder.xml` parses cleanly. It inserts
  `mageaustralia_preorder/badge` before `product.info` on `catalog_product_view`, adds
  `mageaustralia_preorder/button` inside `product.info.addtocart`, and references the badge block
  on `catalog_category_default`._

- [ ] MANUAL — PDP for a preorder product shows the **Pre-order** badge above title.
  _What to do: Create (or edit) a simple product, set Is Preorder = Yes, ensure it is enabled and
  in stock. View its PDP at `http://localhost:8080/<product-url-key>.html`. Look for a
  `.mageaustralia-preorder-badge` element containing the text "Pre-order" above the product
  title._

- [ ] MANUAL — PDP for a preorder product shows the **Pre-order now** button instead of Add to Cart.
  _What to look for: On the same preorder product PDP, the `.mageaustralia-preorder-cta` button
  should appear in the add-to-cart area with the default text "Pre-order now" (or the custom
  `preorder_button_text` if set). The standard "Add to Cart" button may still appear as well since
  the layout adds the block alongside rather than replacing it — Phase 2 should conditionally hide
  the native button via JS or CSS._

- [ ] MANUAL — PDP for a normal product is unchanged.
  _What to look for: Edit a product with Is Preorder = No (default). Its PDP should show no badge
  and no preorder button. The normal "Add to Cart" experience is intact._

- [ ] MANUAL — Category page shows the preorder badge on the preorder product card.
  _What to look for: On a category listing page that includes the preorder product, each product
  card should render the badge. Note: the layout XML uses `setChild` action rather than inline
  block declaration — this may need a template `echo $this->getChildHtml('preorder.badge')` call
  in the product-list phtml to actually output it. If the badge doesn't appear, check that the
  category product list template calls that child block._

- [ ] MANUAL — Cart page shows "Pre-order — ships ~XXX" under the line item.
  _What to do: Add the preorder product to cart. On the cart page, the line item should have a
  `.mageaustralia-preorder-cart-label` element below it reading "Pre-order - ships ~Apr 1, 2025"
  (or whatever date was set). The label is injected via the `core_block_abstract_to_html_after`
  observer when the rendered block is a `Mage_Checkout_Block_Cart_Item_Renderer`._

- [ ] MANUAL — Order placed via offline payment results in `sales_flat_order_item.is_preorder = 1`.
  _What to do: Complete a checkout with the preorder product using Check/Money Order (offline)
  payment. After the order is placed, verify in the DB:_
  ```sql
  SELECT is_preorder, preorder_available_date
  FROM sales_flat_order_item
  WHERE order_id = (SELECT entity_id FROM sales_flat_order ORDER BY entity_id DESC LIMIT 1);
  ```
  _Expected: `is_preorder = 1` and `preorder_available_date` matches the product's configured date._

---

## Auto-verification summary

| Section       | Auto-verified | Manual required |
|---------------|:-------------:|:---------------:|
| Module load   | 4/4           | 0               |
| Install script| 2/2           | 0               |
| Admin         | 3/6           | 3               |
| Frontend      | 2/7           | 5               |
| **Total**     | **11/19**     | **8**           |

## Known issues discovered during this checklist run

1. **`MAGE_IS_DEVELOPER_MODE=1` required** — Without this env var, Maho's composer autoloader
   (in production mode) does not call `Maho::updateComposerAutoloader()` and therefore does not
   add `/app/app/code/local` to the PHP include path. All local-pool classes (our Helper, Blocks,
   Observer, Model) silently fail to load, and the admin System > Configuration page throws a
   fatal error. **Fixed**: `MAGE_IS_DEVELOPER_MODE=1` added to `docker/docker-compose.yml`.

2. **Admin password is not stable across container recreation** — The `maho install` command sets
   `admin/admin` password to `Admin1234_local`, but if the DB volume is retained across a
   container recreation (e.g. `docker compose up -d` without `-v`), the previously-set password
   hash remains. The `make reset` target (`docker compose down -v`) cleanly wipes this. Always
   run `make reset && make up` for a clean smoke test environment.
