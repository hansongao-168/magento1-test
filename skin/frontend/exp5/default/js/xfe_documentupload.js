/**
 * XFE Document Upload - Alpine.js Component
 *
 * Handles carrier selection, dynamic document type loading,
 * file validation, and AJAX upload to the Magento controller.
 */
function documentUpload() {
	return {
		// --- State ---
		carrier: '',
		parcelNumber: '',
		documentType: '',
		parcelNumberList: '',
		selectedFile: null,
		isDragging: false,
		loading: false,
		result: null,
		errors: [],
		docTypeOptions: [],

		// --- Config (set by init) ---
		uploadUrl: '',
		docTypesUrl: '',
		allDocTypes: {},
		maxFileSize: 512000,

		/**
		 * Initialize the component with config from the Block
		 */
		init(uploadUrl, docTypesUrl, allDocTypes, maxFileSize) {
			this.uploadUrl = uploadUrl;
			this.docTypesUrl = docTypesUrl;
			this.allDocTypes = allDocTypes;
			this.maxFileSize = maxFileSize;
		},

		/**
		 * When carrier selection changes, update document type options
		 */
		onCarrierChange() {
			this.documentType = '';
			this.docTypeOptions = [];

			if (!this.carrier) return;

			// Use pre-loaded document types from Block
			if (this.allDocTypes && this.allDocTypes[this.carrier]) {
				var types = this.allDocTypes[this.carrier];
				this.docTypeOptions = Object.keys(types).map(function (code) {
					return { value: code, label: types[code] };
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
			// Client-side validation
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

			// Validate file size
			if (this.selectedFile.size > this.maxFileSize) {
				var maxKb = Math.round(this.maxFileSize / 1024);
				this.errors.push('File too large. Maximum size: ' + maxKb + ' KB');
				return;
			}

			// Validate file extension
			var ext = this.selectedFile.name.split('.').pop().toLowerCase();
			var allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif'];
			if (allowedExts.indexOf(ext) === -1) {
				this.errors.push('Invalid file format. Accepted: PDF, JPG, PNG, TIFF');
				return;
			}

			// Build FormData
			var formData = new FormData();
			formData.append('carrier_code', this.carrier);
			formData.append('parcel_number', this.parcelNumber.trim());
			formData.append('document_type', this.documentType);
			formData.append('file', this.selectedFile);
			if (this.parcelNumberList.trim()) {
				formData.append('parcel_number_list', this.parcelNumberList.trim());
			}

			// Send request
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
