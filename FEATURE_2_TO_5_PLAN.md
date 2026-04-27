# CMS Features 2-5 Implementation Plan

## FEATURE 2: WYSIWYG Rich Text Editor (TinyMCE)
- Package: tinymce via npm
- Blade component: rich-text-editor.blade.php
- Handles multilingual content (5 languages)
- JSON to HTML conversion
- Support for images, links, formatting

## FEATURE 3: Live Preview (Real-time)
- Blade view: admin.cms.sections.edit (add preview panel)
- Alpine.js for real-time sync
- Split-view layout
- Shows live rendering as you type

## FEATURE 4: Audit Trail & Version History
- Migration: sections_versions table
- SectionHistory model to track all changes
- Rollback functionality
- Track admin, timestamp, changes

## FEATURE 5: Media Integration in Editor
- Media picker modal in editor
- Drag-drop upload
- Image preview
- Auto-insert into editor

---

## Implementation Order:
1. WYSIWYG Editor + Admin View
2. Live Preview Component  
3. Audit Trail Database + Model + Functionality
4. Media Integration + Modal

## Testing:
- Unit tests for each feature
- Feature tests for workflows
- Manual verification

---

Implementation Start: NOW
