#!/bin/bash

# GovTrack.Live Daily Scraping Setup Script
# This script helps set up the cron job for daily scraping operations

echo "🚀 GovTrack.Live Daily Scraping Setup"
echo "====================================="

# Get the current directory
CURRENT_DIR=$(pwd)
PHP_PATH=$(which php)

echo "Current directory: $CURRENT_DIR"
echo "PHP path: $PHP_PATH"

# Check if we're in a Laravel project
if [ ! -f "artisan" ]; then
    echo "❌ Error: This doesn't appear to be a Laravel project directory."
    echo "Please run this script from your Laravel project root."
    exit 1
fi

# Test the scraping commands
echo ""
echo "🧪 Testing scraping commands..."

echo "Testing executive orders scraping..."
$PHP_PATH artisan executive-orders:scrape --stats
if [ $? -eq 0 ]; then
    echo "✅ Executive orders command works"
else
    echo "❌ Executive orders command failed"
fi

echo "Testing congress updates scraping..."
$PHP_PATH artisan scrape:congress-updates --dry-run
if [ $? -eq 0 ]; then
    echo "✅ Congress updates command works"
else
    echo "❌ Congress updates command failed"
fi

echo "Testing congress bills scraping..."
$PHP_PATH artisan scrape:congress-bills --limit=1
if [ $? -eq 0 ]; then
    echo "✅ Congress bills command works"
else
    echo "❌ Congress bills command failed"
fi

# Generate cron entries
echo ""
echo "📅 Generating cron job entries..."

CRON_ENTRIES="
# GovTrack.Live Daily Scraping Jobs
# Generated on $(date)

# Daily scraping at 2:00 AM
0 2 * * * cd $CURRENT_DIR && $PHP_PATH artisan scraping:daily >> $CURRENT_DIR/storage/logs/cron.log 2>&1

# Health check every 6 hours
0 */6 * * * cd $CURRENT_DIR && $PHP_PATH artisan scraping:health-check >> $CURRENT_DIR/storage/logs/health-check.log 2>&1

# Weekly full scrape on Sundays at 1:00 AM
0 1 * * 0 cd $CURRENT_DIR && $PHP_PATH artisan scrape:congress-bills --limit=0 --batch-size=25 >> $CURRENT_DIR/storage/logs/weekly-full-scrape.log 2>&1

# Laravel task scheduler (runs every minute to handle scheduled tasks)
* * * * * cd $CURRENT_DIR && $PHP_PATH artisan schedule:run >> /dev/null 2>&1
"

echo "Cron entries to add:"
echo "$CRON_ENTRIES"

# Create logs directory if it doesn't exist
mkdir -p storage/logs

# Save cron entries to a file
echo "$CRON_ENTRIES" > cron-entries.txt
echo "✅ Cron entries saved to cron-entries.txt"

echo ""
echo "📋 Manual Setup Instructions:"
echo "1. Run 'crontab -e' to edit your cron jobs"
echo "2. Add the entries from cron-entries.txt to your crontab"
echo "3. Save and exit the crontab editor"
echo ""
echo "Or run this command to add them automatically:"
echo "crontab -l > current_cron.txt && cat cron-entries.txt >> current_cron.txt && crontab current_cron.txt"

echo ""
echo "🔧 Additional Setup:"
echo "1. Make sure your .env file has the correct API keys:"
echo "   - CONGRESS_API_KEY=your_congress_api_key"
echo "   - ANTHROPIC_API_KEY=your_anthropic_api_key"
echo ""
echo "2. Test the daily scraping manually:"
echo "   php artisan scraping:daily --dry-run"
echo ""
echo "3. Monitor the health:"
echo "   php artisan scraping:health-check"

echo ""
echo "📊 Scraping Schedule:"
echo "- 1:00 AM Sunday: Full congress bills scrape (weekly)"
echo "- 2:00 AM Daily: All daily scraping operations"
echo "- Every 6 hours: Health check"
echo "- Every minute: Laravel task scheduler"

echo ""
echo "🎉 Setup complete! Check the generated cron-entries.txt file."