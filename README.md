# AlmaLinux 9 Development Container

🚀 A comprehensive development environment based on AlmaLinux 9 with all essential tools, web server stack, and modern CLI utilities.

## 🎨 Features

### **Colorful Welcome Banner**
- Rainbow "BayaDev" ASCII art using toilet
- Container information and tool summary
- Personalized MOTD (Message of the Day)

### **Programming Languages & Runtimes**
- **Python 3.9** with development packages
- **Node.js 20.19.4** with npm, yarn, pnpm (upgraded for Claude Flow)
- **Go 1.22** with full toolchain
- **Rust** with Cargo package manager
- **Java 17 OpenJDK** with Maven
- **PHP 8.0** with comprehensive extensions

### **Web Server Stack**
- **Apache HTTP Server 2.4** with SSL support and self-signed certificates
- **MariaDB** full installation for WordPress development
- **PHP-FPM** configured for optimal performance
- **Server monitoring** with server-info and server-status pages
- **Database support** for MariaDB, MySQL, PostgreSQL, SQLite
- **HTTPS Proxy** configuration for secure WordPress development

### **Development Tools**
- **Build Tools:** gcc, g++, make, cmake, automake, autoconf
- **Version Control:** git, svn, mercurial, GitHub CLI
- **Editors:** vim, nano, VS Code Server (code-server)
- **Package Managers:** pip, pipenv, poetry, npm, yarn, cargo, maven

### **Modern CLI Utilities**
- **File Management:** eza (modern ls), bat (syntax highlighting cat)
- **Search Tools:** ripgrep, fd-find, fzf (fuzzy finder)
- **Text Processing:** jq, yq, xmlstarlet
- **Multiplexing:** tmux, screen, zsh with oh-my-zsh

### **DevOps & Cloud Tools**
- **Containers:** Docker CLI, docker-compose
- **Kubernetes:** kubectl, helm
- **Infrastructure:** Terraform, Ansible
- **Cloud Platforms:** AWS CLI, Azure CLI
- **Google Services:** GAM (Google Apps Manager), Gemini CLI

