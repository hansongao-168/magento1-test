# XFE DocumentUpload Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Magento 1 community module for uploading shipping documents to carrier APIs, starting with Colissimo.

**Architecture:** Strategy pattern with carrier adapter interface. Each carrier is an independent class. System config stores API credentials. Frontend and admin share Block logic.

**Tech Stack:** Magento 1.9, PHP, Alpine.js, Bootstrap 5, Colissimo REST API (multipart/form-data)

**Spec:** `docs/superpowers/specs/2026-05-29-document-upload-module-design.md`

---

## File Structure

| File | Responsibility |
|------|---------------|
| `app/etc/modules/XFE_DocumentUpload.xml` | Module declaration |
| `app/code/community/XFE/DocumentUpload/etc/config.xml` | Routes, blocks, helpers, admin menu |
| `app/code/community/XFE/DocumentUpload/etc/system.xml` | System > Configuration fields |
| `app/code/community/XFE/DocumentUpload/Model/Carrier/Interface.php` | Carrier adapter contract |
| `app/code/community/XFE/DocumentUpload/Model/Carrier/Abstract.php` | Shared HTTP + validation |
| `app/code/community/XFE/DocumentUpload/Model/Carrier/Colissimo.php` | Colissimo API adapter |
| `app/code/community/XFE/DocumentUpload/Helper/Data.php` | Carrier registry, file validation |
| `app/code/community/XFE/DocumentUpload/Block/Upload/Form.php` | Upload form block |
| `app/code/community/XFE/DocumentUpload/controllers/UploadController.php` | Frontend AJAX |
| `app/code/community/XFE/DocumentUpload/controllers/Adminhtml/DocumentuploadController.php` | Admin controller |
| `app/design/frontend/exp5/default/layout/xfe_documentupload.xml` | Frontend layout |
| `app/design/frontend/exp5/default/template/xfe_documentupload/upload.phtml` | Frontend template |
| `app/design/adminhtml/default/default/layout/xfe_documentupload.xml` | Admin layout |
| `app/design/adminhtml/default/default/template/xfe_documentupload/upload.phtml` | Admin template |
| `skin/frontend/exp5/default/css/xfe_documentupload.css` | Frontend styles |
| `skin/frontend/exp5/default/js/xfe_documentupload.js` | Frontend Alpine.js |
| `skin/adminhtml/default/default/css/xfe_documentupload.css` | Admin styles |
| `skin/adminhtml/default/default/js/xfe_documentupload.js` | Admin JS |

---

### Task 1: Module Declaration

**Files:** Create `app/etc/modules/XFE_DocumentUpload.xml`

- [ ] Create the module declaration XML:

```xml
<?xml version="1.0"?>
<config>
    <modules>
        <XFE_DocumentUpload>
            <active>true</active>
            <codePool>community</codePool>
            <depends>
                <Mage_Sales />
                <Mage_Adminhtml />
            </depends>
        </XFE_DocumentUpload>
    </modules>
</config>
```

- [ ] Clear `var/cache/`, check System > Configuration > Advanced — `XFE_DocumentUpload` should appear.

---

### Task 2: Module Config (config.xml)

**Files:** Create `app/code/community/XFE/DocumentUpload/etc/config.xml`

- [ ] Create config.xml with frontend/admin routers, block/helper aliases, admin menu, carrier registry:

```xml
<?xml version="1.0"?>
<config>
    <modules>
        <XFE_DocumentUpload>
            <version>1.0.0</version>
        </XFE_DocumentUpload>
    </modules>
    <global>
        <blocks>
            <xfe_documentupload>
                <class>XFE_DocumentUpload_Block</class>
            </xfe_documentupload>
        </blocks>
        <helpers>
            <xfe_documentupload>
                <class>XFE_DocumentUpload_Helper</class>
            </xfe_documentupload>
        </helpers>
        <models>
            <xfe_documentupload>
                <class>XFE_DocumentUpload_Model</class>
            </xfe_documentupload>
        </models>
    </global>
    <frontend>
        <routers>
            <xfe_documentupload>
                <use>standard</use>
                <args>
                    <module>XFE_DocumentUpload</module>
                    <frontName>xfe_documentupload</frontName>
                </args>
            </xfe_documentupload>
        </routers>
        <layout>
            <updates>
                <xfe_documentupload>
                    <file>xfe_documentupload.xml</file>
                </xfe_documentupload>
            </updates>
        </layout>
    </frontend>
    <admin>
        <routers>
            <adminhtml>
                <args>
                    <modules>
                        <XFE_DocumentUpload before="Mage_Adminhtml">XFE_DocumentUpload_Adminhtml</XFE_DocumentUpload>
                    </modules>
                </args>
            </adminhtml>
        </routers>
    </admin>
    <adminhtml>
        <menu>
            <sales>
                <children>
                    <xfe_documentupload translate="title">
                        <title>Document Upload</title>
                        <sort_order>240</sort_order>
                        <action>adminhtml/documentupload/index</action>
                    </xfe_documentupload>
                </children>
            </sales>
        </menu>
        <layout>
            <updates>
                <xfe_documentupload>
                    <file>xfe_documentupload.xml</file>
                </xfe_documentupload>
            </updates>
        </layout>
    </adminhtml>
    <default>
        <xfe_documentupload>
            <carriers>
                <colissimo>
                    <model>xfe_documentupload/carrier_colissimo</model>
                </colissimo>
            </carriers>
        </xfe_documentupload>
    </default>
</config>
```

