# Mageaustralia_Preorder - Design Spec

**Status:** Draft v1 · 2026-04-22
**Repo:** `~/Development/maho-modules/maho-module-preorder/`
**Reference policy:** `~/Development/mageaustralia/CLAUDE.md`

---

## 1. Purpose

Pre-order workflow for Maho. Lets merchants accept orders for products that
aren't yet in stock, with a known availability/dispatch date, the appropriate
labelling at every step (PDP, cart, checkout, order, emails), and a
public-facing landing page that lists every preorder product with structured
data for SEO.

Replaces `Amasty_Preorder` (M1) / Amasty Pre-Order, Mirasvit Advance Order,
Magestore Pre-Order (all M2). Cleaner scope, modern stack, ships an SEO
surface no incumbent does.

## 2. Distinctly Better

> Mandatory section per `CLAUDE.md` "Distinctly Better" rule.

Two differentiators, one per tier:

### OSS tier (`Mageaustralia_Preorder`, Apache 2.0)
**Auto-generated `/preorder` SEO landing page.**

Every product flagged as preorder is automatically listed at a public
`/preorder` URL. Includes:

- `schema.org/Product` JSON-LD with `availability: PreOrder` and the
  `availabilityStarts` date.
- Open Graph + Twitter card meta tags per product.
- Inclusion in the XML sitemap (priority 0.7, changefreq daily until release).
- Crawlable, indexable, ranks for "preorder [product]" queries.

**Why this beats the incumbents:** Amasty, Mirasvit, and Magestore all require
the merchant to manually build a "Coming Soon" or "Preorder" CMS page or
category. We give it for free, with structured data, with sitemap inclusion,
and with marketing-team-friendly URLs out of the box.

### Pro tier (`Mageaustralia_PreorderPro`, commercial)
**AI demand forecast.**

Admin dashboard widget shows "based on N preorders in M days at current
velocity, projected dispatch-day demand is X units (±Y)." Powered by `Maho_Ai`
(uses configured Claude/OpenAI credentials). Merchant can act on this for
inventory planning, supplier orders, marketing spend.

**Why this beats the incumbents:** No M2 preorder module forecasts anything - 
they all just track the count. AI demand forecasting is a feature inventory
planners actively want.

## 3. Compatibility

- **PHP:** 8.3+
- **Maho:** latest stable (target v25.x)
- **Database:** MySQL/MariaDB (default), SQLite, PostgreSQL - module ships
  using only portable schema/query patterns (see §10).
- **Frontend:** works with default Maho theme.
- **Maho Storefront ready** - emits preorder fields via Maho API Platform;
  ships a Stimulus controller + DaisyUI components for headless setups.

## 4. Free / OSS Tier Feature Inventory

### 4.1 Product attributes
- `is_preorder` (bool, store-scoped) - flags a product as preorder.
- `preorder_available_date` (date, store-scoped) - when the product is
  expected to ship. Optional; absence means "no ETA".
- `preorder_button_text` (varchar 64, store-scoped) - overrides the default
  "Pre-order now" button label.
- `preorder_message` (text, store-scoped) - optional message rendered on PDP
  (e.g. "Limited first-batch quantity, expected mid-May").

### 4.2 Frontend behaviour (default Maho theme)
- PDP: "Add to Cart" button replaced with "Pre-order now" (or custom label)
  styled differently. Availability date shown if set. Stock check bypassed.
- Category list: preorder badge on product card.
- Cart: line item shows "Pre-order - ships ~Apr 30, 2026".
- Checkout: same line label.
- Order confirmation page: same line label + persistent badge.

### 4.3 `/preorder` landing page
- Front controller: `Mageaustralia_Preorder_IndexController::indexAction()`.
- Route registered in `config.xml` under `<frontend><routers>`.
- Renders a product collection filtered by `is_preorder=1` AND
  (`preorder_available_date IS NULL OR preorder_available_date >= today`).
- Layout XML adds breadcrumbs, page title, meta description.
- Layout block emits JSON-LD per product with `schema.org/Product` and
  `availability: PreOrder`.
- Sortable by availability date (earliest first by default).
- Pagination via standard `Mage_Catalog_Block_Product_List` patterns.

### 4.4 Sitemap integration

> **DEFERRED to v0.2.0.** Investigation (2026-04-22) found no `dispatchEvent`
> calls in `Mage/Sitemap/` — Maho's sitemap model does not emit observable
> events during `generateXml()`. Extension requires either a rewrite of
> `Mage_Sitemap_Model_Sitemap` or a cron-based post-process approach. Neither
> fits the clean OSS contract. Will revisit when Maho adds a hook or we
> introduce a sitemap rewrite block.

