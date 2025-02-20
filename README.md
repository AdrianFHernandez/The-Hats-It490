The-Hats-It490/
│── frontend/              # React Frontend
│   ├── src/
│   ├── public/
│   ├── build/             # Production Build 
│   ├── package.json
│   └── ...
│
│── backend/               # PHP Backend
│   ├── public/            # Publicly accessible files (like index.php)
│   ├── api/               # API Endpoints (register.php, login.php, etc.)
│   ├── config/            # Configuration files (db.php, rabbitmq.php)
│   ├── vendor/            # Dependencies (from Composer)
│   ├── database/          # SQL migration files, DB initialization
│   ├── scripts/           # RabbitMQ Listener (database_listener.py)
│   ├── .env               # Environment variables (DB credentials)
│   ├── composer.json      # PHP dependency manager (for RabbitMQ)
│   └── ...