- [ ] Clear cache, verify no XML parse errors.

---

### Task 3: Carrier Interface + Abstract Base

**Files:**
- Create `app/code/community/XFE/DocumentUpload/Model/Carrier/Interface.php`
- Create `app/code/community/XFE/DocumentUpload/Model/Carrier/Abstract.php`

- [ ] Create Interface.php with contract methods: `upload(array $params)`, `getDocumentTypes()`, `getCarrierCode()`, `getCarrierName()`, `isEnabled()`, `validateFile(array $fileInfo)`

- [ ] Create Abstract.php implementing Interface with shared logic:
  - `$_configPathPrefix` for config path
  - `isEnabled()` via `Mage::getStoreConfigFlag`
  - `validateFile()` — check extension (pdf/jpg/png/tiff), MIME type, size (max 512000 bytes)
  - `_httpPostMultipart($url, $headers, $fields, $files)` — cURL multipart/form-data POST
  - `_successResult($documentId)` / `_errorResult($errors, $code, $label)` — result formatters

See spec for full method signatures and implementation details.

---

### Task 4: Colissimo Carrier Adapter

**Files:** Create `app/code/community/XFE/DocumentUpload/Model/Carrier/Colissimo.php`

- [ ] Create Colissimo adapter extending Abstract:
  - `$_configPathPrefix = 'xfe_documentupload/colissimo'`
  - `getCarrierCode()` returns `'colissimo'`
  - `getCarrierName()` returns `'Colissimo'`
  - `getDocumentTypes()` returns: CN23, CERTIFICATE_OF_ORIGIN, EXPORT_LICENSE, COMMERCIAL_INVOICE, OTHER
  - `upload(array $params)`:
    1. Read credentials from config: `api_login`, `api_password`, `api_key`, `account_number`
    2. Build auth headers (login+password OR apiKey)
    3. Build form fields: accountNumber, parcelNumber, documentType, filename, parcelNumberList
    4. Build file array with path and name
    5. POST to `https://ws.colissimo.fr/api-document/rest/storedocument`
    6. Parse JSON response — check errorCode === "000"
    7. Return successResult or errorResult

---

### Task 5: Helper Data.php

**Files:** Create `app/code/community/XFE/DocumentUpload/Helper/Data.php`

- [ ] Create helper extending `Mage_Core_Helper_Abstract`:
  - `getCarrierAdapters()` — reads `<default><xfe_documentupload><carriers>` from config, instantiates models
  - `getEnabledCarriers()` — filters by `isEnabled()`
  - `getCarrierAdapter($code)` — single adapter by code
  - `getDocumentTypeOptions($carrierCode)` — delegates to adapter
  - `getAcceptedMimeTypes()` — returns array of MIME types
  - `getMaxFileSize()` — returns 512000
  - `getAcceptedExtensions()` — returns `.pdf,.jpg,.jpeg,.png,.tiff,.tif`

---

### Task 6: System Configuration (system.xml)

**Files:** Create `app/code/community/XFE/DocumentUpload/etc/system.xml`

- [ ] Create system.xml with section under `Sales`:
  - Section: `xfe_documentupload`, tab: `sales`
  - Group: `colissimo`
  - Fields:
    - `enabled` — select (Yes/No), default 0
    - `api_login` — text
    - `api_password` — obscure (encrypted)
    - `api_key` — obscure (encrypted)
    - `account_number` — text
  - Config paths: `xfe_documentupload/colissimo/<field>`

---

### Task 7: Block Upload Form

**Files:** Create `app/code/community/XFE/DocumentUpload/Block/Upload/Form.php`

- [ ] Create block extending `Mage_Core_Block_Template`:
  - `getUploadUrl()` — returns AJAX upload URL via `getUrl('xfe_documentupload/upload/upload')`
  - `getDocumentTypesJsonUrl()` — returns URL for AJAX document types endpoint
  - `getEnabledCarriers()` — delegates to helper
  - `getDocumentTypeOptions($carrierCode)` — delegates to helper
  - `getMaxFileSize()` — delegates to helper
  - `getAcceptedExtensions()` — delegates to helper

---

### Task 8: Frontend Controller

**Files:** Create `app/code/community/XFE/DocumentUpload/controllers/UploadController.php`

