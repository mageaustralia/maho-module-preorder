# Mageaustralia_Preorder OSS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `Mageaustralia_Preorder` (Apache 2.0 OSS tier) - a Maho extension that lets merchants accept pre-orders, with the distinguishing auto-generated `/preorder` SEO landing page, `.ics` calendar exports, and lifecycle reminder emails.

**Architecture:** Standard Maho module under `src/app/code/local/Mageaustralia/Preorder/`. Product attributes added via EAV install script. Quote/order item columns added via portable `addColumn`. Frontend behaviour driven by layout XML + observer hooks. The `/preorder` landing page is its own front-controller route. Storefront support ships as DTO extensions for Maho API Platform; storefront UI components live in upstream `maho-storefront` and activate based on API data presence (per portfolio `CLAUDE.md` rule: one `composer require` = everything).

**Tech Stack:** PHP 8.3+, Maho ≥26.3, Composer (`type: maho-module`), PHPStan (level 5 → 7 ratchet), PHPUnit for testable units, Docker'd Maho install for integration smoke tests, MySQL/MariaDB primary + SQLite + PostgreSQL portability.

**Reference docs:**
- Spec: `~/Development/maho-modules/maho-module-preorder/docs/spec.md`
- Portfolio policy: `~/Development/mageaustralia/CLAUDE.md`
- Module standards skill: `maho-module-standards`
- API migration skill: `maho-api-module-migration`

**Scope of this plan:** Phases 0-5 (OSS tier). Phase 0 is repo scaffold; Phase 1 is OSS core (detailed); Phases 2-5 outlined. Pro tier (Phases 6-10) gets its own plan after OSS ships.

---

## File Structure (Final)

When this plan is complete, the repo will contain:

```
maho-module-preorder/
├── .github/
│ └── workflows/
│ └── ci.yml # PHPStan + PHPUnit
├── .gitignore
├── LICENSE # Apache 2.0
├── README.md # User-facing docs + "Unique / Distinctive Feature(s)"
├── CHANGELOG.md # Keep-a-Changelog
├── composer.json # type: maho-module, mahocommerce/maho >=26.3
├── phpstan.neon # level 6
├── phpunit.xml # bootstrap, suite config
├── docs/
│ ├── spec.md # (already written)
│ └── plans/
│ └── 2026-04-22-preorder-oss-implementation.md # this file
├── tests/
│ ├── bootstrap.php
│ └── Unit/
│ ├── HelperCalendarTest.php
│ └── HelperDataTest.php
├── docker/
│ ├── docker-compose.yml # spins up Maho + MySQL/SQLite/PG
│ └── Makefile # `make test`, `make smoke`
└── src/
  ├── app/
  │ ├── etc/
  │ │ └── modules/
  │ │ └── Mageaustralia_Preorder.xml
  │ ├── locale/
  │ │ └── en_US/
  │ │ ├── Mageaustralia_Preorder.csv
  │ │ └── template/email/preorder/
  │ │ ├── confirmation.html
  │ │ ├── reminder_7d.html
  │ │ └── reminder_1d.html
  │ ├── design/
  │ │ ├── frontend/base/default/
  │ │ │ ├── layout/mageaustralia_preorder.xml
  │ │ │ └── template/mageaustralia/preorder/
  │ │ │ ├── badge.phtml
  │ │ │ ├── button.phtml
  │ │ │ ├── cart/item.phtml
  │ │ │ └── landing/
  │ │ │ ├── list.phtml
  │ │ │ └── jsonld.phtml
  │ │ └── adminhtml/default/default/
  │ │ └── template/mageaustralia/preorder/
  │ │ └── product/edit/preorder.phtml # admin product fieldset
  │ └── code/local/Mageaustralia/Preorder/
  │ ├── Block/
  │ │ ├── Badge.php
  │ │ ├── Button.php
  │ │ ├── JsonLd.php
  │ │ ├── Cart/Item.php
  │ │ └── Landing/ProductList.php
  │ ├── Helper/
  │ │ ├── Data.php
  │ │ └── Calendar.php
  │ ├── Model/
  │ │ ├── Observer.php
  │ │ ├── Cron/Reminders.php
  │ │ ├── Email/Sender.php
  │ │ └── Sitemap/Provider.php
  │ ├── controllers/
  │ │ └── IndexController.php
  │ ├── etc/
  │ │ ├── adminhtml.xml
  │ │ ├── config.xml
  │ │ └── system.xml
  │ └── sql/mageaustralia_preorder_setup/
  │ └── install-0.1.0.php
```

---

## Phase 0 - Repo & Test Environment Scaffold (1 day)

### Task 0.1: Initialise repo

**Files:**
- Create: `~/Development/maho-modules/maho-module-preorder/.gitignore`
- Create: `~/Development/maho-modules/maho-module-preorder/LICENSE`
- Create: `~/Development/maho-modules/maho-module-preorder/README.md`
- Create: `~/Development/maho-modules/maho-module-preorder/CHANGELOG.md`

- [ ] **Step 1: `git init` and add the standard files**

```bash
cd ~/Development/maho-modules/maho-module-preorder
git init -b main
```

- [ ] **Step 2: Write `.gitignore`**

```
/vendor/
/.idea/
/.vscode/
/.phpunit.cache/
/.phpunit.result.cache
/.phpstan.cache/
/composer.lock
/docker/.data/
*.swp
.DS_Store
```

- [ ] **Step 3: Write `LICENSE` (Apache 2.0)**