- Observer on `sitemap_save_after` (or equivalent) appends preorder URLs.
- `<changefreq>daily</changefreq>`, `<priority>0.7</priority>`,
  `<lastmod>` from `preorder_available_date`.

### 4.5 Email lifecycle
Templates added to `app/locale/en_US/template/email/preorder/`:

- `confirmation.html` - appended to standard order confirmation; mentions
  preorder + availability date.
- `reminder_7d.html` - sent 7 days before `preorder_available_date`.
- `reminder_1d.html` - sent 1 day before.

Cron job `mageaustralia_preorder/observer::sendReminders` runs daily; selects
qualifying orders and dispatches.

### 4.6 `.ics` calendar export
- On order confirmation, attach an iCalendar `.ics` event for each preorder
  line item.
- Event title: "Pre-order ships: {product_name}".
- Event date: `preorder_available_date`.
- All-day event (no time).
- Standard iCalendar format (RFC 5545).

### 4.7 Maho Storefront support
- Maho API Platform DTO extension: product DTOs include
  `is_preorder`, `preorder_available_date`, `preorder_button_text`,
  `preorder_message`.
- Storefront component (separate small JS package, distributed alongside the
  module): Stimulus controller `preorder-card-controller` with DaisyUI badge,
  countdown timer, CTA button.

### 4.8 Admin
- Product edit form: new fieldset "Preorder" with the four attributes above.
- System > Configuration > Catalog > Preorder: global defaults (default button
  text, default message, enable/disable email reminders).
- Order grid: filterable column "Preorder" (yes/no).
- No dedicated dashboard in OSS tier.

## 5. Pro Tier Feature Inventory

`Mageaustralia_PreorderPro` is a separate module that depends on
`Mageaustralia_Preorder`. Distributed as a private composer package via our
commerce site.

### 5.1 Waitlist / notify-when-available
- Customer-facing "Notify me" form on PDP for non-preorder out-of-stock
  products.
- Table `mageaustralia_preorder_waitlist` (product_id, store_id, customer_email,
  customer_id nullable, added_at, notified_at, unsubscribe_token).
- Email triggered when `is_preorder` flips to false AND stock returns, or when
  a preorder product passes its availability date.

### 5.2 Inventory rule: deduct-at-ship
- Observer on `sales_order_shipment_save_after` decrements stock at shipment
  time, not order time, for preorder line items.
- Configurable per-product or global.

### 5.3 Deposits / partial payment
- Quote totals collector adds a "Deposit" line for preorder items.
- Configurable percentage (default 20%).
- Customer charged the deposit at order; balance is invoiced on dispatch via
  `sales_order_invoice_save_after` workflow.
- Initial integration with our `Mageaustralia_Stripe` module for the dispatch
  invoice; other payment methods supported via standard Maho invoice flow.

### 5.4 Preorder-specific promotions
- Extends Maho's catalog/sales rule conditions with "Is preorder" attribute.
- Lets merchants set "10% off all preorders" or "Free shipping on
  preorders" rules.

### 5.5 Admin dashboard
- New menu item: Sales > Preorder Dashboard.
- Cards: total preorder value, active preorder SKUs, units sold this period,
  upcoming dispatch dates (calendar view).
- AI demand forecast widget per active preorder SKU (see §2).
- Waitlist subscriber count per product.

### 5.6 AI demand forecast (the Pro differentiator)
- Daily cron job collects preorder velocity data per SKU (orders per day,
  trend, seasonality if enough history).
- Sends a structured prompt to `Maho_Ai` (Claude or OpenAI per merchant's
  config) requesting projected total preorder count by `preorder_available_date`.
- Result cached per SKU per day; rendered as forecast card on dashboard.
- Includes confidence interval and "what changed since yesterday".

## 6. Architecture

