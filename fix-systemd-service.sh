#!/bin/bash
# Update systemd service to include environment variables

sudo tee /etc/systemd/system/proyecto-asojuntas-queue.service > /dev/null <<'EOF'
[Unit]
Description=Proyecto Asojuntas Queue Worker
After=network.target

[Service]
User=administrador
Group=www-data
WorkingDirectory=/var/www/proyecto-asojuntas
EnvironmentFile=/var/www/proyecto-asojuntas/.env
Environment="EXTRACTOR_PYTHON_BIN=/var/www/proyecto-asojuntas/.venv/bin/python"
ExecStart=/usr/bin/php artisan queue:work database --queue=scrutiny-imports --sleep=3 --tries=4 --timeout=240
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

echo "Systemd service updated. Running daemon-reload..."
sudo systemctl daemon-reload
sudo systemctl restart proyecto-asojuntas-queue.service

echo "Service restarted. Checking status..."
systemctl status proyecto-asojuntas-queue.service --no-pager
