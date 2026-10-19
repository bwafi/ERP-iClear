# TEST Sidebar and Menu Authorization Handoff

## Scope

This package is based on TEST commit `b47d8b9` and contains only the area-based
sidebar, route-to-existing-menu permission mapping, the category-form route fix,
and focused tests. It does not seed menu rows or change any account or job
permissions. A route is allowed only when the logged-in account or its job
already contains the matching `menu.idmenu`, and that menu row's `roles` value
matches the mapped route permission. Unknown methods and unknown routes do not
inherit a permission.

`MenuRoutePermissions` maps page routes and their detail, AJAX, save, and export
aliases for Service, Products, Purchasing, Inventory, Sales, and Finance.
`AuthFilter` remains responsible for login; the additional global filter applies
to those mapped paths, including legacy routes that had no `auth` filter or had
the misspelled `fileter` option.

## Staff Test (read-only)

Use an existing TEST account whose permission list is already known; do not edit
the account's roles for this check.

1. Sign in as an Administrator. Open the Service, Products & Prices, Purchasing,
   Inventory, Sales, Finance, and Administration groups. Confirm the active page
   highlights its link and opens its group.
2. Sign in as a staff member who already has only the `proses_service` menu
   permission. Confirm `/proses_service` and its list/AJAX load; confirm
   `/kartu_stok` returns HTTP 403. Do not submit any service transaction.
3. Sign in as a staff member with the existing `kartu_stok` permission. Confirm
   `/kartu_stok`, `/kartu_stok/detail/1/1`, and the export control are available;
   do not submit an export if the role's normal policy prohibits downloads.
4. With each role, directly open one route outside its existing permissions.
   Expected result: HTTP 403, including POST/AJAX endpoints. Do not test by
   saving or deleting business data.
5. On a phone-sized viewport, confirm labels wrap and the current area remains
   usable. Restore desktop width and confirm no duplicate menu links appear.

The PHPUnit role fixtures cover an Administrator fixture with two existing
menu IDs, a service-only staff fixture, and denial when the required ID is
absent. They are synthetic and do not replace the real staff account check.

## Known Route Exceptions and Feature Gaps

- `POST /insert_kategori` remains the existing Kas-category handler. Product
  category create/update/delete forms now use distinct `/kategori/...` routes,
  all mapped to the existing `kategori` permission. This removes the previous
  handler collision without granting a permission.
- `/status_service/{id}` is a public customer-facing status/QR route and remains
  outside menu authorization. The HMAC CRM/customer-chat API routes likewise
  use their own authentication and are outside this sidebar package.
- `/export/kartu_stock` is shared by legacy pages; the active Kartu Stok form is
  gated by `kartu_stok`. Export controls on Stok HP and Produk Terlaris are
  commented out in the current source.
- No Kartu Harga/Pricelist route, Buku Besar page, or warehouse/location
  configuration page exists in this source snapshot. No empty sidebar links are
  added for them.
- Real TEST menu-role rows could not be independently read through cPanel's
  MySQL CLI (the hosting account's local DB login was denied). The filter uses
  the existing account/job/menu schema and fails closed, but the live role-row
  mapping and actual staff account remain to be confirmed by the handoff above.
- The TEST homepage currently stops before rendering the application with
  `PHP 8.2 or newer is required.` cPanel's PHP Selector shows `7.4 (current)`
  under Account Global Settings, and no MultiPHP Manager is available in the
  cPanel tool search. Do not change this account-wide version because the same
  hosting account contains other domains; hosting must provide a TEST-only
  PHP 8.2+ runtime before browser verification or deployment.
