#!/bin/bash
# Launch Non-Profit WordPress Plugin Development with Claude Flow

echo "🏢 Non-Profit Management Plugin Development"
echo "=========================================="

# Check container environment
if [ ! -f /.dockerenv ]; then
    echo "⚠️  Run inside container: docker exec -it almalinux-dev-container bash"
    exit 1
fi

# Load API keys from .env file
if [ -f ~/.env ]; then
    source ~/.env
    echo "🔑 Loaded API keys from .env file"
elif [ -f .env ]; then
    source .env
    echo "🔑 Loaded API keys from local .env file"
else
    # Fallback to bashrc
    source ~/.bashrc
fi

# Determine AI provider - prioritize free Gemini first
if [ ! -z "$GEMINI_API_KEY" ]; then
    LAUNCHER="./claude-flow-gemini"
    echo "🔥 Using Gemini Pro for enterprise development (FREE - load balanced across 2 accounts)"
    echo "💰 Cost: FREE until you hit rate limits, then I'll notify you"
elif [ ! -z "$CLAUDE_API_KEY" ]; then
    LAUNCHER="./claude-flow-claude"
    echo "🔵 Using Claude API for enterprise development (PAID)"
elif [ ! -z "$OPENAI_API_KEY" ]; then
    LAUNCHER="./claude-flow-chatgpt"
    echo "🤖 Using ChatGPT for enterprise development (PAID)"
else
    echo "❌ No API key found! Set GEMINI_API_KEY, CLAUDE_API_KEY, or OPENAI_API_KEY"
    exit 1
fi

echo ""
echo "🎯 Development Options:"
echo "====================="
echo ""
echo "1. 🏗️ Full Plugin Development (3-5 days) - Complete enterprise system"
echo "2. 🧪 Module Demo (1-2 hours) - Single feature demonstration"
echo "3. 📊 Architecture Analysis - Design review and planning"
echo "4. 🔌 WordPress Environment Setup - Local development environment"
echo "5. 📋 Read Project Specification - Review 979-line requirements"
echo ""

read -p "Choose option (1-5): " choice

case $choice in
    1)
        echo ""
        echo "🏗️ Launching Full Enterprise Plugin Development"
        echo "=============================================="
        echo ""
        echo "⚠️  WARNING: This is a complex enterprise-grade development task"
        echo "   • 979-line specification with 15+ modules"
        echo "   • Stripe/Google/GravityForms integrations"
        echo "   • Financial-grade security requirements"
        echo "   • Estimated completion: 3-5 days"
        echo ""
        read -p "Continue with full development? (y/N): " confirm
        
        if [[ $confirm =~ ^[Yy]$ ]]; then
            # Create workspace
            mkdir -p ~/workspace/nonprofit-wordpress-plugin
            cd ~/workspace/nonprofit-wordpress-plugin
            
            # Initialize large hierarchical swarm
            echo "🤖 Initializing 12-agent hierarchical swarm..."
            npx claude-flow@alpha swarm init --topology hierarchical --max-agents 12
            
            # Store project context
            echo "🧠 Loading project specification into memory..."
            npx claude-flow@alpha memory store "specification_file" "project_md_export.md"
            npx claude-flow@alpha memory store "development_type" "wordpress-plugin-enterprise"
            npx claude-flow@alpha memory store "target_organization" "swca-generalizable-template"
            npx claude-flow@alpha memory store "complexity_level" "enterprise-15-modules"
            
            echo "🚀 Starting SPARC TDD workflow..."
            echo "This will create a production-ready WordPress plugin with:"
            echo "• 15+ toggleable feature modules"
            echo "• Stripe payment processing integration"
            echo "• Google Workspace integration (Drive, Calendar, Docs)"
            echo "• GravityForms data synchronization"
            echo "• Role-based access control system"
            echo "• Comprehensive testing and documentation"
            echo ""
            
            $LAUNCHER sparc tdd "$(cat <<'EOF'
**PROJECT: Enterprise Non-Profit Management WordPress Plugin**

Create a comprehensive, production-ready WordPress plugin based on the 979-line specification in project_md_export.md.

**CORE REQUIREMENTS:**
- Organization-neutral design with complete feature toggle system (15+ modules)
- Private WordPress installation model with financial-grade security
- Modular architecture supporting 50-1000+ member organizations
- Complete data portability and import/export capabilities

