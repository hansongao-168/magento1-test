/**
 * XFE Document Upload - Alpine.js Component (Admin)
 *
 * Handles carrier selection, dynamic document type loading,
 * multi-account selection, file validation, and AJAX upload.
 */
function documentUpload() {
	return {
		// --- State ---
		carrier: '',
		accountIndex: 0,
		parcelNumber: '',
		documentType: '',
		parcelNumberList: '',
		selectedFile: null,
		isDragging: false,
		loading: false,
		result: null,
		errors: [],
		docTypeOptions: [],
		accounts: [],

		// --- Config (set by init) ---
		uploadUrl: '',
		docTypesUrl: '',
		accountsUrl: '',
		allDocTypes: {},
		maxFileSize: 512000,

		/**
		 * Initialize the component with config from the Block
		 */
		init(uploadUrl, docTypesUrl, accountsUrl, allDocTypes, maxFileSize) {
			this.uploadUrl = uploadUrl;
			this.docTypesUrl = docTypesUrl;
			this.accountsUrl = accountsUrl;
			this.allDocTypes = allDocTypes;
			this.maxFileSize = maxFileSize;
		},

		/**
		 * When carrier selection changes, update document type options
		 * and fetch accounts list
		 */
		onCarrierChange() {
			this.documentType = '';
			this.docTypeOptions = [];
			this.accountIndex = 0;
			this.accounts = [];

			if (!this.carrier) return;

			// Update document types
			if (this.allDocTypes && this.allDocTypes[this.carrier]) {
				var types = this.allDocTypes[this.carrier];
				this.docTypeOptions = Object.keys(types).map(function (code) {
					return { value: code, label: types[code] };
				});
			}

			// Fetch accounts for this carrier
			if (this.accountsUrl) {
				var self = this;
				fetch(this.accountsUrl + '?carrier_code=' + encodeURIComponent(this.carrier))
					.then(function (r) { return r.json(); })
					.then(function (data) {
						self.accounts = data.accounts || [];
					})
					.catch(function () {
						self.accounts = [];
					});
			}
		},

		/**
		 * Handle file selection from input change
		 */
		onFileChange(event) {
			var file = event.target.files[0];
			if (file) {
				this.selectedFile = file;
				this.errors = [];
				this.result = null;
			}
		},

		/**
		 * Handle file drop
		 */
		onFileDrop(event) {
			this.isDragging = false;
			var file = event.dataTransfer.files[0];
			if (file) {
				this.selectedFile = file;
				this.errors = [];
				this.result = null;
			}
		},

		/**
		 * Clear selected file
		 */
		clearFile() {
			this.selectedFile = null;
			if (this.$refs.fileInput) {
				this.$refs.fileInput.value = '';
			}
			this.result = null;
			this.errors = [];
		},

		/**
		 * Format file size for display
		 */
		formatFileSize(bytes) {
			if (bytes < 1024) return bytes + ' B';
			if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
			return (bytes / 1048576).toFixed(1) + ' MB';
		},

		/**
		 * Submit the upload form via AJAX
		 */
		async submitUpload() {
			this.errors = [];
			this.result = null;

			if (!this.carrier) {
				this.errors.push('Please select a carrier.');
				return;
			}
			if (!this.parcelNumber.trim()) {
				this.errors.push('Parcel number is required.');
				return;
			}
			if (!this.documentType) {
				this.errors.push('Document type is required.');
				return;
			}
			if (!this.selectedFile) {
				this.errors.push('Please select a file.');
				return;
			}

			if (this.selectedFile.size > this.maxFileSize) {
				var maxKb = Math.round(this.maxFileSize / 1024);
				this.errors.push('File too large. Maximum size: ' + maxKb + ' KB');
				return;
			}

			var ext = this.selectedFile.name.split('.').pop().toLowerCase();
			var allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif'];
			if (allowedExts.indexOf(ext) === -1) {
				this.errors.push('Invalid file format. Accepted: PDF, JPG, PNG, TIFF');
				return;
			}

			var formData = new FormData();
			formData.append('carrier_code', this.carrier);
			formData.append('parcel_number', this.parcelNumber.trim());
			formData.append('document_type', this.documentType);
			formData.append('account_index', this.accountIndex);
			formData.append('file', this.selectedFile);
			if (this.parcelNumberList.trim()) {
				formData.append('parcel_number_list', this.parcelNumberList.trim());
			}

			this.loading = true;
			try {
				var response = await fetch(this.uploadUrl, {
					method: 'POST',
					body: formData,
				});
				var json = await response.json();

				if (json.success) {
					this.result = json;
					this.errors = [];
				} else {
					this.errors = json.errors || ['Upload failed. Please try again.'];
					this.result = null;
				}
			} catch (e) {
				this.errors = ['Network error. Please check your connection and try again.'];
				this.result = null;
			} finally {
				this.loading = false;
			}
		}
	};
}
