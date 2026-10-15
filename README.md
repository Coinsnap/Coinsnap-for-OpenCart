# Coinsnap for OpenCart

![Coinsnap for OpenCart](https://resources.coinsnap.org/products/opencart/images/cover.png)

**Bitcoin payment extension for OpenCart — accept Bitcoin and Lightning with Coinsnap or your own BTCPay Server.**

Coinsnap for OpenCart is an open-source Bitcoin payment extension for OpenCart 4. It integrates Bitcoin payments into the existing OpenCart checkout and order workflow.

Customers can pay with **Bitcoin via Lightning or on-chain**. Merchants can use **Coinsnap** without operating their own Bitcoin or Lightning payment infrastructure, or connect their own **BTCPay Server**.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

## Key features

- Accept **Bitcoin and Lightning payments** in OpenCart
- Support for **Lightning and Bitcoin on-chain**
- Support for **Coinsnap** and **BTCPay Server**
- No own Lightning node or Bitcoin payment server required when using Coinsnap
- Native integration into the OpenCart checkout and order flow
- Configurable mapping of gateway payment states to OpenCart order statuses
- Webhook-based payment status updates
- Optional automatic redirect after payment
- Custom redirect page after payment
- Optional **Bitcoin discount** at checkout
- Minimum payment checks
- Open source under the GPL-2.0 License

## Requirements

The current extension release is **1.2.0** and requires:

- **OpenCart 4**
- **PHP 7.4 or newer**
- A **Coinsnap merchant account**, or
- Your own **BTCPay Server**

For Bitcoin settlement with Coinsnap, you can use a compatible Bitcoin or Lightning wallet. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/), which combines a self-custodial Bitcoin wallet with merchant and point-of-sale functionality.

## How it works

1. Create a [Coinsnap merchant account](https://app.coinsnap.io/).
2. Install the Coinsnap payment extension in OpenCart.
3. Choose **Coinsnap** or **BTCPay Server** as the payment provider.
4. Enter the required Store ID and API credentials.
5. Configure the OpenCart order status mapping.
6. Enable the Bitcoin payment method.
7. Perform a test transaction before going live.

## Coinsnap or BTCPay Server

The extension supports two payment-provider modes:

- **Coinsnap** — for merchants who want to accept Bitcoin without running their own Bitcoin or Lightning payment infrastructure.
- **BTCPay Server** — for merchants who operate their own BTCPay Server and want to connect it to OpenCart.

BTCPay Server support was added in version 1.1.0.

## Installation

### Install from the OpenCart Extension Marketplace

1. Open the [Coinsnap OpenCart extension page](https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=45998).
2. Download the latest extension version.
3. In OpenCart administration, go to **Extensions → Installer**.
4. Upload the downloaded ZIP file.
5. Install the Coinsnap payment extension.

### Install from GitHub

You can also download the source code from this repository and install it through the OpenCart extension installer.

## Configuration

After installation:

1. Go to **Extensions → Extensions**.
2. Select **Payments** as the extension type.
3. Find **Coinsnap** and install/enable it.
4. Open the extension settings.
5. Select **Coinsnap** or **BTCPay Server** as your provider.
6. Enter the relevant connection data:
   - Coinsnap Store ID and API key, or
   - BTCPay Server URL, Store ID and API key.
7. Map OpenCart order statuses to payment gateway statuses.
8. Configure redirect and optional Bitcoin discount settings.
9. Save the configuration and test the payment flow.

Recommended order-status mapping:

- **Pending** → New
- **Expired** → Expired
- **Complete** → Settled
- **Processing** → Processing

## Bitcoin discount

Version 1.2.0 adds an optional Bitcoin discount that can be configured as a fixed amount or percentage.

To use it:

1. Install the **Coinsnap Bitcoin Discount** extension included with the package.
2. Open the relevant OpenCart total-extension settings.
3. Enable the discount and configure the desired value.
4. The discount is applied when Bitcoin or Lightning is selected as the payment method.

## Resources

- [Coinsnap OpenCart demo store](https://opencart.coinsnap.org/)
- [Coinsnap for OpenCart article](https://coinsnap.io/coinsnap-for-opencart-payment-plugin/)
- [OpenCart Extension Marketplace listing](https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=45998)
- [Coinsnap Wallet](https://coinsnap.io/wallet/)
- [Coinsnap API documentation](https://docs.coinsnap.io/)
- [Coinsnap merchant account](https://app.coinsnap.io/)
- [Coinsnap website](https://coinsnap.io/)
- [Telegram community and support](https://t.me/coinsnap)

> **Brand note:** Coinsnap is Bitcoin payment software for merchants and is not the provider of the similarly named coin identifier app "CoinSnap".

## Frequently Asked Questions

### What are the requirements for accepting Bitcoin in my OpenCart store?

You need an OpenCart 4 store and either a Coinsnap merchant account or your own BTCPay Server. When using Coinsnap, you do not need to operate your own Lightning node or Bitcoin payment server.

### Can customers pay with both Lightning and Bitcoin on-chain?

Yes. Coinsnap supports Bitcoin payments via both the Lightning Network and Bitcoin on-chain.

### Can I use my own BTCPay Server instead of Coinsnap?

Yes. BTCPay Server is supported as an alternative payment provider.

### Which Bitcoin or Lightning wallets can I use?

You can use a compatible Bitcoin or Lightning wallet for settlement. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Other compatible wallets may include solutions such as Wallet of Satoshi, Blink and Zeus, depending on the settlement setup you choose.

### Does Coinsnap hold my Bitcoin?

Coinsnap follows a non-custodial approach and does not hold merchant Bitcoin. Payments are settled to the merchant's configured wallet or payment destination.

### Can I offer a discount for Bitcoin payments?

Yes. Version 1.2.0 includes an optional Coinsnap Bitcoin Discount extension that supports fixed or percentage discounts.

### How are payment status changes handled?

The extension uses webhooks to receive payment status changes and maps them to the configured OpenCart order statuses.

### Can I customize the redirect after payment?

Yes. The extension supports both an automatic redirect option and a custom redirect page after payment.

### Is the extension available worldwide?

Coinsnap is designed for merchants worldwide. Bitcoin and Lightning payments can be accepted from customers globally, subject to any legal or service restrictions that may apply in the merchant's jurisdiction.

### Where can I get support?

Use the support area in your Coinsnap merchant account or join the official [Coinsnap Telegram channel](https://t.me/coinsnap).

## Changelog

### 1.2.0 — 2026-04-06

- Added Coinsnap Bitcoin discount on checkout with fixed-amount or percentage options.
- Fixed storage of the selected payment gateway.
- Updated the Coinsnap PHP library.

### 1.1.1 — 2025-10-07

- Added handling for currencies not supported by BTCPay Server during invoice creation.
- Added custom redirect after payment.
- Added automatic redirect after payment option.

### 1.1.0 — 2025-10-04

- Updated Coinsnap PHP library.
- Added BTCPay Server as a Bitcoin payment gateway.
- Added minimum payment checks.
- Added connection-status notice.
- Added webhook persistence and signature validation.

### 1.0.0 — 2024-03-29

- Initial release.