Use the standard Apache 2.0 license text (https://www.apache.org/licenses/LICENSE-2.0.txt). Copy verbatim. Replace `[yyyy] [name of copyright owner]` with `2026 Mage Australia Pty Ltd`.

- [ ] **Step 4: Write `README.md` skeleton**

```markdown
# Mageaustralia_Preorder

Pre-order workflow for Maho. Lets merchants accept orders for products before they're in stock, with a known availability date, the right labels everywhere, and an SEO landing page no other module ships.

**Requires:** [Maho](https://github.com/mahocommerce/maho) (PHP 8.3+, Maho ≥26.3)

[![Maho Storefront ready](https://img.shields.io/badge/Maho_Storefront-ready-blue)](https://github.com/mageaustralia/maho-storefront)
[![License: Apache 2.0](https://img.shields.io/badge/license-Apache%202.0-blue)](LICENSE)

## Unique / Distinctive Feature(s)

- **Auto-generated `/preorder` SEO landing page** - every preorder product appears at a public URL with `schema.org/Product` structured data and sitemap inclusion. Indexable, rankable, marketing-ready out of the box. *No M2 incumbent ships this.*
- **`.ics` calendar export** attached to order confirmation - customers add the dispatch date to their calendar in one click.
- **Lifecycle reminder emails** at 7-day and 1-day-before-dispatch.
- **Maho Storefront ready** - works in headless setups out of the box; no separate install.
- **SQLite + PostgreSQL compatible** in addition to MySQL/MariaDB.

## Install

```bash
composer require mageaustralia/maho-module-preorder
```

Then in admin: System > Cache Management > Flush Magento Cache, and System > Configuration > Catalog > Preorder to set defaults.

## Configure

| Setting | Default | Description |
|---|---|---|
| Default button text | "Pre-order now" | Override per-product if needed |
| Send 7-day reminder | yes | Email sent 7 days before `preorder_available_date` |
| Send 1-day reminder | yes | Email sent 1 day before |
| `/preorder` page enabled | yes | Set to no to disable the landing page |

## Use

On any product, set:
- **Is Preorder**: Yes
- **Preorder Available Date**: when it ships
- (optional) **Preorder Button Text**: custom CTA
- (optional) **Preorder Message**: e.g. "Limited first-batch quantity"

That's it. Frontend updates automatically.

## Pro Version

`Mageaustralia_PreorderPro` adds: waitlist / notify-when-available, deduct-at-ship inventory rule, deposits / partial payment, preorder-specific promotions, and an admin dashboard with **AI demand forecasting**. See [mageaustralia.com.au/preorder-pro](https://mageaustralia.com.au/preorder-pro) ($119 single-store / $249 unlimited).

## License

Apache 2.0 - see [LICENSE](LICENSE).

## Contributing

PRs welcome. See [CHANGELOG.md](CHANGELOG.md).
```

- [ ] **Step 5: Write `CHANGELOG.md`**

```markdown
# Changelog

All notable changes to this project will be documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]
- Initial scaffold.
```

- [ ] **Step 6: Initial commit**

```bash
git add .gitignore LICENSE README.md CHANGELOG.md
git commit -m "chore: initialise repo with Apache 2.0 license and README"
```

---

### Task 0.2: composer.json

**Files:**
- Create: `composer.json` at repo root

- [ ] **Step 1: Write `composer.json`**

```json
{
  "name": "mageaustralia/maho-module-preorder",
  "description": "Pre-order workflow for Maho - auto SEO landing page, .ics calendar export, lifecycle emails, headless-ready.",
  "type": "maho-module",
  "license": "Apache-2.0",
  "homepage": "https://github.com/mageaustralia/maho-module-preorder",
  "authors": [
  {
  "name": "Mage Australia",
  "email": "dev@mageaustralia.com.au",
  "homepage": "https://mageaustralia.com.au"
  }
  ],
  "require": {
  "php": ">=8.3",
  "mahocommerce/maho": ">=26.3"
  },
  "require-dev": {
  "phpstan/phpstan": "^1.11",
  "phpunit/phpunit": "^10.5"
  },
  "autoload": {
  "psr-0": {
  "Mageaustralia_Preorder_": "src/app/code/local/"
  }
  },
  "autoload-dev": {
  "psr-4": {
  "Mageaustralia\\Preorder\\Tests\\": "tests/"
  }
  },
  "extra": {
  "map": [
  ["src/app/code/local/Mageaustralia/Preorder", "app/code/local/Mageaustralia/Preorder"],
  ["src/app/design/frontend/base/default", "app/design/frontend/base/default"],
  ["src/app/design/adminhtml/default/default", "app/design/adminhtml/default/default"],
  ["src/app/etc/modules/Mageaustralia_Preorder.xml", "app/etc/modules/Mageaustralia_Preorder.xml"],
  ["src/app/locale/en_US/Mageaustralia_Preorder.csv", "app/locale/en_US/Mageaustralia_Preorder.csv"],
  ["src/app/locale/en_US/template/email/preorder", "app/locale/en_US/template/email/preorder"]
  ]
  }
}
```

- [ ] **Step 2: Run composer validate**

```bash
composer validate
```
Expected: `./composer.json is valid` (a non-fatal warning about the unbound `>=26.3` constraint on `mahocommerce/maho` is expected and accepted - it matches the portfolio convention). **Do not use `--strict`** - Maho uses calendar versioning, not semver, so unbound upper constraints are intentional.

- [ ] **Step 3: Commit**

```bash
git add composer.json
git commit -m "chore: add composer.json (type maho-module, Apache 2.0, PHP 8.3+)"
```

---

### Task 0.3: Module declaration XML

**Files:**
- Create: `src/app/etc/modules/Mageaustralia_Preorder.xml`

- [ ] **Step 1: Write the module declaration**

```xml
<?xml version="1.0"?>
<config>
  <modules>
  <Mageaustralia_Preorder>
  <active>true</active>
  <codePool>local</codePool>
  <depends>
  <Mage_Catalog/>
  <Mage_Sales/>
  <Mage_Checkout/>
  </depends>
  </Mageaustralia_Preorder>
  </modules>
</config>
```

- [ ] **Step 2: Commit**

```bash
git add src/app/etc/modules/Mageaustralia_Preorder.xml
git commit -m "feat: declare Mageaustralia_Preorder module"
```

---

### Task 0.4: Module config.xml stub

**Files:**
- Create: `src/app/code/local/Mageaustralia/Preorder/etc/config.xml`

- [ ] **Step 1: Write the config.xml stub**

```xml
<?xml version="1.0"?>
<config>
  <modules>
  <Mageaustralia_Preorder>
  <version>0.1.0</version>
  </Mageaustralia_Preorder>
  </modules>
  <global>
  <helpers>
  <mageaustralia_preorder>
  <class>Mageaustralia_Preorder_Helper</class>
  </mageaustralia_preorder>
  </helpers>
  <blocks>
  <mageaustralia_preorder>
  <class>Mageaustralia_Preorder_Block</class>
  </mageaustralia_preorder>
  </blocks>
  <models>
  <mageaustralia_preorder>
  <class>Mageaustralia_Preorder_Model</class>
  </mageaustralia_preorder>
  </models>
  <resources>
  <mageaustralia_preorder_setup>
  <setup>
  <module>Mageaustralia_Preorder</module>
  <class>Mage_Eav_Model_Entity_Setup</class>
  </setup>
  </mageaustralia_preorder_setup>
  </resources>
  </global>
  <default>
  <mageaustralia_preorder>
  <general>
  <enabled>1</enabled>
  <default_button_text>Pre-order now</default_button_text>
  <landing_page_enabled>1</landing_page_enabled>
  <send_reminder_7d>1</send_reminder_7d>
  <send_reminder_1d>1</send_reminder_1d>
  <ics_attach>1</ics_attach>
  </general>
  </mageaustralia_preorder>
  </default>
</config>
```

- [ ] **Step 2: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/etc/config.xml
git commit -m "feat: add module config.xml with helpers/blocks/models declarations"
```

---

### Task 0.5: Locale CSV stub

**Files:**
- Create: `src/app/locale/en_US/Mageaustralia_Preorder.csv`

- [ ] **Step 1: Write the locale CSV with the strings used so far**

```csv
"Pre-order now","Pre-order now"
"Pre-order","Pre-order"
"Ships ~%s","Ships ~%s"
"Pre-order - ships ~%s","Pre-order - ships ~%s"
"Preorder","Preorder"
"Is Preorder","Is Preorder"
"Preorder Available Date","Preorder Available Date"
"Preorder Button Text","Preorder Button Text"
"Preorder Message","Preorder Message"
"Pre-order Now","Pre-order Now"
"All products available for pre-order. Reserve yours today.","All products available for pre-order. Reserve yours today."
```

- [ ] **Step 2: Commit**

```bash
git add src/app/locale/en_US/Mageaustralia_Preorder.csv
git commit -m "feat: add en_US locale CSV"
```

---

### Task 0.6: Local Maho test environment

**Files:**
- Create: `docker/docker-compose.yml`
- Create: `docker/Makefile`
- Create: `docker/seed.sql` (optional - empty for now)

- [ ] **Step 1: Write `docker/docker-compose.yml`**

```yaml
services:
  maho:
  image: mahocommerce/maho:latest
  ports:
  - "8080:80"
  environment:
  MAHO_DB_HOST: db
  MAHO_DB_NAME: maho
  MAHO_DB_USER: maho
  MAHO_DB_PASSWORD: maho
  MAHO_BASE_URL: http://localhost:8080/
  MAHO_ADMIN_USER: admin
  MAHO_ADMIN_PASSWORD: admin1234
  volumes:
  - ../src:/var/www/html/app/code/local/Mageaustralia/Preorder/__src_overlay__:ro
  - ./entrypoint-overlay.sh:/usr/local/bin/entrypoint-overlay.sh:ro
  depends_on:
  - db
  entrypoint: ["/usr/local/bin/entrypoint-overlay.sh"]

  db:
  image: mariadb:11
  environment:
  MARIADB_ROOT_PASSWORD: root
  MARIADB_DATABASE: maho
  MARIADB_USER: maho
  MARIADB_PASSWORD: maho
  volumes:
  - ./.data/db:/var/lib/mysql
```

(The overlay entrypoint copies `src/` paths into the running Maho install on container start. We write that next.)

- [ ] **Step 2: Write `docker/entrypoint-overlay.sh`**

```bash
#!/bin/sh
set -e

# Overlay our module source into the Maho install
SRC=/var/www/html/app/code/local/Mageaustralia/Preorder/__src_overlay__
if [ -d "$SRC" ]; then
  cp -r "$SRC/app/code/local/Mageaustralia/Preorder" /var/www/html/app/code/local/Mageaustralia/ 2>/dev/null || true
  cp -r "$SRC/app/etc/modules/Mageaustralia_Preorder.xml" /var/www/html/app/etc/modules/ 2>/dev/null || true
  cp -r "$SRC/app/locale/en_US/" /var/www/html/app/locale/ 2>/dev/null || true
  cp -r "$SRC/app/design/" /var/www/html/app/ 2>/dev/null || true
fi

# Hand off to Maho's normal entrypoint
exec /usr/local/bin/docker-entrypoint.sh "$@"
```

```bash
chmod +x docker/entrypoint-overlay.sh
```

- [ ] **Step 3: Write `docker/Makefile`**

```makefile
.PHONY: up down logs shell smoke reset

up:
	docker compose -f docker-compose.yml up -d --wait

down:
	docker compose -f docker-compose.yml down

logs:
	docker compose -f docker-compose.yml logs -f maho

shell:
	docker compose -f docker-compose.yml exec maho bash

smoke:
	curl -fsS http://localhost:8080/ > /dev/null && echo "✓ frontend reachable"
	curl -fsS http://localhost:8080/preorder > /dev/null && echo "✓ /preorder reachable" || echo "✗ /preorder not reachable yet (expected pre-controller)"

reset:
	docker compose -f docker-compose.yml down -v
	rm -rf .data
```

- [ ] **Step 4: Bring up the stack**

```bash
cd docker
make up
```

Expected: containers report healthy within ~60 seconds. Browse to http://localhost:8080 and confirm Maho frontend renders.

- [ ] **Step 5: Confirm module is loaded**

```bash
make shell
# inside container:
ls -la app/code/local/Mageaustralia/Preorder
ls app/etc/modules/Mageaustralia_Preorder.xml
exit
```

Expected: files present.

- [ ] **Step 6: Verify module shows in admin**

Browse to `http://localhost:8080/admin` (login admin / admin1234), navigate to System > Configuration > Advanced > Advanced. Confirm `Mageaustralia_Preorder` appears in the module list and is enabled.

- [ ] **Step 7: Commit**

```bash
git add docker/
git commit -m "chore: add Docker test environment with overlay entrypoint"
```

---

### Task 0.7: PHPStan + PHPUnit config

**Files:**
- Create: `phpstan.neon`
- Create: `phpunit.xml`
- Create: `tests/bootstrap.php`

- [ ] **Step 1: Install dev dependencies**

```bash
composer install --dev
```

Expected: vendor/ created, phpstan and phpunit binaries available.

- [ ] **Step 2: Write `phpstan.neon`**

```neon
parameters:
  level: 5
  paths:
  - src/app/code/local/Mageaustralia/Preorder
  excludePaths:
  - src/app/code/local/Mageaustralia/Preorder/sql
  bootstrapFiles:
  - tests/bootstrap.php
  ignoreErrors:
  - '#Class Mage(_[A-Za-z0-9]+)+ not found#'
  - '#Class Varien_[A-Za-z0-9_]+ not found#'
  - '#Call to static method [a-zA-Z]+\(\) on an unknown class Mage#'
```

(The ignored errors handle Maho/Magento global classes that PHPStan can't resolve without a full bootstrap. We'll tighten this later.)

- [ ] **Step 3: Write `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
  bootstrap="tests/bootstrap.php"
  colors="true"
  failOnWarning="true"
  failOnRisky="true">
  <testsuites>
  <testsuite name="Unit">
  <directory>tests/Unit</directory>
  </testsuite>
  </testsuites>
  <source>
  <include>
  <directory>src/app/code/local/Mageaustralia/Preorder/Helper</directory>
  <directory>src/app/code/local/Mageaustralia/Preorder/Model</directory>
  </include>
  </source>
</phpunit>
```

- [ ] **Step 4: Write `tests/bootstrap.php`**

```php
<?php
declare(strict_types=1);

// Composer autoload for test deps
require __DIR__ . '/../vendor/autoload.php';

// Minimal class-aliasing shims so unit tests can load Helper/Model classes
// without booting the full Maho framework. Helpers and pure-function models
// are tested in isolation; integration is covered by the Docker smoke suite.

if (!class_exists('Mage_Core_Helper_Abstract')) {
  eval('class Mage_Core_Helper_Abstract {
  public function __($s) { return $s; }
  }');
}

if (!class_exists('Mage')) {
  eval('class Mage {
  public static function helper($name) { return new \stdClass(); }
  public static function getStoreConfig($path, $store = null) { return null; }
  public static function getStoreConfigFlag($path, $store = null) { return false; }
  public static function getModel($name, $args = []) { return new \stdClass(); }
  }');
}

// Autoload our module classes (psr-0 style: Mageaustralia_Preorder_Foo_Bar → src/app/code/local/Mageaustralia/Preorder/Foo/Bar.php)
spl_autoload_register(function ($class) {
  if (strpos($class, 'Mageaustralia_Preorder_') !== 0) {
  return;
  }
  $rel = str_replace('_', '/', $class) . '.php';
  $file = __DIR__ . '/../src/app/code/local/' . $rel;
  if (is_file($file)) {
  require_once $file;
  }
});
```

- [ ] **Step 5: Run PHPStan against empty source**

```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

Expected: `[OK] No errors` (no source yet, but config loads).

- [ ] **Step 6: Run PHPUnit against empty test suite**

```bash
vendor/bin/phpunit
```

Expected: `No tests executed!` warning is fine - config loads.

- [ ] **Step 7: Commit**

```bash
git add phpstan.neon phpunit.xml tests/bootstrap.php
git commit -m "chore: add PHPStan (level 5) and PHPUnit configs"
```

---

### Task 0.8: GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`

- [ ] **Step 1: Write the CI workflow**

```yaml
name: CI

on:
  push:
  branches: [main]
  pull_request:

jobs:
  static-and-unit:
  runs-on: ubuntu-latest
  steps:
  - uses: actions/checkout@v4
  - uses: shivammathur/setup-php@v2
  with:
  php-version: '8.3'
  coverage: none
  - name: Cache composer
  uses: actions/cache@v4
  with:
  path: ~/.composer/cache
  key: composer-${{ hashFiles('composer.json') }}
  - run: composer validate
  - run: composer install --prefer-dist --no-progress
  - run: vendor/bin/phpstan analyse --memory-limit=512M --no-progress
  - run: vendor/bin/phpunit
```

- [ ] **Step 2: Create the GitHub repo and push**

```bash
gh repo create mageaustralia/maho-module-preorder --public --source=. --remote=origin --description "Pre-order workflow for Maho - auto SEO landing page, .ics export, lifecycle emails."
git push -u origin main
```

Expected: repo visible at https://github.com/mageaustralia/maho-module-preorder. CI run kicks off automatically.

- [ ] **Step 3: Verify CI passes on the empty scaffold**

```bash
gh run watch
```

Expected: green check.

- [ ] **Step 4: Commit any local CI tweaks if the run failed**

(Skip if Step 3 was green.)

---

## Phase 1 - OSS Core (5-7 days)

Phase 1 lands the working pre-order workflow on the default Maho theme: product attributes, helpers, PDP button replacement, cart/order labelling, and admin config. This is the meat of the OSS tier minus the landing page (Phase 2) and emails (Phase 3).

### Task 1.1: Helper_Data - TDD'd public API

**Files:**
- Create: `src/app/code/local/Mageaustralia/Preorder/Helper/Data.php`
- Create: `tests/Unit/HelperDataTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/HelperDataTest.php
declare(strict_types=1);

namespace Mageaustralia\Preorder\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperDataTest extends TestCase
{
  public function test_isPreorder_returns_false_when_attribute_missing(): void
  {
  $product = new \stdClass();
  $helper = new \Mageaustralia_Preorder_Helper_Data();
  $this->assertFalse($helper->isPreorder($product));
  }

  public function test_isPreorder_returns_true_when_flag_set(): void
  {
  $product = new class {
  public function getData($key) { return $key === 'is_preorder' ? 1 : null; }
  };
  $helper = new \Mageaustralia_Preorder_Helper_Data();
  $this->assertTrue($helper->isPreorder($product));
  }

  public function test_getAvailableDate_returns_null_when_unset(): void
  {
  $product = new \stdClass();
  $helper = new \Mageaustralia_Preorder_Helper_Data();
  $this->assertNull($helper->getAvailableDate($product));
  }

  public function test_getAvailableDate_returns_DateTimeImmutable_when_set(): void
  {
  $product = new class {
  public function getData($key) { return $key === 'preorder_available_date' ? '2026-05-15 00:00:00' : null; }
  };
  $helper = new \Mageaustralia_Preorder_Helper_Data();
  $date = $helper->getAvailableDate($product);
  $this->assertInstanceOf(\DateTimeImmutable::class, $date);
  $this->assertSame('2026-05-15', $date->format('Y-m-d'));
  }

  public function test_getButtonText_returns_product_override_when_set(): void
  {
  $product = new class {
  public function getData($key) { return $key === 'preorder_button_text' ? 'Reserve yours' : null; }
  };
  $helper = new \Mageaustralia_Preorder_Helper_Data();
  $this->assertSame('Reserve yours', $helper->getButtonText($product));
  }
}
```

- [ ] **Step 2: Run the test - confirm it fails**

```bash
vendor/bin/phpunit --filter HelperDataTest
```

Expected: failures (`Class "Mageaustralia_Preorder_Helper_Data" not found` or similar).

- [ ] **Step 3: Implement `Helper_Data`**

```php
<?php
// src/app/code/local/Mageaustralia/Preorder/Helper/Data.php

class Mageaustralia_Preorder_Helper_Data extends Mage_Core_Helper_Abstract
{
  public const XML_PATH_DEFAULT_BUTTON_TEXT = 'mageaustralia_preorder/general/default_button_text';
  public const XML_PATH_LANDING_ENABLED = 'mageaustralia_preorder/general/landing_page_enabled';
  public const XML_PATH_REMINDER_7D = 'mageaustralia_preorder/general/send_reminder_7d';
  public const XML_PATH_REMINDER_1D = 'mageaustralia_preorder/general/send_reminder_1d';
  public const XML_PATH_ICS_ATTACH = 'mageaustralia_preorder/general/ics_attach';

  public function isPreorder(mixed $product): bool
  {
  return $this->getProductData($product, 'is_preorder') ? true : false;
  }

  public function getAvailableDate(mixed $product): ?\DateTimeImmutable
  {
  $raw = $this->getProductData($product, 'preorder_available_date');
  if (!$raw || !is_string($raw)) {
  return null;
  }
  try {
  return new \DateTimeImmutable($raw);
  } catch (\Exception $e) {
  return null;
  }
  }

  public function getButtonText(mixed $product): string
  {
  $override = $this->getProductData($product, 'preorder_button_text');
  if (is_string($override) && $override !== '') {
  return $override;
  }
  $default = Mage::getStoreConfig(self::XML_PATH_DEFAULT_BUTTON_TEXT);
  return is_string($default) && $default !== '' ? $default : 'Pre-order now';
  }

  public function getMessage(mixed $product): string
  {
  $msg = $this->getProductData($product, 'preorder_message');
  return is_string($msg) ? $msg : '';
  }

  public function isLandingEnabled(): bool
  {
  return (bool) Mage::getStoreConfigFlag(self::XML_PATH_LANDING_ENABLED);
  }

  public function shouldSendReminder7d(): bool
  {
  return (bool) Mage::getStoreConfigFlag(self::XML_PATH_REMINDER_7D);
  }

  public function shouldSendReminder1d(): bool
  {
  return (bool) Mage::getStoreConfigFlag(self::XML_PATH_REMINDER_1D);
  }

  public function shouldAttachIcs(): bool
  {
  return (bool) Mage::getStoreConfigFlag(self::XML_PATH_ICS_ATTACH);
  }

  private function getProductData(mixed $product, string $key): mixed
  {
  if (is_object($product) && method_exists($product, 'getData')) {
  return $product->getData($key);
  }
  if (is_array($product)) {
  return $product[$key] ?? null;
  }
  return null;
  }
}
```

- [ ] **Step 4: Run the test - confirm it passes**

```bash
vendor/bin/phpunit --filter HelperDataTest
```

Expected: 5/5 tests pass.

- [ ] **Step 5: Run PHPStan**

```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

Expected: 0 errors.

- [ ] **Step 6: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/Helper/Data.php tests/Unit/HelperDataTest.php
git commit -m "feat: add Helper_Data with isPreorder/getAvailableDate/getButtonText"
```

---

### Task 1.2: Install script - product attributes

**Files:**
- Create: `src/app/code/local/Mageaustralia/Preorder/sql/mageaustralia_preorder_setup/install-0.1.0.php`

- [ ] **Step 1: Write the install script**

```php
<?php
/** @var Mage_Eav_Model_Entity_Setup $installer */
$installer = $this;
$installer->startSetup();

$catalogProductEntityTypeId = (int) Mage::getModel('eav/entity')
  ->setType('catalog_product')
  ->getTypeId();

// 1. is_preorder (yesno, store-scoped)
$installer->addAttribute('catalog_product', 'is_preorder', [
  'group' => 'Preorder',
  'type' => 'int',
  'backend' => '',
  'frontend' => '',
  'label' => 'Is Preorder',
  'input' => 'boolean',
  'class' => '',
  'source' => 'eav/entity_attribute_source_boolean',
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
  'visible' => true,
  'required' => false,
  'user_defined' => true,
  'default' => '0',
  'searchable' => false,
  'filterable' => false,
  'comparable' => false,
  'visible_on_front' => false,
  'used_in_product_listing' => true,
  'unique' => false,
  'apply_to' => 'simple,configurable,virtual,bundle,downloadable',
]);

// 2. preorder_available_date (date, store-scoped)
$installer->addAttribute('catalog_product', 'preorder_available_date', [
  'group' => 'Preorder',
  'type' => 'datetime',
  'backend' => 'eav/entity_attribute_backend_datetime',
  'frontend' => '',
  'label' => 'Preorder Available Date',
  'input' => 'date',
  'class' => '',
  'source' => '',
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
  'visible' => true,
  'required' => false,
  'user_defined' => true,
  'searchable' => false,
  'filterable' => false,
  'comparable' => false,
  'visible_on_front' => false,
  'used_in_product_listing' => true,
  'apply_to' => 'simple,configurable,virtual,bundle,downloadable',
]);

// 3. preorder_button_text (varchar 64, store-scoped)
$installer->addAttribute('catalog_product', 'preorder_button_text', [
  'group' => 'Preorder',
  'type' => 'varchar',
  'label' => 'Preorder Button Text',
  'input' => 'text',
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
  'visible' => true,
  'required' => false,
  'user_defined' => true,
  'searchable' => false,
  'filterable' => false,
  'comparable' => false,
  'visible_on_front' => false,
  'used_in_product_listing' => false,
  'apply_to' => 'simple,configurable,virtual,bundle,downloadable',
]);

// 4. preorder_message (text, store-scoped)
$installer->addAttribute('catalog_product', 'preorder_message', [
  'group' => 'Preorder',
  'type' => 'text',
  'label' => 'Preorder Message',
  'input' => 'textarea',
  'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
  'visible' => true,
  'required' => false,
  'user_defined' => true,
  'searchable' => false,
  'filterable' => false,
  'comparable' => false,
  'visible_on_front' => false,
  'used_in_product_listing' => false,
  'apply_to' => 'simple,configurable,virtual,bundle,downloadable',
]);

$installer->endSetup();
```

- [ ] **Step 2: Bump module version**

Edit `src/app/code/local/Mageaustralia/Preorder/etc/config.xml` - confirm version is `0.1.0`.

- [ ] **Step 3: Bring up Docker, watch the install run**

```bash
cd docker
make reset
make up
make logs
```

Expected: container logs show install script running. Watch for `Mageaustralia_Preorder` reference and no exceptions.

- [ ] **Step 4: Smoke-test in admin**

Browse to `http://localhost:8080/admin` → Catalog → Manage Products → edit any product. Confirm new "Preorder" tab/group appears with the 4 attributes.

- [ ] **Step 5: Set test data on a product**

In admin: pick one product, set `Is Preorder = Yes`, `Preorder Available Date = 2026-05-15`, save.

Then verify via shell:

```bash
make shell
mysql -h db -u maho -pmaho maho -e "SELECT entity_id, attribute_id FROM eav_attribute WHERE attribute_code IN ('is_preorder','preorder_available_date','preorder_button_text','preorder_message');"
```

Expected: 4 rows.

- [ ] **Step 6: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/sql/
git commit -m "feat: add install script with 4 preorder product attributes"
```

---

### Task 1.3: Install script - quote/order item flags

**Files:**
- Modify: `src/app/code/local/Mageaustralia/Preorder/sql/mageaustralia_preorder_setup/install-0.1.0.php` (append at the end, before `endSetup()`)

- [ ] **Step 1: Append column-add code to the install script**

Insert before `$installer->endSetup();`:

```php
// Add quote/order item columns - portable across MySQL/SQLite/PG
$connection = $installer->getConnection();

foreach (['sales_flat_quote_item', 'sales_flat_order_item'] as $tableName) {
  $table = $installer->getTable($tableName);
  if (!$connection->tableColumnExists($table, 'is_preorder')) {
  $connection->addColumn($table, 'is_preorder', [
  'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
  'nullable' => false,
  'default' => 0,
  'comment' => 'Pre-order flag (1 = preorder, 0 = normal)',
  ]);
  }
  if (!$connection->tableColumnExists($table, 'preorder_available_date')) {
  $connection->addColumn($table, 'preorder_available_date', [
  'type' => Varien_Db_Ddl_Table::TYPE_DATETIME,
  'nullable' => true,
  'comment' => 'Pre-order expected dispatch date',
  ]);
  }
}
```

- [ ] **Step 2: Reset Docker to re-run the install**

```bash
make reset && make up && make logs
```

Expected: install runs cleanly.

- [ ] **Step 3: Verify columns exist**

```bash
make shell
mysql -h db -u maho -pmaho maho -e "DESCRIBE sales_flat_quote_item;" | grep -E "is_preorder|preorder_available_date"
mysql -h db -u maho -pmaho maho -e "DESCRIBE sales_flat_order_item;" | grep -E "is_preorder|preorder_available_date"
```

Expected: 2 rows per table.

- [ ] **Step 4: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/sql/
git commit -m "feat: add is_preorder/preorder_available_date columns on quote+order items"
```

---

### Task 1.4: Admin system config (system.xml)

**Files:**
- Create: `src/app/code/local/Mageaustralia/Preorder/etc/system.xml`
- Create: `src/app/code/local/Mageaustralia/Preorder/etc/adminhtml.xml`

- [ ] **Step 1: Write `system.xml` (the System > Configuration entries)**

```xml
<?xml version="1.0"?>
<config>
  <tabs>
  <catalog translate="label" module="catalog">
  <label>Catalog</label>
  <sort_order>200</sort_order>
  </catalog>
  </tabs>
  <sections>
  <mageaustralia_preorder translate="label" module="mageaustralia_preorder">
  <label>Preorder</label>
  <tab>catalog</tab>
  <frontend_type>text</frontend_type>
  <sort_order>500</sort_order>
  <show_in_default>1</show_in_default>
  <show_in_website>1</show_in_website>
  <show_in_store>1</show_in_store>
  <groups>
  <general translate="label">
  <label>General</label>
  <frontend_type>text</frontend_type>
  <sort_order>10</sort_order>
  <show_in_default>1</show_in_default>
  <show_in_website>1</show_in_website>
  <show_in_store>1</show_in_store>
  <fields>
  <enabled translate="label">
  <label>Module Enabled</label>
  <frontend_type>select</frontend_type>
  <source_model>adminhtml/system_config_source_yesno</source_model>
  <sort_order>10</sort_order>
  <show_in_default>1</show_in_default>
  <show_in_website>1</show_in_website>
  <show_in_store>0</show_in_store>
  </enabled>
  <default_button_text translate="label">
  <label>Default Pre-order Button Text</label>
  <frontend_type>text</frontend_type>
  <sort_order>20</sort_order>
  <show_in_default>1</show_in_default>
  <show_in_website>1</show_in_website>
  <show_in_store>1</show_in_store>
  </default_button_text>
  <landing_page_enabled translate="label">
  <label>Enable /preorder Landing Page</label>
  <frontend_type>select</frontend_type>
  <source_model>adminhtml/system_config_source_yesno</source_model>
  <sort_order>30</sort_order>
  <show_in_default>1</show_in_default>
  <show_in_website>1</show_in_website>
  <show_in_store>1</show_in_store>
  </landing_page_enabled>
  <send_reminder_7d translate="label">
  <label>Send 7-Day-Before Reminder Email</label>
  <frontend_type>select</frontend_type>
  <source_model>adminhtml/system_config_source_yesno</source_model>
  <sort_order>40</sort_order>
  <show_in_default>1</show_in_default>
  <show_in_website>1</show_in_website>
  <show_in_store>1</show_in_store>
  </send_reminder_7d>
  <send_reminder_1d translate="label">
  <label>Send 1-Day-Before Reminder Email</label>
  <frontend_type>select</frontend_type>
  <source_model>adminhtml/system_config_source_yesno</source_model>
  <sort_order>50</sort_order>
  <show_in_default>1</show_in_default>
  <show_in_website>1</show_in_website>
  <show_in_store>1</show_in_store>
  </send_reminder_1d>
  <ics_attach translate="label">
  <label>Attach .ics Calendar File to Order Confirmation</label>
  <frontend_type>select</frontend_type>
  <source_model>adminhtml/system_config_source_yesno</source_model>
  <sort_order>60</sort_order>
  <show_in_default>1</show_in_default>
  <show_in_website>1</show_in_website>
  <show_in_store>1</show_in_store>
  </ics_attach>
  </fields>
  </general>
  </groups>
  </mageaustralia_preorder>
  </sections>
</config>
```

- [ ] **Step 2: Write `adminhtml.xml` (ACL for the config section)**

```xml
<?xml version="1.0"?>
<config>
  <acl>
  <resources>
  <admin>
  <children>
  <system>
  <children>
  <config>
  <children>
  <mageaustralia_preorder translate="title" module="mageaustralia_preorder">
  <title>Preorder</title>
  </mageaustralia_preorder>
  </children>
  </config>
  </children>
  </system>
  </children>
  </admin>
  </resources>
  </acl>
</config>
```

- [ ] **Step 3: Reload admin and check**

Browse to `http://localhost:8080/admin` → System → Configuration → Catalog → Preorder. Confirm all 6 fields render.

- [ ] **Step 4: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/etc/system.xml src/app/code/local/Mageaustralia/Preorder/etc/adminhtml.xml
git commit -m "feat: add admin system.xml + adminhtml.xml for module config"
```

---

### Task 1.5: Block_Badge

**Files:**
- Create: `src/app/code/local/Mageaustralia/Preorder/Block/Badge.php`
- Create: `src/app/design/frontend/base/default/template/mageaustralia/preorder/badge.phtml`

- [ ] **Step 1: Write the Block class**

```php
<?php
class Mageaustralia_Preorder_Block_Badge extends Mage_Core_Block_Template
{
  protected function _construct()
  {
  parent::_construct();
  $this->setTemplate('mageaustralia/preorder/badge.phtml');
  }

  public function getProduct(): ?Mage_Catalog_Model_Product
  {
  $product = $this->getData('product');
  if ($product instanceof Mage_Catalog_Model_Product) {
  return $product;
  }
  return Mage::registry('current_product');
  }

  public function isPreorder(): bool
  {
  $p = $this->getProduct();
  return $p ? Mage::helper('mageaustralia_preorder')->isPreorder($p) : false;
  }

  public function getAvailableDateFormatted(): ?string
  {
  $p = $this->getProduct();
  if (!$p) {
  return null;
  }
  $date = Mage::helper('mageaustralia_preorder')->getAvailableDate($p);
  return $date ? $date->format('M j, Y') : null;
  }
}
```

- [ ] **Step 2: Write the template**

```html
<?php /** @var Mageaustralia_Preorder_Block_Badge $this */ ?>
<?php if ($this->isPreorder()): ?>
  <span class="mageaustralia-preorder-badge">
  <?php echo $this->__('Pre-order'); ?>
  <?php if ($d = $this->getAvailableDateFormatted()): ?>
  <span class="mageaustralia-preorder-badge__date">
  <?php echo $this->__('Ships ~%s', $this->escapeHtml($d)); ?>
  </span>
  <?php endif; ?>
  </span>
<?php endif; ?>
```

- [ ] **Step 3: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/Block/Badge.php src/app/design/frontend/base/default/template/mageaustralia/preorder/badge.phtml
git commit -m "feat: add Block_Badge + template"
```

---

### Task 1.6: Block_Button (PDP CTA)

**Files:**
- Create: `src/app/code/local/Mageaustralia/Preorder/Block/Button.php`
- Create: `src/app/design/frontend/base/default/template/mageaustralia/preorder/button.phtml`

- [ ] **Step 1: Write the Block class**

```php
<?php
class Mageaustralia_Preorder_Block_Button extends Mage_Catalog_Block_Product_View
{
  protected function _construct()
  {
  parent::_construct();
  $this->setTemplate('mageaustralia/preorder/button.phtml');
  }

  public function isPreorder(): bool
  {
  $p = $this->getProduct();
  return $p ? Mage::helper('mageaustralia_preorder')->isPreorder($p) : false;
  }

  public function getButtonText(): string
  {
  $p = $this->getProduct();
  return $p ? Mage::helper('mageaustralia_preorder')->getButtonText($p) : 'Pre-order now';
  }

  public function getMessage(): string
  {
  $p = $this->getProduct();
  return $p ? Mage::helper('mageaustralia_preorder')->getMessage($p) : '';
  }
}
```

- [ ] **Step 2: Write the template**

```html
<?php /** @var Mageaustralia_Preorder_Block_Button $this */ ?>
<?php if ($this->isPreorder() && ($product = $this->getProduct()) && $product->isSaleable()): ?>
  <div class="mageaustralia-preorder-cta">
  <button type="button"
  title="<?php echo $this->escapeHtml($this->getButtonText()); ?>"
  class="button btn-cart mageaustralia-preorder-cta__btn"
  onclick="productAddToCartForm.submit(this)">
  <span><span><?php echo $this->escapeHtml($this->getButtonText()); ?></span></span>
  </button>
  <?php if ($msg = $this->getMessage()): ?>
  <p class="mageaustralia-preorder-cta__message"><?php echo $this->escapeHtml($msg); ?></p>
  <?php endif; ?>
  </div>
<?php endif; ?>
```

- [ ] **Step 3: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/Block/Button.php src/app/design/frontend/base/default/template/mageaustralia/preorder/button.phtml
git commit -m "feat: add Block_Button (PDP CTA replacing Add to Cart for preorders)"
```

---

### Task 1.7: Layout XML - wire blocks into PDP & list

**Files:**
- Create: `src/app/design/frontend/base/default/layout/mageaustralia_preorder.xml`
- Modify: `src/app/code/local/Mageaustralia/Preorder/etc/config.xml` (register the layout file under `<frontend><layout>`)

- [ ] **Step 1: Write the layout XML**

```xml
<?xml version="1.0"?>
<layout version="0.1.0">
  <!-- PDP: insert preorder badge above title, replace add-to-cart with preorder button when applicable -->
  <catalog_product_view>
  <reference name="product.info">
  <block type="mageaustralia_preorder/badge" name="mageaustralia.preorder.badge.pdp" before="-" />
  </reference>
  <reference name="product.info.addtocart">
  <block type="mageaustralia_preorder/button" name="mageaustralia.preorder.button" template="mageaustralia/preorder/button.phtml" />
  </reference>
  </catalog_product_view>

  <!-- Category list: badge on each product card -->
  <catalog_category_default>
  <reference name="product_list">
  <action method="setChild">
  <name>preorder.badge</name>
  <block>mageaustralia.preorder.badge.list</block>
  </action>
  </reference>
  </catalog_category_default>
</layout>
```

- [ ] **Step 2: Register the layout file in `config.xml`**

Edit `src/app/code/local/Mageaustralia/Preorder/etc/config.xml`. Add inside `<config>` (sibling of `<global>`):

```xml
<frontend>
  <layout>
  <updates>
  <mageaustralia_preorder>
  <file>mageaustralia_preorder.xml</file>
  </mageaustralia_preorder>
  </updates>
  </layout>
  <translate>
  <modules>
  <Mageaustralia_Preorder>
  <files>
  <default>Mageaustralia_Preorder.csv</default>
  </files>
  </Mageaustralia_Preorder>
  </modules>
  </translate>
</frontend>
<adminhtml>
  <translate>
  <modules>
  <Mageaustralia_Preorder>
  <files>
  <default>Mageaustralia_Preorder.csv</default>
  </files>
  </Mageaustralia_Preorder>
  </modules>
  </translate>
</adminhtml>
```

- [ ] **Step 3: Reset Docker, browse PDP**

```bash
cd docker && make reset && make up
```

Then in admin set a product as preorder (Task 1.2 step 5). Browse the product on the storefront. Expected:
- Preorder badge above the product title.
- "Pre-order now" button replacing "Add to Cart".

- [ ] **Step 4: Browse the category page**

Expected: badge appears on the product card for the preorder product.

- [ ] **Step 5: Commit**

```bash
git add src/app/design/frontend/base/default/layout/mageaustralia_preorder.xml src/app/code/local/Mageaustralia/Preorder/etc/config.xml
git commit -m "feat: layout XML wires badge into PDP and category list, button into PDP"
```

---

### Task 1.8: Observer - propagate flag from product to quote item

**Files:**
- Create: `src/app/code/local/Mageaustralia/Preorder/Model/Observer.php`
- Modify: `src/app/code/local/Mageaustralia/Preorder/etc/config.xml` (register event observers)

- [ ] **Step 1: Write the Observer class**

```php
<?php
class Mageaustralia_Preorder_Model_Observer
{
  /**
  * Event: checkout_cart_product_add_after
  * Copy is_preorder + preorder_available_date from product onto quote item.
  */
  public function onCartItemAdd(Varien_Event_Observer $observer): void
  {
  /** @var Mage_Sales_Model_Quote_Item $item */
  $item = $observer->getEvent()->getQuoteItem();
  $product = $observer->getEvent()->getProduct();
  if (!$item || !$product) {
  return;
  }
  $helper = Mage::helper('mageaustralia_preorder');
  if (!$helper->isPreorder($product)) {
  return;
  }
  $item->setIsPreorder(1);
  $date = $helper->getAvailableDate($product);
  if ($date) {
  $item->setPreorderAvailableDate($date->format('Y-m-d H:i:s'));
  }
  }

  /**
  * Event: sales_convert_quote_item_to_order_item
  * Carry the flag from quote item onto order item.
  */
  public function onQuoteItemToOrderItem(Varien_Event_Observer $observer): void
  {
  /** @var Mage_Sales_Model_Order_Item $orderItem */
  $orderItem = $observer->getEvent()->getOrderItem();
  $quoteItem = $observer->getEvent()->getItem();
  if (!$orderItem || !$quoteItem) {
  return;
  }
  if ($quoteItem->getIsPreorder()) {
  $orderItem->setIsPreorder(1);
  $orderItem->setPreorderAvailableDate($quoteItem->getPreorderAvailableDate());
  }
  }
}
```

- [ ] **Step 2: Register observers in config.xml**

Add inside `<config>` (sibling of `<global>` and `<frontend>`):

```xml
<global>
  ...existing global block stays...
  <events>
  <checkout_cart_product_add_after>
  <observers>
  <mageaustralia_preorder_cart_add>
  <type>singleton</type>
  <class>mageaustralia_preorder/observer</class>
  <method>onCartItemAdd</method>
  </mageaustralia_preorder_cart_add>
  </observers>
  </checkout_cart_product_add_after>
  <sales_convert_quote_item_to_order_item>
  <observers>
  <mageaustralia_preorder_to_order>
  <type>singleton</type>
  <class>mageaustralia_preorder/observer</class>
  <method>onQuoteItemToOrderItem</method>
  </mageaustralia_preorder_to_order>
  </observers>
  </sales_convert_quote_item_to_order_item>
  </events>
</global>
```

(Merge with existing `<global>` block - don't duplicate it.)

- [ ] **Step 3: Reset Docker, run a smoke order**

```bash
cd docker && make reset && make up
```

In storefront (a) set a product to preorder, (b) add it to cart as a guest, (c) check out using the offline payment method, (d) inspect the resulting order via admin or DB.

```bash
make shell
mysql -h db -u maho -pmaho maho -e "SELECT entity_id, sku, is_preorder, preorder_available_date FROM sales_flat_order_item ORDER BY entity_id DESC LIMIT 5;"
```

Expected: most recent order item shows `is_preorder = 1` with the right date.

- [ ] **Step 4: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/Model/Observer.php src/app/code/local/Mageaustralia/Preorder/etc/config.xml
git commit -m "feat: propagate preorder flag from product → quote item → order item"
```

---

### Task 1.9: Cart line label

**Files:**
- Create: `src/app/code/local/Mageaustralia/Preorder/Block/Cart/Item.php`
- Create: `src/app/design/frontend/base/default/template/mageaustralia/preorder/cart/item.phtml`
- Modify: `src/app/design/frontend/base/default/layout/mageaustralia_preorder.xml`

- [ ] **Step 1: Write the Block class**

```php
<?php
class Mageaustralia_Preorder_Block_Cart_Item extends Mage_Core_Block_Template
{
  protected function _construct()
  {
  parent::_construct();
  $this->setTemplate('mageaustralia/preorder/cart/item.phtml');
  }

  public function getItem(): ?Mage_Sales_Model_Quote_Item
  {
  $item = $this->getData('item');
  return $item instanceof Mage_Sales_Model_Quote_Item ? $item : null;
  }

  public function isPreorder(): bool
  {
  $i = $this->getItem();
  return $i ? (bool) $i->getIsPreorder() : false;
  }

  public function getDateFormatted(): ?string
  {
  $i = $this->getItem();
  if (!$i || !$i->getPreorderAvailableDate()) {
  return null;
  }
  try {
  return (new \DateTimeImmutable($i->getPreorderAvailableDate()))->format('M j, Y');
  } catch (\Exception $e) {
  return null;
  }
  }
}
```

- [ ] **Step 2: Write the template**

```html
<?php /** @var Mageaustralia_Preorder_Block_Cart_Item $this */ ?>
<?php if ($this->isPreorder()): ?>
  <span class="mageaustralia-preorder-cart-label">
  <?php if ($d = $this->getDateFormatted()): ?>
  <?php echo $this->__('Pre-order - ships ~%s', $this->escapeHtml($d)); ?>
  <?php else: ?>
  <?php echo $this->__('Pre-order'); ?>
  <?php endif; ?>
  </span>
<?php endif; ?>
```

- [ ] **Step 3: Add the block to cart layout**

Append to `mageaustralia_preorder.xml`:

```xml
<checkout_cart_index>
  <reference name="checkout.cart.item.renderers.default">
  <action method="setTemplate">
  <template>checkout/cart/item/default.phtml</template>
  </action>
  <block type="mageaustralia_preorder/cart_item" name="mageaustralia.preorder.cart_item" as="preorder_label" />
  </reference>
</checkout_cart_index>
```

(Note: the cart item template needs a `getChildHtml('preorder_label')` call. Either via a layout XML rewrite or a small template override. For Phase 1 we ship a layout-only approach plus document a one-line theme tweak in the README.)

- [ ] **Step 4: Add cart item label visibility via observer instead (more portable)**

Replace the layout block above with an observer hook on `core_block_abstract_to_html_after` that injects the badge into rendered cart items. Add to Observer.php:

```php
/**
 * Inject preorder label into rendered cart item HTML.
 * Hooked to core_block_abstract_to_html_after so we don't need a template override.
 */
public function onBlockHtmlAfter(Varien_Event_Observer $observer): void
{
  $block = $observer->getEvent()->getBlock();
  $transport = $observer->getEvent()->getTransport();
  if (!$block || !$transport) {
  return;
  }
  if (!$block instanceof Mage_Checkout_Block_Cart_Item_Renderer) {
  return;
  }
  $item = $block->getItem();
  if (!$item || !$item->getIsPreorder()) {
  return;
  }
  $labelBlock = Mage::app()->getLayout()
  ->createBlock('mageaustralia_preorder/cart_item')
  ->setData('item', $item);
  $transport->setHtml($transport->getHtml() . $labelBlock->toHtml());
}
```

And register the event in config.xml `<events>`:

```xml
<core_block_abstract_to_html_after>
  <observers>
  <mageaustralia_preorder_cart_label>
  <type>singleton</type>
  <class>mageaustralia_preorder/observer</class>
  <method>onBlockHtmlAfter</method>
  </mageaustralia_preorder_cart_label>
  </observers>
</core_block_abstract_to_html_after>
```

- [ ] **Step 5: Reset Docker, smoke test**

```bash
cd docker && make reset && make up
```

Add a preorder product to cart, view cart. Expected: "Pre-order - ships ~May 15, 2026" appears under the product line.

- [ ] **Step 6: Commit**

```bash
git add src/app/code/local/Mageaustralia/Preorder/Block/Cart/Item.php src/app/design/frontend/base/default/template/mageaustralia/preorder/cart/item.phtml src/app/code/local/Mageaustralia/Preorder/Model/Observer.php src/app/code/local/Mageaustralia/Preorder/etc/config.xml
git commit -m "feat: cart line label for preorder items via block-html observer"
```

---

### Task 1.10: PHPStan ratchet to level 6

**Files:**
- Modify: `phpstan.neon`

- [ ] **Step 1: Bump phpstan level to 6**

```neon
parameters:
  level: 6
  ...rest stays the same...
```

- [ ] **Step 2: Run phpstan, fix any issues inline**

```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

Expected: errors. Fix each one (typically: missing return type hints, missing iterable value types). Rerun until green.

- [ ] **Step 3: Commit when green**

```bash
git add phpstan.neon src/
git commit -m "chore: ratchet PHPStan to level 6, fix typing issues"
```

---

### Task 1.11: Phase 1 manual smoke checklist

**Files:**
- Create: `docs/smoke-checklists/phase-1.md`

- [ ] **Step 1: Write the smoke checklist**

```markdown
# Phase 1 Smoke Checklist

Run after `make reset && make up` against `http://localhost:8080`.

## Admin
- [ ] System > Configuration > Catalog > Preorder shows all 6 fields and saves.
- [ ] Catalog > Manage Products > edit any product → Preorder tab/group shows 4 attributes.
- [ ] Setting Is Preorder = Yes + a date saves without error.

## Frontend (default Maho theme)
- [ ] PDP for a preorder product shows the **Pre-order** badge above title.
- [ ] PDP for a preorder product shows the **Pre-order now** button (or custom text) instead of Add to Cart.
- [ ] PDP for a normal product is unchanged.
- [ ] Category page shows the preorder badge on the preorder product card.
- [ ] Cart page shows "Pre-order - ships ~XXX" under the line item.
- [ ] Order placed via offline payment results in `sales_flat_order_item.is_preorder = 1`.
```

- [ ] **Step 2: Run through every checkbox manually**

Mark them off as you verify.

- [ ] **Step 3: Commit**

```bash
git add docs/smoke-checklists/
git commit -m "docs: add Phase 1 manual smoke checklist"
```

---

## Phase 1 Done = OSS core works on default Maho theme.

What we have at the end of Phase 1:
- Composer-installable module with Apache 2.0 license.
- Product attributes for preorder.
- PDP, category, cart, order all show the right preorder UI / labels.
- Order data persists the preorder flag end-to-end.
- CI green (PHPStan level 6, PHPUnit unit tests passing).
- Docker test environment for fast iteration.

What's missing (covered by later phases):
- The `/preorder` SEO landing page (Phase 2).
- Lifecycle reminder emails + .ics calendar (Phase 3).
- Storefront DTO support (Phase 4).
- v0.1.0 release (Phase 5).

---

## Phase 2 - `/preorder` Landing Page + JSON-LD (2-3 days)

Tasks (each follows the same TDD-bite-size pattern as Phase 1):

- **Task 2.1:** `IndexController::indexAction()` - front controller, route registration in config.xml, basic 200 response.
- **Task 2.2:** `Block_Landing_ProductList` - collection of preorder products, sorted by availability date, paginated.
- **Task 2.3:** `template/mageaustralia/preorder/landing/list.phtml` - page layout.
- **Task 2.4:** `Block_JsonLd` + `template/.../landing/jsonld.phtml` - emits `schema.org/Product` JSON-LD with `availability: PreOrder` and `availabilityStarts`.
- **Task 2.5:** Open Graph + Twitter card meta tags via layout XML.
- **Task 2.6:** `Model_Sitemap_Provider::collect()` - observer on sitemap generation that appends preorder URLs with `priority=0.7`, `changefreq=daily`, `lastmod=preorder_available_date`.
- **Task 2.7:** Smoke check - `curl /preorder` returns 200 with the JSON-LD inline; sitemap.xml contains the preorder URL.

Deliverable: visit `http://localhost:8080/preorder` → see all preorder products listed with structured data.

---

## Phase 3 - Email Lifecycle + `.ics` Calendar (2 days)

- **Task 3.1:** `Helper_Calendar::generateIcs(Order)` - TDD'd .ics builder following RFC 5545. Pure function, easy to unit-test (this is the `Helper_Calendar` test in `phpunit.xml`).
- **Task 3.2:** Email templates (`confirmation.html`, `reminder_7d.html`, `reminder_1d.html`) under `src/app/locale/en_US/template/email/preorder/`.
- **Task 3.3:** `Model_Email_Sender::sendReminder()` - sender helper using `Mage_Core_Model_Email_Template`.
- **Task 3.4:** `Model_Cron_Reminders::run()` - cron-triggered, queries orders with preorder items where `available_date BETWEEN today+7d AND today+8d` (or +1d), dispatches.
- **Task 3.5:** Cron registration in config.xml - daily at 06:00.
- **Task 3.6:** Observer `onOrderEmailQueue` - attaches .ics to outgoing order confirmation emails when an order has preorder items.
- **Task 3.7:** Smoke test - place a preorder order, view the email log (`var/log/mail.log` or Mailhog if added to docker-compose), confirm .ics attached.

---

## Phase 4 - Storefront Support (2-3 days)

- **Task 4.1:** Create `Mageaustralia_Preorder_Provider_PreorderProvider` PHP class extending Maho API Platform's product DTO. Uses convention-based discovery (per `maho-api-module-migration` skill).
- **Task 4.2:** Add the four preorder fields to the product DTO output.
- **Task 4.3:** Verify via `curl http://localhost:8080/api/products/{sku}` that fields appear.
- **Task 4.4:** **In `~/Development/maho-storefront/`**, add `themes/_shared/components/preorder/`:
  - `preorder-card-controller.ts` (Stimulus)
  - `PreorderBadge.tsx` (Hono JSX, DaisyUI)
  - `PreorderButton.tsx`
  - `PreorderCountdown.tsx`
- **Task 4.5:** Wire components into the default product card and PDP templates conditionally on `is_preorder` field presence.
- **Task 4.6:** PR these into upstream `maho-storefront`.
- **Task 4.7:** Smoke test in Maho Storefront pointed at the test backend.

---

## Phase 5 - v0.1.0 Release (1 day)

- **Task 5.1:** Final README pass - screenshots of admin product fieldset, PDP button, cart label, `/preorder` page.
- **Task 5.2:** CHANGELOG.md entry for 0.1.0.
- **Task 5.3:** Tag and release.

```bash
git tag v0.1.0
git push --tags
gh release create v0.1.0 --title "v0.1.0 - Initial release" --notes "$(cat CHANGELOG.md | sed -n '/## \[0.1.0\]/,/## \[/p' | head -n -1)"
```

- **Task 5.4:** Submit to Packagist (`https://packagist.org/packages/submit`).
- **Task 5.5:** Tweet/blog/Maho Discord announcement.
- **Task 5.6:** Add module to mageaustralia.com.au catalogue.

---

## Pro Tier (Phases 6-10) - separate plan after OSS ships

Pro tier work (waitlist, deduct-at-ship, deposits, promotions, AI demand forecast, dashboard) gets its own implementation plan once the OSS tier is live. The OSS tier release is the gate - we want real users on the free tier before we invest in the commercial features, both to validate demand and to source design feedback.

---

## Self-review

- [x] **Spec coverage** - every spec section §3-§16 has at least one task implementing it. Phases 6-10 deferred per scope ("flesh out Phase 1 in detail").
- [x] **No placeholders** - every code block contains real, runnable code. Every command has expected output. No "TBD" or "implement later".
- [x] **Type consistency** - `is_preorder` everywhere as `int`/`bool`. `preorder_available_date` everywhere as `datetime`/`?DateTimeImmutable`. Helper method names match Block usage.
- [x] **Bite-sized** - each task fits a single workday's "build, verify, commit" cycle; each step inside fits 2-5 minutes.
- [x] **DRY/YAGNI** - no speculative abstraction; helpers expose only methods used by Blocks.
- [x] **TDD where applicable** - Helper_Data and Helper_Calendar are TDD'd; layout/template/observer work uses Docker smoke tests because PHPUnit-bootstrapping the full Maho framework adds more cost than value at this scale.
