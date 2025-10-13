# Congressional Bills Scraping Plan

## Overview
- **Total Bills**: 421,409 bills in Congress 119
- **Current Status**: 50 bills scraped (0.01%)
- **Average Processing Time**: ~2-3 seconds per bill
- **Estimated Total Time**: 24-35 hours for complete scraping

## Database Schema ✅ COMPLETE
- ✅ Bills table with comprehensive metadata
- ✅ Bill actions, sponsors, cosponsors, summaries, subjects
- ✅ Text versions with actual bill text content
- ✅ Proper relationships and indexes
- ✅ Processing status tracking

## Scraping Strategy

### Phase 1: Basic Bill Information (Fast)
```bash
# Scrape all bills with basic info only (no detailed fetching)
php artisan scrape:congress-bills --congress=119 --batch-size=100 --limit=10000
```
- **Estimated Time**: 3-4 hours
- **Purpose**: Get all bill records in database quickly

### Phase 2: Detailed Information (Comprehensive)
```bash
# Scrape detailed information for existing bills
php artisan scrape:congress-bills --congress=119 --batch-size=50 --skip-existing
```
- **Estimated Time**: 20-30 hours
- **Purpose**: Fill in actions, sponsors, cosponsors, subjects, summaries

### Phase 3: Text Content (Selective)
```bash
# Scrape text for bills that have text versions
php artisan scrape:congress-bills --congress=119 --text-only
```
- **Estimated Time**: 5-8 hours
- **Purpose**: Get actual bill text content

## Performance Optimizations

### Current Performance
- ✅ Batch processing (25-100 bills per batch)
- ✅ Rate limiting (1 second pause every 10 bills)
- ✅ Skip existing fully scraped bills
- ✅ Error handling and logging
- ✅ Progress tracking

### Recommended Settings for Full Scrape
```bash
# For maximum throughput while being respectful to API
php artisan scrape:congress-bills \
  --congress=119 \
  --batch-size=100 \
  --limit=0 \
  --skip-existing
```

## Storage Estimates

### Current Data (50 bills)
- **Bills**: 50 records
- **Actions**: 282 records  
- **Sponsors**: 50 records
- **Cosponsors**: 189 records
- **Subjects**: 98 records
- **Text Versions**: 47 records
- **Average Text Size**: 79,344 characters
- **Total Text Storage**: 3.8 MB

### Projected Full Dataset (421,409 bills)
- **Bills**: ~421,409 records
- **Actions**: ~2.4 million records
- **Sponsors**: ~421,409 records
- **Cosponsors**: ~1.6 million records
- **Subjects**: ~830,000 records
- **Text Versions**: ~400,000 records
- **Estimated Text Storage**: ~32 GB
- **Total Database Size**: ~40-50 GB

## Execution Commands

### Start Full Scraping Process
```bash
# Step 1: Scrape all bills (basic info)
php artisan scrape:congress-bills --congress=119 --batch-size=100 --limit=0

# Step 2: Check progress
php artisan db:stats --congress=119

# Step 3: Resume if interrupted (skip existing)
php artisan scrape:congress-bills --congress=119 --batch-size=50 --skip-existing

# Step 4: Scrape text for bills without text
php artisan scrape:congress-bills --congress=119 --text-only
```

### Monitor Progress
```bash
# Check database statistics
php artisan db:stats --congress=119

# Check specific bill
php artisan tinker --execute="App\Models\Bill::where('congress_id', '119-hr1')->with('actions', 'sponsors', 'textVersions')->first()"
```

## Risk Mitigation

### API Rate Limiting
- ✅ Built-in delays between requests
- ✅ Batch processing with pauses
- ✅ Graceful error handling
- ✅ Resume capability with --skip-existing

### Data Integrity
- ✅ Database transactions
- ✅ Unique constraints prevent duplicates
- ✅ Error logging and tracking
- ✅ Validation of required fields

### System Resources
- ✅ Memory efficient batch processing
- ✅ Database indexes for performance
- ✅ Configurable batch sizes
- ✅ Progress tracking and statistics

## Expected Timeline

### Conservative Estimate (50 bills/minute)
- **Total Time**: ~140 hours (6 days continuous)
- **Recommended**: Run in 8-hour daily sessions over 2-3 weeks

### Optimistic Estimate (100 bills/minute)  
- **Total Time**: ~70 hours (3 days continuous)
- **Recommended**: Run in 12-hour daily sessions over 1 week

## Success Metrics
- ✅ All 421,409 bills stored in database
- ✅ >95% of bills have detailed information
- ✅ >80% of bills have full text content
- ✅ Complete relational data (actions, sponsors, etc.)
- ✅ Zero data corruption or duplicates

## Daily Update System ✅ COMPLETE

### Automated Daily Updates
```bash
# Daily updates (6:00 AM) - Check last 2 days for changes
php artisan scrape:congress-updates --congress=119 --days=2 --batch-size=100

# Weekly full check (Sunday 2:00 AM) - Comprehensive review
php artisan scrape:congress-updates --congress=119 --days=7 --force-all --batch-size=50

# Daily statistics logging (11:30 PM)
php artisan db:stats --congress=119
```

### Update Detection Features
- ✅ **New Actions**: Detects and adds new legislative actions
- ✅ **New Cosponsors**: Tracks new bill cosponsors
- ✅ **New Summaries**: Captures updated bill summaries
- ✅ **New Text Versions**: Fetches new bill text versions
- ✅ **Metadata Updates**: Updates bill status, dates, counts
- ✅ **Duplicate Prevention**: Avoids duplicate entries
- ✅ **Smart Filtering**: Only checks recently updated bills

### Management Commands
```bash
# Show current status and recent activity
php artisan congress:manage status

# Run manual update check
php artisan congress:manage update

# Start full scraping process
php artisan congress:manage full-scrape

# Show scheduled task status
php artisan congress:manage schedule-status

# Show recent updates (last 24 hours)
php artisan show:recent-updates --congress=119 --days=1

# Database statistics
php artisan db:stats --congress=119
```

### Scheduler Setup
The system automatically runs daily updates via Laravel's scheduler:
```bash
# Start the scheduler (run continuously)
php artisan schedule:run

# Or set up cron job for production
* * * * * php artisan schedule:run >> /dev/null 2>&1
```

## Ready to Execute! 🚀

The scraper is production-ready and can handle the full dataset. The daily update system ensures your database stays current with the latest legislative activity automatically.