**FEATURE MODULES (All Toggleable):**
1. **Advanced Member Management** - CRM with lifecycle tracking, relationship mapping, segmentation
2. **Financial Management** - Stripe integration, transaction matching, processing fee handling
3. **Event Management** - SignUpGenius-style volunteer coordination, multi-day event support
4. **Communication & Marketing** - Hootsuite-inspired social media dashboard, email campaigns
5. **Document Management** - Google Drive integration, automated report generation
6. **Analytics & Reporting** - Predictive analytics, custom dashboards, benchmarking
7. **External Integrations** - Stripe, Google Workspace, GravityForms, social media APIs
8. **Advocacy & Community** - Issue tracking, government relations, local business partnerships
9. **Role-Based Access Control** - Custom WordPress roles and capabilities
10. **Data Import/Export** - Migration tools for major non-profit platforms

**TECHNICAL IMPLEMENTATION:**
- WordPress plugin framework with PSR-4 autoloading and proper plugin structure
- MySQL database with WordPress prefix compatibility and optimized indexes
- Stripe PHP SDK for payment processing with transaction matching algorithms
- Google Client Library for complete Workspace integration (Drive, Calendar, Docs, Sheets, Gmail)
- WordPress REST API endpoints for external service communication
- PHPUnit testing framework with 85%+ code coverage
- WordPress Coding Standards compliance with security best practices
- AES-256 encryption for financial data, multi-factor authentication, audit logging

**INTEGRATION REQUIREMENTS:**
- **Stripe API:** Import existing 150+ transactions, payment matching, fee tracking
- **Google Workspace:** OAuth setup, Drive sync, automated document generation
- **GravityForms:** WordPress REST API sync supporting form evolution (215+ entries)
- **Social Media:** Facebook, Instagram, Twitter, LinkedIn posting and analytics
- **Email Marketing:** Built-in Mailchimp-like system + external platform APIs

**SWCA-SPECIFIC REQUIREMENTS (Template for Generalization):**
- Support membership tiers: Individual ($35), Household ($50), Business (configurable)
- Processing fee handling with member preference tracking
- Annual BBQ event template for recurring event management
- Master email list integration (248 contacts)
- Business member support with website/contact information
- Historical data migration tools for existing organizational data

**SECURITY & COMPLIANCE:**
- Financial-grade security with encrypted data storage and transmission
- Role-based access control with custom WordPress capabilities
- Multi-factor authentication for treasurer/admin roles
- Comprehensive audit logging for all financial data modifications
- GDPR compliance with data portability and privacy controls

**DELIVERABLES:**
- Complete WordPress plugin (.zip ready for installation)
- GitHub repository with proper development workflow
- Database migration scripts with sample SWCA data
- Comprehensive user documentation for all role levels
- API integration setup guides with step-by-step instructions
- Security hardening checklist for production deployment
- PHPUnit test suite with integration and unit tests

**TARGET:** Production-ready plugin for immediate deployment to private WordPress installations serving community associations, small non-profits, and neighborhood organizations.

