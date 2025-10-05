FROM almalinux:9

# Update system and install basic tools
RUN dnf update -y && \
    dnf install -y \
    epel-release \
    dnf-plugins-core && \
    dnf config-manager --set-enabled crb

# Install essential tools and development packages
RUN dnf install -y --allowerasing \
    git \
    wget \
    rsync \
    python3 \
    python3-pip \
    python3-devel \
    sqlite \
    sqlite-devel \
    nodejs \
    npm \
    curl \
    unzip \
    which \
    procps-ng \
    tar \
    gzip \
    sudo \
    # Build tools
    gcc \
    gcc-c++ \
    make \
    cmake \
    automake \
    autoconf \
    libtool \
    # Version control and diff tools
    subversion \
    mercurial \
    patch \
    diffutils \
    # Network tools
    net-tools \
    iproute \
    iputils \
    bind-utils \
    tcpdump \
    nmap \
    telnet \
    openssh-clients \
    # System monitoring
    htop \
    iotop \
    sysstat \
    lsof \
    strace \
    # Text processing
    vim \
    nano \
    jq \
    xmlstarlet \
    # Archive tools
    bzip2 \
    xz \
    zip \
    p7zip \
    # Automation tools
    expect \
    # Text formatting tools
    figlet \
    toilet \
    # Web server components
    httpd \
    php \
    php-fpm \
    php-cli \
    php-common \
    php-devel \
    php-json \
    php-mbstring \
    php-mysqlnd \
    php-pdo \
    php-pgsql \
    php-xml \
    php-gd \
    php-curl \
    php-zip \
    php-opcache \
    # Development libraries
    openssl-devel \
    libffi-devel \
    zlib-devel \
    bzip2-devel \
    readline-devel \
    ncurses-devel \
    libxml2-devel \
    libxslt-devel \
    libyaml-devel \
    && dnf clean all

# Install rclone
RUN curl https://rclone.org/install.sh | bash

# Install yq (detect architecture)
RUN ARCH=$(uname -m) && \
    if [ "$ARCH" = "x86_64" ]; then ARCH="amd64"; elif [ "$ARCH" = "aarch64" ]; then ARCH="arm64"; fi && \
    wget https://github.com/mikefarah/yq/releases/latest/download/yq_linux_${ARCH} -O /usr/bin/yq && \
    chmod +x /usr/bin/yq

