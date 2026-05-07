# Docker Setup

## Quick Start

1. Clone both repositories side by side:
```bash
git clone <backend-repo-url> aventra-booking-system-backend
git clone <frontend-repo-url> aventra-booking-system-ui
cd aventra-booking-system-backend
```

2. Create `.env` file:
```bash
cp .env.example .env
```

3. Start all services:
```bash
docker-compose up --build -d
```

4. Access the application:
- Frontend: http://localhost:3000
- Backend API: http://localhost:5500

## Demo Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@swett.com | Swett2025!Super |
| Admin | admin@swett.com | Swett2025!Admin |
| Manager | manager@swett.com | Swett2025!Admin |
| Support | support@swett.com | Swett2025!Support |
| Accountant | accountant@swett.com | Swett2025!Finance |
| Developer | developer@swett.com | Swett2025!Dev |
| Customer | guest@swett.com | Swett2025!Guest |

## Services

- **MySQL**: Port 3306
- **Backend API**: Port 5500
- **Frontend**: Port 3000

## Troubleshooting

If you get "Email or password incorrect", ensure database is initialized:
```bash
docker logs aventra-mysql
```
