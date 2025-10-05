#!/usr/bin/env python3
"""
Claude Flow - Alternative AI Provider Configuration
Configure Claude Flow to use Gemini Pro or ChatGPT instead of Claude API
"""

import os
import json
import subprocess
import sys

def create_gemini_wrapper():
    """Create a wrapper script that makes Gemini CLI work like Claude CLI"""
    wrapper_content = '''#!/usr/bin/env python3
import sys
import os
import google.generativeai as genai
import json

def main():
    # Get the prompt from command line args
    if len(sys.argv) < 2:
        print("Usage: claude-gemini-wrapper '<prompt>'")
        sys.exit(1)
    
    prompt = " ".join(sys.argv[1:])
    
    # Configure Gemini (you'll need to set your API key)
    api_key = os.getenv('GEMINI_API_KEY')
    if not api_key:
        print("Error: Please set GEMINI_API_KEY environment variable")
        sys.exit(1)
    
    genai.configure(api_key=api_key)
    model = genai.GenerativeModel('gemini-pro')
    
    try:
        # Generate response
        response = model.generate_content(prompt)
        
        # Output in Claude-compatible format
        output = {
            "type": "assistant", 
            "content": response.text,
            "model": "gemini-pro"
        }
        print(json.dumps(output))
    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()
'''
    
    with open('/home/developer/claude-gemini-wrapper', 'w') as f:
        f.write(wrapper_content)
    
    os.chmod('/home/developer/claude-gemini-wrapper', 0o755)
    print("✅ Gemini wrapper created at /home/developer/claude-gemini-wrapper")

def create_chatgpt_wrapper():
    """Create a wrapper for ChatGPT CLI"""
    wrapper_content = '''#!/bin/bash
# ChatGPT CLI wrapper for Claude Flow

if [ -z "$OPENAI_API_KEY" ]; then
    echo "Error: Please set OPENAI_API_KEY environment variable"
    exit 1
fi

# Use chatgpt CLI to send the prompt
chatgpt "$@"
'''
    
    with open('/home/developer/claude-chatgpt-wrapper', 'w') as f:
        f.write(wrapper_content)
    
    os.chmod('/home/developer/claude-chatgpt-wrapper', 0o755)
    print("✅ ChatGPT wrapper created at /home/developer/claude-chatgpt-wrapper")

def configure_claude_flow_for_alternative(provider="gemini"):
    """Configure Claude Flow to use alternative AI provider"""
    
    config_file = '/home/developer/claude-flow/claude-flow.config.json'
    
    # Read existing config
    try:
        with open(config_file, 'r') as f:
            config = json.load(f)
    except FileNotFoundError:
        config = {}
    
    # Add alternative provider configuration
    config['ai_provider'] = {
        "type": provider,
        "wrapper_script": f"/home/developer/claude-{provider}-wrapper",
        "fallback_to_claude": True
    }
    
    # Write updated config
    with open(config_file, 'w') as f:
        json.dump(config, f, indent=2)
    
    print(f"✅ Claude Flow configured to use {provider}")

def create_env_template():
    """Create environment template for API keys"""
    env_content = '''# AI API Keys Configuration
# Copy this to ~/.bashrc or source it before using Claude Flow

# For Gemini Pro (Google AI Studio)
export GEMINI_API_KEY="your_gemini_api_key_here"

# For ChatGPT (OpenAI)
export OPENAI_API_KEY="your_openai_api_key_here"

# Optional: Set preferred AI provider
export CLAUDE_FLOW_PROVIDER="gemini"  # or "chatgpt"
'''
    
    with open('/home/developer/ai-keys-template.sh', 'w') as f:
        f.write(env_content)
    
    print("✅ API key template created at /home/developer/ai-keys-template.sh")

def main():
    print("🔧 Configuring Claude Flow for Alternative AI Providers")
    print("=" * 55)
    
    # Create wrappers
    create_gemini_wrapper()
    create_chatgpt_wrapper()
    
    # Configure for Gemini by default
    configure_claude_flow_for_alternative("gemini")
    
    # Create environment template
    create_env_template()
    
    print("\n🎯 Configuration Complete!")
    print("\n📋 Next Steps:")
    print("1. Get your API keys:")
    print("   • Gemini Pro: https://makersuite.google.com/app/apikey")
    print("   • ChatGPT: https://platform.openai.com/api-keys")
    print("\n2. Set your API key:")
    print("   export GEMINI_API_KEY='your_key_here'")
    print("   # or")
    print("   export OPENAI_API_KEY='your_key_here'")
    print("\n3. Test the wrapper:")
    print("   /home/developer/claude-gemini-wrapper 'Hello, world!'")
    print("\n4. Use with Claude Flow:")
    print("   npx claude-flow@alpha sparc tdd 'create API' --provider gemini")

if __name__ == "__main__":
    main()