# Changelog

All notable changes to IIIF Random Block will be documented in this file.

## [1.2.4] - 2025-09-17

### Added
- Info button (ⓘ) appears on hover over the carousel image; clicking opens an information panel the same width as the image.
- New settings field “Info panel text” with rich-text editor; the content is rendered in the info panel (processed text).
- Info panel: Close button (SVG) in the top-right; also closes on outside click and Escape key.
- While the panel is open, the carousel auto-rotation pauses; it resumes when closed.

### Changed
- Info panel styling: minimum height set to half of the image height; left-aligned text; white panel background with subtle border and enhanced shadow for depth.
- Info button visual: updated to provided SVG; white background, black icon; increased size for better usability.

### Fixed
- Immediate reflection of settings changes: Block now uses config cache tags and language context so saving the settings updates the front-end without manual cache clear.