### **AI Development Tools**
- **Claude CLI** (@anthropic-ai/claude-code)
- **Claude Flow** (claude-flow@alpha) v2.0.0-alpha.101 - AI orchestration and swarm coordination
- **Gemini CLI** (Google's generative AI)
- **OpenCode AI** development assistant
- **Multi-provider API support** (Gemini, OpenAI, Claude with load balancing)

### **WordPress Development Stack**
- **Main Plugin**: SWCA Membership Management System
- **Purpose**: Complete non-profit membership management with modular features
- **Architecture**: Web-based administration with no CLI dependencies
- **Features**: Email management, event coordination, financial tracking, committee management
- **Access**: Password-protected dashboard at `/dashboard` (password: F1v3C0rn3rs)
- **Roles**: Custom WordPress roles for Member, Officer, Treasurer, Committee Chair
- **Integration**: Stripe, Google Calendar, Gmail, Google Drive APIs (optional)
- **Deployment**: Production-ready package at `deployment-packages/swca-membership-management.zip`
- **Administration**: All tools accessible via WordPress admin interface (CRM menu)
- **Status**: ✅ Production-tested with 197 members, all undefined property warnings fixed

### **Database Clients**
- PostgreSQL client
- MySQL client  
- Redis client
- SQLite with development libraries

### **Python Data Science Stack**
- **Core Libraries:** pandas, numpy, matplotlib
- **Development:** black, flake8, pylint, pytest
- **Interactive:** JupyterLab, IPython
- **Web Frameworks:** Support for Django, FastAPI

### **System Monitoring & Debugging**
- **Performance:** htop, iotop, sysstat
- **Process:** lsof, strace, pstree
- **Network:** tcpdump, nmap, telnet, ssh clients

## 🚀 Quick Start

### **Using Docker Compose (Recommended)**

1. **Clone this repository:**
   ```bash
   git clone <repository-url>
   cd almalinux-dev-container
   ```

2. **Build and start the container:**
   ```bash
   docker-compose up -d
   ```

3. **Access the container:**
   ```bash
   docker exec -it almalinux-dev-container bash
   ```

4. **Initialize Claude Flow (first time only):**
   ```bash
   # Claude Flow will auto-initialize on first container start
   # Or manually run:
   ~/setup-claude-flow.sh
   ```

### **Using Docker directly**

```bash
# Build the image
docker build -t almalinux-dev .

# Run the container
docker run -it --name almalinux-dev-container \
  -p 80:80 -p 443:443 -p 8080:8080 -p 3000:3000 \
  -p 5001:5000 -p 8000:8000 -p 8888:8888 \
  -v $(pwd)/workspace:/home/developer/workspace \
  almalinux-dev bash
```

## 🌐 Web Services

The container exposes several web services:

| Service | Port | URL | Description |
|---------|------|-----|-------------|
| Apache HTTP | 80 | http://localhost | Main web server |
| Apache HTTPS | 443 | https://localhost | SSL web server (proxy to WordPress) |
| WordPress | 8080 | http://localhost:8080 | PHP dev server (proxied via HTTPS) |
| SWCA Dashboard | 8080 | http://localhost:8080/dashboard | Membership management system (password: F1v3C0rn3rs) |
| SWCA Admin Tools | 8080 | http://localhost:8080/wp-admin | CRM menu with web-based administrative tools |
| VS Code Server | 8080 | http://localhost:8080 | Browser-based VS Code |
| JupyterLab | 8888 | http://localhost:8888 | Data science notebook |
| Node.js Apps | 3000 | http://localhost:3000 | Development server |
| Python Apps | 5001 | http://localhost:5001 | Flask/Django apps |
| FastAPI/Django | 8000 | http://localhost:8000 | API development |

### **Apache Monitoring Pages**
- **Server Status:** http://localhost/server-status
- **Server Info:** http://localhost/server-info
- **Auto Status:** http://localhost/server-status?auto (machine-readable)

## 📁 Directory Structure

```
/
├── home/developer/          # User home directory
│   └── workspace/          # Mounted workspace volume
├── var/www/html/           # Apache document root
│   ├── index.html         # Welcome page
│   └── info.php           # PHP information page
├── etc/httpd/conf.d/      # Apache configuration
│   ├── php-fpm.conf       # PHP-FPM proxy configuration
│   └── server-info-status.conf  # Server monitoring
└── deployment-packages/    # Production deployment files
    ├── swca-membership-management.zip  # Complete WordPress plugin
    └── INSTALLATION_GUIDE.md          # Deployment documentation
```

## 📦 Production Deployment

### **SWCA Membership Management Plugin**

Ready-to-deploy WordPress plugin with complete web-based administration:

- **Package**: `deployment-packages/swca-membership-management.zip` (58KB)
- **Installation**: Upload via WordPress admin → Plugins → Add New → Upload Plugin
- **Features**: All administrative tools integrated into web interface
- **No CLI Required**: Everything accessible through WordPress admin panel

#### **Web-Based Administrative Tools**
| Tool | Location | Purpose |
|------|----------|----------|
| Main Dashboard | CRM → SWCA Dashboard | Overview and quick actions |
| Export/Import | CRM → Export & Import | Complete data migration packages |
| Stripe Refunds | CRM → Stripe Refunds | Process refunds with secure API input |
| Data Import | CRM → Data Import Tools | Historical membership data upload |
| Financial Mgmt | CRM → Financial Management | Income/expense tracking |
| Member Tools | CRM → Member Tools | Member management utilities |

#### **Key Deployment Features**
- ✅ **Web-Only Administration** - No SSH/CLI access required
- ✅ **Secure API Handling** - Stripe keys entered via form, never stored
- ✅ **File Upload Interface** - CSV import via browser
- ✅ **Preview & Confirmation** - Review all changes before applying
- ✅ **Complete Migration** - Export/import entire database between servers
- ✅ **Multi-Year Tracking** - Historical membership data comparison
- ✅ **Table Prefix Compatibility** - Works with any WordPress table prefix configuration
- ✅ **Production-Tested** - Successfully deployed with 197+ members, all warnings resolved

### **Installation Guide**
Complete deployment instructions available in `deployment-packages/INSTALLATION_GUIDE.md`

## 🔧 Configuration

### **Environment Variables**
- `LANG=en_US.UTF-8` - System locale
- `LC_ALL=en_US.UTF-8` - Locale settings
- `PATH` - Includes Go, Rust, and local binaries
- `GOPATH=/home/developer/go` - Go workspace
- `JAVA_HOME=/usr/lib/jvm/java-17-openjdk` - Java installation

### **Volume Mounts**
- `./workspace:/home/developer/workspace` - Development workspace
- `~/.config/gam:/home/developer/.config/gam` - GAM configuration
- `~/.ssh:/home/developer/.ssh:ro` - SSH keys (read-only)
- `~/.gitconfig:/home/developer/.gitconfig:ro` - Git config (read-only)
- `/var/run/docker.sock:/var/run/docker.sock` - Docker-in-Docker

### **User Configuration**
- **Default User:** `developer`
- **Sudo Access:** Full sudo privileges without password
- **Shell:** bash with enhanced prompt
- **Home Directory:** `/home/developer`

## 🛠️ Usage Examples

### **Claude Flow AI Orchestration**
```bash
# Launch Non-Profit Plugin Development Menu
./launch-nonprofit-plugin.sh

# Initialize a development swarm
npx claude-flow@alpha swarm init --topology hierarchical --max-agents 12

# Start a SPARC TDD project
npx claude-flow@alpha sparc tdd "create WordPress feature"

# Direct SPARC TDD workflow
./claude-flow-gemini sparc tdd "create WordPress feature"

# Check swarm status
npx claude-flow@alpha swarm status

# Monitor real-time progress
npx claude-flow@alpha swarm monitor

# List stored memories
npx claude-flow@alpha memory list
```

### **WordPress Development**
```bash
# Start WordPress development server
docker exec almalinux-dev-container bash -c "cd /home/developer/wordpress-test/wordpress && php -S 0.0.0.0:8080" &

# Start Apache with SSL
sudo /usr/sbin/httpd

# Access WordPress & SWCA System
# WordPress: http://localhost:8080
# SWCA Dashboard: http://localhost:8080/dashboard (password: F1v3C0rn3rs)
# WordPress Admin: http://localhost:8080/wp-admin (admin/admin123)
# HTTPS Proxy: https://localhost:443

# Deploy plugin updates (development only)
docker cp /Users/mjb9/scripts/almalinux-dev-container/swca-membership-export-corrected.php almalinux-dev-container:/var/www/html/wp-content/plugins/swca-membership-export-corrected/swca-membership-export-corrected.php

# Production deployment
# Upload deployment-packages/swca-membership-management.zip via WordPress admin
# All administrative tools available at: CRM > [Tool Name]

# Test with Chromium
chromium-browser --headless --no-sandbox --disable-dev-shm-usage --dump-dom http://localhost:8080/dashboard
```

### **Web Development**
```bash
# Start Apache and PHP-FPM (in container)
sudo /usr/sbin/httpd
sudo /usr/sbin/php-fpm --daemonize

# Create a PHP project
echo "<?php echo 'Hello World!'; ?>" > /var/www/html/hello.php
```

### **Python Development**
```bash
# Create virtual environment
python3 -m venv myproject
source myproject/bin/activate

# Install packages
pip install flask django fastapi

# Start JupyterLab
jupyter lab --ip=0.0.0.0 --port=8888 --allow-root
```

### **Node.js Development**
```bash
# Create new project
npm init -y
npm install express

# Start development server
npm run dev
```

### **Go Development**
```bash
# Initialize Go module
go mod init myproject

# Build and run
go build -o app main.go
./app
```

### **Container Operations**
```bash
# Access container shell
docker exec -it almalinux-dev-container bash

# Check container logs
docker-compose logs -f

# Stop container
docker-compose down

# Rebuild container
docker-compose build --no-cache
```

## 🔍 Monitoring & Debugging

### **System Monitoring**
```bash
# Process monitoring
htop                    # Interactive process viewer
iotop                   # I/O monitoring
ps aux | grep nginx     # Process listing

# Performance analysis
strace -p <pid>         # System call tracing
lsof -i :80            # Open files/network connections
```

### **Apache Monitoring**
- **Real-time Status:** http://localhost/server-status
- **Configuration Info:** http://localhost/server-info?config
- **Module List:** http://localhost/server-info?list

### **Container Health**
```bash
# Check running services
docker exec almalinux-dev-container ps aux

# Test web services
curl http://localhost/server-status
curl http://localhost:8080  # VS Code Server
```

## 🧪 Development Workflows

### **Full-Stack Development**
1. **Backend:** Python/FastAPI or Node.js/Express on port 8000/3000
2. **Frontend:** React/Vue development server on port 3000
3. **Database:** PostgreSQL/MySQL clients for database connections
4. **Code Editor:** VS Code Server on port 8080
5. **Monitoring:** Apache server-status for performance metrics

### **Data Science Workflow**
1. **Environment:** JupyterLab on port 8888
2. **Libraries:** pandas, numpy, matplotlib pre-installed
3. **Databases:** SQLite, PostgreSQL, MySQL clients available
4. **Version Control:** Git with GitHub CLI integration

### **DevOps Workflow**
1. **Infrastructure:** Terraform for provisioning
2. **Configuration:** Ansible for automation
3. **Containers:** Docker-in-Docker for containerization
4. **Orchestration:** kubectl and helm for Kubernetes
5. **Cloud:** AWS CLI and Azure CLI for cloud operations

## 📝 Build Information

- **Base Image:** AlmaLinux 9 (Sage Margay)
- **Architecture:** Multi-arch (amd64/arm64)
- **Container Size:** ~2.5GB (includes all tools and dependencies)
- **Build Time:** ~15-20 minutes (depending on network)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test the build: `docker-compose build`
5. Submit a pull request

## 📄 License

This project is open source and available under the MIT License.

## 🔗 Related Projects

- [AlmaLinux](https://almalinux.org/) - Enterprise Linux distribution
- [Docker](https://docker.com/) - Containerization platform
- [VS Code Server](https://github.com/coder/code-server) - Browser-based VS Code

---

**Built with ❤️ for developers who want a complete, ready-to-use development environment!**