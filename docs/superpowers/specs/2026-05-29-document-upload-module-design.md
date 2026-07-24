# XFE DocumentUpload Module - Design Specification

## Overview

A Magento 1 community module that provides document upload functionality for shipping carriers. Uses a strategy pattern to support multiple carriers, with Colissimo implemented as the first carrier adapter based on the Colissimo API Documents REST API.

## Module Information

- **Module Name**: `XFE_DocumentUpload`
- **Code Pool**: `community`
- **Base Path**: `app/code/community/XFE/DocumentUpload/`
- **Dependencies**: `Mage_Sales`, `Mage_Adminhtml`

## Architecture

### Strategy Pattern

Each carrier is an independent adapter class implementing a common interface:

```
Model/Carrier/
├── Interface.php       # Contract: upload(), getDocumentTypes(), getCarrierCode()
├── Abstract.php        # Shared logic: HTTP requests, file validation, error formatting
└── Colissimo.php       # Colissimo implementation: storedocument API call
```

**Interface contract**:
- `upload(array $params): array` — Upload a document, return result with documentId or errors
- `getDocumentTypes(): array` — Return supported document type codes and labels
- `getCarrierCode(): string` — Return carrier identifier (e.g. 'colissimo')
- `getCarrierName(): string` — Return display name
- `isEnabled(): bool` — Check if carrier is enabled in system config
- `validateFile(array $fileInfo): array` — Validate file format and size

### Directory Structure

```
app/code/community/XFE/DocumentUpload/
├── etc/
│   ├── config.xml
│   └── system.xml
├── Block/
│   └── Upload/
│       └── Form.php
├── Helper/
│   └── Data.php
├── Model/
│   └── Carrier/
│       ├── Interface.php
│       ├── Abstract.php
│       └── Colissimo.php
└── controllers/
    ├── UploadController.php
    └── Adminhtml/
        └── DocumentuploadController.php

app/etc/modules/
└── XFE_DocumentUpload.xml

app/design/frontend/exp5/default/
├── layout/
│   └── xfe_documentupload.xml
└── template/
    └── xfe_documentupload/
        └── upload.phtml

app/design/adminhtml/default/default/
├── layout/
│   └── xfe_documentupload.xml
└── template/
    └── xfe_documentupload/
        └── upload.phtml

skin/frontend/exp5/default/
├── css/
│   └── xfe_documentupload.css
└── js/
    └── xfe_documentupload.js

skin/adminhtml/default/default/
├── css/
│   └── xfe_documentupload.css
└── js/
    └── xfe_documentupload.js
```

## Colissimo API Integration

### API Reference

- **Base URL**: `https://ws.colissimo.fr/api-document/`
- **Endpoint**: `POST /api-document/rest/storedocument`
- **Content-Type**: `multipart/form-data`

### Authentication

Credentials are sent in HTTP headers (not in body). Two modes:
1. `login` + `password` headers
2. `apiKey` header

Only one mode should be used at a time.

### Request Parameters (Body - form-data)

| Parameter | Format | Required | Description |
|-----------|--------|----------|-------------|
| accountNumber | AN | Yes | Account number (from system config) |
| parcelNumber | AN | Yes | Parcel tracking number |
| documentType | AN | Yes | Functional nature code |
| file | Binary | Yes | File binary content |
| filename | AN | Yes | Original file name |
| parcelNumberList | AN | No | Comma-separated list for multi-parcel |

### Supported Document Types

| Code | Description |
|------|-------------|
| CN23 | CN23 customs declaration |
| CERTIFICATE_OF_ORIGIN | Certificate of origin |
| EXPORT_LICENSE | Export license |
| COMMERCIAL_INVOICE | Commercial invoice |
| OTHER | Other document |

### File Constraints

- **Accepted formats**: PDF, JPG, PNG, TIFF
- **Maximum size**: 500 KB
- **MIME type validation**: Required (error code 159 if invalid)

### Response

**Success** (errorCode: "000"):
```json
{
    "errorCode": "000",
    "errorLabel": "OK",
    "documentId": "50c82f93-015f-3c41-a841-07746eee6510.pdf"
}
```

**Error** (errorCode != "000"):
```json
{
    "errorCode": "001",
    "errorLabel": "CAB NOT FOUND",
    "errors": [
        { "code": 153, "message": "Parcel not found" }
    ]
}
```

### Key Error Codes

