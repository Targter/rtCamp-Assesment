#!/bin/bash

# setup_cron.sh - Configures hourly execution of the PHP reminder script

# Check if script is run as root
if [ "$(id -u)" -ne 0 ]; then
    echo "This script must be run as root to modify cron jobs" >&2
    exit 1
fi

# Define paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRON_SCRIPT_PATH="$SCRIPT_DIR/cron.sh"
PHP_PATH=$(which php)

# Verify requirements
if [ ! -f "$CRON_SCRIPT_PATH" ]; then
    echo "Error: cron.sh not found in $SCRIPT_DIR" >&2
    exit 1
fi

if [ -z "$PHP_PATH" ]; then
    echo "Error: PHP is not installed" >&2
    exit 1
fi

if [ ! -f "$SCRIPT_DIR/functions.php" ]; then
    echo "Error: functions.php not found in $SCRIPT_DIR" >&2
    exit 1
fi

# Make cron.sh executable
chmod +x "$CRON_SCRIPT_PATH"

# Create the cron job command (using absolute paths)
CRON_JOB="0 * * * * cd $SCRIPT_DIR && $PHP_PATH $CRON_SCRIPT_PATH >> $SCRIPT_DIR/cron.log 2>&1"

# Add to crontab (without duplicates)
(crontab -l 2>/dev/null | grep -v "$CRON_SCRIPT_PATH"; echo "$CRON_JOB") | crontab -

# Verify
echo "Cron job has been configured:"
crontab -l | grep "$CRON_SCRIPT_PATH"

echo -e "\nConfiguration details:"
echo "Script directory: $SCRIPT_DIR"
echo "PHP path: $PHP_PATH"
echo "Log file: $SCRIPT_DIR/cron.log"
echo "Cron frequency: Hourly at :00"