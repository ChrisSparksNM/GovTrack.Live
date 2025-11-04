# Design Document

## Overview

This design addresses the database schema mismatches and data type conversion errors in the Congress Chatbot Service. The solution involves updating database queries to match the actual schema, implementing proper error handling, and ensuring robust data type management.

## Architecture

The fix will focus on three main areas:
1. **Database Query Layer**: Update all queries to use correct column names and table relationships
2. **Data Processing Layer**: Implement proper type checking and conversion
3. **Error Handling Layer**: Add graceful fallbacks and comprehensive logging

## Components and Interfaces

### 1. Database Query Corrections

**Bill Actions Query Fix**
- Current problematic query uses `action_text` and `action_type`
- Correct schema uses `text` and `type` columns
- Need to update the query in the `getDatabaseQueryService()` method calls

**Bill Sponsors Query Fix**
- Current query attempts to use non-existent `sponsor_bioguide_id` column on bills table
- Correct approach: Join `bills` table with `bill_sponsors` table using `bill_id`
- Schema shows `bill_sponsors` has: `bill_id`, `bioguide_id`, `full_name`, `party`, `state`

### 2. Data Type Management

**Object/Array Conversion Issue**
- Error occurs at line 1776 in `addAnalyticalInsights` method
- Database results return `stdClass` objects, but code expects arrays
- Need to implement consistent type conversion using `(array)` casting or `toArray()` method

### 3. Query Structure Updates

**Sponsor Data Query**
```sql
-- Current (broken):
SELECT members.* FROM bills 
INNER JOIN members ON bills.sponsor_bioguide_id = members.bioguide_id

-- Correct:
SELECT members.*, COUNT(*) as bills_sponsored FROM bills 
INNER JOIN bill_sponsors ON bills.id = bill_sponsors.bill_id
INNER JOIN members ON bill_sponsors.bioguide_id = members.bioguide_id
GROUP BY members.bioguide_id
```

**Bill Actions Query**
```sql
-- Current (broken):
SELECT bills.*, bill_actions.action_text, bill_actions.action_type
FROM bill_actions INNER JOIN bills ON bill_actions.bill_id = bills.id

-- Correct:
SELECT bills.*, bill_actions.text, bill_actions.type
FROM bill_actions INNER JOIN bills ON bill_actions.bill_id = bills.id
```

## Data Models

### Database Result Processing

**Before (Problematic)**
```php
foreach ($data['bills'] as $bill) {
    $bill = (array) $bill; // This fails if $bill is already an array
    if (!empty($bill['policy_area'])) {
        // Process...
    }
}
```

**After (Robust)**
```php
foreach ($data['bills'] as $bill) {
    // Ensure consistent array format
    $billArray = is_array($bill) ? $bill : (array) $bill;
    if (!empty($billArray['policy_area'])) {
        // Process...
    }
}
```

### Query Result Handling

**Sponsor Query Result Structure**
```php
[
    'bioguide_id' => 'V000133',
    'full_name' => 'Jefferson Van Drew',
    'party_abbreviation' => 'R',
    'state' => 'NJ',
    'chamber' => 'House',
    'bills_sponsored' => 15
]
```

**Bill Action Result Structure**
```php
[
    'bill_id' => 12345,
    'action_date' => '2024-11-01',
    'text' => 'Introduced in House',
    'type' => 'IntroReferral'
]
```

## Error Handling

### Graceful Degradation Strategy

1. **Primary Query Attempt**: Try the corrected query with proper schema
2. **Fallback on Error**: If query fails, log error and continue with available data
3. **Partial Data Response**: Provide response based on successfully retrieved data
4. **Clear User Communication**: Indicate which data sources were available

### Logging Strategy

```php
// Log specific SQL errors with context
Log::warning('Bill sponsors query failed', [
    'error' => $e->getMessage(),
    'query_type' => 'bill_sponsors',
    'fallback_used' => true
]);

// Log data type conversion issues
Log::error('Data type conversion error', [
    'expected_type' => 'array',
    'actual_type' => gettype($data),
    'method' => 'addAnalyticalInsights',
    'line' => __LINE__
]);
```

## Testing Strategy

### Unit Tests

1. **Database Query Tests**
   - Test sponsor query with correct table joins
   - Test bill actions query with correct column names
   - Test error handling when queries fail

2. **Data Type Conversion Tests**
   - Test handling of stdClass objects
   - Test handling of arrays
   - Test mixed data type scenarios

3. **Error Handling Tests**
   - Test graceful degradation when sponsor data unavailable
   - Test partial response generation
   - Test logging functionality

### Integration Tests

1. **End-to-End Chatbot Tests**
   - Test specific member queries (e.g., "Jefferson Van Drew recent bills")
   - Test response generation with partial data
   - Test error recovery scenarios

### Database Schema Validation

1. **Schema Compatibility Tests**
   - Verify all queries use existing columns
   - Test joins between related tables
   - Validate data type expectations

## Implementation Approach

### Phase 1: Database Query Fixes
- Update sponsor queries to use proper table joins
- Fix bill actions column name references
- Add query validation and error handling

### Phase 2: Data Type Management
- Implement robust type checking in data processing methods
- Add consistent array conversion utilities
- Update `addAnalyticalInsights` method to handle mixed types

### Phase 3: Error Handling Enhancement
- Add comprehensive logging for database operations
- Implement graceful fallback mechanisms
- Improve user-facing error messages

### Phase 4: Testing and Validation
- Create comprehensive test suite
- Validate against actual database schema
- Test with real user queries