| Code | Message |
|------|---------|
| 000 | OK |
| 001-003 | Not found errors |
| 004 | Credential invalid |
| 005 | Body invalid |
| 148 | Invalid document type |
| 149 | Invalid account number |
| 150 | Invalid parcel number |
| 413 | Document too large (>500KB) |
| 159 | Invalid MIME type |

## Frontend Upload Page

### Route

- **Frontend**: `xfe_documentupload/upload/index`
- **Admin**: `adminhtml/documentupload/upload/index`

### Frontend Block

`XFE_DocumentUpload_Block_Upload_Form` provides:
- `getUploadUrl()` — AJAX upload endpoint URL
- `getEnabledCarriers()` — List of enabled carrier adapters
- `getDocumentTypeOptions($carrierCode)` — Document types for a carrier
- `getMaxFileSize()` — Maximum file size in bytes
- `getAcceptedFormats()` — Accepted file extensions

### Form Fields

1. **Carrier** (select) — Populated from enabled carrier adapters
2. **Parcel Number** (text input) — Package tracking number
3. **Document Type** (select) — Dynamically updated based on selected carrier
4. **File** (file input) — Accepts PDF/JPG/PNG/TIFF, max 500KB
5. **Parcel Number List** (text input, optional) — Comma-separated for multi-parcel

### UI/UX

- Alpine.js for reactive interaction (carrier change updates document types)
- Bootstrap 5 styling (consistent with existing pages)
- Client-side validation before submission (format + size check)
- Fetch API for AJAX upload
- Success: display documentId and success message
- Error: display error code, label, and detailed error messages

### Layout XML

Frontend layout `xfe_documentupload.xml`:
```xml
<xfe_documentupload_upload_index>
    <reference name="content">
        <block type="xfe_documentupload/upload_form" name="document.upload.form"
               template="xfe_documentupload/upload.phtml" />
    </reference>
</xfe_documentupload_upload_index>
```

## Backend (Admin) Upload

### Admin Controller

`XFE_DocumentUpload_Adminhtml_DocumentuploadController`:
- `indexAction()` — Render upload page
- `uploadAction()` — AJAX endpoint for file upload

### Admin Menu

```
Sales > Document Upload
```

Configured in `config.xml` under `<adminhtml><menu>`.

### Admin Block

Reuses `XFE_DocumentUpload_Block_Upload_Form` with admin-specific rendering.

## System Configuration

### Path

`System > Configuration > Sales > Document Upload`

### Configuration Sections (system.xml)

**Group: Colissimo**
| Field | Type | Description |
|-------|------|-------------|
| enabled | select (Yes/No) | Enable/disable Colissimo carrier |
| api_login | text | API login username |
| api_password | obscure | API password (encrypted) |
| api_key | obscure | API key (encrypted, alternative to login/password) |
| account_number | text | Colissimo account number |

Config paths follow: `xfe_documentupload/colissimo/enabled`, etc.

## Helper: Data.php

Key methods:
- `getCarrierAdapters()` — Returns all registered carrier adapter instances
- `getCarrierAdapter($code)` — Returns a specific carrier adapter by code
- `getDocumentTypeOptions($carrierCode)` — Document type options for a carrier
- `validateFile($file, $allowedTypes, $maxSize)` — File validation
- `getAcceptedMimeTypes()` — Returns `['application/pdf', 'image/jpeg', 'image/png', 'image/tiff']`
- `getMaxFileSize()` — Returns 512000 (500KB)

## Upload Flow

```
1. User opens upload page (frontend or admin)
2. User selects carrier (e.g., Colissimo) → document types update
3. User enters parcel number, selects document type, chooses file
4. Client-side validation (format, size)
5. AJAX POST to Magento controller (multipart/form-data)
6. Controller validates input
7. Controller delegates to carrier adapter (e.g., Colissimo::upload())
8. Adapter builds API request with credentials from system config
9. Adapter sends HTTP POST to Colissimo storedocument endpoint
10. Adapter parses response, returns structured result
11. Controller returns JSON to frontend
12. Frontend displays success/error feedback
```

## Extensibility

To add a new carrier:
1. Create `Model/Carrier/NewCarrier.php` implementing `Interface.php`
2. Add system config fields in `system.xml`
3. Register the carrier in `config.xml` under `<xfe_documentupload><carriers>`
4. The frontend form automatically picks up the new carrier
