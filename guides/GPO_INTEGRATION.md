# Congressional Bill Tracker - GPO Integration

## Overview
Integrated Government Publishing Office (GPO) endpoints from https://gpo.congress.gov/ and https://govinfo.gov/ to fetch actual bill text when Congress.gov API doesn't provide the full content.

## Key Features Added

### 1. **GPO Endpoint Integration**
- **Primary Source**: GPO is now tried first for bill text retrieval
- **Multiple URL Patterns**: Tries various GPO URL formats for maximum success
- **Fallback System**: Falls back to Congress.gov API if GPO fails
- **No API Key Required**: GPO endpoints work without authentication

### 2. **Comprehensive URL Building**
- **Multiple Formats**: HTML, XML, and plain text versions
- **Version Codes**: Tries different bill versions (ih, is, rh, rs, eh, es, enr, etc.)
- **Smart Prioritization**: Prioritizes House versions for House bills, Senate for Senate bills
- **URL Patterns**: Supports both Congress.gov and govinfo.gov URL structures

### 3. **Enhanced Content Validation**
- **Bill Content Detection**: Validates that fetched content is actually a bill
- **Content Quality Check**: Ensures substantial content (>100 characters)
- **Format Recognition**: Handles HTML, XML, and plain text formats
- **Error Handling**: Graceful fallback when content is invalid

### 4. **Source Attribution**
- **Source Tracking**: Identifies whether text came from GPO or Congress.gov
- **Visual Indicators**: Shows source in the UI
- **Link Styling**: Different colors for GPO (blue) vs Congress.gov (green) links
- **Transparency**: Users know exactly where their data comes from

## Technical Implementation

### **GPO URL Patterns**
```
Congress.gov Format:
https://www.congress.gov/{congress}/bills/{type}{number}/BILLS-{congress}{type}{number}{version}.htm

GPO HTML Format:
https://www.govinfo.gov/content/pkg/BILLS-{congress}{type}{number}{version}/html/BILLS-{congress}{type}{number}{version}.htm

GPO XML Format:
https://www.govinfo.gov/content/pkg/BILLS-{congress}{type}{number}{version}/xml/BILLS-{congress}{type}{number}{version}.xml

GPO Text Format:
https://www.govinfo.gov/content/pkg/BILLS-{congress}{type}{number}{version}/text/BILLS-{congress}{type}{number}{version}.txt
```

### **Version Code Priority**
- **House Bills**: ih, rh, eh, enr, pp, rfh, is, rs, es, pcs, rfs
- **Senate Bills**: is, rs, es, enr, pp, pcs, rfs, ih, rh, eh, rfh

### **Content Validation**
Checks for common bill indicators:
- "a bill", "an act"
- "be it enacted"
- "congress finds"
- "section 1", "short title"
- "introduced in"

### **New Methods Added**
```php
fetchBillTextFromGPO($congress, $type, $number)
buildGPOUrls($congress, $type, $number)
getBillVersionCodes($type)
isValidBillContent($content)
extractTextFromGPOContent($content, $format)
```

## Features Now Available

### ✅ **Enhanced Text Retrieval**
- **GPO Priority**: Tries GPO endpoints first for actual bill text
- **Multiple Sources**: Falls back to Congress.gov API if needed
- **No API Key Required**: GPO works without authentication
- **Better Success Rate**: Higher chance of finding actual bill text

### ✅ **Source Transparency**
- **Source Attribution**: Shows whether text came from GPO or Congress.gov
- **Visual Indicators**: Source label in the text display
- **Link Differentiation**: Blue links for GPO, green for Congress.gov
- **User Awareness**: Clear indication of data source

### ✅ **Comprehensive Format Support**
- **HTML Processing**: Extracts formatted text from HTML
- **XML Parsing**: Handles XML bill formats
- **Plain Text**: Supports direct text files
- **Smart Detection**: Automatically determines best format

### ✅ **Robust Error Handling**
- **Graceful Fallbacks**: Multiple fallback options
- **Content Validation**: Ensures quality bill content
- **Error Logging**: Comprehensive error tracking
- **User-Friendly Messages**: Clear error messages for users

## URL Examples

### **House Bill H.R. 1234 (118th Congress)**
```
Primary: https://www.govinfo.gov/content/pkg/BILLS-118hr1234ih/html/BILLS-118hr1234ih.htm
Fallback: https://www.congress.gov/118/bills/hr1234/BILLS-118hr1234ih.htm
XML: https://www.govinfo.gov/content/pkg/BILLS-118hr1234ih/xml/BILLS-118hr1234ih.xml
Text: https://www.govinfo.gov/content/pkg/BILLS-118hr1234ih/text/BILLS-118hr1234ih.txt
```

### **Senate Bill S. 567 (118th Congress)**
```
Primary: https://www.govinfo.gov/content/pkg/BILLS-118s567is/html/BILLS-118s567is.htm
Fallback: https://www.congress.gov/118/bills/s567/BILLS-118s567is.htm
XML: https://www.govinfo.gov/content/pkg/BILLS-118s567is/xml/BILLS-118s567is.xml
Text: https://www.govinfo.gov/content/pkg/BILLS-118s567is/text/BILLS-118s567is.txt
```

## User Experience Improvements

### **Better Text Availability**
- Higher success rate for finding actual bill text
- Works even without Congress.gov API key
- Multiple format options increase chances of success
- Comprehensive version code coverage

### **Source Transparency**
- Users know exactly where their data comes from
- Different visual styling for different sources
- Clear attribution in the interface
- Links to original sources with proper labeling

### **Enhanced Reliability**
- Multiple fallback options prevent failures
- Robust content validation ensures quality
- Error handling provides clear feedback
- Comprehensive logging for troubleshooting

The application now has much better access to actual bill text through the GPO integration, providing users with authentic legislative content from the official government publishing office!