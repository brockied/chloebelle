/**
 * Enhanced Upload Handler for Chloe Belle Website
 * Handles photo and video uploads with previews and validation
 */

class UploadHandler {
    constructor() {
        this.maxImageSize = 10 * 1024 * 1024; // 10MB
        this.maxVideoSize = 50 * 1024 * 1024; // 50MB
        this.allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        this.allowedVideoTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv'];
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupDragAndDrop();
    }
    
    setupEventListeners() {
        // Photo upload handler
        const photoInput = document.getElementById('photoUpload');
        if (photoInput) {
            photoInput.addEventListener('change', (e) => this.handleFileSelect(e, 'image'));
        }
        
        // Video upload handler
        const videoInput = document.getElementById('videoUpload');
        if (videoInput) {
            videoInput.addEventListener('change', (e) => this.handleFileSelect(e, 'video'));
        }
        
        // Form submission handler
        const form = document.getElementById('createPostForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
    }
    
    setupDragAndDrop() {
        const createPostCard = document.querySelector('.create-post-card');
        if (!createPostCard) return;
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            createPostCard.addEventListener(eventName, this.preventDefaults, false);
            document.body.addEventListener(eventName, this.preventDefaults, false);
        });
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            createPostCard.addEventListener(eventName, () => this.highlight(createPostCard), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            createPostCard.addEventListener(eventName, () => this.unhighlight(createPostCard), false);
        });
        
        // Handle dropped files
        createPostCard.addEventListener('drop', (e) => this.handleDrop(e), false);
    }
    
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    highlight(element) {
        element.classList.add('drag-over');
    }
    
    unhighlight(element) {
        element.classList.remove('drag-over');
    }
    
    handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            this.processFile(files[0]);
        }
    }
    
    handleFileSelect(e, type) {
        const file = e.target.files[0];
        if (file) {
            this.processFile(file, type);
        }
    }
    
    processFile(file, expectedType = null) {
        // Validate file
        const validation = this.validateFile(file, expectedType);
        if (!validation.valid) {
            this.showError(validation.error);
            return;
        }
        
        // Clear other file inputs
        if (this.isImage(file)) {
            document.getElementById('videoUpload').value = '';
        } else if (this.isVideo(file)) {
            document.getElementById('photoUpload').value = '';
        }
        
        // Show file info and preview
        this.showFilePreview(file);
    }
    
    validateFile(file, expectedType = null) {
        // Check file size
        if (this.isImage(file) && file.size > this.maxImageSize) {
            return {
                valid: false,
                error: `Image file too large. Maximum size is ${this.formatFileSize(this.maxImageSize)}.`
            };
        }
        
        if (this.isVideo(file) && file.size > this.maxVideoSize) {
            return {
                valid: false,
                error: `Video file too large. Maximum size is ${this.formatFileSize(this.maxVideoSize)}.`
            };
        }
        
        // Check file type
        if (!this.allowedImageTypes.includes(file.type) && !this.allowedVideoTypes.includes(file.type)) {
            return {
                valid: false,
                error: 'File type not supported. Please use JPG, PNG, GIF, WebP for images or MP4, MOV, AVI, WMV for videos.'
            };
        }
        
        // Check expected type if specified
        if (expectedType === 'image' && !this.isImage(file)) {
            return {
                valid: false,
                error: 'Please select an image file.'
            };
        }
        
        if (expectedType === 'video' && !this.isVideo(file)) {
            return {
                valid: false,
                error: 'Please select a video file.'
            };
        }
        
        return { valid: true };
    }
    
    isImage(file) {
        return this.allowedImageTypes.includes(file.type);
    }
    
    isVideo(file) {
        return this.allowedVideoTypes.includes(file.type);
    }
    
    showFilePreview(file) {
        const previewContainer = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const imagePreview = document.getElementById('imagePreview');
        const videoPreview = document.getElementById('videoPreview');
        
        if (!previewContainer || !fileName) return;
        
        // Show file info
        const fileSize = this.formatFileSize(file.size);
        const fileIcon = this.isImage(file) ? '📷' : '🎥';
        fileName.textContent = `${fileIcon} ${file.name} (${fileSize})`;
        
        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            if (this.isImage(file)) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                videoPreview.style.display = 'none';
                
                // Set the correct file input
                const photoInput = document.getElementById('photoUpload');
                if (photoInput) {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    photoInput.files = dataTransfer.files;
                }
            } else if (this.isVideo(file)) {
                videoPreview.src = e.target.result;
                videoPreview.style.display = 'block';
                imagePreview.style.display = 'none';
                
                // Set the correct file input
                const videoInput = document.getElementById('videoUpload');
                if (videoInput) {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    videoInput.files = dataTransfer.files;
                }
            }
        };
        reader.readAsDataURL(file);
        
        previewContainer.style.display = 'block';
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    showError(message) {
        // Create or update error alert
        let errorAlert = document.querySelector('.upload-error-alert');
        
        if (!errorAlert) {
            errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-danger alert-dismissible fade show upload-error-alert';
            errorAlert.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span class="error-message"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert before the create post card
            const createPostCard = document.querySelector('.create-post-card');
            if (createPostCard) {
                createPostCard.parentNode.insertBefore(errorAlert, createPostCard);
            }
        }
        
        errorAlert.querySelector('.error-message').textContent = message;
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (errorAlert && errorAlert.parentNode) {
                errorAlert.remove();
            }
        }, 5000);
    }
    
    showSuccess(message) {
        // Create success alert
        const successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success alert-dismissible fade show';
        successAlert.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of the container
        const container = document.querySelector('.main-container');
        if (container) {
            container.insertBefore(successAlert, container.firstChild);
        }
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            if (successAlert && successAlert.parentNode) {
                successAlert.remove();
            }
        }, 3000);
    }
    
    handleFormSubmit(e) {
        const form = e.target;
        const contentTextarea = form.querySelector('[name="content"]');
        const photoInput = form.querySelector('#photoUpload');
        const videoInput = form.querySelector('#videoUpload');
        
        // Validate content
        if (!contentTextarea.value.trim()) {
            e.preventDefault();
            this.showError('Please enter some content for your post.');
            contentTextarea.focus();
            return false;
        }
        
        // Check if files are within size limits
        if (photoInput && photoInput.files[0]) {
            if (photoInput.files[0].size > this.maxImageSize) {
                e.preventDefault();
                this.showError(`Image file is too large. Maximum size is ${this.formatFileSize(this.maxImageSize)}.`);
                return false;
            }
        }
        
        if (videoInput && videoInput.files[0]) {
            if (videoInput.files[0].size > this.maxVideoSize) {
                e.preventDefault();
                this.showError(`Video file is too large. Maximum size is ${this.formatFileSize(this.maxVideoSize)}.`);
                return false;
            }
        }
        
        // Show loading state
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Posting...';
        }
        
        return true;
    }
    
    clearFileSelection() {
        document.getElementById('photoUpload').value = '';
        document.getElementById('videoUpload').value = '';
        document.getElementById('filePreview').style.display = 'none';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('videoPreview').style.display = 'none';
    }
}