# Install GAM (Google Apps Manager)
RUN bash <(curl -s -S -L https://git.io/install-gam) -l

# Install Claude CLI
RUN npm install -g @anthropic-ai/claude-code

# Install Claude Flow (alpha version for orchestration)
RUN npm install -g claude-flow@alpha

# Install Gemini CLI using Python pip
RUN python3 -m pip install google-generativeai-cli || \
    python3 -m pip install google-generativeai

# Install code-server using install script
RUN curl -fsSL https://code-server.dev/install.sh | sh

# Install OpenCode AI
RUN curl -fsSL https://opencode.ai/install | bash

# Install Docker CLI and docker-compose
RUN dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo && \
    dnf install -y docker-ce-cli docker-compose-plugin && \
    dnf clean all

# Install kubectl
RUN ARCH=$(uname -m) && \
    if [ "$ARCH" = "x86_64" ]; then ARCH="amd64"; elif [ "$ARCH" = "aarch64" ]; then ARCH="arm64"; fi && \
    curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/${ARCH}/kubectl" && \
    chmod +x kubectl && \
    mv kubectl /usr/local/bin/

# Install Helm
RUN curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash

# Install modern CLI tools
RUN dnf install -y \
    ripgrep \
    fd-find \
    bat \
    && dnf clean all

# Install exa (now eza) from binary
RUN ARCH=$(uname -m) && \
    if [ "$ARCH" = "x86_64" ]; then ARCH="x86_64"; elif [ "$ARCH" = "aarch64" ]; then ARCH="aarch64"; fi && \
    wget https://github.com/eza-community/eza/releases/latest/download/eza_${ARCH}-unknown-linux-gnu.tar.gz && \
    tar -xzf eza_${ARCH}-unknown-linux-gnu.tar.gz && \
    mv eza /usr/local/bin/ && \
    rm eza_${ARCH}-unknown-linux-gnu.tar.gz

# Install fzf
RUN git clone --depth 1 https://github.com/junegunn/fzf.git /opt/fzf && \
    /opt/fzf/install --all --no-bash --no-fish

# Install database clients
RUN dnf install -y \
    postgresql \
    mysql \
    redis \
    && dnf clean all

# Install Go (detect architecture)
RUN ARCH=$(uname -m) && \
    if [ "$ARCH" = "x86_64" ]; then ARCH="amd64"; elif [ "$ARCH" = "aarch64" ]; then ARCH="arm64"; fi && \
    wget https://go.dev/dl/go1.22.0.linux-${ARCH}.tar.gz && \
    tar -C /usr/local -xzf go1.22.0.linux-${ARCH}.tar.gz && \
    rm go1.22.0.linux-${ARCH}.tar.gz

# Install Rust
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y

# Set up Python environment
RUN python3 -m pip install --upgrade pip setuptools wheel

# Install Python development tools
RUN python3 -m pip install \
    pipenv \
    poetry \
    virtualenv \
    black \
    flake8 \
    pylint \
    pytest \
    requests \
    pandas \
    numpy \
    matplotlib \
    jupyterlab \
    ipython \
    ansible \
    awscli

# Install Azure CLI separately
RUN rpm --import https://packages.microsoft.com/keys/microsoft.asc && \
    echo -e "[azure-cli]\nname=Azure CLI\nbaseurl=https://packages.microsoft.com/yumrepos/azure-cli\nenabled=1\ngpgcheck=1\ngpgkey=https://packages.microsoft.com/keys/microsoft.asc" > /etc/yum.repos.d/azure-cli.repo && \
    dnf install -y azure-cli && \
    dnf clean all

# Configure Apache and PHP-FPM
RUN echo "ServerName localhost" >> /etc/httpd/conf/httpd.conf && \
    echo "Listen 443" >> /etc/httpd/conf/httpd.conf

# Configure server-info and server-status
RUN echo '# Enable server-info and server-status' > /etc/httpd/conf.d/server-info-status.conf && \
    echo '<Location "/server-info">' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    SetHandler server-info' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    Require local' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    Require ip 172.16.0.0/12' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    Require ip 192.168.0.0/16' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    Require ip 10.0.0.0/8' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '</Location>' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '<Location "/server-status">' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    SetHandler server-status' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    Require local' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    Require ip 172.16.0.0/12' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    Require ip 192.168.0.0/16' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '    Require ip 10.0.0.0/8' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '</Location>' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo '# Extended server-status' >> /etc/httpd/conf.d/server-info-status.conf && \
    echo 'ExtendedStatus On' >> /etc/httpd/conf.d/server-info-status.conf

# Configure PHP-FPM
RUN sed -i 's/^user = apache/user = developer/' /etc/php-fpm.d/www.conf && \
    sed -i 's/^group = apache/group = developer/' /etc/php-fpm.d/www.conf && \
    sed -i 's/^listen = 127.0.0.1:9000/listen = \/run\/php-fpm\/www.sock/' /etc/php-fpm.d/www.conf && \
    sed -i 's/^;listen.owner = nobody/listen.owner = apache/' /etc/php-fpm.d/www.conf && \
    sed -i 's/^;listen.group = nobody/listen.group = apache/' /etc/php-fpm.d/www.conf && \
    sed -i 's/^;listen.mode = 0660/listen.mode = 0660/' /etc/php-fpm.d/www.conf

# Create Apache configuration for PHP-FPM
RUN echo '<IfModule mod_proxy.c>' > /etc/httpd/conf.d/php-fpm.conf && \
    echo '    ProxyPassMatch ^/(.*\.php(/.*)?)$ unix:/run/php-fpm/www.sock|fcgi://localhost/var/www/html' >> /etc/httpd/conf.d/php-fpm.conf && \
    echo '</IfModule>' >> /etc/httpd/conf.d/php-fpm.conf && \
    echo 'DirectoryIndex index.php index.html' >> /etc/httpd/conf.d/php-fpm.conf

# Create a simple PHP info page
RUN echo '<?php phpinfo(); ?>' > /var/www/html/info.php && \
    echo '<h1>Welcome to AlmaLinux Development Container</h1><p><a href="info.php">PHP Info</a></p>' > /var/www/html/index.html

# Set proper permissions
RUN chown -R apache:apache /var/www/html && \
    mkdir -p /run/php-fpm && \
    chown apache:apache /run/php-fpm

# Create colorful MOTD
RUN toilet --gay "BayaDev" > /etc/motd && \
    echo "" >> /etc/motd && \
    echo "🚀 AlmaLinux 9 Development Container" >> /etc/motd && \
    echo "🔧 Loaded with: Python, Node.js, Go, Rust, Java, PHP, Apache" >> /etc/motd && \
    echo "🛠️  Tools: Docker, K8s, Terraform, AWS CLI, Azure CLI" >> /etc/motd && \
    echo "🎯 Ready for development!" >> /etc/motd && \
    echo "" >> /etc/motd

# Create a non-root user
RUN useradd -m -s /bin/bash developer && \
    echo "developer ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

# Set working directory
WORKDIR /home/developer

# Copy Claude Flow setup script
COPY --chown=developer:developer setup-claude-flow.sh /home/developer/
RUN chmod +x /home/developer/setup-claude-flow.sh

# Copy Claude configuration
COPY --chown=developer:developer .claude /home/developer/.claude

# Switch to non-root user
USER developer

# Install Java
USER root
RUN dnf install -y java-17-openjdk java-17-openjdk-devel maven && \
    dnf clean all

# Install Node.js tools
RUN npm install -g \
    yarn \
    pnpm \
    typescript \
    ts-node \
    nodemon \
    pm2 \
    eslint \
    prettier

# Install tmux and screen
RUN dnf install -y tmux screen && \
    dnf clean all

# Setup shell enhancements
RUN git clone https://github.com/ohmyzsh/ohmyzsh.git /opt/oh-my-zsh && \
    cp /opt/oh-my-zsh/templates/zshrc.zsh-template /etc/skel/.zshrc && \
    dnf install -y zsh && \
    dnf clean all

# Install terraform
RUN wget https://releases.hashicorp.com/terraform/1.7.0/terraform_1.7.0_linux_amd64.zip && \
    unzip terraform_1.7.0_linux_amd64.zip && \
    mv terraform /usr/local/bin/ && \
    rm terraform_1.7.0_linux_amd64.zip

# Install GitHub CLI
RUN dnf install -y gh && \
    dnf clean all

# Switch back to developer user
USER developer

# Set environment variables
ENV PATH="/home/developer/.local/bin:/usr/local/go/bin:/home/developer/.cargo/bin:${PATH}"
ENV GOPATH="/home/developer/go"
ENV JAVA_HOME="/usr/lib/jvm/java-17-openjdk"
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

# Expose ports for code-server
EXPOSE 8080

# Default command
CMD ["/bin/bash"]