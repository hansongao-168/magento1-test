/**
 * XFE Document Upload - Admin Popup Widget
 *
 * Provides a reusable JavaScript widget to open the document upload
 * form as a popup/lightbox from any admin page.
 *
 * Usage:
 *   <button onclick="XFE_DocumentUpload.openPopup()">Upload Document</button>
 *
 * Or automatically wire elements with the class 'xfe-upload-btn':
 *   <button class="xfe-upload-btn">Upload Document</button>
 *
 * @category   XFE
 * @package    XFE_DocumentUpload
 */
var XFE_DocumentUpload = {

	/** @var {string} Popup URL loaded from server */
	popupUrl: null,

	/** @var {object|null} Popup window reference */
	popupWindow: null,

	/**
	 * Initialize the widget - set the popup URL
	 *
	 * @param {string} url Absolute URL to the popup action
	 */
	init: function (url) {
		this.popupUrl = url;
		this._wireButtons();
	},

	/**
	 * Open the document upload popup
	 *
	 * Uses a lightweight modal overlay pattern compatible with
	 * both Magento 1 admin and modern admin themes.
	 *
	 * @param {object} [options] Optional params (carrier, parcel_number, etc.)
	 */
	openPopup: function (options) {
		if (!this.popupUrl) {
			alert('Document Upload popup URL is not configured.');
			return;
		}

		var self = this;

		// If a popup is already open, close it first
		if (this.popupWindow && !this.popupWindow.closed) {
			this.popupWindow.focus();
			return;
		}

		// Create popup modal overlay
		var overlay = document.createElement('div');
		overlay.id = 'xfe-upload-overlay';
		overlay.style.cssText =
			'position:fixed;top:0;left:0;width:100%;height:100%;' +
			'background:rgba(0,0,0,0.4);z-index:10000;' +
			'display:flex;align-items:center;justify-content:center;';

		// Create popup container
		var popup = document.createElement('div');
		popup.id = 'xfe-upload-popup';
		popup.style.cssText =
			'background:#fff;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,0.3);' +
			'width:640px;max-width:90%;max-height:90vh;overflow:auto;position:relative;';

		// Header with title and close button
		var header = document.createElement('div');
		header.style.cssText =
			'display:flex;align-items:center;justify-content:space-between;' +
			'padding:12px 16px;border-bottom:1px solid #e3e3e3;background:#f8f8f8;' +
			'border-radius:6px 6px 0 0;';
		header.innerHTML =
			'<h3 style="margin:0;font-size:14px;color:#333;">' +
			'Document Upload</h3>' +
			'<button onclick="XFE_DocumentUpload.closePopup()" ' +
			'style="background:none;border:none;font-size:20px;cursor:pointer;color:#666;' +
			'padding:0 4px;line-height:1;" title="Close">&times;</button>';

		// Content area (loading indicator then form loads)
		var content = document.createElement('div');
		content.id = 'xfe-upload-popup-content';
		content.style.cssText = 'padding:0;min-height:100px;';
		content.innerHTML = '<div style="text-align:center;padding:40px;color:#888;">' +
			'Loading...' +
			'</div>';

		popup.appendChild(header);
		popup.appendChild(content);
		overlay.appendChild(popup);
		document.body.appendChild(overlay);

		// Close on overlay click
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) {
				self.closePopup();
			}
		});

		// Close on Escape key
		var escHandler = function (e) {
			if (e.key === 'Escape') {
				self.closePopup();
				document.removeEventListener('keydown', escHandler);
			}
		};
		document.addEventListener('keydown', escHandler);

		// Load popup content via AJAX
		var xhr = new XMLHttpRequest();
		xhr.open('GET', this.popupUrl, true);
		xhr.onload = function () {
			if (xhr.status >= 200 && xhr.status < 300) {
				content.innerHTML = xhr.responseText;
				// Reinitialize Alpine.js on the new content
				if (typeof Alpine !== 'undefined') {
					Alpine.initTree(content);
				}
			} else {
				content.innerHTML = '<div style="padding:20px;color:#dc3545;">' +
					'<strong>Error loading upload form.</strong>' +
					'<p>' + xhr.statusText + '</p></div>';
			}
		};
		xhr.onerror = function () {
			content.innerHTML = '<div style="padding:20px;color:#dc3545;">' +
				'<strong>Network error. Please try again.</strong></div>';
		};
		xhr.send();
	},

	/**
	 * Close the document upload popup
	 */
	closePopup: function () {
		var overlay = document.getElementById('xfe-upload-overlay');
		if (overlay) {
			document.body.removeChild(overlay);
		}
	},

	/**
	 * Wire up all buttons with class 'xfe-upload-btn'
	 * to open the popup on click
	 */
	_wireButtons: function () {
		var self = this;
		var buttons = document.querySelectorAll('.xfe-upload-btn');
		for (var i = 0; i < buttons.length; i++) {
			buttons[i].addEventListener('click', function (e) {
				e.preventDefault();
				self.openPopup();
			});
		}
	}
};
