# Sa'adatul D.I.Y — Setup & Run Guide

This project = static frontend (HTML/CSS/JS) + PHP 8 + MySQL backend.
The frontend never talks to MySQL directly — everything goes through
`api/*.php`, which uses PDO prepared statements.

---

## 1. Database (XAMPP / MySQL)

1. Start **MySQL** in the XAMPP Control Panel.
2. Open phpMyAdmin → **Import** → choose `database/saadatuldiy2_db.sql`
   → Go. (Creates `saadatuldiy2_db` with all tables + seed products.)
3. DB credentials live in `config/database.php` (default: root / no
   password, host `localhost`). For different credentials create
   `config/database.local.php` (git-ignored) — see `config/database.php`.

## 2. PHP backend

PHP does **not** run inside Live Server. Start it once per session:

```
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t "C:\Users\USER\Documents\SaadatulDiy3"
```

Leave that window open. Quick check: http://127.0.0.1:8000/api/products.php
should return JSON products.

(Alternatively use Apache: copy/symlink the folder into `C:\xampp\htdocs`
and open `http://localhost/SaadatulDiy3/` — but Live Server + the PHP
command above is the intended setup.)

## 3. Frontend (VS Code Live Server)

1. Open the project folder in VS Code.
2. Right-click `index.html` → **Open with Live Server**
   (runs on http://127.0.0.1:5501).
3. The frontend calls the PHP backend **directly** at
   `http://127.0.0.1:8000/api/` — configured in ONE place:
   `API_BASE` in `js/global.js`. No proxy is needed; Live Server
   only serves HTML/CSS/JS/images.
4. CORS in `includes/bootstrap.php` allows every `localhost` /
   `127.0.0.1` origin on any port, and the session cookie is sent
   because both use `127.0.0.1` (same site). Always open Live
   Server as `127.0.0.1:5501`, not `localhost:5501`.

## 4. Configurable backend URL (for friends / hosting)

All frontend API calls go through ONE place: `API_BASE` in `js/global.js`.

- Default `http://127.0.0.1:8000/api/` = the local PHP backend.
- To use a **hosted backend**, either:
  - edit `DEFAULT_API_BASE` in `js/global.js`, or
  - run `localStorage.setItem("sdiyApiBase", "https://api.yourdomain.com/")`
    in the browser console, or
  - set `window.SDIY_API_BASE` before `global.js` loads.
- Never put database passwords or SMTP credentials in frontend files —
  they stay in `config/` on the server only.

## 5. Email notifications (optional)

Checkout sends an order email to `Saadatul.Art2@gmail.com` via Gmail SMTP.
Without SMTP config the order is still saved (`email_status = failed`,
logged in `php-server.err.log`) — nothing is lost.

To enable: copy `config/mail.example.php` → `config/mail.local.php`,
set `SMTP_USER` + `SMTP_PASS` (Gmail **App Password**, not the normal
password). Requires 2-Step Verification on the Gmail account.

## 6. Friends testing without XAMPP

Live Server alone cannot run PHP/MySQL. Friends need the **backend hosted**:

- Minimum hosting: any PHP 8+ hosting with MySQL (e.g. shared cPanel host,
  or a small VPS). Upload `api/`, `includes/`, `config/`, `uploads/`,
  `database/saadatuldiy2_db.sql` (import once via phpMyAdmin), and set
  `config/database.php` / `mail.local.php` credentials.
- They then open **only the frontend** (this folder's HTML/CSS/JS/photos)
  with Live Server and point the API base to the hosted backend —
  set `localStorage.sdiyApiBase = "https://api.yourdomain.com/"` once,
  or edit `DEFAULT_API_BASE` in `js/global.js`. Add the frontend's
  origin to the allowed origins in `includes/bootstrap.php` for
  production (localhost origins are already allowed for development).
- HTTPS: put the hosted backend behind HTTPS (most hosts do this
  automatically); sessions already set the `Secure` cookie flag on HTTPS.

## 7. Feature map (where things live)

| Feature | Frontend | Backend |
|---|---|---|
| Shop / search / categories | `js/shop.js`, `shop.html` | `api/products.php` |
| Product details + designs | `js/product.js` | `api/products.php`, `product_variants` table |
| Login / signup / session | `js/login.js`, `js/signup.js` | `api/login.php`, `api/register.php`, `api/session.php` |
| Cart (products + custom) | `js/cart.js` | `api/cart.php` |
| Checkout + payment proof + email | `js/checkout.js`, `checkout.html` | `api/checkout.php`, `includes/mailer.php` |
| Custom keychain / figurine | `js/custom.js`, `js/custom2.js` | `api/custom_order.php`, `includes/custom-orders.php` |
| Favourites | heart buttons via `SDIY.setFavourite` | `api/favourites.php` |
| Reviews (+photos, 1–5 stars) | `js/review.js`, `review.html` | `api/reviews.php`, `review_images` table |
| Contact form | `js/contact.js`, `contact.html` | `api/contact.php` |
| Account / orders | `js/account.js`, `account.html` | `api/orders.php`, `api/profile.php` |