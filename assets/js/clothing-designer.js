/**
 * Clothing Designer Script
 *
 * Handles the clothing designer functionality
 */

(function($) {
    'use strict';
  
    // Check if required libraries are loaded
    if (typeof $ === 'undefined') {
        console.error('Clothing Designer: jQuery is not loaded');
        return;
    }
    
    if (typeof fabric === 'undefined') {
        console.error('Clothing Designer: Fabric.js is not loaded');
        return;
    }
    /**
     * Clothing Designer Class
     */
    class ClothingDesigner {
        /**
         * Constructor
         *
         * @param {Object} options Configuration options
         */
        constructor(options) {
            // Default options
            this.defaults = {
                containerId: '',
                canvasId: '',
                uploadId: '',
                templateId: 0,
                designId: 0,
                width: '100%',
                height: '600px'
            };

            // Merge options with defaults
            this.options = $.extend({}, this.defaults, options);

            // Elements
            this.container = $('#' + this.options.containerId);
            this.canvasElem = document.getElementById(this.options.canvasId);
            this.uploadInput = $('#' + this.options.uploadId);

            // Fabric canvas
            this.canvas = null;

            // State variables
            this.template = null;
            this.templateViews = {};  // Store multiple views
            this.currentView = 'front';  // Default view
            this.design = null;
            this.activeObject = null;
            this.selectedTextElement = null;
            this.isLoading = false;
            this.svgTexts = [];
            this.isLoading = false;
            this.designLayers = [];

            // Initialize designLayers for each view
            ['front', 'back', 'left', 'right'].forEach(view => {
                this.designLayers[view] = [];
            });

            // Initialize
            this.init();
        }

        /**
         * Initialize the designer
         */
        init() {
            this.initCanvas();
            this.setupEventListeners();
            this.loadTemplate();
        }

        /**
         * Initialize the canvas
         */
        initCanvas() {
            // Initialize the fabric canvas
            this.canvas = new fabric.Canvas(this.options.canvasId, {
                preserveObjectStacking: true
            });

            // Set canvas dimensions
            this.canvas.setWidth(this.container.find('.cd-canvas-container').width());
            this.canvas.setHeight(this.container.find('.cd-canvas-container').height());

            // Setup canvas events
            this.canvas.on('selection:created', this.onObjectSelected.bind(this));
            this.canvas.on('selection:updated', this.onObjectSelected.bind(this));
            this.canvas.on('selection:cleared', this.onSelectionCleared.bind(this));
            this.canvas.on('object:modified', this.onObjectModified.bind(this));

            // Setup responsive canvas
            window.addEventListener('resize', this.resizeCanvas.bind(this));
        }

        /**
         * Resize canvas on window resize
         */
        resizeCanvas() {
            const container = this.container.find('.cd-canvas-container');
            const width = container.width();
            const height = container.height();

            this.canvas.setDimensions({
                width: width,
                height: height
            });

            // Re-center objects if we have a template
            if (this.template) {
                this.centerObjects();
            }
        }

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Upload file
            this.container.find('.cd-btn-upload').on('click', () => {
                this.uploadInput.click();
            });

            this.uploadInput.on('change', (e) => {
                this.handleFileUpload(e);
            });

            // Add text
            this.container.find('.cd-btn-add-text').on('click', () => {
                const text = this.container.find('.cd-text-input').val();
                if (text.trim() !== '') {
                    this.addText(text);
                    this.container.find('.cd-text-input').val('');
                }
            });

            // Color picker
            this.container.find('.cd-color-picker').on('change', (e) => {
                const color = $(e.target).val();
                this.changeObjectColor(color);
            });

            // Size slider
            this.container.find('.cd-size-slider').on('input', (e) => {
                const size = $(e.target).val();
                this.changeObjectSize(size);
            });

            // Rotation slider
            this.container.find('.cd-rotation-slider').on('input', (e) => {
                const rotation = $(e.target).val();
                this.changeObjectRotation(rotation);
            });

            // Reset button
            this.container.find('.cd-btn-reset').on('click', () => {
                this.resetDesign();
            });

            // Save button
            this.container.find('.cd-btn-save').on('click', () => {
                this.saveDesign();
            });

            // Enter key for text input
            this.container.find('.cd-text-input').on('keypress', (e) => {
                if (e.which === 13) {
                    this.container.find('.cd-btn-add-text').click();
                }
            });

            // View selection
            this.container.find('.cd-view-btn').on('click', (e) => {
                const viewType = $(e.target).data('view');
                this.switchView(viewType);
            });
        }

        /**
         * Handle file upload
         *
         * @param {Event} e Change event
         */
        handleFileUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            // Check file type
            const allowedTypes = cd_vars.allowed_file_types.extensions;
            const allowedMimeTypes = cd_vars.allowed_file_types.mime_types;
            const fileExt = file.name.split('.').pop().toLowerCase();

            if (!allowedTypes.includes(fileExt) && !allowedMimeTypes.includes(file.type)) {
                alert(cd_vars.messages.invalid_file_type + allowedTypes.join(', '));
                return;
            }

            // Check file size
            if (file.size > cd_vars.upload_max_size) {
                alert(cd_vars.messages.file_too_large + this.formatBytes(cd_vars.upload_max_size));
                return;
            }

            // Show loading
            this.showLoading();

            // Create form data
            const formData = new FormData();
            formData.append('action', 'cd_upload_file');
            formData.append('nonce', cd_vars.nonce);
            formData.append('file', file);

            // Send to server
            $.ajax({
                url: cd_vars.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    this.hideLoading();

                    if (response.success) {
                        const uploadedFile = response.data;

                        // Handle different file types
                        if (uploadedFile.file_type === 'svg') {
                            this.addSVG(uploadedFile);
                        } else if (['png', 'jpg', 'jpeg'].includes(uploadedFile.file_type)) {
                            this.addImage(uploadedFile.file_url);
                        } else if (uploadedFile.file_type === 'ai') {
                            // Show message about AI files
                            if (uploadedFile.message) {
                                alert(uploadedFile.message);
                            }

                            // If converted successfully, add as SVG
                            if (uploadedFile.converted_from === 'ai') {
                                this.addSVG(uploadedFile);
                            }
                        }
                    } else {
                        alert(response.data.message || cd_vars.messages.upload_error);
                    }
                },
                error: () => {
                    this.hideLoading();
                    alert(cd_vars.messages.upload_error);
                }
            });

            // Reset file input
            e.target.value = '';
        }

        /**
         * Load template
         */
        loadTemplate() {
            if (!this.options.templateId) {
                this.showError(cd_vars.messages.no_template);
                return;
            }

            this.showLoading();

            $.ajax({
                url: cd_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cd_get_template',
                    nonce: cd_vars.nonce,
                    template_id: this.options.templateId
                },
                success: (response) => {
                    if (response.success) {
                        this.template = response.data.template;
                        this.loadTemplateContent();

                        // If we have a design ID, load the design
                        if (this.options.designId) {
                            this.loadDesign();
                        } else {
                            this.hideLoading();
                        }
                    } else {
                        this.hideLoading();
                        this.showError(response.data.message || cd_vars.messages.error_loading_template);
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showError(cd_vars.messages.error_loading_template);
                }
            });
        }

        /**
         * Load template content
         */
        loadTemplateContent() {
            // Handle different template types
            if (this.template.file_type === 'svg') {
                this.loadSVGTemplate();
            } else if (['png', 'jpg', 'jpeg'].includes(this.template.file_type)) {
                this.loadImageTemplate();
            } else if (this.template.file_type === 'ai') {
                // Try to load as SVG (server should have converted it)
                this.loadSVGTemplate();
            }
        }

        /**
         * Load SVG template
         */
        loadSVGTemplate() {
            fabric.loadSVGFromString(this.template.content, (objects, options) => {
                if (!objects || objects.length === 0) {
                    this.showError(cd_vars.messages.error_loading_template);
                    return;
                }

                // Create a group for all SVG elements
                const templateGroup = new fabric.Group(objects, {
                    selectable: false,
                    evented: false,
                    hasControls: false,
                    hasBorders: false,
                    lockMovementX: true,
                    lockMovementY: true,
                    lockRotation: true,
                    lockScalingX: true,
                    lockScalingY: true,
                    lockSkewingX: true,
                    lockSkewingY: true,
                    name: 'template'
                });

                // Extract SVG dimensions from viewBox
                const viewBoxMatch = this.template.content.match(/viewBox=['"]([^'"]+)['"]/);
                let viewBox = [0, 0, 300, 150];

                if (viewBoxMatch && viewBoxMatch[1]) {
                    viewBox = viewBoxMatch[1].split(/[\s,]+/).map(Number);
                }

                // Set the template group dimensions
                const viewBoxWidth = viewBox[2] - viewBox[0];
                const viewBoxHeight = viewBox[3] - viewBox[1];
                const scaleX = this.canvas.width / viewBoxWidth;
                const scaleY = this.canvas.height / viewBoxHeight;
                const scale = Math.min(scaleX, scaleY) * 0.9;

                templateGroup.set({
                    scaleX: scale,
                    scaleY: scale,
                    left: this.canvas.width / 2,
                    top: this.canvas.height / 2,
                    originX: 'center',
                    originY: 'center'
                });

                // Clear canvas and add template
                this.canvas.clear();
                this.canvas.add(templateGroup);
                this.canvas.renderAll();

                // Extract text elements from SVG
                this.extractSVGTextElements();
            });
        }

        /**
         * Load image template
         */
        loadImageTemplate() {
            fabric.Image.fromURL(this.template.file_url, (img) => {
                // Set the image as the template
                img.set({
                    selectable: false,
                    evented: false,
                    hasControls: false,
                    hasBorders: false,
                    lockMovementX: true,
                    lockMovementY: true,
                    lockRotation: true,
                    lockScalingX: true,
                    lockScalingY: true,
                    lockSkewingX: true,
                    lockSkewingY: true,
                    name: 'template'
                });

                // Calculate scale to fit canvas
                const scaleX = this.canvas.width / img.width;
                const scaleY = this.canvas.height / img.height;
                const scale = Math.min(scaleX, scaleY) * 0.9;

                img.set({
                    scaleX: scale,
                    scaleY: scale,
                    left: this.canvas.width / 2,
                    top: this.canvas.height / 2,
                    originX: 'center',
                    originY: 'center'
                });

                // Clear canvas and add template
                this.canvas.clear();
                this.canvas.add(img);
                this.canvas.renderAll();
            });
        }

        /**
         * Extract SVG text elements
         */
        extractSVGTextElements() {
            $.ajax({
                url: cd_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cd_extract_svg_text',
                    nonce: cd_vars.nonce,
                    svg_content: this.template.content
                },
                success: (response) => {
                    if (response.success && response.data.text_elements) {
                        this.svgTexts = response.data.text_elements;
                        this.updateTextPanel();
                    }
                }
            });
        }

        /**
         * Load design
         */
        loadDesign() {
            $.ajax({
                url: cd_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cd_load_design',
                    nonce: cd_vars.nonce,
                    design_id: this.options.designId
                },
                success: (response) => {
                    this.hideLoading();

                    if (response.success) {
                        this.design = response.data.design;
                        this.loadDesignElements();
                    } else {
                        alert(response.data.message || 'Error loading design');
                    }
                },
                error: () => {
                    this.hideLoading();
                    alert('Error loading design');
                }
            });
        }

        /**
         * Load design elements
         */
        loadDesignElements() {
            if (!this.design || !this.design.design_data) {
                return;
            }

            try {
                const designData = JSON.parse(this.design.design_data);

                if (designData.elements) {
                    // Process each element
                    designData.elements.forEach(element => {
                        switch (element.type) {
                            case 'svg':
                                this.loadSVGElement(element);
                                break;
                            case 'image':
                                this.loadImageElement(element);
                                break;
                            case 'text':
                                this.loadTextElement(element);
                                break;
                        }
                    });

                    // Update layers panel
                    this.updateLayersPanel();
                }
            } catch (e) {
                console.error('Error parsing design data:', e);
            }
        }

        /**
         * Load SVG element
         *
         * @param {Object} element Element data
         */
        loadSVGElement(element) {
            if (!element.svg_content) {
                return;
            }

            fabric.loadSVGFromString(element.svg_content, (objects, options) => {
                const svgGroup = new fabric.Group(objects, {
                    left: element.left || 0,
                    top: element.top || 0,
                    scaleX: element.scaleX || 1,
                    scaleY: element.scaleY || 1,
                    angle: element.angle || 0,
                    name: element.name || 'svg-element',
                    originX: 'center',
                    originY: 'center'
                });

                // Add to canvas
                this.canvas.add(svgGroup);
                this.canvas.renderAll();

                // Add to design layers
                this.designLayers.push({
                    id: element.id || this.generateId(),
                    name: element.name || 'SVG Element',
                    type: 'svg',
                    object: svgGroup
                });

                this.updateLayersPanel();
            });
        }

        /**
         * Load image element
         *
         * @param {Object} element Element data
         */
        loadImageElement(element) {
            if (!element.src) {
                return;
            }

            fabric.Image.fromURL(element.src, (img) => {
                img.set({
                    left: element.left || 0,
                    top: element.top || 0,
                    scaleX: element.scaleX || 1,
                    scaleY: element.scaleY || 1,
                    angle: element.angle || 0,
                    name: element.name || 'image-element',
                    originX: 'center',
                    originY: 'center'
                });

                // Add to canvas
                this.canvas.add(img);
                this.canvas.renderAll();

                // Add to design layers
                this.designLayers.push({
                    id: element.id || this.generateId(),
                    name: element.name || 'Image Element',
                    type: 'image',
                    object: img
                });

                this.updateLayersPanel();
            });
        }

        /**
         * Load text element
         *
         * @param {Object} element Element data
         */
        loadTextElement(element) {
            const text = new fabric.IText(element.text || 'Text', {
                left: element.left || 0,
                top: element.top || 0,
                scaleX: element.scaleX || 1,
                scaleY: element.scaleY || 1,
                angle: element.angle || 0,
                fill: element.fill || '#000000',
                fontFamily: element.fontFamily || 'Arial',
                fontSize: element.fontSize || 40,
                name: element.name || 'text-element',
                originX: 'center',
                originY: 'center'
            });

            // Add to canvas
            this.canvas.add(text);
            this.canvas.renderAll();

            // Add to design layers
            this.designLayers.push({
                id: element.id || this.generateId(),
                name: element.name || element.text || 'Text Element',
                type: 'text',
                object: text
            });

            this.updateLayersPanel();
        }

        /**
         * Add SVG to canvas
         *
         * @param {Object} svgData SVG data
         */
        addSVG(svgData) {
            fabric.loadSVGFromString(svgData.content, (objects, options) => {
                const svgGroup = new fabric.Group(objects, {
                    left: this.canvas.width / 2,
                    top: this.canvas.height / 2,
                    name: 'svg-element',
                    originX: 'center',
                    originY: 'center'
                });

                // Scale to reasonable size
                const maxDimension = Math.min(this.canvas.width, this.canvas.height) * 0.5;
                const scale = maxDimension / Math.max(svgGroup.width, svgGroup.height);
                svgGroup.scale(scale);

                // Add to canvas
                this.canvas.add(svgGroup);
                this.canvas.setActiveObject(svgGroup);
                this.canvas.renderAll();

                // Add to design layers
                const layerId = this.generateId();
                this.designLayers.push({
                    id: layerId,
                    name: svgData.file_name || 'SVG Element',
                    type: 'svg',
                    object: svgGroup,
                    svg_content: svgData.content,
                    file_url: svgData.file_url,
                    text_elements: svgData.text_elements || [],
                    editable: svgData.editable || false
                });

                this.updateLayersPanel();
                // If the SVG has editable text, show text editing options
                if (svgData.editable && svgData.text_elements && svgData.text_elements.length > 0) {
                    this.showEditableSVGText(layerId, svgData.text_elements);
                }
            });
        }

        /**
         * Show editable text elements for an uploaded SVG
         * 
         * @param {string} layerId The layer ID
         * @param {Array} textElements Array of text elements
         */
        showEditableSVGText(layerId, textElements) {
            // Find the layer in designLayers
            const layerIndex = this.designLayers.findIndex(layer => layer.id === layerId);
            if (layerIndex === -1) return;
            
            const layer = this.designLayers[layerIndex];
            
            // Create a panel to edit SVG text elements
            const panel = $('<div class="cd-svg-text-editor"></div>');
            panel.append(`<h4>${cd_vars.messages.edit_svg_text || 'Edit SVG Text'}</h4>`);
            
            const textList = $('<div class="cd-svg-text-list"></div>');
            panel.append(textList);
            
            // Add each text element
            textElements.forEach(text => {
                const textItem = $(`
                    <div class="cd-svg-text-item" data-id="${text.id}">
                        <div class="cd-svg-text-content">${text.content}</div>
                        <div class="cd-svg-text-edit">
                            <input type="text" value="${text.content.replace(/"/g, '&quot;')}">
                            <button class="cd-btn cd-btn-update-svg-text">${cd_vars.messages.update || 'Update'}</button>
                        </div>
                    </div>
                `);
                
                textList.append(textItem);
                
                // Update button click
                textItem.find('.cd-btn-update-svg-text').on('click', () => {
                    const newText = textItem.find('input').val();
                    this.updateUploadedSVGText(layerId, text.id, newText);
                });
            });
            
            // Show the panel
            this.container.find('.cd-sidebar').append(panel);
        }

        /**
         * Update text in an uploaded SVG
         * 
         * @param {string} layerId The layer ID
         * @param {string} textId The text element ID
         * @param {string} newText The new text content
         */
        updateUploadedSVGText(layerId, textId, newText) {
            // Find the layer
            const layerIndex = this.designLayers.findIndex(layer => layer.id === layerId);
            if (layerIndex === -1) return;
            
            const layer = this.designLayers[layerIndex];
            
            // Show loading
            this.showLoading();
            
            // Send update request to server
            $.ajax({
                url: cd_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cd_update_svg_text',
                    nonce: cd_vars.nonce,
                    svg_content: layer.svg_content,
                    text_updates: {
                        [textId]: newText
                    }
                },
                success: (response) => {
                    this.hideLoading();
                    
                    if (response.success) {
                        // Update the SVG content in the layer
                        layer.svg_content = response.data.updated_svg;
                        
                        // Update text elements
                        layer.text_elements = response.data.text_elements;
                        
                        // Re-create SVG object on canvas
                        fabric.loadSVGFromString(response.data.updated_svg, (objects, options) => {
                            // Remove old object
                            this.canvas.remove(layer.object);
                            
                            // Create new group
                            const svgGroup = new fabric.Group(objects, {
                                left: layer.object.left,
                                top: layer.object.top,
                                scaleX: layer.object.scaleX,
                                scaleY: layer.object.scaleY,
                                angle: layer.object.angle,
                                name: 'svg-element',
                                originX: 'center',
                                originY: 'center'
                            });
                            
                            // Update the layer object
                            layer.object = svgGroup;
                            
                            // Add to canvas
                            this.canvas.add(svgGroup);
                            this.canvas.renderAll();
                            
                            // Update the text display
                            const textItem = this.container.find(`.cd-svg-text-item[data-id="${textId}"]`);
                            textItem.find('.cd-svg-text-content').text(newText);
                        });
                    } else {
                        alert(response.data.message || 'Error updating text');
                    }
                },
                error: () => {
                    this.hideLoading();
                    alert('Error updating SVG text');
                }
            });
        }

        /**
         * Add image to canvas
         *
         * @param {string} imageUrl Image URL
         */
        addImage(imageUrl) {
            fabric.Image.fromURL(imageUrl, (img) => {
                // Scale to reasonable size
                const maxDimension = Math.min(this.canvas.width, this.canvas.height) * 0.5;
                const scale = maxDimension / Math.max(img.width, img.height);

                img.set({
                    left: this.canvas.width / 2,
                    top: this.canvas.height / 2,
                    scaleX: scale,
                    scaleY: scale,
                    name: 'image-element',
                    originX: 'center',
                    originY: 'center'
                });

                // Add to canvas
                this.canvas.add(img);
                this.canvas.setActiveObject(img);
                this.canvas.renderAll();

                // Add to design layers
                const layerId = this.generateId();
                const fileName = imageUrl.split('/').pop();
                this.designLayers.push({
                    id: layerId,
                    name: fileName || 'Image Element',
                    type: 'image',
                    object: img,
                    src: imageUrl
                });

                this.updateLayersPanel();
            });
        }

        /**
         * Add text to canvas
         *
         * @param {string} text Text content
         */
        addText(text) {
            const textObj = new fabric.IText(text, {
                left: this.canvas.width / 2,
                top: this.canvas.height / 2,
                fontFamily: 'Arial',
                fontSize: 40,
                fill: this.container.find('.cd-color-picker').val() || '#000000',
                name: 'text-element',
                originX: 'center',
                originY: 'center'
            });

            // Add to canvas
            this.canvas.add(textObj);
            this.canvas.setActiveObject(textObj);
            this.canvas.renderAll();

            // Add to design layers
            const layerId = this.generateId();
            this.designLayers.push({
                id: layerId,
                name: text.length > 20 ? text.substring(0, 20) + '...' : text,
                type: 'text',
                object: textObj
            });

            this.updateLayersPanel();
        }

        /**
         * Edit SVG text
         *
         * @param {Object} textElement Text element data
         */
        editSVGText(textElement) {
            // Create edit dialog
            const dialog = `
                <div class="cd-modal">
                    <div class="cd-modal-content">
                        <div class="cd-modal-header">
                            <h3>${cd_vars.messages.edit_text}</h3>
                            <span class="cd-modal-close">&times;</span>
                        </div>
                        <div class="cd-modal-body">
                            <div class="cd-form-group">
                                <label for="cd-text-edit">${cd_vars.messages.edit_text}</label>
                                <input type="text" id="cd-text-edit" value="${textElement.content.replace(/"/g, '&quot;')}">
                            </div>
                        </div>
                        <div class="cd-modal-footer">
                            <button class="cd-btn cd-btn-cancel">${cd_vars.messages.cancel}</button>
                            <button class="cd-btn cd-btn-save-text">${cd_vars.messages.save}</button>
                        </div>
                    </div>
                </div>
            `;

            // Add dialog to body
            $('body').append(dialog);

            // Event listeners
            $('.cd-modal-close, .cd-btn-cancel').on('click', function() {
                $('.cd-modal').remove();
            });

            $('.cd-btn-save-text').on('click', () => {
                const newText = $('#cd-text-edit').val();
                this.updateSVGText(textElement.id, newText);
                $('.cd-modal').remove();
            });
        }

        /**
         * Update SVG text
         *
         * @param {string} textId Text element ID
         * @param {string} newText New text content
         */
        updateSVGText(textId, newText) {
            if (!this.template || !this.template.content) {
                console.warn('Cannot update SVG text: template or content is missing')
                return;
            }
            // Validate inputs
            if (!textId || typeof newText !== 'string') {
                console.warn('Invalid parameters for SVG text update');
                return;
            }

            this.showLoading();

            $.ajax({
                url: cd_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cd_extract_svg_text',
                    nonce: cd_vars.nonce,
                    svg_content: this.template.content,
                    text_updates: {
                        [textId]: newText
                    }
                },
                success: (response) => {
                    this.hideLoading();

                    if (response.success) {
                        // Check if we got back valid SVG data
                        if (!response.data.updated_svg || !response.data.updated_svg.includes('<svg')) {
                            console.error('Server returned invalid SVG content');
                            alert('The server returned invalid data. Please try again.');
                            return;
                        }
                        // Update template content
                        this.template.content = response.data.updated_svg;

                        // Reload template
                        this.loadTemplateContent();

                        // Update text elements list
                        if (response.data.text_elements) {
                            this.svgTexts = response.data.text_elements;
                            this.updateTextPanel();
                        }
                    } else {
                        const errorMsg = response.data && response.data.message ? response.data.message : 'Error updating text';
                        console.error('SVG text update failed:', errorMsg);
                        alert(errorMsg);
                    }
                },
                error:  (xhr, status, error) => {
                    this.hideLoading();
                    console.error('AJAX error updating SVG text:', status, error);
                    alert('Error updating text: ' + status);
                }
            });
        }

        /**
         * Update text panel
         */
        updateTextPanel() {
            // If no SVG texts, hide the panel
            if (!this.svgTexts || this.svgTexts.length === 0) {
                return;
            }

            // Create the edit text panel
            const textPanel = this.container.find('.cd-text-panel');
            const textContent = textPanel.find('.cd-tool-content');

            // Clear existing content
            textContent.empty();

            // Add text input and button
            textContent.append(`
                <input type="text" class="cd-text-input" placeholder="${cd_vars.messages.add_text}">
                <button class="cd-btn cd-btn-add-text">${cd_vars.messages.add_text}</button>
            `);

            // Add SVG text elements
            if (this.svgTexts.length > 0) {
                const textList = $('<div class="cd-svg-text-list"></div>');
                textContent.append(textList);

                this.svgTexts.forEach(text => {
                    const textItem = $(`
                        <div class="cd-svg-text-item" data-id="${text.id}">
                            <span class="cd-svg-text-content">${text.content}</span>
                            <button class="cd-btn cd-btn-edit-svg-text">${cd_vars.messages.edit_text}</button>
                        </div>
                    `);

                    textList.append(textItem);

                    // Edit button click
                    textItem.find('.cd-btn-edit-svg-text').on('click', () => {
                        this.editSVGText(text);
                    });
                });
            }

            // Rebind event listeners
            this.container.find('.cd-btn-add-text').on('click', () => {
                const text = this.container.find('.cd-text-input').val();
                if (text.trim() !== '') {
                    this.addText(text);
                    this.container.find('.cd-text-input').val('');
                }
            });

            // Enter key for text input
            this.container.find('.cd-text-input').on('keypress', (e) => {
                if (e.which === 13) {
                    this.container.find('.cd-btn-add-text').click();
                }
            });
        }

        /**
         * Update layers panel
         */
        updateLayersPanel() {
            const layersList = this.container.find('.cd-layers-list');
            layersList.empty();

            // Sort layers by z-index (bottom to top)
            const sortedLayers = [...this.designLayers].sort((a, b) => {
                const aIndex = this.canvas.getObjects().indexOf(a.object);
                const bIndex = this.canvas.getObjects().indexOf(b.object);
                return aIndex - bIndex;
            });

            // Add layers from bottom to top
            sortedLayers.forEach((layer, index) => {
                const layerItem = $(`
                    <li class="cd-layer-item" data-id="${layer.id}">
                        <div class="cd-layer-content">
                            <span class="cd-layer-name">${layer.name}</span>
                            <div class="cd-layer-controls">
                                <button class="cd-btn cd-btn-layer-up" title="${cd_vars.messages.move_up}">↑</button>
                                <button class="cd-btn cd-btn-layer-down" title="${cd_vars.messages.move_down}">↓</button>
                                <button class="cd-btn cd-btn-layer-delete" title="${cd_vars.messages.delete}">×</button>
                            </div>
                        </div>
                    </li>
                `);

                // Highlight active layer
                if (this.activeObject && this.activeObject === layer.object) {
                    layerItem.addClass('active');
                }

                // Layer item click
                layerItem.on('click', (e) => {
                    if (!$(e.target).hasClass('cd-btn')) {
                        this.canvas.setActiveObject(layer.object);
                        this.canvas.renderAll();
                    }
                });

                // Up button
                layerItem.find('.cd-btn-layer-up').on('click', () => {
                    this.moveLayerUp(layer.id);
                });

                // Down button
                layerItem.find('.cd-btn-layer-down').on('click', () => {
                    this.moveLayerDown(layer.id);
                });

                // Delete button
                layerItem.find('.cd-btn-layer-delete').on('click', () => {
                    this.deleteLayer(layer.id);
                });

                layersList.prepend(layerItem);
            });
        }

        /**
         * Move layer up
         *
         * @param {string} layerId Layer ID
         */
        moveLayerUp(layerId) {
            const layerIndex = this.designLayers.findIndex(layer => layer.id === layerId);
            if (layerIndex === -1) return;

            const layer = this.designLayers[layerIndex];
            const canvasObjects = this.canvas.getObjects();
            const objectIndex = canvasObjects.indexOf(layer.object);

            if (objectIndex < canvasObjects.length - 1) {
                this.canvas.bringForward(layer.object);
                this.canvas.renderAll();
                this.updateLayersPanel();
            }
        }

        /**
         * Move layer down
         *
         * @param {string} layerId Layer ID
         */
        moveLayerDown(layerId) {
            const layerIndex = this.designLayers.findIndex(layer => layer.id === layerId);
            if (layerIndex === -1) return;

            const layer = this.designLayers[layerIndex];
            const canvasObjects = this.canvas.getObjects();
            const objectIndex = canvasObjects.indexOf(layer.object);
            const templateIndex = canvasObjects.findIndex(obj => obj.name === 'template');

            // Don't move below template
            if (objectIndex > templateIndex + 1) {
                this.canvas.sendBackwards(layer.object);
                this.canvas.renderAll();
                this.updateLayersPanel();
            }
        }

        /**
         * Delete layer
         *
         * @param {string} layerId Layer ID
         */
        deleteLayer(layerId) {
            if (!confirm(cd_vars.messages.confirm_delete)) {
                return;
            }

            const layerIndex = this.designLayers.findIndex(layer => layer.id === layerId);
            if (layerIndex === -1) return;

            const layer = this.designLayers[layerIndex];

            // Remove from canvas
            this.canvas.remove(layer.object);

            // Remove from layers array
            this.designLayers.splice(layerIndex, 1);

            // Update layers panel
            this.updateLayersPanel();
            this.canvas.renderAll();
        }


        // Switch between views: left right front back
        switchView(viewType) {
            // Check if view exists
            if (!this.templateViews[viewType]) {
                alert(`${viewType} view is not available for this template`);
                return;
            }
            
            // Save current layers
            this.saveCurrentViewLayers();
            
            // Update active button
            this.container.find('.cd-view-btn').removeClass('active');
            this.container.find(`.cd-view-btn[data-view="${viewType}"]`).addClass('active');
            
            // Update current view
            this.currentView = viewType;
            
            // Load the view template
            this.loadViewTemplate(viewType);
        }

        // Load template for specific view
        loadViewTemplate(viewType) {
            this.showLoading();
            
            // Clear canvas
            this.canvas.clear();
            
            // Load template view
            const template = this.templateViews[viewType];
            
            if (template.file_type === 'svg') {
                fabric.loadSVGFromString(template.content, (objects, options) => {
                    this.renderTemplateView(objects, template);
                });
            } else if (['png', 'jpg', 'jpeg'].includes(template.file_type)) {
                fabric.Image.fromURL(template.file_url, (img) => {
                    this.renderTemplateImageView(img, template);
                });
            }
        }

        // Render template view
        renderTemplateView(objects, template) {
            // Create template group
            const templateGroup = new fabric.Group(objects, {
                selectable: false,
                evented: false,
                hasControls: false,
                hasBorders: false,
                lockMovementX: true,
                lockMovementY: true,
                lockRotation: true,
                lockScalingX: true,
                lockScalingY: true,
                lockSkewingX: true,
                lockSkewingY: true,
                name: 'template'
            });
              
            // Set dimensions and position
            // Similar to your existing loadSVGTemplate method
            
            // Add to canvas
            this.canvas.add(templateGroup);
            
            // Load saved layers for this view
            this.loadViewLayers(this.currentView);
            
            this.hideLoading();
        }

        // Load saved layers for a view
        loadViewLayers(viewType) {
            const layers = this.designLayers[viewType] || [];
            
            layers.forEach(layer => {
                // Add layer object to canvas
                this.canvas.add(layer.object);
            });
            
            this.canvas.renderAll();
            this.updateLayersPanel();
        }

        // Update AJAX methods for template loading
        loadTemplate() {
            if (!this.options.templateId) {
                this.showError(cd_vars.messages.no_template);
                return;
            }

            this.showLoading();

            $.ajax({
                url: cd_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cd_get_template_views',
                    nonce: cd_vars.nonce,
                    template_id: this.options.templateId
                },
                success: (response) => {
                    if (response.success) {
                        this.template = response.data.template;
                        this.templateViews = response.data.views;
                        
                        // Load front view by default
                        if (this.templateViews.front) {
                            this.currentView = 'front';
                            this.loadViewTemplate('front');
                        }

                        // If we have a design ID, load the design
                        if (this.options.designId) {
                            this.loadDesign();
                        } else {
                            this.hideLoading();
                        }
                    } else {
                        this.hideLoading();
                        this.showError(response.data.message || cd_vars.messages.error_loading_template);
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showError(cd_vars.messages.error_loading_template);
                }
            });
        }

        /**
         * Handle object selection
         *
         * @param {Object} e Selection event
         */
        onObjectSelected(e) {
            this.activeObject = e.selected[0];

            // Skip if template
            if (this.activeObject.name === 'template') {
                this.canvas.discardActiveObject();
                this.canvas.renderAll();
                return;
            }

            // Update properties panel
            this.updatePropertiesPanel();

            // Update layers panel
            this.updateLayersPanel();
        }

        /**
         * Handle selection cleared
         */
        onSelectionCleared() {
            this.activeObject = null;
            this.updateLayersPanel();
        }

        /**
         * Handle object modified
         */
        onObjectModified() {
            // Nothing specific to do here for now
        }

        /**
         * Update properties panel
         */
        updatePropertiesPanel() {
            if (!this.activeObject) return;

            // Set color picker
            if (this.activeObject.fill) {
                const $colorPicker = this.safeFind('.cd-color-picker');
                if ($colorPicker) {
                    $colorPicker.val(this.activeObject.fill);
                }
            }

            // Set size slider (based on scale)
            const scale = (this.activeObject.scaleX || 1) * 100;
            const $sizeSlider = this.safeFind('.cd-size-slider');
            if ($sizeSlider) {
                $sizeSlider.val(scale);
            }

            // Set rotation slider
            const rotation = this.activeObject.angle || 0;
            const $rotationSlider = this.safeFind('.cd-rotation-slider');
            if ($rotationSlider) {
                $rotationSlider.val(rotation);
            }
        }

        /**
         * Change object color
         *
         * @param {string} color Color value
         */
        changeObjectColor(color) {
            if (!this.activeObject) return;

            this.activeObject.set('fill', color);
            this.canvas.renderAll();
        }

        /**
         * Change object size
         *
         * @param {number} size Size value (percentage)
         */
        changeObjectSize(size) {
            if (!this.activeObject) return;

            const scale = parseInt(size) / 100;
            
            this.activeObject.set({
                scaleX: scale,
                scaleY: scale
            });
            
            this.canvas.renderAll();
        }

        /**
         * Change object rotation
         *
         * @param {number} rotation Rotation value (degrees)
         */
        changeObjectRotation(rotation) {
            if (!this.activeObject) return;

            this.activeObject.set('angle', parseInt(rotation));
            this.canvas.renderAll();
        }

        /**
         * Reset design
         */
        resetDesign() {
            if (!confirm(cd_vars.messages.confirm_reset)) {
                return;
            }

            // Clear design layers
            this.designLayers.forEach(layer => {
                this.canvas.remove(layer.object);
            });

            this.designLayers = [];
            this.updateLayersPanel();

            // Reload template
            this.loadTemplateContent();
        }

        saveDesign() {
            this.showLoading();
            
            // Save current view first
            this.saveCurrentViewLayers();
            
            // Create a copy of the canvas for preview
            const tempCanvas = document.createElement('canvas');
            const tempContext = tempCanvas.getContext('2d');
            
            tempCanvas.width = this.canvas.width;
            tempCanvas.height = this.canvas.height;
            
            // Fill with white background
            tempContext.fillStyle = '#ffffff';
            tempContext.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
            
            // Draw canvas to temp canvas
            tempContext.drawImage(this.canvas.lowerCanvasEl, 0, 0);
            
            // Get data URL for preview
            const previewImage = tempCanvas.toDataURL('image/png');
            
            // Prepare design data for all views
            const designData = {
                currentView: this.currentView,
                views: {}
            };
            
            // Add data for each view
            Object.keys(this.designLayers).forEach(viewType => {
                if (this.templateViews[viewType]) {
                    designData.views[viewType] = {
                        elements: this.designLayers[viewType].map(layer => {
                            const baseData = {
                                id: layer.id,
                                name: layer.name,
                                type: layer.type,
                                left: layer.object.left,
                                top: layer.object.top,
                                scaleX: layer.object.scaleX,
                                scaleY: layer.object.scaleY,
                                angle: layer.object.angle
                            };
                            
                            // Add type-specific data
                            switch (layer.type) {
                                case 'svg':
                                    return {
                                        ...baseData,
                                        svg_content: layer.svg_content,
                                        file_url: layer.file_url,
                                        text_elements: layer.text_elements
                                    };
                                case 'image':
                                    return {
                                        ...baseData,
                                        src: layer.src
                                    };
                                case 'text':
                                    return {
                                        ...baseData,
                                        text: layer.object.text,
                                        fontFamily: layer.object.fontFamily,
                                        fontSize: layer.object.fontSize,
                                        fill: layer.object.fill
                                    };
                                default:
                                    return baseData;
                            }
                        })
                    };
                }
            });
            
            // Debug: Log the JSON data size
            const designDataJSON = JSON.stringify(designData);
            console.log('Design data JSON size: ' + designDataJSON.length + ' bytes');
            
            // Step 1: Save design metadata first (without large SVG content)
            const metadataOnly = {
                currentView: designData.currentView,
                views: {}
            };
            
            // Process each view to remove large content
            Object.keys(designData.views).forEach(viewType => {
                if (designData.views[viewType] && designData.views[viewType].elements) {
                    metadataOnly.views[viewType] = {
                        elements: designData.views[viewType].elements.map(element => {
                            // Create a metadata-only copy without large SVG content
                            const metaElement = {...element};
                            
                            // Remove large fields like SVG content
                            if (metaElement.svg_content) {
                                delete metaElement.svg_content;
                            }
                            
                            return metaElement;
                        })
                    };
                }
            });
            
            // Send to server
            $.ajax({
                url: cd_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'cd_save_design',
                    nonce: cd_vars.nonce,
                    template_id: this.options.templateId,
                    design_data: JSON.stringify(metadataOnly),
                    preview_image: previewImage
                },
                success: (response) => {
                    if (response.success) {
                        // Now send each SVG element separately from all views
                        this.saveDesignElements(response.data.design_id);
                    } else {
                        this.hideLoading();
                        alert(response.data.message || cd_vars.messages.save_error);
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    console.error('AJAX error saving design:', status, error);
                    alert(cd_vars.messages.save_error + (status ? ': ' + status : ''));
                }
            });
        }      
        
        // Helper method to save the layers for current view
        saveCurrentViewLayers() {
            // Only save if we have a current view
            if (this.currentView && this.templateViews[this.currentView]) {
                // Get all objects from canvas except the template
                const objects = this.canvas.getObjects().filter(obj => obj.name !== 'template');
                
                // Make sure designLayers for this view exists
                if (!this.designLayers[this.currentView]) {
                    this.designLayers[this.currentView] = [];
                }
                
                // Update the positions and properties of existing layers
                this.designLayers[this.currentView].forEach(layer => {
                    if (layer.object) {
                        // Update properties from the object on canvas
                        const obj = objects.find(o => o === layer.object);
                        if (obj) {
                            layer.left = obj.left;
                            layer.top = obj.top;
                            layer.scaleX = obj.scaleX;
                            layer.scaleY = obj.scaleY;
                            layer.angle = obj.angle;
                            
                            // For text elements, update text-specific properties
                            if (layer.type === 'text' && obj.type === 'i-text') {
                                layer.text = obj.text;
                                layer.fontFamily = obj.fontFamily;
                                layer.fontSize = obj.fontSize;
                                layer.fill = obj.fill;
                            }
                        }
                    }
                });
            }
        }
        
        // New method to save individual design elements with large content
        saveDesignElements(designId) {
            // Find elements with SVG content
            const svgElements = this.designLayers.filter(layer => 
                layer.type === 'svg' && layer.svg_content
            );
            
            // If no SVG elements, we're done
            if (svgElements.length === 0) {
                this.hideLoading();
                alert(cd_vars.messages.save_success);
                this.options.designId = designId;
                return;
            }
            
            // Create a queue to process SVG elements
            let processed = 0;
            
            // Process each SVG element
            svgElements.forEach(element => {
                $.ajax({
                    url: cd_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'cd_save_design_element',
                        nonce: cd_vars.nonce,
                        design_id: designId,
                        element_id: element.id,
                        svg_content: element.svg_content
                    },
                    success: () => {
                        processed++;
                        if (processed === svgElements.length) {
                            // All done
                            this.hideLoading();
                            alert(cd_vars.messages.save_success);
                            this.options.designId = designId;
                        }
                    },
                    error: () => {
                        processed++;
                        if (processed === svgElements.length) {
                            // Continue even with errors
                            this.hideLoading();
                            alert(cd_vars.messages.save_success);
                            this.options.designId = designId;
                        }
                    }
                });
            });
        }

        /**
         * Center objects in canvas
         */
        centerObjects() {
            const objects = this.canvas.getObjects();
            
            objects.forEach(obj => {
                if (obj.name === 'template') {
                    obj.center();
                }
            });
            
            this.canvas.renderAll();
        }

        /**
         * Safely find an element, return null if not found
         * 
         * @param {string} selector jQuery selector
         * @return {jQuery|null} jQuery object or null
         */
        safeFind(selector) {
            const $element = this.container.find(selector);
            if ($element.length === 0) {
                console.warn(`Element not found: ${selector}`);
                return null;
            }
            return $element;
        }

        /**
         * Show loading overlay
         */
        showLoading() {
            this.isLoading = true;
            const $overlay = this.safeFind('.cd-loading-overlay');
            if ($overlay) {
                $overlay.show();
            }
        }

        /**
         * Hide loading overlay
         */
        hideLoading() {
            this.isLoading = false;
            const $overlay = this.safeFind('.cd-loading-overlay');
            if ($overlay) {
                $overlay.hide();
            }
        }

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError(message) {
            this.container.find('.cd-canvas-container').html(`
                <div class="cd-error-message">
                    <p>${message}</p>
                </div>
            `);
        }

        /**
         * Generate unique ID
         *
         * @return {string} Unique ID
         */
        generateId() {
            return 'element-' + Math.random().toString(36).substring(2, 9);
        }

        /**
         * Format bytes to human-readable string
         *
         * @param {number} bytes Bytes
         * @param {number} decimals Decimal places
         * @return {string} Formatted string
         */
        formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
        
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
    }

    // Make the class globally available
    window.ClothingDesigner = ClothingDesigner;

})(jQuery);