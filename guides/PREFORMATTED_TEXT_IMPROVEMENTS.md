# Congressional Bill Tracker - Preformatted Text Improvements

## Overview
Enhanced the bill text display to use preformatted text that preserves the original formatting from Congress.gov's "Formatted Text" URLs, providing an authentic legislative document experience.

## Key Improvements Made

### 1. **Congress.gov HTML Text Extraction**
- **Direct HTML Processing**: Fetches formatted HTML directly from Congress.gov "Formatted Text" URLs
- **DOM Parsing**: Uses DOMDocument to properly parse and extract text content
- **Multiple Content Selectors**: Tries various selectors to find the main bill content:
  - `<pre>` tags for preformatted content
  - `.generated-html-container` for Congress.gov containers
  - `#billTextContainer` for bill-specific containers
  - `.bill-text` divs for text content
  - `<body>` as fallback

### 2. **Preformatted Text Preservation**
- **Original Formatting**: Preserves the exact formatting from Congress.gov
- **Whitespace Handling**: Maintains intentional spacing and indentation
- **Line Break Preservation**: Keeps original line breaks and paragraph structure
- **Clean Text Extraction**: Removes HTML tags while preserving text structure

### 3. **Enhanced Text Processing**
- **Node-by-Node Processing**: Recursively processes DOM nodes to preserve structure
- **Block Element Recognition**: Adds appropriate line breaks for block elements
- **HTML Entity Decoding**: Properly decodes HTML entities to readable characters
- **Whitespace Normalization**: Cleans excessive whitespace while preserving formatting

### 4. **Professional Monospace Display**
- **Monospace Font**: Uses Courier New/Monaco for authentic document appearance
- **Proper Sizing**: 12px font size for optimal readability
- **Line Height**: 1.4 line height for clear text separation
- **White Background**: Clean white background with subtle border

### 5. **Responsive Design**
- **Mobile Optimization**: Smaller font sizes on mobile devices
- **Tablet Support**: Medium font size for tablet screens
- **Desktop Display**: Full-size font for desktop viewing
- **Print Friendly**: Optimized styles for printing

### 6. **Advanced Styling Features**
- **Custom Scrollbars**: Styled scrollbars for better UX
- **High Contrast Support**: Enhanced visibility for accessibility
- **Dark Mode Support**: Automatic dark theme adaptation
- **Print Optimization**: Clean black text for printing

## Technical Implementation

### **Text Extraction Pipeline**
1. **Fetch HTML**: Get formatted HTML from Congress.gov URL
2. **Parse DOM**: Load HTML into DOMDocument for processing
3. **Content Selection**: Try multiple selectors to find bill content
4. **Node Processing**: Recursively extract text while preserving structure
5. **Text Cleaning**: Remove excessive whitespace and normalize formatting
6. **Display**: Show in preformatted container with monospace font

### **New Methods Added**
```php
extractPreformattedText($html)     // Main extraction function
getNodeTextWithFormatting($node)   // Recursive node processing
cleanPreformattedText($text)       // Text cleanup and normalization
```

### **CSS Enhancements**
- **Monospace Typography**: Professional document appearance
- **Responsive Breakpoints**: Optimized for all screen sizes
- **Accessibility Features**: High contrast and dark mode support
- **Print Styles**: Clean formatting for document printing

## Before vs After Comparison

### **Before (Formatted Text)**
```
A BILL

To improve healthcare access and affordability for all Americans.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

SECTION 1. SHORT TITLE.

📜 This Act may be cited as the 'Healthcare Access and Affordability Act'.
```

### **After (Preformatted Text)**
```
A BILL

To improve healthcare access and affordability for all Americans.

Be it enacted by the Senate and House of Representatives of the United 
States of America in Congress assembled,

SECTION 1. SHORT TITLE.

    This Act may be cited as the "Healthcare Access and Affordability 
Act".

SECTION 2. FINDINGS.

    Congress finds the following:
        (1) Healthcare costs continue to rise, making it difficult for 
    many Americans to access necessary medical care.
        (2) Prescription drug prices have increased significantly over 
    the past decade.
```

## Features Now Available

### ✅ **Authentic Document Display**
- Exact formatting from Congress.gov
- Original spacing and indentation preserved
- Professional monospace typography
- Clean, document-like appearance

### ✅ **Enhanced Readability**
- Proper line spacing for legal documents
- Consistent monospace font for alignment
- Clean white background with subtle border
- Optimized font sizes for different devices

### ✅ **Responsive Design**
- Mobile-optimized font sizes (10px)
- Tablet-friendly display (11px)
- Desktop full-size text (12px)
- Print-optimized formatting

### ✅ **Accessibility Features**
- High contrast mode support
- Dark mode automatic adaptation
- Custom scrollbars for better navigation
- Print-friendly styles

### ✅ **Dynamic Loading**
- On-demand text fetching from Congress.gov
- Loading states with visual feedback
- Error handling for unavailable content
- Fallback to sample data when API unavailable

## User Experience Improvements

### **Professional Appearance**
- Authentic legislative document formatting
- Consistent with official government documents
- Professional monospace typography
- Clean, distraction-free reading experience

### **Better Navigation**
- Preserved original structure and indentation
- Easy to scan section headers and subsections
- Consistent formatting across all bill types
- Proper spacing for complex legal language

### **Device Optimization**
- Readable on mobile devices with smaller text
- Optimal display on tablets and desktops
- Print-friendly formatting for hard copies
- Responsive design for all screen sizes

The bill text now displays exactly as it appears on Congress.gov, providing users with an authentic legislative document reading experience while maintaining excellent readability across all devices!