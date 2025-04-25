#!/bin/bash

LOG_DIR="/var/log/DistributedLogging"
LOG_FILE="$LOG_DIR/DistributedInvestZero.log"
LOG_ERR="$LOG_DIR/DistributedInvestZero.err"

mkdir -p "$LOG_DIR"
touch "$LOG_FILE" "$LOG_ERR"

# Set permissions to allow writing by anyone
chmod 777 "$LOG_DIR"
chmod 666 "$LOG_FILE" "$LOG_ERR"

echo "Log files and directory created with world-writable permissions at $LOG_DIR"