### 6.1 Module structure (OSS tier)
```
src/
├── app/
│ ├── etc/
│ │ └── modules/
│ │ └── Mageaustralia_Preorder.xml
│ ├── locale/
│ │ ├── en_US/
│ │ │ ├── Mageaustralia_Preorder.csv
│ │ │ └── template/
│ │ │ └── email/
│ │ │ └── preorder/
│ │ │ ├── confirmation.html
│ │ │ ├── reminder_7d.html
│ │ │ └── reminder_1d.html
│ │ └── ...other locales as contributed...
│ ├── design/
│ │ └── frontend/
│ │ └── base/
│ │ └── default/
│ │ ├── layout/
│ │ │ └── mageaustralia_preorder.xml
│ │ └── template/
│ │ └── mageaustralia/
│ │ └── preorder/
│ │ ├── badge.phtml
│ │ ├── button.phtml
│ │ ├── landing/
│ │ │ ├── list.phtml
│ │ │ └── jsonld.phtml
│ │ └── cart/
│ │ └── item.phtml
│ └── code/
│ └── local/
│ └── Mageaustralia/
│ └── Preorder/
│ ├── Block/
│ │ ├── Badge.php
│ │ ├── Button.php
│ │ ├── JsonLd.php
│ │ └── Landing/
│ │ └── ProductList.php
│ ├── Helper/
│ │ ├── Data.php
│ │ └── Calendar.php # .ics generation
│ ├── Model/
│ │ ├── Observer.php # event handlers
│ │ ├── Cron/
│ │ │ └── Reminders.php
│ │ ├── Email/
│ │ │ └── Sender.php
│ │ └── Sitemap/
│ │ └── Provider.php
│ ├── controllers/
│ │ └── IndexController.php
│ ├── etc/
│ │ ├── adminhtml.xml
│ │ ├── config.xml
│ │ └── system.xml
│ └── sql/
│ └── mageaustralia_preorder_setup/
│ └── install-0.1.0.php
└── README.md (top-level, not under src/)
```

### 6.2 Key classes and their one-line purpose

| Class | Purpose |
|---|---|
| `Helper_Data::isPreorder($product)` | Authoritative "is this product preorder" check. |
| `Helper_Data::getAvailableDate($product)` | Returns date or `null`. |
| `Helper_Data::getButtonText($product)` | Returns label, falling back to system config. |
| `Helper_Calendar::generateIcs($order)` | Builds .ics body for a preorder order. |
| `Block_Badge` | Renders the "Pre-order" badge wherever invoked. |
| `Block_Button` | Replaces "Add to Cart" on preorder PDPs. |
| `Block_JsonLd` | Emits JSON-LD on the `/preorder` page and on PDPs. |
| `Block_Landing_ProductList` | Builds the collection for `/preorder`. |
| `IndexController::indexAction()` | Renders `/preorder`. |
| `Model_Observer::onProductView()` | Adjusts the buybox block when product is preorder. |
| `Model_Observer::onCartItemAdd()` | Bypasses stock check for preorder items. |
| `Model_Observer::onOrderEmailQueue()` | Attaches `.ics` to confirmation email. |
| `Model_Cron_Reminders::run()` | Daily - sends 7d / 1d reminders. |
| `Model_Email_Sender::sendReminder($order, $variant)` | Reusable email send. |
| `Model_Sitemap_Provider::collect()` | Hooks into sitemap generation. |

## 7. Data model

### 7.1 Product attributes (added via install script)

| Attribute | Type | Scope | Required |
|---|---|---|---|
| `is_preorder` | int (bool) | store | no |
| `preorder_available_date` | datetime | store | no |
| `preorder_button_text` | varchar(64) | store | no |
| `preorder_message` | text | store | no |

Added via standard Maho EAV install script
(`Mage_Eav_Model_Entity_Setup::addAttribute()`) - portable across MySQL,
SQLite, PostgreSQL because Maho's EAV layer abstracts the storage.

### 7.2 Quote / Order item flags (added via install script)

| Column | On table | Type |
|---|---|---|
| `is_preorder` | `sales_flat_quote_item`, `sales_flat_order_item` | smallint default 0 |
| `preorder_available_date` | same | datetime nullable |

Added via `$installer->getConnection()->addColumn()` - portable.

### 7.3 No new tables in OSS tier
The OSS tier persists only via product attributes and quote/order item columns.
Pro tier adds `mageaustralia_preorder_waitlist` and a dashboard cache table.

## 8. Frontend / Storefront integration

### 8.1 Default Maho theme
- Layout XML inserts the badge + button template into PDP, category list,
  cart, checkout, order confirmation.
- All templates use `escapeHtml()` for any user-supplied strings.
- No jQuery dependency; small Stimulus controller for the countdown timer
  and the `/preorder` page filter dropdown.

### 8.2 Maho Storefront (headless)
**End-user experience: zero extra install steps.** Per the portfolio-wide
"Distribution & Storefront Components" rule in `CLAUDE.md`:

