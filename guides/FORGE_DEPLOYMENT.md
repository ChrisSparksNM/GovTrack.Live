# Laravel Forge MySQL Deployment Guide

## Database Configuration

Your Forge database credentials are now configured in `.env.example`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=forge
DB_USERNAME=forge
DB_PASSWORD="ohLGpOVbBef0e4cuhzgt"
```

## Deployment Steps

### 1. Update Production Environment
In your Laravel Forge dashboard:
- Go to your site → Environment
- Update the database configuration with the MySQL credentials above
- **REQUIRED API Keys:**
  - `CONGRESS_API_KEY` - For scraping bills and members
  - `VOYAGE_API_KEY` - For generating embeddings (semantic search)
- **Optional API Keys:**
  - `ANTHROPIC_API_KEY` - For enhanced chatbot features

### 2. Deploy and Run Migrations
The migrations now have safety checks to prevent "table already exists" errors:
```bash
php artisan migrate --force
```

### 3. Test the Setup
```bash
# Check database connection and stats
php artisan db:stats

# Test member fetching
php artisan members:fetch-all-current --limit=5

# Test bill scraping
php artisan scrape:congress-bills --limit=5 --congress=119
```

### 4. Populate Data
```bash
# Fetch current members of Congress
php artisan members:fetch-all-current

# Scrape recent bills
php artisan scrape:congress-bills --congress=119 --limit=100

# Generate embeddings for search
php artisan embeddings:generate --type=bills
```

## Available Scraper Commands

### Bills
- `php artisan scrape:congress-bills` - Scrape detailed bill information
- `php artisan scrape:congress-updates` - Update existing bills with new actions
- `php artisan db:stats` - Show database statistics

### Members
- `php artisan members:fetch-all-current` - Fetch all current Congress members
- `php artisan members:fetch-all-profiles` - Fetch detailed member profiles
- `php artisan members:fetch-missing-profiles` - Fetch missing member data

### Embeddings & Search
- `php artisan embeddings:generate` - Generate embeddings for semantic search
- `php artisan claude:analyze` - Generate Claude analysis for enhanced search

## Migration Safety Features

All migrations now include safety checks:
- `Schema::hasTable()` checks before creating tables
- `Schema::hasColumn()` checks before adding columns
- No more "table already exists" errors on deployment

## Monitoring

Set up these commands to run regularly:
- Daily: `php artisan scrape:congress-updates` (check for bill updates)
- Weekly: `php artisan members:fetch-missing-profiles` (get new member data)
- As needed: `php artisan embeddings:generate --force` (refresh search embeddings)

## Troubleshooting

### API Key Issues
If embeddings fail or scrapers don't work:
1. **Check API keys:** `php check-api-keys.php`
2. **Test embeddings:** `php artisan test:embeddings`
3. **Check specific config:** `php artisan config:show services.voyage.api_key`

### Database Issues
1. Check database connection: `php artisan tinker` then `DB::connection()->getPdo()`
2. Run migration status: `php artisan migrate:status`
3. Check logs: `tail -f storage/logs/laravel.log`

### Common Fixes
- **Embeddings failing:** Set `VOYAGE_API_KEY` in Forge Environment
- **Scraper failing:** Set `CONGRESS_API_KEY` in Forge Environment
- **Duplicate errors:** Run `php fix-duplicates.php`