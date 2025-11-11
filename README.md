# Feedlot Management System

A comprehensive cattle feedlot management application with health tracking, feed management, and financial analytics.

## Features

- **Cattle Management**: Track cattle from purchase to sale with detailed records
- **Health Management**: Monitor cattle health with symptoms, treatments, and recovery tracking
- **Feed Management**: Manage raw materials, rations, and usage tracking
- **Sales Management**: Record sales with detailed financial information
- **Dashboard & Analytics**: Real-time metrics and KPIs with interactive charts
- **Authentication & Authorization**: Role-based access control (Admin, Manager, Operator)
- **Dark/Light Mode**: User preference theme support
- **Export Functionality**: Export reports in CSV, JSON, and PDF formats

## Prerequisites

- Node.js 18 or higher
- Docker and Docker Compose (for containerized deployment)

## Installation

### Option 1: Local Development

1. Install dependencies:
   ```bash
   npm install
   ```

2. Set up environment variables:
   ```bash
   cp .env.example .env
   # Edit .env with your database and auth configuration
   ```

3. Set up the database:
   ```bash
   npx prisma db push
   npx prisma generate
   ```

4. Run the development server:
   ```bash
   npm run dev
   ```

5. Open [http://localhost:3000](http://localhost:3000) in your browser

### Option 2: Docker Deployment

1. Build and start the services:
   ```bash
   docker-compose up --build
   ```

2. The application will be available at [http://localhost:3000](http://localhost:3000)

## Project Structure

```
src/
├── app/                 # Next.js 13+ app directory
│   ├── api/             # API routes
│   ├── auth/            # Authentication pages
│   ├── cattle/          # Cattle management pages
│   ├── dashboard/       # Dashboard page
│   ├── feed/            # Feed management pages
│   ├── health/          # Health management pages
│   ├── sales/           # Sales management pages
│   └── reports/         # Reports pages
├── components/          # Reusable React components
├── context/             # React context providers
├── lib/                 # Utility functions and database client
├── services/            # Business logic services
└── utils/               # Helper functions
```

## Scripts

- `npm run dev` - Start the development server
- `npm run build` - Build the application for production
- `npm run start` - Start the production server
- `npm run lint` - Run ESLint

## Database Schema

The application uses PostgreSQL with Prisma ORM. The main entities include:
- Cattle
- Purchases
- Inductions
- Weight Records
- Sales
- Health Records
- Raw Materials
- Rations
- Feed Usage
- Suppliers

## Authentication

The application uses NextAuth.js for authentication with role-based access control (Admin, Manager, Operator).

## Export Functionality

Reports can be exported in multiple formats:
- CSV
- JSON
- PDF (via browser print functionality)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is licensed under the MIT License.
