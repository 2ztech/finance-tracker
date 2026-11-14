# E-Wallet / BNPL Billing Models (Malaysia) — Research Reference

_Last updated: 2026-11-10. Scope: e-wallets / BNPL products. Banking is intentionally out of scope (savings = pure in/out flow)._
_Used as reference for future account-template work._

> Accuracy note: provider rules change over time (e.g. Grab pricing changed in Mar 2026).
> Treat this as a snapshot and re-verify against official T&Cs before hardcoding behaviour.

---

## 0. Big picture

A single **institution** (Grab, Atome, Shopee, TikTok) typically offers **several products/schemes**:

- a **stored-value wallet** (money in/out, no billing),
- a **BNPL statement** (monthly bill),
- **per-purchase instalments**,
- sometimes **personal financing** (cash loan repaid monthly).

So the future "template" should be **per product/scheme, grouped by institution** — not one
template per institution. Example groups:

| Institution | Wallet (savings-like) | Statement BNPL | Per-purchase instalments | Personal financing |
|---|---|---|---|---|
| **Grab** | GrabPay Wallet | Grab PayLater **Postpaid** | Grab PayLater **Instalments** | — |
| **Atome** | — | **Atome Card** (card cut-off/due) | Atome **PayLater** (Pay-in-3/6/9/12) | Atome **i-Cash** |
| **Shopee** | ShopeePay Wallet | **SPayLater** | SPayLater instalments | (SPayLater Cash, regional) |
| **TikTok** | — | TikTok **PayLater** (statement) | TikTok PayLater instalments | TikTok **Personal Financing** |

Banking templates: add the **major/common Malaysian banks** for savings accounts (no special billing).

---

## 1. TikTok PayLater (PIPO Fintech)

- **Monthly statement**, not per-purchase.
- **Billing date** chosen once at activation: **1st or 21st**.
  - Billing **1st → due 10th** (same month).
  - Billing **21st → due 1st** (following month).
- Bill includes all **completed orders** in the cycle. Cycle runs from the day after the previous
  billing date to the day before the current billing date.
- **Plans:** 1-month (defer full to next bill), **3 / 6 / 9 / 12-month instalments**.
- **Fee:** ~1.5% per tenure on the order total (processing).
- **Full bill payment** required; **late fee RM10 per overdue bill**; account frozen until paid.
- **Refunds** auto-offset future/other outstanding bills.
- App UX shows **Unpaid / Paid** statement tabs.
- **App mapping:** `kind=paylater`, monthly statement, cycle ≈ previous calendar month, `due_day=10`
  (or 1 for the 21st billing).

Source: seller-my.tiktok.com/university (TikTok PayLater FAQ); easyparcel.com/my/tiktok-paylater.

---

## 2. Grab PayLater (two products on the same account)

**Postpaid**
- All Grab purchases in a month are consolidated into **one bill** at the start of the following month.
- **Due by the 7th** of the following month; **interest-free**.
- **Full payment**; **late fee RM10**.

**Instalments**
- Split into **4 / 8 / 12** equal monthly payments.
- **First instalment charged at purchase**, then monthly.
- Pricing:
  - 4-month often **0%** (promos/VIP); 8/12 historically **1.5% per instalment**.
  - **From 9 Mar 2026:** standardised to **1.25%/month** on new plans (existing plans keep their rate).
- **Late fee RM10** per missed instalment (capped RM30).
- Refunds are applied to the next unpaid instalment bill.

- **App mapping (hybrid):** the **same Grab account** can hold a monthly statement (Postpaid) **and**
  per-purchase schedules (Instalments). Billing model must be per-charge/plan, not only per account.

Sources: grab.com/my/finance/pay-later; grab.com/my/terms-policies/paylater; ringgitplus.com (How Grab PayLater Works).

---

## 3. Atome Card

- Visa-like BNPL card; **up to 40 days interest-free**.
- Monthly **bill date** (e.g. the 16th). **Due date = bill date + 10 calendar days** (e.g. the 26th).
  - If the bill date falls on 29–31, it is adjusted to the 1st–3rd of the following month.
- Pay the **full bill** by the due date (0%), or **convert the bill into an instalment plan
  (3 / 6 / 12 months)** after the bill is generated (interest/fees may apply).
- **Atome PayLater (merchant BNPL):** 3 / 6 / 9 / 12 months; **first payment 1 day after purchase**,
  then every 30 days; 3-month 0%, 6/9/12 ~1.5%/month.
- **App mapping:** `kind=credit`, **cut-off = bill date − 1** (last day included), **due = bill date + 10**.
  e.g. bill date 16 → `statement_day=15`, `due_day=26`.

