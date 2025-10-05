#!/bin/bash
# Claude Flow Setup Script for AlmaLinux Dev Container

echo "🚀 Setting up Claude Flow in AlmaLinux Dev Container"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running inside container
if [ -f /.dockerenv ]; then
    print_status "Running inside Docker container"
else
    print_warning "Not running inside Docker container. This script is designed for the AlmaLinux dev container."
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Create Claude Flow directory structure
print_status "Creating Claude Flow directory structure..."
mkdir -p ~/claude-flow/{memory,coordination,projects}
mkdir -p ~/claude-flow/memory/{sessions,agents}
mkdir -p ~/claude-flow/coordination/{memory_bank,orchestration,subtasks}

# Create Claude Flow configuration
print_status "Creating Claude Flow configuration..."
cat > ~/claude-flow/claude-flow.config.json << 'EOF'
{
  "features": {
    "autoTopologySelection": true,
    "parallelExecution": true,
    "neuralTraining": true,
    "bottleneckAnalysis": true,
    "smartAutoSpawning": true,
    "selfHealingWorkflows": true,
    "crossSessionMemory": true,
    "githubIntegration": true
  },
  "performance": {
    "maxAgents": 10,
    "defaultTopology": "hierarchical",
    "executionStrategy": "parallel",
    "tokenOptimization": true,
    "cacheEnabled": true,
    "telemetryLevel": "detailed"
  },
  "container": {
    "environment": "almalinux-dev",
    "workspaceDir": "/home/developer/workspace",
    "memoryDir": "/home/developer/claude-flow/memory",
    "coordinationDir": "/home/developer/claude-flow/coordination"
  }
}
EOF

# Create CLAUDE.md for the container
print_status "Creating CLAUDE.md configuration..."
cat > ~/CLAUDE.md << 'EOF'
# Claude Code Configuration - AlmaLinux Dev Container

## Container Environment
This is the AlmaLinux 9 development container with Claude Flow orchestration enabled.

## Available Tools
- **Claude CLI**: `claude` - Direct Claude Code interface
- **Claude Flow**: `npx claude-flow@alpha` - Orchestration and swarm coordination
- **Development**: Python, Node.js, Go, Rust, Java, PHP
- **DevOps**: Docker, Kubernetes, Terraform, Ansible
- **Cloud**: AWS CLI, Azure CLI, GAM

## Claude Flow Commands
- `npx claude-flow@alpha sparc modes` - List SPARC development modes
- `npx claude-flow@alpha sparc run <mode> "<task>"` - Execute SPARC mode
- `npx claude-flow@alpha sparc tdd "<feature>"` - Run TDD workflow
- `npx claude-flow@alpha swarm init` - Initialize swarm coordination
- `npx claude-flow@alpha hooks pre-task` - Pre-task coordination
- `npx claude-flow@alpha memory list` - View stored memories

## Container-Specific Paths
- Workspace: `/home/developer/workspace`
- Claude Flow: `/home/developer/claude-flow`
- Web Root: `/var/www/html`
- Projects: `/home/developer/projects`

## Quick Start
1. Initialize a swarm: `npx claude-flow@alpha swarm init --topology mesh --max-agents 5`
2. Start a project: `npx claude-flow@alpha sparc tdd "create REST API"`
3. Check status: `npx claude-flow@alpha swarm status`

## Performance Tips
- Use parallel execution for multi-file operations
- Enable caching for repeated searches
- Leverage swarm coordination for complex tasks
- Store context in memory for cross-session persistence
EOF

# Create initialization script
print_status "Creating Claude Flow initialization script..."
cat > ~/claude-flow-init.sh << 'EOF'
#!/bin/bash
# Initialize Claude Flow for new sessions

echo "🐝 Initializing Claude Flow..."

# Check if Claude Flow is installed
if ! command -v claude-flow &> /dev/null; then
    echo "Installing Claude Flow..."
    npm install -g claude-flow@alpha
fi

# Restore previous session if exists
if [ -f ~/claude-flow/memory/sessions/latest.json ]; then
    echo "Restoring previous session..."
    npx claude-flow@alpha hooks session-restore --session-id latest --load-memory true
fi

# Display status
echo "✅ Claude Flow ready!"
echo "Run 'npx claude-flow@alpha swarm status' to check swarm status"
echo "Run 'npx claude-flow@alpha sparc modes' to see available SPARC modes"
EOF

chmod +x ~/claude-flow-init.sh

# Create example project template
print_status "Creating example project template..."
mkdir -p ~/claude-flow/projects/example-api
cat > ~/claude-flow/projects/example-api/README.md << 'EOF'
# Example API Project

This is a template for starting a new API project with Claude Flow.

## Quick Start

```bash
# Initialize swarm for API development
npx claude-flow@alpha swarm init --topology hierarchical --max-agents 6

# Start SPARC TDD development
npx claude-flow@alpha sparc tdd "create user authentication API with JWT"

# Monitor progress
npx claude-flow@alpha swarm monitor
```

## Project Structure
```
api/
├── src/
│   ├── models/
│   ├── routes/
│   ├── middleware/
│   └── services/
├── tests/
├── docs/
└── config/
```
EOF

# Add Claude Flow to bashrc
print_status "Adding Claude Flow to shell configuration..."
if ! grep -q "claude-flow-init" ~/.bashrc; then
    echo "" >> ~/.bashrc
    echo "# Claude Flow initialization" >> ~/.bashrc
    echo "[ -f ~/claude-flow-init.sh ] && source ~/claude-flow-init.sh" >> ~/.bashrc
fi

# Test Claude Flow installation
print_status "Testing Claude Flow installation..."
if npx claude-flow@alpha --version &> /dev/null; then
    print_success "Claude Flow is installed and ready!"
    npx claude-flow@alpha --version
else
    print_warning "Claude Flow installation needs to be completed. Run: npm install -g claude-flow@alpha"
fi

print_success "Claude Flow setup complete!"
echo ""
echo "🎯 Next steps:"
echo "1. Restart your shell or run: source ~/.bashrc"
echo "2. Initialize a swarm: npx claude-flow@alpha swarm init"
echo "3. Start development: npx claude-flow@alpha sparc tdd \"your task\""
echo ""
echo "📚 Documentation: https://github.com/ruvnet/claude-flow"