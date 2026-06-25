#!/bin/bash
set -e

# Trivia Platform Deployment Script
# This script compiles the React application using standard Vite defaults.
# The production server should be configured to serve from the 'dist/' directory.

echo "=== Starting Trivia Platform Build ==="

# 1. Ensure Node.js and npm are on the PATH
if [ -d "$HOME/.local/bin" ]; then
    export PATH="$HOME/.local/bin:$PATH"
fi

# Detect system Node.js, otherwise attempt to use local download
NODE_TEMP_DIR="$PWD/node-temp-dist"
USING_SYSTEM_NODE=true

if ! command -v node &> /dev/null || ! command -v npm &> /dev/null; then
    echo "Node.js/npm not found on system PATH. Attempting automatic local installation..."
    USING_SYSTEM_NODE=false

    OS="$(uname -s)"
    ARCH="$(uname -m)"
    echo "Detected system: $OS ($ARCH)"

    if [ "$OS" = "Linux" ] && [ "$ARCH" = "x86_64" ]; then
        NODE_URL="https://nodejs.org/dist/v22.12.0/node-v22.12.0-linux-x64.tar.xz"
        EXT="tar.xz"
    elif [ "$OS" = "Linux" ] && { [ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ]; }; then
        NODE_URL="https://nodejs.org/dist/v22.12.0/node-v22.12.0-linux-arm64.tar.xz"
        EXT="tar.xz"
    elif [ "$OS" = "Darwin" ] && [ "$ARCH" = "arm64" ]; then
        NODE_URL="https://nodejs.org/dist/v22.12.0/node-v22.12.0-darwin-arm64.tar.gz"
        EXT="tar.gz"
    elif [ "$OS" = "Darwin" ] && [ "$ARCH" = "x86_64" ]; then
        NODE_URL="https://nodejs.org/dist/v22.12.0/node-v22.12.0-darwin-x64.tar.gz"
        EXT="tar.gz"
    else
        echo "Error: Unsupported system combination: $OS $ARCH. Cannot install Node.js automatically."
        exit 1
    fi

    mkdir -p "$NODE_TEMP_DIR"
    echo "Downloading precompiled Node.js binary from $NODE_URL..."

    if command -v curl &> /dev/null; then
        curl -L -o "$NODE_TEMP_DIR/node-bin.$EXT" "$NODE_URL"
    elif command -v wget &> /dev/null; then
        wget -O "$NODE_TEMP_DIR/node-bin.$EXT" "$NODE_URL"
    else
        echo "Error: Neither curl nor wget is installed. Cannot download Node.js automatically."
        rm -rf "$NODE_TEMP_DIR"
        exit 1
    fi

    echo "Extracting binary archive..."
    tar -xf "$NODE_TEMP_DIR/node-bin.$EXT" -C "$NODE_TEMP_DIR" --strip-components=1
    rm -f "$NODE_TEMP_DIR/node-bin.$EXT"

    export PATH="$NODE_TEMP_DIR/bin:$PATH"
fi

echo "Node.js version: $(node -v)"
echo "npm version: $(npm -v)"

# 2. Install dependencies
echo "Installing npm dependencies..."
npm install

# 3. Build production bundle (outputs to 'dist/')
echo "Compiling Vite production bundle (outputs to dist/)..."
npm run build

# 4. Set database and telemetry file permissions
echo "Setting database and telemetry file permissions..."
# Ensure files exist in the root (outside of dist/)
touch data_user_progress.json data_telemetry.json
chmod 666 data_questions.json data_user_progress.json data_telemetry.json

# 5. Clean up local Node.js binaries if they were downloaded
if [ "$USING_SYSTEM_NODE" = false ]; then
    echo "Removing temporary Node.js files..."
    rm -rf "$NODE_TEMP_DIR"
fi

echo "=== Build Completed Successfully! ==="
echo "Configure your web server (e.g. Nginx or Apache) to serve from '/var/www/train/dist'."