- Maho API Platform DTO extension via the convention-based discovery pattern
  (per the `maho-api-module-migration` skill). Adds a
  `Provider/PreorderProvider.php` that decorates the product DTO with
  `is_preorder`, `preorder_available_date`, `preorder_button_text`,
  `preorder_message`.
- The storefront UI components - Stimulus `preorder-card-controller`, DaisyUI
  badge / button / countdown variants - live in the **upstream
  `maho-storefront` repo** under
  `themes/_shared/components/preorder/`. They render conditionally based on
  the presence of `is_preorder` in the product DTO. If the field is absent
  (module not installed, or product not flagged), the components are an
  invisible no-op.
- The merchant installs the PHP module via composer; if they're on Maho
  Storefront, the next deploy picks up the new API fields and the
  already-shipped components light up. **No npm package, no manual import,
  no second install step.**
- We carry the cost: the components live in a repo we control and PR into the
  upstream as part of the module's release.

### 8.3 The `/preorder` landing page

URL: `/preorder` (configurable suffix, but default is the unsuffixed root).
Template:

```
[Page H1: Pre-order Now]
[Optional CMS block above the list - admin-editable]

[Filters: Sort by availability ↑↓ | Category dropdown]

[Product grid - same card component as category pages, but with a "Ships ~MMM DD" overlay and a "Pre-order" badge]

[Pagination]

[JSON-LD block - one Product per item, all wrapped in a single ItemList]
```

Performance: collection limited to 36 items per page, eager-loads
`preorder_available_date`. No N+1.

## 9. Email templates

Three templates per language. Variables available:

| Template | Triggered by | Key variables |
|---|---|---|
| `preorder/confirmation.html` | Standard `sales_order_place_after` (appended to existing confirmation) | `{{var order}}`, `{{var preorder_items}}`, `{{var earliest_dispatch_date}}` |
| `preorder/reminder_7d.html` | Cron `mageaustralia_preorder_reminders`, 7 days before | `{{var customer.firstname}}`, `{{var product.name}}`, `{{var dispatch_date}}` |
| `preorder/reminder_1d.html` | Same cron, 1 day before | Same |

All templates respect store scope and locale. Configurable on/off per template
in System > Config.

## 10. Database portability

This module **only** uses Maho-portable patterns. No raw SQL anywhere.

| Allowed | Forbidden |
|---|---|
| `$installer->getConnection()->newTable()` | `CREATE TABLE ...` strings |
| `$installer->getConnection()->addColumn()` | `ALTER TABLE ... ADD COLUMN` strings |
| `Mage_Eav_Model_Entity_Setup::addAttribute()` | direct EAV table inserts |
| `$collection->addFieldToFilter()` | hand-rolled `WHERE` clauses |
| `$select->where('? = column', $value)` parameterised | string-concatenated SQL |
| PHP-side aggregation for any "GROUP_CONCAT/FIND_IN_SET" need | MySQL-only functions |
| `varchar`, `int`, `text`, `datetime`, `decimal` columns | `ENUM`, `MEDIUMTEXT`, MySQL-only types |

CI matrix runs the test suite against MySQL, SQLite, and PostgreSQL Maho
installs to keep the rule honest.

## 11. Testing strategy

### 11.1 Unit tests
- `Helper_Data` and `Helper_Calendar` are pure-function-ish - straight PHPUnit.
- Coverage target: 80%+ on Helper, Block, Model.

### 11.2 Integration tests
- One Maho test environment per database (MySQL, SQLite, PostgreSQL) via Docker.
- Scenarios:
  - Create preorder product → renders correct PDP button.
  - Add preorder product to cart → quote item carries flag.
  - Place order → order item carries flag, .ics attached to email.
  - Visit `/preorder` → product appears with JSON-LD.
  - Pass availability date → product no longer appears on `/preorder`.
  - Cron triggers reminder emails at correct intervals.

### 11.3 Storefront test
- Spin up Maho Storefront pointed at the test backend, navigate to a
  preorder product, assert badge + countdown render, assert API DTO contains
  preorder fields.

### 11.4 No mocking the database
Per `superpowers:test-driven-development` and our prior incident lessons - 
integration tests hit real DB containers, not mocks.

## 12. Pricing & Licensing

### OSS tier - `Mageaustralia_Preorder`
- License: **Apache 2.0**
- Distribution: GitHub (`mageaustralia/maho-module-preorder`), Packagist
- Price: **Free**

