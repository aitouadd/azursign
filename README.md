<div align="center">

# AzurSign

**Electronic Signature Module for Dolibarr ERP**

[![Dolibarr](https://img.shields.io/badge/Dolibarr-%3E%3D%2017.0-blue)](https://www.dolibarr.org)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D%207.4-777BB4?logo=php)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPLv3-green)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange)](https://github.com/aitouadd/azursign/releases)

*Allow clients and users to sign commercial proposals directly inside Dolibarr — with a tamper-evident, stamped signed PDF generated on the fly.*

</div>

---

## Screenshots

| Signature Pad | Signed PDF Output |
|:---:|:---:|
| ![Signature pad modal with ERASE / VALIDATE / CANCEL](https://raw.githubusercontent.com/aitouadd/azursign/main/screenshots/signature-pad.png) | ![Signed PDF with signature stamp and metadata](https://raw.githubusercontent.com/aitouadd/azursign/main/screenshots/signed-pdf.png) |

---

## Features

- **Canvas signature pad** — draw your signature directly in the browser with erase/validate/cancel controls
- **Signed PDF generation** — the original proposal PDF is cloned and the signature image + signer name + date are stamped onto the last page
- **Full audit trail** — each signature is stored in the database with signer name, IP address, user-agent, SHA-256 hash, date and the path to the signed PDF
- **Legal disclaimer** — configurable legal acknowledgement text that must be accepted before signing (optional)
- **One-click access** — an **Sign Now** button is injected directly into the proposal card (Documents section); no page reload required
- **Auto-status update** — optionally transitions the proposal to *Signed* status automatically after a successful signature
- **Rights management** — dedicated `read` and `write` permission levels; admin users always have access
- **Multi-entity support** — fully compatible with Dolibarr multi-entity setups

---

## Requirements

| Requirement | Version |
|---|---|
| Dolibarr ERP | ≥ 17.0 |
| PHP | ≥ 7.4 |
| MySQL / MariaDB | Any version supported by Dolibarr |
| Module dependency | `modPropale` (Commercial Proposals) must be enabled |

---

## Installation

1. **Download** the module and copy the `azursign/` folder into your Dolibarr custom modules directory:

   ```
   dolibarr/custom/azursign/
   ```

2. **Activate** the module in Dolibarr:
   > Home → Setup → Modules/Applications → *search "AzurSign"* → Enable

3. **Configure** the module:
   > Home → Setup → Modules/Applications → AzurSign → *Setup*

---

## Configuration

Navigate to **Home → Setup → AzurSign** to configure the following parameters:

| Parameter | Default | Description |
|---|---|---|
| Require legal acknowledgement | Yes | Forces signers to accept the legal disclaimer before drawing their signature |
| Legal text | *Preset FR* | The disclaimer text shown on the signature screen and stored in the audit trail |
| Log IP address | Yes | Records the signer's IP address for traceability |
| Auto-set proposal to Signed | Yes | Automatically moves the proposal to *Signed* status after a successful AzurSign flow |

---

## How It Works

```
Proposal Card (propalcard hook)
        │
        ▼
  [Sign Now] button
        │
        ▼
  sign.php — signature canvas
        │  ① Signer draws signature
        │  ② Legal disclaimer acceptance
        │  ③ Signer name input
        ▼
  POST → sign.php (action=sign)
        │
        ├─► Signature PNG saved to disk
        ├─► Source PDF located (last_main_doc or latest in /propal/)
        ├─► Signed PDF stamped (signature image + "Signer: X / Date: Y")
        ├─► DB row inserted (llx_azursign_signature)
        ├─► Private note added to proposal
        └─► [optional] Proposal status → Signed
```

---

## File Structure

```
azursign/
├── admin/
│   └── setup.php                        # Module configuration page
├── class/
│   ├── actions_azursign.class.php       # Hook handler (injects Sign Now button)
│   └── azursignsignature.class.php      # DB model for signature traces
├── core/
│   └── modules/
│       └── modAzursign.class.php        # Module descriptor
├── langs/
│   └── fr_FR/
│       └── azursign.lang                # French translations
├── sql/
│   └── llx_azursign_signature.sql       # Table creation script
└── sign.php                             # Signature canvas + PDF generation
```

---

## Database

The module creates one table: `llx_azursign_signature`

| Column | Type | Description |
|---|---|---|
| `rowid` | INT AUTO_INCREMENT | Primary key |
| `fk_propal` | INT | Linked proposal ID |
| `fk_soc` | INT | Linked company ID |
| `propal_ref` | VARCHAR(128) | Proposal reference |
| `signer_name` | VARCHAR(128) | Full name of the signer |
| `signer_ip` | VARCHAR(64) | IP address at time of signing |
| `user_agent` | VARCHAR(255) | Browser user-agent string |
| `signature_hash` | VARCHAR(128) | SHA-256 hash of the signature PNG |
| `signature_image` | VARCHAR(255) | Path to the saved signature PNG |
| `signed_pdf` | VARCHAR(255) | Path to the generated signed PDF |
| `legal_text` | TEXT | Legal disclaimer text accepted by signer |
| `legal_accepted` | SMALLINT | 1 if legal disclaimer was accepted |
| `date_sign` | DATETIME | Exact date and time of signature |
| `fk_user_sign` | INT | Dolibarr user who performed the signature |

---

## Permissions

| Right key | Description |
|---|---|
| `azursign` → `read` | View signature information on proposals |
| `azursign` → `write` | Sign proposals using AzurSign |

> Admin users bypass permission checks and always have full access.

---

## License

This module is distributed under the **GNU General Public License v3.0**.  
See [GPL-3.0](https://www.gnu.org/licenses/gpl-3.0.en.html) for details.

---

## Author

**ITCAMELION SARL AU**  
Contact: [Y.AITOUADDI@ITCAMELION.COM](mailto:Y.AITOUADDI@ITCAMELION.COM)  
GitHub: [github.com/aitouadd](https://github.com/aitouadd)