- [ ] Create frontend controller extending `Mage_Core_Controller_Front_Action`:
  - `indexAction()` — loadLayout + renderLayout (renders the upload page)
  - `uploadAction()` — AJAX endpoint:
    1. Get POST params: carrier_code, parcel_number, document_type, parcel_number_list
    2. Get uploaded file from `$_FILES['file']`
    3. Validate required fields
    4. Get carrier adapter via helper
    5. Call `$adapter->validateFile()` on the file
    6. Call `$adapter->upload()` with params
    7. Return JSON response (success/error)
  - `documentTypesAction()` — AJAX: returns document types for a given carrier_code param

---

### Task 9: Frontend Layout XML

**Files:** Create `app/design/frontend/exp5/default/layout/xfe_documentupload.xml`

- [ ] Create layout XML:
  - Handle `xfe_documentupload_upload_index`: add block `xfe_documentupload/upload_form` with template `xfe_documentupload/upload.phtml` in content reference
  - Add CSS via `<action method="addItem">` for `skin_css` `css/xfe_documentupload.css`
  - Add JS via `<action method="addItem">` for `skin_js` `js/xfe_documentupload.js`

---

### Task 10: Frontend Upload Template (phtml)

**Files:** Create `app/design/frontend/exp5/default/template/xfe_documentupload/upload.phtml`

- [ ] Create upload page template with Alpine.js + Bootstrap 5:
  - Page title: "Upload Document"
  - Carrier select dropdown (from `$this->getEnabledCarriers()`)
  - On carrier change: fetch document types via AJAX, update document type dropdown
  - Parcel number text input
  - Document type select dropdown (dynamically populated)
  - File input with accept attribute and size validation display
  - Optional parcel number list text input
  - Upload button
  - Result area: success shows documentId, error shows error messages
  - Alpine.js x-data component for state management and Fetch API calls
  - Loading spinner during upload

---

### Task 11: Frontend JS (Alpine.js Component)

**Files:** Create `skin/frontend/exp5/default/js/xfe_documentupload.js`

- [ ] Create Alpine.js component `documentUpload()`:
  - State: carrier, parcelNumber, documentType, file, parcelNumberList, loading, result, errors
  - `init()`: set initial state
  - `onCarrierChange()`: fetch document types from AJAX endpoint, reset documentType
  - `onFileChange(event)`: validate file size and extension client-side
  - `submitUpload()`: build FormData, POST via fetch, parse response, update result/errors
  - `resetForm()`: clear all fields and results
  - Include documentTypesUrl and uploadUrl as data attributes from block

---

### Task 12: Frontend CSS

**Files:** Create `skin/frontend/exp5/default/css/xfe_documentupload.css`

- [ ] Create styles for upload page:
  - `.xfe-document-upload` container styling
  - Form card with border-radius 12px, shadow
  - File drop zone styling (dashed border)
  - Success/error alert styling
  - Loading overlay/spinner
  - Responsive layout for form fields

---

### Task 13: Admin Controller

**Files:** Create `app/code/community/XFE/DocumentUpload/controllers/Adminhtml/DocumentuploadController.php`

- [ ] Create admin controller extending `Mage_Adminhtml_Controller_Action`:
  - `_isAllowed()` — return true (or check ACL if needed)
  - `indexAction()` — loadLayout + renderLayout
  - `uploadAction()` — same logic as frontend controller's uploadAction
  - `documentTypesAction()` — same as frontend

---

### Task 14: Admin Layout + Template

**Files:**
- Create `app/design/adminhtml/default/default/layout/xfe_documentupload.xml`
- Create `app/design/adminhtml/default/default/template/xfe_documentupload/upload.phtml`

- [ ] Create admin layout XML:
  - Handle `adminhtml_documentupload_index`: add block to content
  - Add CSS/JS references

- [ ] Create admin template:
  - Similar to frontend but uses admin upload URL
  - Wraps in admin page structure
  - Uses `$this->getUrl('adminhtml/documentupload/upload')` for AJAX

---

### Task 15: Admin CSS + JS

**Files:**
- Create `skin/adminhtml/default/default/css/xfe_documentupload.css`
- Create `skin/adminhtml/default/default/js/xfe_documentupload.js`

- [ ] Create admin CSS (can be same as frontend with minor admin adjustments)

- [ ] Create admin JS (same Alpine.js component as frontend)

---

### Task 16: Final Verification

- [ ] Clear all Magento caches
- [ ] Verify module appears in System > Configuration > Advanced
- [ ] Verify System > Configuration > Sales > Document Upload shows Colissimo settings
- [ ] Configure Colissimo with test credentials
- [ ] Enable Colissimo carrier
- [ ] Access frontend upload page at `/xfe_documentupload/upload/index`
- [ ] Verify carrier dropdown shows Colissimo
- [ ] Verify document type dropdown populates on carrier selection
- [ ] Test file upload with a valid PDF < 500KB
- [ ] Verify error handling with invalid file types
- [ ] Verify error handling with oversized files
- [ ] Access admin upload page via Sales > Document Upload menu
- [ ] Verify admin upload works same as frontend
- [ ] Test with Colissimo API (if credentials available) or verify request structure via logs