### Pro tier - `Mageaustralia_PreorderPro`
- License: Proprietary EULA (separate file in private repo)
- Distribution: private composer repo (auth required)
- **Single-store: $119**
- **Unlimited: $249**
- Year 1 updates included; Year 2+ maintenance: $36/yr single, $75/yr unlimited
- Calculation reference: median M2 incumbent $232 → 50-65% (display/catalog
  category) = $116-151 → friction-free credit-card price $119

## 13. Viability assessment

| Criterion | Verdict |
|---|---|
| Market demand | High - every catalog hits preorder eventually; M1 → Maho migrators arrive needing this. |
| Competitive pressure | None on Maho yet. Three M2 incumbents (Amasty, Mirasvit, Magestore) all $199-299. |
| Build cost | Low-medium - OSS tier ~800-1.2k LOC, Pro tier +1.5-3k LOC. ~2-3 weeks for both tiers. |
| Maintenance burden | Low - minimal external integrations (only `Maho_Ai` in Pro). |
| Funnel value | High - OSS tier indexable via `/preorder` SEO is its own marketing channel. |
| **Go/no-go** | **Go.** Highest confidence module in the first batch. |

## 14. Phased delivery

Suggested order of work, each phase shippable on its own:

1. **Phase 1 - OSS core (~5-7 days)**
  - Module scaffold, install script, attributes, helpers.
  - PDP button + cart/order labelling.
  - System config for default text + reminder toggles.

2. **Phase 2 - `/preorder` landing page + JSON-LD (~2-3 days)**
  - Front controller, layout, block, sitemap observer.
  - This is the OSS-tier differentiator - must be excellent.

3. **Phase 3 - Email lifecycle + .ics (~2 days)**
  - Templates + cron + `.ics` helper.

4. **Phase 4 - Storefront support (~2-3 days)**
  - DTO provider, npm storefront package.

5. **Phase 5 - OSS tier release** - README with screenshots, CHANGELOG, GitHub release, Packagist publish.

6. **Phase 6 - Pro tier scaffold (separate repo, private) (~1 day)**

7. **Phase 7 - Pro waitlist + deduct-at-ship (~2-3 days)**

8. **Phase 8 - Pro deposits + promotions (~2-3 days)**

9. **Phase 9 - Pro dashboard + AI forecast (~3-4 days)**

10. **Phase 10 - Pro tier release** - private package, sales-page copy, EULA.

Total: ~3-4 weeks of focused work for both tiers.

## 15. Open questions / deferred decisions

- **Multi-source inventory (MSI)**: Maho doesn't have MSI. If/when added,
  preorder availability per source becomes relevant. Defer.
- **B2B-style "request quote" preorders**: out of scope. May warrant a
  separate `Mageaustralia_RequestQuote` module.
- **Subscriptions overlap**: a recurring preorder ("preorder every month")
  is a subscriptions feature - out of scope for this module.
- **Pre-order analytics deeper than the Pro dashboard** (e.g. integration
  with GA4 enhanced ecommerce events for preorder-specific funnels): defer
  to Pro v2.

## 16. Out of scope (anti-spec)

Explicitly **not** doing:

- Custom payment gateways for deposits (use existing Stripe / Maho payment
  modules).
- A dedicated "Preorder Customer Group" - handled by existing Maho customer
  segmentation.
- Multilingual `/preorder` URL slugs (will be `/preorder` per store; localisation handled by store-scoped configuration).
- Preorder-specific shipping methods.
- Preorder reviews / Q&A on the landing page (out of scope; standard product
  blocks render under the product detail anyway).

## 17. Decision log

| Date | Decision |
|---|---|
| 2026-04-22 | OSS differentiator = auto `/preorder` SEO landing page (selected over headless-API-first, calendar export, lifecycle emails - those are bundled but not the headline). |
| 2026-04-22 | Pro differentiator = AI demand forecast (uses `Maho_Ai`). |
| 2026-04-22 | Pricing locked: $119 single / $249 unlimited Pro tier; OSS tier free. |
| 2026-04-22 | License: Apache 2.0 (OSS), proprietary EULA (Pro). |
| 2026-04-22 | OSS tier explicitly carries calendar export + lifecycle emails (originally considered Pro-only) to make the free tier strong enough to be a real funnel. |
| 2026-04-22 | **Distribution: one `composer require` = everything.** No separate npm package. Storefront components live in upstream `maho-storefront` repo under `themes/_shared/components/preorder/`, activate conditionally based on the API DTO's `is_preorder` field. End user effort: zero beyond the composer install. |