Sources: help.atome.my (When is my payment due date; How does installment work for Atome Card);
fintechnews.my (Atome vs Ryt review).

---

## 4. Shopee PayLater (SPayLater / Monee Capital)

- **Monthly statement**: bill includes completed **and refunded** orders from the **1st to the last
  day of the previous month**.
- **Due:** **1st or 10th** of the month (cycle assigned at activation; changeable only before the
  first purchase).
- **Full bill payment** required.
- **3-month instalment 0%** on Shopee platform checkouts (excludes ShopeeFood, insurance, Prepaids,
  Bills & Tickets).
- **Late:** ~2.5%–5% penalty + account freeze.
- **App mapping:** `kind=paylater`, monthly statement, cycle = previous calendar month,
  `due_day=1` or `10` (same model as TikTok).

Sources: help.shopee.com.my (SPayLater terms); senheng.com.my/spaylater-promo (billing covers 1st–last day of previous month).

---

## 5. Boost PayFlex (excluded from v1 templates)

- Shariah-compliant **financing / BNPL** (Commodity Murabahah), **up to 24 months**.
- Usable at DuitNow/Boost QR, in-app bills, **wallet cash-in**, **Beyond Card** (prepaid Mastercard,
  up to 9 months).
- Per-contract instalment schedules; auto-debit or manual pay. Not a fixed monthly statement.
- **App mapping:** financing/instalment plans (or a credit line).

_Source: myboost.co/feature/boost-payflex; NST / IBS Intelligence._

---

## 6. Stored-value e-wallets (wallet, no billing)

- **TNG eWallet, GrabPay Wallet, ShopeePay, Boost Wallet** → savings-like: top-up, pay, transfer,
  pure **in/out**. No billing cycle.
- **App mapping:** `kind=savings`.
- TNG eWallet: no BNPL product identified in this research (verify against current T&Cs).

---

## 7. Cut-off / due conventions observed

- **Calendar-month statement** (cycle = previous month) → due on a fixed day:
  - TikTok (billing 1st) → due **10th**; TikTok (billing 21st) → due **1st**
  - Grab **Postpaid** → due **7th**
  - Shopee **SPayLater** → due **1st** or **10th**
