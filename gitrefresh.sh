#!/bin/bash

# --- Automatically Identify Git Repositories ---
REPOS=()
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

echo "🔎 Searching for Git repositories in $SCRIPT_DIR..."

# Iterate over all directories in the script's location
for dir in "$SCRIPT_DIR"/*/ ; do
    # Remove the trailing slash for cleaner directory name
    repo_path="${dir%/}"
    
    # Extract just the directory name
    repo_name="$(basename "$repo_path")"
    
    # Check if a .git directory exists inside the subdirectory
    if [ -d "$repo_path/.git" ]; then
        REPOS+=("$repo_name")
        echo "   ✅ Found repository: $repo_name"
    fi
done

if [ ${#REPOS[@]} -eq 0 ]; then
    echo "⚠️ No Git repositories found in the current directory. Exiting."
    exit 0
fi

echo "--- Found ${#REPOS[@]} repositories to process. ---"
echo "------------------------------------------------"

# --- Function to refresh a single Git directory ---
refresh_repo() {
    local repo_name="$1"
    local repo_path="$SCRIPT_DIR/$repo_name"
    
    echo ">>> Starting update for: $repo_name"
    # Navigate to the repository directory
    cd "$repo_path" || { echo "ERROR: Could not change directory to $repo_path"; exit 1; }

    # Check if the local branch is behind the remote
    changed=0
    # Update remotes silently, then check for "Your branch is behind"
    git remote update &> /dev/null 
    if git status -uno | grep -q 'Your branch is behind'; then
        changed=1
    fi

    if [ $changed -eq 1 ]; then
        echo "   --- Local branch is behind, pulling changes... ---"
        if git pull; then
            echo "   ✅ Updated $repo_name successfully."
            if [ -f "deploy.sh" ]; then
                echo "   🚀 Found deploy.sh in $repo_name. Checking permissions..."
                if [ ! -x "deploy.sh" ]; then
                    echo "   🔧 Making deploy.sh executable..."
                    chmod +x deploy.sh
                fi
                echo "   🏃 Executing deploy.sh..."
                ./deploy.sh
            fi
        else
            echo "   ❌ Failed to pull changes for $repo_name."
        fi
    else
        echo "   ☑️ $repo_name is already up-to-date."
    fi
    
    echo "----------------------------------------"
    # Return to the original script directory
    cd "$SCRIPT_DIR"
}

# --- Main loop to process the array ---
for directory in "${REPOS[@]}"; do
    refresh_repo "$directory"
done

echo "🎉 All identified repositories processed."