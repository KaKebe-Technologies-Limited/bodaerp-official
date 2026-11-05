// BodaERP rich-text editor: every <textarea data-rich-text> becomes a CKEditor 5 (GPL, self-hosted,
// assets/vendor/ckeditor5). Images dropped or pasted into the editor are uploaded to the media library
// through api/media-upload.php. Load with partials/editor-assets.php.
(function () {
    'use strict';
    if (!window.CKEDITOR) { console.error('CKEditor files are missing: assets/vendor/ckeditor5/ckeditor5.umd.js'); return; }

    const {
        ClassicEditor, Essentials, Paragraph, Heading, Bold, Italic, Underline, Strikethrough, Subscript, Superscript, Code,
        RemoveFormat, Link, AutoLink, List, TodoList, Indent, IndentBlock, Alignment, BlockQuote, CodeBlock, HorizontalLine,
        Table, TableToolbar, TableProperties, TableCellProperties, TableCaption, Image, ImageToolbar, ImageCaption, ImageStyle,
        ImageResize, ImageUpload, ImageInsert, LinkImage, SimpleUploadAdapter, MediaEmbed, FontFamily, FontSize, FontColor,
        FontBackgroundColor, Highlight, FindAndReplace, WordCount, SourceEditing, ShowBlocks, SpecialCharacters,
        SpecialCharactersEssentials, Autoformat, TextTransformation, PasteFromOffice, GeneralHtmlSupport, Undo
    } = window.CKEDITOR;

    const meta = name => (document.querySelector('meta[name="' + name + '"]') || {}).content || '';

    function create(textarea) {
        const isEmail = textarea.dataset.richText === 'email';
        const counter = document.createElement('div');
        counter.className = 'form-text text-end';
        counter.setAttribute('aria-live', 'polite');

        return ClassicEditor.create({
            attachTo: textarea,
            licenseKey: 'GPL',
            plugins: [Essentials, Paragraph, Heading, Bold, Italic, Underline, Strikethrough, Subscript, Superscript, Code,
                RemoveFormat, Link, AutoLink, List, TodoList, Indent, IndentBlock, Alignment, BlockQuote, CodeBlock, HorizontalLine,
                Table, TableToolbar, TableProperties, TableCellProperties, TableCaption, Image, ImageToolbar, ImageCaption, ImageStyle,
                ImageResize, ImageUpload, ImageInsert, LinkImage, SimpleUploadAdapter, MediaEmbed, FontFamily, FontSize, FontColor,
                FontBackgroundColor, Highlight, FindAndReplace, WordCount, SourceEditing, ShowBlocks, SpecialCharacters,
                SpecialCharactersEssentials, Autoformat, TextTransformation, PasteFromOffice, GeneralHtmlSupport, Undo],
            toolbar: {
                items: ['undo', 'redo', '|', 'heading', '|', 'fontFamily', 'fontSize', 'fontColor', 'fontBackgroundColor', 'highlight', '|',
                    'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'code', 'removeFormat', '|',
                    'link', 'insertImage', 'mediaEmbed', 'insertTable', 'blockQuote', 'codeBlock', 'horizontalLine', 'specialCharacters', '|',
                    'alignment', 'bulletedList', 'numberedList', 'todoList', 'outdent', 'indent', '|',
                    'findAndReplace', 'showBlocks', 'sourceEditing'],
                shouldNotGroupWhenFull: false
            },
            root: { placeholder: textarea.getAttribute('placeholder') || '', label: textarea.getAttribute('aria-label') || 'Rich text editor' },
            heading: { options: [
                { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                { model: 'heading2', view: 'h2', title: 'Heading', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Subheading', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: 'Small heading', class: 'ck-heading_heading4' }
            ] },
            fontFamily: { options: ['default', 'Arial, Helvetica, sans-serif', 'Georgia, serif', 'Segoe UI, Tahoma, sans-serif', 'Courier New, monospace'], supportAllValues: false },
            fontSize: { options: [12, 14, 'default', 18, 22, 28] },
            image: {
                toolbar: ['imageTextAlternative', 'toggleImageCaption', '|', 'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|', 'linkImage', 'resizeImage'],
                insert: { integrations: ['upload', 'url'] }
            },
            table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties', 'toggleTableCaption'] },
            link: { addTargetToExternalLinks: true, defaultProtocol: 'https://' },
            mediaEmbed: { previewsInData: false },
            codeBlock: { languages: [{ language: 'plaintext', label: 'Plain text' }, { language: 'php', label: 'PHP' }, { language: 'sql', label: 'SQL' }, { language: 'javascript', label: 'JavaScript' }] },
            // Emails need class="button" on links and the classed tables/divs of the email layout to survive editing.
            htmlSupport: { allow: [{ name: /^(a|p|div|span|table|td|th|tr|blockquote|img)$/, classes: true, styles: isEmail }] },
            simpleUpload: {
                uploadUrl: meta('bodaerp-base-url') + '/api/media-upload.php',
                headers: { 'X-CSRF-Token': meta('csrf-token') },
                withCredentials: true
            },
            wordCount: { onUpdate: stats => { counter.textContent = stats.words + ' words, ' + stats.characters + ' characters'; } }
        }).then(editor => {
            editor.ui.element.after(counter);
            const form = textarea.form;
            if (form) form.addEventListener('submit', () => editor.updateSourceElement(), true);
            if (textarea.required) {
                textarea.required = false;
                if (form) form.addEventListener('submit', e => {
                    if (editor.getData().trim() === '') { e.preventDefault(); e.stopImmediatePropagation(); editor.editing.view.focus(); counter.textContent = 'This field cannot be empty.'; counter.classList.add('text-danger'); }
                }, true);
            }
            return editor;
        }).catch(err => {
            console.error('CKEditor failed to start', err);
            textarea.hidden = false;
            textarea.classList.add('form-control');
        });
    }

    document.querySelectorAll('textarea[data-rich-text]').forEach(create);
})();