**MEMORY CONTEXT:** Use stored specification_file and development context for complete implementation.
EOF
)"
        else
            echo "Development cancelled."
            exit 0
        fi
        ;;
        
    2)
        echo ""
        echo "🧪 Module Demo: Choose Feature to Demonstrate"
        echo "==========================================="
        echo ""
        echo "1. 💰 Financial Management - Stripe integration demo"
        echo "2. 📅 Event Management - Volunteer coordination system"
        echo "3. 📧 Communication System - Email marketing dashboard"
        echo "4. 📊 Analytics Dashboard - Reporting and visualizations"
        echo "5. 🔗 Google Integration - Drive, Calendar, Docs sync"
        echo ""
        
        read -p "Choose module demo (1-5): " module_choice
        
        mkdir -p ~/workspace/nonprofit-plugin-demo
        cd ~/workspace/nonprofit-plugin-demo
        
        npx claude-flow@alpha swarm init --topology mesh --max-agents 4
        
        case $module_choice in
            1)
                echo "🚀 Creating Stripe Integration Demo..."
                $LAUNCHER sparc tdd "create WordPress plugin module demonstrating Stripe payment integration with transaction matching, processing fee handling, and financial reporting"
                ;;
            2)
                echo "🚀 Creating Event Management Demo..."
                $LAUNCHER sparc tdd "create WordPress plugin module for event management with SignUpGenius-style volunteer coordination and time slot management"
                ;;
            3)
                echo "🚀 Creating Communication System Demo..."
                $LAUNCHER sparc tdd "create WordPress plugin module for email marketing with Hootsuite-inspired social media dashboard"
                ;;
            4)
                echo "🚀 Creating Analytics Dashboard Demo..."
                $LAUNCHER sparc tdd "create WordPress plugin module for analytics and reporting with visual dashboards and predictive insights"
                ;;
            5)
                echo "🚀 Creating Google Integration Demo..."
                $LAUNCHER sparc tdd "create WordPress plugin module for Google Workspace integration with Drive sync, Calendar, and automated document generation"
                ;;
        esac
        ;;
        
    3)
        echo ""
        echo "📊 Architecture Analysis Mode"
        echo "============================"
        echo ""
        
        mkdir -p ~/workspace/nonprofit-plugin-analysis
        cd ~/workspace/nonprofit-plugin-analysis
        
        npx claude-flow@alpha swarm init --topology hierarchical --max-agents 6
        
        echo "🔍 Analyzing project specification and designing system architecture..."
        $LAUNCHER sparc architect "analyze the 979-line non-profit management system specification and create comprehensive system architecture with database design, API integration plan, and development roadmap"
        ;;
        
    4)
        echo ""
        echo "🔌 Setting up WordPress Development Environment"
        echo "=============================================="
        echo ""
        
        # Install LAMP stack components
        echo "📦 Installing LAMP stack for WordPress development..."
        sudo dnf update -y
        sudo dnf install -y httpd php php-mysqlnd php-gd php-xml php-curl php-mbstring php-zip mariadb-server
        
        # Start services
        echo "🚀 Starting Apache and MySQL services..."
        sudo systemctl start httpd mariadb
        sudo systemctl enable httpd mariadb
        
        # Create WordPress database
        echo "🗄️ Setting up WordPress database..."
        sudo mysql -e "CREATE DATABASE nonprofit_wp;"
        sudo mysql -e "CREATE USER 'wp_user'@'localhost' IDENTIFIED BY 'wp_password';"
        sudo mysql -e "GRANT ALL PRIVILEGES ON nonprofit_wp.* TO 'wp_user'@'localhost';"
        sudo mysql -e "FLUSH PRIVILEGES;"
        
        # Download and configure WordPress
        echo "⬇️ Downloading WordPress..."
        cd /var/www/html
        sudo wget https://wordpress.org/latest.tar.gz
        sudo tar -xzf latest.tar.gz
        sudo mv wordpress nonprofit-management
        sudo chown -R apache:apache nonprofit-management
        
        echo "✅ WordPress development environment ready!"
        echo "📍 Access at: http://localhost/nonprofit-management"
        echo "🗄️ Database: nonprofit_wp (user: wp_user, pass: wp_password)"
        ;;
        
    5)
        echo ""
        echo "📋 Project Specification Review"
        echo "==============================="
        echo ""
        
        if [ -f "/Users/mjb9/scripts/almalinux-dev-container/project_md_export.md" ]; then
            echo "📄 Found project specification (979 lines)"
            echo "🔍 Key highlights:"
            echo "• Enterprise WordPress plugin for non-profit management"
            echo "• 15+ toggleable feature modules"
            echo "• Stripe, Google Workspace, GravityForms integrations"
            echo "• Financial-grade security and compliance"
            echo "• Organization-neutral design (SWCA template)"
            echo "• Production-ready for 50-1000+ member organizations"
            echo ""
            echo "📖 View full specification:"
            echo "cat /Users/mjb9/scripts/almalinux-dev-container/project_md_export.md"
        else
            echo "❌ Project specification not found!"
            echo "Expected: project_md_export.md"
        fi
        ;;
        
    *)
        echo "❌ Invalid choice. Please select 1-5."
        exit 1
        ;;
esac

echo ""
echo "✅ Task Complete!"
echo ""
echo "🎯 Available next steps:"
echo "• Monitor swarm activity: npx claude-flow@alpha swarm monitor"
echo "• Check memory usage: npx claude-flow@alpha memory list"
echo "• View performance metrics: npx claude-flow@alpha performance report"
echo "• Review generated code in workspace directory"
echo ""
echo "📚 Documentation:"
echo "• Full workflow: NONPROFIT_CLAUDE_FLOW_WORKFLOW.md"
echo "• Project specification: project_md_export.md"
echo "• Claude Flow setup: CLAUDE_FLOW_COMPLETE_GUIDE.md"