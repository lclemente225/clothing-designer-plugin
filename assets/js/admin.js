/**
 * Clothing Designer Admin JavaScript
 */
(function($) {
    'use strict';

    // Initialize the admin functionality
    function initClothingDesignerAdmin() {
          // Check if jQuery is properly loaded
        if (typeof jQuery !== 'function') {
            console.error('Clothing Designer Admin: jQuery is not loaded');
            return;
        }
        
        // Check if required admin variables are available
        if (typeof cd_admin_vars === 'undefined') {
            console.error('Clothing Designer Admin: Required admin variables are not available');
            return;
        }

        initMediaUploaders();
        initTemplateActions();
        initTemplateViews();
        initDesignActions();
        initFormHandling();
        initShortcodeGeneration();
        initDirectFileUpload();
        initBulkActions();
    }
    // Initialize bulk actions
    function initBulkActions() {
        // Select all checkbox for templates
        $('#cd-select-all-templates').on('change', function() {
            $('.cd-template-checkbox').prop('checked', $(this).prop('checked'));
        });
        
        // Select all checkbox for designs
        $('#cd-select-all-designs').on('change', function() {
            $('.cd-design-checkbox').prop('checked', $(this).prop('checked'));
        });
        
        // Bulk action for templates
        $('.cd-bulk-action-templates').on('click', function() {
            const action = $('#bulk-action-selector-top').val();
            if (action === 'delete') {
                const selectedIds = $('.cd-template-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selectedIds.length === 0) {
                    alert('Please select at least one template to delete.');
                    return;
                }
                
                if (confirm('Are you sure you want to delete ' + selectedIds.length + ' templates? This cannot be undone.')) {
                    bulkDeleteTemplates(selectedIds);
                }
            }
        });
        
        // Bulk action for designs
        $('.cd-bulk-action-designs').on('click', function() {
            const action = $('#bulk-action-selector-top').val();
            if (action === 'delete') {
                const selectedIds = $('.cd-design-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selectedIds.length === 0) {
                    alert('Please select at least one design to delete.');
                    return;
                }
                
                if (confirm('Are you sure you want to delete ' + selectedIds.length + ' designs? This cannot be undone.')) {
                    bulkDeleteDesigns(selectedIds);
                }
            }
        });
    }

    // Initialize media uploaders for files and thumbnails
    function initMediaUploaders() {
        // Template file uploader
        $('.cd-upload-file').on('click', function(e) {
            e.preventDefault();

            var fileFrame = wp.media({
                title: cd_admin_vars.upload_template_title || 'Select or Upload Template File',
                button: {
                    text: cd_admin_vars.upload_template_button || 'Use this file'
                },
                multiple: false,
                library: {
                    type: ['image/svg+xml', 'application/postscript', 'image/png', 'image/jpeg']
                }
            });

            fileFrame.on('select', function() {
                var attachment = fileFrame.state().get('selection').first().toJSON();
                $('#template-file').val(attachment.url);
            });

            fileFrame.open();
        });

        // Thumbnail uploader
        $('.cd-upload-thumbnail').on('click', function(e) {
            e.preventDefault();

            var thumbnailFrame = wp.media({
                title: cd_admin_vars.upload_thumbnail_title || 'Select or Upload Thumbnail',
                button: {
                    text: cd_admin_vars.upload_thumbnail_button || 'Use this image'
                },
                multiple: false,
                library: {
                    type: ['image']
                }
            });

            thumbnailFrame.on('select', function() {
                var attachment = thumbnailFrame.state().get('selection').first().toJSON();
                $('#template-thumbnail').val(attachment.url);
                
                // Update the preview
                var previewContainer = $('.cd-thumbnail-preview');
                previewContainer.empty();
                previewContainer.append('<img src="' + attachment.url + '" alt="Thumbnail Preview">');
            });

            thumbnailFrame.open();
        });
    }

    // Initialize template actions (delete, edit, etc.)
    function initTemplateActions() {
        // Delete template
        $('.cd-delete-template').on('click', function() {
            var templateId = $(this).data('id');
            
            if (!templateId) {
                return;
            }
            
            if (confirm(cd_admin_vars.confirm_delete_template)) {
                $.ajax({
                    url: cd_admin_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cd_delete_template',
                        template_id: templateId,
                        nonce: cd_admin_vars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error deleting template');
                        }
                    },
                    error: function() {
                        alert('Server error. Please try again.');
                    }
                });
            }
        });
        
      // Get shortcode for template
        $('.cd-shortcode-template').on('click', function() {
            var templateId = $(this).data('id');
            
            if (!templateId) {
                return;
            }
            
            var shortcode = '[clothing_designer template_id="' + templateId + '"]';
            
            // Try to use Clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                // Modern approach with Clipboard API
                navigator.clipboard.writeText(shortcode)
                    .then(function() {
                        alert('Shortcode copied to clipboard');
                    })
                    .catch(function() {
                        // Fallback to the old method if Clipboard API fails
                        copyShortcodeLegacy(shortcode);
                    });
            } else {
                // Fallback for older browsers
                copyShortcodeLegacy(shortcode);
            }
        });
    }
    
    // Legacy method for copying text to clipboard
    function copyShortcodeLegacy(text) {
        // Create a temporary input element
        var tempInput = $('<input>');
        $('body').append(tempInput);
        
        // Set the value to the text
        tempInput.val(text);
        
        // Select the text
        tempInput.select();
        
        // Copy to clipboard
        var success = document.execCommand('copy');
        
        // Remove the temporary element
        tempInput.remove();
        
        // Notify user
        if (success) {
            alert('Shortcode copied to clipboard');
        } else {
            alert('Failed to copy shortcode. Please select and copy it manually: ' + text);
        }
    }

    // Initialize design actions
    function initDesignActions() {
        // Delete design
        $('.cd-delete-design').on('click', function() {
            var designId = $(this).data('id');
            
            if (!designId) {
                return;
            }
            
            if (confirm(cd_admin_vars.confirm_delete_design)) {
                $.ajax({
                    url: cd_admin_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cd_delete_design',
                        design_id: designId,
                        nonce: cd_admin_vars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error deleting design');
                        }
                    },
                    error: function() {
                        alert('Server error. Please try again.');
                    }
                });
            }
        });
    }
    
    // Initialize form handling
    function initFormHandling() {
        // Template form submission
        $('.cd-template-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitButton = form.find('button[type="submit"]');
            
            // Disable the submit button to prevent double submission
            submitButton.prop('disabled', true);
            
            // Gather form data
            var formData = new FormData(this);
            formData.append('action', 'cd_save_template');
            formData.append('nonce', cd_admin_vars.nonce);
            
            $.ajax({
                url: cd_admin_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Re-enable the submit button
                    submitButton.prop('disabled', false);
                    
                    if (response.success) {
                        alert(response.data.message);
                        // Redirect to templates list
                        window.location.href = 'admin.php?page=clothing-designer';
                    } else {
                        alert(response.data.message || 'Error saving template');
                    }
                },
                error: function() {
                    // Re-enable the submit button
                    submitButton.prop('disabled', false);
                    alert('Server error. Please try again.');
                }
            });
        });
    }
    
    // Initialize shortcode generation for TinyMCE editor
    function initShortcodeGeneration() {
        // Insert designer button click
        $('.cd-insert-designer').on('click', function() {
            // Open a dialog to select a template
            openTemplateSelector();
        });
    }
    
    // Open template selector dialog
    function openTemplateSelector() {
        // Check if dialog already exists
        if ($('#cd-template-selector-dialog').length) {
            $('#cd-template-selector-dialog').remove();
        }
        
        // Load templates via AJAX
        $.ajax({
            url: cd_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'cd_get_templates',
                nonce: cd_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    createTemplateSelectorDialog(response.data.templates);
                } else {
                    alert(response.data.message || 'Error loading templates');
                };
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    }
    
   // Create template selector dialog
    function createTemplateSelectorDialog(templates) {
        var dialogHtml = '<div id="cd-template-selector-dialog" title="Select Template">';
        dialogHtml += '<p>Select a template to insert the clothing designer:</p>';
        
        if (templates && templates.length > 0) {
            dialogHtml += '<ul class="cd-template-selector-list">';
            
            templates.forEach(function(template) {
                dialogHtml += '<li data-id="' + template.id + '">';
                
                if (template.thumbnail_url) {
                    dialogHtml += '<img src="' + template.thumbnail_url + '" alt="' + template.title + '">';
                }
                
                dialogHtml += '<span>' + template.title + '</span>';
                dialogHtml += '</li>';
            });
            
            dialogHtml += '</ul>';
        } else {
            dialogHtml += '<p>No templates found. Please create a template first.</p>';
        }
        
        dialogHtml += '</div>';
        
        // Add dialog to body
        $('body').append(dialogHtml);
        
        // Check if jQuery UI dialog is available
        if ($.fn.dialog) {
            // Initialize jQuery UI dialog
            $('#cd-template-selector-dialog').dialog({
                modal: true,
                width: 500,
                height: 400,
                buttons: {
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                }
            });
            
            // Template selection
            $('.cd-template-selector-list li').on('click', function() {
                var templateId = $(this).data('id');
                insertDesignerShortcode(templateId);
                $('#cd-template-selector-dialog').dialog("close");
            });
        } else {
            // Fallback if jQuery UI dialog is not available
            var dialogElement = $('#cd-template-selector-dialog');
            dialogElement.addClass('cd-dialog-fallback');
            
            // Add a close button
            dialogElement.prepend('<div class="cd-dialog-header"><span class="cd-dialog-close">×</span></div>');
            
            // Close button functionality
            $('.cd-dialog-close').on('click', function() {
                dialogElement.remove();
            });
            
            // Template selection
            $('.cd-template-selector-list li').on('click', function() {
                var templateId = $(this).data('id');
                insertDesignerShortcode(templateId);
                dialogElement.remove();
            });
            
            // You'll need to add some CSS for the fallback dialog
            $('head').append('<style>' +
                '.cd-dialog-fallback {' +
                '  position: fixed;' +
                '  top: 50%;' +
                '  left: 50%;' +
                '  transform: translate(-50%, -50%);' +
                '  background: white;' +
                '  padding: 20px;' +
                '  border: 1px solid #ccc;' +
                '  width: 500px;' +
                '  max-width: 90%;' +
                '  z-index: 159001;' +
                '  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);' +
                '}' +
                '.cd-dialog-header {' +
                '  text-align: right;' +
                '  margin: -20px -20px 10px;' +
                '  padding: 10px 20px;' +
                '  background: #f5f5f5;' +
                '  border-bottom: 1px solid #ccc;' +
                '}' +
                '.cd-dialog-close {' +
                '  cursor: pointer;' +
                '  font-size: 20px;' +
                '}' +
                '</style>');
        }
    }

    function initTemplateViews() {
        // Handle view tab clicks
        $('.cd-view-tab').on('click', function() {
            const viewType = $(this).data('view');
            
            // Update active tab
            $('.cd-view-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding content
            $('.cd-view-panel').removeClass('active');
            $(`.cd-view-panel[data-view="${viewType}"]`).addClass('active');
        });
        
        // Handle view file uploads
        $('.cd-upload-view').on('click', function() {
            const viewType = $(this).data('view');
            
            var fileFrame = wp.media({
                title: cd_admin_vars.upload_view_title || `Upload ${viewType} view`,
                button: {
                    text: cd_admin_vars.upload_view_button || 'Use this file'
                },
                multiple: false,
                library: {
                    type: ['image/svg+xml', 'application/postscript', 'image/png', 'image/jpeg']
                }
            });

            fileFrame.on('select', function() {
                var attachment = fileFrame.state().get('selection').first().toJSON();
                $(`.cd-view-panel[data-view="${viewType}"] input[type="text"]`).val(attachment.url);
                
                // Add preview image
                let previewContainer = $(`.cd-view-panel[data-view="${viewType}"] .cd-view-preview`);
                if (previewContainer.length === 0) {
                    previewContainer = $('<div class="cd-view-preview"></div>');
                    $(`.cd-view-panel[data-view="${viewType}"]`).append(previewContainer);
                }
                
                previewContainer.html(`<img src="${attachment.url}" alt="${viewType} view">`);
            });

            fileFrame.open();
        });
        //direct file upload functionality
        initDirectFileUpload();
    }

    function initDirectFileUpload() {
        // Direct upload button click
        $('.cd-upload-view-direct').off('click').on('click', function(event) {
            const viewType = $(this).data('view');
            event.stopPropagation();
            // Find the file input in the same container
            $(this).closest('.cd-file-upload-container').find('.cd-view-file-input').click();
        });
        
        // File input change handler
        $('.cd-view-file-input').on('change', function(e) {
            if (!e.target.files || !e.target.files[0]) return;
            
            const viewType = $(this).data('view');
            const container = $(this).closest('.cd-file-upload-container');
            const file = e.target.files[0];
            
            // Check file type
            const fileExt = file.name.split('.').pop().toLowerCase();
            const allowedTypes = ['svg', 'png', 'jpg', 'jpeg', 'ai'];
            
            if (!allowedTypes.includes(fileExt)) {
                alert('Invalid file type. Allowed file types: ' + allowedTypes.join(', '));
                $(this).val(''); // Clear the input
                return;
            }
            
            // Show progress bar
            const progressContainer = container.find('.cd-upload-progress');
            const progressBar = progressContainer.find('.cd-upload-progress-bar');
            progressContainer.show();
            progressBar.width('0%');
            
            // Disable buttons during upload
            container.find('button').prop('disabled', true);
            
            // Create FormData for the file
            const formData = new FormData();
            formData.append('action', 'cd_upload_file');
            formData.append('nonce', cd_admin_vars.nonce);
            formData.append('file', file);
            
            // Send upload request
            $.ajax({
                url: cd_admin_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            progressBar.width(percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    // Re-enable buttons
                    container.find('button').prop('disabled', false);
                    
                    if (response.success) {
                        // Update the file URL input
                        container.find('input[type="text"]').val(response.data.file_url);
                        
                        // Add or update preview image
                        let previewContainer = container.find('.cd-view-preview');
                        if (previewContainer.length === 0) {
                            previewContainer = $('<div class="cd-view-preview"></div>');
                            container.append(previewContainer);
                        }
                        
                        previewContainer.html(`<img src="${response.data.file_url}" alt="${viewType} view">`);
                    } else {
                        alert(response.data.message || 'Error uploading file');
                    }
                    
                    // Reset the file input
                    $(this).val('');
                    
                    // Hide progress bar
                    setTimeout(function() {
                        progressContainer.hide();
                        progressBar.width('0%');
                    }, 1000);
                },
                error: function() {
                    container.find('button').prop('disabled', false);
                    alert('Server error occurred during upload.');
                    
                    // Hide progress bar
                    progressContainer.hide();
                    progressBar.width('0%');
                    
                    // Reset file input
                    $(this).val('');
                }
            });
        });
    }

    // Insert designer shortcode to editor
    function insertDesignerShortcode(templateId) {
        var shortcode = '[clothing_designer template_id="' + templateId + '"]';
        
        // Insert into TinyMCE editor if available
        if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            tinymce.activeEditor.execCommand('mceInsertContent', false, shortcode);
        } 
        // Otherwise insert into textarea
        else if (typeof wpActiveEditor !== 'undefined') {
            var textArea = $('#' + wpActiveEditor);
            if (textArea.length) {
                textArea.val(textArea.val() + shortcode);
            }
        }
    }


    // Bulk delete templates
    function bulkDeleteTemplates(ids) {
        $.ajax({
            url: cd_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'cd_bulk_delete_templates',
                template_ids: ids,
                nonce: cd_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'Error deleting templates');
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    }

    // Bulk delete designs
    function bulkDeleteDesigns(ids) {
        $.ajax({
            url: cd_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'cd_bulk_delete_designs',
                design_ids: ids,
                nonce: cd_admin_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'Error deleting designs');
                }
            },
            error: function() {
                alert('Server error. Please try again.');
            }
        });
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        initClothingDesignerAdmin();
    });

})(jQuery);