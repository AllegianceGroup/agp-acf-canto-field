# Changelog

All notable changes to the ACF Canto Field plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.4.0] - 2025-11-04

### Added
- **Grid & List View Toggle**: Users can now switch between grid view (larger thumbnails in cards) and list view (compact horizontal rows) for browsing assets
- **Search Clear Button**: One-click button to clear search queries and reset results, automatically shows/hides based on input
- **Fuzzy Search Fallback**: Enhanced `find_asset_by_filename()` with intelligent three-tier fallback matching:
  1. Priority 1: Exact filename match
  2. Priority 2: Exact name field match
  3. Priority 3: First fuzzy search result (NEW)
- **Enhanced User Feedback**: Added detailed debug logging for all fallback levels when `WP_DEBUG` is enabled

### Changed
- **Asset Matching Strategy**: `find_asset_by_filename()` now returns first fuzzy match when no exact match found, making it more forgiving for filename variations
- **UI Navigation**: View mode persists across searches and tab switches for consistent user experience
- **Search Interface**: Search input now includes inline clear button with proper focus management

### Enhanced
- **User Experience**: View toggle with WordPress dashicons (grid-view/list-view) for familiar interface
- **CSS Responsive Design**: List view adapts to screen size with optimized layouts for mobile devices
- **Developer Experience**: Consistent three-tier fallback logic in both `find_asset_by_filename()` and `find_best_filename_match()` methods
- **Asset Discovery**: More flexible asset retrieval that handles filename variations, typos, and partial matches

### Technical Details
- **View State Management**: JavaScript maintains `currentViewMode` variable to track grid/list preference
- **CSS Classes**: `.list-view` modifier class for grid-to-list transformation
- **Fallback Logging**: Detailed logs show which priority level matched (exact filename, exact name, or fuzzy)
- **Search Clear UX**: Button positioned inside input field with absolute positioning and hover effects

## [2.3.0] - 2025-09-25

### Added
- **Direct URL Support**: Full support for Canto's direct document URLs (`/direct/document/ASSET_ID/TOKEN/original`)
- **WP All Import Pro Compatibility**: Seamless import of assets directly using URLs from CSV/XML files
- **Multi-Pattern URL Recognition**: Support for 4 different URL patterns with automatic detection
- **Smart URL Prioritization**: Direct URLs preferred over legacy API binary URLs
- **New Helper Function**: `acf_canto_extract_asset_id()` for extracting asset IDs from various URL formats
- **Enhanced Asset Data Formatting**: Constructs direct URLs by default when possible
- **Comprehensive Documentation**: Updated README with detailed usage examples and technical specifications

### Changed
- **MAJOR ENHANCEMENT**: Direct URLs now prioritized as the default format throughout the plugin
- **JavaScript Asset Selection**: Updated with 4-tier URL prioritization system (direct_url → download_url → constructed direct → API binary)
- **Asset Data Processing**: Enhanced `format_asset_data_from_search()` to construct and prioritize direct URLs
- **URL Pattern Matching**: Expanded from 1 to 4 supported URL patterns with regex optimization
- **Import Workflow**: Optimized for WP All Import Pro with automatic URL validation and processing

### Fixed
- **Plugin Header**: Corrected corrupted plugin header information  
- **Version Constants**: Added missing `ACF_CANTO_FIELD_VERSION` constant definition
- **AJAX Security**: Enhanced nonce verification and capability checks in AJAX handlers
- **Plugin Constants**: Added `ACF_CANTO_FIELD_PLUGIN_FILE`, `ACF_CANTO_FIELD_PLUGIN_URL`, and `ACF_CANTO_FIELD_PLUGIN_PATH`

### Enhanced
- **Security**: Strengthened AJAX request validation with proper nonce verification
- **Performance**: Maintained 1-hour transient caching with optimized cache keys
- **Error Handling**: Improved graceful fallback between URL formats
- **User Experience**: Enhanced asset selection interface with better URL handling
- **Developer Experience**: Comprehensive helper functions and technical documentation

### Technical Details
- **URL Processing Pipeline**: Implemented 5-stage URL validation and processing system
- **Pattern Recognition**: Advanced regex patterns for reliable asset ID extraction
- **Backward Compatibility**: Full support for existing API binary URLs while prioritizing direct URLs
- **Import Integration**: Seamless WP All Import Pro integration with automatic URL processing

## [2.1.0] - 2024-08-13

### Added
- **New API Helper Classes**: Added `ACF_Canto_API`, `ACF_Canto_Logger`, and `ACF_Canto_Asset_Formatter` for better code organization
- **Centralized Logging System**: Configurable debug levels (ERROR, WARNING, INFO, DEBUG) via `ACF_CANTO_LOG_LEVEL` constant
- **Advanced Caching Strategy**: Multi-tier caching with optimized cache keys and namespace separation
- **Comprehensive Error Handling**: Full try-catch blocks and WP_Error handling throughout
- **Constants for Configuration**: Replaced magic numbers with proper constants for timeouts, limits, and file types
- **Enhanced Documentation**: Improved PHPDoc blocks with proper @param and @return types

### Changed
- **MAJOR CODE REFACTOR**: Complete architectural overhaul for better maintainability and performance
- **Method Refactoring**: Broke down 900+ line methods into focused, single-responsibility functions
- **Improved API Communication**: Centralized HTTP request handling with proper timeout and error management
- **Better Asset Data Formatting**: Dedicated formatter class for consistent data structure across all responses
- **Optimized Cache Keys**: More efficient cache key generation using serialized data instead of simple MD5 hashes
- **Modern PHP Standards**: Updated codebase to follow WordPress coding standards with separation of concerns

### Enhanced
- **Performance**: Reduced duplicate code and API calls through better abstraction
- **Reliability**: Better fallback mechanisms and graceful error handling
- **Developer Experience**: Structured logging with context data and exception stack traces
- **Debugging**: Advanced logging system with configurable levels for different deployment scenarios

### Maintained
- **Backward Compatibility**: All existing functionality continues to work seamlessly
- **Download URL-Based Storage**: Fields continue to store asset download URLs as unique identifiers
- **Legacy Support**: Filename-based helper functions continue to work for migration scenarios
- **Security**: Maintained all existing security measures including nonce verification and capability checks

## [1.1.0] - Previous Release

### Changed
- **BREAKING CHANGE**: Simplified storage format to filename-based strings
- Added filename-based functionality and helper functions
- Migration support for transitioning from old storage formats

### Added
- `acf_canto_find_asset_by_filename()` helper function
- `acf_canto_get_asset()` flexible retrieval function
- AJAX endpoint for filename-based searches
- Smart filename extraction from Canto metadata

## [1.0.0] - Initial Release

### Added
- Custom ACF field type for Canto asset selection
- AJAX-powered search functionality
- Tree navigation for albums and folders
- Multiple return format options (Object, ID, URL)
- Thumbnail proxy for authenticated asset access
- Responsive modal interface with tab navigation
- Support for images, videos, and documents
- Asset data caching for improved performance
- Debug logging for troubleshooting
- Comprehensive usage examples and documentation