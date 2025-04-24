#!/bin/bash

LOG_DIR="/var/log/DistributedLogging"
LOG_FILE="$LOG_DIR/DistributedInvestZero.log"
LOG_ERR="$LOG_DIR/DistributedInvestZero.err"

mkdir -p "$LOG_DIR"
touch "$LOG_FILE" "$LOG_ERR"

# Set permissions
chmod 775 "$LOG_DIR"
chmod 664 "$LOG_FILE" "$LOG_ERR"

echo "Log files and directory created at $LOG_DIR"

