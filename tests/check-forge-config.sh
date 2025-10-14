#!/bin/bash

echo "🔍 Laravel Forge Configuration Checker"
echo "======================================"

# Check if .env exists
if [ ! -f .env ]; then
    echo "❌ .env file not found!"
    echo "   Run: cp .env.example .env"
    echo "   Then edit .env with your configuration"
    exit 1
fi

echo "✅ .env file exists"

# Check database configuration
echo ""
echo "📊 Database Configuration:"
echo "  DB_CONNECTION=$(grep DB_CONNECTION .env | cut -d '=' -f2)"
echo "  DB_HOST=$(grep DB_HOST .env | cut -d '=' -f2)"
echo "  DB_DATABASE=$(grep DB_DATABASE .env | cut -d '=' -f2)"
echo "  DB_USERNAME=$(grep DB_USERNAME .env | cut -d '=' -f2)"

# Check API keys
echo ""
echo "🔑 API Keys:"
CONGRESS_KEY=$(grep CONGRESS_API_KEY .env | cut -d '=' -f2)
ANTHROPIC_KEY=$(grep ANTHROPIC_API_KEY .env | cut -d '=' -f2)

if [ -n "$CONGRESS_KEY" ]; then
    echo "  ✅ Congress API Key: Set"
else
    echo "  ❌ Congress API Key: Missing"
fi

if [ -n "$ANTHROPIC_KEY" ]; then
    echo "  ✅ Anthropic API Key: Set"
else
    echo "  ❌ Anthropic API Key: Missing"
fi

# Test database connection
echo ""
echo "🔌 Testing Database Connection..."
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Database connection: ✅ Success'; } catch(Exception \$e) { echo 'Database connection: ❌ Failed - ' . \$e->getMessage(); }"

# Check migration status
echo ""
echo "📋 Migration Status:"
php artisan migrate:status

echo ""
echo "🚀 Ready to run setup script:"
echo "   php forge-setup.php"