- **Card cut-off within the month** → Atome Card: **due = bill date + 10 days**.
- Common provider cut-off date choices: **1st, 15th, 30th** (and TikTok's 1st/21st).

---

## 8. Implications for account templates (future work)

**Billing models to support**
- `wallet` — savings-like in/out.
- `statement` — monthly bill, cycle = previous calendar month, due day.
- `card` — cut-off day + due (due may be computed, e.g. bill date + 10).
- `instalments` — per-purchase schedule (first at purchase).
- `financing` — cash loan repaid monthly (personal financing; already partly modeled).

**Hybrid caveat**
- The hybrid is at the **institution** level (wallet + BNPL + financing), and in some cases at the
  **account** level (Grab: Postpaid + Instalments; Atome Card: pay-full vs convert-to-instalment).
- The current app's `bnpl_mode` is per **account**; supporting these fully needs the billing model to
  be chosen **per charge/plan** (the add form already has a `repay_mode` full/instalments field that
  can drive this).

**Proposed template fields (for discussion)**
- Group/institution, label, brand/logo key, default name, color.
- `billing_model` (wallet | statement | card | instalments | financing).
- `billing_day` / `cut_off_day`, `due_day`, optional `due_offset_days` (e.g. Atome +10).
- Default `credit_limit`, `kind`.
- Instalment tenures + fee rate (advanced, optional for v1).
- `full_payment_only` (TikTok / SPayLater / Grab Postpaid require full bill).

**What users may want to edit on a template** (decided)
- **Credit limit only** is worth exposing as a provider-specific editable field.
- Name, colour and icon stay editable as normal account fields.
- Cut-off/due/mode are seeded by the chosen preset; users who need a variant
  pick the matching preset (e.g. TikTok billing 1st vs 21st) or use Custom.
- (Advanced) instalment tenures and fee rate are out of scope for templates.

**Logos / icons**
- Store brand logos in a **separate folder** for easy management, e.g. `public/assets/brands/`
  (PNG/SVG named by template key, e.g. `tiktok.svg`, `grab.svg`, `atome.svg`, `maybank.svg`).
- Keep the existing uploadable account icon as an override.

---

## 9. Decisions captured in this discussion

- Focus on **e-wallets** now; banking needs no special feature (savings in/out).
- Add templates for **most e-wallets described here, except Boost**.
- **E-wallets map to `savings`** in v1 (no new `wallet` kind).
- Add templates for **major Malaysian banks only**: Maybank, CIMB, Bank Islam,
  RHB, BSN, Hong Leong.
- **Grab is bundled into one account**: PayLater Postpaid (`paylater`, cycle,
  due 7; GrabFood/GrabCar purchases). Grab Instalments is not a separate preset.
- Atome Card should use the **bill date + 10 days** rule in the template.
- **Only credit limit** is worth exposing as an editable provider field.
- **Brand icons**: the user will provide ICO files later. The loader supports
  `public/assets/brands/<logo>.{ico,png,svg,webp}`; until then the chooser uses
  the monogram tiles.
- **Retain the current account format for now**; template/editable fields may be reworked later.
- **Implemented** with an **institution container**: `institutions` groups the
  existing `accounts` rows as **sections** (wallet, PayLater, card, savings).
  Catalog/apply live in `src/InstitutionTemplate.php`; the one-question-per-screen
  wizard is in `templates/accounts.php`; creation is `institutions/create-from-template`.
  Bundled logos are served from `public/assets/brands/`; category image icons
  from `public/assets/icons/`.
- **Institution → sections implemented:** TikTok = Wallet + PayLater + Personal
  Financing; Grab = Wallet + Postpaid + Instalments; Shopee = Wallet + SPayLater +
  **SLoan**; Atome = Card + PayLater (separate limits); TNG = Wallet; banks = Savings.
- **PayLater roles:** `bnpl`, `financing`, or `both` (legacy). PF/SLoan are their own
  financing sections and are **off by default** (all products are opt-in). Bills shows
  tabs per liability section; a BNPL section never shows financing bills, and the
  Financing page exists only for financing/both sections.
- **Navigation:** the account switcher lists institutions; a sidebar submenu lists
  sections. Switching institution/section returns to the shared Dashboard.
- **Credit tracking:** outstanding accrues from transactions; credit limits show
  available / limit and a gauge on the Dashboard for the viewed section, plus usage
  on Accounts and Bills.
- **SLoan reference:** personal loan for selected users, limit up to RM100,000,
  1.5%/month (~18% p.a.), 3/6/12-month tenures, RM1,000 minimum first withdrawal,
  RM5 fee, monthly repayment 30 days after signing.
- **Legacy import repair:** snapshots taken before `paylater_plans.kind` existed
  imported financing plans as `purchase`. On import, a plan whose description or
  linked transaction matches financing/loan/SLoan is marked `financing` once, and
  the account role is upgraded to `both` (or `financing` when there are no purchase
  plans), so old PF data is visible again.
- **Automatic section split (`FinancingSplit`, `financing_split_v1`):** on
  import/upgrade, a mixed section is split automatically. An existing financing
  section in the same institution is reused; otherwise one is created using the
  catalog name (TikTok Personal Financing / Shopee SLoan / Atome i-Cash, fallback
  `<Institution> Personal Financing`). Financing plans, linked transactions and
  paid allocation dates move there; the source becomes BNPL. Runs once so later
  manual combinations are respected.
- **Financing access:** the Financing page works for any financing/both section
  regardless of `bnpl_mode`; the sidebar PF section entry opens `/financing`
  directly. Atome i-Cash is in the catalog.

---

## 10. Sources

- TikTok PayLater (MY): https://seller-my.tiktok.com/university/essay?knowledge_id=1243424507447056&lang=en
- TikTok PayLater guide: https://blog.easyparcel.com/my/tiktok-paylater/
- Grab PayLater: https://www.grab.com/my/finance/pay-later/
- Grab PayLater terms: https://www.grab.com/my/terms-policies/paylater/
- Grab PayLater explainer: https://ringgitplus.com/en/blog/the-experts-corner/how-grab-paylater-works-in-malaysia.html
- Grab rate update (1.25%/mo, Mar 2026): https://www.thestar.com.my/tech/tech-news/2026/03/04/grab-standardises-paylater-interest-rate-at-125-monthly-from-march-9
- Atome due date: https://help.atome.my/hc/en-gb/articles/52130838470681-When-is-my-payment-due-date
- Atome Card instalments: https://help.atome.my/hc/en-gb/articles/52130353055129-How-does-installment-work-for-Atome-Card
- Atome availability: https://www.atome.my/en-my/news/atome-card-malaysia-shop-now-and-pay-later
- SPayLater terms: https://help.shopee.com.my/portal/4/article/78468
- SPayLater billing period: https://www.senheng.com.my/spaylater-promo/
- Boost PayFlex: https://myboost.co/feature/boost-payflex
