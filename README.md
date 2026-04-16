```markdown
# Online Exam System - Laravel Application

![Laravel](https://img.shields.io/badge/Laravel-12.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-12+-blue.svg)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.8-purple.svg)
![Pusher](https://img.shields.io/badge/Pusher-Real%20time%20chat-blue.svg)
![GitHub Stars](https://img.shields.io/github/stars/yourusername/online-exam-app-laravel?style=social)

# 🚀 Online Exam Application with Real-Time Monitoring

An advanced online exam system built with Laravel. It allows 100+ concurrent users. It features real-time monitoring capabilities, exam scheduling, question management, and comprehensive reporting.

## ✨ Key Features

- **Multi-role System**: Admin, Teacher, and Student roles with distinct permissions
- **Real-time Monitoring**: Teachers can monitor student progress in real-time using Pusher
- **Exam Management**: Create, publish, and archive exams with customizable settings
- **Question Bank**: Organize questions by subject and type (MCQ, short answer, etc.)
- **Secure Exam Environment**: Prevent cheating with session tracking and violation detection
- **Responsive UI**: Built with Bootstrap 5 for all device compatibility
- **Comprehensive Reporting**: Detailed results and analytics for all exam attempts
- **Exam Scheduling**: Set start and end times for exams
- **Violation Detection**: Automatic detection of suspicious behavior during exams
- **Admin Dashboard**: Complete control over users, subjects, questions, and exams

## 🛠️ Tech Stack

### Core Technologies
- **Framework**: Laravel 12
- **Frontend**: Bootstrap 5.3.8, Vite
- **Backend**: PHP 8.2+
- **Database**: PostgreSQL
- **Real-time**: Laravel Reverb
- **Authentication**: Built-in Laravel Auth

### Development Tools
- **Package Manager**: Composer
- **Build Tool**: Vite
- **Testing**: PHPUnit

## 📦 Installation

### Prerequisites

Before you begin, ensure you have the following installed:
- [PHP](https://www.php.net/downloads.php) 8.2+
- [Composer](https://getcomposer.org/download/)
- [Node.js](https://nodejs.org/) 18+
- [PostgreSQL](https://www.postgresql.org/download/) 12+
- [Git](https://git-scm.com/downloads)

### Quick Start

1. **Clone the repository**:
   ```bash
    git clone https://github.com/yourusername/online-exam-app-laravel.git
    cd online-exam-app-laravel
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Set up environment**:
   ```bash
   cp .env.example .env
   ```

4. **Generate application key**:
   ```bash
   php artisan key:generate
   ```

5. **Configure database** in `.env` file:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=exam_system
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```

6. **Run migrations**:
   ```bash
   php artisan migrate
   ```

7. **Compile assets**:
   ```bash
   npm run dev
   ```

8. **Set up Pusher** (for real-time features):
   - Create a Pusher account at [pusher.com](https://pusher.com/)
   - Add your credentials to `.env`:
     ```env
     BROADCAST_DRIVER=pusher
     PUSHER_APP_ID=your_app_id
     PUSHER_APP_KEY=your_app_key
     PUSHER_APP_SECRET=your_app_secret
     PUSHER_APP_CLUSTER=your_cluster
     ```

9. **Start development server**:
   ```
   php artisan serve
   ```
```

## 🎯 Usage

### Basic Workflow

#### For Teachers:
1. **Create Subjects**: Organize your curriculum
2. **Add Questions**: Build a comprehensive question bank
3. **Create Exams**: Combine questions into exams with custom settings
4. **Schedule Exams**: Set start and end times
5. **Monitor Exams**: Watch student progress in real-time

#### For Students:
1. **Access Available Exams**: View exams that are open for participation
2. **Start Exams**: Begin your exam when scheduled
3. **Answer Questions**: Complete the exam within the time limit
4. **View Results**: Check your performance after completion
```

### Testing Credentials
```
**Admin**
Username: admin@examsystem.com
Password: admin123

**Teacher**
Username: teacher@examsystem.com
Password: teacher123