// Progress bar for uploads
class UploadProgressBar {
    constructor() {
        this.progressBar = null;
        this.createProgressBar();
    }
    
    createProgressBar() {
        this.progressBar = document.createElement('div');
        this.progressBar.className = 'upload-progress-container';
        this.progressBar.style.display = 'none';
        this.progressBar.innerHTML = `
            <div class="progress mb-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 0%">
                    <span class="progress-text">Uploading...</span>
                </div>
            </div>
        `;
        
        // Insert after file preview
        const filePreview = document.getElementById('filePreview');
        if (filePreview) {
            filePreview.parentNode.insertBefore(this.progressBar, filePreview.nextSibling);
        }
    }
    
    show() {
        if (this.progressBar) {
            this.progressBar.style.display = 'block';
        }
    }
    
    hide() {
        if (this.progressBar) {
            this.progressBar.style.display = 'none';
        }
    }
    
    updateProgress(percent) {
        const progressBarInner = this.progressBar.querySelector('.progress-bar');
        const progressText = this.progressBar.querySelector('.progress-text');
        
        if (progressBarInner) {
            progressBarInner.style.width = percent + '%';
        }
        
        if (progressText) {
            progressText.textContent = `Uploading... ${Math.round(percent)}%`;
        }
    }
    
    setComplete() {
        const progressBarInner = this.progressBar.querySelector('.progress-bar');
        const progressText = this.progressBar.querySelector('.progress-text');
        
        if (progressBarInner) {
            progressBarInner.classList.remove('progress-bar-striped', 'progress-bar-animated');
            progressBarInner.classList.add('bg-success');
            progressBarInner.style.width = '100%';
        }
        
        if (progressText) {
            progressText.textContent = 'Upload Complete!';
        }
        
        // Hide after 2 seconds
        setTimeout(() => this.hide(), 2000);
    }
}

// Image compression utility
class ImageCompressor {
    static compress(file, maxWidth = 1200, maxHeight = 800, quality = 0.8) {
        return new Promise((resolve) => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const img = new Image();
            
            img.onload = function() {
                // Calculate new dimensions
                let { width, height } = img;
                
                if (width > maxWidth || height > maxHeight) {
                    const ratio = Math.min(maxWidth / width, maxHeight / height);
                    width *= ratio;
                    height *= ratio;
                }
                
                canvas.width = width;
                canvas.height = height;
                
                // Draw and compress
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob((blob) => {
                    // Create new file object
                    const compressedFile = new File([blob], file.name, {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    
                    resolve(compressedFile);
                }, 'image/jpeg', quality);
            };
            
            img.src = URL.createObjectURL(file);
        });
    }
}

// Initialize upload handler when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('photoUpload') || document.getElementById('videoUpload')) {
        window.uploadHandler = new UploadHandler();
        window.uploadProgressBar = new UploadProgressBar();
        
        // Global function for clearing file selection (used by the remove button)
        window.clearFileSelection = function() {
            window.uploadHandler.clearFileSelection();
        };
        
        console.log('📤 Upload handler initialized successfully!');
    }
});

// Add CSS for drag and drop functionality
const style = document.createElement('style');
style.textContent = `
    .drag-over {
        border: 2px dashed #6c5ce7 !important;
        background: rgba(108, 92, 231, 0.1) !important;
        transform: scale(1.02);
        transition: all 0.3s ease;
    }
    
    .upload-progress-container {
        margin: 15px 0;
    }
    
    .progress {
        height: 25px;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .progress-text {
        font-size: 0.9rem;
        font-weight: 500;
        line-height: 25px;
    }
    
    .upload-error-alert {
        margin-bottom: 20px;
        border-radius: 15px;
        border: none;
    }
    
    .file-preview {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .file-preview:hover {
        border-color: #6c5ce7;
        transform: scale(1.02);
    }
`;
document.head.appendChild(style);