**Student**
Username: student@examsystem.com
Password: student123
```

## 📁 Project Structure

```
online-exam-app-laravel/
├── app/                  # Application source code
│   ├── Events/           # Event classes for real-time features
│   ├── Http/             # Controllers and middleware
│   ├── Models/           # Eloquent models
│   └── ...
├── database/             # Database migrations and seeders
├── resources/           # Views, languages, and assets
│   ├── views/            # Blade templates
│   ├── js/               # JavaScript files
│   └── css/              # CSS files
├── routes/               # Application routes
├── tests/                # Test cases
├── public/               # Publicly accessible files
├── config/               # Configuration files
├── vendor/               # Composer dependencies
├── .env.example          # Environment variables template
├── composer.json         # PHP dependencies
├── package.json          # JavaScript dependencies
└── README.md             # This file
```

## 🔧 Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure your environment:

```env
# Application settings
APP_NAME=Online Exam System
APP_ENV=local
APP_KEY=your-app-key
APP_DEBUG=true
APP_URL=http://localhost

# Database settings
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=exam_system
DB_USERNAME=root
DB_PASSWORD=Root@123

# Authentication
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Broadcasting (Pusher)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
```

### Customization Options

1. **Change the UI theme**: Modify the Tailwind CSS configuration in `resources/css/app.css`
2. **Adjust exam settings**: Modify the `Exam` model and related controllers
3. **Add new question types**: Extend the `Question` model and add new validation rules
4. **Change notification system**: Modify the event classes in `app/Events/`

## 🤝 Contributing

We welcome contributions from the community! Here's how you can help:

### Development Setup

1. Fork the repository
2. Clone your fork locally
3. Install dependencies:
   ```
   composer install
   npm install
   ```
4. Set up your environment and run migrations

### Code Style Guidelines

- Follow Laravel's coding standards
- Use PSR-12 for PHP code
- Keep Blade templates clean and organized
- Write comprehensive tests for new features

### Pull Request Process

1. Create a feature branch: `git checkout -b feature/your-feature`
2. Make your changes
3. Write tests for your changes
4. Commit your changes: `git commit -m 'Add some feature'`
5. Push to the branch: `git push origin feature/your-feature`
6. Open a pull request

## 📝 License

This project is open-sourced under the MIT License. See the [LICENSE](LICENSE) file for more information.

## 👥 Authors & Contributors

- **Maintainer**: Priyabrata Das
- **Contributors**: [List of contributors]

## 🐛 Issues & Support

### Reporting Issues

If you encounter any problems or have feature requests:

1. Check if the issue has already been reported
2. Open a new issue on GitHub with:
   - A clear title describing the issue
   - Detailed steps to reproduce
   - Expected behavior
   - Actual behavior
   - Any relevant error messages

### Getting Help

- Join our [Discord community](https://discord.gg/your-server)
- Ask questions on [Stack Overflow](https://stackoverflow.com) with the `laravel-exam` tag
- Check our [documentation](https://github.com/yourusername/online-exam-app-laravel/wiki)

## 🗺️ Roadmap

### Planned Features

- **Mobile App**: iOS and Android applications
- **Advanced Analytics**: Detailed exam performance reports
- **AI Grading**: Automated grading for certain question types
- **Collaborative Exams**: Group exam functionality
- **Exam Templates**: Reusable exam templates
- **Multi-language Support**: Localization for different languages

### Known Issues

- [Issue #1]: Some edge cases in real-time monitoring
- [Issue #2]: Mobile responsiveness in exam interface

## 🚀 Getting Started

Ready to get started? Follow these steps:

1. **Install the application** as described above
2. **Set up your database** with the provided migrations
3. **Create your first exam** and start testing
4. **Invite students** to participate in your exams
5. **Monitor progress** in real-time

Join us in building the future of online education with this powerful exam management system!
```

This README.md provides:

1. A compelling overview of the project
2. Clear installation instructions with multiple approaches
3. Practical usage examples with Blade code snippets
4. Comprehensive project structure documentation
5. Detailed configuration information
6. Contribution guidelines
7. Roadmap for future development
8. Professional formatting with badges and emojis
9. Clear sections for easy navigation
10. Practical information for developers and contributors

The README follows modern GitHub best practices and focuses on developer experience while maintaining a professional tone that encourages contributions.
